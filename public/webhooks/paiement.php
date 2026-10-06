<?php
/**
 * SAMA MAILLOT — notification serveur à serveur de l'agrégateur de paiement (§13.4).
 *
 *   POST webhooks/paiement.php?fournisseur=simulateur
 *
 * La signature est vérifiée, PUIS le statut est contrôlé par l'API du fournisseur avant toute
 * action. Une même notification reçue plusieurs fois n'est traitée qu'une fois.
 */
require __DIR__ . '/../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$entetes = array_change_key_case(function_exists('getallheaders') ? (getallheaders() ?: []) : [], CASE_LOWER);
$corps = (string) file_get_contents('php://input', false, null, 0, 65536);
$fournisseur = is_string($_GET['fournisseur'] ?? null) ? $_GET['fournisseur'] : '';

http_response_code(recevoir_notification_paiement($fournisseur, $corps, $entetes));
header('Content-Type: text/plain; charset=UTF-8');
echo 'ok';
