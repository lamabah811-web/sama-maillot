<?php
/**
 * SAMA MAILLOT — tâche planifiée (§27.3), chaque jour à 8h : cycle des abonnements.
 * Statut des boutiques aligné sur l'abonnement, rappels J-2, J0, J+1, J+2, masquage à J+3,
 * rappel hebdomadaire pendant un mois. Chaque rappel n'est envoyé qu'une fois.
 */

// Cron Infomaniak (chaque jour à 8h) :
//   0 8 * * *  php /chemin/vers/sama-maillot/taches/abonnements.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/config.php';

$bilan = traiter_abonnements();
echo date('Y-m-d H:i:s') . ' — boutiques vérifiées : ' . $bilan['boutiques'] . ', rappels envoyés : ' . $bilan['rappels'] . PHP_EOL;
