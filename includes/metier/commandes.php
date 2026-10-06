<?php
/**
 * SAMA MAILLOT — commandes, stock, statuts et code de livraison (§12, §14, §17).
 *
 * Règles clés :
 *   - une commande par boutique, toutes créées dans UNE transaction (tout ou rien) ;
 *   - prix, stock et frais recalculés côté serveur ; stock verrouillé (SELECT … FOR UPDATE) :
 *     le premier client enregistré obtient la dernière pièce ;
 *   - stock décrémenté à la création, restitué à l'annulation ;
 *   - seules les transitions de la matrice §12.4 sont acceptées, chacune est historisée ;
 *   - code de livraison UNIQUEMENT pour le paiement en ligne (lot 8), quand la plateforme détient
 *     l'argent. En paiement à la livraison, le client paie le vendeur à la remise : pas de code
 *     (décision du porteur du projet, adaptation du §14.1) ; le vendeur marque la commande livrée.
 *
 * Le paiement en ligne (statut « attente_paiement ») arrive au lot 8 : seul le paiement à la
 * livraison est ouvert pour l'instant.
 */

declare(strict_types=1);

/** Statuts : [libellé, couleur d'état de la charte]. */
const STATUTS_COMMANDE = [
    'attente_paiement' => ['En attente de paiement', 'attention'],
    'attente_confirmation' => ['En attente de confirmation', 'attention'],
    'confirmee' => ['Confirmée', 'info'],
    'expediee' => ['Expédiée', 'info'],
    'livree' => ['Livrée', 'succes'],
    'recue' => ['Reçue', 'succes'],
    'en_litige' => ['En litige', 'erreur'],
    'annulee' => ['Annulée', 'erreur'],
    'remboursee' => ['Remboursée', 'info'],
];

/**
 * Matrice des transitions (§12.4) : statut actuel → [nouveau statut → rôles autorisés].
 * Les litiges (lot 7) et le paiement en ligne (lot 8) compléteront cette matrice.
 */
const TRANSITIONS_COMMANDE = [
    'attente_paiement' => [
        'attente_confirmation' => ['systeme'],   // paiement confirmé par l'agrégateur (§13.4)
        'annulee' => ['systeme', 'client'],      // paiement échoué, annulé ou expiré
    ],
    'attente_confirmation' => [
        'confirmee' => ['vendeur'],
        'annulee' => ['vendeur', 'client', 'systeme', 'admin'],
    ],
    'confirmee' => [
        'expediee' => ['vendeur'],
        'annulee' => ['vendeur', 'client', 'admin'],
    ],
    'expediee' => [
        'livree' => ['vendeur'],
        'recue' => ['client'],
        'annulee' => ['vendeur', 'admin'],   // uniquement « client injoignable / refus à la livraison » (§12.5)
        'en_litige' => ['client'],
    ],
    'livree' => [
        'recue' => ['client', 'systeme'],
        'en_litige' => ['client'],
    ],
    'recue' => [
        'en_litige' => ['client'],           // dans les 48 h qui suivent la réception (contrôlé par refus_reclamation)
    ],
    'en_litige' => [
        'recue' => ['client', 'admin'],      // réglé à l'amiable, ou décision de l'administration
        'remboursee' => ['admin'],           // paiement en ligne : réclamation fondée, client remboursé
    ],
    'annulee' => [
        'remboursee' => ['admin'],           // paiement en ligne : remboursement effectué
    ],
];

/** Statuts d'une commande « non terminée » (limite de 3 commandes à la livraison, §12.5). */
const STATUTS_COMMANDE_EN_COURS = ['attente_paiement', 'attente_confirmation', 'confirmee', 'expediee', 'livree'];

const CODE_LIVRAISON_ESSAIS_MAX = 5;

/** Erreur à montrer telle quelle au client (stock épuisé, zone non desservie…). */
class ErreurCommande extends RuntimeException
{
}

/* -------------------------------------------------------------------------
   Création des commandes
   ------------------------------------------------------------------------- */

/**
 * Peut-il encore commander en paiement à la livraison ? (§12.5) Renvoie null ou le motif du refus.
 */
function refus_paiement_livraison(array $client, int $nouvellesCommandes): ?string
{
    $refusMax = max(1, (int) parametre('refus_livraison_avant_blocage', '3'));
    if ((int) ($client['refus_livraison'] ?? 0) >= $refusMax) {
        return 'Le paiement à la livraison n\'est plus disponible sur votre compte (plusieurs refus à la livraison). '
            . (paiement_en_ligne_actif() ? 'Choisissez le paiement en ligne.' : 'Le paiement en ligne arrive très prochainement.');
    }

    $maximum = max(1, (int) parametre('commandes_livraison_simultanees', '3'));
    $place = implode(',', array_fill(0, count(STATUTS_COMMANDE_EN_COURS), '?'));
    $requete = bdd()->prepare(
        "SELECT COUNT(*) FROM commandes WHERE client_id = ? AND mode_paiement = 'livraison' AND statut IN ($place)"
    );
    $requete->execute([$client['id'], ...STATUTS_COMMANDE_EN_COURS]);
    $enCours = (int) $requete->fetchColumn();

    if ($enCours + $nouvellesCommandes > $maximum) {
        return 'Vous avez déjà ' . $enCours . ' commande(s) en paiement à la livraison en cours. Le maximum est de '
            . $maximum . ' à la fois : attendez la réception d\'une commande avant d\'en passer une nouvelle.';
    }

    return null;
}

