<?php
/**
 * SAMA MAILLOT — boutons « Continuer avec Google / Facebook », au-dessus des formulaires de connexion
 * et d'inscription, suivis du séparateur « ou ». Un fournisseur inactif n'a pas de bouton ; sans
 * aucun fournisseur actif, rien n'est affiché.
 * Liens simples vers le serveur (aucun SDK JavaScript). Au clic, sama.js désactive le bouton et
 * affiche un indicateur de chargement (aria-busy) pour éviter le double clic.
 */
$fournisseurs_boutons = fournisseurs_actifs();
if ($fournisseurs_boutons === []) {
    return;
}
?>
<div class="sm-sociaux">
    <?php foreach ($fournisseurs_boutons as $fournisseur_bouton): ?>
        <?php $libelle_bouton = 'Continuer avec ' . nom_fournisseur($fournisseur_bouton); ?>
        <a class="sm-bouton-social sm-bouton-social--<?= e($fournisseur_bouton) ?>" href="<?= e(url('connexion/' . $fournisseur_bouton . '.php')) ?>"
           aria-label="<?= e($libelle_bouton) ?>" data-bouton-social>
            <?php require __DIR__ . '/logo_' . $fournisseur_bouton . '.php'; ?>
            <span class="sm-bouton-social__texte"><?= e($libelle_bouton) ?></span>
            <span class="spinner-border spinner-border-sm sm-bouton-social__attente" aria-hidden="true"></span>
        </a>
    <?php endforeach; ?>
</div>
<div class="sm-separateur" aria-hidden="true">ou</div>
