<?php
/**
 * SAMA MAILLOT — espace vendeur : boutique, abonnement, maillots, livraison (§9).
 *
 * Sécurité (§24, IDOR) : toutes les lectures et modifications passent par la boutique
 * du vendeur CONNECTÉ ; un identifiant reçu dans l'adresse n'est jamais utilisé seul.
 */

declare(strict_types=1);

const STATUTS_MAILLOT = [
    'brouillon' => ['Brouillon', 'info'],
    'publie' => ['Publié', 'succes'],
    'masque_vendeur' => ['Masqué', 'attention'],
    'masque_admin' => ['Masqué par l\'administration', 'erreur'],
];

const DELAIS_LIVRAISON = ['Le jour même', '24h', '24-48h', '48-72h', '3 à 5 jours', 'Sur rendez-vous'];

const PHOTOS_PAR_MAILLOT_MAX = 8;   // valeur par défaut (Annexe A : 5, porté à 8 à la demande du client)

/** Nombre maximum de photos par maillot : paramètre « photos_par_maillot » (réglable par l'admin). */
function photos_par_maillot_max(): int
{
    return max(1, min(20, (int) parametre('photos_par_maillot', (string) PHOTOS_PAR_MAILLOT_MAX)));
}

/* -------------------------------------------------------------------------
   Vendeur connecté et sa boutique
   ------------------------------------------------------------------------- */

/** Boutique d'un vendeur (quel que soit son statut), ou null. */
function boutique_du_vendeur(int $utilisateurId): ?array
{
    $requete = bdd()->prepare('SELECT * FROM boutiques WHERE utilisateur_id = :id AND supprime_le IS NULL');
    $requete->execute(['id' => $utilisateurId]);
    $boutique = $requete->fetch();

    return $boutique === false ? null : $boutique;
}

/**
 * À appeler en haut de chaque page de l'espace vendeur.
 * Vendeur connecté + téléphone vérifié (§8.2) + boutique existante.
 *
 * @return array{0: array, 1: array} [utilisateur, boutique]
 */
function exiger_vendeur(): array
{
    $utilisateur = exiger_connexion('vendeur');

    if (VERIFICATION_SMS && $utilisateur['telephone_verifie_le'] === null) {
        rediriger('inscription/verifier-telephone.php');
    }

    $boutique = boutique_du_vendeur((int) $utilisateur['id']);
    if ($boutique === null) {
        afficher_erreur(404);
    }

    // Vérification SMS désactivée : un vendeur inscrit avant ce réglage, resté en attente du
    // code, reçoit son essai gratuit à sa première visite (un seul essai par numéro).
    if (!VERIFICATION_SMS && $utilisateur['telephone_verifie_le'] === null && $utilisateur['telephone'] !== null) {
        $requete = bdd()->prepare('SELECT COUNT(*) FROM essais_gratuits WHERE telephone = :telephone');
        $requete->execute(['telephone' => $utilisateur['telephone']]);
        if ((int) $requete->fetchColumn() === 0) {
            demarrer_essai_gratuit((int) $utilisateur['id'], $utilisateur['telephone']);
        }
    }

    return [$utilisateur, $boutique];
}

/**
 * Démarre l'essai gratuit d'un vendeur dont le téléphone vient d'être vérifié (§9.5).
 * Un numéro n'a droit qu'à un seul essai, même après suppression du compte : INSERT IGNORE
 * n'insère rien si ce numéro figure déjà dans essais_gratuits.
 *
 * Renvoie le message à afficher au vendeur.
 */
function demarrer_essai_gratuit(int $utilisateurId, string $telephone): string
{
    $jours = max(1, (int) parametre('essai_gratuit_jours', (string) ESSAI_GRATUIT_JOURS));

    $essai = bdd()->prepare(
        'INSERT IGNORE INTO essais_gratuits (telephone, utilisateur_id, debut_le, fin_le)
         VALUES (:telephone, :id, NOW(), NOW() + INTERVAL ' . $jours . ' DAY)'
    );
    $essai->execute(['telephone' => $telephone, 'id' => $utilisateurId]);

    if ($essai->rowCount() === 1) {
        return 'Votre essai gratuit de ' . $jours . ' jours commence aujourd\'hui.';
    }

    return 'Ce numéro a déjà bénéficié de l\'essai gratuit : un abonnement sera nécessaire pour publier vos maillots.';
}

