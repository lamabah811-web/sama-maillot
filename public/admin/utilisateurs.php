<?php
/**
 * SAMA MAILLOT — administration : utilisateurs et vendeurs (§23.2).
 * Recherche (nom, e-mail, téléphone, boutique) et filtres (rôle, statut, statut de boutique).
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

$filtres = [];
foreach (['q', 'role', 'statut', 'boutique'] as $cle) {
    $filtres[$cle] = is_string($_GET[$cle] ?? null) ? trim(mb_substr($_GET[$cle], 0, 80)) : '';
}
$resultat = rechercher_utilisateurs($filtres, (int) ($_GET['page'] ?? 1));

$titre_page = 'Utilisateurs';
$admin_actif = $filtres['role'] === 'vendeur' ? 'vendeurs' : 'utilisateurs';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<section>
    <div>
        <h1 class="sm-titre-section mb-3">Utilisateurs <span class="text-body-secondary fw-normal fs-5">(<?= e($resultat['total']) ?>)</span></h1>

        <form class="sm-filtres-admin mb-3" method="get">
            <label class="visually-hidden" for="q">Rechercher</label>
            <input class="form-control" type="search" id="q" name="q" value="<?= e($filtres['q']) ?>" placeholder="Nom, e-mail, téléphone, boutique…">
            <label class="visually-hidden" for="role">Rôle</label>
            <select class="form-select" id="role" name="role">
                <option value="">Tous les rôles</option>
                <?php foreach (['client' => 'Clients', 'vendeur' => 'Vendeurs', 'admin' => 'Administrateurs'] as $cle => $libelle): ?>
                    <option value="<?= e($cle) ?>"<?= $filtres['role'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="visually-hidden" for="statut">Statut</label>
            <select class="form-select" id="statut" name="statut">
                <option value="">Tous les statuts</option>
                <?php foreach (STATUTS_UTILISATEUR as $cle => [$libelle]): ?>
                    <option value="<?= e($cle) ?>"<?= $filtres['statut'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
                <option value="inactifs"<?= $filtres['statut'] === 'inactifs' ? ' selected' : '' ?>>Désactivés ou suspendus</option>
            </select>
            <label class="visually-hidden" for="boutique">Boutique</label>
            <select class="form-select" id="boutique" name="boutique">
                <option value="">Toutes les boutiques</option>
                <?php foreach (STATUTS_BOUTIQUE as $cle => [$libelle]): ?>
                    <option value="<?= e($cle) ?>"<?= $filtres['boutique'] === $cle ? ' selected' : '' ?>>Boutique : <?= e(mb_strtolower($libelle)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-or" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Filtrer</button>
        </form>

        <?php if ($resultat['utilisateurs'] === []): ?>
            <div class="sm-etat-vide"><h2 class="h5">Aucun utilisateur ne correspond.</h2></div>
        <?php else: ?>
            <div class="sm-tableau-admin">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Nom</th><th>Rôle</th><th>Contact</th><th>Statut</th><th>Inscrit le</th><th><span class="visually-hidden">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultat['utilisateurs'] as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['prenom'] . ' ' . $u['nom']) ?></strong>
                                    <?php if ($u['boutique_nom'] !== null): ?>
                                        <span class="d-block small text-body-secondary"><i class="bi bi-shop" aria-hidden="true"></i> <?= e($u['boutique_nom']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(ucfirst($u['role'])) ?><?php if ((int) $u['est_invite'] === 1): ?> <span class="badge text-bg-light border">Sans compte</span><?php endif; ?></td>
                                <td class="small">
                                    <?= email_technique($u['email']) ? '<span class="text-body-secondary">Pas d\'e-mail</span>' : e($u['email']) ?>
                                    <?php if ($u['telephone'] !== null): ?><span class="d-block text-body-secondary"><?= e(formater_telephone($u['telephone'])) ?></span><?php endif; ?>
                                </td>
                                <td>
                                    <?= badge_statut(STATUTS_UTILISATEUR, $u['statut']) ?>
                                    <?php if ($u['boutique_statut'] === 'suspendue'): ?>
                                        <span class="d-block mt-1"><?= badge_statut(STATUTS_BOUTIQUE, 'suspendue') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-nowrap"><?= e(date('d/m/Y', strtotime($u['cree_le']))) ?></td>
                                <td class="text-end"><a class="btn btn-sm btn-contour" href="<?= e(url('admin/utilisateur.php?id=' . (int) $u['id'])) ?>">Gérer</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <?= pagination($resultat['page'], $resultat['pages'], static fn (int $p): string =>
                    url('admin/utilisateurs.php') . '?' . http_build_query(array_filter($filtres + ['page' => $p > 1 ? $p : null]))) ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
