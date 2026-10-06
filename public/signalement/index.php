<?php
/**
 * SAMA MAILLOT — signaler un vendeur ou un maillot (§21).
 *
 *   signalement/?boutique=4   → signaler le vendeur de cette boutique
 *   signalement/?maillot=12   → signaler ce maillot (file de modération de l'administrateur)
 *
 * Seul un client ayant passé une commande non annulée chez ce vendeur peut signaler.
 * Motif obligatoire ; description obligatoire (20 à 2 000 caractères).
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();

$maillot = null;
$boutique = null;

if (isset($_GET['maillot']) || isset($_POST['maillot_id'])) {
    $requete = bdd()->prepare(
        "SELECT m.id, m.titre, m.slug, m.boutique_id, b.nom AS boutique_nom, b.slug AS boutique_slug
         FROM maillots m JOIN boutiques b ON b.id = m.boutique_id WHERE m.id = :id AND m.statut <> 'supprime'"
    );
    $requete->execute(['id' => (int) ($_POST['maillot_id'] ?? $_GET['maillot'])]);
    $maillot = $requete->fetch() ?: null;
    if ($maillot === null) {
        afficher_erreur(404);
    }
    $type = 'maillot';
    $boutiqueId = (int) $maillot['boutique_id'];
    $nomCible = $maillot['titre'];
    $motifs = MOTIFS_SIGNALEMENT_MAILLOT;
    $refus = refus_signalement_maillot($utilisateur, $maillot);
} else {
    $requete = bdd()->prepare('SELECT id, nom, slug FROM boutiques WHERE id = :id AND supprime_le IS NULL');
    $requete->execute(['id' => (int) ($_POST['boutique_id'] ?? $_GET['boutique'] ?? 0)]);
    $boutique = $requete->fetch() ?: null;
    if ($boutique === null) {
        afficher_erreur(404);
    }
    $type = 'vendeur';
    $boutiqueId = (int) $boutique['id'];
    $nomCible = $boutique['nom'];
    $motifs = MOTIFS_SIGNALEMENT_VENDEUR;
    $refus = refus_signalement_vendeur($utilisateur, $boutiqueId);
}

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
        if (!isset($motifs[$valeurs['motif']])) {
            $erreurs['motif'] = 'Choisissez le motif du signalement.';
        }
        $longueur = mb_strlen($valeurs['description']);
        if ($longueur < 20 || $longueur > 2000) {
            $erreurs['description'] = 'Décrivez le problème en 20 à 2 000 caractères.';
        }

        if ($erreurs === []) {
            creer_signalement($utilisateur, $type, $boutiqueId, $maillot !== null ? (int) $maillot['id'] : null,
                $valeurs['motif'], $valeurs['description']);
            message_flash('succes', 'Merci, votre signalement est enregistré. Notre équipe va l\'examiner.');
            rediriger($maillot !== null ? 'maillot/' . $maillot['slug'] . '-' . (int) $maillot['id'] : 'boutique/' . $boutique['slug']);
        }
    }
}

$titre_page = $type === 'vendeur' ? 'Signaler un vendeur' : 'Signaler un maillot';
$sans_indexation = true;

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire sm-carte-formulaire--large">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-flag"></i></span>
            <h1 class="mb-1"><?= e($titre_page) ?></h1>
            <p class="text-body-secondary mb-4">
                <?= $type === 'vendeur' ? 'Boutique' : 'Maillot' ?> : <strong><?= e($nomCible) ?></strong>
                <?php if ($maillot !== null): ?> · <?= e($maillot['boutique_nom']) ?><?php endif; ?>
            </p>

            <?php if ($refus !== null): ?>
                <div class="alert alert-info" role="note"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e($refus) ?></div>
                <a class="btn btn-contour" href="<?= e(url()) ?>">Retour à l'accueil</a>
            <?php else: ?>
                <?= alerte_formulaire($erreurs) ?>
                <form method="post" novalidate>
                    <?= champ_csrf() ?>
                    <?php if ($maillot !== null): ?>
                        <input type="hidden" name="maillot_id" value="<?= e($maillot['id']) ?>">
                    <?php else: ?>
                        <input type="hidden" name="boutique_id" value="<?= e($boutique['id']) ?>">
                    <?php endif; ?>

                    <fieldset class="mb-3">
                        <legend class="form-label fw-semibold fs-6">Motif</legend>
                        <?php foreach ($motifs as $cle => $libelle): ?>
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
                                  maxlength="2000" required placeholder="Que s'est-il passé ? Numéro de commande, dates, échanges avec le vendeur…"
                                  <?= aria_erreur($erreurs, 'description') ?>><?= e($valeurs['description']) ?></textarea>
                        <?= message_erreur($erreurs, 'description') ?>
                    </div>

                    <p class="small text-body-secondary">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        Votre signalement est examiné par l'équipe SAMA MAILLOT. Un signalement abusif peut être classé.
                    </p>
                    <button class="btn btn-or w-100" type="submit"><i class="bi bi-flag" aria-hidden="true"></i> Envoyer le signalement</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
