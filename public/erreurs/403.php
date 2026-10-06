<?php
/**
 * Page d'erreur 403 « Accès refusé ».
 * Affichée par Apache (ErrorDocument dans public/.htaccess), par exemple pour un dossier
 * sans index.php, et par le code PHP via afficher_erreur(403).
 */
require __DIR__ . '/../../includes/config.php';

afficher_erreur(403);
