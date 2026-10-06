<?php
/**
 * SAMA MAILLOT — SIMULATEUR DE PAIEMENT (développement et recette UNIQUEMENT).
 *
 * Imite la page de paiement d'un agrégateur : le testeur choisit le moyen et l'issue
 * (réussi, refusé, annulé, montant incorrect). Le simulateur envoie alors une notification
 * SIGNÉE au webhook du site, exactement comme un vrai agrégateur, puis renvoie vers la page de retour.
 * Refusé en production et si un autre fournisseur est configuré (404).
 */
require __DIR__ . '/../../includes/config.php';

if (!paiement_mode_test()) {
    afficher_erreur(404);
}

$transaction = transaction_par_reference(is_string($_GET['ref'] ?? null) ? $_GET['ref'] : (string) ($_POST['ref'] ?? ''));
if ($transaction === null || $transaction['fournisseur'] !== 'simulateur') {
    afficher_erreur(404);
}
$reference = $transaction['reference'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $transaction['statut'] === 'initie') {
    $issue = champ_post('issue');
    $moyen = champ_post('moyen');
    if (verifier_csrf() && in_array($issue, ['reussi', 'echoue', 'annule', 'montant_faux'], true)) {
        bdd()->prepare('UPDATE transactions_paiement SET simulateur_etat = :etat, moyen = :moyen WHERE id = :id AND traitee_le IS NULL')
            ->execute(['etat' => $issue, 'moyen' => isset(MOYENS_PAIEMENT[$moyen]) ? $moyen : 'wave', 'id' => $transaction['id']]);
        simulateur_envoyer_notification($transaction);
    }
    header('Location: ' . url_retour_paiement($reference), true, 303);
    exit;
}

$commandes = commandes_de_la_transaction((int) $transaction['id']);
$titre_page = 'Simulateur de paiement (TEST)';
$sans_indexation = true;

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire sm-simulateur">
            <div class="sm-simulateur__bandeau" role="note">
                <i class="bi bi-cone-striped" aria-hidden="true"></i> MODE TEST — aucun argent réel n'est débité
            </div>
            <h1 class="h4 fw-bold mb-1">Paiement SAMA MAILLOT</h1>
            <p class="text-body-secondary mb-3">Référence <strong><?= e($reference) ?></strong></p>

            <div class="sm-simulateur__montant"><?= e(format_fcfa((int) $transaction['montant'])) ?></div>
            <p class="small text-body-secondary text-center mb-4">
                <?= e(count($commandes)) ?> commande<?= count($commandes) > 1 ? 's' : '' ?> :
                <?= e(implode(', ', array_column($commandes, 'numero'))) ?>
            </p>

            <?php if ($transaction['statut'] !== 'initie'): ?>
                <div class="alert alert-info">Ce paiement est déjà traité (<?= e(STATUTS_TRANSACTION[$transaction['statut']][0]) ?>).</div>
                <a class="btn btn-contour w-100" href="<?= e(url_retour_paiement($reference)) ?>">Voir le résultat</a>
            <?php else: ?>
                <form method="post">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="ref" value="<?= e($reference) ?>">
                    <fieldset class="mb-3">
                        <legend class="form-label fw-semibold fs-6">Moyen de paiement</legend>
                        <div class="sm-simulateur__moyens">
                            <?php foreach (MOYENS_PAIEMENT as $cle => $libelle): ?>
                                <input class="btn-check" type="radio" name="moyen" id="moyen-<?= e($cle) ?>" value="<?= e($cle) ?>" <?= $cle === 'wave' ? 'checked' : '' ?>>
                                <label class="btn btn-contour" for="moyen-<?= e($cle) ?>"><?= e($libelle) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <p class="small fw-semibold mb-2">Issue à simuler :</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-or btn-lg" type="submit" name="issue" value="reussi"><i class="bi bi-check2-circle" aria-hidden="true"></i> Payer (paiement réussi)</button>
                        <button class="btn btn-contour sm-bouton-danger" type="submit" name="issue" value="echoue">Paiement refusé (solde insuffisant)</button>
                        <button class="btn btn-contour" type="submit" name="issue" value="annule">Annuler le paiement</button>
                        <button class="btn btn-link btn-sm text-body-secondary" type="submit" name="issue" value="montant_faux">Test : montant reçu incorrect</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
