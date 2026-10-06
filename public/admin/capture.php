<?php
/**
 * SAMA MAILLOT — administration : affiche la capture d'écran d'un paiement Wave.
 * Les captures sont stockées hors du site public (stockage/paiements/) : seul un administrateur
 * connecté peut les voir, via ce script.
 */
require __DIR__ . '/../../includes/config.php';

exiger_admin();

$requete = bdd()->prepare('SELECT capture FROM paiements_abonnement WHERE id = :id');
$requete->execute(['id' => (int) ($_GET['id'] ?? 0)]);
$capture = (string) $requete->fetchColumn();

// Nom de fichier généré par le site uniquement (32 caractères hexadécimaux + extension image).
if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $capture, $m) !== 1 || !is_file(DOSSIER_CAPTURES . '/' . $capture)) {
    afficher_erreur(404);
}

header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$m[1]]);
header('Content-Length: ' . filesize(DOSSIER_CAPTURES . '/' . $capture));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile(DOSSIER_CAPTURES . '/' . $capture);
