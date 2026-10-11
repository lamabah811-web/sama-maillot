<?php
/**
 * SAMA MAILLOT — démarre la connexion avec Facebook (flux « code d'autorisation », côté serveur).
 * Toute la logique est dans includes/metier/connexion_sociale.php. 404 si Facebook est désactivé.
 */
require __DIR__ . '/../../includes/config.php';

page_depart_sociale('facebook');
