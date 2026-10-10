<?php
/**
 * SAMA MAILLOT — fin de chaque page : fermeture de <main>, pied de page (§7.2),
 * barre de navigation basse (mobile) et scripts.
 */

$colonnes_pied = [
    'Découvrir' => ['a-propos' => 'À propos', 'comment-ca-marche' => 'Comment ça marche',
        'devenir-vendeur' => 'Devenir vendeur', 'faq' => 'FAQ', 'contact' => 'Contact'],
    'Informations légales' => ['cgu' => 'CGU', 'cgv' => 'CGV',
        'politique-vendeur' => 'Politique vendeur', 'confidentialite' => 'Confidentialité', 'mentions-legales' => 'Mentions légales'],
];

// Moyens de paiement acceptés : [icône, libellé]
$moyens_paiement = [
    ['bi-phone', 'Wave'],
    ['bi-phone', 'Orange Money'],
    ['bi-cash-coin', 'Paiement à la livraison'],
];

// Entrées de la barre basse mobile : [clé de page, icône, libellé].
$liens_nav_basse = [
    ['accueil', 'bi-house', 'Accueil'],
    ['recherche', 'bi-search', 'Recherche'],
    ['panier', 'bi-bag', 'Panier'],
    ['compte', 'bi-person', 'Compte'],
];
?>
</main>

<footer class="sm-pied">
    <div class="container py-5">
        <div class="row g-4 g-lg-5">
            <div class="col-12 col-lg-4">
                <a class="sm-logo" href="<?= e(url()) ?>" aria-label="SAMA MAILLOT, retour à l'accueil"><img class="sm-logo__image" src="<?= e(ressource('images/logo-sombre.webp')) ?>" alt="" width="180" height="162" loading="lazy"><span class="sm-logo__sama">SAMA</span>&nbsp;<span class="sm-logo__maillot">MAILLOT</span></a>
                <p class="mt-3 mb-3 sm-pied__description">
                    La marketplace sénégalaise des maillots de football : équipes nationales, clubs et rétro,
                    vendus par des boutiques du Sénégal.
                </p>
                <p class="mb-0 d-flex flex-wrap gap-3 small">
                    <span><i class="bi bi-currency-exchange" aria-hidden="true"></i> Prix en FCFA</span>
                    <span><i class="bi bi-truck" aria-hidden="true"></i> Livraison au Sénégal</span>
                </p>
            </div>

            <?php foreach ($colonnes_pied as $titre_colonne => $liens): ?>
                <nav class="col-6 col-lg-2" aria-label="<?= e($titre_colonne) ?>">
                    <h2><?= e($titre_colonne) ?></h2>
                    <ul>
                        <?php foreach ($liens as $cle => $libelle): ?>
                            <li><a href="<?= e(url_page($cle)) ?>"><?= e($libelle) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endforeach; ?>

            <div class="col-12 col-lg-4">
                <h2 id="titre-paiements">Moyens de paiement</h2>
                <ul class="d-flex flex-wrap gap-2" aria-labelledby="titre-paiements">
                    <?php foreach ($moyens_paiement as [$icone_paiement, $moyen]): ?>
                        <li><span class="sm-paiement"><i class="bi <?= e($icone_paiement) ?>" aria-hidden="true"></i> <?= e($moyen) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <p class="mt-3 mb-0 small">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    En paiement en ligne, votre argent est protégé jusqu'à la livraison.
                </p>
            </div>
        </div>
    </div>

    <div class="sm-pied__bas">
        <div class="container py-3 d-flex flex-wrap justify-content-between gap-2">
            <span>© <?= e(date('Y')) ?> SAMA MAILLOT. Tous droits réservés.</span>
            <span>Fait au Sénégal, pour les supporters.</span>
        </div>
    </div>
</footer>

<!-- Barre de navigation basse : mobile et tablette uniquement (§7.2) -->
<nav class="sm-nav-basse d-lg-none" aria-label="Navigation rapide">
    <ul>
        <?php foreach ($liens_nav_basse as [$cle, $icone, $libelle]): ?>
            <li>
                <?php
                $href_nav = match ($cle) {
                    'accueil' => url(),
                    'compte' => $url_espace ?? url_page('compte'),   // espace du compte, ou connexion
                    default => url_page($cle),
                };
                ?>
                <a href="<?= e($href_nav) ?>"<?= attribut_page_active($cle) ?>
                    <?= $cle === 'recherche' ? ' data-aller-recherche' : '' ?>>
                    <i class="bi <?= e($icone) ?>" aria-hidden="true"></i>
                    <span><?= e($libelle) ?></span>
                    <?php if ($cle === 'panier'): ?>
                        <?php if (($nombre_articles_panier ?? 0) > 0): ?>
                            <span class="sm-pastille" aria-hidden="true"><?= e($nombre_articles_panier) ?></span>
                        <?php endif; ?>
                        <span class="visually-hidden">(<?= e($libelle_panier ?? '0 article') ?>)</span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<!-- Clair / sombre : bouton rond flottant en bas à droite (sama.js, 1) ; aria-pressed="true" en mode sombre -->
<button class="sm-theme-flottant" type="button" data-bascule-theme aria-label="Mode sombre" aria-pressed="false" title="Passer en mode sombre">
    <i class="bi bi-moon-fill sm-icone-lune" aria-hidden="true"></i>
    <i class="bi bi-sun-fill sm-icone-soleil" aria-hidden="true"></i>
</button>

<!-- Retour en haut : apparaît après avoir fait défiler la page (sama.js, 10), au-dessus du bouton thème -->
<button class="sm-haut-de-page" type="button" data-haut-de-page hidden aria-label="Revenir en haut de la page" title="Revenir en haut">
    <i class="bi bi-arrow-up" aria-hidden="true"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous" defer></script>
<script src="<?= e(ressource('js/sama.js')) ?>" defer></script>
</body>
</html>
