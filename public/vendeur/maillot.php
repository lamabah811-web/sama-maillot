<?php
/**
 * SAMA MAILLOT — espace vendeur : créer ou modifier un maillot (§9.4).
 *
 * vendeur/maillot.php          → nouveau maillot
 * vendeur/maillot.php?id=12    → modification (uniquement un maillot de SA boutique)
 *
 * Boutons : « Enregistrer » (brouillon, ou garde le statut actuel) et « Publier ».
 * Publier exige : au moins 1 photo, au moins 1 taille en stock, un abonnement valide.
 * Pendant la période de grâce : impossible d'ajouter un NOUVEAU maillot (§9.5).
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();
$abonnement = etat_abonnement($boutique);
$boutiqueId = (int) $boutique['id'];

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$maillot = $id > 0 ? maillot_du_vendeur($id, $boutiqueId) : null;

if ($id > 0 && $maillot === null) {
    afficher_erreur(404);   // inexistant ou appartenant à une autre boutique
}

$nouveau = $maillot === null;
$bloque_par_admin = !$nouveau && $maillot['statut'] === 'masque_admin';
$ajout_interdit = $nouveau && !$abonnement['peut_ajouter'];

$categories = categories_actives();
$equipes = equipes_par_type();
$photos = $nouveau ? [] : photos_du_maillot($id);

// Valeurs du formulaire : celles du maillot, ou des valeurs vides pour un nouveau maillot
$valeurs = [
    'titre' => $maillot['titre'] ?? '',
    'categorie_id' => (string) ($maillot['categorie_id'] ?? ''),
    'equipe_id' => (string) ($maillot['equipe_id'] ?? ''),
    'saison' => $maillot['saison'] ?? '',
    'type' => $maillot['type'] ?? 'domicile',
    'authenticite' => $maillot['authenticite'] ?? '',
    'etat' => $maillot['etat'] ?? 'neuf_etiquette',
    'prix' => isset($maillot['prix']) ? (string) $maillot['prix'] : '',
    'flocage_propose' => (int) ($maillot['flocage_propose'] ?? 0) === 1,
    'flocage_supplement' => isset($maillot['flocage_supplement']) && (int) $maillot['flocage_supplement'] > 0 ? (string) $maillot['flocage_supplement'] : '',
    'description' => $maillot['description'] ?? '',
];
$stocks = [];
foreach (($nouveau ? [] : tailles_du_maillot($id)) as $taille => $stock) {
    $stocks[$taille] = (string) $stock;
}
$photo_principale = $photos[0]['id'] ?? 0;
$photos_a_supprimer = [];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$ajout_interdit) {
    $action = champ_post('action') === 'publier' ? 'publier' : 'enregistrer';

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    } else {
        // ---- 1. Lecture des champs ----
        foreach (['titre', 'categorie_id', 'equipe_id', 'saison', 'type', 'authenticite', 'etat', 'description'] as $champ) {
            $valeurs[$champ] = champ_post($champ);
        }
        $valeurs['prix'] = preg_replace('/\D+/', '', champ_post('prix')) ?? '';
        $valeurs['flocage_propose'] = case_cochee('flocage_propose');
        $valeurs['flocage_supplement'] = preg_replace('/\D+/', '', champ_post('flocage_supplement')) ?? '';

        $stocks = [];
        $stocksRecus = is_array($_POST['stock'] ?? null) ? $_POST['stock'] : [];
        foreach (TAILLES as $taille) {
            $saisie = is_string($stocksRecus[$taille] ?? null) ? trim($stocksRecus[$taille]) : '';
            if ($saisie !== '') {
                $stocks[$taille] = $saisie;
            }
        }

        $idsPhotos = array_map(static fn (array $p): int => (int) $p['id'], $photos);
        $photos_a_supprimer = array_values(array_intersect(
            $idsPhotos,
            array_map('intval', is_array($_POST['supprimer_photo'] ?? null) ? $_POST['supprimer_photo'] : [])
        ));
        $photo_principale = (int) champ_post('photo_principale');
        $nouvellesPhotos = fichiers_envoyes('photos');

        // ---- 2. Contrôles (§9.4) ----
        $longueur = mb_strlen($valeurs['titre']);
        if ($longueur < 10 || $longueur > 100) {
            $erreurs['titre'] = 'Le titre doit contenir entre 10 et 100 caractères (ex. : Maillot Sénégal domicile 2025/2026).';
        }

        if (!in_array((int) $valeurs['categorie_id'], array_map(static fn (array $c): int => (int) $c['id'], $categories), true)) {
            $erreurs['categorie_id'] = 'Choisissez une catégorie.';
        }

        $idsEquipes = array_map(static fn (array $e): int => (int) $e['id'], array_merge($equipes['selection'], $equipes['club']));
        if (!in_array((int) $valeurs['equipe_id'], $idsEquipes, true)) {
            $erreurs['equipe_id'] = 'Choisissez une équipe dans la liste.';
        }

        // Saison : deux années qui se suivent, ou une année pour le rétro. Écritures acceptées :
        // 2025/2026, 2025-2026, 2025 - 2026, 2025-26, 2025/26 → enregistrée sous la forme 2025/2026.
        if (preg_match('#^((?:19|20)\d{2})\s*[/\-–—.]\s*(\d{2}|(?:19|20)\d{2})$#u', $valeurs['saison'], $m) === 1) {
            $debut = (int) $m[1];
            $fin = strlen($m[2]) === 2 ? intdiv($debut + 1, 100) * 100 + (int) $m[2] : (int) $m[2];

            if ($fin !== $debut + 1) {
                $erreurs['saison'] = 'Les deux années doivent se suivre (ex. : 2025/2026).';
            } else {
                $valeurs['saison'] = $debut . '/' . $fin;
            }
        } elseif (preg_match('#^(19|20)\d{2}$#', $valeurs['saison']) !== 1) {
            $erreurs['saison'] = 'Format attendu : 2025/2026, ou une année pour le rétro (ex. : 2002).';
        }

        if (!isset(LIBELLES_TYPE[$valeurs['type']])) {
            $erreurs['type'] = 'Choisissez le type de maillot.';
        }
        if (!isset(LIBELLES_AUTHENTICITE[$valeurs['authenticite']])) {
            $erreurs['authenticite'] = 'Indiquez si le maillot est officiel, une réplique ou une version fan.';
        }
        if (!isset(LIBELLES_ETAT[$valeurs['etat']])) {
            $erreurs['etat'] = 'Choisissez l\'état du maillot.';
        }

        $prix = (int) $valeurs['prix'];
        if ($valeurs['prix'] === '' || $prix < 1000 || $prix > 500000) {
            $erreurs['prix'] = 'Le prix doit être compris entre 1 000 et 500 000 FCFA.';
        }

        $supplement = $valeurs['flocage_propose'] ? (int) $valeurs['flocage_supplement'] : 0;
        if ($valeurs['flocage_propose'] && ($supplement < 0 || $supplement > 50000)) {
            $erreurs['flocage_supplement'] = 'Le supplément de flocage doit être compris entre 0 et 50 000 FCFA.';
        }

        if (mb_strlen($valeurs['description']) > 2000) {
            $erreurs['description'] = 'La description ne doit pas dépasser 2 000 caractères.';
        }

        $stockTotal = 0;
        foreach ($stocks as $taille => $saisie) {
            if (preg_match('/^\d{1,3}$/', $saisie) !== 1) {
                $erreurs['stock'] = 'Le stock de chaque taille doit être un nombre entre 0 et 999.';
                break;
            }
            $stockTotal += (int) $saisie;
        }

        $nombrePhotos = count($photos) - count($photos_a_supprimer) + count($nouvellesPhotos);
        if ($nombrePhotos > photos_par_maillot_max()) {
            $erreurs['photos'] = photos_par_maillot_max() . ' photos maximum par maillot.';
        }

        // Conditions de publication
        if ($action === 'publier' && $erreurs === []) {
            if (!$abonnement['peut_publier']) {
                $erreurs['general'] = 'Votre abonnement ne permet pas de publier (' . mb_strtolower($abonnement['libelle']) . ').';
            } elseif ($bloque_par_admin) {
                $erreurs['general'] = 'Ce maillot a été masqué par l\'administration : il ne peut pas être republié.';
            } elseif ($nombrePhotos < 1) {
                $erreurs['photos'] = 'Ajoutez au moins une photo pour publier ce maillot.';
            } elseif ($stockTotal < 1) {
                $erreurs['stock'] = 'Indiquez au moins une taille en stock pour publier ce maillot.';
            }
        }

        // ---- 3. Traitement des nouvelles photos (avant toute écriture en base) ----
        $photosTraitees = [];
        if ($erreurs === []) {
            try {
                foreach ($nouvellesPhotos as $fichier) {
                    $photosTraitees[] = traiter_photo_maillot($fichier);
                }
            } catch (ErreurImage $exception) {
                foreach ($photosTraitees as $photo) {
                    supprimer_fichier_envoye('maillots', $photo['fichier']);
                    supprimer_fichier_envoye('maillots', $photo['miniature']);
                }
                $photosTraitees = [];
                $erreurs['photos'] = $exception->getMessage();
            }
        }

        // ---- 4. Enregistrement (transaction : tout ou rien) ----
        if ($erreurs === []) {
            $statut = $nouveau ? 'brouillon' : $maillot['statut'];
            if ($action === 'publier' && !$bloque_par_admin) {
                $statut = 'publie';
            }

            $donnees = [
                'categorie_id' => (int) $valeurs['categorie_id'],
                'equipe_id' => (int) $valeurs['equipe_id'],
                'titre' => $valeurs['titre'],
                'slug' => creer_slug($valeurs['titre']) ?: 'maillot',
                'saison' => $valeurs['saison'],
                'type' => $valeurs['type'],
                'authenticite' => $valeurs['authenticite'],
                'etat' => $valeurs['etat'],
                'prix' => $prix,
                'flocage_propose' => $valeurs['flocage_propose'] ? 1 : 0,
                'flocage_supplement' => $supplement,
                'description' => $valeurs['description'] !== '' ? $valeurs['description'] : null,
                'statut' => $statut,
            ];

            $pdo = bdd();
            $pdo->beginTransaction();

            try {
                if ($nouveau) {
                    $pdo->prepare(
                        'INSERT INTO maillots (boutique_id, categorie_id, equipe_id, titre, slug, saison, type, authenticite, etat,
                                               prix, flocage_propose, flocage_supplement, description, statut, publie_le)
                         VALUES (:boutique_id, :categorie_id, :equipe_id, :titre, :slug, :saison, :type, :authenticite, :etat,
                                 :prix, :flocage_propose, :flocage_supplement, :description, :statut,
                                 IF(:statut2 = \'publie\', NOW(), NULL))'
                    )->execute($donnees + ['boutique_id' => $boutiqueId, 'statut2' => $statut]);
                    $id = (int) $pdo->lastInsertId();
                } else {
                    $pdo->prepare(
                        'UPDATE maillots SET categorie_id = :categorie_id, equipe_id = :equipe_id, titre = :titre, slug = :slug,
                                saison = :saison, type = :type, authenticite = :authenticite, etat = :etat, prix = :prix,
                                flocage_propose = :flocage_propose, flocage_supplement = :flocage_supplement,
                                description = :description, statut = :statut,
                                publie_le = IF(:statut2 = \'publie\', COALESCE(publie_le, NOW()), publie_le)
                         WHERE id = :id AND boutique_id = :boutique_id'
                    )->execute($donnees + ['id' => $id, 'boutique_id' => $boutiqueId, 'statut2' => $statut]);
                }

                // Tailles : seules celles saisies sont proposées
                $pdo->prepare('DELETE FROM maillot_tailles WHERE maillot_id = :id')->execute(['id' => $id]);
                $ajoutTaille = $pdo->prepare('INSERT INTO maillot_tailles (maillot_id, taille, stock) VALUES (:id, :taille, :stock)');
                foreach ($stocks as $taille => $saisie) {
                    $ajoutTaille->execute(['id' => $id, 'taille' => $taille, 'stock' => (int) $saisie]);
                }

                // Photos : suppressions, ajouts, puis ordre (la principale en premier)
                $fichiersASupprimer = [];
                foreach ($photos as $photo) {
                    if (in_array((int) $photo['id'], $photos_a_supprimer, true)) {
                        $pdo->prepare('DELETE FROM maillot_images WHERE id = :id AND maillot_id = :maillot')
                            ->execute(['id' => $photo['id'], 'maillot' => $id]);
                        $fichiersASupprimer[] = $photo;
                    }
                }
                $ajoutPhoto = $pdo->prepare('INSERT INTO maillot_images (maillot_id, fichier, miniature, ordre) VALUES (:id, :fichier, :miniature, 99)');
                foreach ($photosTraitees as $photo) {
                    $ajoutPhoto->execute(['id' => $id, 'fichier' => $photo['fichier'], 'miniature' => $photo['miniature']]);
                }

                $restantes = $pdo->prepare('SELECT id FROM maillot_images WHERE maillot_id = :id ORDER BY ordre, id');
                $restantes->execute(['id' => $id]);
                $ordreIds = array_map('intval', $restantes->fetchAll(PDO::FETCH_COLUMN));
                if (in_array($photo_principale, $ordreIds, true)) {
                    $ordreIds = array_merge([$photo_principale], array_values(array_diff($ordreIds, [$photo_principale])));
                }
                $miseEnOrdre = $pdo->prepare('UPDATE maillot_images SET ordre = :ordre WHERE id = :id');
                foreach ($ordreIds as $position => $photoId) {
                    $miseEnOrdre->execute(['ordre' => $position, 'id' => $photoId]);
                }

                $pdo->commit();
            } catch (Throwable $exception) {
                $pdo->rollBack();
                foreach ($photosTraitees as $photo) {
                    supprimer_fichier_envoye('maillots', $photo['fichier']);
                    supprimer_fichier_envoye('maillots', $photo['miniature']);
                }
                throw $exception;
            }

            // Fichiers des photos retirées : effacés seulement après la validation en base
            foreach ($fichiersASupprimer as $photo) {
                supprimer_fichier_envoye('maillots', $photo['fichier']);
                supprimer_fichier_envoye('maillots', $photo['miniature']);
            }

            mettre_a_jour_texte_recherche($id);

            message_flash('succes', $statut === 'publie'
                ? 'Maillot publié : il est visible dans le catalogue.'
                : 'Maillot enregistré.');
            rediriger('vendeur/maillot.php?id=' . $id);
        }

        if ($nouvellesPhotos !== [] && $erreurs !== [] && !isset($erreurs['photos'])) {
            $erreurs['photos'] = 'Le formulaire contient des erreurs : sélectionnez à nouveau vos nouvelles photos.';
        }
    }
}

$titre_page = $nouveau ? 'Nouveau maillot' : 'Modifier le maillot';
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-catalogue">
    <div class="container">
        <?= menu_vendeur('maillots') ?>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h1 class="sm-titre-section mb-1"><?= e($titre_page) ?></h1>
                <?php if (!$nouveau): ?>
                    <p class="mb-0"><?= badge_statut_maillot($maillot['statut']) ?>
                        <?php if ($maillot['statut'] === 'publie'): ?>
                            <a class="ms-2" href="<?= e(url_maillot($maillot)) ?>" target="_blank" rel="noopener">Voir la fiche publique <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <a class="btn btn-contour" href="<?= e(url('vendeur/maillots.php')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Mes maillots</a>
        </div>

        <?php if ($ajout_interdit): ?>
            <!-- §9.5 : pas de nouveau maillot pendant la grâce ou sans abonnement -->
            <div class="sm-etat-vide">
                <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-lock"></i></div>
                <h2 class="h3">Ajout de maillots indisponible</h2>
                <p class="mb-0">
                    <?php if ($abonnement['etat'] === 'grace'): ?>
                        Votre période d'essai est terminée : pendant la période de grâce, vos maillots restent visibles
                        mais vous ne pouvez pas en ajouter de nouveaux. Renouvelez votre abonnement pour continuer.
                    <?php elseif ($abonnement['etat'] === 'suspendue'): ?>
                        Votre boutique est suspendue. Contactez le support SAMA MAILLOT.
                    <?php else: ?>
                        Un abonnement actif est nécessaire pour publier des maillots. Le renouvellement par Wave arrive très prochainement.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>

            <?php if ($bloque_par_admin): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                    Ce maillot a été masqué par l'administration (non conforme à la politique vendeur). Vous pouvez le corriger,
                    mais seule l'administration peut le rendre de nouveau visible.
                </div>
            <?php endif; ?>

            <?= alerte_formulaire($erreurs) ?>

            <form method="post" enctype="multipart/form-data" novalidate class="sm-formulaire-vendeur">
                <?= champ_csrf() ?>
                <input type="hidden" name="id" value="<?= e($id) ?>">

                <div class="row g-4">
                    <div class="col-12 col-lg-8">
                        <!-- Informations -->
                        <fieldset class="sm-tuile mb-4">
                            <legend class="h5 fw-bold mb-3">Informations</legend>

                            <div class="mb-3">
                                <label class="form-label" for="titre">Titre</label>
                                <input class="form-control<?= classe_erreur($erreurs, 'titre') ?>" type="text" id="titre" name="titre"
                                       value="<?= e($valeurs['titre']) ?>" minlength="10" maxlength="100" required
                                       placeholder="Maillot Sénégal domicile 2025/2026" <?= aria_erreur($erreurs, 'titre') ?>>
                                <?= message_erreur($erreurs, 'titre') ?>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="categorie_id">Catégorie</label>
                                    <select class="form-select<?= classe_erreur($erreurs, 'categorie_id') ?>" id="categorie_id" name="categorie_id" required <?= aria_erreur($erreurs, 'categorie_id') ?>>
                                        <option value="">Choisir…</option>
                                        <?php foreach ($categories as $categorie): ?>
                                            <option value="<?= e($categorie['id']) ?>"<?= $valeurs['categorie_id'] === (string) $categorie['id'] ? ' selected' : '' ?>><?= e($categorie['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= message_erreur($erreurs, 'categorie_id') ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="equipe_id">Équipe</label>
                                    <select class="form-select<?= classe_erreur($erreurs, 'equipe_id') ?>" id="equipe_id" name="equipe_id" required
                                            aria-describedby="aide-equipe<?= isset($erreurs['equipe_id']) ? ' erreur-equipe_id' : '' ?>">
                                        <option value="">Choisir…</option>
                                        <optgroup label="Sélections">
                                            <?php foreach ($equipes['selection'] as $equipe): ?>
                                                <option value="<?= e($equipe['id']) ?>"<?= $valeurs['equipe_id'] === (string) $equipe['id'] ? ' selected' : '' ?>><?= e($equipe['nom']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="Clubs">
                                            <?php foreach ($equipes['club'] as $equipe): ?>
                                                <option value="<?= e($equipe['id']) ?>"<?= $valeurs['equipe_id'] === (string) $equipe['id'] ? ' selected' : '' ?>><?= e($equipe['nom']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                    <div class="form-text" id="aide-equipe">Équipe absente ? Écrivez au support pour la faire ajouter.</div>
                                    <?= message_erreur($erreurs, 'equipe_id') ?>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="saison">Saison</label>
                                    <input class="form-control<?= classe_erreur($erreurs, 'saison') ?>" type="text" id="saison" name="saison"
                                           value="<?= e($valeurs['saison']) ?>" maxlength="11" placeholder="2025/2026" required
                                           <?= aria_erreur($erreurs, 'saison') ?>>
                                    <?= message_erreur($erreurs, 'saison') ?>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="type">Type</label>
                                    <select class="form-select" id="type" name="type">
                                        <?php foreach (LIBELLES_TYPE as $cle => $libelle): ?>
                                            <option value="<?= e($cle) ?>"<?= $valeurs['type'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="etat">État</label>
                                    <select class="form-select" id="etat" name="etat">
                                        <?php foreach (LIBELLES_ETAT as $cle => $libelle): ?>
                                            <option value="<?= e($cle) ?>"<?= $valeurs['etat'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Authenticité : obligatoire et affichée clairement (§9.4) -->
                            <fieldset class="mb-2">
                                <legend class="form-label fs-6">Authenticité</legend>
                                <div class="sm-choix-authenticite">
                                    <?php foreach (LIBELLES_AUTHENTICITE as $cle => $libelle): ?>
                                        <input class="btn-check" type="radio" name="authenticite" id="authenticite-<?= e($cle) ?>" value="<?= e($cle) ?>"
                                               <?= $valeurs['authenticite'] === $cle ? 'checked' : '' ?> required>
                                        <label class="sm-taille" for="authenticite-<?= e($cle) ?>"><?= e($libelle) ?></label>
                                    <?php endforeach; ?>
                                </div>
                                <?= message_erreur($erreurs, 'authenticite') ?>
                            </fieldset>
                            <div class="alert alert-warning small mb-0" role="note">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                Les répliques sont autorisées uniquement si elles sont déclarées comme telles. Présenter une réplique
                                comme « Officiel » entraîne la suppression du maillot et peut conduire à la suspension du compte.
                            </div>
                        </fieldset>

                        <!-- Tailles et stock -->
                        <fieldset class="sm-tuile mb-4">
                            <legend class="h5 fw-bold mb-1">Tailles et stock</legend>
                            <p class="small text-body-secondary">Laissez vide une taille que vous ne proposez pas. 0 = épuisée.</p>
                            <div class="sm-grille-stock">
                                <?php foreach (TAILLES as $taille): ?>
                                    <div>
                                        <label class="form-label fw-semibold" for="stock-<?= e($taille) ?>"><?= e($taille) ?></label>
                                        <input class="form-control<?= classe_erreur($erreurs, 'stock') ?>" type="number" inputmode="numeric" min="0" max="999"
                                               id="stock-<?= e($taille) ?>" name="stock[<?= e($taille) ?>]" value="<?= e($stocks[$taille] ?? '') ?>" placeholder="—">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?= message_erreur($erreurs, 'stock') ?>
                        </fieldset>

                        <!-- Photos -->
                        <fieldset class="sm-tuile mb-4">
                            <legend class="h5 fw-bold mb-1">Photos</legend>
                            <p class="small text-body-secondary">
                                Jusqu'à <?= e(photos_par_maillot_max()) ?> photos (face, dos, détails…) : une seule suffit pour publier.
                                JPG, PNG, WebP ou AVIF, <?= e(parametre('photo_taille_max_mo', '5')) ?> Mo maximum chacune, au moins <?= e(PHOTO_MAILLOT_COTE_MIN) ?> × <?= e(PHOTO_MAILLOT_COTE_MIN) ?> px.
                                La photo principale apparaît dans le catalogue ; les autres défilent sur la fiche du maillot.
                            </p>

                            <?php if ($photos !== []): ?>
                                <div class="sm-photos-vendeur mb-3">
                                    <?php foreach ($photos as $photo): ?>
                                        <div class="sm-photo-vendeur">
                                            <img src="<?= e(url_image_maillot($photo['miniature'])) ?>" alt="" width="400" height="400" loading="lazy">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="radio" name="photo_principale" id="principale-<?= e($photo['id']) ?>"
                                                       value="<?= e($photo['id']) ?>"<?= (int) $photo['id'] === (int) $photo_principale ? ' checked' : '' ?>>
                                                <label class="form-check-label small" for="principale-<?= e($photo['id']) ?>">Principale</label>
                                            </div>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" name="supprimer_photo[]" data-photo-existante id="supprimer-<?= e($photo['id']) ?>"
                                                       value="<?= e($photo['id']) ?>"<?= in_array((int) $photo['id'], $photos_a_supprimer, true) ? ' checked' : '' ?>>
                                                <label class="form-check-label small" for="supprimer-<?= e($photo['id']) ?>">Retirer</label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="sm-photos-vendeur mb-3" id="apercu-photos" hidden></div>

                            <label class="form-label" for="photos">Ajouter des photos</label>
                            <input class="form-control<?= classe_erreur($erreurs, 'photos') ?>" type="file" id="photos" name="photos[]"
                                   accept="image/jpeg,image/png,image/webp,image/avif" multiple data-ajout-photos="apercu-photos"
                                   data-photos-max="<?= e(photos_par_maillot_max()) ?>" aria-describedby="aide-photos<?= isset($erreurs['photos']) ? ' erreur-photos' : '' ?>"
                                   <?= isset($erreurs['photos']) ? 'aria-invalid="true"' : '' ?>>
                            <div class="form-text" id="aide-photos" data-photos-restantes>
                                Vous pouvez en choisir plusieurs à la fois, ou les ajouter une par une.
                            </div>
                            <?= message_erreur($erreurs, 'photos') ?>
                        </fieldset>

                        <!-- Description -->
                        <fieldset class="sm-tuile">
                            <legend class="h5 fw-bold mb-3">Description</legend>
                            <label class="visually-hidden" for="description">Description</label>
                            <textarea class="form-control<?= classe_erreur($erreurs, 'description') ?>" id="description" name="description" rows="6"
                                      maxlength="2000" placeholder="Matière, coupe, détails, défauts éventuels…" <?= aria_erreur($erreurs, 'description') ?>><?= e($valeurs['description']) ?></textarea>
                            <div class="form-text">2 000 caractères maximum.</div>
                            <?= message_erreur($erreurs, 'description') ?>
                        </fieldset>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="sm-colonne-collante">
                            <!-- Prix et flocage -->
                            <fieldset class="sm-tuile mb-4">
                                <legend class="h5 fw-bold mb-3">Prix</legend>
                                <label class="form-label" for="prix">Prix de vente</label>
                                <div class="input-group">
                                    <input class="form-control<?= classe_erreur($erreurs, 'prix') ?>" type="text" inputmode="numeric" id="prix" name="prix"
                                           value="<?= e($valeurs['prix']) ?>" placeholder="15000" required <?= aria_erreur($erreurs, 'prix') ?>>
                                    <span class="input-group-text">FCFA</span>
                                </div>
                                <div class="form-text">Entre 1 000 et 500 000 FCFA.</div>
                                <?= message_erreur($erreurs, 'prix') ?>

                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" id="flocage_propose" name="flocage_propose" value="1"<?= $valeurs['flocage_propose'] ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="flocage_propose">Je propose le flocage (nom et numéro)</label>
                                </div>
                                <label class="form-label mt-2" for="flocage_supplement">Supplément de flocage</label>
                                <div class="input-group">
                                    <input class="form-control<?= classe_erreur($erreurs, 'flocage_supplement') ?>" type="text" inputmode="numeric" id="flocage_supplement"
                                           name="flocage_supplement" value="<?= e($valeurs['flocage_supplement']) ?>" placeholder="2000">
                                    <span class="input-group-text">FCFA</span>
                                </div>
                                <?= message_erreur($erreurs, 'flocage_supplement') ?>
                            </fieldset>

                            <!-- Actions -->
                            <div class="sm-tuile">
                                <p class="small text-body-secondary mb-3">
                                    Pour publier : au moins une photo et une taille en stock.
                                    <?php if ($abonnement['etat'] === 'grace'): ?>
                                        <br><strong>Période de grâce :</strong> pensez à renouveler votre abonnement.
                                    <?php endif; ?>
                                </p>
                                <div class="d-grid gap-2">
                                    <?php if (!$bloque_par_admin && ($nouveau || $maillot['statut'] !== 'publie')): ?>
                                        <button class="btn btn-or" type="submit" name="action" value="publier"<?= $abonnement['peut_publier'] ? '' : ' disabled' ?>>
                                            <i class="bi bi-send-check" aria-hidden="true"></i> Publier
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn <?= !$nouveau && $maillot['statut'] === 'publie' ? 'btn-or' : 'btn-contour' ?>" type="submit" name="action" value="enregistrer">
                                        <i class="bi bi-floppy" aria-hidden="true"></i>
                                        <?= $nouveau ? 'Enregistrer en brouillon' : 'Enregistrer les modifications' ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
