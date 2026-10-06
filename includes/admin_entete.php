<?php
/**
 * SAMA MAILLOT — mise en page de l'espace administrateur (§23) : début de page.
 *
 * Barre latérale (rubriques, compteurs réels, profil + déconnexion) et barre du haut
 * (état de la plateforme, date, actualisation). Sur mobile, la barre latérale devient un
 * panneau glissant (offcanvas Bootstrap) ouvert par le bouton menu.
 *
 * Variables attendues : $admin (compte connecté), $titre_page, $admin_actif (clé de rubrique).
 */

$titre_complet = ($titre_page ?? 'Administration') . ' — Administration ' . NOM_SITE;
$description_page = 'Espace d\'administration SAMA MAILLOT.';
$sans_indexation = true;
$admin_actif ??= 'tableau';

$compteurs = compteurs_menu_admin();
$initiales_admin = mb_strtoupper(mb_substr($admin['prenom'], 0, 1) . mb_substr($admin['nom'], 0, 1));

$jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$date_du_jour = ucfirst($jours[(int) date('w')]) . ' ' . date('j') . ' ' . $mois[(int) date('n') - 1] . ' ' . date('Y');

// Rubriques : [clé, adresse (null = bientôt), icône, libellé, compteur, couleur du compteur]
$rubriques_admin = [
    'Tableau de bord' => [
        ['tableau', 'admin/', 'bi-speedometer2', 'Vue d\'ensemble', null, ''],
    ],
    'Gestion' => [
        ['utilisateurs', 'admin/utilisateurs.php', 'bi-people-fill', 'Utilisateurs', $compteurs['utilisateurs'], ''],
        ['vendeurs', 'admin/utilisateurs.php?role=vendeur', 'bi-shop', 'Vendeurs', $compteurs['vendeurs'], ''],
        ['maillots', 'admin/maillots.php', 'bi-box-seam-fill', 'Maillots', $compteurs['maillots'], ''],
        ['commandes', 'admin/commandes.php', 'bi-bag-fill', 'Commandes', $compteurs['commandes_attente'], $compteurs['commandes_attente'] > 0 ? 'attention' : ''],
        ['abonnements', 'admin/abonnements.php', 'bi-calendar-check-fill', 'Abonnements', $compteurs['paiements'], $compteurs['paiements'] > 0 ? 'attention' : ''],
        ['paiements', 'admin/paiements.php', 'bi-cash-coin', 'Paiements', $compteurs['paiements_admin'], $compteurs['paiements_admin'] > 0 ? 'attention' : ''],
        ['catalogue', 'admin/catalogue.php', 'bi-tags-fill', 'Catalogue', null, ''],
    ],
    'Modération' => [
        ['signalements', 'admin/signalements.php', 'bi-exclamation-triangle-fill', 'Signalements', $compteurs['signalements'],
            $compteurs['signalements'] > 0 ? 'erreur' : ''],
        ['litiges', 'admin/litiges.php', 'bi-shield-exclamation', 'Réclamations', $compteurs['litiges'], $compteurs['litiges'] > 0 ? 'erreur' : ''],
        ['avis', 'admin/avis.php', 'bi-star-fill', 'Avis', null, ''],
        ['messages', 'admin/messages.php', 'bi-envelope-fill', 'Messages', $compteurs['messages'], $compteurs['messages'] > 0 ? 'erreur' : ''],
        ['journal', 'admin/journal.php', 'bi-journal-text', 'Journal d\'audit', null, ''],
    ],
    'Réglages' => [
        ['parametres', 'admin/parametres.php', 'bi-gear-fill', 'Paramètres', null, ''],
        ['systeme', 'admin/systeme.php', 'bi-heart-pulse-fill', 'État du système', null, ''],
    ],
];
?>
<?php require __DIR__ . '/tete_html.php'; ?>
<body class="sm-admin">
<a class="sm-evitement" href="#contenu">Aller au contenu</a>

