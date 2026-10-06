<?php
/**
 * SAMA MAILLOT — administration : état du système (liste de contrôle de la mise en production, §33.2).
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

$controles = controles_systeme();
$comptes = array_count_values(array_column($controles, 2));
$rubriques = [];
foreach ($controles as $controle) {
    $rubriques[$controle[0]][] = $controle;
}
$styles = ['ok' => ['OK', 'succes', 'bi-check-circle-fill'], 'attention' => ['À vérifier', 'attention', 'bi-exclamation-circle-fill'],
    'erreur' => ['Bloquant', 'erreur', 'bi-x-circle-fill']];

$titre_page = 'État du système';
$admin_actif = 'systeme';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>État du système</h1>
        <p>Liste de contrôle de la mise en production, vérifiée en direct sur ce serveur (environnement : <strong><?= e(APP_ENV) ?></strong>).</p>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <?php foreach ($styles as $niveau => [$libelle, $couleur]): ?>
        <span class="sm-statut sm-statut--<?= e($couleur) ?>"><?= e($comptes[$niveau] ?? 0) ?> <?= e(mb_strtolower($libelle)) ?></span>
    <?php endforeach; ?>
</div>

<div class="d-grid gap-3">
    <?php foreach ($rubriques as $rubrique => $lignes): ?>
        <section class="sm-panneau">
            <header class="sm-panneau__entete"><div><h2><?= e($rubrique) ?></h2></div></header>
            <ul class="sm-controles">
                <?php foreach ($lignes as [, $libelle, $niveau, $detail]): ?>
                    <?php [$texte, $couleur, $icone] = $styles[$niveau]; ?>
                    <li class="sm-controle sm-controle--<?= e($couleur) ?>">
                        <i class="bi <?= e($icone) ?>" aria-hidden="true"></i>
                        <span class="visually-hidden"><?= e($texte) ?> :</span>
                        <strong><?= e($libelle) ?></strong>
                        <span class="small text-body-secondary"><?= e($detail) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
