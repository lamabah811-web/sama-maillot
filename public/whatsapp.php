<?php
/**
 * SAMA MAILLOT — ouverture de WhatsApp vers un vendeur (§17).
 *
 * Le numéro WhatsApp du vendeur n'est jamais écrit dans le code des pages (limite la collecte
 * automatique) : les boutons pointent ici, et c'est le serveur qui redirige vers wa.me.
 *   whatsapp.php?maillot=142       → « Poser une question » sur un maillot (sans créer de commande)
 *   whatsapp.php?boutique=<slug>   → « Contacter sur WhatsApp » depuis la page boutique
 *   whatsapp.php?commande=12       → commande via WhatsApp / contacter le vendeur d'une commande
 *                                    (uniquement le client de cette commande)
 *   whatsapp.php?support=1         → support SAMA MAILLOT (paramètre numero_whatsapp_support)
 */
require __DIR__ . '/../includes/config.php';

// Cette adresse ne doit pas être indexée ni suivie par les robots.
header('X-Robots-Tag: noindex, nofollow');

$numero = null;
$message = '';

if (isset($_GET['maillot'])) {
    $maillot = maillot_par_id((int) $_GET['maillot']);
    if ($maillot !== null) {
        $numero = $maillot['boutique_whatsapp'];
        $message = 'Bonjour, j\'ai une question sur ce maillot vu sur SAMA MAILLOT : '
            . $maillot['titre'] . ' — ' . url_maillot($maillot);
    }
} elseif (isset($_GET['support'])) {
    $numero = parametre('numero_whatsapp_support');
    $message = 'Bonjour SAMA MAILLOT, j\x27ai une question : ';
} elseif (isset($_GET['commande'])) {
    $client = utilisateur_connecte();
    $commande = $client !== null ? commande_du_client((int) $_GET['commande'], (int) $client['id']) : null;
    if ($commande !== null) {
        $numero = $commande['boutique_whatsapp'];
        $message = message_whatsapp_commande($commande, lignes_de_la_commande((int) $commande['id']));
    }
} elseif (isset($_GET['boutique']) && is_string($_GET['boutique'])) {
    $boutique = boutique_par_slug($_GET['boutique']);
    if ($boutique !== null) {
        $numero = $boutique['numero_whatsapp'];
        $message = 'Bonjour, je vous contacte depuis votre boutique « ' . $boutique['nom'] . ' » sur SAMA MAILLOT.';
    }
}

// Numéro au format +221XXXXXXXXX → wa.me attend les chiffres seuls (221XXXXXXXXX)
$chiffres = preg_replace('/\D+/', '', (string) $numero) ?? '';

if (preg_match('/^2217[05678]\d{7}$/', $chiffres) !== 1) {
    afficher_erreur(404);
}

header('Location: https://wa.me/' . $chiffres . '?text=' . rawurlencode($message), true, 302);
exit;
