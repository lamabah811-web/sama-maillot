<?php
/**
 * SAMA MAILLOT — administration : modération des avis (§20, §23.2).
 * Masquer un avis abusif (insultes, faux avis…) ou le réafficher ; motif journalisé.
 * Un avis masqué ne compte plus dans la note du vendeur.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        message_flash('erreur', 'Votre session a expiré. Merci de réessayer.');
    } elseif (($motif = lire_motif_admin()) === null) {
        message_flash('erreur', 'Indiquez un motif (5 caractères minimum) : il est enregistré dans le journal.');
    } else {
        $erreur = moderer_avis($admin, (int) champ_post('avis_id'), champ_post('action') === 'reafficher', $motif);
        message_flash($erreur === null ? 'succes' : 'erreur', $erreur ?? 'Avis mis à jour.');
    }
    rediriger('admin/avis.php' . (($_POST['retour'] ?? '') === 'masques' ? '?filtre=masques' : ''));
}

$filtre = ($_GET['filtre'] ?? '') === 'masques' ? 'masques' : 'tous';
$liste_avis = bdd()->query(
    'SELECT a.*, b.nom AS boutique_nom, b.utilisateur_id AS vendeur_id, u.prenom, u.nom, c.numero
     FROM avis a JOIN boutiques b ON b.id = a.boutique_id JOIN utilisateurs u ON u.id = a.client_id JOIN commandes c ON c.id = a.commande_id
     WHERE ' . ($filtre === 'masques' ? 'a.visible = 0' : '1') . ' ORDER BY a.cree_le DESC LIMIT 200'
)->fetchAll();
$comptes = bdd()->query('SELECT COUNT(*) AS tous, COALESCE(SUM(visible = 0), 0) AS masques FROM avis')->fetch();

$titre_page = 'Avis';
$admin_actif = 'avis';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>Avis</h1>
        <p>Les avis sont publics sur la page de chaque boutique. Masquez un avis abusif : il ne compte plus dans la note du vendeur.</p>
    </div>
</div>

<nav class="sm-onglets mb-3" aria-label="Filtrer les avis">
    <a class="sm-onglet" href="<?= e(url('admin/avis.php')) ?>" <?= $filtre === 'tous' ? 'aria-current="page"' : '' ?>>Tous <span class="sm-onglet__nombre"><?= e($comptes['tous']) ?></span></a>
    <a class="sm-onglet" href="<?= e(url('admin/avis.php?filtre=masques')) ?>" <?= $filtre === 'masques' ? 'aria-current="page"' : '' ?>>Masqués <span class="sm-onglet__nombre"><?= e($comptes['masques']) ?></span></a>
</nav>

<?php if ($liste_avis === []): ?>
    <div class="sm-panneau"><p class="sm-panneau__vide"><i class="bi bi-star" aria-hidden="true"></i> Aucun avis ici.</p></div>
<?php else: ?>
    <div class="d-grid gap-3">
        <?php foreach ($liste_avis as $a): ?>
            <article class="sm-panneau">
                <header class="sm-panneau__entete">
                    <div>
                        <h2><?= etoiles((int) $a['note']) ?></h2>
                        <p>
                            <a href="<?= e(url('admin/utilisateur.php?id=' . (int) $a['client_id'])) ?>"><?= e($a['prenom'] . ' ' . $a['nom']) ?></a>
                            → <a href="<?= e(url('admin/utilisateur.php?id=' . (int) $a['vendeur_id'])) ?>"><?= e($a['boutique_nom']) ?></a>
                            · <?= e($a['numero']) ?> · <?= e(date_relative($a['cree_le'])) ?>
                        </p>
                    </div>
                    <?php if ((int) $a['visible'] === 0): ?>
                        <span class="sm-statut sm-statut--erreur">Masqué</span>
                    <?php endif; ?>
                </header>
                <?php if ($a['commentaire'] !== null): ?>
                    <p class="mb-2 sm-texte-multiligne"><?= e($a['commentaire']) ?></p>
                <?php endif; ?>
                <?php if ($a['reponse'] !== null): ?>
                    <div class="sm-avis__reponse mb-2"><strong class="small">Réponse du vendeur</strong><p class="mb-0 small sm-texte-multiligne"><?= e($a['reponse']) ?></p></div>
                <?php endif; ?>
                <?php if ((int) $a['visible'] === 0 && $a['motif_masquage'] !== null): ?>
                    <p class="small text-body-secondary">Motif du masquage : <?= e($a['motif_masquage']) ?></p>
                <?php endif; ?>
                <form method="post" class="d-flex flex-wrap gap-2">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="avis_id" value="<?= e($a['id']) ?>">
                    <input type="hidden" name="retour" value="<?= e($filtre) ?>">
                    <label class="visually-hidden" for="motif-<?= e($a['id']) ?>">Motif</label>
                    <input class="form-control form-control-sm flex-grow-1 w-auto" type="text" id="motif-<?= e($a['id']) ?>" name="motif"
                           maxlength="255" required minlength="5" placeholder="Motif (journalisé)">
                    <?php if ((int) $a['visible'] === 1): ?>
                        <button class="btn btn-sm btn-contour sm-bouton-danger" type="submit" name="action" value="masquer">Masquer</button>
                    <?php else: ?>
                        <button class="btn btn-sm btn-contour" type="submit" name="action" value="reafficher">Réafficher</button>
                    <?php endif; ?>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
