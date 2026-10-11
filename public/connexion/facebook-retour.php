<?php
/**
 * SAMA MAILLOT — retour de Facebook : state vérifié, identité contrôlée côté serveur, puis connexion,
 * création du compte (page « Bienvenue ») ou liaison à confirmer. N'affiche jamais d'exception :
 * en cas d'erreur, retour à la connexion avec un message simple.
 */
require __DIR__ . '/../../includes/config.php';

exiger_fournisseur_actif('facebook');
terminer_retour_oauth('facebook');
