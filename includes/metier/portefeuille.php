<?php
/**
 * SAMA MAILLOT — portefeuille vendeur, versements et remboursements (§13.2, §15).
 *
 * Registre NON MODIFIABLE : la table mouvements_portefeuille ne reçoit que des INSERT (aucune
 * fonction de ce site ne la modifie ni ne la vide). Les soldes sont TOUJOURS calculés à partir
 * du registre : SUM(montant) par compartiment.
 *
 *   en_attente  payé en ligne, délai de réclamation en cours
 *   disponible  débloqué, en attente du prochain versement (mardi et vendredi)
 *   bloque      commande en litige
 *   verse       déjà viré sur le Wave du vendeur
 *
 * Seules les commandes PAYÉES EN LIGNE passent par le portefeuille : en paiement à la livraison,
 * le client paie directement le vendeur.
 */

declare(strict_types=1);

const COMPARTIMENTS_PORTEFEUILLE = [
    'en_attente' => ['En attente', 'Payé en ligne, délai de réclamation en cours', 'bi-hourglass-split', 'attention'],
    'disponible' => ['Disponible', 'Débloqué, versé au prochain virement', 'bi-wallet2', 'succes'],
    'bloque' => ['Bloqué', 'Commandes en litige', 'bi-lock', 'erreur'],
    'verse' => ['Versé', 'Déjà viré sur votre Wave', 'bi-send-check', 'info'],
];

const LIBELLES_OPERATION = [
    'credit' => 'Paiement reçu',
    'deblocage' => 'Déblocage',
    'blocage' => 'Blocage (réclamation)',
    'liberation' => 'Libération (réclamation close)',
    'remboursement' => 'Remboursement au client',
    'versement' => 'Versement Wave',
];

/* -------------------------------------------------------------------------
   Registre
   ------------------------------------------------------------------------- */

/** Ajoute une ligne au registre (seule écriture autorisée). */
function inscrire_mouvement(int $boutiqueId, ?int $commandeId, ?int $versementId, string $operation, string $compartiment,
                            int $montant, string $libelle): void
{
    bdd()->prepare(
        'INSERT INTO mouvements_portefeuille (boutique_id, commande_id, versement_id, operation, compartiment, montant, libelle)
         VALUES (:boutique, :commande, :versement, :operation, :compartiment, :montant, :libelle)'
    )->execute([
        'boutique' => $boutiqueId, 'commande' => $commandeId, 'versement' => $versementId, 'operation' => $operation,
        'compartiment' => $compartiment, 'montant' => $montant, 'libelle' => mb_substr($libelle, 0, 160),
    ]);
}

/** Transfert entre deux compartiments : deux lignes (−x, +x). */
function transferer_portefeuille(int $boutiqueId, int $commandeId, string $operation, string $de, string $vers, int $montant, string $libelle): void
{
    if ($montant <= 0) {
        return;
    }
    inscrire_mouvement($boutiqueId, $commandeId, null, $operation, $de, -$montant, $libelle);
    inscrire_mouvement($boutiqueId, $commandeId, null, $operation, $vers, $montant, $libelle);
}

/**
 * Soldes calculés à partir du registre.
 *
 * @return array{en_attente: int, disponible: int, bloque: int, verse: int}
 */
function soldes_portefeuille(int $boutiqueId): array
{
    $requete = bdd()->prepare('SELECT compartiment, SUM(montant) FROM mouvements_portefeuille WHERE boutique_id = :id GROUP BY compartiment');
    $requete->execute(['id' => $boutiqueId]);
    $sommes = $requete->fetchAll(PDO::FETCH_KEY_PAIR);

    $soldes = [];
    foreach (array_keys(COMPARTIMENTS_PORTEFEUILLE) as $compartiment) {
        $soldes[$compartiment] = (int) ($sommes[$compartiment] ?? 0);
    }

    return $soldes;
}

/** Derniers mouvements d'une boutique (les transferts sont regroupés : on montre la ligne positive). */
function mouvements_boutique(int $boutiqueId, int $limite = 50): array
{
    $requete = bdd()->prepare(
        "SELECT m.*, c.numero FROM mouvements_portefeuille m LEFT JOIN commandes c ON c.id = m.commande_id
         WHERE m.boutique_id = :id AND (m.montant > 0 OR m.operation = 'remboursement')
         ORDER BY m.cree_le DESC, m.id DESC LIMIT " . max(1, $limite)
    );
    $requete->execute(['id' => $boutiqueId]);

    return $requete->fetchAll();
}

/* -------------------------------------------------------------------------
   L'argent suit la commande (appelé par changer_statut_commande, DANS sa transaction)
   ------------------------------------------------------------------------- */

/**
 * Mouvements du portefeuille liés à un changement de statut d'une commande PAYÉE en ligne :
 *   payée                → crédit « en attente »
 *   reçue                → en attente (ou bloqué, après un litige) → disponible
 *   en litige            → en attente → bloqué
 *   annulée              → retrait « en attente » + remboursement du client à effectuer
 *   remboursée (litige)  → retrait « bloqué » + remboursement du client à effectuer
 */
