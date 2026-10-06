<?php
/**
 * SAMA MAILLOT — administration : signalements et suspensions (§21, §23.2).
 *
 * - Boutiques suspendues (automatiquement ou par l'admin), avec la contestation du vendeur :
 *   lever la suspension (motif journalisé).
 * - Signalements : retenir ou classer (infondé : ne compte plus pour la suspension automatique).
 *   Un signalement de maillot renvoie vers la modération du maillot.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $retour = 'admin/signalements.php' . (in_array($_POST['retour'] ?? '', ['?filtre=retenu', '?filtre=classe'], true) ? $_POST['retour'] : '');

    if (!verifier_csrf()) {
        message_flash('erreur', 'Votre session a expiré. Merci de réessayer.');
    } elseif (($motif = lire_motif_admin()) === null) {
        message_flash('erreur', 'Indiquez un motif (5 caractères minimum) : il est enregistré dans le journal.');
    } elseif (champ_post('action') === 'lever') {
        $erreur = changer_statut_boutique($admin, (int) champ_post('boutique_id'), false, $motif);
        message_flash($erreur === null ? 'succes' : 'erreur', $erreur ?? 'Suspension levée. Le vendeur est prévenu.');
    } else {
        $erreur = traiter_signalement($admin, (int) champ_post('signalement_id'), champ_post('action'), $motif);
        message_flash($erreur === null ? 'succes' : 'erreur', $erreur ?? 'Décision enregistrée.');
    }
    rediriger($retour);
}

$filtre = in_array($_GET['filtre'] ?? '', ['retenu', 'classe'], true) ? $_GET['filtre'] : 'ouvert';

$requete = bdd()->prepare(
    "SELECT s.*, b.nom AS boutique_nom, b.utilisateur_id AS vendeur_id, b.statut AS boutique_statut,
            m.titre AS maillot_titre, m.slug AS maillot_slug,
            u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.email AS auteur_email
     FROM signalements s
     JOIN boutiques b ON b.id = s.boutique_id
     JOIN utilisateurs u ON u.id = s.auteur_id
     LEFT JOIN maillots m ON m.id = s.maillot_id
     WHERE s.statut = :statut ORDER BY s.cree_le DESC, s.id DESC LIMIT 100"
);
$requete->execute(['statut' => $filtre]);
$signalements = $requete->fetchAll();

$comptes = bdd()->query('SELECT statut, COUNT(*) FROM signalements GROUP BY statut')->fetchAll(PDO::FETCH_KEY_PAIR);
$suspendues = bdd()->query(
    "SELECT b.*, u.prenom, u.nom AS vendeur_nom FROM boutiques b JOIN utilisateurs u ON u.id = b.utilisateur_id
     WHERE b.statut = 'suspendue' AND b.supprime_le IS NULL ORDER BY b.contestation_le IS NULL, b.contestation_le DESC"
)->fetchAll();
$seuil = max(1, (int) parametre('signalements_avant_suspension', '3'));

$titre_page = 'Signalements';
$admin_actif = 'signalements';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>Signalements</h1>
        <p>Seuls les clients ayant commandé chez un vendeur peuvent le signaler. Suspension automatique à <?= e($seuil) ?> clients différents en <?= e(parametre('signalements_periode_jours', '90')) ?> jours.</p>
    </div>
</div>

<?php if ($suspendues !== []): ?>
    <section class="sm-panneau mb-4">
        <header class="sm-panneau__entete">
            <span class="sm-kpi__icone sm-teinte--rouge" aria-hidden="true"><i class="bi bi-shop"></i></span>
            <div><h2>Boutiques suspendues</h2><p>Maillots masqués, plus de nouvelles commandes</p></div>
            <span class="sm-panneau__pastille"><?= e(count($suspendues)) ?></span>
        </header>
        <div class="d-grid gap-3">
            <?php foreach ($suspendues as $b): ?>
                <article class="sm-suspendue">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <a class="fw-bold" href="<?= e(url('admin/utilisateur.php?id=' . (int) $b['utilisateur_id'])) ?>"><?= e($b['nom']) ?></a>
                        <span class="small text-body-secondary">· <?= e($b['prenom'] . ' ' . $b['vendeur_nom']) ?></span>
                        <?= $b['suspension_auto_le'] !== null
                            ? '<span class="sm-statut sm-statut--erreur">Automatique · ' . e(signaleurs_distincts((int) $b['id'])) . ' clients</span>'
                            : '<span class="sm-statut sm-statut--attention">Par l\'administration</span>' ?>
                    </div>
                    <?php if ($b['contestation'] !== null): ?>
                        <blockquote class="sm-contestation">
                            <strong><i class="bi bi-chat-left-quote" aria-hidden="true"></i> Contestation du vendeur</strong>
                            <span class="small text-body-secondary">(<?= e(date_commande($b['contestation_le'])) ?>)</span>
                            <p class="mb-0"><?= nl2br(e($b['contestation'])) ?></p>
                        </blockquote>
                    <?php else: ?>
                        <p class="small text-body-secondary mb-2">Aucune contestation reçue pour le moment.</p>
                    <?php endif; ?>
                    <form method="post" class="d-flex flex-wrap gap-2">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="action" value="lever">
                        <input type="hidden" name="boutique_id" value="<?= e($b['id']) ?>">
                        <label class="visually-hidden" for="motif-lever-<?= e($b['id']) ?>">Motif</label>
                        <input class="form-control form-control-sm flex-grow-1 w-auto" type="text" id="motif-lever-<?= e($b['id']) ?>" name="motif"
                               maxlength="255" required minlength="5" placeholder="Motif de la levée (journalisé)">
                        <button class="btn btn-sm btn-or" type="submit">Lever la suspension</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<nav class="sm-onglets mb-3" aria-label="Filtrer les signalements">
    <?php foreach (['ouvert' => 'À traiter', 'retenu' => 'Retenus', 'classe' => 'Classés'] as $cle => $libelle): ?>
        <a class="sm-onglet" href="<?= e(url('admin/signalements.php' . ($cle !== 'ouvert' ? '?filtre=' . $cle : ''))) ?>"
           <?= $cle === $filtre ? 'aria-current="page"' : '' ?>><?= e($libelle) ?> <span class="sm-onglet__nombre"><?= e($comptes[$cle] ?? 0) ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if ($signalements === []): ?>
    <div class="sm-panneau"><p class="sm-panneau__vide"><i class="bi bi-shield-check" aria-hidden="true"></i> Aucun signalement ici.</p></div>
<?php else: ?>
    <div class="d-grid gap-3">
        <?php foreach ($signalements as $s): ?>
            <?php $motifs = $s['type'] === 'vendeur' ? MOTIFS_SIGNALEMENT_VENDEUR : MOTIFS_SIGNALEMENT_MAILLOT; ?>
            <article class="sm-panneau">
                <header class="sm-panneau__entete">
                    <span class="sm-kpi__icone <?= $s['type'] === 'vendeur' ? 'sm-teinte--violet' : 'sm-teinte--or' ?>" aria-hidden="true">
                        <i class="bi <?= $s['type'] === 'vendeur' ? 'bi-shop' : 'bi-box-seam-fill' ?>"></i>
                    </span>
                    <div>
                        <h2><?= e($motifs[$s['motif']] ?? $s['motif']) ?></h2>
                        <p>
                            <?= $s['type'] === 'vendeur' ? 'Vendeur' : 'Maillot' ?> :
                            <?php if ($s['type'] === 'maillot' && $s['maillot_titre'] !== null): ?>
                                « <?= e($s['maillot_titre']) ?> » ·
                            <?php endif; ?>
                            <a href="<?= e(url('admin/utilisateur.php?id=' . (int) $s['vendeur_id'])) ?>"><?= e($s['boutique_nom']) ?></a>
                            · <?= e(date_relative($s['cree_le'])) ?>
                        </p>
                    </div>
                    <?= badge_statut(STATUTS_SIGNALEMENT, $s['statut']) ?>
                </header>

                <p class="mb-2"><?= nl2br(e($s['description'])) ?></p>
                <p class="small text-body-secondary mb-3">
                    Signalé par <a href="<?= e(url('admin/utilisateur.php?id=' . (int) $s['auteur_id'])) ?>"><?= e($s['auteur_prenom'] . ' ' . $s['auteur_nom']) ?></a>
                    <?php if ($s['type'] === 'vendeur'): ?>
                        · <?= e(signaleurs_distincts((int) $s['boutique_id'])) ?> / <?= e($seuil) ?> clients différents ont signalé ce vendeur
                    <?php endif; ?>
                    <?php if ($s['decision'] !== null): ?>
                        <br>Décision : <?= e($s['decision']) ?>
                    <?php endif; ?>
                </p>

                <?php if ($s['statut'] === 'ouvert'): ?>
                    <form method="post" class="d-flex flex-wrap gap-2">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="signalement_id" value="<?= e($s['id']) ?>">
                        <label class="visually-hidden" for="motif-<?= e($s['id']) ?>">Motif de la décision</label>
                        <input class="form-control form-control-sm flex-grow-1 w-auto" type="text" id="motif-<?= e($s['id']) ?>" name="motif"
                               maxlength="255" required minlength="5" placeholder="Motif de la décision (journalisé)">
                        <button class="btn btn-sm btn-contour sm-bouton-danger" type="submit" name="action" value="retenu">Retenir</button>
                        <button class="btn btn-sm btn-contour" type="submit" name="action" value="classe">Classer (infondé)</button>
                        <?php if ($s['type'] === 'maillot' && $s['maillot_titre'] !== null): ?>
                            <a class="btn btn-sm btn-or" href="<?= e(url('admin/maillots.php?q=' . rawurlencode($s['maillot_titre']))) ?>">Modérer le maillot</a>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
