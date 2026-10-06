<?php
/**
 * SAMA MAILLOT — tâche planifiée (§27.3), toutes les 15 minutes.
 *
 * Passe en « Reçue » les commandes livrées dont le délai de réclamation est écoulé
 * (24 h avec code, 48 h sans code). Pour une commande payée en ligne, l'argent passe alors
 * de « en attente » à « disponible » dans le portefeuille du vendeur (§13.2).
 */

// Cron Infomaniak (toutes les 15 minutes) :
//   */15 * * * *  php /chemin/vers/sama-maillot/taches/debloquer_fonds.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/config.php';

$bilan = traiter_commandes_echues();
echo date('Y-m-d H:i:s') . ' — commandes passées en « Reçue » : ' . $bilan['recues'] . PHP_EOL;
