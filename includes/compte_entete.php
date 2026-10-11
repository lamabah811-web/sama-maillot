<?php
/**
 * SAMA MAILLOT — compte de l'utilisateur connecté dans l'en-tête : cloche des notifications + menu du compte.
 * Inclus par entete.php, tout à droite dès 992 px (sur téléphone, le compte est dans menu_mobile.php).
 * Attend : $utilisateur_entete, $notifications_non_lues, $menu_compte, $initiales_entete, $role_entete.
 */
?>
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
