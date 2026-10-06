<?php
/**
 * SAMA MAILLOT — espace vendeur : commandes reçues (§9.1, §12).
 * Uniquement les commandes de la boutique du vendeur connecté (§24, IDOR).
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();
traiter_commandes_echues();

$onglets = [
    'a-confirmer' => ['À confirmer', ['attente_confirmation']],
    'en-cours' => ['En cours', ['confirmee', 'expediee']],
    'livrees' => ['Livrées', ['livree', 'recue', 'en_litige', 'remboursee']],
    'annulees' => ['Annulées', ['annulee']],
];
$onglet = is_string($_GET['onglet'] ?? null) && isset($onglets[$_GET['onglet']]) ? $_GET['onglet'] : 'a-confirmer';
$commandes = liste_commandes('boutique_id', (int) $boutique['id'], $onglets[$onglet][1]);
$comptes = compter_commandes_boutique((int) $boutique['id']);

$titre_page = 'Commandes reçues';
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <?= menu_vendeur('commandes') ?>
        <h1 class="sm-titre-section mb-3">Commandes reçues</h1>

        <nav class="sm-onglets mb-3" aria-label="Filtrer les commandes">
            <?php foreach ($onglets as $cle => [$libelle, $statuts]): ?>
                <?php $nombre = array_sum(array_intersect_key($comptes, array_flip($statuts))); ?>
                <a class="sm-onglet" href="<?= e(url('vendeur/commandes.php?onglet=' . $cle)) ?>"
                   <?= $cle === $onglet ? 'aria-current="page"' : '' ?>>
                    <?= e($libelle) ?> <span class="sm-onglet__nombre"><?= e($nombre) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($commandes === []): ?>
            <div class="sm-etat-vide">
                <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-inbox"></i></div>
                <h2 class="h5">Aucune commande dans cette liste</h2>
                <p class="mb-0">Les nouvelles commandes arrivent ici. Vous avez 24 h pour confirmer chacune d'elles.</p>
            </div>
        <?php else: ?>
            <ul class="sm-liste-commandes">
                <?php foreach ($commandes as $commande): ?>
                    <li>
                        <a class="sm-carte-commande" href="<?= e(url('vendeur/commande.php?id=' . (int) $commande['id'])) ?>">
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
                                    <?= e($commande['client_nom']) ?> · <?= e($commande['nombre_articles']) ?> article<?= (int) $commande['nombre_articles'] > 1 ? 's' : '' ?>
                                    · <?= e(date_commande($commande['cree_le'])) ?>
                                </span>
                                <span class="d-flex flex-wrap gap-2 align-items-center">
                                    <?= badge_statut_commande($commande['statut']) ?>
                                    <?php if ($restant = delai_confirmation_restant($commande)): ?>
                                        <span class="small sm-delai"><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= e($restant) ?> pour confirmer</span>
                                    <?php endif; ?>
                                    <?php if ($commande['origine'] === 'whatsapp'): ?>
                                        <span class="small text-body-secondary"><i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp</span>
                                    <?php endif; ?>
                                </span>
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
