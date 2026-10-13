<?php
/**
 * SAMA MAILLOT — catalogue et recherche (§10.1 à §10.3).
 *
 * Adresses : /catalogue, /catalogue/clubs, /catalogue/?q=senegal&taille=L&tri=prix_asc
 * Filtres V1 : catégorie, équipe, fourchette de prix, taille. 24 maillots par page.
 */
require __DIR__ . '/../../includes/config.php';

$filtres = lire_filtres($_GET);

// Catégorie demandée dans l'adresse mais inconnue → 404
$categorie = null;
if ($filtres['categorie'] !== '') {
    $categorie = categorie_par_slug($filtres['categorie']);
    if ($categorie === null) {
        afficher_erreur(404);
    }
}

$resultat = rechercher_maillots($filtres);
$equipes = equipes_par_type();

// Adresse de cette page avec d'autres valeurs de filtres (pagination, liens de réinitialisation)
$adresse = static fn (array $changements = []): string => url_catalogue(array_merge($filtres, $changements, ['boutique_id' => null]));

// Titre de la page
if ($filtres['q'] !== '') {
    $titre_page = 'Recherche « ' . $filtres['q'] . ' »';
} elseif ($categorie !== null) {
    $titre_page = 'Maillots ' . mb_strtolower($categorie['nom']);
} else {
    $titre_page = 'Catalogue des maillots';
}

$description_page = $categorie !== null
    ? $categorie['description'] . (paiement_en_ligne_actif() ? ' Prix en FCFA, paiement sécurisé ou à la livraison.' : ' Prix en FCFA, paiement à la livraison.')
    : 'Tous les maillots de football vendus par les boutiques du Sénégal : équipes nationales, clubs et rétro.';