function suivre_portefeuille(array $commande, string $ancien, string $nouveau, ?string $motif): void
{
    if ($commande['mode_paiement'] !== 'en_ligne' || $commande['payee_le'] === null) {
        return;
    }

    $boutiqueId = (int) $commande['boutique_id'];
    $id = (int) $commande['id'];
    $montant = (int) $commande['montant_vendeur'];
    $numero = $commande['numero'];

    switch (true) {
        case $ancien === 'attente_paiement' && $nouveau === 'attente_confirmation':
            inscrire_mouvement($boutiqueId, $id, null, 'credit', 'en_attente', $montant, "Commande $numero payée en ligne");
            break;
        case $nouveau === 'recue':
            $ancien === 'en_litige'
                ? transferer_portefeuille($boutiqueId, $id, 'liberation', 'bloque', 'disponible', $montant, "Commande $numero : réclamation close")
                : transferer_portefeuille($boutiqueId, $id, 'deblocage', 'en_attente', 'disponible', $montant, "Commande $numero reçue");
            break;
        case $nouveau === 'en_litige':
            transferer_portefeuille($boutiqueId, $id, 'blocage', 'en_attente', 'bloque', $montant, "Commande $numero : réclamation ouverte");
            break;
        case $nouveau === 'annulee':
            inscrire_mouvement($boutiqueId, $id, null, 'remboursement', 'en_attente', -$montant, "Commande $numero annulée : client remboursé");
            creer_remboursement($commande, $motif ?? 'Commande annulée');
            break;
        case $nouveau === 'remboursee' && $ancien === 'en_litige':
            inscrire_mouvement($boutiqueId, $id, null, 'remboursement', 'bloque', -$montant, "Commande $numero : réclamation fondée, client remboursé");
            creer_remboursement($commande, $motif ?? 'Réclamation fondée');
            break;
    }
}

/* -------------------------------------------------------------------------
   Remboursements (V1 : manuels, par l'administration)
   ------------------------------------------------------------------------- */

function creer_remboursement(array $commande, string $motif): void
{
    bdd()->prepare(
        'INSERT IGNORE INTO remboursements (commande_id, client_id, montant, motif) VALUES (:commande, :client, :montant, :motif)'
    )->execute([
        'commande' => $commande['id'], 'client' => $commande['client_id'], 'montant' => $commande['total'], 'motif' => mb_substr($motif, 0, 255),
    ]);
}

/** Remboursements à effectuer (administration). */
function remboursements_a_faire(): array
{
    return bdd()->query(
        "SELECT r.*, c.numero, c.statut AS statut_commande, c.telephone_client, u.prenom, u.nom, t.moyen, t.reference AS reference_paiement
         FROM remboursements r JOIN commandes c ON c.id = r.commande_id JOIN utilisateurs u ON u.id = r.client_id
         LEFT JOIN transactions_paiement t ON t.id = c.transaction_id
         WHERE r.statut = 'a_rembourser' ORDER BY r.cree_le"
    )->fetchAll();
}

/** L'administration a remboursé le client : référence saisie, commande « Remboursée », client prévenu. */
function marquer_rembourse(array $admin, int $remboursementId, string $reference): ?string
{
    $reference = strtoupper(trim($reference));
    if (preg_match('/^[A-Z0-9-]{4,60}$/', $reference) !== 1) {
        return 'Indiquez la référence du remboursement (lettres, chiffres et tirets).';
    }

    $requete = bdd()->prepare(
        "UPDATE remboursements SET statut = 'rembourse', reference = :reference, admin_id = :admin, rembourse_le = NOW()
         WHERE id = :id AND statut = 'a_rembourser'"
    );
    $requete->execute(['reference' => $reference, 'admin' => $admin['id'], 'id' => $remboursementId]);
    if ($requete->rowCount() !== 1) {
        return 'Ce remboursement est déjà enregistré.';
    }

    $lecture = bdd()->prepare('SELECT r.*, c.numero, c.statut, c.jeton_suivi_chiffre FROM remboursements r JOIN commandes c ON c.id = r.commande_id WHERE r.id = :id');
    $lecture->execute(['id' => $remboursementId]);
    $remboursement = $lecture->fetch();

    if ($remboursement['statut'] === 'annulee') {
        changer_statut_commande((int) $remboursement['commande_id'], 'remboursee', 'admin', (int) $admin['id'], 'Remboursement ' . $reference);
    }
    journaliser_action('admin_remboursement', (int) $admin['id'], 'commande', (int) $remboursement['commande_id'], null, $reference);
    notifier((int) $remboursement['client_id'], 'remboursement_effectue', 'Commande ' . $remboursement['numero'] . ' remboursée',
        format_fcfa((int) $remboursement['montant']) . ' vous ont été remboursés (référence ' . $reference . ').',
        lien_commande_client(['id' => $remboursement['commande_id'], 'jeton_suivi_chiffre' => $remboursement['jeton_suivi_chiffre']]));

    return null;
}

