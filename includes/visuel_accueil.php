<?php
/**
 * SAMA MAILLOT — vitrine de la bannière d'accueil : maillots à l'affiche qui défilent tout seuls.
 *
 * Le maillot détouré flotte en grand, éclairé par des projecteurs et une lueur de sa couleur ;
 * les maillots se succèdent automatiquement : chacun arrive par le bas à droite, se pose au centre,
 * puis repart vers le haut à gauche (sama.js, « vitrine de l'accueil »). Chaque maillot mène
 * aux annonces RÉELLES de son équipe dans le catalogue (aucun faux prix, aucun faux produit).
 *
 * Images : public/images/vitrine/<fichier>.webp, détourées (fond transparent). Un maillot dont le
 * fichier n'existe pas n'est pas affiché : pour en ajouter un, déposer l'image et compléter la liste.
 * Sans JavaScript, le premier maillot reste affiché.
 */

$maillots_vitrine = array_values(array_filter([
    ['fichier' => 'psg.webp', 'equipe' => 'Paris Saint-Germain', 'slug' => 'psg', 'lueur' => 'bleu'],
    ['fichier' => 'bayern.webp', 'equipe' => 'Bayern Munich', 'slug' => 'bayern-munich', 'lueur' => 'rouge'],
    ['fichier' => 'city.webp', 'equipe' => 'Manchester City', 'slug' => 'manchester-city', 'lueur' => 'ciel'],
    ['fichier' => 'real.webp', 'equipe' => 'Real Madrid', 'slug' => 'real-madrid', 'lueur' => 'blanc'],
    ['fichier' => 'barca.webp', 'equipe' => 'FC Barcelone', 'slug' => 'fc-barcelone', 'lueur' => 'grenat'],
    ['fichier' => 'chelsea.webp', 'equipe' => 'Chelsea', 'slug' => 'chelsea', 'lueur' => 'bleu'],
    ['fichier' => 'espagne.webp', 'equipe' => 'Espagne', 'slug' => 'espagne', 'lueur' => 'creme'],
], static fn (array $m): bool => is_file(DOSSIER_PUBLIC . '/images/vitrine/' . $m['fichier'])));
$nombre_vitrine = count($maillots_vitrine);
?>
<div class="sm-visuel" data-vitrine>
    <div class="sm-visuel__halo" aria-hidden="true"></div>
    <span class="sm-visuel__projecteur sm-visuel__projecteur--gauche" aria-hidden="true"></span>
    <span class="sm-visuel__projecteur sm-visuel__projecteur--droite" aria-hidden="true"></span>
    <span class="sm-visuel__socle" aria-hidden="true"></span>

    <?php if ($nombre_vitrine > 0): ?>
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
                <!-- Indicateur de position (défilement automatique, rien à cliquer) -->
                <div class="sm-vitrine__points" aria-hidden="true">
                    <?php for ($rang = 0; $rang < $nombre_vitrine; $rang++): ?>
                        <span class="<?= $rang === 0 ? 'sm-vitrine__point--actif' : '' ?>" data-vitrine-point></span>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php for ($n = 1; $n <= 6; $n++): ?>
        <span class="sm-visuel__particule sm-visuel__particule--<?= $n ?>" aria-hidden="true"></span>
    <?php endfor; ?>
</div>
