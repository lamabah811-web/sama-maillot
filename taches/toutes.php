<?php
/**
 * SAMA MAILLOT — TOUTES les tâches automatiques en une seule tâche planifiée (§27.3).
 *
 * C'est la seule ligne cron à programmer chez Infomaniak : paiements expirés, commandes non
 * confirmées, déblocage des fonds, abonnements (rappels, grâce, masquage), file des SMS, purge des
 * données anciennes. Chaque traitement est sans effet s'il n'y a rien à faire, et ne fait jamais
 * deux fois la même chose (rappels uniques, statuts vérifiés) : il peut tourner aussi souvent que voulu.
 * Les scripts séparés (paiements.php, abonnements.php…) restent utilisables à la main.
 */

// Cron Infomaniak (toutes les 5 minutes) :
//   */5 * * * *  php /chemin/vers/sama-maillot/taches/toutes.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/config.php';

$bilan = executer_taches_automatiques();

// Trace pour la page « État du système » (l'administration voit si le cron tourne).
file_put_contents(DOSSIER_JOURNAUX . '/derniere_execution_cron.txt', (string) time());

echo date('Y-m-d H:i:s') . ' — ' . json_encode($bilan, JSON_UNESCAPED_UNICODE) . PHP_EOL;
