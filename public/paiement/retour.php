<?php
/**
 * SAMA MAILLOT — retour du client après la page de l'agrégateur (§13.4).
 *
 * Ce retour n'est PAS une preuve de paiement : si la notification de l'agrégateur n'est pas encore
 * arrivée, le statut est vérifié côté serveur par l'API du fournisseur, puis affiché.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();

$transaction = transaction_par_reference(is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '');
if ($transaction === null || (int) $transaction['client_id'] !== (int) $utilisateur['id']) {
    afficher_erreur(404);
}

if ($transaction['statut'] === 'initie' || $transaction['statut'] === 'inconnu') {
    verifier_paiement($transaction);
    $transaction = transaction_par_reference($transaction['reference']);
}

$statut = $transaction['statut'];
$commandes = commandes_de_la_transaction((int) $transaction['id']);
$lien_commandes = count($commandes) === 1 ? url('compte/commande.php?id=' . (int) $commandes[0]['id']) : url_page('mes-commandes');

$titre_page = 'Paiement';
$sans_indexation = true;
$page_active = 'mes-commandes';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire text-center">
            <?php if ($statut === 'reussi'): ?>
                <span class="sm-medaillon sm-medaillon--succes mb-3" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
                <h1 class="h3 fw-bold">Paiement reçu, merci !</h1>
                <p class="text-body-secondary">
                    <?= e(format_fcfa((int) $transaction['montant'])) ?> payés<?= $transaction['moyen'] !== null ? ' par ' . e(MOYENS_PAIEMENT[$transaction['moyen']] ?? $transaction['moyen']) : '' ?>.
                    SAMA MAILLOT garde l'argent jusqu'à la réception de votre maillot.
                </p>
                <p class="small"><i class="bi bi-shield-lock" aria-hidden="true"></i> Votre <strong>code de livraison</strong> est dans votre commande : donnez-le au livreur <strong>seulement à la réception</strong>.</p>
            <?php elseif ($statut === 'initie' || $statut === 'inconnu'): ?>
                <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                <h1 class="h3 fw-bold">Paiement en cours de vérification</h1>
                <p class="text-body-secondary">Nous attendons la confirmation de l'opérateur. Vous serez prévenu dès qu'elle arrive ; votre commande est réservée en attendant.</p>
                <?php if ($statut === 'initie' && strtotime($transaction['expire_le']) > time() && $transaction['url_paiement'] !== null): ?>
                    <a class="btn btn-or mb-2" href="<?= e($transaction['url_paiement']) ?>">Reprendre le paiement</a>
                <?php endif; ?>
            <?php elseif ($statut === 'montant_incorrect'): ?>
                <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
                <h1 class="h3 fw-bold">Paiement à vérifier</h1>
                <p class="text-body-secondary">Le montant reçu ne correspond pas à votre commande. Notre équipe vérifie et vous contacte rapidement.</p>
            <?php else: ?>
                <span class="sm-medaillon sm-medaillon--erreur mb-3" aria-hidden="true"><i class="bi bi-x-circle"></i></span>
                <h1 class="h3 fw-bold">Paiement non effectué</h1>
                <p class="text-body-secondary">
                    <?= e(['echoue' => 'Le paiement a été refusé.', 'annule' => 'Vous avez annulé le paiement.', 'expire' => 'Le délai de paiement est dépassé.'][$statut] ?? '') ?>
                    Aucun montant n'a été prélevé et la commande est annulée.
                </p>
                <a class="btn btn-or mb-2" href="<?= e(url_page('catalogue')) ?>">Retour au catalogue</a>
            <?php endif; ?>
            <div><a class="btn btn-contour" href="<?= e($lien_commandes) ?>">Voir <?= count($commandes) > 1 ? 'mes commandes' : 'ma commande' ?></a></div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
