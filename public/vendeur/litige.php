<?php
/**
 * SAMA MAILLOT — espace vendeur : réclamation d'un client sur une commande (§16).
 * Le vendeur a 48 h pour répondre ; sans réponse, l'administration tranche.
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();

$litige = litige_complet((int) ($_GET['id'] ?? $_POST['id'] ?? 0));
if ($litige === null || (int) $litige['boutique_id'] !== (int) $boutique['id']) {
    afficher_erreur(404);
}
$id = (int) $litige['id'];
$erreurs = [];
$valeur_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeur_message = trim((string) ($_POST['message'] ?? ''));

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } else {
        $erreurs = repondre_litige($litige, $utilisateur, 'vendeur', $valeur_message, fichiers_envoyes('photos'));
        if ($erreurs === []) {
            message_flash('succes', 'Votre réponse est envoyée au client et à l\'administration.');
            rediriger('vendeur/litige.php?id=' . $id);
        }
    }
}

$messages = messages_du_litige($id);
$role_fil = 'vendeur';
$en_retard = $litige['statut'] === 'ouvert' && (int) $litige['vendeur_a_repondu'] === 0 && strtotime($litige['reponse_limite_le']) < time();

$titre_page = 'Réclamation ' . $litige['numero'];
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <?= menu_vendeur('commandes') ?>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <a class="btn btn-sm btn-contour" href="<?= e(url('vendeur/commande.php?id=' . (int) $litige['commande_id'])) ?>">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= e($litige['numero']) ?>
            </a>
            <h1 class="sm-titre-section mb-0 mx-2">Réclamation</h1>
            <?= badge_statut_litige($litige) ?>
        </div>

        <?= alerte_formulaire(array_intersect_key($erreurs, ['general' => 1])) ?>

        <?php if ($litige['statut'] === 'ouvert' && (int) $litige['vendeur_a_repondu'] === 0): ?>
            <div class="alert <?= $en_retard ? 'alert-danger' : 'alert-warning' ?>" role="status">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <?= $en_retard
                    ? 'Le délai de réponse est dépassé : répondez au plus vite, l\'administration va trancher.'
                    : 'Répondez avant le <strong>' . e(date_commande($litige['reponse_limite_le'])) . '</strong>. Sans réponse, l\'administration tranchera.' ?>
            </div>
        <?php endif; ?>
        <?php if ($litige['statut'] === 'decide'): ?>
            <div class="alert <?= $litige['decision'] === 'fondee' ? 'alert-danger' : 'alert-success' ?>" role="status">
                <strong>Décision de SAMA MAILLOT :</strong> réclamation <?= $litige['decision'] === 'fondee' ? 'fondée (comptée comme un signalement)' : 'non fondée' ?>.
                <?= e($litige['decision_motif']) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <?php require __DIR__ . '/../../includes/fil_litige.php'; ?>
            </div>
            <div class="col-12 col-lg-4">
                <div class="sm-tuile">
                    <h2 class="h6 fw-bold">Dossier</h2>
                    <dl class="sm-recap__lignes mb-0">
                        <dt>Motif</dt>
                        <dd><?= e(MOTIFS_LITIGE[$litige['motif']] ?? $litige['motif']) ?></dd>
                        <dt>Client</dt>
                        <dd><?= e($litige['client_prenom'] . ' ' . $litige['client_nom']) ?></dd>
                        <dt>Total de la commande</dt>
                        <dd><?= e(format_fcfa((int) $litige['total'])) ?></dd>
                        <dt>Ouverte</dt>
                        <dd><?= e(date_commande($litige['cree_le'])) ?></dd>
                    </dl>
                    <p class="small text-body-secondary mt-3 mb-0">
                        Cherchez un accord avec le client (échange, remboursement…). Une réclamation jugée fondée compte comme un signalement de votre boutique.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
