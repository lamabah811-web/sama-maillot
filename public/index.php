<?php
/**
 * SAMA MAILLOT — page d'accueil (§7.1).
 *
 * Aucun faux chiffre, faux avis, faux compteur ni faux produit : les sections
 * « Derniers maillots » et « Équipes populaires » sont alimentées par la base
 * (maillots réellement publiés) et restent vides tant qu'il n'y en a pas.
 */
require __DIR__ . '/../includes/config.php';

$page_active = 'accueil';

// Maillots réellement publiés (les 24 plus récents) et équipes qui en ont le plus.
// Une ligne de carrousel par tranche de 8 : quand il y en a plus, les suivants passent à la ligne.
$derniers_maillots = derniers_maillots(24);
$lignes_maillots = array_chunk($derniers_maillots, 8);
$equipes_populaires = equipes_populaires(8);

// Photo de la bannière : public/images/banniere-maillot.webp (ou .png / .jpg).
// Affichée uniquement si le fichier existe ; ses dimensions réelles évitent les sauts de mise en page.
$image_banniere = null;
foreach (['webp', 'png', 'jpg', 'jpeg'] as $extension) {
    $chemin = DOSSIER_PUBLIC . '/images/banniere-maillot.' . $extension;
    if (is_file($chemin)) {
        $taille = @getimagesize($chemin) ?: [800, 800];
        $image_banniere = ['fichier' => 'banniere-maillot.' . $extension, 'largeur' => $taille[0], 'hauteur' => $taille[1]];
        break;
    }
}

// Atouts vendeur (§9, §17, §15) — fonctionnalités prévues au cahier des charges
$atouts_vendeur = [
    'Votre boutique en ligne en quelques minutes',
    'Stock géré taille par taille',
    'Commandes WhatsApp suivies sur le site',
    'Versements sur votre numéro Wave',
];

require __DIR__ . '/../includes/entete.php';
?>

<!-- =====================================================================
     Bannière : noire et or dans les deux thèmes, compacte
     ===================================================================== -->
