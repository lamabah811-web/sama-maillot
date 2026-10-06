<?php
/**
 * SAMA MAILLOT — tâche planifiée (§27.3), toutes les 5 minutes.
 *
 * Annule les commandes non confirmées par le vendeur après 24 h (stock restitué), envoie le
 * rappel « 12 h restantes » au vendeur, passe en « Reçue » les commandes livrées depuis
 * 24 h (avec code) ou 48 h (sans code), et supprime les paniers invités expirés.
 * Les pages de commandes lancent aussi ce traitement, pour un affichage toujours à jour.
 */

// Cron Infomaniak (toutes les 5 minutes) :
//   */5 * * * *  php /chemin/vers/sama-maillot/taches/commandes_expirees.php
// En local (XAMPP) :
//   C:\xampp\php\php.exe C:\xampp\htdocs\sama-maillot\taches\commandes_expirees.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/config.php';

$bilan = traiter_commandes_echues();
echo date('Y-m-d H:i:s') . ' — annulées : ' . $bilan['annulees'] . ', rappels : ' . $bilan['rappels']
    . ', reçues : ' . $bilan['recues'] . PHP_EOL;
