<?php
/**
 * SAMA MAILLOT — déconnexion.
 *
 * Uniquement par formulaire POST avec jeton CSRF : un simple lien (ou une image piégée
 * sur un autre site) ne peut pas déconnecter l'utilisateur à son insu.
 */
require __DIR__ . '/../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifier_csrf()) {
    rediriger('');
}

deconnecter();
message_flash('succes', 'Vous êtes déconnecté. À bientôt sur SAMA MAILLOT !');
rediriger('');
