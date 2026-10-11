<?php
/**
 * SAMA MAILLOT — menu du téléphone et de la tablette (< 1200 px) : panneau qui glisse depuis la gauche.
 *
 * Ouvert par le burger de l'en-tête (offcanvas Bootstrap). Placé HORS de l'en-tête : son flou
 * (backdrop-filter) enfermerait un élément « fixed » dans sa hauteur.
 * Contenu : liens principaux, « Catalogue » dépliable (3 catégories), puis le compte
 * (connecté : entrées selon le rôle, notifications, déconnexion ; visiteur : connexion).
 * Attend les variables préparées par entete.php.
 */
?>
<div class="offcanvas offcanvas-start sm-menu-mobile d-xl-none" tabindex="-1" id="menu-mobile" aria-labelledby="titre-menu-mobile">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title sm-menu-mobile__titre" id="titre-menu-mobile"><span class="sm-menu-mobile__titre-1">Me</span><span class="sm-menu-mobile__titre-2">nu</span></h2>
        <button type="button" class="sm-menu-mobile__fermer" data-bs-dismiss="offcanvas" aria-label="Fermer le menu">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="offcanvas-body">
        <nav aria-label="Menu principal">
            <ul class="sm-menu-mobile__liste">
                <li>
                    <a class="sm-menu-mobile__lien" href="<?= e(url()) ?>"<?= attribut_page_active('accueil') ?>>
                        <i class="bi bi-house-fill" aria-hidden="true"></i> Accueil
                    </a>
                </li>
                <li class="sm-menu-mobile__groupe">
                    <button class="sm-menu-mobile__lien sm-menu-mobile__deplier collapsed" type="button"
                            data-bs-toggle="collapse" data-bs-target="#menu-mobile-catalogue"
                            aria-expanded="false" aria-controls="menu-mobile-catalogue">
                        <i class="bi bi-grid-fill" aria-hidden="true"></i> Catalogue
                        <i class="bi bi-chevron-down sm-menu-mobile__chevron" aria-hidden="true"></i>
                    </button>
                    <div class="collapse" id="menu-mobile-catalogue">
                        <div class="sm-menu-mobile__sous-menu">
                            <?php foreach (CATEGORIES as $cle_menu_categorie => $categorie_menu): ?>
                                <a class="sm-mega__carte" href="<?= e(url_catalogue(['categorie' => $cle_menu_categorie])) ?>">
                                    <span class="sm-mega__icone" aria-hidden="true"><i class="bi <?= e($categorie_menu['icone']) ?>"></i></span>
                                    <span class="sm-mega__titre"><?= e($categorie_menu['nom']) ?></span>
                                </a>
                            <?php endforeach; ?>
                            <a class="sm-menu-mobile__tout" href="<?= e(url_catalogue()) ?>">
                                Tout le catalogue <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </li>
                <li>
                    <a class="sm-menu-mobile__lien" href="<?= e(url_page('a-propos')) ?>"<?= attribut_page_active('a-propos') ?>>
                        <i class="bi bi-info-circle-fill" aria-hidden="true"></i> À propos
                    </a>
                </li>
                <li>
                    <a class="sm-menu-mobile__lien" href="<?= e(url_page('contact')) ?>"<?= attribut_page_active('contact') ?>>
                        <i class="bi bi-chat-dots-fill" aria-hidden="true"></i> Contact
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sm-menu-mobile__compte">
            <?php if ($utilisateur_entete !== null): ?>
                <div class="sm-menu-mobile__profil">
                    <span class="sm-avatar" aria-hidden="true"><?= e($initiales_entete) ?></span>
                    <span>
                        <span class="sm-menu-compte__nom"><?= e($utilisateur_entete['prenom'] . ' ' . $utilisateur_entete['nom']) ?></span>
                        <span class="sm-menu-compte__role"><?= e($role_entete) ?></span>
                    </span>
                </div>
                <ul class="sm-menu-mobile__liste">
                    <?php foreach ($menu_compte as [$href_menu, $icone_menu, $libelle_menu, $cle_menu]): ?>
                        <li>
                            <a class="sm-menu-mobile__lien" href="<?= e($href_menu) ?>"<?= attribut_page_active($cle_menu) ?>>
                                <i class="bi <?= e($icone_menu) ?>" aria-hidden="true"></i> <?= e($libelle_menu) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li>
                        <a class="sm-menu-mobile__lien" href="<?= e(url_page('notifications')) ?>"<?= attribut_page_active('notifications') ?>>
                            <i class="bi bi-bell-fill" aria-hidden="true"></i> Notifications
                            <?php if ($notifications_non_lues > 0): ?>
                                <span class="sm-menu-mobile__compteur"><?= e($notifications_non_lues > 99 ? '99+' : $notifications_non_lues) ?><span class="visually-hidden"> non lue<?= $notifications_non_lues > 1 ? 's' : '' ?></span></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <form method="post" action="<?= e(url_page('deconnexion')) ?>">
                            <?= champ_csrf() ?>
                            <button class="sm-menu-mobile__lien sm-menu-compte__deconnexion" type="submit">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Se déconnecter
                            </button>
                        </form>
                    </li>
                </ul>
            <?php else: ?>
                <a class="btn btn-or w-100 sm-menu-mobile__connexion" href="<?= e(url_page('connexion')) ?>">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Connexion
                </a>
                <a class="sm-menu-mobile__inscription" href="<?= e(url_page('inscription')) ?>">Créer un compte</a>
            <?php endif; ?>
        </div>
    </div>
</div>
