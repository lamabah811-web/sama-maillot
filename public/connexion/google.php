<?php
/**
 * SAMA MAILLOT — démarre la connexion avec Google (flux « code d'autorisation », côté serveur).
 * Toute la logique est dans includes/metier/connexion_sociale.php. 404 si Google est désactivé.
 */
require __DIR__ . '/../../includes/config.php';

page_depart_sociale('google');
