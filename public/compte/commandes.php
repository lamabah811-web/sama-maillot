<?php
/**
 * SAMA MAILLOT — « Mes commandes » (§8.7, §14) : suivi des commandes passées.
 * Un vendeur y retrouve aussi ses ACHATS (les commandes reçues par sa boutique sont dans
 * l'espace vendeur).
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();
traiter_commandes_echues();   // annulations et réceptions automatiques à jour avant l'affichage

$onglets = [
    'en-cours' => ['En cours', STATUTS_COMMANDE_EN_COURS],
    'terminees' => ['Terminées', ['recue', 'remboursee', 'en_litige']],
    'annulees' => ['Annulées', ['annulee']],
];
$onglet = is_string($_GET['onglet'] ?? null) && isset($onglets[$_GET['onglet']]) ? $_GET['onglet'] : 'en-cours';
$commandes = liste_commandes('client_id', (int) $utilisateur['id'], $onglets[$onglet][1]);

$titre_page = 'Mes commandes';
$sans_indexation = true;
$page_active = 'mes-commandes';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb sm-fil-ariane">
                <li class="breadcrumb-item"><a href="<?= e(url_page('compte')) ?>">Mon compte</a></li>
                <li class="breadcrumb-item active" aria-current="page">Mes commandes</li>
            </ol>
        </nav>
        <h1 class="sm-titre-section mb-3">Mes commandes</h1>

        <nav class="sm-onglets mb-3" aria-label="Filtrer les commandes">
            <?php foreach ($onglets as $cle => [$libelle]): ?>
                <a class="sm-onglet" href="<?= e(url('compte/commandes.php?onglet=' . $cle)) ?>"
                   <?= $cle === $onglet ? 'aria-current="page"' : '' ?>><?= e($libelle) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($commandes === []): ?>
            <div class="sm-etat-vide">
                <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-receipt"></i></div>
                <h2 class="h5">Aucune commande ici</h2>
                <p>Vos commandes apparaîtront ici, avec leur suivi.</p>
                <a class="btn btn-or" href="<?= e(url_page('catalogue')) ?>">Voir le catalogue</a>
            </div>
        <?php else: ?>
            <ul class="sm-liste-commandes">
                <?php foreach ($commandes as $commande): ?>
                    <li>
                        <a class="sm-carte-commande" href="<?= e(url('compte/commande.php?id=' . (int) $commande['id'])) ?>">
                            <span class="sm-carte-commande__image" aria-hidden="true">
                                <?php if ($commande['image']): ?>
                                    <img src="<?= e(url_image_maillot($commande['image'])) ?>" alt="" width="400" height="400" loading="lazy">
                                <?php else: ?>
                                    <i class="bi bi-box-seam"></i>
                                <?php endif; ?>
                            </span>
                            <span class="sm-carte-commande__infos">
                                <strong><?= e($commande['numero']) ?></strong>
                                <span class="small text-body-secondary">
                                    <?= e($commande['boutique_nom']) ?> · <?= e($commande['nombre_articles']) ?> article<?= (int) $commande['nombre_articles'] > 1 ? 's' : '' ?>
                                    · <?= e(date_commande($commande['cree_le'])) ?>
                                </span>
                                <?= badge_statut_commande($commande['statut']) ?>
                            </span>
                            <strong class="sm-carte-commande__total"><?= e(format_fcfa((int) $commande['total'])) ?></strong>
                            <i class="bi bi-chevron-right text-body-secondary" aria-hidden="true"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
