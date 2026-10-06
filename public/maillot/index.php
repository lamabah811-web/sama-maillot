<?php
/**
 * SAMA MAILLOT — fiche maillot (§10.4).
 *
 * Adresse lisible : /maillot/senegal-domicile-2025-2026-142 (le numéro final est l'id).
 * Panier et « Commander via WhatsApp » arrivent au lot 4 : les boutons sont visibles mais
 * désactivés. « Poser une question » (WhatsApp) et « Favori » fonctionnent dès maintenant.
 */
require __DIR__ . '/../../includes/config.php';

$id = (int) ($_GET['id'] ?? 0);
$maillot = $id > 0 ? maillot_par_id($id) : null;

if ($maillot === null) {
    afficher_erreur(404);
}

// Adresse canonique : si le texte de l'adresse ne correspond pas (titre modifié...), on redirige.
$chemin_attendu = 'maillot/' . $maillot['slug'] . '-' . $maillot['id'];
if (rtrim((string) parse_url(chemin_relatif_actuel(), PHP_URL_PATH), '/') !== $chemin_attendu) {
    header('Location: ' . url_maillot($maillot), true, 301);
    exit;
}

$tailles = tailles_du_maillot((int) $maillot['id']);
$zones_livraison = zones_desservies((int) $maillot['boutique_id']);
$images = images_du_maillot((int) $maillot['id']);
$stock_total = array_sum($tailles);
$note = note_vendeur((int) $maillot['boutique_id']);
$utilisateur = utilisateur_connecte();
$est_favori = $utilisateur !== null && in_array((int) $maillot['id'], ids_favoris((int) $utilisateur['id']), true);

// Achat (§5.1) : un vendeur n'achète pas dans sa propre boutique ; un administrateur n'achète pas.
$propre_boutique = $utilisateur !== null && boutique_proprietaire((int) $maillot['boutique_id']) === (int) $utilisateur['id'];
$achat_possible = peut_acheter($utilisateur) && !$propre_boutique && $stock_total > 0;

// Ajout au panier refusé juste avant : message affiché près du bouton, choix du client conservés.
$achat_refuse = null;
if ((int) ($_SESSION['achat_refuse']['maillot_id'] ?? 0) === (int) $maillot['id']) {
    $achat_refuse = $_SESSION['achat_refuse'];
}
unset($_SESSION['achat_refuse']);
$taille_choisie = (string) ($achat_refuse['taille'] ?? '');

// Autres maillots de la même équipe (hors celui-ci)
$similaires = array_values(array_filter(
    rechercher_maillots(array_merge(lire_filtres([]), ['equipe' => $maillot['equipe_slug']]), 5)['maillots'],
    static fn (array $m): bool => (int) $m['id'] !== (int) $maillot['id']
));
$similaires = array_slice($similaires, 0, 4);

$titre_page = $maillot['titre'];
$description_page = $maillot['titre'] . ' — ' . LIBELLES_AUTHENTICITE[$maillot['authenticite']] . ', '
    . mb_strtolower(LIBELLES_ETAT[$maillot['etat']]) . '. ' . format_fcfa((int) $maillot['prix'])
    . ' chez ' . $maillot['boutique_nom'] . ' sur SAMA MAILLOT.';
$page_active = 'catalogue';
$url_canonique = url_maillot($maillot);
$type_partage = 'product';
$image_partage = $images !== [] ? url_image_maillot($images[0]['fichier']) : null;

// Données structurées « Product » pour les moteurs de recherche (§30)
$donnees_structurees = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $maillot['titre'],
    'description' => mb_substr((string) ($maillot['description'] ?? $maillot['titre']), 0, 300),
    'category' => $maillot['categorie_nom'],
    'offers' => [
        '@type' => 'Offer',
        'price' => (int) $maillot['prix'],
        'priceCurrency' => 'XOF',
        'availability' => $stock_total > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => url_maillot($maillot),
        'seller' => ['@type' => 'Organization', 'name' => $maillot['boutique_nom']],
    ],
];
if ($images !== []) {
    $donnees_structurees['image'] = array_map(static fn (array $i): string => url_image_maillot($i['fichier']), $images);
}

require __DIR__ . '/../../includes/entete.php';
?>

