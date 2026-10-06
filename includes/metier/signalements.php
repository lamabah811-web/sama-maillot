<?php
/**
 * SAMA MAILLOT — signalements et suspension automatique (§21).
 *
 * Règle du porteur du projet (plus stricte que le §21.3) : seul un client ayant passé une
 * commande NON ANNULÉE chez ce vendeur peut signaler ce vendeur ou l'un de ses maillots.
 *   - Vendeur : un signalement par client et par vendeur sur 90 jours ; motif + description.
 *   - Maillot : un signalement en cours par client et par maillot ; va en modération.
 *   - 3 signalements de vendeur par 3 clients différents sur 90 jours glissants → boutique
 *     suspendue automatiquement (maillots masqués, plus de nouvelles commandes, commandes en
 *     cours toujours accessibles). Seul l'administrateur lève la suspension.
 *   - Un signalement « classé » (jugé infondé) ne compte plus.
 */

declare(strict_types=1);

const MOTIFS_SIGNALEMENT_VENDEUR = [
    'arnaque' => 'Arnaque ou maillot non livré',
    'non_conforme' => 'Maillot non conforme à l\'annonce',
    'contrefacon' => 'Contrefaçon présentée comme officielle',
    'comportement' => 'Comportement inapproprié',
    'autre' => 'Autre',
];

const MOTIFS_SIGNALEMENT_MAILLOT = [
    'contrefacon' => 'Contrefaçon présentée comme officielle',
    'photo_volee' => 'Photo volée ou trompeuse',
    'contenu' => 'Contenu inapproprié',
    'prix' => 'Prix trompeur',
    'autre' => 'Autre',
];

const STATUTS_SIGNALEMENT = [
    'ouvert' => ['À traiter', 'attention'],
    'retenu' => ['Retenu', 'erreur'],
    'classe' => ['Classé (infondé)', 'succes'],
];

/** Vrai si ce client a passé au moins une commande non annulée chez cette boutique. */
function a_commande_chez(int $clientId, int $boutiqueId): bool
{
    $requete = bdd()->prepare(
        "SELECT 1 FROM commandes WHERE client_id = :client AND boutique_id = :boutique AND statut NOT IN ('annulee') LIMIT 1"
    );
    $requete->execute(['client' => $clientId, 'boutique' => $boutiqueId]);

    return $requete->fetchColumn() !== false;
}

/** Refus de signaler un vendeur (message), ou null si c'est possible. */
function refus_signalement_vendeur(?array $utilisateur, int $boutiqueId): ?string
{
    if ($utilisateur === null) {
        return 'Connectez-vous pour signaler un vendeur.';
    }
    if (boutique_proprietaire($boutiqueId) === (int) $utilisateur['id']) {
        return 'Vous ne pouvez pas signaler votre propre boutique.';
    }
    if (!a_commande_chez((int) $utilisateur['id'], $boutiqueId)) {
        return 'Seuls les clients ayant passé une commande chez ce vendeur peuvent le signaler.';
    }

    $jours = max(1, (int) parametre('signalements_periode_jours', '90'));
    $requete = bdd()->prepare(
        "SELECT 1 FROM signalements WHERE auteur_id = :auteur AND boutique_id = :boutique AND type = 'vendeur'
         AND cree_le >= NOW() - INTERVAL $jours DAY LIMIT 1"
    );
    $requete->execute(['auteur' => $utilisateur['id'], 'boutique' => $boutiqueId]);
    if ($requete->fetchColumn() !== false) {
        return 'Vous avez déjà signalé ce vendeur ces ' . $jours . ' derniers jours. Notre équipe examine votre signalement.';
    }

    return null;
}

/** Refus de signaler un maillot (message), ou null si c'est possible. */
function refus_signalement_maillot(?array $utilisateur, array $maillot): ?string
{
    if ($utilisateur === null) {
        return 'Connectez-vous pour signaler un maillot.';
    }
    $boutiqueId = (int) $maillot['boutique_id'];
    if (boutique_proprietaire($boutiqueId) === (int) $utilisateur['id']) {
        return 'Vous ne pouvez pas signaler un maillot de votre propre boutique.';
    }
    if (!a_commande_chez((int) $utilisateur['id'], $boutiqueId)) {
        return 'Seuls les clients ayant passé une commande chez ce vendeur peuvent signaler ses maillots.';
    }

    $requete = bdd()->prepare(
        "SELECT 1 FROM signalements WHERE auteur_id = :auteur AND maillot_id = :maillot AND statut = 'ouvert' LIMIT 1"
    );
    $requete->execute(['auteur' => $utilisateur['id'], 'maillot' => $maillot['id']]);
    if ($requete->fetchColumn() !== false) {
        return 'Vous avez déjà signalé ce maillot. Notre équipe examine votre signalement.';
    }

    return null;
}

