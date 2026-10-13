<?php
/**
 * SAMA MAILLOT — boutons Google / Facebook au-dessus des formulaires de connexion et d'inscription,
 * côte à côte, logo + « Continuer avec … » (logo Google multicolore officiel, « f » de Facebook en or du site),
 * suivis du séparateur « ou ».
 * Un fournisseur inactif n'a pas de bouton (aujourd'hui : Google seul, Facebook non configuré).
 * Liens simples vers le serveur (aucun SDK JavaScript). Au clic, sama.js désactive le bouton et
 * affiche un indicateur de chargement (aria-busy) pour éviter le double clic.
 */
$fournisseurs_boutons = fournisseurs_boutons();
if ($fournisseurs_boutons === []) {
    return;
}
?>
<div class="sm-sociaux sm-sociaux--icones">
    <?php foreach ($fournisseurs_boutons as $fournisseur_bouton): ?>
        <?php $libelle_bouton = 'Continuer avec ' . nom_fournisseur($fournisseur_bouton); ?>
        <a class="sm-bouton-icone-social sm-bouton-icone-social--<?= e($fournisseur_bouton) ?>" href="<?= e(url('connexion/' . $fournisseur_bouton . '.php')) ?>"
           data-bouton-social>
            <?php require __DIR__ . '/logo_' . $fournisseur_bouton . '.php'; ?>
            <span class="sm-bouton-icone-social__texte"><?= e($libelle_bouton) ?></span>
            <span class="spinner-border spinner-border-sm sm-bouton-social__attente" aria-hidden="true"></span>
        </a>
    <?php endforeach; ?>
</div>
<div class="sm-separateur" aria-hidden="true">ou</div>
