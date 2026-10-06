<?php
/**
 * SAMA MAILLOT — réclamations et litiges (§16).
 *
 * Paiement à la livraison : la plateforme ne détient pas l'argent, elle fait de la MÉDIATION (§16.3).
 *   - Le client ouvre une réclamation sur une commande expédiée, livrée, ou reçue depuis moins
 *     de 48 h (paramètre reclamation_delai_heures) : motif, description, jusqu'à 3 photos privées.
 *   - La commande passe « En litige » : la réception automatique est suspendue.
 *   - Le vendeur a 48 h pour répondre ; client, vendeur et administration échangent dans un fil.
 *   - Le client peut clore à l'amiable (problème réglé) → la commande redevient « Reçue ».
 *   - Sinon l'administrateur tranche :
 *       fondée     → compte comme un signalement retenu du vendeur (§21 : suspension au seuil) ;
 *       non fondée → la commande redevient « Reçue ».
 *   - Les photos sont ré-encodées (WebP) et rangées hors du site public (stockage/litiges/),
 *     visibles seulement par les parties et l'administration.
 */

declare(strict_types=1);

const DOSSIER_LITIGES = DOSSIER_RACINE . '/stockage/litiges';
const LITIGE_PHOTOS_MAX = 3;
const LITIGE_MESSAGE_MAX = 2000;

const MOTIFS_LITIGE = [
    'non_recu' => 'Maillot non reçu',
    'different' => 'Différent de l\'annonce',
    'taille' => 'Mauvaise taille',
    'abime' => 'Maillot abîmé ou défectueux',
    'contrefacon' => 'Contrefaçon présentée comme officielle',
    'autre' => 'Autre',
];

// Correspondance avec les motifs de signalement (une réclamation fondée compte comme un signalement).
const MOTIF_LITIGE_VERS_SIGNALEMENT = [
    'non_recu' => 'arnaque', 'different' => 'non_conforme', 'taille' => 'non_conforme',
    'abime' => 'non_conforme', 'contrefacon' => 'contrefacon', 'autre' => 'autre',
];

const STATUTS_LITIGE = [
    'ouvert' => ['En cours', 'attention'],
    'clos_amiable' => ['Réglé à l\'amiable', 'succes'],
    'decide' => ['Décidé par l\'administration', 'info'],
];

/* -------------------------------------------------------------------------
   Lecture
   ------------------------------------------------------------------------- */

function litige_de_la_commande(int $commandeId): ?array
{
    $requete = bdd()->prepare('SELECT * FROM litiges WHERE commande_id = :id');
    $requete->execute(['id' => $commandeId]);
    $litige = $requete->fetch();

    return $litige === false ? null : $litige;
}

/** Litige complet (avec commande, boutique, client) pour l'administration. */
function litige_complet(int $id): ?array
{
    $requete = bdd()->prepare(
        'SELECT l.*, c.numero, c.total, c.statut AS statut_commande, c.telephone_client, c.mode_paiement, c.payee_le,
                b.nom AS boutique_nom, b.slug AS boutique_slug, b.utilisateur_id AS vendeur_id,
                u.prenom AS client_prenom, u.nom AS client_nom, u.email AS client_email
         FROM litiges l
         JOIN commandes c ON c.id = l.commande_id
         JOIN boutiques b ON b.id = l.boutique_id
         JOIN utilisateurs u ON u.id = l.client_id
         WHERE l.id = :id'
    );
    $requete->execute(['id' => $id]);
    $litige = $requete->fetch();

    return $litige === false ? null : $litige;
}

/** Messages du fil, avec leurs photos. */
function messages_du_litige(int $litigeId): array
{
    $requete = bdd()->prepare(
        'SELECT m.*, u.prenom, u.nom FROM litige_messages m LEFT JOIN utilisateurs u ON u.id = m.auteur_id
         WHERE m.litige_id = :id ORDER BY m.cree_le, m.id'
    );
    $requete->execute(['id' => $litigeId]);
    $messages = $requete->fetchAll();

    $photos = bdd()->prepare(
        'SELECT p.id, p.message_id FROM litige_photos p JOIN litige_messages m ON m.id = p.message_id WHERE m.litige_id = :id ORDER BY p.id'
    );
    $photos->execute(['id' => $litigeId]);
    $parMessage = [];
    foreach ($photos->fetchAll() as $photo) {
        $parMessage[(int) $photo['message_id']][] = (int) $photo['id'];
    }
    foreach ($messages as &$message) {
        $message['photos'] = $parMessage[(int) $message['id']] ?? [];
    }

    return $messages;
}