/**
 * Enregistre un signalement (après contrôle des droits par l'appelant), prévient les
 * administrateurs et, pour un vendeur, vérifie la suspension automatique.
 */
function creer_signalement(array $auteur, string $type, int $boutiqueId, ?int $maillotId, string $motif, string $description): int
{
    bdd()->prepare(
        'INSERT INTO signalements (auteur_id, type, boutique_id, maillot_id, motif, description)
         VALUES (:auteur, :type, :boutique, :maillot, :motif, :description)'
    )->execute([
        'auteur' => $auteur['id'], 'type' => $type, 'boutique' => $boutiqueId,
        'maillot' => $maillotId, 'motif' => $motif, 'description' => $description,
    ]);
    $id = (int) bdd()->lastInsertId();

    journaliser_action('signalement_' . $type, (int) $auteur['id'], $type === 'vendeur' ? 'boutique' : 'maillot',
        $type === 'vendeur' ? $boutiqueId : $maillotId, null, $motif);

    $motifs = $type === 'vendeur' ? MOTIFS_SIGNALEMENT_VENDEUR : MOTIFS_SIGNALEMENT_MAILLOT;
    notifier_admins('signalement_nouveau', 'Nouveau signalement de ' . ($type === 'vendeur' ? 'vendeur' : 'maillot'),
        ($motifs[$motif] ?? $motif) . ' — par ' . $auteur['prenom'] . ' ' . $auteur['nom'] . '.', 'admin/signalements.php');

    // Suspension automatique au seuil ; sinon, avertissement automatique au vendeur.
    $suspendue = $type === 'vendeur' && verifier_suspension_automatique($boutiqueId);
    if (!$suspendue) {
        avertir_vendeur_signale($boutiqueId, $type, $maillotId, $motifs[$motif] ?? $motif);
    }

    return $id;
}

/**
 * Avertissement automatique au vendeur signalé (notification + SMS).
 * L'identité du client n'est jamais révélée (protection contre les représailles).
 */
function avertir_vendeur_signale(int $boutiqueId, string $type, ?int $maillotId, string $libelleMotif): void
{
    $vendeurId = (int) boutique_proprietaire($boutiqueId);

    if ($type === 'vendeur') {
        $seuil = max(1, (int) parametre('signalements_avant_suspension', '3'));
        $jours = max(1, (int) parametre('signalements_periode_jours', '90'));
        $nombre = signaleurs_distincts($boutiqueId);
        $message = 'Motif : ' . $libelleMotif . '. ' . $nombre . ' signalement' . ($nombre > 1 ? 's' : '') . ' de clients sur '
            . $jours . ' jours : à ' . $seuil . ', votre boutique sera suspendue automatiquement. Contactez vos clients pour régler le problème.';
        notifier($vendeurId, 'signalement_avertissement', 'Avertissement : votre boutique a été signalée', $message, 'vendeur/');
        prevenir_vendeur($vendeurId, 'SAMA MAILLOT : avertissement, un client a signalé votre boutique (' . $libelleMotif . '). '
            . $nombre . '/' . $seuil . ' avant suspension automatique.');

        return;
    }

    $titre = '';
    if ($maillotId !== null) {
        $requete = bdd()->prepare('SELECT titre FROM maillots WHERE id = :id');
        $requete->execute(['id' => $maillotId]);
        $titre = (string) $requete->fetchColumn();
    }
    notifier($vendeurId, 'signalement_avertissement', 'Avertissement : un de vos maillots a été signalé',
        '« ' . $titre . ' » — motif : ' . $libelleMotif . '. Vérifiez l\'annonce : l\'administration peut la masquer.',
        $maillotId !== null ? 'vendeur/maillot.php?id=' . $maillotId : 'vendeur/maillots.php');
    prevenir_vendeur($vendeurId, 'SAMA MAILLOT : avertissement, votre maillot « ' . $titre . ' » a été signalé (' . $libelleMotif . '). Vérifiez l\'annonce.');
}

/** Notification à tous les administrateurs actifs. */
function notifier_admins(string $type, string $titre, string $message, string $lien): void
{
    $admins = bdd()->query("SELECT id FROM utilisateurs WHERE role = 'admin' AND statut = 'actif' AND supprime_le IS NULL")
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        notifier((int) $adminId, $type, $titre, $message, $lien);
    }
}

