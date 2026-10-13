<?php
/**
 * SAMA MAILLOT — espace vendeur : portefeuille et versements (§15).
 *
 * Soldes calculés à partir du registre des mouvements (non modifiable) : en attente, disponible,
 * bloqué, versé. Versements groupés (mardi et vendredi) sur le numéro Wave de la boutique.
 * Seules les commandes payées EN LIGNE passent par le portefeuille.
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();
$boutiqueId = (int) $boutique['id'];

// Paiement en ligne éteint : rien ne passe par le portefeuille (les clients paient le vendeur à la livraison).
if (!paiement_en_ligne_actif()) {
    rediriger('vendeur/');
}

$soldes = soldes_portefeuille($boutiqueId);
$mouvements = mouvements_boutique($boutiqueId, 60);
$versements = versements_effectues($boutiqueId, 20);
$minimum = (int) parametre('versement_minimum_fcfa', '1000');

$titre_page = 'Mon portefeuille';
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-catalogue">
    <div class="container">
        <?= menu_vendeur('portefeuille') ?>
        <h1 class="sm-titre-section mb-2">Mon portefeuille</h1>
        <p class="text-body-secondary mb-4">
            Commandes payées en ligne : SAMA MAILLOT garde l'argent jusqu'à la réception, puis vous le verse par Wave.
            Prochain versement : <strong><?= e(prochain_versement()) ?></strong> (à partir de <?= e(format_fcfa($minimum)) ?> disponibles).
        </p>

        <?php if (empty($boutique['numero_wave'])): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                Indiquez votre <strong>numéro Wave</strong> dans <a href="<?= e(url('vendeur/boutique.php')) ?>">Ma boutique</a> pour recevoir vos versements.
            </div>
        <?php endif; ?>

        <div class="sm-soldes mb-4">
            <?php foreach (COMPARTIMENTS_PORTEFEUILLE as $cle => [$libelle, $aide, $icone, $couleur]): ?>
                <div class="sm-solde sm-solde--<?= e($couleur) ?>">
                    <span class="sm-solde__libelle"><i class="bi <?= e($icone) ?>" aria-hidden="true"></i> <?= e($libelle) ?></span>
                    <span class="sm-solde__montant"><?= e(format_fcfa($soldes[$cle])) ?></span>
                    <span class="small text-body-secondary"><?= e($aide) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="sm-tuile">
                    <h2 class="h6 fw-bold">Mouvements</h2>
                    <?php if ($mouvements === []): ?>
                        <p class="small text-body-secondary mb-0">Aucun mouvement pour le moment. Ils apparaîtront avec vos premières commandes payées en ligne.</p>
                    <?php else: ?>
                        <ul class="sm-recap-articles mb-0">
                            <?php foreach ($mouvements as $m): ?>
                                <li>
                                    <span>
                                        <?= e(LIBELLES_OPERATION[$m['operation']] ?? $m['operation']) ?>
                                        <?php if ($m['numero'] !== null): ?>
                                            · <a href="<?= e(url('vendeur/commande.php?id=' . (int) $m['commande_id'])) ?>"><?= e($m['numero']) ?></a>
                                        <?php endif; ?>
                                        <span class="small text-body-secondary d-block">
                                            <?= e(date_commande($m['cree_le'])) ?> · <?= e(COMPARTIMENTS_PORTEFEUILLE[$m['compartiment']][0]) ?>
                                        </span>
                                    </span>
                                    <strong class="<?= (int) $m['montant'] < 0 ? 'sm-montant-negatif' : 'sm-montant-positif' ?>">
                                        <?= (int) $m['montant'] < 0 ? '−' : '+' ?> <?= e(format_fcfa(abs((int) $m['montant']))) ?>
                                    </strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold">Versements reçus</h2>
                    <?php if ($versements === []): ?>
                        <p class="small text-body-secondary mb-0">Aucun versement pour le moment.</p>
                    <?php else: ?>
                        <ul class="sm-recap-articles mb-0">
                            <?php foreach ($versements as $v): ?>
                                <li>
                                    <span>
                                        Wave <?= e(formater_telephone($v['numero_wave'])) ?>
                                        <span class="small text-body-secondary d-block"><?= e(date_commande($v['cree_le'])) ?> · réf. <?= e($v['reference_wave']) ?></span>
                                    </span>
                                    <strong><?= e(format_fcfa((int) $v['montant'])) ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="sm-tuile">
                    <h2 class="h6 fw-bold">Comment ça marche ?</h2>
                    <ol class="small mb-0 ps-3">
                        <li>Le client paie en ligne : le montant arrive « en attente ».</li>
                        <li>Il devient « disponible » quand le client confirme la réception, ou 24 h après une livraison prouvée par le code (48 h sans code).</li>
                        <li>En cas de réclamation, il est « bloqué » jusqu'à la décision.</li>
                        <li>Le mardi et le vendredi, le disponible est viré sur votre Wave.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