/** Litiges pour l'administration (en cours d'abord). */
function liste_litiges(string $filtre): array
{
    $where = $filtre === 'tous' ? '1' : "l.statut = 'ouvert'";

    return bdd()->query(
        "SELECT l.*, c.numero, c.total, b.nom AS boutique_nom, u.prenom AS client_prenom, u.nom AS client_nom
         FROM litiges l JOIN commandes c ON c.id = l.commande_id JOIN boutiques b ON b.id = l.boutique_id
         JOIN utilisateurs u ON u.id = l.client_id
         WHERE $where ORDER BY l.statut = 'ouvert' DESC, l.cree_le DESC LIMIT 200"
    )->fetchAll();
}

/* -------------------------------------------------------------------------
   Ouverture
   ------------------------------------------------------------------------- */

/** Le client peut-il ouvrir une réclamation sur cette commande ? null = oui, sinon le motif. */
function refus_reclamation(array $commande): ?string
{
    if (litige_de_la_commande((int) $commande['id']) !== null) {
        return 'Une réclamation existe déjà pour cette commande.';
    }
    if (in_array($commande['statut'], ['expediee', 'livree'], true)) {
        return null;
    }
    // Paiement en ligne : la réclamation se fait avant la réception (24 h ou 48 h après la livraison, §16.1) ;
    // une fois la réception confirmée, l'argent est débloqué pour le vendeur.
    if ($commande['statut'] === 'recue' && $commande['mode_paiement'] === 'en_ligne') {
        return 'La réception est confirmée : le délai de réclamation est terminé.';
    }
    if ($commande['statut'] === 'recue' && $commande['recue_le'] !== null) {
        $heures = max(1, (int) parametre('reclamation_delai_heures', '48'));
        if (strtotime($commande['recue_le']) + $heures * 3600 >= time()) {
            return null;
        }

        return 'Le délai de réclamation (' . $heures . ' h après la réception) est dépassé.';
    }

    return 'Une réclamation est possible une fois la commande expédiée.';
}

/**
 * Ouvre le litige : commande « En litige », premier message, photos, notifications.
 *
 * @return array{erreurs: array<string, string>, id: ?int}
 */
function ouvrir_litige(array $client, array $commande, string $motif, string $description, array $fichiers): array
{
    $erreurs = [];
    $description = trim($description);

    if (!isset(MOTIFS_LITIGE[$motif])) {
        $erreurs['motif'] = 'Choisissez le motif de votre réclamation.';
    }
    if (mb_strlen($description) < 20 || mb_strlen($description) > LITIGE_MESSAGE_MAX) {
        $erreurs['description'] = 'Décrivez le problème en 20 à ' . LITIGE_MESSAGE_MAX . ' caractères.';
    }
    if (count($fichiers) > LITIGE_PHOTOS_MAX) {
        $erreurs['photos'] = 'Au maximum ' . LITIGE_PHOTOS_MAX . ' photos.';
    }

    $photos = [];
    if ($erreurs === []) {
        try {
            foreach ($fichiers as $fichier) {
                $photos[] = enregistrer_photo_litige($fichier);
            }
        } catch (ErreurImage $exception) {
            supprimer_photos_litige($photos);
            $erreurs['photos'] = $exception->getMessage();
        }
    }
    if ($erreurs !== []) {
        return ['erreurs' => $erreurs, 'id' => null];
    }

    $heures = max(1, (int) parametre('litige_reponse_vendeur_heures', '48'));
    $pdo = bdd();

    try {
        changer_statut_commande((int) $commande['id'], 'en_litige', 'client', (int) $client['id'], MOTIFS_LITIGE[$motif]);
    } catch (ErreurCommande $exception) {
        supprimer_photos_litige($photos);

        return ['erreurs' => ['motif' => $exception->getMessage()], 'id' => null];
    }

    $pdo->prepare(
        "INSERT INTO litiges (commande_id, client_id, boutique_id, motif, statut_avant, reponse_limite_le)
         VALUES (:commande, :client, :boutique, :motif, :avant, NOW() + INTERVAL $heures HOUR)"
    )->execute([
        'commande' => $commande['id'], 'client' => $client['id'], 'boutique' => $commande['boutique_id'],
        'motif' => $motif, 'avant' => $commande['statut'],
    ]);
    $id = (int) $pdo->lastInsertId();
    ajouter_message_litige($id, (int) $client['id'], 'client', $description, $photos);

    journaliser_action('litige_ouvert', (int) $client['id'], 'commande', (int) $commande['id'], null, $motif);

    $numero = $commande['numero'];
    notifier((int) boutique_proprietaire((int) $commande['boutique_id']), 'litige_ouvert', "Réclamation sur la commande $numero",
        MOTIFS_LITIGE[$motif] . '. Répondez sous ' . $heures . ' h : sans réponse, l\'administration tranchera.',
        'vendeur/litige.php?id=' . $id);
    notifier_admins('litige_ouvert', "Nouvelle réclamation ($numero)",
        MOTIFS_LITIGE[$motif] . ' — par ' . $client['prenom'] . ' ' . $client['nom'] . '.', 'admin/litige.php?id=' . $id);

    return ['erreurs' => [], 'id' => $id];
}