/** Nombre de clients DIFFÉRENTS ayant signalé ce vendeur (non classés) sur la période. */
function signaleurs_distincts(int $boutiqueId): int
{
    $jours = max(1, (int) parametre('signalements_periode_jours', '90'));
    $requete = bdd()->prepare(
        "SELECT COUNT(DISTINCT auteur_id) FROM signalements
         WHERE boutique_id = :boutique AND type = 'vendeur' AND statut <> 'classe' AND cree_le >= NOW() - INTERVAL $jours DAY"
    );
    $requete->execute(['boutique' => $boutiqueId]);

    return (int) $requete->fetchColumn();
}

/** Suspension automatique au seuil (§21.2). Renvoie vrai si la boutique vient d'être suspendue. */
function verifier_suspension_automatique(int $boutiqueId): bool
{
    $seuil = max(1, (int) parametre('signalements_avant_suspension', '3'));
    if (signaleurs_distincts($boutiqueId) < $seuil) {
        return false;
    }

    $requete = bdd()->prepare(
        "UPDATE boutiques SET statut = 'suspendue', suspension_auto_le = NOW(), contestation = NULL, contestation_le = NULL
         WHERE id = :id AND statut <> 'suspendue' AND supprime_le IS NULL"
    );
    $requete->execute(['id' => $boutiqueId]);
    if ($requete->rowCount() !== 1) {
        return false;
    }

    journaliser_action('suspension_automatique', null, 'boutique', $boutiqueId, null, $seuil . ' clients différents ont signalé ce vendeur');

    $vendeurId = (int) boutique_proprietaire($boutiqueId);
    notifier($vendeurId, 'boutique_suspendue', 'Votre boutique est suspendue',
        'Plusieurs clients ont signalé votre boutique. Livrez vos commandes en cours et envoyez votre contestation depuis votre tableau de bord.',
        'vendeur/');
    prevenir_vendeur($vendeurId, 'SAMA MAILLOT : votre boutique est suspendue suite à plusieurs signalements de clients. '
        . 'Vous pouvez contester depuis votre espace vendeur.');
    notifier_admins('suspension_automatique', 'Suspension automatique d\'une boutique',
        $seuil . ' clients différents ont signalé ce vendeur : sa boutique est suspendue.', 'admin/signalements.php');

    return true;
}

/** Décision de l'administrateur : retenir ou classer (infondé). */
function traiter_signalement(array $admin, int $id, string $decision, string $motif): ?string
{
    if (!in_array($decision, ['retenu', 'classe'], true)) {
        return 'Décision inconnue.';
    }

    $requete = bdd()->prepare('UPDATE signalements SET statut = :statut, decision = :motif, traite_par = :admin, traite_le = NOW() WHERE id = :id');
    $requete->execute(['statut' => $decision, 'motif' => $motif, 'admin' => $admin['id'], 'id' => $id]);
    if ($requete->rowCount() !== 1) {
        return 'Signalement introuvable.';
    }

    journaliser_action('admin_signalement_' . $decision, (int) $admin['id'], 'signalement', $id, null, $motif);

    return null;
}

/** Le vendeur suspendu envoie sa contestation (§21.2). */
function envoyer_contestation(array $vendeur, array $boutique, string $texte): ?string
{
    if ($boutique['statut'] !== 'suspendue') {
        return 'Votre boutique n\'est pas suspendue.';
    }
    if (mb_strlen($texte) < 20 || mb_strlen($texte) > 2000) {
        return 'Votre contestation doit contenir entre 20 et 2 000 caractères.';
    }

    bdd()->prepare('UPDATE boutiques SET contestation = :texte, contestation_le = NOW() WHERE id = :id')
        ->execute(['texte' => $texte, 'id' => $boutique['id']]);
    journaliser_action('contestation_suspension', (int) $vendeur['id'], 'boutique', (int) $boutique['id']);
    notifier_admins('contestation', 'Contestation de suspension', '« ' . $boutique['nom'] . ' » conteste sa suspension.', 'admin/signalements.php');

    return null;
}

/** Lien « Signaler » à afficher (null si ce visiteur ne peut pas signaler). */
function lien_signaler_vendeur(?array $utilisateur, int $boutiqueId): ?string
{
    return refus_signalement_vendeur($utilisateur, $boutiqueId) === null ? url('signalement/?boutique=' . $boutiqueId) : null;
}
