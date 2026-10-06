<?php
/**
 * SAMA MAILLOT — ouvrir une réclamation sur une commande (§16).
 *
 *   compte/reclamation.php?commande=12
 *
 * Possible sur une commande expédiée, livrée, ou reçue depuis moins de 48 h.
 * Motif + description (20 à 2 000 caractères) + jusqu'à 3 photos (privées : parties et administration).
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();

$commande = commande_du_client((int) ($_GET['commande'] ?? $_POST['commande'] ?? 0), (int) $utilisateur['id']);
if ($commande === null) {
    afficher_erreur(404);
}
$id = (int) $commande['id'];

$existant = litige_de_la_commande($id);
if ($existant !== null) {
    rediriger('compte/litige.php?id=' . (int) $existant['id']);
}
$refus = refus_reclamation($commande);

$erreurs = [];
$valeurs = ['motif' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeurs['motif'] = champ_post('motif');
    $valeurs['description'] = trim((string) ($_POST['description'] ?? ''));

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($refus !== null) {
        $erreurs['general'] = $refus;
    } else {
        $resultat = ouvrir_litige($utilisateur, $commande, $valeurs['motif'], $valeurs['description'], fichiers_envoyes('photos'));
        $erreurs = $resultat['erreurs'];
        if ($resultat['id'] !== null) {
            message_flash('succes', 'Votre réclamation est ouverte. Le vendeur a 48 h pour répondre ; notre équipe suit le dossier.');
            rediriger('compte/litige.php?id=' . $resultat['id']);
        }
    }
}

$titre_page = 'Ouvrir une réclamation';
$sans_indexation = true;
$page_active = 'mes-commandes';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire sm-carte-formulaire--large">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-shield-exclamation"></i></span>
            <h1 class="mb-1">Ouvrir une réclamation</h1>
            <p class="text-body-secondary mb-4">
                Commande <strong><?= e($commande['numero']) ?></strong> · <?= e($commande['boutique_nom']) ?>
            </p>

            <?php if ($refus !== null): ?>
                <div class="alert alert-info" role="note"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e($refus) ?></div>
                <a class="btn btn-contour" href="<?= e(url('compte/commande.php?id=' . $id)) ?>">Retour à la commande</a>
            <?php else: ?>
                <?= alerte_formulaire($erreurs) ?>
                <form method="post" enctype="multipart/form-data" novalidate>
                    <?= champ_csrf() ?>
                    <input type="hidden" name="commande" value="<?= e($id) ?>">

                    <fieldset class="mb-3">
                        <legend class="form-label fw-semibold fs-6">Motif</legend>
                        <?php foreach (MOTIFS_LITIGE as $cle => $libelle): ?>
                            <div class="form-check sm-choix-adresse">
                                <input class="form-check-input" type="radio" name="motif" id="motif-<?= e($cle) ?>" value="<?= e($cle) ?>"
                                       <?= $valeurs['motif'] === $cle ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="motif-<?= e($cle) ?>"><?= e($libelle) ?></label>
                            </div>
                        <?php endforeach; ?>
                        <?= message_erreur($erreurs, 'motif') ?>
                    </fieldset>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="description">Décrivez le problème</label>
                        <textarea class="form-control<?= classe_erreur($erreurs, 'description') ?>" id="description" name="description" rows="5"
                                  maxlength="<?= LITIGE_MESSAGE_MAX ?>" required placeholder="Que s'est-il passé ? Qu'attendez-vous du vendeur (échange, remboursement…) ?"
                                  <?= aria_erreur($erreurs, 'description') ?>><?= e($valeurs['description']) ?></textarea>
                        <?= message_erreur($erreurs, 'description') ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="photos">Photos <span class="fw-normal text-body-secondary">(facultatif, 3 au maximum)</span></label>
                        <input class="form-control<?= classe_erreur($erreurs, 'photos') ?>" type="file" id="photos" name="photos[]" multiple
                               accept="image/jpeg,image/png,image/webp,image/avif" <?= aria_erreur($erreurs, 'photos') ?>>
                        <?= message_erreur($erreurs, 'photos') ?>
                        <div class="form-text">Vues seulement par le vendeur et l'équipe SAMA MAILLOT.</div>
                    </div>

                    <p class="small text-body-secondary">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        Le paiement se fait à la livraison, entre vous et le vendeur : SAMA MAILLOT fait la <strong>médiation</strong>.
                        Une réclamation jugée fondée compte comme un signalement du vendeur.
                    </p>
                    <button class="btn btn-or w-100" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Envoyer la réclamation</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
