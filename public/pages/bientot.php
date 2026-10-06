<?php
/**
 * SAMA MAILLOT — page provisoire « Bientôt disponible ».
 *
 * Tous les liens vers des pages pas encore créées arrivent ici (voir url_page() dans
 * includes/fonctions.php). Adresse : /pages/bientot.php?page=faq
 * Seules les clés connues (tableau PAGES de config.php) sont acceptées, sinon : 404.
 */
require __DIR__ . '/../../includes/config.php';

$cle = is_string($_GET['page'] ?? null) ? $_GET['page'] : '';

if (!isset(PAGES[$cle])) {
    afficher_erreur(404);
}

// Si la page a été développée entre-temps, on y envoie directement le visiteur.
if (page_existe($cle)) {
    header('Location: ' . url_page($cle), true, 302);
    exit;
}

$page_active = $cle;
$titre_page = PAGES[$cle]['titre'];
$sans_indexation = true;

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-message" aria-labelledby="titre-bientot">
    <div class="container">
        <span class="sm-medaillon" aria-hidden="true"><i class="bi bi-tools"></i></span>
        <p class="sm-sur-titre d-block mb-1"><?= e($titre_page) ?></p>
        <h1 id="titre-bientot" class="fw-bold mb-3">Bientôt disponible</h1>
        <p class="lead mb-4">
            Cette page est en cours de préparation. Elle sera disponible très prochainement.
        </p>
        <a class="btn btn-or" href="<?= e(url()) ?>">
            <i class="bi bi-house" aria-hidden="true"></i> Retour à l'accueil
        </a>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
