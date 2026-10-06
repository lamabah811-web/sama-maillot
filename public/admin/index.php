<?php
/**
 * SAMA MAILLOT — administration : tableau de bord (§23.1).
 * Chiffres réels uniquement (lus en base, en direct), files d'action prioritaires,
 * activité des commandes (30 jours) et répartition des utilisateurs.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();
traiter_commandes_echues();

$chiffres = chiffres_tableau_de_bord();
$files = files_prioritaires();
$serie = commandes_par_jour(30);
$inscrits = derniers_inscrits(6);
$dernieres_commandes = bdd()->query(
    'SELECT c.id, c.numero, c.client_nom, c.total, c.statut, c.cree_le, b.nom AS boutique_nom
     FROM commandes c JOIN boutiques b ON b.id = c.boutique_id ORDER BY c.cree_le DESC, c.id DESC LIMIT 6'
)->fetchAll();

$utilisateurs = $chiffres['utilisateurs'];
$total_utilisateurs = array_sum($utilisateurs);
$commandes = $chiffres['commandes'];
$total_commandes = array_sum($commandes);
$en_attente = $commandes['attente_confirmation'] ?? 0;
$messages = (int) bdd()->query('SELECT COUNT(*) FROM messages_contact')->fetchColumn();
$messages_non_lus = (int) bdd()->query("SELECT COUNT(*) FROM messages_contact WHERE statut = 'nouveau'")->fetchColumn();
$essais = $chiffres['abonnements']['Essai gratuit'] ?? 0;
$abonnes = $chiffres['abonnements']['Abonnement actif'] ?? 0;

// Cartes d'indicateurs : [libellé, valeur, détail, icône, teinte, lien]
$cartes = [
    ['Utilisateurs', $total_utilisateurs, ($utilisateurs['client'] ?? 0) . ' clients · ' . ($utilisateurs['vendeur'] ?? 0) . ' vendeurs',
        'bi-people-fill', 'or', 'admin/utilisateurs.php'],
    ['Vendeurs', $utilisateurs['vendeur'] ?? 0, $essais . ' en essai · ' . $abonnes . ' abonnés', 'bi-shop', 'violet', 'admin/utilisateurs.php?role=vendeur'],
    ['Maillots', $chiffres['maillots']['publie'] ?? 0, 'publiés au catalogue', 'bi-box-seam-fill', 'vert', 'admin/maillots.php?statut=publie'],
    ['Commandes', $total_commandes, $en_attente . ' en attente de confirmation', 'bi-bag-fill', 'bleu', 'admin/commandes.php'],
    ['Ventes reçues', format_fcfa($chiffres['volume_recu']), 'paiement à la livraison', 'bi-cash-stack', 'or', 'admin/commandes.php?statut=recue'],
    ['Revenus abo.', format_fcfa(revenus_abonnement(date('Y-m-01 00:00:00'))), 'ce mois · ' . format_fcfa(revenus_abonnement()) . ' au total',
        'bi-wallet2', 'rouge', 'admin/abonnements.php'],
];

$repartition = [
    ['Clients', $utilisateurs['client'] ?? 0, '#D4AF37', 'or'],
    ['Vendeurs', $utilisateurs['vendeur'] ?? 0, '#7C5CFF', 'violet'],
    ['Administrateurs', $utilisateurs['admin'] ?? 0, '#14B8A6', 'turquoise'],
];

$titre_page = 'Tableau de bord';
$admin_actif = 'tableau';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>Tableau de bord</h1>
        <p>Bonjour <?= e($admin['prenom']) ?>, voici l'activité de SAMA MAILLOT.</p>
    </div>
</div>

<!-- Indicateurs clés -->
<div class="sm-kpis">
    <?php foreach ($cartes as [$libelle, $valeur, $detail, $icone, $teinte, $lien]): ?>
        <a class="sm-kpi" href="<?= e(url($lien)) ?>">
            <span class="sm-kpi__entete">
                <span class="sm-kpi__libelle"><?= e($libelle) ?></span>
                <span class="sm-kpi__icone sm-teinte--<?= e($teinte) ?>" aria-hidden="true"><i class="bi <?= e($icone) ?>"></i></span>
            </span>
            <span class="sm-kpi__valeur"><?= e($valeur) ?></span>
            <span class="sm-kpi__detail"><?= e($detail) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Files d'action prioritaires (§23.1) -->
<div class="sm-alertes-admin" aria-label="À traiter">
    <?php foreach ($files as [$libelle, $nombre, $lien, $icone]): ?>
        <?php $balise = $lien !== null && $nombre > 0 ? 'a' : 'span'; ?>
        <<?= $balise ?> class="sm-alerte-admin<?= $nombre > 0 ? ' sm-alerte-admin--active' : '' ?>"
            <?= $balise === 'a' ? 'href="' . e(url($lien)) . '"' : '' ?> title="<?= e($libelle) ?>">
            <i class="bi <?= e($icone) ?>" aria-hidden="true"></i>
            <span class="sm-alerte-admin__libelle"><?= e($libelle) ?></span>
            <span class="sm-alerte-admin__nombre"><?= e($nombre) ?></span>
        </<?= $balise ?>>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <!-- Activité des commandes -->
    <div class="col-12 col-xl-8">
        <section class="sm-panneau">
            <header class="sm-panneau__entete">
                <span class="sm-kpi__icone sm-teinte--or" aria-hidden="true"><i class="bi bi-graph-up"></i></span>
                <div>
                    <h2>Activité des commandes</h2>
                    <p>Commandes passées chaque jour, 30 derniers jours</p>
                </div>
                <span class="sm-panneau__pastille">Total : <?= e(array_sum($serie)) ?></span>
            </header>
            <?= graphique_courbe($serie, 'Commandes par jour sur 30 jours') ?>
        </section>
    </div>

    <!-- Répartition des utilisateurs -->
    <div class="col-12 col-xl-4">
        <section class="sm-panneau">
            <header class="sm-panneau__entete">
                <span class="sm-kpi__icone sm-teinte--violet" aria-hidden="true"><i class="bi bi-pie-chart-fill"></i></span>
                <div>
                    <h2>Répartition des utilisateurs</h2>
                    <p>Comptes par rôle</p>
                </div>
            </header>
            <div class="sm-anneau-bloc">
                <?= graphique_anneau($repartition, 'Répartition des utilisateurs') ?>
                <ul class="sm-legende">
                    <?php foreach ($repartition as [$libelle, $valeur, , $teinte]): ?>
                        <li>
                            <span class="sm-legende__puce sm-legende__puce--<?= e($teinte) ?>"></span>
                            <span><?= e($libelle) ?></span>
                            <strong><?= e($valeur) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>
</div>

<div class="row g-3">
    <!-- Dernières commandes -->
    <div class="col-12 col-xl-7">
        <section class="sm-panneau">
            <header class="sm-panneau__entete">
                <span class="sm-kpi__icone sm-teinte--bleu" aria-hidden="true"><i class="bi bi-bag-fill"></i></span>
                <div><h2>Dernières commandes</h2><p>Les 6 plus récentes</p></div>
                <a class="sm-panneau__lien" href="<?= e(url('admin/commandes.php')) ?>">Tout voir <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </header>
            <?php if ($dernieres_commandes === []): ?>
                <p class="sm-panneau__vide">Aucune commande pour le moment.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table sm-table-admin align-middle mb-0">
                        <thead><tr><th>Numéro</th><th>Client</th><th>Boutique</th><th class="text-end">Total</th><th>Statut</th></tr></thead>
                        <tbody>
                            <?php foreach ($dernieres_commandes as $c): ?>
                                <tr>
                                    <td class="text-nowrap"><a class="fw-semibold" href="<?= e(url('admin/commandes.php?id=' . (int) $c['id'])) ?>"><?= e($c['numero']) ?></a></td>
                                    <td><?= e($c['client_nom']) ?></td>
                                    <td class="text-body-secondary text-nowrap"><?= e($c['boutique_nom']) ?></td>
                                    <td class="text-end fw-semibold text-nowrap"><?= e(format_fcfa((int) $c['total'])) ?></td>
                                    <td><?= badge_statut_commande($c['statut']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Derniers inscrits -->
    <div class="col-12 col-xl-5">
        <section class="sm-panneau">
            <header class="sm-panneau__entete">
                <span class="sm-kpi__icone sm-teinte--vert" aria-hidden="true"><i class="bi bi-person-plus-fill"></i></span>
                <div><h2>Derniers inscrits</h2><p><?= e($chiffres['nouveaux_7_jours']) ?> ces 7 derniers jours</p></div>
                <a class="sm-panneau__lien" href="<?= e(url('admin/utilisateurs.php')) ?>">Tout voir <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </header>
            <ul class="sm-liste-admin">
                <?php foreach ($inscrits as $u): ?>
                    <li>
                        <span class="sm-admin__avatar sm-admin__avatar--petit" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1))) ?></span>
                        <a href="<?= e(url('admin/utilisateur.php?id=' . (int) $u['id'])) ?>">
                            <strong><?= e($u['prenom'] . ' ' . $u['nom']) ?></strong>
                            <span><?= e($u['boutique_nom'] ?? $u['email']) ?></span>
                        </a>
                        <span class="sm-role sm-role--<?= e($u['role']) ?>"><?= e(ucfirst($u['role'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
