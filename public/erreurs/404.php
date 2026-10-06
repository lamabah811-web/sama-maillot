<?php
/**
 * Page d'erreur 404 « Page introuvable ».
 * Affichée par Apache (ErrorDocument dans public/.htaccess) pour toute adresse inconnue.
 */
require __DIR__ . '/../../includes/config.php';

afficher_erreur(404);
