<?php
/**
 * SAMA MAILLOT — administration et modération (§23).
 *
 * - Réservé au rôle « admin » ; chaque action sensible exige un motif et est journalisée (§31).
 * - Un compte DÉSACTIVÉ ou SUSPENDU ne peut plus se connecter (sessions fermées).
 * - Une BOUTIQUE suspendue (§21.2) : boutique et maillots masqués, aucune nouvelle commande,
 *   mais le vendeur garde l'accès à ses commandes en cours pour les livrer.
 * - Maillots : pas de validation préalable ; l'admin peut masquer, supprimer (logiquement)
 *   ou remettre en ligne un maillot qu'il avait masqué.
 * - Tous les chiffres affichés sont réels.
 */

declare(strict_types=1);

const STATUTS_UTILISATEUR = [
    'actif' => ['Actif', 'succes'],
    'desactive' => ['Désactivé', 'erreur'],
    'suspendu' => ['Suspendu', 'attention'],
];

const STATUTS_BOUTIQUE = [
    'active' => ['Active', 'succes'],
    'grace' => ['Période de grâce', 'attention'],
    'masquee' => ['Masquée', 'attention'],
    'suspendue' => ['Suspendue', 'erreur'],
];

const ADMIN_PAR_PAGE = 25;

/** À mettre en haut de chaque page d'administration. */
function exiger_admin(): array
{
    return exiger_connexion('admin');
}

/** Menu de l'espace administrateur. */
function menu_admin(string $actif): string
{
    $entrees = [
        'tableau' => ['admin/', 'bi-speedometer2', 'Tableau de bord'],
        'utilisateurs' => ['admin/utilisateurs.php', 'bi-people', 'Utilisateurs'],
        'maillots' => ['admin/maillots.php', 'bi-box-seam', 'Maillots'],
        'commandes' => ['admin/commandes.php', 'bi-receipt', 'Commandes'],
        'journal' => ['admin/journal.php', 'bi-journal-text', 'Journal d\'audit'],
    ];

    $html = '<nav class="sm-menu-vendeur mb-4" aria-label="Administration"><ul>';
    foreach ($entrees as $cle => [$chemin, $icone, $libelle]) {
        $html .= '<li><a href="' . e(url($chemin)) . '"' . ($cle === $actif ? ' aria-current="page"' : '') . '>'
            . '<i class="bi ' . e($icone) . '" aria-hidden="true"></i> ' . e($libelle) . '</a></li>';
    }

    return $html . '</ul></nav>';
}

/** Badge coloré générique (statuts utilisateur, boutique…). */
function badge_statut(array $statuts, string $statut): string
{
    [$libelle, $couleur] = $statuts[$statut] ?? [$statut, 'info'];

    return '<span class="sm-statut sm-statut--' . e($couleur) . '">' . e($libelle) . '</span>';
}

/** Motif d'une action d'administration : obligatoire, 5 à 255 caractères. */
function lire_motif_admin(): ?string
{
    $motif = trim(champ_post('motif'));

    return mb_strlen($motif) >= 5 ? mb_substr($motif, 0, 255) : null;
}

/* -------------------------------------------------------------------------
   Tableau de bord (§23.1) : chiffres réels uniquement
   ------------------------------------------------------------------------- */

function chiffres_tableau_de_bord(): array
{
    $pdo = bdd();
    $compter = static function (string $sql) use ($pdo): array {
        $resultat = [];
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_NUM) as [$cle, $nombre]) {
            $resultat[(string) $cle] = (int) $nombre;
        }

        return $resultat;
    };

    // Vendeurs par état d'abonnement (calculé comme dans l'espace vendeur)
    $abonnements = [];
    foreach ($pdo->query('SELECT * FROM boutiques WHERE supprime_le IS NULL')->fetchAll() as $boutique) {
        $etat = etat_abonnement($boutique)['libelle'];
        $abonnements[$etat] = ($abonnements[$etat] ?? 0) + 1;
    }

    return [
        'utilisateurs' => $compter('SELECT role, COUNT(*) FROM utilisateurs WHERE supprime_le IS NULL GROUP BY role'),
        'statuts_utilisateurs' => $compter('SELECT statut, COUNT(*) FROM utilisateurs WHERE supprime_le IS NULL GROUP BY statut'),
        'boutiques' => $compter('SELECT statut, COUNT(*) FROM boutiques WHERE supprime_le IS NULL GROUP BY statut'),
        'abonnements' => $abonnements,
        'maillots' => $compter('SELECT statut, COUNT(*) FROM maillots GROUP BY statut'),
        'commandes' => $compter('SELECT statut, COUNT(*) FROM commandes GROUP BY statut'),
        'volume_recu' => (int) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE statut = 'recue'")->fetchColumn(),
        'nouveaux_7_jours' => (int) $pdo->query('SELECT COUNT(*) FROM utilisateurs WHERE cree_le >= NOW() - INTERVAL 7 DAY')->fetchColumn(),
    ];
}

