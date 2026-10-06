<?php
/**
 * SAMA MAILLOT — affiche une photo jointe à un litige.
 * Les photos sont stockées hors du site public (stockage/litiges/) : seuls le client, le vendeur
 * concerné et l'administration peuvent les voir.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();

$requete = bdd()->prepare(
    'SELECT p.fichier, l.client_id, l.boutique_id FROM litige_photos p
     JOIN litige_messages m ON m.id = p.message_id JOIN litiges l ON l.id = m.litige_id WHERE p.id = :id'
);
$requete->execute(['id' => (int) ($_GET['id'] ?? 0)]);
$photo = $requete->fetch();

if ($photo === false || !acces_litige($photo, $utilisateur)
    || preg_match('/^[a-f0-9]{32}\.webp$/', $photo['fichier']) !== 1 || !is_file(DOSSIER_LITIGES . '/' . $photo['fichier'])) {
    afficher_erreur(404);
}

$chemin = DOSSIER_LITIGES . '/' . $photo['fichier'];
header('Content-Type: image/webp');
header('Content-Length: ' . filesize($chemin));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($chemin);