/* -------------------------------------------------------------------------
   Abonnement (§9.5, §18)
   Le paiement des abonnements arrive au lot 6. En attendant, l'état est calculé
   à partir de l'essai gratuit enregistré en base (jamais d'une variable de session).
   ------------------------------------------------------------------------- */

/**
 * @return array{etat: string, libelle: string, fin: ?string, peut_ajouter: bool, peut_publier: bool}
 *   etat : 'essai', 'grace', 'expire' ou 'aucun' (et 'suspendue' si la boutique est suspendue)
 */
function etat_abonnement(array $boutique): array
{
    // Calcul complet (essai, périodes payées, paiement en vérification, grâce) : metier/abonnements.php
    return calculer_etat_abonnement($boutique);
}

/* -------------------------------------------------------------------------
   Maillots du vendeur
   ------------------------------------------------------------------------- */

/** Un maillot de CETTE boutique (jamais celui d'une autre), hors supprimés. */
function maillot_du_vendeur(int $maillotId, int $boutiqueId): ?array
{
    $requete = bdd()->prepare(
        "SELECT * FROM maillots WHERE id = :id AND boutique_id = :boutique AND statut <> 'supprime' AND supprime_le IS NULL"
    );
    $requete->execute(['id' => $maillotId, 'boutique' => $boutiqueId]);
    $maillot = $requete->fetch();

    return $maillot === false ? null : $maillot;
}

/** Liste des maillots de la boutique, avec stock total et miniature. */
function maillots_de_la_boutique(int $boutiqueId, string $statut = ''): array
{
    $sql = 'SELECT m.id, m.titre, m.slug, m.prix, m.statut, m.authenticite, m.modifie_le, e.nom AS equipe_nom,
                (SELECT COALESCE(SUM(t.stock), 0) FROM maillot_tailles t WHERE t.maillot_id = m.id) AS stock_total,
                (SELECT i.miniature FROM maillot_images i WHERE i.maillot_id = m.id ORDER BY i.ordre, i.id LIMIT 1) AS miniature
            FROM maillots m
            JOIN equipes e ON e.id = m.equipe_id
            WHERE m.boutique_id = :boutique AND m.statut <> \'supprime\' AND m.supprime_le IS NULL';
    $parametres = ['boutique' => $boutiqueId];

    if ($statut !== '' && isset(STATUTS_MAILLOT[$statut])) {
        $sql .= ' AND m.statut = :statut';
        $parametres['statut'] = $statut;
    }

    $requete = bdd()->prepare($sql . ' ORDER BY m.modifie_le DESC, m.id DESC');
    $requete->execute($parametres);

    return $requete->fetchAll();
}

/** Nombre de maillots par statut et nombre de maillots publiés en rupture. */
function statistiques_boutique(int $boutiqueId): array
{
    $requete = bdd()->prepare(
        "SELECT
            SUM(statut = 'publie') AS publies,
            SUM(statut = 'brouillon') AS brouillons,
            SUM(statut IN ('masque_vendeur', 'masque_admin')) AS masques,
            SUM(statut = 'publie' AND (SELECT COALESCE(SUM(t.stock), 0) FROM maillot_tailles t WHERE t.maillot_id = m.id) = 0) AS ruptures
         FROM maillots m
         WHERE boutique_id = :boutique AND statut <> 'supprime' AND supprime_le IS NULL"
    );
    $requete->execute(['boutique' => $boutiqueId]);
    $stats = $requete->fetch() ?: [];

    return array_map('intval', array_merge(['publies' => 0, 'brouillons' => 0, 'masques' => 0, 'ruptures' => 0], array_filter($stats, 'is_numeric')));
}

