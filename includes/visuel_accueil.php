<?php
/**
 * SAMA MAILLOT — illustration de la bannière d'accueil (décorative, aria-hidden).
 *
 * Terrain de football en 3D (perspective inclinée) : pelouse rayée, lignes dorées lumineuses qui se
 * dessinent à l'ouverture, lumière qui court le long des lignes de touche, ondes depuis le rond central,
 * faisceaux de projecteurs et particules de lumière. Aucune image à télécharger ; animations en CSS
 * (sama.css, « Bannière : illustration »), coupées si le visiteur préfère réduire les animations.
 */
?>
<div class="sm-visuel" aria-hidden="true">
    <div class="sm-visuel__halo"></div>
    <span class="sm-visuel__projecteur sm-visuel__projecteur--gauche"></span>
    <span class="sm-visuel__projecteur sm-visuel__projecteur--droite"></span>

    <div class="sm-visuel__scene">
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

            <!-- Pelouse : fond, bandes de tonte, lumière centrale -->
            <rect class="sm-visuel__pelouse" x="0" y="0" width="400" height="260" rx="4" fill="url(#terrain-pelouse)"/>
            <g clip-path="url(#terrain-surface)">
                <?php for ($bande = 0; $bande < 10; $bande += 2): ?>
                    <rect class="sm-visuel__bande" x="<?= $bande * 40 ?>" y="0" width="40" height="260"/>
                <?php endfor; ?>
                <rect x="0" y="0" width="400" height="260" fill="url(#terrain-lumiere)"/>
            </g>

            <!-- Lignes du terrain (se dessinent à l'ouverture) -->
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

            <!-- Ondes depuis le rond central -->
            <circle class="sm-visuel__onde" cx="200" cy="130" r="38"/>
            <circle class="sm-visuel__onde sm-visuel__onde--2" cx="200" cy="130" r="38"/>

            <!-- Lumière qui court le long des lignes de touche -->
            <rect class="sm-visuel__comete" pathLength="100" x="10" y="10" width="380" height="240" rx="2"/>
            <rect class="sm-visuel__comete sm-visuel__comete--2" pathLength="100" x="10" y="10" width="380" height="240" rx="2"/>
        </svg>
    </div>

    <?php for ($n = 1; $n <= 6; $n++): ?>
        <span class="sm-visuel__particule sm-visuel__particule--<?= $n ?>"></span>
    <?php endfor; ?>
</div>