<div class="sm-admin__cadre">
    <!-- ===== Barre latérale (panneau glissant sous 992 px) ===== -->
    <aside class="sm-admin__barre offcanvas-lg offcanvas-start" tabindex="-1" id="barre-admin" aria-label="Menu d'administration">
        <div class="sm-admin__marque">
            <a class="sm-logo" href="<?= e(url('admin/')) ?>">
                <img class="sm-logo__image sm-logo__image--clair" src="<?= e(ressource('images/logo-clair.webp')) ?>" alt="" width="180" height="162">
                <img class="sm-logo__image sm-logo__image--sombre" src="<?= e(ressource('images/logo-sombre.webp')) ?>" alt="" width="180" height="162">
                <span><span class="sm-logo__sama">SAMA</span>&nbsp;<span class="sm-logo__maillot">MAILLOT</span></span>
            </a>
            <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#barre-admin" aria-label="Fermer le menu"></button>
        </div>

        <nav class="sm-admin__nav" aria-label="Rubriques d'administration">
            <?php foreach ($rubriques_admin as $section => $entrees): ?>
                <p class="sm-admin__section"><?= e($section) ?></p>
                <ul>
                    <?php foreach ($entrees as [$cle, $chemin, $icone, $libelle, $compteur, $couleur]): ?>
                        <li>
                            <?php if ($chemin === null): ?>
                                <span class="sm-admin__lien sm-admin__lien--bientot" title="Arrive avec la partie 2 du lot 5">
                                    <i class="bi <?= e($icone) ?>" aria-hidden="true"></i>
                                    <span><?= e($libelle) ?></span>
                                    <small>Bientôt</small>
                                </span>
                            <?php else: ?>
                                <a class="sm-admin__lien" href="<?= e(url($chemin)) ?>"<?= $cle === $admin_actif ? ' aria-current="page"' : '' ?>>
                                    <i class="bi <?= e($icone) ?>" aria-hidden="true"></i>
                                    <span><?= e($libelle) ?></span>
                                    <?php if ($compteur !== null): ?>
                                        <span class="sm-admin__compteur<?= $couleur !== '' ? ' sm-admin__compteur--' . e($couleur) : '' ?>"><?= e($compteur) ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>

            <p class="sm-admin__section">Site</p>
            <ul>
                <li>
                    <a class="sm-admin__lien" href="<?= e(url()) ?>">
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>Voir le site</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sm-admin__profil">
            <span class="sm-admin__avatar" aria-hidden="true"><?= e($initiales_admin) ?></span>
            <span class="sm-admin__identite">
                <strong><?= e($admin['prenom'] . ' ' . $admin['nom']) ?></strong>
                <span><?= e($admin['role_libelle']) ?></span>
            </span>
            <form method="post" action="<?= e(url_page('deconnexion')) ?>">
                <?= champ_csrf() ?>
                <button class="sm-admin__deconnexion" type="submit" aria-label="Se déconnecter" title="Se déconnecter">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </aside>

    <!-- ===== Zone principale ===== -->
    <div class="sm-admin__principal">
        <header class="sm-admin__haut">
            <button class="sm-admin__menu d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#barre-admin"
                    aria-controls="barre-admin" aria-label="Ouvrir le menu">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <span class="sm-admin__etat"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Plateforme active</span>
            <span class="sm-admin__date d-none d-sm-inline-flex"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e($date_du_jour) ?></span>
            <span class="sm-admin__direct d-none d-md-inline ms-auto">Données en direct · <?= e(date('H\hi')) ?></span>
            <button class="sm-admin__theme ms-auto ms-md-0" type="button" data-bascule-theme aria-label="Mode sombre" aria-pressed="false" title="Passer en mode sombre">
                <i class="bi bi-moon-fill sm-icone-lune" aria-hidden="true"></i>
                <i class="bi bi-sun-fill sm-icone-soleil" aria-hidden="true"></i>
            </button>
        </header>

        <main id="contenu" class="sm-admin__contenu" tabindex="-1">
            <?php require __DIR__ . '/messages_flash.php'; ?>
