<?php
/**
 * SAMA MAILLOT — <!doctype>, <html> et <head> communs au site et à l'espace administrateur.
 * Attend : $titre_complet, $description_page ; facultatifs : $sans_indexation, $url_canonique,
 * $image_partage (adresse complète d'une image), $type_partage (« website », « product »…).
 */
?>
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="color-scheme" content="light dark">
    <script><?= script_theme() ?></script>

    <title><?= e($titre_complet) ?></title>
    <meta name="description" content="<?= e($description_page) ?>">
    <?php if (!empty($sans_indexation)): ?>
        <meta name="robots" content="noindex">
    <?php endif; ?>

    <?php if (!empty($url_canonique)): ?>
        <link rel="canonical" href="<?= e($url_canonique) ?>">
    <?php endif; ?>

    <!-- Aperçu lors du partage sur WhatsApp et les réseaux sociaux (§30) -->
    <meta property="og:title" content="<?= e($titre_complet) ?>">
    <meta property="og:description" content="<?= e($description_page) ?>">
    <meta property="og:type" content="<?= e($type_partage ?? 'website') ?>">
    <meta property="og:url" content="<?= e($url_canonique ?? url_actuelle()) ?>">
    <meta property="og:image" content="<?= e($image_partage ?? url('images/logo-512.png')) ?>">
    <meta name="twitter:card" content="<?= !empty($image_partage) ? 'summary_large_image' : 'summary' ?>">
    <meta property="og:site_name" content="<?= e(NOM_SITE) ?>">
    <meta property="og:locale" content="fr_SN">

    <!-- Logo dans l'onglet du navigateur, sur l'écran d'accueil (Android) et sur iPhone -->
    <!-- Favicon sans fond : « S » noir si le navigateur est en thème clair, blanc s'il est en sombre.
         Le SVG s'adapte tout seul ; les PNG servent aux navigateurs qui ne lisent pas le SVG. -->
    <link rel="icon" type="image/svg+xml" href="<?= e(ressource('images/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(ressource('images/favicon-32-clair.png')) ?>" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(ressource('images/favicon-32-sombre.png')) ?>" media="(prefers-color-scheme: dark)">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= e(ressource('images/favicon-192-clair.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(ressource('images/apple-touch-icon.png')) ?>">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+"
          crossorigin="anonymous">
    <!-- Polices : Outfit (titres) et Inter (texte), versions variables, sous-ensembles chargés à la demande -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/@fontsource-variable/outfit@5.3.0/index.css"
          integrity="sha384-rAhuThhEmur1082+rEa3iN0VKr8WJJ3XHDvYVUhHl9dSi6CoPR3V94k9jNzbsHLv"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/@fontsource-variable/inter@5.3.0/index.css"
          integrity="sha384-ZTIcl2CmY0wyM9ANOhomBco7rEUIlWztf6lGcLKCocKUrIgGCsfisuZyE+hadW8K"
          crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(ressource('css/sama.css')) ?>">
</head>
