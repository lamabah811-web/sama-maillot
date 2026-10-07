<?php
/**
 * SAMA MAILLOT — vitrine de la bannière d'accueil : maillots à l'affiche au-dessus d'un terrain 3D.
 *
 * Inspirée des vitrines « produit unique » : le maillot détouré flotte en grand au-dessus du terrain
 * doré, avec des flèches pour passer au suivant et la miniature du maillot suivant. Chaque maillot
 * mène aux annonces RÉELLES de son équipe dans le catalogue (aucun faux prix, aucun faux produit).
 *
 * Images : public/images/vitrine/<fichier>.webp, détourées (fond transparent). Un maillot dont le
 * fichier n'existe pas n'est pas affiché : pour en ajouter un, déposer l'image et compléter la liste.
 * Défilement automatique et flèches : sama.js (« vitrine de l'accueil ») ; sans JavaScript, le
 * premier maillot reste affiché.
 */

$maillots_vitrine = array_values(array_filter([
    ['fichier' => 'psg.webp', 'equipe' => 'Paris Saint-Germain', 'slug' => 'psg', 'lueur' => 'bleu'],
    ['fichier' => 'real.webp', 'equipe' => 'Real Madrid', 'slug' => 'real-madrid', 'lueur' => 'blanc'],
    ['fichier' => 'barca.webp', 'equipe' => 'FC Barcelone', 'slug' => 'fc-barcelone', 'lueur' => 'grenat'],
], static fn (array $m): bool => is_file(DOSSIER_PUBLIC . '/images/vitrine/' . $m['fichier'])));
$nombre_vitrine = count($maillots_vitrine);
?>
<div class="sm-visuel" data-vitrine>
    <div class="sm-visuel__halo" aria-hidden="true"></div>
    <span class="sm-visuel__projecteur sm-visuel__projecteur--gauche" aria-hidden="true"></span>
    <span class="sm-visuel__projecteur sm-visuel__projecteur--droite" aria-hidden="true"></span>

    <!-- Terrain vu en perspective -->
    <div class="sm-visuel__scene" aria-hidden="true">
        <svg class="sm-visuel__terrain" viewBox="-16 -10 432 280" fill="none" focusable="false">
            <defs>
                <linearGradient id="terrain-pelouse" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#2A2410"/>
                    <stop offset="0.5" stop-color="#16130A"/>
                    <stop offset="1" stop-color="#0B0B0B"/>
                </linearGradient>
                <radialGradient id="terrain-lumiere" cx="0.5" cy="0.5" r="0.6">
                    <stop offset="0" stop-color="#D4AF37" stop-opacity="0.28"/>
                    <stop offset="1" stop-color="#D4AF37" stop-opacity="0"/>
                </radialGradient>
                <clipPath id="terrain-surface"><rect x="0" y="0" width="400" height="260" rx="4"/></clipPath>
            </defs>
            <rect class="sm-visuel__pelouse" x="0" y="0" width="400" height="260" rx="4" fill="url(#terrain-pelouse)"/>
            <g clip-path="url(#terrain-surface)">
                <?php for ($bande = 0; $bande < 10; $bande += 2): ?>
                    <rect class="sm-visuel__bande" x="<?= $bande * 40 ?>" y="0" width="40" height="260"/>
                <?php endfor; ?>
                <rect x="0" y="0" width="400" height="260" fill="url(#terrain-lumiere)"/>
            </g>
            <g class="sm-visuel__lignes">
                <rect pathLength="1" x="10" y="10" width="380" height="240" rx="2"/>
                <line pathLength="1" x1="200" y1="10" x2="200" y2="250"/>
                <circle pathLength="1" cx="200" cy="130" r="38"/>
                <rect pathLength="1" x="10" y="68" width="62" height="124"/>
                <rect pathLength="1" x="10" y="102" width="22" height="56"/>
                <rect pathLength="1" x="328" y="68" width="62" height="124"/>
                <rect pathLength="1" x="368" y="102" width="22" height="56"/>
                <path pathLength="1" d="M72 108 A28 28 0 0 1 72 152"/>
                <path pathLength="1" d="M328 108 A28 28 0 0 0 328 152"/>
                <path pathLength="1" d="M10 18 A8 8 0 0 0 18 10 M382 10 A8 8 0 0 0 390 18 M390 242 A8 8 0 0 0 382 250 M18 250 A8 8 0 0 0 10 242"/>
                <rect pathLength="1" x="-2" y="114" width="12" height="32"/>
                <rect pathLength="1" x="390" y="114" width="12" height="32"/>
            </g>
            <g class="sm-visuel__points">
                <circle cx="200" cy="130" r="2.6"/>
                <circle cx="52" cy="130" r="2.2"/>
                <circle cx="348" cy="130" r="2.2"/>
            </g>
            <circle class="sm-visuel__onde" cx="200" cy="130" r="38"/>
            <circle class="sm-visuel__onde sm-visuel__onde--2" cx="200" cy="130" r="38"/>
            <rect class="sm-visuel__comete" pathLength="100" x="10" y="10" width="380" height="240" rx="2"/>
            <rect class="sm-visuel__comete sm-visuel__comete--2" pathLength="100" x="10" y="10" width="380" height="240" rx="2"/>
        </svg>
    </div>

    <?php if ($nombre_vitrine > 0): ?>
        <!-- Maillots à l'affiche -->
        <section class="sm-vitrine" aria-roledescription="carrousel" aria-label="Maillots à l'affiche">
            <?php foreach ($maillots_vitrine as $rang => $m): ?>
                <figure class="sm-vitrine__maillot sm-vitrine__maillot--<?= e($m['lueur']) ?><?= $rang === 0 ? ' sm-vitrine__maillot--actif' : '' ?>"
                        data-vitrine-maillot aria-roledescription="diapositive" aria-label="<?= e(($rang + 1) . ' sur ' . $nombre_vitrine . ' : ' . $m['equipe']) ?>"
                        <?= $rang === 0 ? '' : 'aria-hidden="true"' ?>>
                    <span class="sm-vitrine__lueur" aria-hidden="true"></span>
                    <img src="<?= e(ressource('images/vitrine/' . $m['fichier'])) ?>" alt="Maillot <?= e($m['equipe']) ?>"
                         width="380" height="440" <?= $rang === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                    <figcaption class="sm-vitrine__legende">
                        <span class="sm-vitrine__equipe"><?= e($m['equipe']) ?></span>
                        <a class="sm-vitrine__lien" href="<?= e(url_catalogue(['equipe' => $m['slug']])) ?>"<?= $rang === 0 ? '' : ' tabindex="-1"' ?>>
                            Voir les maillots <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>
                    </figcaption>
                </figure>
            <?php endforeach; ?>

            <?php if ($nombre_vitrine > 1): ?>
                <div class="sm-vitrine__commandes">
                    <button class="sm-vitrine__fleche" type="button" data-vitrine-precedent aria-label="Maillot précédent">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </button>
                    <span class="sm-vitrine__compteur" aria-hidden="true"><span data-vitrine-numero>1</span> / <?= e($nombre_vitrine) ?></span>
                    <button class="sm-vitrine__fleche" type="button" data-vitrine-suivant aria-label="Maillot suivant">
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
                <!-- Miniature du maillot suivant -->
                <button class="sm-vitrine__suivant" type="button" data-vitrine-suivant aria-label="Voir le maillot suivant">
                    <?php foreach ($maillots_vitrine as $rang => $m): ?>
                        <img src="<?= e(ressource('images/vitrine/' . $m['fichier'])) ?>" alt="" width="76" height="88" loading="lazy"
                             data-vitrine-miniature class="<?= $rang === 1 % $nombre_vitrine ? 'sm-vitrine__miniature--active' : '' ?>">
                    <?php endforeach; ?>
                </button>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php for ($n = 1; $n <= 6; $n++): ?>
        <span class="sm-visuel__particule sm-visuel__particule--<?= $n ?>" aria-hidden="true"></span>
    <?php endfor; ?>
</div>
