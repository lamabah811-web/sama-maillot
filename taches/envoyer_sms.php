<?php
/**
 * SAMA MAILLOT — tâche planifiée (§27.3), chaque minute : envoie la file des SMS et réessaie
 * ceux qui ont échoué (5 essais au maximum). Aucun SMS non urgent entre 22h et 7h.
 */

// Cron Infomaniak (chaque minute) :
//   * * * * *  php /chemin/vers/sama-maillot/taches/envoyer_sms.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/config.php';

[$envoyes, $echecs] = traiter_file_sms(200);
echo date('Y-m-d H:i:s') . ' — SMS envoyés : ' . $envoyes . ', en échec (nouvel essai plus tard) : ' . $echecs . PHP_EOL;
