<?php
/**
 * SAMA MAILLOT — début de chaque page : <head>, en-tête du site, ouverture de <main>.
 *
 * Variables facultatives à définir AVANT d'inclure ce fichier :
 *   $titre_page        titre de la page (sans le nom du site)
 *   $description_page  meta description propre à la page
 *   $sans_indexation   true pour une page à ne pas indexer (bientôt disponible, erreurs…)
 *   $page_active       clé de la page pour surligner le menu : 'accueil', 'panier', 'compte'…
 *
 * Ordre important dans le <head> :
 *   1. <meta name="theme-color"> ;
 *   2. le script inline du thème, AVANT les feuilles de style : la page s'affiche
 *      directement dans le bon thème, sans flash blanc ;
 *   3. Bootstrap, ses icônes, les polices, puis notre sama.css qui surcharge Bootstrap.
 */

$titre_complet = !empty($titre_page)
    ? $titre_page . ' — ' . NOM_SITE
    : NOM_SITE . ' — La marketplace sénégalaise des maillots de football';

$description_page ??= 'Achetez et vendez des maillots de football au Sénégal : équipes nationales, clubs et rétro. '
    . 'Paiement sécurisé, paiement à la livraison ou commande via WhatsApp. Prix en FCFA.';

// Utilisateur connecté (null pour un visiteur) et adresse de son espace.
$utilisateur_entete = utilisateur_connecte();
$url_espace = $utilisateur_entete !== null ? url(chemin_espace($utilisateur_entete)) : url_page('connexion');

// En-tête du menu du compte : initiales et libellé du rôle (lu dans la table roles).
$initiales_entete = $utilisateur_entete !== null
    ? mb_strtoupper(mb_substr($utilisateur_entete['prenom'], 0, 1) . mb_substr($utilisateur_entete['nom'], 0, 1))
    : '';
$role_entete = (string) ($utilisateur_entete['role_libelle'] ?? '');

// Entrées du menu du compte selon le rôle : [adresse, icône, libellé, clé de page active].
$menu_compte = match ($utilisateur_entete['role'] ?? null) {
    // Un vendeur est aussi un acheteur (§5.1) : il garde commandes, panier et favoris.
    'vendeur' => [
        [url_page('compte'), 'bi-person-fill', 'Mon compte', 'compte'],
        [url_page('vendeur'), 'bi-shop', 'Espace vendeur', 'vendeur'],
        [url_page('mes-commandes'), 'bi-box-seam-fill', 'Mes commandes', 'mes-commandes'],
        [url_page('panier'), 'bi-cart-fill', 'Mon panier', 'panier'],
        [url_page('mes-favoris'), 'bi-heart-fill', 'Mes favoris', 'mes-favoris'],
    ],
    'admin' => [
        [url_page('compte'), 'bi-person-fill', 'Mon compte', 'compte'],
        [url_page('admin'), 'bi-shield-fill', 'Administration', 'admin'],
    ],
    'client' => [
        [url_page('compte'), 'bi-person-fill', 'Mon compte', 'compte'],
        [url_page('mes-commandes'), 'bi-box-seam-fill', 'Mes commandes', 'mes-commandes'],
        [url_page('panier'), 'bi-cart-fill', 'Mon panier', 'panier'],
        [url_page('devenir-vendeur'), 'bi-shop', 'Devenir vendeur', 'devenir-vendeur'],
    ],
    default => [],
};

// Nombre réel d'articles du panier (invité ou compte) — badge de l'en-tête et de la barre basse.
$nombre_articles_panier = nombre_articles_panier();

// Notifications non lues (pastille de la cloche, §22.1)
$notifications_non_lues = $utilisateur_entete !== null ? nombre_notifications_non_lues((int) $utilisateur_entete['id']) : 0;
$libelle_panier = $nombre_articles_panier . ($nombre_articles_panier > 1 ? ' articles' : ' article');
?>
<?php require __DIR__ . '/tete_html.php'; ?>
<body>
<a class="sm-evitement" href="#contenu">Aller au contenu</a>

