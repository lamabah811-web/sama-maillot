<?php
/**
 * SAMA MAILLOT — administration : commandes (§23.2) — consultation complète et historique.
 *   admin/commandes.php            → liste filtrable (numéro, client, boutique, statut)
 *   admin/commandes.php?id=12      → détail : lignes, adresse, historique des statuts
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();
traiter_commandes_echues();

$commande = null;
if (isset($_GET['id'])) {
    $requete = bdd()->prepare(SQL_COMMANDE . ' WHERE c.id = :id');
    $requete->execute(['id' => (int) $_GET['id']]);
    $commande = $requete->fetch() ?: afficher_erreur(404);
}

if ($commande === null) {
    $q = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 80)) : '';
    $statut = is_string($_GET['statut'] ?? null) && isset(STATUTS_COMMANDE[$_GET['statut']]) ? $_GET['statut'] : '';

    $conditions = ['1 = 1'];
    $parametres = [];
    if ($q !== '') {
        $conditions[] = '(c.numero LIKE :q OR c.client_nom LIKE :q2 OR b.nom LIKE :q3 OR c.telephone_client LIKE :q4)';
        foreach (['q', 'q2', 'q3', 'q4'] as $cle) {
            $parametres[$cle] = '%' . $q . '%';
        }
    }
    if ($statut !== '') {
        $conditions[] = 'c.statut = :statut';
        $parametres['statut'] = $statut;
    }
    $depuis = ' FROM commandes c JOIN boutiques b ON b.id = c.boutique_id WHERE ' . implode(' AND ', $conditions);

    $requete = bdd()->prepare('SELECT COUNT(*)' . $depuis);
    $requete->execute($parametres);
    $total = (int) $requete->fetchColumn();
    $pages = max(1, (int) ceil($total / ADMIN_PAR_PAGE));
    $page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

    $requete = bdd()->prepare('SELECT c.*, b.nom AS boutique_nom' . $depuis
        . ' ORDER BY c.cree_le DESC, c.id DESC LIMIT ' . ADMIN_PAR_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_PAR_PAGE));
    $requete->execute($parametres);
    $commandes = $requete->fetchAll();
    $filtres = array_filter(['q' => $q, 'statut' => $statut]);
} else {
    $lignes = lignes_de_la_commande((int) $commande['id']);
    $historique = historique_de_la_commande((int) $commande['id']);
}

$titre_page = $commande !== null ? 'Commande ' . $commande['numero'] : 'Commandes';
$admin_actif = 'commandes';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<section>
    <div>

        <?php if ($commande === null): ?>
            <h1 class="sm-titre-section mb-3">Commandes <span class="text-body-secondary fw-normal fs-5">(<?= e($total) ?>)</span></h1>

            <form class="sm-filtres-admin mb-3" method="get">
                <label class="visually-hidden" for="q">Rechercher</label>
                <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Numéro, client, boutique, téléphone…">
                <label class="visually-hidden" for="statut">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <option value="">Tous les statuts</option>
                    <?php foreach (STATUTS_COMMANDE as $cle => [$libelle]): ?>
                        <option value="<?= e($cle) ?>"<?= $statut === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-or" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Filtrer</button>
            </form>

            <?php if ($commandes === []): ?>
                <div class="sm-etat-vide"><h2 class="h5">Aucune commande ne correspond.</h2></div>
            <?php else: ?>
                <div class="sm-tableau-admin">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Numéro</th><th>Client</th><th>Boutique</th><th>Total</th><th>Statut</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($commandes as $c): ?>
                                <tr>
                                    <td><a class="fw-semibold" href="<?= e(url('admin/commandes.php?id=' . (int) $c['id'])) ?>"><?= e($c['numero']) ?></a></td>
                                    <td class="small"><a href="<?= e(url('admin/utilisateur.php?id=' . (int) $c['client_id'])) ?>"><?= e($c['client_nom']) ?></a></td>
                                    <td class="small"><?= e($c['boutique_nom']) ?></td>
                                    <td class="text-nowrap fw-semibold"><?= e(format_fcfa((int) $c['total'])) ?></td>
                                    <td><?= badge_statut_commande($c['statut']) ?></td>
                                    <td class="small text-nowrap"><?= e(date_commande($c['cree_le'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <?= pagination($page, $pages, static fn (int $p): string =>
                        url('admin/commandes.php') . '?' . http_build_query($filtres + ($p > 1 ? ['page' => $p] : []))) ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                <a class="btn btn-sm btn-contour" href="<?= e(url('admin/commandes.php')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Commandes</a>
                <h1 class="sm-titre-section mb-0 mx-2"><?= e($commande['numero']) ?></h1>
                <?= badge_statut_commande($commande['statut']) ?>
            </div>

            <div class="row g-4">
                <div class="col-12 col-lg-7">
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold">Articles</h2>
                        <ul class="sm-recap-articles">
                            <?php foreach ($lignes as $ligne): ?>
                                <li>
                                    <span><?= e($ligne['quantite']) ?> × <?= e($ligne['titre']) ?>
                                        <span class="small text-body-secondary d-block">Taille <?= e($ligne['taille']) ?><?= libelle_flocage($ligne) !== '' ? ' · Flocage ' . e(libelle_flocage($ligne)) : '' ?></span>
                                    </span>
                                    <strong><?= e(format_fcfa((int) $ligne['total_ligne'])) ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <dl class="sm-recap__lignes mb-0">
                            <dt>Articles</dt><dd><?= e(format_fcfa((int) $commande['sous_total'])) ?></dd>
                            <dt>Livraison</dt><dd><?= (int) $commande['frais_a_convenir'] === 1 ? 'À convenir' : e(format_fcfa((int) $commande['frais_livraison'])) ?></dd>
                            <dt class="sm-recap__total">Total</dt><dd class="sm-recap__total"><?= e(format_fcfa((int) $commande['total'])) ?></dd>
                        </dl>
                    </div>
                    <div class="sm-tuile">
                        <h2 class="h6 fw-bold">Historique des statuts</h2>
                        <ol class="sm-suivi">
                            <?php foreach ($historique as $etape): ?>
                                <li>
                                    <strong><?= e(STATUTS_COMMANDE[$etape['nouveau_statut']][0] ?? $etape['nouveau_statut']) ?></strong>
                                    <span class="small text-body-secondary"><?= e(date_commande($etape['cree_le'])) ?> · <?= e($etape['auteur_role']) ?></span>
                                    <?php if ($etape['motif'] !== null): ?><span class="small d-block"><?= e($etape['motif']) ?></span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold">Client</h2>
                        <p class="mb-1"><a href="<?= e(url('admin/utilisateur.php?id=' . (int) $commande['client_id'])) ?>"><?= e($commande['client_nom']) ?></a></p>
                        <p class="small mb-1"><?= e(formater_telephone($commande['telephone_client'])) ?></p>
                        <p class="small mb-0">
                            <?= $commande['mode_livraison'] === 'retrait' ? 'Retrait sur place' : e($commande['adresse_quartier'] . ', ' . $commande['adresse_ville'] . ' (' . $commande['zone_nom'] . ')') ?>
                        </p>
                    </div>
                    <div class="sm-tuile">
                        <h2 class="h6 fw-bold">Boutique</h2>
                        <p class="mb-1"><a href="<?= e(url('admin/utilisateur.php?id=' . (int) $commande['vendeur_id'])) ?>"><?= e($commande['boutique_nom']) ?></a></p>
                        <p class="small mb-0">
                            <?= $commande['origine'] === 'whatsapp' ? 'Commande via WhatsApp' : 'Commande sur le site' ?> ·
                            paiement <?= $commande['mode_paiement'] === 'livraison' ? 'à la livraison' : 'en ligne' ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
