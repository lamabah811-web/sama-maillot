<?php
/**
 * SAMA MAILLOT — administration : abonnements des vendeurs (§18, §23.2).
 *
 * - Paiements Wave à vérifier : capture, référence, montant ; valider (nouvelle période) ou
 *   refuser avec motif. Le vendeur est prévenu (notification + SMS).
 * - Toutes les boutiques avec l'état de leur abonnement et la date de fin.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        message_flash('erreur', 'Votre session a expiré. Merci de réessayer.');
    } elseif (champ_post('action') === 'valider') {
        $erreur = valider_paiement_abonnement($admin, (int) champ_post('paiement_id'));
        message_flash($erreur === null ? 'succes' : 'erreur', $erreur ?? 'Paiement validé : l\'abonnement est prolongé et le vendeur prévenu.');
    } elseif (($motif = lire_motif_admin()) === null) {
        message_flash('erreur', 'Pour refuser, indiquez un motif (5 caractères minimum) : il est envoyé au vendeur.');
    } else {
        $erreur = refuser_paiement_abonnement($admin, (int) champ_post('paiement_id'), $motif);
        message_flash($erreur === null ? 'succes' : 'erreur', $erreur ?? 'Paiement refusé. Le vendeur est prévenu.');
    }
    rediriger('admin/abonnements.php');
}

$a_verifier = bdd()->query(
    "SELECT p.*, b.nom AS boutique_nom, u.prenom, u.nom, u.telephone
     FROM paiements_abonnement p JOIN boutiques b ON b.id = p.boutique_id JOIN utilisateurs u ON u.id = p.utilisateur_id
     WHERE p.statut = 'en_verification' ORDER BY p.cree_le"
)->fetchAll();

$boutiques = [];
foreach (bdd()->query('SELECT b.*, u.prenom, u.nom AS vendeur_nom FROM boutiques b JOIN utilisateurs u ON u.id = b.utilisateur_id WHERE b.supprime_le IS NULL ORDER BY b.nom')->fetchAll() as $b) {
    $b['abonnement'] = calculer_etat_abonnement($b);
    $boutiques[] = $b;
}
$revenus_mois = revenus_abonnement(date('Y-m-01 00:00:00'));
$revenus_total = revenus_abonnement();

$couleurs = ['essai' => 'succes', 'actif' => 'succes', 'verification' => 'attention', 'grace' => 'attention', 'expire' => 'erreur', 'aucun' => 'erreur', 'suspendue' => 'erreur'];

$titre_page = 'Abonnements';
$admin_actif = 'abonnements';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>Abonnements</h1>
        <p><?= e(format_fcfa(prix_abonnement())) ?> par mois, payés par Wave
            (<?= lien_paiement_wave(prix_abonnement()) !== null ? 'lien Wave Business configuré' : 'lien Wave : <a href="' . e(url('admin/parametres.php')) . '">à renseigner</a>' ?>
            · numéro : <?= parametre('numero_wave_plateforme') !== '' ? e(formater_telephone(parametre('numero_wave_plateforme'))) : '<a href="' . e(url('admin/parametres.php')) . '">à renseigner</a>' ?>).
            Vérifiez chaque identifiant dans votre application Wave Business avant de valider.</p>
    </div>
</div>

<div class="sm-kpis sm-kpis--3 mb-3">
    <div class="sm-kpi">
        <span class="sm-kpi__entete"><span class="sm-kpi__libelle">À vérifier</span><span class="sm-kpi__icone sm-teinte--or" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span></span>
        <span class="sm-kpi__valeur"><?= e(count($a_verifier)) ?></span><span class="sm-kpi__detail">paiement(s) Wave en attente</span>
    </div>
    <div class="sm-kpi">
        <span class="sm-kpi__entete"><span class="sm-kpi__libelle">Ce mois-ci</span><span class="sm-kpi__icone sm-teinte--vert" aria-hidden="true"><i class="bi bi-cash-stack"></i></span></span>
        <span class="sm-kpi__valeur"><?= e(format_fcfa($revenus_mois)) ?></span><span class="sm-kpi__detail">revenus d'abonnement validés</span>
    </div>
    <div class="sm-kpi">
        <span class="sm-kpi__entete"><span class="sm-kpi__libelle">Depuis le début</span><span class="sm-kpi__icone sm-teinte--violet" aria-hidden="true"><i class="bi bi-graph-up"></i></span></span>
        <span class="sm-kpi__valeur"><?= e(format_fcfa($revenus_total)) ?></span><span class="sm-kpi__detail">total validé</span>
    </div>
</div>

<section class="sm-panneau mb-4">
    <header class="sm-panneau__entete">
        <span class="sm-kpi__icone sm-teinte--or" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
        <div><h2>Paiements à vérifier</h2><p>Vérifiez la réception sur le compte Wave de la plateforme avant de valider.</p></div>
    </header>
    <?php if ($a_verifier === []): ?>
        <p class="sm-panneau__vide"><i class="bi bi-check2-circle" aria-hidden="true"></i> Aucun paiement en attente.</p>
    <?php else: ?>
        <div class="d-grid gap-3">
            <?php foreach ($a_verifier as $p): ?>
                <article class="sm-paiement-admin">
                    <?php if ($p['capture'] !== null): ?>
                        <a class="sm-paiement-admin__capture" href="<?= e(url('admin/capture.php?id=' . (int) $p['id'])) ?>" target="_blank" rel="noopener" title="Agrandir la capture">
                            <img src="<?= e(url('admin/capture.php?id=' . (int) $p['id'])) ?>" alt="Capture du paiement <?= e($p['reference_wave']) ?>" loading="lazy">
                        </a>
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <p class="mb-1 fw-bold"><?= e($p['boutique_nom']) ?> · <?= e(format_fcfa((int) $p['montant'])) ?> (<?= e($p['mois']) ?> mois)</p>
                        <p class="small mb-1">Référence Wave : <strong class="sm-reference"><?= e($p['reference_wave']) ?></strong></p>
                        <p class="small text-body-secondary mb-3">
                            <?= e($p['prenom'] . ' ' . $p['nom']) ?><?= $p['telephone'] !== null ? ' · ' . e(formater_telephone($p['telephone'])) : '' ?> · envoyé <?= e(date_relative($p['cree_le'])) ?>
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <form method="post">
                                <?= champ_csrf() ?>
                                <input type="hidden" name="paiement_id" value="<?= e($p['id']) ?>">
                                <button class="btn btn-sm btn-or" type="submit" name="action" value="valider"><i class="bi bi-check2" aria-hidden="true"></i> Valider</button>
                            </form>
                            <form method="post" class="d-flex flex-wrap gap-2 flex-grow-1">
                                <?= champ_csrf() ?>
                                <input type="hidden" name="paiement_id" value="<?= e($p['id']) ?>">
                                <label class="visually-hidden" for="motif-<?= e($p['id']) ?>">Motif du refus</label>
                                <input class="form-control form-control-sm flex-grow-1 w-auto" type="text" id="motif-<?= e($p['id']) ?>" name="motif"
                                       maxlength="255" minlength="5" placeholder="Motif du refus (ex. : montant non reçu)">
                                <button class="btn btn-sm btn-contour sm-bouton-danger" type="submit" name="action" value="refuser">Refuser</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="sm-panneau">
    <header class="sm-panneau__entete">
        <span class="sm-kpi__icone sm-teinte--violet" aria-hidden="true"><i class="bi bi-shop"></i></span>
        <div><h2>Boutiques</h2><p>État de l'abonnement calculé en direct</p></div>
    </header>
    <div class="table-responsive">
        <table class="table sm-table-admin align-middle mb-0">
            <thead><tr><th>Boutique</th><th>Vendeur</th><th>Abonnement</th><th>Fin de période</th></tr></thead>
            <tbody>
                <?php foreach ($boutiques as $b): ?>
                    <tr>
                        <td><a class="fw-semibold" href="<?= e(url('admin/utilisateur.php?id=' . (int) $b['utilisateur_id'])) ?>"><?= e($b['nom']) ?></a></td>
                        <td><?= e($b['prenom'] . ' ' . $b['vendeur_nom']) ?></td>
                        <td><span class="sm-statut sm-statut--<?= e($couleurs[$b['abonnement']['etat']] ?? 'info') ?>"><?= e($b['abonnement']['libelle']) ?></span></td>
                        <td class="text-nowrap"><?= $b['abonnement']['fin'] !== null ? e(date('d/m/Y', strtotime($b['abonnement']['fin']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
