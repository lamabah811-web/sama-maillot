<?php
/**
 * SAMA MAILLOT — page boutique publique (§7.4).
 *
 * Adresse lisible : /boutique/dakar-foot-store
 * Nom, ville, date d'inscription, note (« Nouveau vendeur » tant qu'il y a moins de 3 avis),
 * description, bouton « Contacter sur WhatsApp » et maillots de la boutique.
 * Zones de livraison et avis publics des clients (avec la réponse du vendeur).
 */
require __DIR__ . '/../../includes/config.php';

$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$boutique = preg_match('/^[a-z0-9-]{1,80}$/', $slug) === 1 ? boutique_par_slug($slug) : null;

if ($boutique === null) {
    afficher_erreur(404);
}

$filtres = lire_filtres($_GET);
$filtres['categorie'] = '';
$filtres['boutique_id'] = (int) $boutique['id'];
$resultat = rechercher_maillots($filtres);
$note = note_vendeur((int) $boutique['id']);
$zones_livraison = zones_desservies((int) $boutique['id']);
$derniers_avis = avis_de_boutique((int) $boutique['id'], 10);   // avis publics (§20)

$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$inscription = strtotime($boutique['cree_le']);
$depuis = $mois[(int) date('n', $inscription) - 1] . ' ' . date('Y', $inscription);

// « Signaler ce vendeur » : uniquement pour un client ayant commandé chez lui (§21)
$lien_signaler = lien_signaler_vendeur(utilisateur_connecte(), (int) $boutique['id']);

$titre_page = $boutique['nom'];
$url_canonique = url_boutique($boutique['slug']);
$image_partage = !empty($boutique['banniere']) ? url_image_boutique($boutique['banniere'])
    : (!empty($boutique['logo']) ? url_image_boutique($boutique['logo']) : null);