/** Files d'action prioritaires (§23.1) : [libellé, nombre, lien, icône]. */
function files_prioritaires(): array
{
    $pdo = bdd();
    $delai = intdiv(max(1, (int) parametre('confirmation_vendeur_heures', '24')), 2);

    return [
        ['Paiements d\'abonnement à valider', (int) $pdo->query("SELECT COUNT(*) FROM paiements_abonnement WHERE statut = 'en_verification'")->fetchColumn(),
            'admin/abonnements.php', 'bi-wallet2'],
        ['Réclamations en cours', nombre_litiges_ouverts(), 'admin/litiges.php', 'bi-shield-exclamation'],
        ['Remboursements à effectuer', (int) $pdo->query("SELECT COUNT(*) FROM remboursements WHERE statut = 'a_rembourser'")->fetchColumn(),
            'admin/paiements.php?onglet=remboursements', 'bi-arrow-counterclockwise'],
        ['Versements à faire', count(boutiques_a_verser()), 'admin/paiements.php', 'bi-send'],
        ['Paiements à vérifier', (int) $pdo->query("SELECT COUNT(*) FROM transactions_paiement WHERE statut = 'montant_incorrect'")->fetchColumn(),
            'admin/paiements.php?onglet=transactions', 'bi-exclamation-diamond'],
        ['Signalements à traiter', (int) $pdo->query("SELECT COUNT(*) FROM signalements WHERE statut = 'ouvert'")->fetchColumn(),
            'admin/signalements.php', 'bi-flag'],
        ['Messages à traiter', (int) $pdo->query("SELECT COUNT(*) FROM messages_contact WHERE statut = 'nouveau'")->fetchColumn(),
            'admin/messages.php', 'bi-envelope'],
        ['Boutiques suspendues', (int) $pdo->query("SELECT COUNT(*) FROM boutiques WHERE statut = 'suspendue' AND supprime_le IS NULL")->fetchColumn(),
            'admin/signalements.php', 'bi-shop'],
        ['Comptes inactifs', (int) $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE statut <> 'actif' AND supprime_le IS NULL")->fetchColumn(),
            'admin/utilisateurs.php?statut=inactifs', 'bi-person-x'],
        ['Commandes en retard (+' . $delai . ' h)',
            (int) $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'attente_confirmation' AND cree_le <= NOW() - INTERVAL $delai HOUR")->fetchColumn(),
            'admin/commandes.php?statut=attente_confirmation', 'bi-hourglass-split'],
        ['Maillots masqués', (int) $pdo->query("SELECT COUNT(*) FROM maillots WHERE statut = 'masque_admin'")->fetchColumn(),
            'admin/maillots.php?statut=masque_admin', 'bi-eye-slash'],
    ];
}

/* -------------------------------------------------------------------------
   Utilisateurs et boutiques (§23.2)
   ------------------------------------------------------------------------- */

/**
 * Liste filtrée et paginée des utilisateurs.
 *
 * @param array{q?: string, role?: string, statut?: string, boutique?: string} $filtres
 * @return array{utilisateurs: list<array>, total: int, pages: int, page: int}
 */