/* -------------------------------------------------------------------------
   Fil de discussion et photos
   ------------------------------------------------------------------------- */

function ajouter_message_litige(int $litigeId, ?int $auteurId, string $role, string $message, array $photos = []): int
{
    bdd()->prepare('INSERT INTO litige_messages (litige_id, auteur_id, auteur_role, message) VALUES (:litige, :auteur, :role, :message)')
        ->execute(['litige' => $litigeId, 'auteur' => $auteurId, 'role' => $role, 'message' => $message]);
    $messageId = (int) bdd()->lastInsertId();

    $insertion = bdd()->prepare('INSERT INTO litige_photos (message_id, fichier) VALUES (:message, :fichier)');
    foreach ($photos as $fichier) {
        $insertion->execute(['message' => $messageId, 'fichier' => $fichier]);
    }

    return $messageId;
}

/**
 * Nouveau message d'une des parties (litige ouvert seulement), puis notification des autres.
 *
 * @return array<string, string> erreurs
 */
function repondre_litige(array $litige, array $auteur, string $role, string $message, array $fichiers): array
{
    if ($litige['statut'] !== 'ouvert') {
        return ['message' => 'Cette réclamation est close.'];
    }

    $message = trim($message);
    if (mb_strlen($message) < 2 || mb_strlen($message) > LITIGE_MESSAGE_MAX) {
        return ['message' => 'Votre message doit contenir entre 2 et ' . LITIGE_MESSAGE_MAX . ' caractères.'];
    }
    if (count($fichiers) > LITIGE_PHOTOS_MAX) {
        return ['photos' => 'Au maximum ' . LITIGE_PHOTOS_MAX . ' photos.'];
    }

    $photos = [];
    try {
        foreach ($fichiers as $fichier) {
            $photos[] = enregistrer_photo_litige($fichier);
        }
    } catch (ErreurImage $exception) {
        supprimer_photos_litige($photos);

        return ['photos' => $exception->getMessage()];
    }

    ajouter_message_litige((int) $litige['id'], (int) $auteur['id'], $role, $message, $photos);
    if ($role === 'vendeur' && (int) $litige['vendeur_a_repondu'] === 0) {
        bdd()->prepare('UPDATE litiges SET vendeur_a_repondu = 1 WHERE id = :id')->execute(['id' => $litige['id']]);
    }

    $numero = numero_commande_du_litige($litige);
    $titre = "Réclamation $numero : nouveau message";
    $extrait = mb_substr($message, 0, 140);
    $id = (int) $litige['id'];
    if ($role !== 'client') {
        notifier((int) $litige['client_id'], 'litige_message', $titre, $extrait, 'compte/litige.php?id=' . $id);
    }
    if ($role !== 'vendeur') {
        notifier((int) boutique_proprietaire((int) $litige['boutique_id']), 'litige_message', $titre, $extrait, 'vendeur/litige.php?id=' . $id);
    }
    if ($role !== 'admin') {
        notifier_admins('litige_message', $titre, $extrait, 'admin/litige.php?id=' . $id);
    }

    return [];
}

