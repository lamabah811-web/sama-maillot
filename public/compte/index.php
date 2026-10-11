<?php
/**
 * SAMA MAILLOT — « Mon compte » (§8.7) : profil et mot de passe, pour tous les rôles.
 *
 * Client : rubriques commandes, favoris, adresses, avis (lots suivants).
 * Vendeur et administrateur : lien vers leur espace de travail.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();

$titre_page = 'Mon compte';
$sans_indexation = true;
$page_active = 'compte';

// Rubriques : [icône, titre, texte, adresse ou null si pas encore disponible].
// Aucune fausse donnée n'est affichée.
// Rubriques d'acheteur : client ET vendeur (un vendeur peut acheter, §5.1).
$rubriques_achat = [
    ['bi-receipt', 'Mes commandes', 'Suivez vos commandes et confirmez leur réception.', url_page('mes-commandes')],
    ['bi-heart', 'Mes favoris', 'Retrouvez les maillots que vous avez aimés.', url_page('mes-favoris')],
    ['bi-geo-alt', 'Mes adresses', 'Quartier, ville et repère pour la livraison.', null],
    ['bi-star', 'Mes avis', 'Les notes laissées aux vendeurs.', null],
];
$rubriques = match ($utilisateur['role']) {
    'vendeur' => [
        ['bi-shop', 'Espace vendeur', 'Votre boutique, vos maillots, vos commandes et votre abonnement.', url_page('vendeur')],
        ...$rubriques_achat,
    ],
    'admin' => [
        ['bi-shield-fill', 'Administration', 'Modération, litiges, abonnements et versements.', url_page('admin')],
    ],
    default => $rubriques_achat,
};

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <span class="sm-sur-titre">Mon compte</span>
        <h1 class="sm-titre-section mb-4">Bonjour <?= e($utilisateur['prenom']) ?> !</h1>

        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="sm-tuile">
                    <h2><i class="bi bi-person" aria-hidden="true"></i> Mon profil</h2>
                    <dl class="mb-3">
                        <dt class="small text-body-secondary">Nom</dt>
                        <dd><?= e($utilisateur['prenom'] . ' ' . $utilisateur['nom']) ?></dd>
                        <dt class="small text-body-secondary">E-mail</dt>
                        <dd class="text-break"><?= e($utilisateur['email']) ?></dd>
                        <dt class="small text-body-secondary">Téléphone</dt>
                        <dd class="mb-0">
                            <?php if ($utilisateur['telephone_verifie_le'] !== null): ?>
                                <?= e(formater_telephone($utilisateur['telephone'])) ?>
                                <span class="badge text-bg-success">Vérifié</span>
                            <?php else: ?>
                                <span class="text-body-secondary">Il vous sera demandé lors de votre première commande.</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-contour" href="<?= e(url_page('compte-mot-de-passe')) ?>">
                            <i class="bi bi-key" aria-hidden="true"></i> <?= $utilisateur['mot_de_passe_hache'] === null ? 'Définir un mot de passe' : 'Changer mon mot de passe' ?>
                        </a>
                        <?php if ($utilisateur['role'] !== 'admin' && (fournisseurs_actifs() !== [] || liaisons_du_compte((int) $utilisateur['id']) !== [])): ?>
                            <a class="btn btn-contour" href="<?= e(url('compte/connexions.php')) ?>">
                                <i class="bi bi-link-45deg" aria-hidden="true"></i> Google et Facebook
                            </a>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url_page('deconnexion')) ?>">
                            <?= champ_csrf() ?>
                            <button class="btn btn-contour" type="submit">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Se déconnecter
                            </button>
                        </form>
                    </div>
                    <?php if ($utilisateur['role'] !== 'admin'): ?>
                        <a class="d-inline-block small text-body-secondary mt-3" href="<?= e(url('compte/supprimer.php')) ?>">
                            <i class="bi bi-trash3" aria-hidden="true"></i> Supprimer mon compte
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="row g-3">
                    <?php foreach ($rubriques as [$icone, $titre, $texte, $lien]): ?>
                        <div class="col-12<?= count($rubriques) > 1 ? ' col-sm-6' : '' ?>">
                            <div class="sm-tuile">
                                <h2><i class="bi <?= e($icone) ?>" aria-hidden="true"></i> <?= e($titre) ?></h2>
                                <p class="mb-2"><?= e($texte) ?></p>
                                <?php if ($lien !== null): ?>
                                    <a class="btn btn-or" href="<?= e($lien) ?>">
                                        Ouvrir <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Bientôt disponible</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
