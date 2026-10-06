<?php
/**
 * SAMA MAILLOT — administration : journal d'audit (§23.2, §31).
 * Consultation et filtres : action, auteur, type d'objet, période. Lecture seule.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

$filtres = [];
foreach (['action', 'auteur', 'objet', 'du', 'au'] as $cle) {
    $filtres[$cle] = is_string($_GET[$cle] ?? null) ? trim(mb_substr($_GET[$cle], 0, 80)) : '';
}
$resultat = rechercher_journal($filtres, (int) ($_GET['page'] ?? 1));

$familles = [
    '' => 'Toutes les actions', 'admin_' => 'Actions d\'administration', 'commande_' => 'Commandes',
    'inscription' => 'Inscriptions', 'telephone' => 'Téléphones vérifiés', 'code_' => 'Codes de livraison',
];

$titre_page = 'Journal d\'audit';
$admin_actif = 'journal';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<section>
    <div>
        <h1 class="sm-titre-section mb-3">Journal d'audit <span class="text-body-secondary fw-normal fs-5">(<?= e($resultat['total']) ?>)</span></h1>

        <form class="sm-filtres-admin mb-3" method="get">
            <label class="visually-hidden" for="action">Action</label>
            <select class="form-select" id="action" name="action">
                <?php foreach ($familles as $cle => $libelle): ?>
                    <option value="<?= e($cle) ?>"<?= $filtres['action'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="visually-hidden" for="auteur">Auteur</label>
            <input class="form-control" type="search" id="auteur" name="auteur" value="<?= e($filtres['auteur']) ?>" placeholder="Auteur (e-mail ou nom)">
            <label class="visually-hidden" for="objet">Objet</label>
            <select class="form-select" id="objet" name="objet">
                <option value="">Tous les objets</option>
                <?php foreach (['utilisateur' => 'Utilisateurs', 'boutique' => 'Boutiques', 'maillot' => 'Maillots', 'commande' => 'Commandes'] as $cle => $libelle): ?>
                    <option value="<?= e($cle) ?>"<?= $filtres['objet'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="visually-hidden" for="du">Du</label>
            <input class="form-control" type="date" id="du" name="du" value="<?= e($filtres['du']) ?>" title="Du">
            <label class="visually-hidden" for="au">Au</label>
            <input class="form-control" type="date" id="au" name="au" value="<?= e($filtres['au']) ?>" title="Au">
            <button class="btn btn-or" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrer</button>
        </form>

        <?php if ($resultat['lignes'] === []): ?>
            <div class="sm-etat-vide"><h2 class="h5">Aucune entrée ne correspond.</h2></div>
        <?php else: ?>
            <div class="sm-tableau-admin">
                <table class="table align-middle mb-0 small">
                    <thead><tr><th>Date</th><th>Action</th><th>Auteur</th><th>Objet</th><th>Détail</th><th>IP</th></tr></thead>
                    <tbody>
                        <?php foreach ($resultat['lignes'] as $ligne): ?>
                            <tr>
                                <td class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($ligne['cree_le']))) ?></td>
                                <td><?= e(libelle_action_journal($ligne['action'])) ?></td>
                                <td>
                                    <?php if ($ligne['auteur_email'] !== null): ?>
                                        <a href="<?= e(url('admin/utilisateur.php?id=' . (int) $ligne['utilisateur_id'])) ?>"><?= e($ligne['auteur_prenom'] . ' ' . $ligne['auteur_nom']) ?></a>
                                    <?php else: ?>
                                        <span class="text-body-secondary">Système</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($ligne['objet_type'] === 'commande'): ?>
                                        <a href="<?= e(url('admin/commandes.php?id=' . (int) $ligne['objet_id'])) ?>">Commande n° <?= e($ligne['objet_id']) ?></a>
                                    <?php elseif ($ligne['objet_type'] === 'utilisateur'): ?>
                                        <a href="<?= e(url('admin/utilisateur.php?id=' . (int) $ligne['objet_id'])) ?>">Utilisateur n° <?= e($ligne['objet_id']) ?></a>
                                    <?php elseif ($ligne['objet_type'] !== null): ?>
                                        <?= e(ucfirst($ligne['objet_type']) . ' n° ' . $ligne['objet_id']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $ligne['nouvelle_valeur'] !== null ? e($ligne['nouvelle_valeur']) : '' ?></td>
                                <td class="text-body-secondary"><?= e($ligne['adresse_ip']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <?= pagination($resultat['page'], $resultat['pages'], static fn (int $p): string =>
                    url('admin/journal.php') . '?' . http_build_query(array_filter($filtres) + ($p > 1 ? ['page' => $p] : []))) ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
