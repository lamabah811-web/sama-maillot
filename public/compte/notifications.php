<?php
/**
 * SAMA MAILLOT — « Notifications » (§22.1) : liste, ouverture (marque comme lue), tout marquer lu.
 *
 *   compte/notifications.php            → liste (les 50 plus récentes)
 *   compte/notifications.php?ouvrir=12  → marque la notification comme lue puis ouvre sa page
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();
$utilisateurId = (int) $utilisateur['id'];

// Ouvrir une notification : marquée lue, puis redirection vers son lien interne.
if (isset($_GET['ouvrir'])) {
    $notification = notification_de((int) $_GET['ouvrir'], $utilisateurId);
    if ($notification === null) {
        afficher_erreur(404);
    }
    marquer_notifications_lues($utilisateurId, (int) $notification['id']);

    $lien = (string) $notification['lien'];
    // Uniquement un chemin interne simple (jamais une adresse externe).
    rediriger(preg_match('#^[a-z0-9/_.\-]+(\?[a-z0-9=&_-]*)?$#i', $lien) === 1 ? $lien : 'compte/notifications.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifier_csrf() && champ_post('action') === 'tout_lire') {
        marquer_notifications_lues($utilisateurId);
        message_flash('succes', 'Toutes vos notifications sont marquées comme lues.');
    }
    rediriger('compte/notifications.php');
}

$notifications = notifications_de($utilisateurId);
$non_lues = count(array_filter($notifications, static fn (array $n): bool => $n['lue_le'] === null));

$titre_page = 'Notifications';
$sans_indexation = true;
$page_active = 'notifications';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <span class="sm-sur-titre">Mon compte</span>
                        <h1 class="sm-titre-section mb-0">Notifications</h1>
                    </div>
                    <?php if ($non_lues > 0): ?>
                        <form method="post">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="action" value="tout_lire">
                            <button class="btn btn-contour btn-sm" type="submit">
                                <i class="bi bi-check2-all" aria-hidden="true"></i> Tout marquer comme lu
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($notifications === []): ?>
                    <div class="sm-etat-vide">
                        <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-bell"></i></div>
                        <h2 class="h5">Aucune notification</h2>
                        <p class="mb-0">Vous serez prévenu ici de vos commandes : nouvelle commande, confirmation, expédition, livraison.</p>
                    </div>
                <?php else: ?>
                    <ul class="sm-liste-notifications">
                        <?php foreach ($notifications as $notification): ?>
                            <?php $non_lue = $notification['lue_le'] === null; ?>
                            <li>
                                <a class="sm-notification<?= $non_lue ? ' sm-notification--non-lue' : '' ?>"
                                   href="<?= e(url('compte/notifications.php?ouvrir=' . (int) $notification['id'])) ?>">
                                    <span class="sm-notification__icone" aria-hidden="true">
                                        <i class="bi <?= e(ICONES_NOTIFICATION[$notification['type']] ?? 'bi-bell') ?>"></i>
                                    </span>
                                    <span class="sm-notification__corps">
                                        <strong><?= e($notification['titre']) ?></strong>
                                        <span><?= e($notification['message']) ?></span>
                                        <span class="sm-notification__date"><?= e(date_relative($notification['cree_le'])) ?></span>
                                    </span>
                                    <?php if ($non_lue): ?>
                                        <span class="sm-notification__point"><span class="visually-hidden">Non lue</span></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
