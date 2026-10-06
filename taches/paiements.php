<?php
/**
 * SAMA MAILLOT — tâche planifiée (§13.4, §27.3), toutes les 5 minutes.
 *
 * Revérifie auprès de l'agrégateur les paiements restés « en cours » au-delà du délai
 * (paiement_en_ligne_minutes) ou « à vérifier » : réussi → commandes confirmées ; sinon
 * expiré → commandes annulées et stock remis en vente.
 */

// Cron Infomaniak (toutes les 5 minutes) :
//   */5 * * * *  php /chemin/vers/sama-maillot/taches/paiements.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/config.php';

$bilan = traiter_paiements_en_attente();
echo date('Y-m-d H:i:s') . ' — paiements vérifiés : ' . $bilan['verifies'] . ', expirés : ' . $bilan['expires'] . PHP_EOL;
