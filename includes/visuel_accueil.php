<?php
/**
 * SAMA MAILLOT — illustration de la bannière d'accueil (décorative, aria-hidden).
 *
 * Ballon de football en SVG (motifs calculés ci-dessous) qui flotte et tourne, terrain en
 * perspective, orbites et étincelles dorées, étiquettes flottantes rappelant de VRAIES fonctions
 * du site (aucun faux chiffre, §29.1). Aucune image à télécharger ; animations en CSS (sama.css,
 * « Bannière : illustration »), coupées si le visiteur préfère réduire les animations.
 */

/** Points d'un pentagone régulier : centre ($cx, $cy), rayon $r, premier sommet à l'angle $angle (degrés). */
$pentagone = static function (float $cx, float $cy, float $r, float $angle): array {
    $points = [];
    for ($k = 0; $k < 5; $k++) {
        $a = deg2rad($angle + $k * 72);
        $points[] = [round($cx + $r * cos($a), 2), round($cy + $r * sin($a), 2)];
    }

    return $points;
};
$trace = static fn (array $points): string => implode(' ', array_map(static fn (array $p): string => $p[0] . ',' . $p[1], $points));

// Pentagone central, 5 pentagones autour (pointe vers le centre), 5 au bord (coupés par le cercle)
$central = $pentagone(0, 0, 25, -90);
$autour = [];
$bord = [];
$coutures = [];
for ($k = 0; $k < 5; $k++) {
    $angle = -90 + $k * 72;
    $a = deg2rad($angle);
    $autour[$k] = $pentagone(64 * cos($a), 64 * sin($a), 24, $angle + 180);
    $b = deg2rad($angle + 36);
    $bord[$k] = $pentagone(108 * cos($b), 108 * sin($b), 26, $angle + 36 + 180);
    // Couture : sommet du pentagone central → sommet intérieur du pentagone voisin
    $coutures[] = [$central[$k], $autour[$k][0]];
}
for ($k = 0; $k < 5; $k++) {
    // Coutures entre pentagones voisins (contour des hexagones)
    $suivant = ($k + 1) % 5;
    $coutures[] = [$autour[$k][1], $autour[$suivant][4]];
    $coutures[] = [$autour[$k][2], $bord[$k][1]];
    $coutures[] = [$autour[$suivant][3], $bord[$k][4]];
}
?>
<div class="sm-visuel" aria-hidden="true">
    <div class="sm-visuel__halo"></div>

    <!-- Terrain vu en perspective -->
    <div class="sm-visuel__terrain">
        <svg viewBox="0 0 400 260" fill="none" focusable="false">
            <g class="sm-visuel__lignes">
                <rect x="10" y="10" width="380" height="240" rx="6"/>
                <line x1="200" y1="10" x2="200" y2="250"/>
                <circle cx="200" cy="130" r="42"/>
                <circle cx="200" cy="130" r="3" class="sm-visuel__point"/>
                <rect x="10" y="70" width="58" height="120"/>
                <rect x="10" y="100" width="22" height="60"/>
                <rect x="332" y="70" width="58" height="120"/>
                <rect x="368" y="100" width="22" height="60"/>
                <path d="M68 105 A30 30 0 0 1 68 155"/>
                <path d="M332 105 A30 30 0 0 0 332 155"/>
            </g>
        </svg>
    </div>

    <!-- Orbites -->
    <svg class="sm-visuel__orbites" viewBox="-200 -200 400 400" fill="none" focusable="false">
        <ellipse class="sm-visuel__orbite sm-visuel__orbite--1" cx="0" cy="0" rx="178" ry="62"/>
        <ellipse class="sm-visuel__orbite sm-visuel__orbite--2" cx="0" cy="0" rx="150" ry="150"/>
    </svg>

    <!-- Ombre portée puis ballon -->
    <div class="sm-visuel__ombre"></div>
    <div class="sm-visuel__ballon">
        <svg viewBox="-100 -100 200 200" focusable="false">
            <defs>
                <radialGradient id="ballon-matiere" cx="-0.25" cy="-0.35" r="1.15" gradientUnits="objectBoundingBox" fx="0.32" fy="0.28">
                    <stop offset="0" stop-color="#FFFFFF"/>
                    <stop offset="0.55" stop-color="#ECE8DD"/>
                    <stop offset="1" stop-color="#9C968A"/>
                </radialGradient>
                <radialGradient id="ballon-ombre" cx="0.5" cy="0.5" r="0.5">
                    <stop offset="0.62" stop-color="#000000" stop-opacity="0"/>
                    <stop offset="1" stop-color="#000000" stop-opacity="0.45"/>
                </radialGradient>
                <radialGradient id="ballon-reflet" cx="0.3" cy="0.25" r="0.35">
                    <stop offset="0" stop-color="#FFFFFF" stop-opacity="0.85"/>
                    <stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/>
                </radialGradient>
                <clipPath id="ballon-forme"><circle cx="0" cy="0" r="88"/></clipPath>
            </defs>
            <circle cx="0" cy="0" r="88" fill="url(#ballon-matiere)"/>
            <g clip-path="url(#ballon-forme)">
                <g class="sm-visuel__motifs">
                    <g class="sm-visuel__coutures">
                        <?php foreach ($coutures as [$de, $vers]): ?>
                            <line x1="<?= e($de[0]) ?>" y1="<?= e($de[1]) ?>" x2="<?= e($vers[0]) ?>" y2="<?= e($vers[1]) ?>"/>
                        <?php endforeach; ?>
                    </g>
                    <g class="sm-visuel__pieces">
                        <polygon points="<?= e($trace($central)) ?>"/>
                        <?php foreach (array_merge($autour, $bord) as $piece): ?>
                            <polygon points="<?= e($trace($piece)) ?>"/>
                        <?php endforeach; ?>
                    </g>
                </g>
                <circle cx="0" cy="0" r="88" fill="url(#ballon-ombre)"/>
                <circle cx="0" cy="0" r="88" fill="url(#ballon-reflet)"/>
            </g>
            <circle class="sm-visuel__contour" cx="0" cy="0" r="88" fill="none"/>
        </svg>
    </div>

    <!-- Étincelles -->
    <?php for ($n = 1; $n <= 5; $n++): ?>
        <span class="sm-visuel__etincelle sm-visuel__etincelle--<?= $n ?>"></span>
    <?php endfor; ?>

    <!-- Étiquettes : vraies fonctions du site -->
    <span class="sm-visuel__etiquette sm-visuel__etiquette--1"><i class="bi bi-shield-check"></i> Paiement protégé</span>
    <span class="sm-visuel__etiquette sm-visuel__etiquette--2"><i class="bi bi-whatsapp"></i> Commande WhatsApp</span>
    <span class="sm-visuel__etiquette sm-visuel__etiquette--3"><i class="bi bi-star-fill"></i> Avis vérifiés</span>
</div>
