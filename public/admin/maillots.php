<?php
/**
 * SAMA MAILLOT — administration : modération des maillots (§23.2).
 *
 * Pas de validation préalable : l'administrateur peut masquer, supprimer (suppression logique,
 * l'historique des commandes est conservé) ou remettre en ligne un maillot qu'il avait masqué.
 * Motif obligatoire ; le vendeur est prévenu ; chaque action est journalisée.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $retour = 'admin/maillots.php' . (is_string($_POST['retour'] ?? null) && preg_match('/^\?[a-z0-9=&_%+-]*$/i', $_POST['retour']) === 1 ? $_POST['retour'] : '');

    if (!verifier_csrf()) {
        message_flash('erreur', 'Votre session a expiré. Merci de réessayer.');
    } elseif (($motif = lire_motif_admin()) === null) {
        message_flash('erreur', 'Indiquez un motif (5 caractères minimum) : il est envoyé au vendeur et enregistré dans le journal.');
    } else {
        $erreur = moderer_maillot($admin, (int) champ_post('maillot_id'), champ_post('action'), $motif);
        message_flash($erreur === null ? 'succes' : 'erreur', $erreur ?? 'Action enregistrée. Le vendeur est prévenu.');
    }
    rediriger($retour);
}

// ---- Filtres ----
$q = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 80)) : '';
$statut = is_string($_GET['statut'] ?? null) && isset(STATUTS_MAILLOT[$_GET['statut']]) ? $_GET['statut'] : '';
$boutiqueId = (int) ($_GET['boutique'] ?? 0);

$conditions = ["m.statut <> 'supprime'"];
$parametres = [];
if ($q !== '') {
    $conditions[] = '(m.titre LIKE :q OR b.nom LIKE :q2)';
    $parametres['q'] = '%' . $q . '%';
    $parametres['q2'] = '%' . $q . '%';
}
if ($statut !== '') {
    $conditions[] = 'm.statut = :statut';
    $parametres['statut'] = $statut;
}
if ($boutiqueId > 0) {
    $conditions[] = 'm.boutique_id = :boutique';
    $parametres['boutique'] = $boutiqueId;
}
$depuis = ' FROM maillots m JOIN boutiques b ON b.id = m.boutique_id WHERE ' . implode(' AND ', $conditions);

$requete = bdd()->prepare('SELECT COUNT(*)' . $depuis);
$requete->execute($parametres);
$total = (int) $requete->fetchColumn();
$pages = max(1, (int) ceil($total / ADMIN_PAR_PAGE));
$page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

$requete = bdd()->prepare(
    'SELECT m.id, m.titre, m.slug, m.prix, m.statut, m.authenticite, m.cree_le, b.nom AS boutique_nom, b.utilisateur_id AS vendeur_id,
            (SELECT i.miniature FROM maillot_images i WHERE i.maillot_id = m.id ORDER BY i.ordre, i.id LIMIT 1) AS miniature'
    . $depuis . ' ORDER BY m.cree_le DESC, m.id DESC LIMIT ' . ADMIN_PAR_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_PAR_PAGE)
);
$requete->execute($parametres);
$maillots = $requete->fetchAll();

$filtres = array_filter(['q' => $q, 'statut' => $statut, 'boutique' => $boutiqueId ?: null]);
$retour_courant = '?' . http_build_query($filtres + ($page > 1 ? ['page' => $page] : []));

$titre_page = 'Maillots';
$admin_actif = 'maillots';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<section>
    <div>
        <h1 class="sm-titre-section mb-3">Maillots <span class="text-body-secondary fw-normal fs-5">(<?= e($total) ?>)</span></h1>

        <form class="sm-filtres-admin mb-3" method="get">
            <label class="visually-hidden" for="q">Rechercher</label>
            <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Titre ou boutique…">
            <label class="visually-hidden" for="statut">Statut</label>
            <select class="form-select" id="statut" name="statut">
                <option value="">Tous les statuts</option>
                <?php foreach (STATUTS_MAILLOT as $cle => [$libelle]): ?>
                    <option value="<?= e($cle) ?>"<?= $statut === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($boutiqueId > 0): ?><input type="hidden" name="boutique" value="<?= e($boutiqueId) ?>"><?php endif; ?>
            <button class="btn btn-or" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Filtrer</button>
            <?php if ($filtres !== []): ?><a class="btn btn-link" href="<?= e(url('admin/maillots.php')) ?>">Réinitialiser</a><?php endif; ?>
        </form>

        <?php if ($maillots === []): ?>
            <div class="sm-etat-vide"><h2 class="h5">Aucun maillot ne correspond.</h2></div>
        <?php else: ?>
            <ul class="sm-liste-commandes">
                <?php foreach ($maillots as $maillot): ?>
                    <li class="sm-ligne-moderation">
                        <span class="sm-carte-commande__image" aria-hidden="true">
                            <?php if ($maillot['miniature']): ?>
                                <img src="<?= e(url_image_maillot($maillot['miniature'])) ?>" alt="" width="400" height="400" loading="lazy">
                            <?php else: ?>
                                <i class="bi bi-image"></i>
                            <?php endif; ?>
                        </span>
                        <span class="sm-carte-commande__infos">
                            <?php if ($maillot['statut'] === 'publie'): ?>
                                <a class="fw-semibold" href="<?= e(url_maillot($maillot)) ?>" target="_blank" rel="noopener"><?= e($maillot['titre']) ?></a>
                            <?php else: ?>
                                <strong><?= e($maillot['titre']) ?></strong>
                            <?php endif; ?>
                            <span class="small text-body-secondary">
                                <a href="<?= e(url('admin/utilisateur.php?id=' . (int) $maillot['vendeur_id'])) ?>"><?= e($maillot['boutique_nom']) ?></a>
                                · <?= e(format_fcfa((int) $maillot['prix'])) ?> · <?= e(LIBELLES_AUTHENTICITE[$maillot['authenticite']] ?? '') ?>
                            </span>
                            <?= badge_statut_maillot($maillot['statut']) ?>
                        </span>
                        <details class="sm-moderation">
                            <summary class="btn btn-sm btn-contour">Modérer</summary>
                            <form method="post" class="sm-moderation__formulaire">
                                <?= champ_csrf() ?>
                                <input type="hidden" name="maillot_id" value="<?= e($maillot['id']) ?>">
                                <input type="hidden" name="retour" value="<?= e($retour_courant) ?>">
                                <label class="form-label small" for="motif-<?= e($maillot['id']) ?>">Motif (envoyé au vendeur)</label>
                                <input class="form-control form-control-sm mb-2" type="text" id="motif-<?= e($maillot['id']) ?>" name="motif"
                                       maxlength="255" required minlength="5" placeholder="Ex. : réplique présentée comme officielle">
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if ($maillot['statut'] === 'masque_admin'): ?>
                                        <button class="btn btn-sm btn-or" type="submit" name="action" value="republier">Remettre en ligne</button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-contour sm-bouton-danger" type="submit" name="action" value="masquer">Masquer</button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-contour sm-bouton-danger" type="submit" name="action" value="supprimer">Supprimer</button>
                                </div>
                            </form>
                        </details>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="mt-3">
                <?= pagination($page, $pages, static fn (int $p): string =>
                    url('admin/maillots.php') . '?' . http_build_query($filtres + ($p > 1 ? ['page' => $p] : []))) ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