// Les pages de résultats de recherche et les combinaisons de filtres ne sont pas indexées (§30)
$sans_indexation = filtres_actifs($filtres);
$page_active = 'catalogue';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-catalogue">
    <div class="container">

        <!-- Fil d'Ariane -->
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb sm-fil-ariane">
                <li class="breadcrumb-item"><a href="<?= e(url()) ?>">Accueil</a></li>
                <?php if ($categorie !== null): ?>
                    <li class="breadcrumb-item"><a href="<?= e(url_catalogue()) ?>">Catalogue</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($categorie['nom']) ?></li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Catalogue</li>
                <?php endif; ?>
            </ol>
        </nav>

        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
            <div>
                <h1 class="sm-titre-section mb-1"><?= e($titre_page) ?></h1>
                <p class="text-body-secondary mb-0" role="status">
                    <?= e($resultat['total']) ?> maillot<?= $resultat['total'] > 1 ? 's' : '' ?>
                    <?= $resultat['total'] > 1 ? 'disponibles' : 'disponible' ?>
                </p>
            </div>

            <div class="d-flex gap-2 align-items-center">
                <!-- Bouton des filtres : mobile et tablette -->
                <button class="btn btn-contour d-lg-none" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#filtres-catalogue" aria-controls="filtres-catalogue">
                    <i class="bi bi-sliders" aria-hidden="true"></i> Filtres
                </button>

                <label class="visually-hidden" for="tri">Trier par</label>
                <select class="form-select sm-select-tri" id="tri" name="tri" form="formulaire-filtres" data-envoi-auto>
                    <?php foreach (TRIS as $cle => $libelle): ?>
                        <option value="<?= e($cle) ?>"<?= $filtres['tri'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Onglets des catégories -->
        <ul class="sm-onglets mb-4" aria-label="Catégories">
            <li><a class="sm-onglet" href="<?= e(url_catalogue(array_merge($filtres, ['categorie' => '', 'page' => 1, 'boutique_id' => null]))) ?>"
                   <?= $categorie === null ? 'aria-current="page"' : '' ?>>Tous</a></li>
            <?php foreach (categories_actives() as $cat): ?>
                <li><a class="sm-onglet" href="<?= e(url_catalogue(array_merge($filtres, ['categorie' => $cat['slug'], 'page' => 1, 'boutique_id' => null]))) ?>"
                       <?= ($categorie['slug'] ?? '') === $cat['slug'] ? 'aria-current="page"' : '' ?>><?= e($cat['nom']) ?></a></li>
            <?php endforeach; ?>
        </ul>

        <div class="row g-4">
            <!-- Filtres : colonne sur ordinateur, volet coulissant sur mobile -->
            <aside class="col-lg-3">
                <div class="offcanvas-lg offcanvas-start sm-filtres" tabindex="-1" id="filtres-catalogue" aria-labelledby="titre-filtres">
                    <div class="offcanvas-header">
                        <h2 class="h5 mb-0" id="titre-filtres">Filtres</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#filtres-catalogue" aria-label="Fermer"></button>
                    </div>
                    <div class="offcanvas-body">
                        <form id="formulaire-filtres" class="w-100" method="get" action="<?= e(url_catalogue(['categorie' => $filtres['categorie']])) ?>">
                            <div class="mb-3">
                                <label class="form-label" for="filtre-q">Recherche</label>
                                <input class="form-control" type="search" id="filtre-q" name="q" value="<?= e($filtres['q']) ?>"
                                       placeholder="Équipe, saison…" maxlength="100">
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="filtre-equipe">Équipe</label>
                                <select class="form-select" id="filtre-equipe" name="equipe">
                                    <option value="">Toutes les équipes</option>
                                    <optgroup label="Sélections">
                                        <?php foreach ($equipes['selection'] as $equipe): ?>
                                            <option value="<?= e($equipe['slug']) ?>"<?= $filtres['equipe'] === $equipe['slug'] ? ' selected' : '' ?>><?= e($equipe['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Clubs">
                                        <?php foreach ($equipes['club'] as $equipe): ?>
                                            <option value="<?= e($equipe['slug']) ?>"<?= $filtres['equipe'] === $equipe['slug'] ? ' selected' : '' ?>><?= e($equipe['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>

                            <fieldset class="mb-3">
                                <legend class="form-label fs-6">Prix (FCFA)</legend>
                                <div class="d-flex gap-2 align-items-center">
                                    <label class="visually-hidden" for="filtre-prix-min">Prix minimum</label>
                                    <input class="form-control" type="number" id="filtre-prix-min" name="prix_min" inputmode="numeric"
                                           min="0" max="500000" step="500" placeholder="Min" value="<?= $filtres['prix_min'] > 0 ? e($filtres['prix_min']) : '' ?>">
                                    <span aria-hidden="true">–</span>
                                    <label class="visually-hidden" for="filtre-prix-max">Prix maximum</label>
                                    <input class="form-control" type="number" id="filtre-prix-max" name="prix_max" inputmode="numeric"
                                           min="0" max="500000" step="500" placeholder="Max" value="<?= $filtres['prix_max'] > 0 ? e($filtres['prix_max']) : '' ?>">
                                </div>
                            </fieldset>

                            <fieldset class="mb-4">
                                <legend class="form-label fs-6">Taille disponible</legend>
                                <div class="sm-choix-tailles">
                                    <input class="btn-check" type="radio" name="taille" id="taille-toutes" value=""<?= $filtres['taille'] === '' ? ' checked' : '' ?>>
                                    <label class="sm-taille" for="taille-toutes">Toutes</label>
                                    <?php foreach (TAILLES as $n => $taille): ?>
                                        <input class="btn-check" type="radio" name="taille" id="taille-<?= e($n) ?>" value="<?= e($taille) ?>"<?= $filtres['taille'] === $taille ? ' checked' : '' ?>>
                                        <label class="sm-taille" for="taille-<?= e($n) ?>"><?= e($taille) ?></label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>

                            <div class="d-grid gap-2">
                                <button class="btn btn-or" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Appliquer</button>
                                <?php if (filtres_actifs($filtres)): ?>
                                    <a class="btn btn-contour" href="<?= e(url_catalogue(['categorie' => $filtres['categorie']])) ?>">Effacer les filtres</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </aside>

            <!-- Résultats -->
            <div class="col-lg-9">
                <?php if ($resultat['maillots'] !== []): ?>
                    <div class="sm-grille-maillots">
                        <?php foreach ($resultat['maillots'] as $maillot): ?>
                            <?= carte_maillot($maillot) ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4">
                        <?= pagination($resultat['page'], $resultat['pages'], static fn (int $p): string => $adresse(['page' => $p])) ?>
                    </div>
                <?php else: ?>
                    <!-- Aucun résultat : message clair et suggestions (§10.2) -->
                    <div class="sm-etat-vide">
                        <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-search"></i></div>
                        <h2 class="h3">Aucun maillot ne correspond</h2>
                        <p class="mb-4">
                            <?php if ($filtres['q'] !== ''): ?>
                                Aucun résultat pour « <?= e($filtres['q']) ?> ». Vérifiez l'orthographe ou essayez un autre mot
                                (le nom d'une équipe, une saison…).
                            <?php else: ?>
                                Aucun maillot n'est disponible avec ces filtres pour le moment.
                            <?php endif; ?>
                        </p>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <?php foreach (categories_actives() as $cat): ?>
                                <a class="btn btn-contour" href="<?= e(url_catalogue(['categorie' => $cat['slug']])) ?>"><?= e($cat['nom']) ?></a>
                            <?php endforeach; ?>
                            <?php foreach (equipes_populaires(4) as $equipe): ?>
                                <a class="btn btn-contour" href="<?= e(url_catalogue(['equipe' => $equipe['slug']])) ?>"><?= e($equipe['nom']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