/** Photos d'un maillot, avec leur id (pour les gérer dans le formulaire). */
function photos_du_maillot(int $maillotId): array
{
    $requete = bdd()->prepare('SELECT id, fichier, miniature, ordre FROM maillot_images WHERE maillot_id = :id ORDER BY ordre, id');
    $requete->execute(['id' => $maillotId]);

    return $requete->fetchAll();
}

/** Texte de recherche d'un maillot : titre + équipe + saison (§10.2). */
function mettre_a_jour_texte_recherche(int $maillotId): void
{
    bdd()->prepare(
        "UPDATE maillots m JOIN equipes e ON e.id = m.equipe_id
         SET m.texte_recherche = LEFT(CONCAT_WS(' ', m.titre, e.nom, m.saison), 255)
         WHERE m.id = :id"
    )->execute(['id' => $maillotId]);
}

/* -------------------------------------------------------------------------
   Livraison (§9.3)
   ------------------------------------------------------------------------- */

/** Zones actives de l'admin, avec le tarif de la boutique si elle les dessert. */
function zones_et_tarifs(int $boutiqueId): array
{
    $requete = bdd()->prepare(
        'SELECT z.id, z.nom, z.description, t.tarif, t.delai
         FROM zones_livraison z
         LEFT JOIN tarifs_livraison t ON t.zone_id = z.id AND t.boutique_id = :boutique
         WHERE z.actif = 1
         ORDER BY z.ordre, z.nom'
    );
    $requete->execute(['boutique' => $boutiqueId]);

    return $requete->fetchAll();
}

/** Uniquement les zones desservies (pages publiques). */
function zones_desservies(int $boutiqueId): array
{
    return array_values(array_filter(zones_et_tarifs($boutiqueId), static fn (array $z): bool => $z['tarif'] !== null));
}

/* -------------------------------------------------------------------------
   Affichage
   ------------------------------------------------------------------------- */

/** Badge de statut d'un maillot (couleurs d'état de la charte). */
function badge_statut_maillot(string $statut): string
{
    [$libelle, $couleur] = STATUTS_MAILLOT[$statut] ?? [$statut, 'info'];

    return '<span class="sm-statut sm-statut--' . e($couleur) . '">' . e($libelle) . '</span>';
}

/** Menu de navigation de l'espace vendeur. */
function menu_vendeur(string $actif): string
{
    $entrees = [
        'tableau' => ['vendeur/', 'bi-speedometer2', 'Tableau de bord'],
        'commandes' => ['vendeur/commandes.php', 'bi-receipt', 'Commandes'],
        'maillots' => ['vendeur/maillots.php', 'bi-box-seam', 'Mes maillots'],
        'boutique' => ['vendeur/boutique.php', 'bi-shop', 'Ma boutique'],
        'livraison' => ['vendeur/livraison.php', 'bi-truck', 'Livraison'],
        'portefeuille' => ['vendeur/portefeuille.php', 'bi-wallet2', 'Portefeuille'],
        'avis' => ['vendeur/avis.php', 'bi-star', 'Avis'],
        'abonnement' => ['vendeur/abonnement.php', 'bi-calendar-check', 'Abonnement'],
    ];

    // Le portefeuille ne reçoit que l'argent des paiements en ligne : masqué quand ils sont éteints.
    if (!paiement_en_ligne_actif()) {
        unset($entrees['portefeuille']);
    }

    $html = '<nav class="sm-menu-vendeur mb-4" aria-label="Espace vendeur"><ul>';
    foreach ($entrees as $cle => [$chemin, $icone, $libelle]) {
        $html .= '<li><a href="' . e(url($chemin)) . '"' . ($cle === $actif ? ' aria-current="page"' : '') . '>'
            . '<i class="bi ' . e($icone) . '" aria-hidden="true"></i> ' . e($libelle) . '</a></li>';
    }

    return $html . '</ul></nav>';
}
