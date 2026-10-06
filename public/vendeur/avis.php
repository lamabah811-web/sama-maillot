<?php
/**
 * SAMA MAILLOT — espace vendeur : avis des clients (§20).
 * Le vendeur publie une réponse publique par avis (modifiable). Les avis masqués par
 * l'administration restent visibles ici, signalés comme tels.
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();
$boutiqueId = (int) $boutique['id'];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $avisId = (int) ($_POST['avis'] ?? 0);
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif (($refus = repondre_avis($boutiqueId, $avisId, (string) ($_POST['reponse'] ?? ''))) !== null) {
        $erreurs['general'] = $refus;
    } else {
        message_flash('succes', 'Votre réponse est publiée sous l\'avis.');
        rediriger('vendeur/avis.php#avis-' . $avisId);
    }
}

$requete = bdd()->prepare(
    'SELECT a.*, u.prenom, LEFT(u.nom, 1) AS initiale, c.numero
     FROM avis a JOIN utilisateurs u ON u.id = a.client_id JOIN commandes c ON c.id = a.commande_id
     WHERE a.boutique_id = :id ORDER BY a.cree_le DESC LIMIT 200'
);
$requete->execute(['id' => $boutiqueId]);
$liste_avis = $requete->fetchAll();
$note = note_vendeur($boutiqueId);

$titre_page = 'Avis des clients';
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <?= menu_vendeur('avis') ?>

        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <h1 class="sm-titre-section mb-0">Avis des clients</h1>
            <span class="sm-badge-nouveau"><?= e($note['libelle']) ?></span>
        </div>

        <?= alerte_formulaire($erreurs) ?>

        <?php if ($liste_avis === []): ?>
            <div class="sm-etat-vide">
                <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-star"></i></div>
                <h3>Pas encore d'avis</h3>
                <p class="mb-0">Vos clients sont invités à vous noter quand ils confirment la réception d'une commande.</p>
            </div>
        <?php else: ?>
            <ul class="sm-liste-avis">
                <?php foreach ($liste_avis as $avis): ?>
                    <li class="sm-tuile mb-3" id="avis-<?= e($avis['id']) ?>">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <?= etoiles((int) $avis['note']) ?>
                            <strong><?= e($avis['prenom'] . ' ' . $avis['initiale'] . '.') ?></strong>
                            <span class="small text-body-secondary">· <?= e($avis['numero']) ?> · <?= e(date_relative($avis['cree_le'])) ?></span>
                            <?php if ((int) $avis['visible'] === 0): ?>
                                <span class="sm-statut sm-statut--erreur ms-auto">Masqué par l'administration</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($avis['commentaire'] !== null): ?>
                            <p class="sm-texte-multiligne"><?= e($avis['commentaire']) ?></p>
                        <?php endif; ?>

                        <form method="post" class="sm-reponse-avis">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="avis" value="<?= e($avis['id']) ?>">
                            <label class="form-label small fw-semibold" for="reponse-<?= e($avis['id']) ?>">
                                <?= $avis['reponse'] === null ? 'Répondre publiquement' : 'Votre réponse publique' ?>
                            </label>
                            <textarea class="form-control form-control-sm mb-2" id="reponse-<?= e($avis['id']) ?>" name="reponse" rows="2"
                                      maxlength="500" required placeholder="Merci pour votre retour…"><?= e($avis['reponse'] ?? '') ?></textarea>
                            <button class="btn btn-sm btn-contour" type="submit"><?= $avis['reponse'] === null ? 'Publier la réponse' : 'Modifier la réponse' ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
