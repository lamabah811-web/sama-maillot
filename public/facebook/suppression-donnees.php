<?php
/**
 * SAMA MAILLOT — rappel de suppression des données exigé par Meta (« Data Deletion Request Callback »).
 *
 * Meta envoie un POST contenant « signed_request » quand une personne retire l'application
 * dans ses paramètres Facebook et demande la suppression de ses données.
 *
 * Pas de jeton CSRF ici : l'appel vient des serveurs de Meta, pas d'un formulaire du site.
 * La signature HMAC-SHA256 calculée avec FACEBOOK_APP_SECRET (connu de Meta et de nous seuls)
 * remplace le jeton : une requête non signée par Meta est refusée.
 *
 * Réponse JSON attendue par Meta : {"url": "<page de statut>", "confirmation_code": "<code>"}.
 */
require __DIR__ . '/../../includes/config.php';

exiger_fournisseur_actif('facebook');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Méthode non autorisée.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$requeteSignee = $_POST['signed_request'] ?? '';
$charge = is_string($requeteSignee) && strlen($requeteSignee) <= 8192
    ? decoder_requete_signee_facebook($requeteSignee, (string) env('FACEBOOK_APP_SECRET', ''))
    : null;
$idFacebook = is_array($charge) ? (string) ($charge['user_id'] ?? '') : '';

if ($charge === null || preg_match('/^\d{1,40}$/', $idFacebook) !== 1) {
    error_log('Suppression Facebook : requête refusée (signature ou contenu invalide).');
    http_response_code(400);
    echo json_encode(['error' => 'Requête invalide.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$code = enregistrer_suppression_facebook($idFacebook);

echo json_encode([
    'url' => url('facebook/statut-suppression.php?code=' . $code),
    'confirmation_code' => $code,
], JSON_UNESCAPED_SLASHES);
