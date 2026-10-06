<?php
/**
 * SAMA MAILLOT — administration : paiements en ligne, versements et remboursements (§13, §15).
 *
 *   Versements      boutiques dont le disponible atteint le minimum : l'admin fait le virement Wave,
 *                   puis saisit la référence (tout le disponible passe en « versé »).
 *   Remboursements  commandes payées en ligne annulées ou réclamations fondées : l'admin rembourse
 *                   le client, puis saisit la référence.
 *   Transactions    paiements reçus de l'agrégateur, anomalies (montant incorrect) en premier.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $onglet = champ_post('action') === 'rembourser' ? 'remboursements' : 'versements';
    if (!verifier_csrf()) {
        message_flash('erreur', 'Votre session a expiré. Merci de réessayer.');
    } elseif (champ_post('action') === 'verser') {
        $erreur = enregistrer_versement($admin, (int) champ_post('boutique_id'), champ_post('reference'));
        message_flash($erreur === null ? 'succes' : 'erreur', $erreur ?? 'Versement enregistré. Le vendeur est prévenu.');
    } elseif (champ_post('action') === 'rembourser') {
        $erreur = marquer_rembourse($admin, (int) champ_post('remboursement_id'), champ_post('reference'));
        message_flash($erreur === null ? 'succes' : 'erreur', $erreur ?? 'Remboursement enregistré. Le client est prévenu.');
    }
    rediriger('admin/paiements.php?onglet=' . $onglet);
}

$onglet = in_array($_GET['onglet'] ?? '', ['remboursements', 'transactions'], true) ? $_GET['onglet'] : 'versements';
$a_verser = boutiques_a_verser();
$a_rembourser = remboursements_a_faire();
$transactions = bdd()->query(
    "SELECT t.*, u.prenom, u.nom FROM transactions_paiement t JOIN utilisateurs u ON u.id = t.client_id
     ORDER BY t.statut = 'montant_incorrect' DESC, t.cree_le DESC LIMIT 100"
)->fetchAll();
$historique = versements_effectues(null, 30);
$total_sequestre = (int) bdd()->query("SELECT COALESCE(SUM(montant), 0) FROM mouvements_portefeuille WHERE compartiment IN ('en_attente', 'disponible', 'bloque')")->fetchColumn();

$titre_page = 'Paiements';
$admin_actif = 'paiements';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>Paiements</h1>
        <p>
            <?php if (paiement_mode_test()): ?><span class="badge text-bg-warning">Simulateur de TEST</span><?php elseif (!paiement_en_ligne_actif()): ?><span class="badge text-bg-secondary">Paiement en ligne désactivé</span><?php endif; ?>
            Argent détenu pour les vendeurs : <strong><?= e(format_fcfa($total_sequestre)) ?></strong>.
            Prochain versement : <?= e(prochain_versement()) ?>.
        </p>
    </div>
</div>

<nav class="sm-onglets mb-3" aria-label="Rubriques des paiements">
    <a class="sm-onglet" href="<?= e(url('admin/paiements.php')) ?>" <?= $onglet === 'versements' ? 'aria-current="page"' : '' ?>>Versements <span class="sm-onglet__nombre"><?= e(count($a_verser)) ?></span></a>
    <a class="sm-onglet" href="<?= e(url('admin/paiements.php?onglet=remboursements')) ?>" <?= $onglet === 'remboursements' ? 'aria-current="page"' : '' ?>>Remboursements <span class="sm-onglet__nombre"><?= e(count($a_rembourser)) ?></span></a>
    <a class="sm-onglet" href="<?= e(url('admin/paiements.php?onglet=transactions')) ?>" <?= $onglet === 'transactions' ? 'aria-current="page"' : '' ?>>Transactions</a>
</nav>

<?php if ($onglet === 'versements'): ?>
    <section class="sm-panneau mb-4">
        <header class="sm-panneau__entete">
            <span class="sm-kpi__icone sm-teinte--or" aria-hidden="true"><i class="bi bi-send"></i></span>
            <div><h2>À verser</h2><p>Disponible ≥ <?= e(format_fcfa((int) parametre('versement_minimum_fcfa', '1000'))) ?>. Faites le virement Wave, puis saisissez la référence.</p></div>
        </header>
        <?php if ($a_verser === []): ?>
            <p class="sm-panneau__vide"><i class="bi bi-check2-all" aria-hidden="true"></i> Aucun versement à faire.</p>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($a_verser as $b): ?>
                    <form method="post" class="sm-ligne-admin d-flex flex-wrap align-items-center gap-2">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="action" value="verser">
                        <input type="hidden" name="boutique_id" value="<?= e($b['id']) ?>">
                        <div class="flex-grow-1">
                            <strong><?= e($b['nom']) ?></strong> <span class="small text-body-secondary">· <?= e($b['prenom'] . ' ' . $b['vendeur_nom']) ?></span>
                            <span class="d-block small">
                                <?= $b['numero_wave'] !== null && $b['numero_wave'] !== ''
                                    ? 'Wave : <strong>' . e(formater_telephone($b['numero_wave'])) . '</strong>'
                                    : '<span class="text-danger">Pas de numéro Wave</span>' ?>
                            </span>
                        </div>
                        <strong class="fs-5"><?= e(format_fcfa((int) $b['disponible'])) ?></strong>
                        <label class="visually-hidden" for="ref-v-<?= e($b['id']) ?>">Référence Wave</label>
                        <input class="form-control form-control-sm w-auto" type="text" id="ref-v-<?= e($b['id']) ?>" name="reference" required
                               maxlength="60" placeholder="Référence Wave" autocomplete="off">
                        <button class="btn btn-sm btn-or" type="submit">Marquer versé</button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="sm-panneau">
        <header class="sm-panneau__entete"><div><h2>Derniers versements</h2></div></header>
        <?php if ($historique === []): ?>
            <p class="sm-panneau__vide">Aucun versement enregistré.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Date</th><th>Boutique</th><th>Wave</th><th>Référence</th><th class="text-end">Montant</th></tr></thead>
                    <tbody>
                        <?php foreach ($historique as $v): ?>
                            <tr>
                                <td><?= e(date_commande($v['cree_le'])) ?></td>
                                <td><?= e($v['boutique_nom']) ?></td>
                                <td><?= e(formater_telephone($v['numero_wave'])) ?></td>
                                <td><?= e($v['reference_wave']) ?></td>
                                <td class="text-end fw-bold"><?= e(format_fcfa((int) $v['montant'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($onglet === 'remboursements'): ?>
    <section class="sm-panneau">
        <header class="sm-panneau__entete">
            <span class="sm-kpi__icone sm-teinte--rouge" aria-hidden="true"><i class="bi bi-arrow-counterclockwise"></i></span>
            <div><h2>Remboursements à effectuer</h2><p>Remboursez le client sur le moyen utilisé, puis saisissez la référence.</p></div>
        </header>
        <?php if ($a_rembourser === []): ?>
            <p class="sm-panneau__vide"><i class="bi bi-check2-all" aria-hidden="true"></i> Aucun remboursement en attente.</p>
        <?php else: ?>
            <div class="d-grid gap-3">
                <?php foreach ($a_rembourser as $r): ?>
                    <form method="post" class="sm-ligne-admin d-flex flex-wrap align-items-center gap-2">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="action" value="rembourser">
                        <input type="hidden" name="remboursement_id" value="<?= e($r['id']) ?>">
                        <div class="flex-grow-1">
                            <strong><?= e($r['numero']) ?></strong> · <?= e($r['prenom'] . ' ' . $r['nom']) ?>
                            <span class="d-block small text-body-secondary">
                                <?= e($r['motif']) ?> · <?= e(formater_telephone($r['telephone_client'])) ?>
                                <?= $r['moyen'] !== null ? ' · payé par ' . e(MOYENS_PAIEMENT[$r['moyen']] ?? $r['moyen']) : '' ?>
                                · paiement <?= e($r['reference_paiement']) ?>
                            </span>
                        </div>
                        <strong class="fs-5"><?= e(format_fcfa((int) $r['montant'])) ?></strong>
                        <label class="visually-hidden" for="ref-r-<?= e($r['id']) ?>">Référence du remboursement</label>
                        <input class="form-control form-control-sm w-auto" type="text" id="ref-r-<?= e($r['id']) ?>" name="reference" required
                               maxlength="60" placeholder="Référence" autocomplete="off">
                        <button class="btn btn-sm btn-or" type="submit">Marquer remboursé</button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

<?php else: ?>
    <section class="sm-panneau">
        <header class="sm-panneau__entete"><div><h2>Transactions</h2><p>Les anomalies (montant incorrect) apparaissent en premier.</p></div></header>
        <?php if ($transactions === []): ?>
            <p class="sm-panneau__vide">Aucune transaction.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Date</th><th>Référence</th><th>Client</th><th>Moyen</th><th class="text-end">Attendu</th><th class="text-end">Reçu</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><?= e(date_commande($t['cree_le'])) ?></td>
                                <td><?= e($t['reference']) ?><?= $t['fournisseur'] === 'simulateur' ? ' <span class="badge text-bg-warning">TEST</span>' : '' ?></td>
                                <td><?= e($t['prenom'] . ' ' . $t['nom']) ?></td>
                                <td><?= e($t['moyen'] !== null ? (MOYENS_PAIEMENT[$t['moyen']] ?? $t['moyen']) : '—') ?></td>
                                <td class="text-end"><?= e(format_fcfa((int) $t['montant'])) ?></td>
                                <td class="text-end"><?= $t['montant_recu'] !== null ? e(format_fcfa((int) $t['montant_recu'])) : '—' ?></td>
                                <td><?= badge_statut(STATUTS_TRANSACTION, $t['statut']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
