<?php
/**
 * SAMA MAILLOT — ajouter ou retirer un maillot des favoris.
 *
 * Formulaire POST avec jeton CSRF uniquement. Un visiteur non connecté est envoyé vers la
 * connexion, puis revient sur la page du maillot.
 */
require __DIR__ . '/../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifier_csrf()) {
    rediriger('catalogue/');
}

$retour = chemin_retour(champ_post('retour'), 'catalogue/');
$utilisateur = utilisateur_connecte();

if ($utilisateur === null) {
    $_SESSION['retour_apres_connexion'] = $retour;
    message_flash('erreur', 'Connectez-vous pour enregistrer vos maillots favoris.');
    rediriger('connexion/');
}

$maillot = maillot_par_id((int) champ_post('maillot_id'));

if ($maillot === null) {
    message_flash('erreur', 'Ce maillot n\'est plus disponible.');
    rediriger($retour);
}

$ajoute = basculer_favori((int) $utilisateur['id'], (int) $maillot['id']);
message_flash('succes', $ajoute ? 'Maillot ajouté à vos favoris.' : 'Maillot retiré de vos favoris.');
rediriger($retour);