<header class="sm-entete sticky-top">
    <!--
        Barre de navigation :
        - ordinateur (≥ 1200 px) : logo | Accueil, Catalogue, À propos, Contact |
          notifications + menu du compte (connecté) | loupe | panier | Connexion (visiteur) ;
        - mobile et tablette : burger | logo | loupe | panier (+ Connexion dès 992 px ; sinon « Compte » en barre basse).
        Pas de bouton « Inscription » : la page de connexion propose « Créer un compte ».
        Le choix clair / sombre est un bouton rond flottant en bas à droite (pied_de_page.php).
        La loupe ouvre la barre de recherche juste sous l'en-tête (et la referme).
    -->
    <nav class="navbar navbar-expand-xl py-2" aria-label="Navigation principale">
        <div class="container gap-1 gap-sm-2">

            <!-- Bouton burger (mobile et tablette) -->
            <button class="navbar-toggler sm-burger" type="button"
                    data-bs-toggle="collapse" data-bs-target="#menu-principal"
                    aria-controls="menu-principal" aria-expanded="false" aria-label="Ouvrir le menu">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>

            <!-- Logo : monogramme SM + « SAMA » noir (blanc en sombre) + « MAILLOT » or -->
            <a class="sm-logo mx-auto mx-xl-0" href="<?= e(url()) ?>" aria-label="SAMA MAILLOT, retour à l'accueil">
                <img class="sm-logo__image sm-logo__image--clair" src="<?= e(ressource('images/logo-clair.webp')) ?>" alt="" width="180" height="162">
                <img class="sm-logo__image sm-logo__image--sombre" src="<?= e(ressource('images/logo-sombre.webp')) ?>" alt="" width="180" height="162">
                <span class="sm-logo__sama">SAMA</span>&nbsp;<span class="sm-logo__maillot">MAILLOT</span>
            </a>

            <!-- Toujours visibles : recherche, panier, puis Connexion pour un visiteur (thème : bouton flottant, pied de page) -->
            <div class="d-flex align-items-center gap-1 gap-sm-2 order-xl-last">
                <!-- Loupe : ouvre / referme la barre de recherche sous l'en-tête -->
                <button type="button" class="sm-bouton-carre sm-bouton-carre--or sm-bouton-recherche" data-bs-toggle="collapse"
                        data-bs-target="#volet-recherche" aria-controls="volet-recherche" aria-expanded="false"
                        aria-label="Rechercher un maillot" title="Rechercher">
                    <i class="bi bi-search sm-icone-loupe" aria-hidden="true"></i>
                    <i class="bi bi-x-lg sm-icone-fermer" aria-hidden="true"></i>
                </button>

                <a class="sm-bouton-carre sm-bouton-carre--or" href="<?= e(url_page('panier')) ?>"
                   aria-label="Panier : <?= e($libelle_panier) ?>" title="Panier"<?= attribut_page_active('panier') ?>>
                    <i class="bi bi-cart-fill" aria-hidden="true"></i>
                    <?php if ($nombre_articles_panier > 0): ?>
                        <span class="sm-pastille" aria-hidden="true"><?= e($nombre_articles_panier) ?></span>
                    <?php endif; ?>
                </a>

                <?php if ($utilisateur_entete === null): ?>
                    <!-- Connexion : tout à droite (tablette en largeur et ordinateur) ; sur téléphone, « Compte » de la barre basse -->
                    <a class="btn btn-or sm-bouton-connexion d-none d-lg-inline-flex" href="<?= e(url_page('connexion')) ?>"<?= attribut_page_active('connexion') ?>>
                        <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Connexion
                    </a>
                <?php endif; ?>
            </div>

            <!-- Menu repliable -->
            <div class="collapse navbar-collapse" id="menu-principal">
                <ul class="navbar-nav ms-xl-4 me-xl-3">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url()) ?>"<?= attribut_page_active('accueil') ?>>
                            <i class="bi bi-house d-xl-none" aria-hidden="true"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <button class="nav-link dropdown-toggle" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-grid d-xl-none" aria-hidden="true"></i> Catalogue
                        </button>
                        <!-- Menu compact : les 3 catégories, puis l'accès au catalogue complet -->
                        <div class="dropdown-menu sm-mega">
                            <div class="sm-mega__grille">
                                <?php foreach (CATEGORIES as $cle_menu_categorie => $categorie_menu): ?>
                                    <a class="sm-mega__carte" href="<?= e(url_catalogue(['categorie' => $cle_menu_categorie])) ?>">
                                        <span class="sm-mega__icone" aria-hidden="true"><i class="bi <?= e($categorie_menu['icone']) ?>"></i></span>
                                        <span>
                                            <span class="sm-mega__titre"><?= e($categorie_menu['nom']) ?></span>
                                            <span class="sm-mega__texte"><?= e($categorie_menu['texte']) ?></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="sm-mega__pied">
                                <a href="<?= e(url_catalogue()) ?>">Tout le catalogue <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url_page('a-propos')) ?>"<?= attribut_page_active('a-propos') ?>>
                            <i class="bi bi-info-circle d-xl-none" aria-hidden="true"></i> À propos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url_page('contact')) ?>"<?= attribut_page_active('contact') ?>>
                            <i class="bi bi-chat-dots d-xl-none" aria-hidden="true"></i> Contact
                        </a>
                    </li>
                </ul>

                <!-- Compte (utilisateur connecté) : notifications + menu du compte.
                     Visiteur : bouton « Connexion » à droite de l'en-tête ; l'inscription se fait depuis la page de connexion. -->
                <?php if ($utilisateur_entete !== null): ?>
                    <div class="sm-actions-compte d-flex flex-column flex-xl-row gap-2 mt-2 mt-xl-0">
                        <div class="d-flex gap-2">
                            <!-- Notifications dans le site (§22.1) : pastille = nombre réel de non lues -->
                            <a class="sm-bouton-carre sm-bouton-carre--or flex-shrink-0" href="<?= e(url_page('notifications')) ?>"
                               aria-label="Notifications<?= $notifications_non_lues > 0 ? ' : ' . e($notifications_non_lues) . ' non lue' . ($notifications_non_lues > 1 ? 's' : '') : '' ?>"
                               title="Notifications"<?= attribut_page_active('notifications') ?>>
                                <i class="bi bi-bell-fill" aria-hidden="true"></i>
                                <?php if ($notifications_non_lues > 0): ?>
                                    <span class="sm-pastille" aria-hidden="true"><?= e($notifications_non_lues > 99 ? '99+' : $notifications_non_lues) ?></span>
                                <?php endif; ?>
                            </a>

                            <!-- Menu du compte : prénom + entrées selon le rôle -->
                            <div class="dropdown flex-grow-1">
                                <button class="btn btn-or dropdown-toggle sm-bouton-compte w-100" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="Menu du compte de <?= e($utilisateur_entete['prenom']) ?>">
                                    <?= e($utilisateur_entete['prenom']) ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end sm-menu-compte">
                                    <li class="sm-menu-compte__entete" aria-hidden="true">
                                        <span class="sm-avatar"><?= e($initiales_entete) ?></span>
                                        <span>
                                            <span class="sm-menu-compte__nom"><?= e($utilisateur_entete['prenom'] . ' ' . $utilisateur_entete['nom']) ?></span>
                                            <span class="sm-menu-compte__role"><?= e($role_entete) ?></span>
                                        </span>
                                    </li>
                                    <?php foreach ($menu_compte as [$href_menu, $icone_menu, $libelle_menu, $cle_menu]): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= e($href_menu) ?>"<?= attribut_page_active($cle_menu) ?>>
                                                <i class="bi <?= e($icone_menu) ?>" aria-hidden="true"></i> <?= e($libelle_menu) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" action="<?= e(url_page('deconnexion')) ?>">
                                            <?= champ_csrf() ?>
                                            <button class="dropdown-item sm-menu-compte__deconnexion" type="submit">
                                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Se déconnecter
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Barre de recherche (§10.2) : s'ouvre sous l'en-tête avec la loupe -->
    <div class="collapse sm-volet-recherche" id="volet-recherche">
        <div class="container pb-3 pt-1">
            <form class="sm-recherche-barre" role="search" action="<?= e(url_page('recherche')) ?>" method="get">
                <?php if (!page_existe('recherche')): ?>
                    <input type="hidden" name="page" value="recherche">
                <?php endif; ?>
                <div class="input-group">
                    <label class="visually-hidden" for="champ-recherche">Rechercher un maillot</label>
                    <input class="form-control" type="search" id="champ-recherche" name="q"
                           placeholder="Rechercher un maillot…" maxlength="100" autocomplete="off" enterkeyhint="search">
                    <button class="btn btn-or sm-recherche-barre__bouton" type="submit" aria-label="Rechercher">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</header>

<main id="contenu" tabindex="-1">
    <?php require __DIR__ . '/messages_flash.php'; ?>
