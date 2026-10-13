<?php
/**
 * SAMA MAILLOT — espace vendeur : « Ma boutique » (§9.2).
 *
 * Description, ville, numéro WhatsApp de la boutique, numéro Wave de versement,
 * logo et bannière. Le nom (et donc l'adresse /boutique/<nom>) ne se change pas ici :
 * il est unique et sert de lien public ; un changement passe par le support.
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();
$boutiqueId = (int) $boutique['id'];

$valeurs = [
    'ville' => $boutique['ville'],
    'description' => (string) ($boutique['description'] ?? ''),
    'numero_whatsapp' => $boutique['numero_whatsapp'] !== null ? formater_telephone($boutique['numero_whatsapp']) : '',
    'numero_wave' => $boutique['numero_wave'] !== null ? formater_telephone($boutique['numero_wave']) : '',
];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    } else {
        foreach (array_keys($valeurs) as $champ) {
            // Paiement en ligne éteint : le champ Wave (versements) est masqué, le numéro déjà enregistré est gardé.
            if ($champ === 'numero_wave' && !paiement_en_ligne_actif()) {
                continue;
            }
            $valeurs[$champ] = champ_post($champ);
        }

        if (mb_strlen($valeurs['ville']) < 2 || mb_strlen($valeurs['ville']) > 60) {
            $erreurs['ville'] = 'Indiquez la ville de votre boutique.';
        }
        if (mb_strlen($valeurs['description']) > 1000) {
            $erreurs['description'] = 'La description ne doit pas dépasser 1 000 caractères.';
        }

        $whatsapp = normaliser_telephone($valeurs['numero_whatsapp']);
        if ($whatsapp === null) {
            $erreurs['numero_whatsapp'] = 'Saisissez un numéro sénégalais valide (70, 75, 76, 77 ou 78).';
        }

        // Numéro Wave : facultatif maintenant, obligatoire avant le premier versement (§8.2)
        $wave = null;
        if ($valeurs['numero_wave'] !== '') {
            $wave = normaliser_telephone($valeurs['numero_wave']);
            if ($wave === null) {
                $erreurs['numero_wave'] = 'Saisissez un numéro Wave sénégalais valide.';
            }
        }

        // Images : traitées seulement si le reste est correct
        $nouveauLogo = null;
        $nouvelleBanniere = null;
        if ($erreurs === []) {
            try {
                $fichierLogo = fichiers_envoyes('logo')[0] ?? null;
                if ($fichierLogo !== null) {
                    $nouveauLogo = traiter_image_boutique($fichierLogo, 'logo');
                }
                $fichierBanniere = fichiers_envoyes('banniere')[0] ?? null;
                if ($fichierBanniere !== null) {
                    $nouvelleBanniere = traiter_image_boutique($fichierBanniere, 'banniere');
                }
            } catch (ErreurImage $exception) {
                supprimer_fichier_envoye('boutiques', $nouveauLogo);
                $nouveauLogo = null;
                $erreurs['images'] = $exception->getMessage();
            }
        }

        if ($erreurs === []) {
            $logo = $nouveauLogo ?? (case_cochee('retirer_logo') ? null : $boutique['logo']);
            $banniere = $nouvelleBanniere ?? (case_cochee('retirer_banniere') ? null : $boutique['banniere']);

            bdd()->prepare(
                'UPDATE boutiques SET ville = :ville, description = :description, numero_whatsapp = :whatsapp,
                        numero_wave = :wave, logo = :logo, banniere = :banniere
                 WHERE id = :id'
            )->execute([
                'ville' => $valeurs['ville'],
                'description' => $valeurs['description'] !== '' ? $valeurs['description'] : null,
                'whatsapp' => $whatsapp,
                'wave' => $wave,
                'logo' => $logo,
                'banniere' => $banniere,
                'id' => $boutiqueId,
            ]);

            // Anciennes images remplacées ou retirées : fichiers effacés
            if ($logo !== $boutique['logo']) {
                supprimer_fichier_envoye('boutiques', $boutique['logo']);
            }
            if ($banniere !== $boutique['banniere']) {
                supprimer_fichier_envoye('boutiques', $boutique['banniere']);
            }

            message_flash('succes', 'Boutique mise à jour.');
            rediriger('vendeur/boutique.php');
        }
    }
}

$titre_page = 'Ma boutique';
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-catalogue">
    <div class="container">
        <?= menu_vendeur('boutique') ?>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="sm-titre-section mb-0">Ma boutique</h1>
            <a class="btn btn-contour" href="<?= e(url_boutique($boutique['slug'])) ?>">
                Voir ma page publique <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <?= alerte_formulaire($erreurs) ?>

        <form method="post" enctype="multipart/form-data" novalidate>
            <?= champ_csrf() ?>
            <div class="row g-4">
                <div class="col-12 col-lg-7">
                    <fieldset class="sm-tuile mb-4">
                        <legend class="h5 fw-bold mb-3">Informations</legend>

                        <div class="mb-3">
                            <span class="form-label d-block">Nom de la boutique</span>
                            <p class="mb-0 fw-semibold"><?= e($boutique['nom']) ?></p>
                            <div class="form-text">Adresse publique : <?= e(url_boutique($boutique['slug'])) ?> — pour changer de nom, contactez le support.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="ville">Ville</label>
                            <input class="form-control<?= classe_erreur($erreurs, 'ville') ?>" type="text" id="ville" name="ville" value="<?= e($valeurs['ville']) ?>"
                                   maxlength="60" required <?= aria_erreur($erreurs, 'ville') ?>>
                            <?= message_erreur($erreurs, 'ville') ?>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control<?= classe_erreur($erreurs, 'description') ?>" id="description" name="description" rows="5" maxlength="1000"
                                      placeholder="Présentez votre boutique : spécialités, délais, conditions…" <?= aria_erreur($erreurs, 'description') ?>><?= e($valeurs['description']) ?></textarea>
                            <?= message_erreur($erreurs, 'description') ?>
                        </div>
                    </fieldset>

                    <fieldset class="sm-tuile">
                        <legend class="h5 fw-bold mb-3">Numéros</legend>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="numero_whatsapp">WhatsApp de la boutique</label>
                                <div class="input-group">
                                    <span class="input-group-text" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
                                    <input class="form-control<?= classe_erreur($erreurs, 'numero_whatsapp') ?>" type="tel" inputmode="numeric" id="numero_whatsapp"
                                           name="numero_whatsapp" value="<?= e($valeurs['numero_whatsapp']) ?>" placeholder="77 123 45 67" required
                                           aria-describedby="aide-whatsapp<?= isset($erreurs['numero_whatsapp']) ? ' erreur-numero_whatsapp' : '' ?>">
                                </div>
                                <div class="form-text" id="aide-whatsapp">Les clients vous écrivent ici. Il n'apparaît jamais dans le code des pages.</div>
                                <?= message_erreur($erreurs, 'numero_whatsapp') ?>
                            </div>
                            <?php if (paiement_en_ligne_actif()): ?>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="numero_wave">Numéro Wave (versements)</label>
                                <div class="input-group">
                                    <span class="input-group-text" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
                                    <input class="form-control<?= classe_erreur($erreurs, 'numero_wave') ?>" type="tel" inputmode="numeric" id="numero_wave"
                                           name="numero_wave" value="<?= e($valeurs['numero_wave']) ?>" placeholder="77 123 45 67"
                                           aria-describedby="aide-wave<?= isset($erreurs['numero_wave']) ? ' erreur-numero_wave' : '' ?>">
                                </div>
                                <div class="form-text" id="aide-wave">Facultatif pour l'instant, obligatoire avant votre premier versement.</div>
                                <?= message_erreur($erreurs, 'numero_wave') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </fieldset>
                </div>

                <div class="col-12 col-lg-5">
                    <fieldset class="sm-tuile mb-4">
                        <legend class="h5 fw-bold mb-3">Logo et bannière</legend>

                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="sm-entete-boutique__logo">
                                <?php if (!empty($boutique['logo'])): ?>
                                    <img src="<?= e(url_image_boutique($boutique['logo'])) ?>" alt="Logo actuel" width="400" height="400">
                                <?php else: ?>
                                    <?= e(mb_strtoupper(mb_substr($boutique['nom'], 0, 2))) ?>
                                <?php endif; ?>
                            </span>
                            <div class="flex-grow-1">
                                <label class="form-label" for="logo">Logo (carré, 200 px minimum)</label>
                                <input class="form-control" type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp,image/avif">
                                <?php if (!empty($boutique['logo'])): ?>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" id="retirer_logo" name="retirer_logo" value="1">
                                        <label class="form-check-label small" for="retirer_logo">Retirer le logo</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($boutique['banniere'])): ?>
                            <img class="sm-apercu-banniere mb-2" src="<?= e(url_image_boutique($boutique['banniere'])) ?>" alt="Bannière actuelle" width="1600" height="400">
                        <?php endif; ?>
                        <label class="form-label" for="banniere">Bannière (format large, 600 px minimum)</label>
                        <input class="form-control" type="file" id="banniere" name="banniere" accept="image/jpeg,image/png,image/webp,image/avif">
                        <?php if (!empty($boutique['banniere'])): ?>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="retirer_banniere" name="retirer_banniere" value="1">
                                <label class="form-check-label small" for="retirer_banniere">Retirer la bannière</label>
                            </div>
                        <?php endif; ?>
                        <div class="form-text">JPG, PNG, WebP ou AVIF, 5 Mo maximum.</div>
                        <?= message_erreur($erreurs, 'images') ?>
                    </fieldset>

                    <button class="btn btn-or w-100" type="submit"><i class="bi bi-floppy" aria-hidden="true"></i> Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