/* -------------------------------------------------------------------------
   Versements (V1 : virement Wave manuel par l'administration, §15.2)
   ------------------------------------------------------------------------- */

/** Prochain jour de versement (paramètre versement_jours, ex. « mardi,vendredi »), au format jj/mm/aaaa. */
function prochain_versement(): string
{
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $voulus = array_map('trim', explode(',', mb_strtolower(parametre('versement_jours', 'mardi,vendredi'))));

    for ($i = 0; $i < 8; $i++) {
        $date = strtotime("+$i day");
        if (in_array($jours[(int) date('w', $date)], $voulus, true)) {
            return $jours[(int) date('w', $date)] . ' ' . date('d/m/Y', $date);
        }
    }

    return 'prochainement';
}

/** Boutiques dont le solde disponible atteint le minimum de versement (administration). */
function boutiques_a_verser(): array
{
    $minimum = max(0, (int) parametre('versement_minimum_fcfa', '1000'));
    $requete = bdd()->prepare(
        "SELECT b.id, b.nom, b.numero_wave, b.utilisateur_id, u.prenom, u.nom AS vendeur_nom, SUM(m.montant) AS disponible
         FROM mouvements_portefeuille m JOIN boutiques b ON b.id = m.boutique_id JOIN utilisateurs u ON u.id = b.utilisateur_id
         WHERE m.compartiment = 'disponible'
         GROUP BY b.id HAVING disponible >= :minimum AND disponible > 0 ORDER BY disponible DESC"
    );
    $requete->execute(['minimum' => $minimum]);

    return $requete->fetchAll();
}

/**
 * Enregistre un versement : tout le solde disponible part vers « versé ». Le solde est relu sous
 * verrou (ligne de la boutique) pour ne jamais verser deux fois le même argent.
 */
function enregistrer_versement(array $admin, int $boutiqueId, string $reference): ?string
{
    $reference = strtoupper(trim($reference));
    if (preg_match('/^[A-Z0-9-]{4,60}$/', $reference) !== 1) {
        return 'Indiquez la référence de la transaction Wave (lettres, chiffres et tirets).';
    }

    $pdo = bdd();
    $pdo->beginTransaction();
    try {
        $verrou = $pdo->prepare('SELECT id, nom, numero_wave, utilisateur_id FROM boutiques WHERE id = :id FOR UPDATE');
        $verrou->execute(['id' => $boutiqueId]);
        $boutique = $verrou->fetch();
        $disponible = $boutique !== false ? soldes_portefeuille($boutiqueId)['disponible'] : 0;

        if ($boutique === false || $disponible <= 0) {
            $pdo->rollBack();

            return 'Aucun montant disponible à verser pour cette boutique.';
        }
        if (empty($boutique['numero_wave'])) {
            $pdo->rollBack();

            return 'Le vendeur n\'a pas indiqué de numéro Wave.';
        }

        $pdo->prepare('INSERT INTO versements (boutique_id, montant, numero_wave, reference_wave, admin_id) VALUES (:boutique, :montant, :wave, :reference, :admin)')
            ->execute(['boutique' => $boutiqueId, 'montant' => $disponible, 'wave' => $boutique['numero_wave'], 'reference' => $reference, 'admin' => $admin['id']]);
        $versementId = (int) $pdo->lastInsertId();
        inscrire_mouvement($boutiqueId, null, $versementId, 'versement', 'disponible', -$disponible, 'Versement Wave ' . $reference);
        inscrire_mouvement($boutiqueId, null, $versementId, 'versement', 'verse', $disponible, 'Versement Wave ' . $reference);
        $pdo->commit();
    } catch (PDOException $exception) {
        $pdo->rollBack();
        if ($exception->getCode() === '23000') {
            return 'Cette référence Wave a déjà été utilisée pour un autre versement.';
        }
        throw $exception;
    }

    journaliser_action('admin_versement', (int) $admin['id'], 'boutique', $boutiqueId, null, $reference . ' : ' . $disponible);
    notifier((int) $boutique['utilisateur_id'], 'versement_effectue', 'Versement de ' . format_fcfa($disponible),
        'Viré sur votre Wave ' . formater_telephone((string) $boutique['numero_wave']) . ' (référence ' . $reference . ').', 'vendeur/portefeuille.php');

    return null;
}

/** Historique des versements d'une boutique (ou de toutes, $boutiqueId = null). */
function versements_effectues(?int $boutiqueId, int $limite = 50): array
{
    $sql = 'SELECT v.*, b.nom AS boutique_nom FROM versements v JOIN boutiques b ON b.id = v.boutique_id';
    $parametres = [];
    if ($boutiqueId !== null) {
        $sql .= ' WHERE v.boutique_id = :id';
        $parametres['id'] = $boutiqueId;
    }
    $requete = bdd()->prepare($sql . ' ORDER BY v.cree_le DESC LIMIT ' . max(1, $limite));
    $requete->execute($parametres);

    return $requete->fetchAll();
}