/**
 * Crée les commandes (une par boutique), dans une transaction ; le stock est réservé tout de suite.
 *
 * Paiement à la livraison : « En attente de confirmation », vendeur prévenu, pas de code.
 * Paiement en ligne (§13) : « En attente de paiement », code de livraison chiffré, part du vendeur
 * calculée (total − frais de transaction) ; le vendeur n'est prévenu qu'une fois le paiement confirmé.
 *
 * @param array $client        compte connecté
 * @param array $articles      [['maillot_id', 'taille', 'flocage_nom', 'flocage_numero', 'quantite'], …]
 * @param array $adresse       ['zone_id', 'ville', 'quartier', 'repere', 'telephone']
 * @param array $modes         boutique_id => 'livraison' | 'retrait'
 * @param string $origine      'site' ou 'whatsapp'
 * @param string $modePaiement 'livraison' ou 'en_ligne'
 * @return list<array{id: int, numero: string, code: ?string, boutique_id: int, total: int}>
 * @throws ErreurCommande
 */
function creer_commandes(array $client, array $articles, array $adresse, array $modes, string $origine = 'site',
                         string $modePaiement = 'livraison'): array
{
    if ($articles === []) {
        throw new ErreurCommande('Votre panier est vide.');
    }
    $enLigne = $modePaiement === 'en_ligne';
    if ($enLigne && !paiement_en_ligne_actif()) {
        throw new ErreurCommande('Le paiement en ligne n\'est pas disponible pour le moment.');
    }
    $pourcentageFrais = max(0.0, (float) str_replace(',', '.', parametre('frais_transaction_pourcentage', '0')));

    $pdo = bdd();
    $pdo->beginTransaction();

    try {
        // 1. Relecture et VERROUILLAGE de chaque taille commandée (premier arrivé, premier servi)
        $lecture = $pdo->prepare(
            "SELECT m.id, m.titre, m.prix, m.flocage_propose, m.flocage_supplement, m.boutique_id,
                    (m.statut = 'publie' AND m.supprime_le IS NULL AND b.statut IN ('active', 'grace') AND b.supprime_le IS NULL) AS visible,
                    b.utilisateur_id AS vendeur_id, b.nom AS boutique_nom, b.retrait_sur_place, b.adresse_retrait,
                    t.id AS taille_id, t.stock,
                    (SELECT i.miniature FROM maillot_images i WHERE i.maillot_id = m.id ORDER BY i.ordre, i.id LIMIT 1) AS miniature
             FROM maillot_tailles t
             JOIN maillots m ON m.id = t.maillot_id
             JOIN boutiques b ON b.id = m.boutique_id
             WHERE t.maillot_id = :maillot AND t.taille = :taille
             FOR UPDATE"
        );

        $groupes = [];
        $demandeParTaille = [];

        foreach ($articles as $article) {
            $lecture->execute(['maillot' => $article['maillot_id'], 'taille' => $article['taille']]);
            $maillot = $lecture->fetch();

            if ($maillot === false || (int) $maillot['visible'] !== 1) {
                throw new ErreurCommande('Un maillot de votre panier n\'est plus en vente. Retirez-le puis réessayez.');
            }
            if ((int) $maillot['vendeur_id'] === (int) $client['id']) {
                throw new ErreurCommande('Vous ne pouvez pas acheter un maillot de votre propre boutique.');
            }

            $avecFlocage = $article['flocage_nom'] !== '';
            if ($avecFlocage && (int) $maillot['flocage_propose'] !== 1) {
                throw new ErreurCommande('Le vendeur ne propose plus le flocage pour « ' . $maillot['titre'] . ' ».');
            }

            $quantite = (int) $article['quantite'];
            $cleTaille = (int) $maillot['taille_id'];
            $demandeParTaille[$cleTaille] = ($demandeParTaille[$cleTaille] ?? 0) + $quantite;

            if ($quantite < 1 || $demandeParTaille[$cleTaille] > (int) $maillot['stock']) {
                throw new ErreurCommande((int) $maillot['stock'] <= 0
                    ? 'Ce maillot vient d\'être vendu : « ' . $maillot['titre'] . ' », taille ' . $article['taille'] . '.'
                    : 'Il ne reste que ' . $maillot['stock'] . ' « ' . $maillot['titre'] . ' » en taille ' . $article['taille'] . '.');
            }

            $prixUnitaire = (int) $maillot['prix'] + ($avecFlocage ? (int) $maillot['flocage_supplement'] : 0);
            $boutiqueId = (int) $maillot['boutique_id'];

            $groupes[$boutiqueId] ??= ['boutique' => $maillot, 'lignes' => [], 'sous_total' => 0];
            $groupes[$boutiqueId]['lignes'][] = [
                'maillot_id' => (int) $maillot['id'],
                'taille_id' => $cleTaille,
                'titre' => $maillot['titre'],
                'image' => $maillot['miniature'],
                'taille' => $article['taille'],
                'flocage_nom' => $article['flocage_nom'],
                'flocage_numero' => $article['flocage_numero'],
                'flocage_supplement' => $avecFlocage ? (int) $maillot['flocage_supplement'] : 0,
                'prix_unitaire' => $prixUnitaire,
                'quantite' => $quantite,
                'total_ligne' => $prixUnitaire * $quantite,
            ];
            $groupes[$boutiqueId]['sous_total'] += $prixUnitaire * $quantite;
        }

        // 2. Limites du paiement à la livraison (§12.5) ; le paiement en ligne n'y est pas soumis
        if (!$enLigne && ($motif = refus_paiement_livraison($client, count($groupes)))) {
            throw new ErreurCommande($motif);
        }

        // 3. Une commande par boutique
        $insertionCommande = $pdo->prepare(
            "INSERT INTO commandes (numero, client_id, boutique_id, origine, mode_paiement, statut, sous_total,
                                   frais_livraison, frais_a_convenir, total, frais_transaction, montant_vendeur,
                                   mode_livraison, zone_id, zone_nom, delai_livraison,
                                   client_nom, telephone_client, adresse_ville, adresse_quartier, adresse_repere,
                                   adresse_retrait, code_livraison_chiffre)
             VALUES (:numero, :client, :boutique, :origine, :mode_paiement, :statut, :sous_total,
                     :frais, :a_convenir, :total, :frais_transaction, :montant_vendeur,
                     :mode, :zone, :zone_nom, :delai,
                     :client_nom, :telephone, :ville, :quartier, :repere, :adresse_retrait, :code)"
        );
        $insertionLigne = $pdo->prepare(
            'INSERT INTO commande_lignes (commande_id, maillot_id, titre, image, taille, flocage_nom, flocage_numero,
                                          flocage_supplement, prix_unitaire, quantite, total_ligne)
             VALUES (:commande, :maillot, :titre, :image, :taille, :nom, :numero, :supplement, :prix, :quantite, :total)'
        );
        $baisseStock = $pdo->prepare('UPDATE maillot_tailles SET stock = stock - :quantite WHERE id = :id AND stock >= :quantite2');

        $creees = [];

        foreach ($groupes as $boutiqueId => $groupe) {
            $boutique = $groupe['boutique'];
            $mode = ($modes[$boutiqueId] ?? 'livraison') === 'retrait' ? 'retrait' : 'livraison';

            if ($mode === 'retrait') {
                if ((int) $boutique['retrait_sur_place'] !== 1) {
                    throw new ErreurCommande('« ' . $boutique['boutique_nom'] . ' » ne propose pas le retrait sur place.');
                }
                $tarif = ['tarif' => 0, 'delai' => 'Retrait sur place', 'zone_nom' => '', 'a_convenir' => false];
            } else {
                // Pas de tarif pour cette zone : la commande reste possible, frais fixés avec le vendeur.
                $tarif = tarif_boutique_zone($boutiqueId, (int) $adresse['zone_id'])
                    ?? ['tarif' => 0, 'delai' => 'À convenir avec le vendeur', 'zone_nom' => nom_zone((int) $adresse['zone_id']), 'a_convenir' => true];
                $tarif['a_convenir'] ??= false;
            }

            // Le paiement en ligne exige un montant connu d'avance : pas de frais « à convenir ».
            if ($enLigne && $tarif['a_convenir']) {
                throw new ErreurCommande('« ' . $boutique['boutique_nom'] . ' » n\'a pas de tarif de livraison pour votre zone : '
                    . 'le paiement en ligne est impossible pour cette commande. Choisissez le paiement à la livraison ou le retrait.');
            }

            // Code de livraison à 4 chiffres : paiement en ligne uniquement (§14.1).
            $code = $enLigne ? str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT) : null;
            $total = $groupe['sous_total'] + $tarif['tarif'];
            $fraisTransaction = $enLigne ? (int) round($total * $pourcentageFrais / 100) : 0;

            $insertionCommande->execute([
                'numero' => 'TMP-' . bin2hex(random_bytes(6)),
                'client' => $client['id'],
                'boutique' => $boutiqueId,
                'origine' => $origine === 'whatsapp' ? 'whatsapp' : 'site',
                'mode_paiement' => $enLigne ? 'en_ligne' : 'livraison',
                'statut' => $enLigne ? 'attente_paiement' : 'attente_confirmation',
                'sous_total' => $groupe['sous_total'],
                'frais' => $tarif['tarif'],
                'a_convenir' => $tarif['a_convenir'] ? 1 : 0,
                'total' => $total,
                'frais_transaction' => $fraisTransaction,
                'montant_vendeur' => $enLigne ? $total - $fraisTransaction : 0,
                'mode' => $mode,
                'zone' => $mode === 'livraison' ? (int) $adresse['zone_id'] : null,
                'zone_nom' => $tarif['zone_nom'],
                'delai' => $tarif['delai'],
                'client_nom' => $client['prenom'] . ' ' . $client['nom'],
                'telephone' => $adresse['telephone'],
                'ville' => $mode === 'livraison' ? $adresse['ville'] : '',
                'quartier' => $mode === 'livraison' ? $adresse['quartier'] : '',
                'repere' => $mode === 'livraison' ? $adresse['repere'] : '',
                'adresse_retrait' => $mode === 'retrait' ? (string) $boutique['adresse_retrait'] : '',
                'code' => $code !== null ? chiffrer($code) : null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $numero = sprintf('CMD-%s-%06d', date('Y'), $id);
            $pdo->prepare('UPDATE commandes SET numero = :numero WHERE id = :id')->execute(['numero' => $numero, 'id' => $id]);

            foreach ($groupe['lignes'] as $ligne) {
                $insertionLigne->execute([
                    'commande' => $id, 'maillot' => $ligne['maillot_id'], 'titre' => $ligne['titre'], 'image' => $ligne['image'],
                    'taille' => $ligne['taille'], 'nom' => $ligne['flocage_nom'], 'numero' => $ligne['flocage_numero'],
                    'supplement' => $ligne['flocage_supplement'], 'prix' => $ligne['prix_unitaire'],
                    'quantite' => $ligne['quantite'], 'total' => $ligne['total_ligne'],
                ]);
                $baisseStock->execute(['quantite' => $ligne['quantite'], 'id' => $ligne['taille_id'], 'quantite2' => $ligne['quantite']]);
                if ($baisseStock->rowCount() !== 1) {
                    throw new ErreurCommande('Ce maillot vient d\'être vendu : « ' . $ligne['titre'] . ' ».');
                }
            }

            historiser_statut($id, null, $enLigne ? 'attente_paiement' : 'attente_confirmation', 'client', (int) $client['id'],
                $origine === 'whatsapp' ? 'Commande via WhatsApp' : ($enLigne ? 'Paiement en ligne en cours' : null));

            $creees[] = ['id' => $id, 'numero' => $numero, 'code' => $code, 'boutique_id' => $boutiqueId,
                'total' => $total, 'vendeur_id' => (int) $boutique['vendeur_id'],
                'a_convenir' => $tarif['a_convenir']];
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    // Paiement en ligne : le vendeur n'est prévenu qu'après confirmation du paiement (paiements.php).
    if ($enLigne) {
        foreach ($creees as $commande) {
            journaliser_action('commande_creee', (int) $client['id'], 'commande', $commande['id'], null, 'en_ligne');
        }

        return $creees;
    }

    // 4. Après validation : SMS et journal (§22.2)
    foreach ($creees as $commande) {
        journaliser_action('commande_creee', (int) $client['id'], 'commande', $commande['id']);
        envoyer_sms($adresse['telephone'], 'SAMA MAILLOT : commande ' . $commande['numero'] . ' enregistrée ('
            . format_fcfa($commande['total']) . ($commande['a_convenir'] ? ' + livraison à convenir' : '')
            . '). Le vendeur va vous appeler pour organiser la livraison. Vous payez à la réception.');
        prevenir_vendeur($commande['vendeur_id'], 'SAMA MAILLOT : nouvelle commande ' . $commande['numero']
            . ' (' . format_fcfa($commande['total']) . ($commande['a_convenir'] ? ', frais de livraison à convenir avec le client' : '') . '). Confirmez-la dans votre espace vendeur sous 24 h.');

        // Notifications dans le site (cloche) : vendeur et client
        notifier($commande['vendeur_id'], 'commande_nouvelle', 'Nouvelle commande ' . $commande['numero'],
            $client['prenom'] . ' ' . $client['nom'] . ' a commandé pour ' . format_fcfa($commande['total'])
            . ($commande['a_convenir'] ? ' (+ livraison à convenir)' : '') . '. À confirmer sous 24 h.',
            'vendeur/commande.php?id=' . $commande['id']);
        notifier((int) $client['id'], 'commande_enregistree', 'Commande ' . $commande['numero'] . ' enregistrée',
            'Le vendeur doit la confirmer, puis il vous appellera pour la livraison.',
            'compte/commande.php?id=' . $commande['id']);
    }

    return $creees;
}

/**
 * Vérification SMS du téléphone avant la première commande (§12.1), si VERIFICATION_SMS est actif.
 * Le numéro du compte est utilisé ; à défaut, celui de l'adresse de livraison devient le numéro
 * du compte. Envoie le code puis ouvre la page de saisie ; $retour est la page à rouvrir ensuite.
 */
function demarrer_verification_telephone(array $utilisateur, string $telephoneAdresse, string $retour): never
{
    $telephone = $utilisateur['telephone'] ?? null;

    if ($telephone === null) {
        try {
            bdd()->prepare('UPDATE utilisateurs SET telephone = :telephone WHERE id = :id')
                ->execute(['telephone' => $telephoneAdresse, 'id' => $utilisateur['id']]);
            $telephone = $telephoneAdresse;
        } catch (PDOException) {
            message_flash('erreur', 'Ce numéro est déjà utilisé par un autre compte client. Indiquez un autre numéro.');
            rediriger('panier/commander.php');
        }
    }

    $code = creer_code_sms('verification_telephone', $telephone, (int) $utilisateur['id']);
    if ($code !== null) {
        envoyer_sms($telephone, "SAMA MAILLOT : votre code de vérification est $code. Il expire dans 10 minutes.");
    }
    $_SESSION['retour_apres_verification'] = $retour;
    message_flash('succes', 'Pour votre première commande, confirmez votre numéro avec le code reçu par SMS.');
    rediriger('inscription/verifier-telephone.php');
}

/** SMS au vendeur (numéro de son compte), s'il en a un. */
function prevenir_vendeur(int $vendeurId, string $message): void
{
    $requete = bdd()->prepare('SELECT telephone FROM utilisateurs WHERE id = :id');
    $requete->execute(['id' => $vendeurId]);
    $telephone = $requete->fetchColumn();

    if (is_string($telephone) && $telephone !== '') {
        envoyer_sms($telephone, $message, false);   // jamais affiché chez le client en local
    }
}

/* -------------------------------------------------------------------------
   Lecture
   ------------------------------------------------------------------------- */

/** Le vendeur ne voit une commande payée en ligne qu'une fois le paiement confirmé. */
const SQL_VISIBLE_VENDEUR = "NOT (c.mode_paiement = 'en_ligne' AND c.payee_le IS NULL)";

const SQL_COMMANDE = 'SELECT c.*, b.nom AS boutique_nom, b.slug AS boutique_slug, b.numero_whatsapp AS boutique_whatsapp,
        b.utilisateur_id AS vendeur_id
    FROM commandes c JOIN boutiques b ON b.id = c.boutique_id';

/** Commande d'un CLIENT (contrôle d'accès : jamais celle d'un autre). */
function commande_du_client(int $id, int $clientId): ?array
{
    $requete = bdd()->prepare(SQL_COMMANDE . ' WHERE c.id = :id AND c.client_id = :client');
    $requete->execute(['id' => $id, 'client' => $clientId]);
    $commande = $requete->fetch();

    return $commande === false ? null : $commande;
}

/** Commande reçue par une BOUTIQUE (contrôle d'accès vendeur). */
function commande_de_la_boutique(int $id, int $boutiqueId): ?array
{
    $requete = bdd()->prepare(SQL_COMMANDE . ' WHERE c.id = :id AND c.boutique_id = :boutique AND ' . SQL_VISIBLE_VENDEUR);
    $requete->execute(['id' => $id, 'boutique' => $boutiqueId]);
    $commande = $requete->fetch();

    return $commande === false ? null : $commande;
}

function lignes_de_la_commande(int $commandeId): array
{
    $requete = bdd()->prepare('SELECT * FROM commande_lignes WHERE commande_id = :id ORDER BY id');
    $requete->execute(['id' => $commandeId]);

    return $requete->fetchAll();
}

function historique_de_la_commande(int $commandeId): array
{
    $requete = bdd()->prepare('SELECT * FROM commande_historique WHERE commande_id = :id ORDER BY cree_le, id');
    $requete->execute(['id' => $commandeId]);

    return $requete->fetchAll();
}

/**
 * Liste des commandes d'un client ou d'une boutique, avec le nombre d'articles.
 *
 * @param string $colonne 'client_id' ou 'boutique_id'
 * @param list<string>|null $statuts filtre facultatif
 */
function liste_commandes(string $colonne, int $id, ?array $statuts = null, int $limite = 100): array
{
    if (!in_array($colonne, ['client_id', 'boutique_id'], true)) {
        throw new InvalidArgumentException('Colonne de filtre invalide.');
    }

    $sql = 'SELECT c.*, b.nom AS boutique_nom, b.slug AS boutique_slug,
                   (SELECT COALESCE(SUM(l.quantite), 0) FROM commande_lignes l WHERE l.commande_id = c.id) AS nombre_articles,
                   (SELECT l.image FROM commande_lignes l WHERE l.commande_id = c.id ORDER BY l.id LIMIT 1) AS image
            FROM commandes c JOIN boutiques b ON b.id = c.boutique_id
            WHERE c.' . $colonne . ' = ?' . ($colonne === 'boutique_id' ? ' AND ' . SQL_VISIBLE_VENDEUR : '');
    $parametres = [$id];

    if ($statuts !== null && $statuts !== []) {
        $sql .= ' AND c.statut IN (' . implode(',', array_fill(0, count($statuts), '?')) . ')';
        array_push($parametres, ...$statuts);
    }

    $requete = bdd()->prepare($sql . ' ORDER BY c.cree_le DESC, c.id DESC LIMIT ' . max(1, $limite));
    $requete->execute($parametres);

    return $requete->fetchAll();
}

/** Nombre de commandes d'une boutique par statut (tableau de bord vendeur, §9.1). */
function compter_commandes_boutique(int $boutiqueId): array
{
    $requete = bdd()->prepare('SELECT statut, COUNT(*) AS nombre FROM commandes c WHERE boutique_id = :id AND ' . SQL_VISIBLE_VENDEUR . ' GROUP BY statut');
    $requete->execute(['id' => $boutiqueId]);

    $comptes = array_fill_keys(array_keys(STATUTS_COMMANDE), 0);
    foreach ($requete->fetchAll() as $ligne) {
        $comptes[$ligne['statut']] = (int) $ligne['nombre'];
    }

    return $comptes;
}

/** Code de livraison en clair — à n'afficher QUE dans l'espace du client (§14.1). */
function code_livraison_client(array $commande): ?string
{
    // Paiement à la livraison : pas de code.
    if (empty($commande['code_livraison_chiffre'])) {
        return null;
    }

    return dechiffrer((string) $commande['code_livraison_chiffre']);
}

/* -------------------------------------------------------------------------
   Changements de statut (§12.4)
   ------------------------------------------------------------------------- */

function historiser_statut(int $commandeId, ?string $ancien, string $nouveau, string $role, ?int $auteurId, ?string $motif = null): void
{
    bdd()->prepare(
        'INSERT INTO commande_historique (commande_id, ancien_statut, nouveau_statut, auteur_id, auteur_role, motif)
         VALUES (:commande, :ancien, :nouveau, :auteur, :role, :motif)'
    )->execute([
        'commande' => $commandeId, 'ancien' => $ancien, 'nouveau' => $nouveau,
        'auteur' => $auteurId, 'role' => $role, 'motif' => $motif,
    ]);
}

/** Vrai si la matrice §12.4 autorise ce passage pour ce rôle. */
function transition_autorisee(string $actuel, string $nouveau, string $role): bool
{
    return in_array($role, TRANSITIONS_COMMANDE[$actuel][$nouveau] ?? [], true);
}

/**
 * Change le statut d'une commande, dans une transaction avec verrouillage de la ligne.
 * Annulation : stock restitué. Motif obligatoire pour une annulation par le vendeur, pour
 * toute annulation d'une commande déjà confirmée, et pour un refus à la livraison.
 *
 * @param array $options ['avec_code' => bool, 'refus_livraison' => bool]
 * @throws ErreurCommande si la transition est refusée
 */
function changer_statut_commande(int $commandeId, string $nouveau, string $role, ?int $auteurId,
                                 ?string $motif = null, array $options = []): array
{
    $motif = $motif === null || trim($motif) === '' ? null : mb_substr(trim($motif), 0, 255);

    $pdo = bdd();
    $pdo->beginTransaction();

    try {
        $requete = $pdo->prepare('SELECT * FROM commandes WHERE id = :id FOR UPDATE');
        $requete->execute(['id' => $commandeId]);
        $commande = $requete->fetch();

        if ($commande === false) {
            throw new ErreurCommande('Commande introuvable.');
        }

        $actuel = $commande['statut'];
        if (!transition_autorisee($actuel, $nouveau, $role)) {
            throw new ErreurCommande('Cette action n\'est plus possible : la commande est « '
                . STATUTS_COMMANDE[$actuel][0] . ' ».');
        }

        $refus = !empty($options['refus_livraison']);
        if ($actuel === 'expediee' && $nouveau === 'annulee' && !$refus) {
            throw new ErreurCommande('Une commande expédiée ne peut être annulée que pour « client injoignable ou refus à la livraison ».');
        }
        $motifObligatoire = $nouveau === 'annulee' && ($role === 'vendeur' || !in_array($actuel, ['attente_confirmation', 'attente_paiement'], true));
        if ($motifObligatoire && $motif === null) {
            throw new ErreurCommande('Indiquez le motif de l\'annulation.');
        }

        $colonnesDate = [
            'confirmee' => 'confirmee_le', 'expediee' => 'expediee_le', 'livree' => 'livree_le',
            'recue' => 'recue_le', 'annulee' => 'annulee_le',
        ];
        $sql = 'UPDATE commandes SET statut = :statut';
        $parametres = ['statut' => $nouveau, 'id' => $commandeId];

        if (isset($colonnesDate[$nouveau])) {
            $sql .= ', ' . $colonnesDate[$nouveau] . ' = NOW()';
        }
        if ($nouveau === 'annulee') {
            $sql .= ', motif_annulation = :motif';
            $parametres['motif'] = $motif;
        }
        if ($nouveau === 'livree') {
            $sql .= ', livree_avec_code = :avec_code';
            $parametres['avec_code'] = !empty($options['avec_code']) ? 1 : 0;
        }
        $pdo->prepare($sql . ' WHERE id = :id')->execute($parametres);

        // Annulation : le stock revient au vendeur (la taille est recréée si elle a été retirée entre-temps).
        if ($nouveau === 'annulee' && $actuel !== 'annulee') {
            $restitution = $pdo->prepare(
                'INSERT INTO maillot_tailles (maillot_id, taille, stock) VALUES (:maillot, :taille, :quantite)
                 ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)'
            );
            foreach (lignes_de_la_commande($commandeId) as $ligne) {
                if ($ligne['maillot_id'] !== null) {
                    $restitution->execute(['maillot' => $ligne['maillot_id'], 'taille' => $ligne['taille'], 'quantite' => $ligne['quantite']]);
                }
            }
        }

        // Refus à la livraison : compté sur le compte du client (blocage au 3e, §12.5).
        if ($refus) {
            $pdo->prepare('UPDATE utilisateurs SET refus_livraison = refus_livraison + 1 WHERE id = :id')
                ->execute(['id' => $commande['client_id']]);
        }

        historiser_statut($commandeId, $actuel, $nouveau, $role, $auteurId, $motif);

        // Paiement en ligne : l'argent suit la commande dans le portefeuille du vendeur (§13.2), même transaction.
        suivre_portefeuille($commande, $actuel, $nouveau, $motif);

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    journaliser_action('commande_' . $nouveau, $auteurId, 'commande', $commandeId);
    $commande['statut_precedent'] = $actuel;
    $commande['statut'] = $nouveau;
    if ($nouveau === 'livree') {
        $commande['livree_avec_code'] = !empty($options['avec_code']) ? 1 : 0;
    }
    prevenir_changement_statut($commande, $nouveau, $role, $motif);

    return $commande;
}

/** SMS liés aux changements de statut (§22.2). */
function prevenir_changement_statut(array $commande, string $nouveau, string $role, ?string $motif): void
{
    $numero = $commande['numero'];
    $client = (string) $commande['telephone_client'];
    $clientId = (int) $commande['client_id'];
    $vendeurId = (int) boutique_proprietaire((int) $commande['boutique_id']);
    $lienClient = 'compte/commande.php?id=' . (int) $commande['id'];
    $lienVendeur = 'vendeur/commande.php?id=' . (int) $commande['id'];

    switch ($nouveau) {
        case 'confirmee':
            envoyer_sms($client, "SAMA MAILLOT : votre commande $numero est confirmée par le vendeur.", false);
            notifier($clientId, 'commande_confirmee', "Commande $numero confirmée", 'Le vendeur prépare votre maillot.', $lienClient);
            break;
        case 'expediee':
            $avecCode = !empty($commande['code_livraison_chiffre']);
            envoyer_sms($client, "SAMA MAILLOT : votre commande $numero est en route. " . ($avecCode
                ? 'Donnez votre code de livraison au livreur à la réception.' : 'Le vendeur vous contactera pour la remise.'), false);
            notifier($clientId, 'commande_expediee', "Commande $numero en route", $avecCode
                ? 'Donnez votre code de livraison au livreur, seulement à la réception.'
                : 'Le vendeur vous contactera pour la remise. Vous payez à la réception.', $lienClient);
            break;
        case 'livree':
            notifier($clientId, 'commande_livree', "Commande $numero livrée",
                (int) $commande['livree_avec_code'] === 1 ? 'Livraison confirmée par votre code. Bon match !'
                    : 'Le vendeur a marqué la commande livrée. Confirmez la réception, ou signalez un problème sous 48 h.', $lienClient);
            // Seulement pour une livraison SANS code : le client peut encore réagir (§14.2).
            if ((int) $commande['livree_avec_code'] === 0) {
                $heures = (int) parametre('deblocage_sans_code_heures', '48');
                $limite = date('d/m/Y à H\hi', time() + $heures * 3600);
                envoyer_sms($client, "SAMA MAILLOT : votre commande $numero est marquée livrée. Un problème ? Signalez-le avant le $limite.", false);
            }
            break;
        case 'annulee':
            // Paiement en ligne non abouti : le vendeur n'avait jamais été prévenu de cette commande.
            if (($commande['statut_precedent'] ?? '') === 'attente_paiement') {
                if ($role !== 'client') {
                    notifier($clientId, 'paiement_echoue', "Commande $numero annulée",
                        'Le paiement en ligne n\'a pas abouti' . ($motif !== null ? ' (' . $motif . ')' : '') . '. Aucun montant n\'a été prélevé.', $lienClient);
                }
                break;
            }
            $texte = "SAMA MAILLOT : la commande $numero est annulée" . ($motif !== null ? " ($motif)" : '') . '.';
            $detail = $motif !== null ? $motif . '.' : 'Le stock a été remis en vente.';
            if ($commande['mode_paiement'] === 'en_ligne' && $commande['payee_le'] !== null) {
                $detail .= ' Vous serez remboursé de ' . format_fcfa((int) $commande['total']) . ' par SAMA MAILLOT.';
            }
            if ($role !== 'client') {
                envoyer_sms($client, $texte, false);
                notifier($clientId, 'commande_annulee', "Commande $numero annulée", $detail, $lienClient);
            }
            if ($role !== 'vendeur') {
                prevenir_vendeur($vendeurId, $texte);
                notifier($vendeurId, 'commande_annulee', "Commande $numero annulée",
                    ($role === 'client' ? 'Annulée par le client. ' : '') . $detail, $lienVendeur);
            }
            break;
        case 'recue':
            notifier($vendeurId, 'commande_recue', "Commande $numero reçue",
                $role === 'client' ? 'Le client a confirmé la réception.' : 'Délai de réclamation écoulé : commande terminée.', $lienVendeur);
            // Invitation à noter le vendeur (§20), une seule fois : pas après la clôture d'une réclamation.
            if (litige_de_la_commande((int) $commande['id']) === null && avis_de_la_commande((int) $commande['id']) === null) {
                notifier($clientId, 'avis_invitation', "Notez le vendeur de la commande $numero",
                    'Votre avis aide les autres clients à choisir. Cela prend 30 secondes.', 'compte/avis.php?commande=' . (int) $commande['id']);
            }
            break;
    }
}

/* -------------------------------------------------------------------------
   Code de livraison (§14.1)
   ------------------------------------------------------------------------- */

/**
 * Le vendeur saisit le code donné par le client. 5 essais, puis blocage (la livraison ne peut
 * alors être déclarée que sans code) et alerte dans le journal pour l'administrateur.
 *
 * @return array{ok: bool, message: string}
 */
function verifier_code_livraison(array $commande, string $saisie, int $vendeurId): array
{
    if ($commande['statut'] !== 'expediee') {
        return ['ok' => false, 'message' => 'Le code se saisit quand la commande est expédiée.'];
    }
    if (empty($commande['code_livraison_chiffre'])) {
        return ['ok' => false, 'message' => 'Cette commande n\x27a pas de code de livraison (paiement à la livraison).'];
    }
    if ((int) $commande['code_essais'] >= CODE_LIVRAISON_ESSAIS_MAX) {
        return ['ok' => false, 'message' => 'Trop d\'essais : le code est bloqué. Déclarez la livraison sans code.'];
    }

    $saisie = preg_replace('/\D+/', '', $saisie) ?? '';
    $code = code_livraison_client($commande);

    if ($code !== null && strlen($saisie) === 4 && hash_equals($code, $saisie)) {
        changer_statut_commande((int) $commande['id'], 'livree', 'vendeur', $vendeurId, 'Livraison prouvée par le code', ['avec_code' => true]);

        return ['ok' => true, 'message' => 'Code correct : la livraison est confirmée.'];
    }

    bdd()->prepare('UPDATE commandes SET code_essais = code_essais + 1 WHERE id = :id')->execute(['id' => $commande['id']]);
    $restants = CODE_LIVRAISON_ESSAIS_MAX - (int) $commande['code_essais'] - 1;

    if ($restants <= 0) {
        journaliser_action('code_livraison_bloque', $vendeurId, 'commande', (int) $commande['id']);
        notifier_admins('code_bloque', 'Code de livraison bloqué : ' . $commande['numero'],
            '5 codes faux saisis par le vendeur. Vérifiez la livraison avant tout déblocage des fonds.',
            'admin/commandes.php?id=' . (int) $commande['id']);

        return ['ok' => false, 'message' => 'Code incorrect. 5 essais atteints : le code est bloqué et l\'administration est alertée.'];
    }

    return ['ok' => false, 'message' => 'Code incorrect. Il vous reste ' . $restants . ' essai(s).'];
}

/* -------------------------------------------------------------------------
   Tâches automatiques (§27.3) : annulations après 24 h, rappels, réception automatique
   ------------------------------------------------------------------------- */

/**
 * Exécutée par le cron (taches/) et, par sécurité, à l'ouverture des pages de commandes :
 *   - commande non confirmée après 24 h → annulée par le système, stock restitué ;
 *   - 12 h restantes → rappel au vendeur (une seule fois) ;
 *   - livrée avec code depuis 24 h (sans code : 48 h) sans réclamation → reçue.
 *
 * @return array{annulees: int, rappels: int, recues: int}
 */
function traiter_commandes_echues(): array
{
    $bilan = ['annulees' => 0, 'rappels' => 0, 'recues' => 0];
    $pdo = bdd();
    $delai = max(1, (int) parametre('confirmation_vendeur_heures', '24'));

    $echues = $pdo->query(
        "SELECT id FROM commandes WHERE statut = 'attente_confirmation' AND COALESCE(payee_le, cree_le) <= NOW() - INTERVAL $delai HOUR LIMIT 200"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($echues as $id) {
        try {
            changer_statut_commande((int) $id, 'annulee', 'systeme', null, 'Non confirmée par le vendeur sous ' . $delai . ' h');
            $bilan['annulees']++;
        } catch (ErreurCommande) {
            // Déjà traitée entre-temps (autre processus) : rien à faire.
        }
    }

    $moitie = intdiv($delai, 2);
    $rappels = $pdo->query(
        "SELECT id, numero, boutique_id FROM commandes
         WHERE statut = 'attente_confirmation' AND rappel_envoye_le IS NULL AND COALESCE(payee_le, cree_le) <= NOW() - INTERVAL $moitie HOUR LIMIT 200"
    )->fetchAll();
    foreach ($rappels as $commande) {
        $marque = $pdo->prepare('UPDATE commandes SET rappel_envoye_le = NOW() WHERE id = :id AND rappel_envoye_le IS NULL');
        $marque->execute(['id' => $commande['id']]);
        if ($marque->rowCount() === 1) {
            $vendeur = (int) boutique_proprietaire((int) $commande['boutique_id']);
            prevenir_vendeur($vendeur,
                'SAMA MAILLOT : rappel, la commande ' . $commande['numero'] . ' attend votre confirmation (moins de 12 h restantes).');
            notifier($vendeur, 'commande_rappel', 'Commande ' . $commande['numero'] . ' à confirmer',
                'Moins de 12 h restantes, sinon elle sera annulée automatiquement.', 'vendeur/commande.php?id=' . (int) $commande['id']);
            $bilan['rappels']++;
        }
    }

    $avecCode = max(1, (int) parametre('deblocage_avec_code_heures', '24'));
    $sansCode = max(1, (int) parametre('deblocage_sans_code_heures', '48'));
    $livrees = $pdo->query(
        "SELECT id FROM commandes WHERE statut = 'livree' AND (
             (livree_avec_code = 1 AND livree_le <= NOW() - INTERVAL $avecCode HOUR)
          OR (livree_avec_code = 0 AND livree_le <= NOW() - INTERVAL $sansCode HOUR)) LIMIT 200"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($livrees as $id) {
        try {
            changer_statut_commande((int) $id, 'recue', 'systeme', null, 'Délai de réclamation écoulé');
            $bilan['recues']++;
        } catch (ErreurCommande) {
        }
    }

    // Paniers invités expirés (7 jours)
    $pdo->exec('DELETE FROM paniers WHERE utilisateur_id IS NULL AND expire_le < NOW()');

    return $bilan;
}

/* -------------------------------------------------------------------------
   Affichage
   ------------------------------------------------------------------------- */

function badge_statut_commande(string $statut): string
{
    [$libelle, $couleur] = STATUTS_COMMANDE[$statut] ?? [$statut, 'info'];

    return '<span class="sm-statut sm-statut--' . e($couleur) . '">' . e($libelle) . '</span>';
}

/** « 14 h 20 min » restantes avant l'annulation automatique (vendeur), ou null. */
function delai_confirmation_restant(array $commande): ?string
{
    if ($commande['statut'] !== 'attente_confirmation') {
        return null;
    }

    $fin = strtotime($commande['payee_le'] ?? $commande['cree_le']) + max(1, (int) parametre('confirmation_vendeur_heures', '24')) * 3600;
    $reste = max(0, $fin - time());

    return intdiv($reste, 3600) . ' h ' . str_pad((string) intdiv($reste % 3600, 60), 2, '0', STR_PAD_LEFT) . ' min';
}

/** Date courte à la française : 05/10/2026 à 14h32. */
function date_commande(?string $date): string
{
    return $date === null ? '' : date('d/m/Y à H\hi', strtotime($date));
}

/** Libellé du flocage d'une ligne (« DIOP 10 ») ou chaîne vide. */
function libelle_flocage(array $ligne): string
{
    return $ligne['flocage_nom'] !== '' ? trim($ligne['flocage_nom'] . ' ' . $ligne['flocage_numero']) : '';
}

/** Message WhatsApp pré-rempli d'une commande (§17). */
function message_whatsapp_commande(array $commande, array $lignes): string
{
    $articles = array_map(static function (array $ligne): string {
        $flocage = libelle_flocage($ligne);

        return $ligne['titre'] . ', taille ' . $ligne['taille'] . ($flocage !== '' ? ', flocage ' . $flocage : '')
            . ', ' . $ligne['quantite'] . ' exemplaire' . ((int) $ligne['quantite'] > 1 ? 's' : '');
    }, $lignes);

    return 'Bonjour, je viens de passer la commande ' . $commande['numero'] . ' sur SAMA MAILLOT : '
        . implode(' ; ', $articles) . '. Total : ' . format_fcfa((int) $commande['total']) . '.';
}