function numero_commande_du_litige(array $litige): string
{
    if (isset($litige['numero'])) {
        return (string) $litige['numero'];
    }
    $requete = bdd()->prepare('SELECT numero FROM commandes WHERE id = :id');
    $requete->execute(['id' => $litige['commande_id']]);

    return (string) $requete->fetchColumn();
}

/** Ré-encode la photo en WebP (métadonnées supprimées) dans le stockage privé. */
function enregistrer_photo_litige(array $fichier): string
{
    $image = redimensionner_image(ouvrir_image_envoyee($fichier, 200), 1600, 1600, false);

    if (!is_dir(DOSSIER_LITIGES)) {
        mkdir(DOSSIER_LITIGES, 0750, true);
    }
    $nom = bin2hex(random_bytes(16)) . '.webp';
    if (!imagewebp($image, DOSSIER_LITIGES . '/' . $nom, IMAGE_QUALITE_WEBP)) {
        throw new RuntimeException('Impossible d\'enregistrer la photo ' . $nom);
    }

    return $nom;
}

function supprimer_photos_litige(array $noms): void
{
    foreach ($noms as $nom) {
        if (preg_match('/^[a-f0-9]{32}\.webp$/', $nom) === 1) {
            @unlink(DOSSIER_LITIGES . '/' . $nom);
        }
    }
}

/** L'utilisateur peut-il voir ce litige ? (client, vendeur de la boutique ou administrateur) */
function acces_litige(array $litige, array $utilisateur): bool
{
    return $utilisateur['role'] === 'admin'
        || (int) $litige['client_id'] === (int) $utilisateur['id']
        || boutique_proprietaire((int) $litige['boutique_id']) === (int) $utilisateur['id'];
}

/* -------------------------------------------------------------------------
   Clôture
   ------------------------------------------------------------------------- */

/** Le client indique que le problème est réglé : la commande redevient « Reçue ». */
function clore_litige_amiable(array $litige, array $client): ?string
{
    if ($litige['statut'] !== 'ouvert') {
        return 'Cette réclamation est déjà close.';
    }

    $requete = bdd()->prepare("UPDATE litiges SET statut = 'clos_amiable', clos_le = NOW() WHERE id = :id AND statut = 'ouvert'");
    $requete->execute(['id' => $litige['id']]);
    if ($requete->rowCount() !== 1) {
        return 'Cette réclamation est déjà close.';
    }

    ajouter_message_litige((int) $litige['id'], (int) $client['id'], 'client', 'Le client a indiqué que le problème est réglé à l\'amiable.');
    changer_statut_commande((int) $litige['commande_id'], 'recue', 'client', (int) $client['id'], 'Réclamation réglée à l\'amiable');
    journaliser_action('litige_clos_amiable', (int) $client['id'], 'litige', (int) $litige['id']);

    $numero = numero_commande_du_litige($litige);
    notifier((int) boutique_proprietaire((int) $litige['boutique_id']), 'litige_clos', "Réclamation $numero réglée",
        'Le client a clos la réclamation à l\'amiable.', 'vendeur/litige.php?id=' . (int) $litige['id']);

    return null;
}

/**
 * Décision de l'administration ; dans les deux cas la commande est terminée (« Reçue »).
 * Fondée : compte en plus comme un signalement retenu du vendeur (suspension au seuil).
 */
