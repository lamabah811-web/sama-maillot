<?php
/**
 * SAMA MAILLOT — suivi d'une demande de suppression des données envoyée par Meta.
 * Seul le code de confirmation permet de trouver la demande. Aucune donnée personnelle n'est affichée.
 */
require __DIR__ . '/../../includes/config.php';

exiger_fournisseur_actif('facebook');

$code = strtoupper(is_string($_GET['code'] ?? null) ? $_GET['code'] : '');
$demande = false;

if (preg_match('/^[A-F0-9]{20}$/', $code) === 1) {
    $requete = bdd()->prepare('SELECT statut, recue_le, traitee_le FROM demandes_suppression_facebook WHERE code_confirmation = :code');
    $requete->execute(['code' => $code]);
    $demande = $requete->fetch();
}

$titre_page = 'Suppression de vos données';
$sans_indexation = true;

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
            <h1 class="mb-3">Suppression de vos données Facebook</h1>

            <?php if ($demande === false): ?>
                <p class="mb-0">Aucune demande ne correspond à ce code. Vérifiez le lien reçu de Facebook.</p>
            <?php else: ?>
                <dl class="mb-3">
                    <dt class="small text-body-secondary">Code de confirmation</dt>
                    <dd class="font-monospace"><?= e($code) ?></dd>
                    <dt class="small text-body-secondary">Demande reçue le</dt>
                    <dd><?= e(date('d/m/Y à H:i', strtotime($demande['recue_le']))) ?></dd>
                    <dt class="small text-body-secondary">Statut</dt>
                    <dd class="mb-0">
                        <?php if ($demande['statut'] === 'traitee'): ?>
                            <span class="badge text-bg-success">Traitée</span>
                            le <?= e(date('d/m/Y à H:i', strtotime($demande['traitee_le']))) ?>
                        <?php else: ?>
                            <span class="badge text-bg-warning">En cours</span>
                        <?php endif; ?>
                    </dd>
                </dl>
                <p class="small text-body-secondary mb-0">
                    Les données reçues de Facebook sont supprimées dès réception de la demande. Si une commande
                    est en cours, l'anonymisation du compte est terminée après sa clôture ; l'historique des
                    commandes est conservé de façon anonyme (obligation comptable).
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
