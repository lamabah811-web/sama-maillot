<?php
/**
 * SAMA MAILLOT — administration : messages du formulaire de contact (§7.3).
 * Liste (à traiter / traités / tous), réponse par e-mail ou WhatsApp, marquer comme traité.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifier_csrf()) {
        $id = (int) champ_post('message_id');
        $nouveau = champ_post('action') === 'rouvrir' ? 'nouveau' : 'traite';
        bdd()->prepare('UPDATE messages_contact SET statut = :statut WHERE id = :id')->execute(['statut' => $nouveau, 'id' => $id]);
        journaliser_action('admin_message_' . $nouveau, (int) $admin['id'], 'message', $id);
        message_flash('succes', $nouveau === 'traite' ? 'Message marqué comme traité.' : 'Message remis à traiter.');
    }
    rediriger('admin/messages.php' . (is_string($_POST['retour'] ?? null) && preg_match('/^\?filtre=[a-z]+$/', $_POST['retour']) === 1 ? $_POST['retour'] : ''));
}

$filtre = in_array($_GET['filtre'] ?? '', ['traites', 'tous'], true) ? $_GET['filtre'] : 'a-traiter';
$condition = match ($filtre) {
    'traites' => "WHERE statut = 'traite'",
    'tous' => '',
    default => "WHERE statut = 'nouveau'",
};
$messages = bdd()->query('SELECT * FROM messages_contact ' . $condition . ' ORDER BY cree_le DESC, id DESC LIMIT 100')->fetchAll();
$comptes = bdd()->query("SELECT COALESCE(SUM(statut = 'nouveau'), 0) AS nouveaux, COALESCE(SUM(statut = 'traite'), 0) AS traites, COUNT(*) AS tous FROM messages_contact")->fetch();

$sujets = ['commande' => 'Une commande', 'vendeur' => 'Vendre sur SAMA MAILLOT', 'compte' => 'Mon compte',
    'signalement' => 'Signalement', 'partenariat' => 'Partenariat ou presse', 'autre' => 'Autre question'];

$titre_page = 'Messages';
$admin_actif = 'messages';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>Messages de contact</h1>
        <p>Messages envoyés depuis la page Contact du site.</p>
    </div>
</div>

<nav class="sm-onglets mb-3" aria-label="Filtrer les messages">
    <?php foreach (['a-traiter' => ['À traiter', $comptes['nouveaux']], 'traites' => ['Traités', $comptes['traites']], 'tous' => ['Tous', $comptes['tous']]] as $cle => [$libelle, $nombre]): ?>
        <a class="sm-onglet" href="<?= e(url('admin/messages.php' . ($cle !== 'a-traiter' ? '?filtre=' . $cle : ''))) ?>"
           <?= $cle === $filtre ? 'aria-current="page"' : '' ?>><?= e($libelle) ?> <span class="sm-onglet__nombre"><?= e($nombre) ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if ($messages === []): ?>
    <div class="sm-panneau"><p class="sm-panneau__vide"><i class="bi bi-inbox" aria-hidden="true"></i> Aucun message ici.</p></div>
<?php else: ?>
    <div class="d-grid gap-3">
        <?php foreach ($messages as $m): ?>
            <article class="sm-panneau sm-message-admin<?= $m['statut'] === 'nouveau' ? ' sm-message-admin--nouveau' : '' ?>">
                <header class="sm-panneau__entete">
                    <span class="sm-admin__avatar sm-admin__avatar--petit" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($m['nom'], 0, 2))) ?></span>
                    <div>
                        <h2><?= e($m['nom']) ?></h2>
                        <p>
                            <?= e($sujets[$m['sujet']] ?? $m['sujet']) ?>
                            <?= $m['numero_commande'] !== null ? ' · ' . e($m['numero_commande']) : '' ?>
                            · <?= e(date_relative($m['cree_le'])) ?>
                        </p>
                    </div>
                    <?= $m['statut'] === 'nouveau' ? '<span class="sm-statut sm-statut--attention">À traiter</span>' : '<span class="sm-statut sm-statut--succes">Traité</span>' ?>
                </header>
                <p class="sm-message-admin__texte"><?= nl2br(e($m['message'])) ?></p>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <a class="btn btn-sm btn-or" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re : votre message à SAMA MAILLOT')) ?>">
                        <i class="bi bi-reply" aria-hidden="true"></i> Répondre à <?= e($m['email']) ?>
                    </a>
                    <?php if ($m['telephone'] !== null): ?>
                        <a class="btn btn-sm btn-contour" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $m['telephone'])) ?>" target="_blank" rel="noopener nofollow">
                            <i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e(formater_telephone($m['telephone'])) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($m['utilisateur_id'] !== null): ?>
                        <a class="btn btn-sm btn-contour" href="<?= e(url('admin/utilisateur.php?id=' . (int) $m['utilisateur_id'])) ?>"><i class="bi bi-person" aria-hidden="true"></i> Voir le compte</a>
                    <?php endif; ?>
                    <form method="post" class="ms-auto">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="message_id" value="<?= e($m['id']) ?>">
                        <input type="hidden" name="retour" value="<?= e($filtre !== 'a-traiter' ? '?filtre=' . $filtre : '') ?>">
                        <?php if ($m['statut'] === 'nouveau'): ?>
                            <button class="btn btn-sm btn-contour" type="submit" name="action" value="traiter"><i class="bi bi-check2" aria-hidden="true"></i> Marquer comme traité</button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-link" type="submit" name="action" value="rouvrir">Remettre à traiter</button>
                        <?php endif; ?>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
