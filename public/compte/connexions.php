<?php
/**
 * SAMA MAILLOT — « Mes connexions » : lier ou délier son compte Google / Facebook.
 *
 * - Lier : POST + jeton CSRF vers connexion/<fournisseur>.php (le compte connecté reçoit
 *   la liaison au retour ; refusé si ce compte Google / Facebook est déjà lié à quelqu'un d'autre).
 * - Délier : POST + jeton CSRF ; refusé s'il s'agit du dernier moyen de connexion.
 * Les administrateurs n'utilisent pas ces connexions.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();
$id = (int) $utilisateur['id'];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fournisseur = champ_post('fournisseur');

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif (!isset(FOURNISSEURS_SOCIAUX[$fournisseur])) {
        $erreurs['general'] = 'Demande invalide.';
    } elseif ($refus = refus_deliaison($utilisateur, $fournisseur)) {
        $erreurs['general'] = $refus;
    } else {
        delier_fournisseur($id, $fournisseur);
        message_flash('succes', 'Votre compte ' . nom_fournisseur($fournisseur) . ' n\'est plus lié.');
        rediriger('compte/connexions.php');
    }
}

$liaisons = liaisons_du_compte($id);
$titre_page = 'Google et Facebook';
$sans_indexation = true;
$page_active = 'compte';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-link-45deg"></i></span>
            <h1 class="mb-2">Google et Facebook</h1>
            <p class="text-body-secondary mb-4">Connectez-vous en un clic avec un compte lié.</p>

            <?= alerte_formulaire($erreurs) ?>

            <?php if ($utilisateur['role'] === 'admin'): ?>
                <p class="mb-0">Les comptes administrateur se connectent uniquement avec leur mot de passe.</p>
            <?php else: ?>
                <ul class="list-unstyled d-grid gap-3 mb-4">
                    <?php foreach (FOURNISSEURS_SOCIAUX as $fournisseur => $nom): ?>
                        <?php $liaison = $liaisons[$fournisseur] ?? null; ?>
                        <?php if ($liaison === null && !fournisseur_actif($fournisseur)) { continue; } ?>
                        <li class="sm-liaison">
                            <span class="sm-liaison__logo sm-liaison__logo--<?= e($fournisseur) ?>" aria-hidden="true">
                                <?php require __DIR__ . '/../../includes/logo_' . $fournisseur . '.php'; ?>
                            </span>
                            <span class="sm-liaison__texte">
                                <strong><?= e($nom) ?></strong>
                                <span class="d-block small text-body-secondary text-break">
                                    <?php if ($liaison !== null): ?>
                                        Lié<?= $liaison['email_fournisseur'] !== null ? ' : ' . e($liaison['email_fournisseur']) : '' ?>
                                    <?php else: ?>
                                        Non lié
                                    <?php endif; ?>
                                </span>
                            </span>
                            <?php if ($liaison !== null): ?>
                                <form method="post">
                                    <?= champ_csrf() ?>
                                    <input type="hidden" name="fournisseur" value="<?= e($fournisseur) ?>">
                                    <button class="btn btn-contour btn-sm" type="submit" aria-label="Délier mon compte <?= e($nom) ?>">Délier</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('connexion/' . $fournisseur . '.php')) ?>">
                                    <?= champ_csrf() ?>
                                    <input type="hidden" name="action" value="lier">
                                    <button class="btn btn-or btn-sm" type="submit" aria-label="Lier mon compte <?= e($nom) ?>">Lier</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p class="small text-body-secondary mb-0">
                    Mot de passe SAMA MAILLOT :
                    <?php if ($utilisateur['mot_de_passe_hache'] === null): ?>
                        aucun pour l'instant. <a href="<?= e(url_page('compte-mot-de-passe')) ?>">Définir un mot de passe</a>
                        pour pouvoir délier votre dernier compte Google ou Facebook.
                    <?php else: ?>
                        défini.
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <p class="text-center mt-4 mb-0">
                <a href="<?= e(url_page('compte')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à mon compte</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