$description_page = 'Les maillots de football de la boutique ' . $boutique['nom'] . ' (' . $boutique['ville'] . ') sur SAMA MAILLOT.';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-catalogue">
    <div class="container">
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb sm-fil-ariane">
                <li class="breadcrumb-item"><a href="<?= e(url()) ?>">Accueil</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url_catalogue()) ?>">Catalogue</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($boutique['nom']) ?></li>
            </ol>
        </nav>

        <?php if (!empty($boutique['banniere'])): ?>
            <img class="sm-banniere-boutique mb-3" src="<?= e(url_image_boutique($boutique['banniere'])) ?>" alt=""
                 width="1600" height="400" fetchpriority="high">
        <?php endif; ?>

        <!-- En-tête de la boutique -->
        <div class="sm-entete-boutique mb-4">
            <span class="sm-entete-boutique__logo" aria-hidden="true">
                <?php if (!empty($boutique['logo'])): ?>
                    <img src="<?= e(url_image_boutique($boutique['logo'])) ?>" alt="" width="400" height="400">
                <?php else: ?>
                    <?= e(mb_strtoupper(mb_substr($boutique['nom'], 0, 2))) ?>
                <?php endif; ?>
            </span>
            <div class="flex-grow-1">
                <h1 class="sm-titre-section mb-1"><?= e($boutique['nom']) ?></h1>
                <p class="mb-0 text-body-secondary d-flex flex-wrap gap-3">
                    <span><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= e($boutique['ville']) ?></span>
                    <span><i class="bi bi-calendar3" aria-hidden="true"></i> Sur SAMA MAILLOT depuis <?= e($depuis) ?></span>
                    <?php if ($note['nombre'] > 0): ?>
                        <a class="sm-badge-nouveau text-decoration-none" href="#avis" title="Voir les avis"><?= e($note['libelle']) ?></a>
                    <?php else: ?>
                        <span class="sm-badge-nouveau"><?= e($note['libelle']) ?></span>
                    <?php endif; ?>
                    <?php if ($lien_signaler !== null): ?>
                        <a class="text-body-secondary small" href="<?= e($lien_signaler) ?>"><i class="bi bi-flag" aria-hidden="true"></i> Signaler ce vendeur</a>
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!empty($boutique['numero_whatsapp'])): ?>
                <a class="btn btn-or" href="<?= e(url('whatsapp.php?boutique=' . rawurlencode($boutique['slug']))) ?>" target="_blank" rel="noopener nofollow">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i> Contacter sur WhatsApp
                </a>
            <?php endif; ?>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-lg-7">
                <?php if (!empty($boutique['description'])): ?>
                    <p class="sm-texte-section mb-0"><?= nl2br(e($boutique['description'])) ?></p>
                <?php endif; ?>
            </div>
            <!-- Zones livrées (§7.4) -->
            <div class="col-12 col-lg-5">
                <?php if ($zones_livraison !== [] || (int) $boutique['retrait_sur_place'] === 1): ?>
                    <div class="sm-encadre-livraison">
                        <h2 class="h6 fw-bold mb-2"><i class="bi bi-truck" aria-hidden="true"></i> Zones de livraison</h2>
                        <ul class="sm-liste-zones">
                            <?php foreach ($zones_livraison as $zone): ?>
                                <li>
                                    <span><?= e($zone['nom']) ?> <span class="text-body-secondary small">· <?= e($zone['delai']) ?></span></span>
                                    <strong><?= (int) $zone['tarif'] === 0 ? 'Gratuit' : e(format_fcfa((int) $zone['tarif'])) ?></strong>
                                </li>
                            <?php endforeach; ?>
                            <?php if ((int) $boutique['retrait_sur_place'] === 1): ?>
                                <li>
                                    <span>Retrait sur place <span class="text-body-secondary small">· <?= e($boutique['adresse_retrait']) ?></span></span>
                                    <strong>Gratuit</strong>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h4 fw-bold mb-0">
                Maillots de la boutique
                <span class="text-body-secondary fw-normal fs-6">(<?= e($resultat['total']) ?>)</span>
            </h2>
            <form method="get" action="<?= e(url_boutique($boutique['slug'])) ?>">
                <label class="visually-hidden" for="tri-boutique">Trier par</label>
                <select class="form-select sm-select-tri" id="tri-boutique" name="tri" data-envoi-auto>
                    <?php foreach (TRIS as $cle => $libelle): ?>
                        <option value="<?= e($cle) ?>"<?= $filtres['tri'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($resultat['maillots'] !== []): ?>
            <div class="sm-grille-maillots sm-grille-maillots--4">
                <?php foreach ($resultat['maillots'] as $maillot): ?>
                    <?= carte_maillot($maillot) ?>
                <?php endforeach; ?>
            </div>
            <div class="mt-4">
                <?= pagination($resultat['page'], $resultat['pages'], static fn (int $p): string =>
                    url_boutique($boutique['slug']) . '?' . http_build_query(array_filter(['tri' => $filtres['tri'] !== 'recent' ? $filtres['tri'] : null, 'page' => $p > 1 ? $p : null]))) ?>
            </div>
        <?php else: ?>
            <div class="sm-etat-vide">
                <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-box-seam"></i></div>
                <h3>Aucun maillot pour le moment</h3>
                <p class="mb-0">Cette boutique n'a pas encore publié de maillot. Revenez bientôt !</p>
            </div>
        <?php endif; ?>

        <!-- Avis des clients (§20) : publics, avec la réponse du vendeur -->
        <section class="mt-5" id="avis" aria-labelledby="titre-avis">
            <h2 class="h4 fw-bold mb-3" id="titre-avis">Avis des clients</h2>
            <?php if ($derniers_avis === []): ?>
                <p class="text-body-secondary">Cette boutique n'a pas encore reçu d'avis. Seuls les clients ayant reçu une commande peuvent noter le vendeur.</p>
            <?php else: ?>
              <div class="row g-4">
                <!-- Résumé : moyenne + répartition des étoiles -->
                <div class="col-12 col-md-4">
                    <div class="sm-resume-notes">
                        <span class="sm-resume-notes__moyenne"><?= e(number_format((float) $note['moyenne'], 1, ',', '')) ?><small>/5</small></span>
                        <?= etoiles((int) round((float) $note['moyenne'])) ?>
                        <span class="small text-body-secondary d-block mt-1"><?= e($note['nombre']) ?> avis vérifié<?= $note['nombre'] > 1 ? 's' : '' ?></span>
                        <ul class="sm-repartition">
                            <?php foreach (repartition_notes((int) $boutique['id']) as $etoile => $nombre): ?>
                                <?php $pourcent = (int) round($nombre * 100 / max(1, $note['nombre'])); ?>
                                <li>
                                    <span><?= e($etoile) ?> <i class="bi bi-star-fill" aria-hidden="true"></i></span>
                                    <span class="sm-repartition__barre" role="img" aria-label="<?= e($nombre) ?> avis à <?= e($etoile) ?> étoiles">
                                        <span class="sm-repartition__remplissage sm-largeur-<?= e(intdiv($pourcent + 4, 5) * 5) ?>"></span>
                                    </span>
                                    <span class="sm-repartition__nombre"><?= e($nombre) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-12 col-md-8">
                <ul class="sm-liste-avis">
                    <?php foreach ($derniers_avis as $avis): ?>
                        <li class="sm-avis">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <?= etoiles((int) $avis['note']) ?>
                                <strong><?= e($avis['prenom'] . ' ' . $avis['initiale'] . '.') ?></strong>
                                <span class="small text-body-secondary">· <?= e(date_relative($avis['cree_le'])) ?></span>
                                <span class="small sm-avis__verifie"><i class="bi bi-patch-check" aria-hidden="true"></i> Achat vérifié</span>
                            </div>
                            <?php if ($avis['commentaire'] !== null): ?>
                                <p class="mb-0 sm-texte-multiligne"><?= e($avis['commentaire']) ?></p>
                            <?php endif; ?>
                            <?php if ($avis['reponse'] !== null): ?>
                                <div class="sm-avis__reponse">
                                    <strong class="small"><i class="bi bi-reply" aria-hidden="true"></i> Réponse du vendeur</strong>
                                    <p class="mb-0 small sm-texte-multiligne"><?= e($avis['reponse']) ?></p>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                </div>
              </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