<section class="sm-banniere" aria-labelledby="titre-accueil">
    <div class="container sm-banniere__contenu">
        <div class="row align-items-center g-5">
            <div class="col-12 col-lg-7">
                <h1 id="titre-accueil">
                    Le maillot de ton équipe,<br>
                    <span class="sm-texte-or">en toute confiance.</span>
                </h1>

                <p class="lead mb-4">
                    Trouvez votre maillot parmi les boutiques du Sénégal. <?= paiement_en_ligne_actif()
                        ? 'Payez en ligne de façon sécurisée, à la livraison, ou commandez directement via WhatsApp.'
                        : 'Payez à la livraison, maillot en main, ou commandez directement via WhatsApp.' ?>
                </p>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a class="btn btn-or btn-lg" href="<?= e(url_catalogue()) ?>">
                        <i class="bi bi-grid-fill" aria-hidden="true"></i> Explorer le catalogue
                    </a>
                    <a class="btn btn-clair btn-lg" href="<?= e(url_page('inscription-vendeur')) ?>">
                        <i class="bi bi-shop" aria-hidden="true"></i> Devenir vendeur
                    </a>
                </div>

                <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0" aria-label="Accès rapide aux catégories">
                    <?php foreach (CATEGORIES as $cle_categorie => $categorie): ?>
                        <li>
                            <a class="sm-puce-claire" href="<?= e(url_catalogue(['categorie' => $cle_categorie])) ?>">
                                <i class="bi <?= e($categorie['icone']) ?>" aria-hidden="true"></i> <?= e($categorie['nom']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <ul class="sm-garanties">
                    <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Prix en FCFA</li>
                    <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Authenticité affichée</li>
                    <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Vendeurs notés par les clients</li>
                </ul>
            </div>

            <?php if ($image_banniere !== null): ?>
                <!-- Photo de la bannière (public/images/banniere-maillot.*), affichée si le fichier existe -->
                <div class="col-12 col-lg-5 text-center">
                    <img class="sm-banniere__photo" src="<?= e(ressource('images/' . $image_banniere['fichier'])) ?>"
                         alt="Maillot de football" width="<?= e($image_banniere['largeur']) ?>"
                         height="<?= e($image_banniere['hauteur']) ?>" fetchpriority="high" decoding="async">
                </div>
            <?php else: ?>
                <!-- Sans photo : illustration animée (ballon, terrain, orbites), masquée sur mobile -->
                <div class="col-lg-5 d-none d-lg-block">
                    <?php require __DIR__ . '/../includes/visuel_accueil.php'; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- =====================================================================
     Équipes populaires : données réelles uniquement (§7.1)
     ===================================================================== -->
<?php if ($equipes_populaires !== []): ?>
    <section class="pt-5 pb-5" aria-labelledby="titre-equipes">
        <div class="container">
            <span class="sm-sur-titre">Équipes populaires</span>
            <h2 id="titre-equipes" class="sm-titre-section mb-4">Les plus recherchées</h2>
            <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0">
                <?php foreach ($equipes_populaires as $equipe): ?>
                    <li>
                        <a class="sm-puce-equipe" href="<?= e(url_catalogue(['equipe' => $equipe['slug']])) ?>">
                            <?= e($equipe['nom']) ?>
                            <span class="sm-puce-equipe__nombre"><?= e($equipe['nombre']) ?></span>
                            <span class="visually-hidden">maillot<?= (int) $equipe['nombre'] > 1 ? 's' : '' ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<!-- =====================================================================
     Derniers maillots : vraies annonces, état vide tant qu'aucun vendeur n'a publié
     ===================================================================== -->
<section class="pb-5<?= $equipes_populaires === [] ? ' pt-5' : '' ?>" aria-labelledby="titre-derniers">
    <div class="container">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
            <div>
                <span class="sm-sur-titre">Nouveautés</span>
                <h2 id="titre-derniers" class="sm-titre-section mb-0">Derniers maillots</h2>
            </div>
            <?php if ($derniers_maillots !== []): ?>
                <a class="btn btn-contour" href="<?= e(url_catalogue()) ?>">
                    Voir tout <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($derniers_maillots !== []): ?>
            <!-- Une ligne de carrousel par tranche de 8 maillots : flèches sur ordinateur,
                 glissement au doigt sur mobile (sama.js, 13) -->
            <?php foreach ($lignes_maillots as $rang => $ligne_maillots): ?>
                <div class="sm-carrousel-cartes<?= $rang > 0 ? ' mt-4' : '' ?>" data-carrousel-cartes>
                    <button class="sm-carrousel-cartes__fleche sm-carrousel-cartes__fleche--precedente" type="button"
                            data-defiler="-1" aria-label="Maillots précédents" disabled>
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </button>
                    <div class="sm-carrousel-cartes__piste" tabindex="0" role="region"
                         aria-label="Derniers maillots<?= count($lignes_maillots) > 1 ? ', ligne ' . ($rang + 1) : '' ?>, faites défiler">
                        <?php foreach ($ligne_maillots as $maillot): ?>
                            <?= carte_maillot($maillot) ?>
                        <?php endforeach; ?>
                    </div>
                    <button class="sm-carrousel-cartes__fleche sm-carrousel-cartes__fleche--suivante" type="button"
                            data-defiler="1" aria-label="Maillots suivants">
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sm-etat-vide">
                <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-hourglass-split"></i></div>
                <h3>Les premiers maillots arrivent bientôt</h3>
                <p class="mb-4">
                    Les boutiques sont en train de s'installer. Revenez très vite pour découvrir
                    les maillots des équipes nationales, des clubs et les modèles rétro.
                </p>
                <a class="btn btn-or" href="<?= e(url_page('inscription-vendeur')) ?>">
                    <i class="bi bi-shop" aria-hidden="true"></i> Vous vendez des maillots ? Ouvrez votre boutique
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- =====================================================================
     Comment ça marche : 3 étapes
     ===================================================================== -->
<section class="sm-section border-top" aria-labelledby="titre-fonctionnement">
    <div class="container">
        <div class="text-center mx-auto mb-5 sm-largeur-texte">
            <span class="sm-sur-titre">Comment ça marche</span>
            <h2 id="titre-fonctionnement" class="sm-titre-section">Achetez sans crainte</h2>
            <p class="sm-texte-section mx-auto mb-0">
                Trois garanties pour acheter un maillot en ligne au Sénégal, sans mauvaise surprise.
            </p>
        </div>

        <div class="sm-etapes">
            <article class="sm-bloc">
                <span class="sm-bloc__numero" aria-hidden="true">01</span>
                <?php if (paiement_en_ligne_actif()): ?>
                    <h3><i class="bi bi-shield-check" aria-hidden="true"></i> Paiement sécurisé</h3>
                    <p>
                        En paiement en ligne (Wave, Orange Money…), votre argent est conservé par SAMA MAILLOT
                        et n'est versé au vendeur qu'après la livraison. Maillot non reçu ? Vous êtes remboursé.
                    </p>
                <?php else: ?>
                    <h3><i class="bi bi-patch-check" aria-hidden="true"></i> Boutiques identifiées</h3>
                    <p>
                        Chaque boutique est identifiée et indique sur chaque annonce si le maillot est officiel,
                        une réplique ou une version fan. Les avis des clients sont publics.
                    </p>
                <?php endif; ?>
            </article>
            <article class="sm-bloc">
                <span class="sm-bloc__numero" aria-hidden="true">02</span>
                <h3><i class="bi bi-cash-coin" aria-hidden="true"></i> Paiement à la livraison</h3>
                <p>
                    <?= paiement_en_ligne_actif() ? 'Vous préférez payer en main propre ? Réglez' : 'Rien à payer avant : réglez' ?>
                    le vendeur en espèces ou par Wave au moment où vous recevez votre maillot.
                </p>
            </article>
            <article class="sm-bloc">
                <span class="sm-bloc__numero" aria-hidden="true">03</span>
                <h3><i class="bi bi-telephone" aria-hidden="true"></i> Livraison avec le vendeur</h3>
                <p>
                    Le vendeur vous appelle pour convenir de la livraison. Suivez votre commande sur le site
                    et confirmez la réception une fois le maillot entre vos mains.
                </p>
            </article>
        </div>
    </div>
</section>

<!-- =====================================================================
     Devenir vendeur
     ===================================================================== -->
<section class="pb-5" aria-labelledby="titre-vendeur">
    <div class="container pb-lg-4">
        <div class="sm-bandeau-vendeur">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-12 col-lg-7">
                    <span class="sm-sur-titre">Espace vendeur</span>
                    <h2 id="titre-vendeur" class="sm-titre-section">Vous vendez des maillots ?<br>Ouvrez votre boutique.</h2>
                    <p class="mb-0">
                        Publiez vos maillots, gérez votre stock et suivez vos commandes au même endroit,
                        avec des clients qui achètent en confiance.
                    </p>
                    <ul class="sm-liste-atouts">
                        <?php foreach ($atouts_vendeur as $atout): ?>
                            <li><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= e($atout) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="sm-carte-tarif">
                        <span class="sm-carte-tarif__essai sm-texte-or"><?= e(ESSAI_GRATUIT_JOURS) ?> jours</span>
                        <span class="sm-carte-tarif__sous-titre">d'essai gratuit, sans engagement</span>
                        <a class="btn btn-or btn-lg w-100" href="<?= e(url_page('inscription-vendeur')) ?>">
                            Ouvrir ma boutique <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>
                        <span class="sm-carte-tarif__prix mt-3">
                            Ensuite <strong><?= e(format_fcfa(PRIX_ABONNEMENT_FCFA)) ?></strong> par mois
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/pied_de_page.php'; ?>
