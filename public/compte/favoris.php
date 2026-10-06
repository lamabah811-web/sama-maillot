<?php
/**
 * SAMA MAILLOT — « Mes favoris » (§8.7).
 *
 * Maillots mis de côté par l'utilisateur. Un maillot devenu indisponible (retiré, boutique
 * masquée) n'apparaît plus ici, mais revient s'il est republié.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();

$requete = bdd()->prepare(
    SQL_CARTE_MAILLOT . '
     JOIN favoris f ON f.maillot_id = m.id AND f.utilisateur_id = :utilisateur
     WHERE ' . SQL_MAILLOT_VISIBLE . '
     ORDER BY f.cree_le DESC'
);
$requete->execute(['utilisateur' => $utilisateur['id']]);
$favoris = $requete->fetchAll();

$titre_page = 'Mes favoris';
$sans_indexation = true;
$page_active = 'mes-favoris';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-catalogue">
    <div class="container">
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb sm-fil-ariane">
                <li class="breadcrumb-item"><a href="<?= e(url_page('compte')) ?>">Mon compte</a></li>
                <li class="breadcrumb-item active" aria-current="page">Mes favoris</li>
            </ol>
        </nav>

        <h1 class="sm-titre-section mb-4">
            Mes favoris <span class="text-body-secondary fw-normal fs-5">(<?= e(count($favoris)) ?>)</span>
        </h1>

        <?php if ($favoris !== []): ?>
            <div class="sm-grille-maillots sm-grille-maillots--4">
                <?php foreach ($favoris as $maillot): ?>
                    <div class="sm-favori">
                        <?= carte_maillot($maillot) ?>
                        <form method="post" action="<?= e(url('favoris/basculer.php')) ?>">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="maillot_id" value="<?= e($maillot['id']) ?>">
                            <input type="hidden" name="retour" value="compte/favoris.php">
                            <button class="btn btn-contour btn-sm w-100 mt-2" type="submit">
                                <i class="bi bi-heartbreak" aria-hidden="true"></i> Retirer
                                <span class="visually-hidden">« <?= e($maillot['titre']) ?> »</span>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="sm-etat-vide">
                <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-heart"></i></div>
                <h2 class="h3">Aucun favori pour le moment</h2>
                <p class="mb-4">Sur la fiche d'un maillot, touchez « Ajouter aux favoris » pour le retrouver ici.</p>
                <a class="btn btn-or" href="<?= e(url_catalogue()) ?>"><i class="bi bi-grid" aria-hidden="true"></i> Parcourir le catalogue</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
