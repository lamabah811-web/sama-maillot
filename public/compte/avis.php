<?php
/**
 * SAMA MAILLOT — noter le vendeur d'une commande reçue (§20).
 *
 *   compte/avis.php?commande=12
 *
 * Note de 1 à 5 (obligatoire), commentaire facultatif (500 caractères). Les avis sont PUBLICS :
 * ils s'affichent sur la page de la boutique (prénom + initiale du nom). Modifiable une fois sous 7 jours.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();

$commande = commande_du_client((int) ($_GET['commande'] ?? $_POST['commande'] ?? 0), (int) $utilisateur['id']);
if ($commande === null) {
    afficher_erreur(404);
}
$id = (int) $commande['id'];
$avis = avis_de_la_commande($id);
$refus = refus_avis($commande, $avis);

$erreurs = [];
$valeurs = [
    'note' => $avis !== null ? (string) $avis['note'] : '',
    'commentaire' => $avis['commentaire'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeurs['note'] = champ_post('note');
    $valeurs['commentaire'] = trim((string) ($_POST['commentaire'] ?? ''));

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($refus !== null) {
        $erreurs['general'] = $refus;
    } else {
        $note = (int) $valeurs['note'];
        if ($note < 1 || $note > 5) {
            $erreurs['note'] = 'Choisissez une note de 1 à 5 étoiles.';
        }
        if (mb_strlen($valeurs['commentaire']) > 500) {
            $erreurs['commentaire'] = 'Votre commentaire ne doit pas dépasser 500 caractères.';
        }

        if ($erreurs === []) {
            enregistrer_avis($utilisateur, $commande, $avis, $note, $valeurs['commentaire']);
            message_flash('succes', $avis === null ? 'Merci pour votre avis ! Il est publié sur la page de la boutique.' : 'Votre avis est modifié.');
            rediriger('compte/commande.php?id=' . $id);
        }
    }
}

$titre_page = 'Noter le vendeur';
$sans_indexation = true;
$page_active = 'mes-commandes';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire sm-carte-formulaire--large">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-star"></i></span>
            <h1 class="mb-1"><?= $avis === null ? 'Noter le vendeur' : 'Modifier mon avis' ?></h1>
            <p class="text-body-secondary mb-4">
                Boutique : <strong><?= e($commande['boutique_nom']) ?></strong> · commande <?= e($commande['numero']) ?>
            </p>

            <?php if ($refus !== null): ?>
                <div class="alert alert-info" role="note"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e($refus) ?></div>
                <a class="btn btn-contour" href="<?= e(url('compte/commande.php?id=' . $id)) ?>">Retour à la commande</a>
            <?php else: ?>
                <?= alerte_formulaire($erreurs) ?>
                <form method="post" novalidate>
                    <?= champ_csrf() ?>
                    <input type="hidden" name="commande" value="<?= e($id) ?>">

                    <fieldset class="mb-3">
                        <legend class="form-label fw-semibold fs-6">Votre note</legend>
                        <!-- Étoiles : boutons radio (clavier et lecteur d'écran), affichés de 5 à 1 pour le survol CSS -->
                        <div class="sm-notation">
                            <?php for ($n = 5; $n >= 1; $n--): ?>
                                <input class="visually-hidden" type="radio" name="note" id="note-<?= $n ?>" value="<?= $n ?>"
                                       <?= $valeurs['note'] === (string) $n ? 'checked' : '' ?> required>
                                <label for="note-<?= $n ?>" title="<?= $n ?> sur 5">
                                    <i class="bi bi-star-fill" aria-hidden="true"></i>
                                    <span class="visually-hidden"><?= $n ?> étoile<?= $n > 1 ? 's' : '' ?></span>
                                </label>
                            <?php endfor; ?>
                        </div>
                        <?= message_erreur($erreurs, 'note') ?>
                    </fieldset>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="commentaire">Commentaire <span class="fw-normal text-body-secondary">(facultatif)</span></label>
                        <textarea class="form-control<?= classe_erreur($erreurs, 'commentaire') ?>" id="commentaire" name="commentaire" rows="4"
                                  maxlength="500" placeholder="Qualité du maillot, rapidité, sérieux du vendeur…"
                                  <?= aria_erreur($erreurs, 'commentaire') ?>><?= e($valeurs['commentaire']) ?></textarea>
                        <?= message_erreur($erreurs, 'commentaire') ?>
                    </div>

                    <p class="small text-body-secondary">
                        <i class="bi bi-globe2" aria-hidden="true"></i>
                        Votre avis est <strong>public</strong> : il s'affiche sur la page de la boutique avec votre prénom et l'initiale de votre nom.
                        <?= $avis === null ? 'Vous pourrez le modifier une fois, dans les 7 jours.' : 'C\'est votre seule modification possible.' ?>
                    </p>
                    <button class="btn btn-or w-100" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Publier mon avis</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
