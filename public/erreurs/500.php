<?php
/**
 * Page d'erreur 500 « Erreur du serveur ».
 * Affichée par Apache (ErrorDocument dans public/.htaccess). Les erreurs PHP non prévues
 * affichent la même page grâce à gerer_exception() (includes/fonctions.php).
 */
require __DIR__ . '/../../includes/config.php';

afficher_erreur(500);