function rechercher_utilisateurs(array $filtres, int $page): array
{
    $conditions = ['u.supprime_le IS NULL'];
    $parametres = [];

    if (($filtres['q'] ?? '') !== '') {
        $conditions[] = '(u.email LIKE :q OR u.prenom LIKE :q2 OR u.nom LIKE :q3 OR u.telephone LIKE :q4 OR b.nom LIKE :q5)';
        $motif = '%' . $filtres['q'] . '%';
        $parametres += ['q' => $motif, 'q2' => $motif, 'q3' => $motif, 'q4' => $motif, 'q5' => $motif];
    }
    if (in_array($filtres['role'] ?? '', ['client', 'vendeur', 'admin'], true)) {
        $conditions[] = 'u.role = :role';
        $parametres['role'] = $filtres['role'];
    }
    if (($filtres['statut'] ?? '') === 'inactifs') {
        $conditions[] = "u.statut <> 'actif'";
    } elseif (isset(STATUTS_UTILISATEUR[$filtres['statut'] ?? ''])) {
        $conditions[] = 'u.statut = :statut';
        $parametres['statut'] = $filtres['statut'];
    }
    if (isset(STATUTS_BOUTIQUE[$filtres['boutique'] ?? ''])) {
        $conditions[] = 'b.statut = :boutique';
        $parametres['boutique'] = $filtres['boutique'];
    }

    $depuis = ' FROM utilisateurs u LEFT JOIN boutiques b ON b.utilisateur_id = u.id AND b.supprime_le IS NULL WHERE '
        . implode(' AND ', $conditions);

    $requete = bdd()->prepare('SELECT COUNT(*)' . $depuis);
    $requete->execute($parametres);
    $total = (int) $requete->fetchColumn();
    $pages = max(1, (int) ceil($total / ADMIN_PAR_PAGE));
    $page = min(max(1, $page), $pages);

    $requete = bdd()->prepare(
        'SELECT u.id, u.role, u.prenom, u.nom, u.email, u.telephone, u.statut, u.cree_le, u.derniere_connexion_le,
                b.id AS boutique_id, b.nom AS boutique_nom, b.statut AS boutique_statut'
        . $depuis . ' ORDER BY u.cree_le DESC, u.id DESC LIMIT ' . ADMIN_PAR_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_PAR_PAGE)
    );
    $requete->execute($parametres);

    return ['utilisateurs' => $requete->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/**
 * Change le statut d'un compte (actif, désactivé, suspendu). Motif obligatoire, journalisé.
 * Toutes les sessions de la personne sont fermées si le compte n'est plus actif.
 */
function changer_statut_utilisateur(array $admin, int $cibleId, string $nouveau, string $motif): ?string
{
    if (!isset(STATUTS_UTILISATEUR[$nouveau])) {
        return 'Statut inconnu.';
    }
    if ($cibleId === (int) $admin['id']) {
        return 'Vous ne pouvez pas modifier votre propre compte.';
    }

    $cible = trouver_utilisateur($cibleId);
    if ($cible === null) {
        return 'Compte introuvable.';
    }
    if ($cible['role'] === 'admin') {
        return 'Un compte administrateur ne peut pas être modifié ici.';
    }
    if ($cible['statut'] === $nouveau) {
        return null;
    }

    $sql = 'UPDATE utilisateurs SET statut = :statut' . ($nouveau !== 'actif' ? ', version_session = version_session + 1' : '') . ' WHERE id = :id';
    bdd()->prepare($sql)->execute(['statut' => $nouveau, 'id' => $cibleId]);
    if ($nouveau !== 'actif') {
        bdd()->prepare('DELETE FROM jetons_connexion WHERE utilisateur_id = :id')->execute(['id' => $cibleId]);
    }

    journaliser_action('admin_utilisateur_' . $nouveau, (int) $admin['id'], 'utilisateur', $cibleId, $cible['statut'], $nouveau . ' — ' . $motif);
    if ($nouveau === 'actif') {
        notifier($cibleId, 'compte_reactive', 'Votre compte est réactivé', 'Vous pouvez de nouveau utiliser SAMA MAILLOT.', 'compte/');
    }

    return null;
}

/** Suspend ou réactive une boutique (§21.2). Motif obligatoire, vendeur prévenu. */
function changer_statut_boutique(array $admin, int $boutiqueId, bool $suspendre, string $motif): ?string
{
    $requete = bdd()->prepare('SELECT * FROM boutiques WHERE id = :id AND supprime_le IS NULL');
    $requete->execute(['id' => $boutiqueId]);
    $boutique = $requete->fetch();

    if ($boutique === false) {
        return 'Boutique introuvable.';
    }
    if ($suspendre === ($boutique['statut'] === 'suspendue')) {
        return null;
    }

    $nouveau = $suspendre ? 'suspendue' : 'active';
    // Levée : la suspension automatique et la contestation sont effacées (l'historique reste au journal).
    bdd()->prepare('UPDATE boutiques SET statut = :statut' . ($suspendre ? '' : ', suspension_auto_le = NULL, contestation = NULL, contestation_le = NULL')
        . ' WHERE id = :id')->execute(['statut' => $nouveau, 'id' => $boutiqueId]);
    journaliser_action($suspendre ? 'admin_boutique_suspendue' : 'admin_boutique_reactivee', (int) $admin['id'], 'boutique', $boutiqueId,
        $boutique['statut'], $nouveau . ' — ' . $motif);

    $vendeurId = (int) $boutique['utilisateur_id'];
    if ($suspendre) {
        notifier($vendeurId, 'boutique_suspendue', 'Votre boutique est suspendue',
            'Motif : ' . $motif . '. Vos maillots sont masqués ; livrez vos commandes en cours.', 'vendeur/');
        prevenir_vendeur($vendeurId, 'SAMA MAILLOT : votre boutique est suspendue (' . $motif . '). Livrez vos commandes en cours. Contact : support SAMA MAILLOT.');
    } else {
        notifier($vendeurId, 'boutique_reactivee', 'Votre boutique est réactivée', 'Vos maillots sont de nouveau visibles.', 'vendeur/');
        prevenir_vendeur($vendeurId, 'SAMA MAILLOT : votre boutique est réactivée, vos maillots sont de nouveau visibles.');
    }

    return null;
}

/* -------------------------------------------------------------------------
   Maillots (§23.2) : masquer, supprimer, remettre en ligne
   ------------------------------------------------------------------------- */

/**
 * @param string $action 'masquer' | 'supprimer' | 'republier'
 */
function moderer_maillot(array $admin, int $maillotId, string $action, string $motif): ?string
{
    $requete = bdd()->prepare('SELECT m.*, b.utilisateur_id AS vendeur_id FROM maillots m JOIN boutiques b ON b.id = m.boutique_id WHERE m.id = :id');
    $requete->execute(['id' => $maillotId]);
    $maillot = $requete->fetch();

    if ($maillot === false || $maillot['statut'] === 'supprime') {
        return 'Maillot introuvable.';
    }

    [$sql, $nouveau] = match ($action) {
        'masquer' => ["UPDATE maillots SET statut = 'masque_admin' WHERE id = :id", 'masque_admin'],
        'supprimer' => ["UPDATE maillots SET statut = 'supprime', supprime_le = NOW() WHERE id = :id", 'supprime'],
        'republier' => $maillot['statut'] === 'masque_admin'
            ? ["UPDATE maillots SET statut = 'publie', publie_le = COALESCE(publie_le, NOW()) WHERE id = :id", 'publie']
            : [null, null],
        default => [null, null],
    };
    if ($sql === null) {
        return 'Action impossible pour ce maillot.';
    }

    bdd()->prepare($sql)->execute(['id' => $maillotId]);
    journaliser_action('admin_maillot_' . $action, (int) $admin['id'], 'maillot', $maillotId, $maillot['statut'], $nouveau . ' — ' . $motif);

    $vendeurId = (int) $maillot['vendeur_id'];
    $titre = $maillot['titre'];
    match ($action) {
        'masquer' => notifier($vendeurId, 'maillot_masque', 'Maillot masqué par l\'administration',
            '« ' . $titre . ' » — motif : ' . $motif, 'vendeur/maillot.php?id=' . $maillotId),
        'supprimer' => notifier($vendeurId, 'maillot_supprime', 'Maillot supprimé par l\'administration',
            '« ' . $titre . ' » — motif : ' . $motif, 'vendeur/maillots.php'),
        'republier' => notifier($vendeurId, 'maillot_republie', 'Maillot remis en ligne',
            '« ' . $titre . ' » est de nouveau visible.', 'vendeur/maillot.php?id=' . $maillotId),
    };
    if ($action !== 'republier') {
        prevenir_vendeur($vendeurId, 'SAMA MAILLOT : votre maillot « ' . $titre . ' » a été '
            . ($action === 'masquer' ? 'masqué' : 'supprimé') . ' par l\'administration (' . $motif . ').');
    }

    return null;
}

/* -------------------------------------------------------------------------
   Journal d'audit (§31)
   ------------------------------------------------------------------------- */

/**
 * @param array{action?: string, auteur?: string, du?: string, au?: string, objet?: string} $filtres
 */
function rechercher_journal(array $filtres, int $page): array
{
    $conditions = ['1 = 1'];
    $parametres = [];

    if (($filtres['action'] ?? '') !== '') {
        $conditions[] = 'j.action LIKE :action';
        $parametres['action'] = $filtres['action'] . '%';
    }
    if (($filtres['auteur'] ?? '') !== '') {
        $conditions[] = '(u.email LIKE :auteur OR u.nom LIKE :auteur2)';
        $parametres['auteur'] = '%' . $filtres['auteur'] . '%';
        $parametres['auteur2'] = '%' . $filtres['auteur'] . '%';
    }
    if (in_array($filtres['objet'] ?? '', ['utilisateur', 'boutique', 'maillot', 'commande'], true)) {
        $conditions[] = 'j.objet_type = :objet';
        $parametres['objet'] = $filtres['objet'];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres['du'] ?? '') === 1) {
        $conditions[] = 'j.cree_le >= :du';
        $parametres['du'] = $filtres['du'] . ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres['au'] ?? '') === 1) {
        $conditions[] = 'j.cree_le <= :au';
        $parametres['au'] = $filtres['au'] . ' 23:59:59';
    }

    $depuis = ' FROM journal_actions j LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id WHERE ' . implode(' AND ', $conditions);

    $requete = bdd()->prepare('SELECT COUNT(*)' . $depuis);
    $requete->execute($parametres);
    $total = (int) $requete->fetchColumn();
    $pages = max(1, (int) ceil($total / 50));
    $page = min(max(1, $page), $pages);

    $requete = bdd()->prepare(
        'SELECT j.*, u.email AS auteur_email, u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.role AS auteur_role'
        . $depuis . ' ORDER BY j.cree_le DESC, j.id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50)
    );
    $requete->execute($parametres);

    return ['lignes' => $requete->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/** Libellé lisible d'une action du journal. */
function libelle_action_journal(string $action): string
{
    $libelles = [
        'inscription_client' => 'Inscription client',
        'inscription_vendeur' => 'Inscription vendeur',
        'client_devenu_vendeur' => 'Client devenu vendeur',
        'telephone_verifie' => 'Téléphone vérifié',
        'commande_creee' => 'Commande créée',
        'commande_confirmee' => 'Commande confirmée',
        'commande_expediee' => 'Commande expédiée',
        'commande_livree' => 'Commande livrée',
        'commande_recue' => 'Commande reçue',
        'commande_annulee' => 'Commande annulée',
        'admin_utilisateur_actif' => 'Admin : compte réactivé',
        'admin_utilisateur_desactive' => 'Admin : compte désactivé',
        'admin_utilisateur_suspendu' => 'Admin : compte suspendu',
        'admin_boutique_suspendue' => 'Admin : boutique suspendue',
        'admin_boutique_reactivee' => 'Admin : boutique réactivée',
        'admin_maillot_masquer' => 'Admin : maillot masqué',
        'admin_maillot_supprimer' => 'Admin : maillot supprimé',
        'admin_maillot_republier' => 'Admin : maillot remis en ligne',
        'code_livraison_bloque' => 'Code de livraison bloqué',
        'admin_role_modifie' => 'Admin : rôle modifié',
        'signalement_vendeur' => 'Signalement d\'un vendeur',
        'signalement_maillot' => 'Signalement d\'un maillot',
        'suspension_automatique' => 'Suspension automatique (signalements)',
        'contestation_suspension' => 'Contestation de suspension',
        'admin_signalement_retenu' => 'Admin : signalement retenu',
        'admin_signalement_classe' => 'Admin : signalement classé',
        'admin_catalogue_categorie' => 'Admin : catégorie enregistrée',
        'admin_catalogue_equipe' => 'Admin : équipe enregistrée',
        'admin_catalogue_zone' => 'Admin : zone enregistrée',
        'admin_catalogue_activation' => 'Admin : élément du catalogue activé / désactivé',
        'admin_parametre_modifie' => 'Admin : paramètre modifié',
        'admin_message_traite' => 'Admin : message traité',
        'admin_message_nouveau' => 'Admin : message remis à traiter',
        'avis_publie' => 'Avis publié',
        'avis_modifie' => 'Avis modifié',
        'admin_avis_masque' => 'Admin : avis masqué',
        'admin_avis_reaffiche' => 'Admin : avis réaffiché',
        'litige_ouvert' => 'Réclamation ouverte',
        'litige_clos_amiable' => 'Réclamation close à l\'amiable',
        'admin_litige_fondee' => 'Admin : réclamation fondée',
        'admin_litige_non_fondee' => 'Admin : réclamation non fondée',
        'commande_en_litige' => 'Commande en litige',
        'commande_attente_confirmation' => 'Commande payée (à confirmer)',
        'commande_remboursee' => 'Commande remboursée',
        'admin_versement' => 'Admin : versement Wave au vendeur',
        'admin_remboursement' => 'Admin : client remboursé',
        'paiement_montant_incorrect' => 'Paiement : montant incorrect',
    ];

    return $libelles[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

/* -------------------------------------------------------------------------
   Mise en page du back-office et graphiques (dessinés en SVG côté serveur :
   aucune bibliothèque JavaScript, rien n'est inventé)
   ------------------------------------------------------------------------- */

/** Compteurs réels de la barre latérale. */
function compteurs_menu_admin(): array
{
    $pdo = bdd();

    return [
        'utilisateurs' => (int) $pdo->query('SELECT COUNT(*) FROM utilisateurs WHERE supprime_le IS NULL')->fetchColumn(),
        'vendeurs' => (int) $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'vendeur' AND supprime_le IS NULL")->fetchColumn(),
        'maillots' => (int) $pdo->query("SELECT COUNT(*) FROM maillots WHERE statut = 'publie' AND supprime_le IS NULL")->fetchColumn(),
        'commandes_attente' => (int) $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'attente_confirmation'")->fetchColumn(),
        'messages' => (int) $pdo->query("SELECT COUNT(*) FROM messages_contact WHERE statut = 'nouveau'")->fetchColumn(),
        'paiements' => (int) $pdo->query("SELECT COUNT(*) FROM paiements_abonnement WHERE statut = 'en_verification'")->fetchColumn(),
        'signalements' => (int) $pdo->query("SELECT COUNT(*) FROM signalements WHERE statut = 'ouvert'")->fetchColumn()
            + (int) $pdo->query("SELECT COUNT(*) FROM boutiques WHERE statut = 'suspendue' AND contestation IS NOT NULL")->fetchColumn(),
        'litiges' => nombre_litiges_ouverts(),
        'paiements_admin' => (int) $pdo->query("SELECT COUNT(*) FROM remboursements WHERE statut = 'a_rembourser'")->fetchColumn()
            + (int) $pdo->query("SELECT COUNT(*) FROM transactions_paiement WHERE statut = 'montant_incorrect'")->fetchColumn()
            + count(boutiques_a_verser()),
    ];
}

/**
 * Nombre de commandes par jour sur les N derniers jours (jours sans commande = 0).
 *
 * @return array<string, int> 'AAAA-MM-JJ' => nombre
 */
function commandes_par_jour(int $jours = 30): array
{
    $requete = bdd()->prepare(
        'SELECT DATE(cree_le) AS jour, COUNT(*) AS nombre FROM commandes
         WHERE cree_le >= CURDATE() - INTERVAL :jours DAY GROUP BY DATE(cree_le)'
    );
    $requete->bindValue('jours', $jours - 1, PDO::PARAM_INT);
    $requete->execute();
    $existants = array_column($requete->fetchAll(), 'nombre', 'jour');

    $serie = [];
    for ($i = $jours - 1; $i >= 0; $i--) {
        $jour = date('Y-m-d', strtotime("-$i day"));
        $serie[$jour] = (int) ($existants[$jour] ?? 0);
    }

    return $serie;
}

/** Derniers comptes créés. */
function derniers_inscrits(int $limite = 6): array
{
    return bdd()->query(
        'SELECT u.id, u.prenom, u.nom, u.email, u.role, u.cree_le, b.nom AS boutique_nom
         FROM utilisateurs u LEFT JOIN boutiques b ON b.utilisateur_id = u.id
         WHERE u.supprime_le IS NULL ORDER BY u.cree_le DESC, u.id DESC LIMIT ' . max(1, $limite)
    )->fetchAll();
}

/**
 * Courbe en aire (SVG) : une valeur par jour. Accessible : titre + description textuelle.
 *
 * @param array<string, int> $serie 'AAAA-MM-JJ' => valeur
 */
function graphique_courbe(array $serie, string $titre): string
{
    $largeur = 640;
    $hauteur = 220;
    $marge = ['haut' => 16, 'droite' => 26, 'bas' => 28, 'gauche' => 34];
    $zoneL = $largeur - $marge['gauche'] - $marge['droite'];
    $zoneH = $hauteur - $marge['haut'] - $marge['bas'];

    $valeurs = array_values($serie);
    $dates = array_keys($serie);
    $n = count($valeurs);
    $maximum = max(4, (int) ceil(max($valeurs ?: [0]) / 4) * 4);

    $x = static fn (int $i): float => $marge['gauche'] + ($n > 1 ? $i * $zoneL / ($n - 1) : $zoneL / 2);
    $y = static fn (int $v): float => $marge['haut'] + $zoneH - ($v / $maximum) * $zoneH;

    $svg = '<svg class="sm-graphique" viewBox="0 0 ' . $largeur . ' ' . $hauteur . '" role="img" aria-labelledby="g-titre g-desc">'
        . '<title id="g-titre">' . e($titre) . '</title>'
        . '<desc id="g-desc">' . e(array_sum($valeurs) . ' commandes au total sur ' . $n . ' jours.') . '</desc>'
        . '<defs><linearGradient id="g-degrade" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" stop-color="#D4AF37" stop-opacity="0.35"/><stop offset="1" stop-color="#D4AF37" stop-opacity="0"/></linearGradient></defs>';

    // Lignes de repère horizontales + valeurs
    for ($k = 0; $k <= 4; $k++) {
        $valeur = (int) ($maximum * $k / 4);
        $ligneY = round($y($valeur), 1);
        $svg .= '<line class="sm-graphique__repere" x1="' . $marge['gauche'] . '" x2="' . ($largeur - $marge['droite']) . '" y1="' . $ligneY . '" y2="' . $ligneY . '"/>'
            . '<text class="sm-graphique__texte" x="' . ($marge['gauche'] - 8) . '" y="' . ($ligneY + 4) . '" text-anchor="end">' . $valeur . '</text>';
    }

    // Aire et courbe
    $points = [];
    foreach ($valeurs as $i => $v) {
        $points[] = round($x($i), 1) . ',' . round($y($v), 1);
    }
    $base = round($y(0), 1);
    $svg .= '<polygon fill="url(#g-degrade)" points="' . round($x(0), 1) . ',' . $base . ' ' . implode(' ', $points) . ' ' . round($x($n - 1), 1) . ',' . $base . '"/>'
        . '<polyline class="sm-graphique__courbe" points="' . implode(' ', $points) . '"/>';

    // Points (jours avec commandes) et dates (1 sur 5)
    foreach ($valeurs as $i => $v) {
        if ($v > 0) {
            $svg .= '<circle class="sm-graphique__point" cx="' . round($x($i), 1) . '" cy="' . round($y($v), 1) . '" r="3.5"><title>'
                . e(date('d/m', strtotime($dates[$i])) . ' : ' . $v . ' commande' . ($v > 1 ? 's' : '')) . '</title></circle>';
        }
        if ($i % 5 === 0 || $i === $n - 1) {
            $ancre = $i === $n - 1 ? 'end' : ($i === 0 ? 'start' : 'middle');
            $svg .= '<text class="sm-graphique__texte" x="' . round($x($i), 1) . '" y="' . ($hauteur - 8) . '" text-anchor="' . $ancre . '">'
                . e(date('d/m', strtotime($dates[$i]))) . '</text>';
        }
    }

    return $svg . '</svg>';
}

/**
 * Anneau (SVG) : répartition. $segments = [[libellé, valeur, couleur CSS], …]
 */
function graphique_anneau(array $segments, string $titre): string
{
    $total = array_sum(array_column($segments, 1));
    $rayon = 70;
    $circonference = 2 * M_PI * $rayon;

    $svg = '<svg class="sm-anneau" viewBox="0 0 200 200" role="img" aria-label="' . e($titre . ' : ' . $total . ' au total') . '">'
        . '<circle cx="100" cy="100" r="' . $rayon . '" class="sm-anneau__fond"/>';

    $decalage = 0.0;
    foreach ($segments as [$libelle, $valeur, $couleur]) {
        if ($total === 0 || $valeur === 0) {
            continue;
        }
        $longueur = $circonference * $valeur / $total;
        $svg .= '<circle cx="100" cy="100" r="' . $rayon . '" fill="none" stroke="' . e($couleur) . '" stroke-width="26"'
            . ' stroke-dasharray="' . round($longueur, 2) . ' ' . round($circonference - $longueur, 2) . '"'
            . ' stroke-dashoffset="' . round(-$decalage, 2) . '" transform="rotate(-90 100 100)"><title>'
            . e($libelle . ' : ' . $valeur) . '</title></circle>';
        $decalage += $longueur;
    }

    return $svg . '<text x="100" y="98" text-anchor="middle" class="sm-anneau__total">' . $total . '</text>'
        . '<text x="100" y="120" text-anchor="middle" class="sm-anneau__libelle">au total</text></svg>';
}

/* -------------------------------------------------------------------------
   Changement de rôle (client, vendeur, administrateur)
   ------------------------------------------------------------------------- */

/**
 * Change le rôle d'un compte. Motif obligatoire, journalisé, personne prévenue.
 *   - vers « vendeur » : la boutique existante est réactivée ; sinon elle est créée
 *     ($nomBoutique et $ville obligatoires) ;
 *   - de « vendeur » vers un autre rôle : la boutique est masquée (rien n'est supprimé).
 * Un administrateur ne peut pas changer son propre rôle.
 */
function changer_role_utilisateur(array $admin, int $cibleId, string $nouveauRole, string $motif,
                                  string $nomBoutique = '', string $ville = ''): ?string
{
    if (!in_array($nouveauRole, ['client', 'vendeur', 'admin'], true)) {
        return 'Rôle inconnu.';
    }
    if ($cibleId === (int) $admin['id']) {
        return 'Vous ne pouvez pas changer votre propre rôle.';
    }

    $cible = trouver_utilisateur($cibleId);
    if ($cible === null) {
        return 'Compte introuvable.';
    }
    $ancienRole = $cible['role'];
    if ($ancienRole === $nouveauRole) {
        return 'Ce compte a déjà ce rôle.';
    }

    $boutique = boutique_du_vendeur($cibleId);
    $slug = '';
    if ($nouveauRole === 'vendeur' && $boutique === null) {
        $nomBoutique = trim($nomBoutique);
        $slug = creer_slug($nomBoutique);
        if (mb_strlen($nomBoutique) < 3 || mb_strlen($nomBoutique) > 60 || strlen($slug) < 3) {
            return 'Pour en faire un vendeur, indiquez le nom de sa boutique (3 à 60 caractères).';
        }
        if (mb_strlen(trim($ville)) < 2) {
            return 'Indiquez la ville de la boutique.';
        }
        $requete = bdd()->prepare('SELECT COUNT(*) FROM boutiques WHERE nom = :nom OR slug = :slug');
        $requete->execute(['nom' => $nomBoutique, 'slug' => $slug]);
        if ((int) $requete->fetchColumn() > 0) {
            return 'Ce nom de boutique est déjà pris.';
        }
    }

    $pdo = bdd();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE utilisateurs SET role = :role WHERE id = :id')->execute(['role' => $nouveauRole, 'id' => $cibleId]);

        if ($nouveauRole === 'vendeur') {
            if ($boutique === null) {
                $pdo->prepare(
                    'INSERT INTO boutiques (utilisateur_id, nom, slug, ville, numero_whatsapp) VALUES (:id, :nom, :slug, :ville, :whatsapp)'
                )->execute(['id' => $cibleId, 'nom' => $nomBoutique, 'slug' => $slug, 'ville' => trim($ville), 'whatsapp' => $cible['telephone']]);
            } elseif ($boutique['statut'] === 'masquee') {
                $pdo->prepare("UPDATE boutiques SET statut = 'active' WHERE id = :id")->execute(['id' => $boutique['id']]);
            }
        } elseif ($ancienRole === 'vendeur' && $boutique !== null && $boutique['statut'] !== 'suspendue') {
            $pdo->prepare("UPDATE boutiques SET statut = 'masquee' WHERE id = :id")->execute(['id' => $boutique['id']]);
        }

        $pdo->commit();
    } catch (PDOException $exception) {
        $pdo->rollBack();
        if ($exception->getCode() === '23000') {
            return 'Impossible : un autre compte « ' . $nouveauRole . ' » utilise déjà ce numéro de téléphone.';
        }
        throw $exception;
    }

    journaliser_action('admin_role_modifie', (int) $admin['id'], 'utilisateur', $cibleId, $ancienRole, $nouveauRole . ' — ' . $motif);

    $libelles = ['client' => 'client', 'vendeur' => 'vendeur', 'admin' => 'administrateur'];
    $liens = ['client' => 'compte/', 'vendeur' => 'vendeur/', 'admin' => 'admin/'];
    notifier($cibleId, 'role_modifie', 'Votre compte est maintenant « ' . $libelles[$nouveauRole] . ' »',
        $nouveauRole === 'vendeur' ? 'Votre espace vendeur est ouvert : complétez votre boutique et publiez vos maillots.'
            : 'Changement effectué par l\'administration.', $liens[$nouveauRole]);

    return null;
}
