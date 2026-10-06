<?php
/**
 * SAMA MAILLOT — suppression du compte (§8.7, loi n° 2008-12 : droit à l'effacement).
 *
 * Confirmation par le mot de passe (ou par la saisie de « SUPPRIMER » pour un compte Google).
 * Refusée tant qu'une commande, un remboursement ou de l'argent du portefeuille est en cours.
 * Les données personnelles sont anonymisées ; l'historique des commandes est conservé.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();
$refus = refus_suppression_compte($utilisateur);
$avec_mot_de_passe = $utilisateur['mot_de_passe_hache'] !== null;
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($refus !== null) {
        $erreurs['general'] = $refus;
    } elseif ($avec_mot_de_passe && !password_verify((string) ($_POST['mot_de_passe'] ?? ''), $utilisateur['mot_de_passe_hache'])) {
        $erreurs['mot_de_passe'] = 'Le mot de passe est incorrect.';
    } elseif (!$avec_mot_de_passe && trim((string) ($_POST['confirmation'] ?? '')) !== 'SUPPRIMER') {
        $erreurs['confirmation'] = 'Écrivez SUPPRIMER en majuscules pour confirmer.';
    } else {
        supprimer_compte($utilisateur);
        deconnecter();
        message_flash('succes', 'Votre compte est supprimé. Merci d\'avoir utilisé SAMA MAILLOT.');
        rediriger();
    }
}

$titre_page = 'Supprimer mon compte';
$sans_indexation = true;
$page_active = 'compte';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <span class="sm-medaillon sm-medaillon--erreur mb-3" aria-hidden="true"><i class="bi bi-trash3"></i></span>
            <h1 class="mb-1">Supprimer mon compte</h1>
            <p class="text-body-secondary">Cette action est <strong>définitive</strong>.</p>

            <ul class="small mb-4">
                <li>Vos nom, e-mail, téléphone, adresses, favoris et notifications sont effacés.</li>
                <?php if ($utilisateur['role'] === 'vendeur'): ?>
                    <li>Votre boutique et tous vos maillots sont retirés du site.</li>
                <?php endif; ?>
                <li>L'historique de vos commandes est conservé de façon anonyme, pour la comptabilité (obligation légale).</li>
                <li>Vos avis restent visibles, signés « Utilisateur S. ».</li>
            </ul>

            <?php if ($refus !== null): ?>
                <div class="alert alert-info" role="note"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e($refus) ?></div>
                <a class="btn btn-contour" href="<?= e(url_page('compte')) ?>">Retour à mon compte</a>
            <?php else: ?>
                <?= alerte_formulaire($erreurs) ?>
                <form method="post" novalidate>
                    <?= champ_csrf() ?>
                    <?php if ($avec_mot_de_passe): ?>
                        <?= champ_mot_de_passe('mot_de_passe', 'Votre mot de passe, pour confirmer', $erreurs, 'current-password') ?>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="confirmation">Écrivez SUPPRIMER pour confirmer</label>
                            <input class="form-control<?= classe_erreur($erreurs, 'confirmation') ?>" type="text" id="confirmation" name="confirmation"
                                   autocomplete="off" required <?= aria_erreur($erreurs, 'confirmation') ?>>
                            <?= message_erreur($erreurs, 'confirmation') ?>
                        </div>
                    <?php endif; ?>
                    <button class="btn btn-contour sm-bouton-danger w-100 mt-2" type="submit">Supprimer définitivement mon compte</button>
                    <a class="btn btn-link w-100 mt-1" href="<?= e(url_page('compte')) ?>">Annuler</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