<script type="application/ld+json"><?= json_encode($donnees_structurees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>

<section class="sm-page-fiche">
    <div class="container">
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb sm-fil-ariane">
                <li class="breadcrumb-item"><a href="<?= e(url()) ?>">Accueil</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url_catalogue(['categorie' => $maillot['categorie_slug']])) ?>"><?= e($maillot['categorie_nom']) ?></a></li>
                <li class="breadcrumb-item"><a href="<?= e(url_catalogue(['equipe' => $maillot['equipe_slug']])) ?>"><?= e($maillot['equipe_nom']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($maillot['titre']) ?></li>
            </ol>
        </nav>

        <div class="row g-4 g-lg-5">
            <!-- ===== Galerie ===== -->
            <div class="col-12 col-lg-6">
                <div class="sm-galerie">
                    <?php if ($images !== []): ?>
                        <!-- Carrousel : flèches, glissement au doigt, miniatures ; un clic agrandit la photo -->
                        <div id="carrousel-maillot" class="carousel slide sm-carrousel" data-bs-touch="true" data-bs-interval="false"
                             aria-roledescription="carrousel" aria-label="Photos du maillot">
                            <div class="carousel-inner">
                                <?php foreach ($images as $n => $image): ?>
                                    <div class="carousel-item<?= $n === 0 ? ' active' : '' ?>" aria-roledescription="diapositive"
                                         aria-label="Photo <?= e($n + 1) ?> sur <?= e(count($images)) ?>">
                                        <button class="sm-galerie__principale" type="button" data-bs-toggle="modal" data-bs-target="#zoom-photo"
                                                aria-label="Agrandir la photo <?= e($n + 1) ?>">
                                            <img src="<?= e(url_image_maillot($image['fichier'])) ?>"
                                                 alt="<?= e($maillot['titre']) ?><?= $n > 0 ? ' — photo ' . e($n + 1) : '' ?>"
                                                 width="1200" height="1200"<?= $n === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($images) > 1): ?>
                                <button class="sm-carrousel__fleche sm-carrousel__fleche--precedente" type="button"
                                        data-bs-target="#carrousel-maillot" data-bs-slide="prev" aria-label="Photo précédente">
                                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                </button>
                                <button class="sm-carrousel__fleche sm-carrousel__fleche--suivante" type="button"
                                        data-bs-target="#carrousel-maillot" data-bs-slide="next" aria-label="Photo suivante">
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </button>
                                <span class="sm-carrousel__compteur" aria-hidden="true">
                                    <span data-carrousel-position>1</span> / <?= e(count($images)) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (count($images) > 1): ?>
                            <div class="sm-galerie__miniatures">
                                <?php foreach ($images as $n => $image): ?>
                                    <button class="sm-galerie__miniature" type="button" data-bs-target="#carrousel-maillot"
                                            data-bs-slide-to="<?= e($n) ?>" aria-label="Voir la photo <?= e($n + 1) ?>"<?= $n === 0 ? ' aria-current="true"' : '' ?>>
                                        <img src="<?= e(url_image_maillot($image['miniature'])) ?>" alt="" width="400" height="400" loading="lazy">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="sm-galerie__vide">
                            <i class="bi bi-image" aria-hidden="true"></i>
                            <span>Les photos de ce maillot arrivent bientôt</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== Informations et achat ===== -->
            <div class="col-12 col-lg-6">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="sm-badge-authenticite sm-badge-authenticite--<?= e($maillot['authenticite']) ?> position-static">
                        <?= e(LIBELLES_AUTHENTICITE[$maillot['authenticite']]) ?>
                    </span>
                    <span class="sm-etiquette-info"><?= e(LIBELLES_ETAT[$maillot['etat']]) ?></span>
                    <span class="sm-etiquette-info"><?= e(LIBELLES_TYPE[$maillot['type']]) ?></span>
                </div>

                <h1 class="sm-fiche__titre"><?= e($maillot['titre']) ?></h1>
                <p class="text-body-secondary mb-3">
                    <?= e($maillot['equipe_nom']) ?> · Saison <?= e($maillot['saison']) ?> · <?= e($maillot['categorie_nom']) ?>
                </p>

                <p class="sm-fiche__prix"><?= e(format_fcfa((int) $maillot['prix'])) ?></p>
                <?php if ((int) $maillot['flocage_propose'] === 1): ?>
                    <p class="text-body-secondary small mb-3">
                        <i class="bi bi-type" aria-hidden="true"></i>
                        Flocage nom et numéro proposé : +<?= e(format_fcfa((int) $maillot['flocage_supplement'])) ?>
                    </p>
                <?php endif; ?>

                <!-- Achat (§10.4, §11, §17) : un seul formulaire ; « Commander via WhatsApp » l'envoie ailleurs (formaction) -->
                <form method="post" action="<?= e(url_page('panier')) ?>" id="form-achat">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="ajouter">
                <input type="hidden" name="maillot_id" value="<?= e($maillot['id']) ?>">

                <!-- Choix de la taille : tailles épuisées grisées (§10.4) -->
                <fieldset class="mb-3">
                    <legend class="form-label fs-6 fw-semibold">Taille</legend>
                    <?php if ($tailles !== []): ?>
                        <div class="sm-choix-tailles">
                            <?php foreach ($tailles as $taille => $stock): ?>
                                <?php $id_taille = 'taille-' . preg_replace('/[^a-z0-9]+/i', '-', $taille); ?>
                                <input class="btn-check" type="radio" name="taille" id="<?= e($id_taille) ?>" value="<?= e($taille) ?>" required data-stock="<?= e($stock) ?>"
                                       <?= $stock <= 0 ? 'disabled' : '' ?><?= $stock > 0 && ($taille_choisie === (string) $taille || ($taille_choisie === '' && count(array_filter($tailles)) === 1)) ? ' checked' : '' ?>>
                                <label class="sm-taille<?= $stock <= 0 ? ' sm-taille--epuisee' : '' ?>" for="<?= e($id_taille) ?>"
                                       <?= $stock <= 0 ? 'title="Épuisée"' : '' ?>>
                                    <?= e($taille) ?><?php if ($stock <= 0): ?><span class="visually-hidden"> (épuisée)</span><?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($stock_total <= 0): ?>
                            <p class="text-danger small mt-2 mb-0"><i class="bi bi-x-circle" aria-hidden="true"></i> Ce maillot est en rupture de stock.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-body-secondary small mb-0">Tailles non renseignées par le vendeur.</p>
                    <?php endif; ?>
                </fieldset>

                <?php if ($achat_possible): ?>
                    <div class="row g-2 mb-3 align-items-end">
                        <div class="col-auto">
                            <label class="form-label fw-semibold" for="quantite">Quantité</label>
                            <select class="form-select sm-select-quantite" id="quantite" name="quantite" data-quantite-max="<?= e(QUANTITE_MAX_PAR_LIGNE) ?>">
                                <?php for ($q = 1; $q <= min(QUANTITE_MAX_PAR_LIGNE, $stock_total); $q++): ?>
                                    <option value="<?= $q ?>"<?= $q === (int) ($achat_refuse['quantite'] ?? 1) ? ' selected' : '' ?>><?= $q ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <?php if ((int) $maillot['flocage_propose'] === 1): ?>
                        <!-- Flocage (§9.4) : nom et numéro saisis par le client -->
                        <details class="sm-flocage mb-3"<?= ($achat_refuse['flocage_nom'] ?? '') !== '' || ($achat_refuse['flocage_numero'] ?? '') !== '' ? ' open' : '' ?>>
                            <summary>
                                <i class="bi bi-type" aria-hidden="true"></i> Ajouter un flocage
                                (+<?= e(format_fcfa((int) $maillot['flocage_supplement'])) ?>)
                            </summary>
                            <div class="row g-2 mt-1">
                                <div class="col-8">
                                    <label class="form-label small" for="flocage_nom">Nom</label>
                                    <input class="form-control" type="text" id="flocage_nom" name="flocage_nom" maxlength="12" value="<?= e($achat_refuse['flocage_nom'] ?? '') ?>"
                                           placeholder="MANÉ" autocomplete="off" autocapitalize="characters">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small" for="flocage_numero">Numéro</label>
                                    <input class="form-control" type="text" id="flocage_numero" name="flocage_numero" maxlength="2" value="<?= e($achat_refuse['flocage_numero'] ?? '') ?>"
                                           inputmode="numeric" pattern="[0-9]{1,2}" placeholder="10" autocomplete="off">
                                </div>
                            </div>
                            <p class="form-text mb-0">Laissez vide pour un maillot sans flocage.</p>
                        </details>
                    <?php endif; ?>

                    <?php if ($achat_refuse !== null): ?>
                        <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
                            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                            <span><?= e($achat_refuse['message']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mb-3">
                        <button class="btn btn-or btn-lg" type="submit">
                            <i class="bi bi-bag-plus" aria-hidden="true"></i> Ajouter au panier
                        </button>
                        <button class="btn btn-contour btn-lg" type="submit" formaction="<?= e(url('commande/whatsapp.php')) ?>">
                            <i class="bi bi-whatsapp" aria-hidden="true"></i> Commander via WhatsApp
                        </button>
                    </div>
                <?php elseif ($propre_boutique): ?>
                    <p class="alert alert-info small mb-3" role="note">
                        <i class="bi bi-shop" aria-hidden="true"></i>
                        C'est un maillot de votre boutique : vous ne pouvez pas l'acheter.
                        <a href="<?= e(url('vendeur/maillot.php?id=' . (int) $maillot['id'])) ?>">Modifier ce maillot</a>
                    </p>
                <?php elseif (!peut_acheter($utilisateur)): ?>
                    <p class="small text-body-secondary mb-3">Un compte administrateur ne peut pas passer de commande.</p>
                <?php endif; ?>
                </form>

                <?php if ($achat_possible): ?>
                    <!-- Mobile (§29.2) : « Ajouter au panier » toujours à portée de pouce, au-dessus de la barre basse.
                         Il envoie le formulaire ci-dessus : sans taille choisie, le navigateur y amène le client. -->
                    <div class="sm-barre-achat d-lg-none">
                        <span class="sm-barre-achat__prix"><?= e(format_fcfa((int) $maillot['prix'])) ?></span>
                        <button class="btn btn-or" type="submit" form="form-achat">
                            <i class="bi bi-bag-plus" aria-hidden="true"></i> Ajouter au panier
                        </button>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a class="btn btn-contour flex-grow-1" href="<?= e(url('whatsapp.php?maillot=' . (int) $maillot['id'])) ?>"
                       target="_blank" rel="noopener nofollow">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i> Poser une question
                    </a>
                    <form method="post" action="<?= e(url('favoris/basculer.php')) ?>">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="maillot_id" value="<?= e($maillot['id']) ?>">
                        <input type="hidden" name="retour" value="<?= e(chemin_relatif_actuel()) ?>">
                        <button class="btn btn-contour sm-bouton-favori<?= $est_favori ? ' sm-bouton-favori--actif' : '' ?>" type="submit"
                                aria-pressed="<?= $est_favori ? 'true' : 'false' ?>">
                            <i class="bi <?= $est_favori ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
                            <?= $est_favori ? 'Dans vos favoris' : 'Ajouter aux favoris' ?>
                        </button>
                    </form>
                </div>

                <!-- Paiement sécurisé (§10.4) -->
                <div class="sm-encadre-confiance mb-3">
                    <span class="sm-icone-teintee" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                    <div>
                        <strong>Paiement sécurisé</strong>
                        <p class="mb-0">En paiement en ligne, votre argent est remboursé si vous ne recevez pas votre maillot.</p>
                    </div>
                </div>

                <!-- Vendeur (§10.4) -->
                <div class="sm-encadre-vendeur mb-3">
                    <?php if (!empty($maillot['boutique_logo'])): ?>
                        <img class="sm-avatar sm-avatar--image" src="<?= e(url_image_boutique($maillot['boutique_logo'])) ?>" alt="" width="400" height="400" loading="lazy">
                    <?php else: ?>
                        <span class="sm-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($maillot['boutique_nom'], 0, 2))) ?></span>
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <a class="sm-encadre-vendeur__nom" href="<?= e(url_boutique($maillot['boutique_slug'])) ?>"><?= e($maillot['boutique_nom']) ?></a>
                        <span class="d-block small text-body-secondary">
                            <i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e($maillot['boutique_ville']) ?>
                            · <span class="sm-badge-nouveau"><?= e($note['libelle']) ?></span>
                        </span>
                    </div>
                    <a class="btn btn-contour btn-sm" href="<?= e(url_boutique($maillot['boutique_slug'])) ?>">Voir la boutique</a>
                </div>

                <!-- Livraison (§9.3) : zones et tarifs fixés par le vendeur, payés par le client -->
                <div class="sm-encadre-livraison mb-3">
                    <h2 class="h6 fw-bold mb-2"><i class="bi bi-truck" aria-hidden="true"></i> Livraison par le vendeur</h2>
                    <?php if ($zones_livraison === [] && (int) $maillot['boutique_retrait'] !== 1): ?>
                        <p class="small text-body-secondary mb-0">Le vendeur n'a pas encore indiqué ses zones de livraison. Posez-lui la question sur WhatsApp.</p>
                    <?php else: ?>
                        <ul class="sm-liste-zones">
                            <?php foreach ($zones_livraison as $zone): ?>
                                <li>
                                    <span><?= e($zone['nom']) ?> <span class="text-body-secondary small">· <?= e($zone['delai']) ?></span></span>
                                    <strong><?= (int) $zone['tarif'] === 0 ? 'Gratuit' : e(format_fcfa((int) $zone['tarif'])) ?></strong>
                                </li>
                            <?php endforeach; ?>
                            <?php if ((int) $maillot['boutique_retrait'] === 1): ?>
                                <li>
                                    <span>Retrait sur place <span class="text-body-secondary small">· <?= e($maillot['boutique_adresse_retrait']) ?></span></span>
                                    <strong>Gratuit</strong>
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <?php if (refus_signalement_maillot($utilisateur, $maillot) === null): ?>
                    <!-- Signalement : uniquement pour un client ayant commandé chez ce vendeur (§21) -->
                    <p class="small mb-0">
                        <a class="text-body-secondary" href="<?= e(url('signalement/?maillot=' . (int) $maillot['id'])) ?>">
                            <i class="bi bi-flag" aria-hidden="true"></i> Signaler ce maillot
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Description et caractéristiques ===== -->
        <div class="row g-4 mt-2">
            <div class="col-12 col-lg-7">
                <h2 class="h4 fw-bold">Description</h2>
                <?php if (!empty($maillot['description'])): ?>
                    <p class="sm-fiche__description"><?= nl2br(e($maillot['description'])) ?></p>
                <?php else: ?>
                    <p class="text-body-secondary">Le vendeur n'a pas ajouté de description.</p>
                <?php endif; ?>
            </div>
            <div class="col-12 col-lg-5">
                <h2 class="h4 fw-bold">Caractéristiques</h2>
                <dl class="sm-caracteristiques">
                    <dt>Équipe</dt><dd><?= e($maillot['equipe_nom']) ?></dd>
                    <dt>Saison</dt><dd><?= e($maillot['saison']) ?></dd>
                    <dt>Type</dt><dd><?= e(LIBELLES_TYPE[$maillot['type']]) ?></dd>
                    <dt>Authenticité</dt><dd><?= e(LIBELLES_AUTHENTICITE[$maillot['authenticite']]) ?></dd>
                    <dt>État</dt><dd><?= e(LIBELLES_ETAT[$maillot['etat']]) ?></dd>
                    <dt>Catégorie</dt><dd><?= e($maillot['categorie_nom']) ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($similaires !== []): ?>
            <section class="mt-5" aria-labelledby="titre-similaires">
                <h2 id="titre-similaires" class="h4 fw-bold mb-3">Autres maillots <?= e($maillot['equipe_nom']) ?></h2>
                <div class="sm-grille-maillots sm-grille-maillots--4">
                    <?php foreach ($similaires as $similaire): ?>
                        <?= carte_maillot($similaire) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>

<?php if ($images !== []): ?>
    <!-- Zoom de la photo -->
    <div class="modal fade" id="zoom-photo" tabindex="-1" aria-label="Photo agrandie" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content sm-zoom">
                <button type="button" class="btn-close sm-zoom__fermer" data-bs-dismiss="modal" aria-label="Fermer"></button>
                <!-- La photo affichée dans le carrousel est recopiée ici à l'ouverture (sama.js, 8) -->
                <img src="<?= e(url_image_maillot($images[0]['fichier'])) ?>" alt="<?= e($maillot['titre']) ?>" data-galerie-zoom>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
