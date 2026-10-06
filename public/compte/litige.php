<?php
/**
 * SAMA MAILLOT — suivi d'une réclamation, côté client (§16).
 * Fil d'échanges avec le vendeur et l'administration ; le client peut clore à l'amiable.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();

$litige = litige_complet((int) ($_GET['id'] ?? $_POST['id'] ?? 0));
if ($litige === null || (int) $litige['client_id'] !== (int) $utilisateur['id']) {
    afficher_erreur(404);
}
$id = (int) $litige['id'];
$erreurs = [];
$valeur_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeur_message = trim((string) ($_POST['message'] ?? ''));

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif (champ_post('action') === 'clore') {
        $refus = clore_litige_amiable($litige, $utilisateur);
        if ($refus === null) {
            message_flash('succes', 'Merci : la réclamation est close à l\'amiable et la commande est terminée.');
            rediriger('compte/litige.php?id=' . $id);
        }
        $erreurs['general'] = $refus;
    } else {
        $erreurs = repondre_litige($litige, $utilisateur, 'client', $valeur_message, fichiers_envoyes('photos'));
        if ($erreurs === []) {
            message_flash('succes', 'Votre message est envoyé.');
            rediriger('compte/litige.php?id=' . $id);
        }
    }
}

$messages = messages_du_litige($id);
$role_fil = 'client';

$titre_page = 'Réclamation ' . $litige['numero'];
$sans_indexation = true;
$page_active = 'mes-commandes';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb sm-fil-ariane">
                <li class="breadcrumb-item"><a href="<?= e(url_page('mes-commandes')) ?>">Mes commandes</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url('compte/commande.php?id=' . (int) $litige['commande_id'])) ?>"><?= e($litige['numero']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Réclamation</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <h1 class="sm-titre-section mb-0 me-2">Réclamation</h1>
            <?= badge_statut_litige($litige) ?>
        </div>

        <?= alerte_formulaire($erreurs) ?>

        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <?php if ($litige['statut'] === 'decide'): ?>
                    <div class="alert <?= $litige['decision'] === 'fondee' ? 'alert-success' : 'alert-secondary' ?>" role="status">
                        <strong>Décision de SAMA MAILLOT :</strong> réclamation <?= $litige['decision'] === 'fondee' ? 'fondée' : 'non fondée' ?>.
                        <?= e($litige['decision_motif']) ?>
                    </div>
                <?php endif; ?>
                <?php require __DIR__ . '/../../includes/fil_litige.php'; ?>
            </div>

            <div class="col-12 col-lg-4">
                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold">Dossier</h2>
                    <dl class="sm-recap__lignes mb-0">
                        <dt>Motif</dt>
                        <dd><?= e(MOTIFS_LITIGE[$litige['motif']] ?? $litige['motif']) ?></dd>
                        <dt>Boutique</dt>
                        <dd><?= e($litige['boutique_nom']) ?></dd>
                        <dt>Ouverte</dt>
                        <dd><?= e(date_commande($litige['cree_le'])) ?></dd>
                        <?php if ($litige['statut'] === 'ouvert'): ?>
                            <dt>Réponse du vendeur</dt>
                            <dd><?= (int) $litige['vendeur_a_repondu'] === 1 ? 'Reçue' : 'Attendue avant le ' . e(date_commande($litige['reponse_limite_le'])) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>

                <?php if ($litige['statut'] === 'ouvert'): ?>
                    <form method="post" class="sm-tuile">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <input type="hidden" name="action" value="clore">
                        <h2 class="h6 fw-bold">Problème réglé ?</h2>
                        <p class="small text-body-secondary">Si vous avez trouvé un accord avec le vendeur, clôturez la réclamation : la commande sera terminée.</p>
                        <button class="btn btn-contour w-100" type="submit"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> Clore à l'amiable</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
