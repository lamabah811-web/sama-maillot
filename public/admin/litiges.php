<?php
/**
 * SAMA MAILLOT — administration : réclamations des clients (§16.3).
 * En cours d'abord ; un retard de réponse du vendeur (48 h) est mis en évidence.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

$filtre = ($_GET['filtre'] ?? '') === 'tous' ? 'tous' : 'ouvert';
$litiges = liste_litiges($filtre);
$total = (int) bdd()->query('SELECT COUNT(*) FROM litiges')->fetchColumn();
$ouverts = nombre_litiges_ouverts();

$titre_page = 'Réclamations';
$admin_actif = 'litiges';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>Réclamations</h1>
        <p>Paiement à la livraison : médiation entre le client et le vendeur. Une réclamation fondée compte comme un signalement du vendeur.</p>
    </div>
</div>

<nav class="sm-onglets mb-3" aria-label="Filtrer les réclamations">
    <a class="sm-onglet" href="<?= e(url('admin/litiges.php')) ?>" <?= $filtre === 'ouvert' ? 'aria-current="page"' : '' ?>>En cours <span class="sm-onglet__nombre"><?= e($ouverts) ?></span></a>
    <a class="sm-onglet" href="<?= e(url('admin/litiges.php?filtre=tous')) ?>" <?= $filtre === 'tous' ? 'aria-current="page"' : '' ?>>Toutes <span class="sm-onglet__nombre"><?= e($total) ?></span></a>
</nav>

<?php if ($litiges === []): ?>
    <div class="sm-panneau"><p class="sm-panneau__vide"><i class="bi bi-shield-check" aria-hidden="true"></i> Aucune réclamation ici.</p></div>
<?php else: ?>
    <div class="d-grid gap-3">
        <?php foreach ($litiges as $l): ?>
            <?php $retard = $l['statut'] === 'ouvert' && (int) $l['vendeur_a_repondu'] === 0 && strtotime($l['reponse_limite_le']) < time(); ?>
            <article class="sm-panneau">
                <header class="sm-panneau__entete">
                    <span class="sm-kpi__icone <?= $l['statut'] === 'ouvert' ? 'sm-teinte--rouge' : 'sm-teinte--violet' ?>" aria-hidden="true"><i class="bi bi-shield-exclamation"></i></span>
                    <div>
                        <h2><a href="<?= e(url('admin/litige.php?id=' . (int) $l['id'])) ?>"><?= e(MOTIFS_LITIGE[$l['motif']] ?? $l['motif']) ?> · <?= e($l['numero']) ?></a></h2>
                        <p>
                            <?= e($l['client_prenom'] . ' ' . $l['client_nom']) ?> → <?= e($l['boutique_nom']) ?>
                            · <?= e(format_fcfa((int) $l['total'])) ?> · <?= e(date_relative($l['cree_le'])) ?>
                        </p>
                    </div>
                    <?php if ($retard): ?>
                        <span class="sm-statut sm-statut--erreur">Vendeur sans réponse</span>
                    <?php elseif ($l['statut'] === 'ouvert' && (int) $l['vendeur_a_repondu'] === 1): ?>
                        <span class="sm-statut sm-statut--info">Vendeur a répondu</span>
                    <?php endif; ?>
                    <?= badge_statut_litige($l) ?>
                </header>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