function decider_litige(array $admin, array $litige, string $decision, string $motif): ?string
{
    if (!in_array($decision, ['fondee', 'non_fondee'], true)) {
        return 'Décision inconnue.';
    }
    $motif = trim($motif);
    if (mb_strlen($motif) < 5 || mb_strlen($motif) > 500) {
        return 'Expliquez la décision (5 à 500 caractères) : elle est envoyée au client et au vendeur.';
    }
    if ($litige['statut'] !== 'ouvert') {
        return 'Cette réclamation est déjà close.';
    }

    $requete = bdd()->prepare(
        "UPDATE litiges SET statut = 'decide', decision = :decision, decision_motif = :motif, decide_par = :admin, clos_le = NOW()
         WHERE id = :id AND statut = 'ouvert'"
    );
    $requete->execute(['decision' => $decision, 'motif' => $motif, 'admin' => $admin['id'], 'id' => $litige['id']]);
    if ($requete->rowCount() !== 1) {
        return 'Cette réclamation est déjà close.';
    }

    $libelle = $decision === 'fondee' ? 'Réclamation jugée fondée' : 'Réclamation jugée non fondée';
    ajouter_message_litige((int) $litige['id'], (int) $admin['id'], 'admin', $libelle . ' : ' . $motif);
    // Paiement en ligne et réclamation fondée : le client est remboursé (l'argent bloqué ne va pas au vendeur).
    $commandeLitige = bdd()->prepare('SELECT mode_paiement, payee_le, total FROM commandes WHERE id = :id');
    $commandeLitige->execute(['id' => $litige['commande_id']]);
    $commandeLitige = $commandeLitige->fetch();
    $rembourser = $decision === 'fondee' && $commandeLitige['mode_paiement'] === 'en_ligne' && $commandeLitige['payee_le'] !== null;
    changer_statut_commande((int) $litige['commande_id'], $rembourser ? 'remboursee' : 'recue', 'admin', (int) $admin['id'], $libelle);
    $libelleClient = $rembourser ? $libelle . ' : vous serez remboursé de ' . format_fcfa((int) $commandeLitige['total']) : $libelle;
    journaliser_action('admin_litige_' . $decision, (int) $admin['id'], 'litige', (int) $litige['id'], null, $motif);

    $boutiqueId = (int) $litige['boutique_id'];
    if ($decision === 'fondee') {
        // Compte comme un signalement retenu (§16.3 + §21) : 3 clients différents → suspension.
        bdd()->prepare(
            "INSERT INTO signalements (auteur_id, type, boutique_id, motif, description, statut, decision, traite_par, traite_le)
             VALUES (:auteur, 'vendeur', :boutique, :motif, :description, 'retenu', :decision, :admin, NOW())"
        )->execute([
            'auteur' => $litige['client_id'], 'boutique' => $boutiqueId,
            'motif' => MOTIF_LITIGE_VERS_SIGNALEMENT[$litige['motif']] ?? 'autre',
            'description' => 'Réclamation fondée sur la commande ' . numero_commande_du_litige($litige),
            'decision' => mb_substr($motif, 0, 255), 'admin' => $admin['id'],
        ]);
        verifier_suspension_automatique($boutiqueId);
    }

    $numero = numero_commande_du_litige($litige);
    $id = (int) $litige['id'];
    notifier((int) $litige['client_id'], 'litige_decide', "Réclamation $numero : décision", $libelleClient . '. ' . $motif, 'compte/litige.php?id=' . $id);
    notifier((int) boutique_proprietaire($boutiqueId), 'litige_decide', "Réclamation $numero : décision", $libelle . '. ' . $motif, 'vendeur/litige.php?id=' . $id);

    return null;
}

/* -------------------------------------------------------------------------
   Affichage
   ------------------------------------------------------------------------- */

function badge_statut_litige(array $litige): string
{
    [$libelle, $couleur] = STATUTS_LITIGE[$litige['statut']] ?? [$litige['statut'], 'info'];
    if ($litige['statut'] === 'decide') {
        $libelle = $litige['decision'] === 'fondee' ? 'Fondée' : 'Non fondée';
        $couleur = $litige['decision'] === 'fondee' ? 'erreur' : 'succes';
    }

    return '<span class="sm-statut sm-statut--' . e($couleur) . '">' . e($libelle) . '</span>';
}

/** Nombre de litiges ouverts (menu et files prioritaires de l'administration). */
function nombre_litiges_ouverts(): int
{
    return (int) bdd()->query("SELECT COUNT(*) FROM litiges WHERE statut = 'ouvert'")->fetchColumn();
}
