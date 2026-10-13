<?php
/**
 * SAMA MAILLOT — « Comment ça marche » (§7.3) : parcours de l'acheteur et du vendeur, en étapes simples.
 */
require __DIR__ . '/../../includes/config.php';

$titre_page = 'Comment ça marche';
$description_page = 'Acheter un maillot de football sur SAMA MAILLOT en quelques étapes, ou ouvrir votre boutique : paiement protégé, livraison au Sénégal.';
$page_active = 'comment-ca-marche';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-texte">
    <div class="container">
        <article class="sm-texte-legal">
            <header class="sm-texte-legal__entete">
                <span class="sm-sur-titre">Guide</span>
                <h1>Comment ça marche</h1>
                <p class="text-body-secondary mb-0">Des maillots vendus par des boutiques du Sénégal, avec un paiement protégé.</p>
            </header>

            <h2 id="acheteurs"><i class="bi bi-bag-heart" aria-hidden="true"></i> Pour les acheteurs</h2>
            <ol class="sm-etapes-guide">
                <li><strong>Trouvez votre maillot.</strong> Cherchez une équipe (« Sénégal », « Barça »…) ou parcourez le catalogue.
                    Chaque annonce indique s'il s'agit d'un maillot officiel, d'une réplique ou d'une version fan.</li>
                <li><strong>Choisissez la taille</strong> (et le flocage si la boutique le propose), puis ajoutez au panier.</li>
                <li><strong>Indiquez votre adresse et votre numéro</strong> : le vendeur vous appellera pour la livraison.</li>
                <?php if (paiement_en_ligne_actif()): ?>
                <li><strong>Payez comme vous voulez</strong> : en ligne (Wave, Orange Money, Free Money, carte — protégé, remboursable)
                    ou à la livraison, au vendeur.</li>
                <?php else: ?>
                <li><strong>Payez à la livraison</strong> : en espèces ou par Wave, au vendeur, quand vous recevez votre maillot.
                    Rien à payer avant.</li>
                <?php endif; ?>
                <li><strong>Suivez votre commande</strong> dans « Mes commandes » : confirmée, en route, livrée. Vous êtes prévenu à chaque étape.</li>
                <li><strong>Recevez votre maillot.</strong> <?= paiement_en_ligne_actif()
                    ? 'Paiement en ligne : donnez votre code de livraison seulement maillot en main.'
                    : 'Vérifiez-le, puis payez le vendeur.' ?>
                    Confirmez la réception, puis notez le vendeur.</li>
            </ol>
            <p class="mt-3">
                Un problème ? Ouvrez une réclamation depuis votre commande : SAMA MAILLOT fait la médiation<?= paiement_en_ligne_actif()
                    ? ' et, si vous avez payé en ligne, vous rembourse quand la réclamation est fondée.'
                    : ' avec le vendeur.' ?>
            </p>
            <p><a class="btn btn-or" href="<?= e(url_page('catalogue')) ?>"><i class="bi bi-grid" aria-hidden="true"></i> Voir le catalogue</a></p>

            <h2 id="vendeurs"><i class="bi bi-shop" aria-hidden="true"></i> Pour les vendeurs</h2>
            <ol class="sm-etapes-guide">
                <li><strong>Ouvrez votre boutique</strong> en quelques minutes : nom, ville, WhatsApp, numéro Wave.
                    <?= e(parametre('essai_gratuit_jours', (string) ESSAI_GRATUIT_JOURS)) ?> jours d'essai gratuit.</li>
                <li><strong>Réglez vos zones de livraison</strong> et vos tarifs (ou le retrait sur place).</li>
                <li><strong>Publiez vos maillots</strong> avec de vraies photos, l'authenticité, l'état et le stock par taille.</li>
                <li><strong>Confirmez vos commandes</strong> sous <?= e(parametre('confirmation_vendeur_heures', '24')) ?> heures, appelez le client et livrez.</li>
                <?php if (paiement_en_ligne_actif()): ?>
                <li><strong>Soyez payé</strong> : à la livraison directement, ou en ligne via votre portefeuille, versé par Wave
                    le <?= e(str_replace(',', ' et le ', parametre('versement_jours', 'mardi,vendredi'))) ?>.</li>
                <?php else: ?>
                <li><strong>Soyez payé</strong> directement par le client, à la livraison (espèces ou Wave).</li>
                <?php endif; ?>
                <li><strong>Renouvelez votre abonnement</strong> : <?= e(format_fcfa(prix_abonnement())) ?> par mois, sans commission sur vos ventes.</li>
            </ol>
            <p class="mt-3">
                <a class="btn btn-or" href="<?= e(url_page('devenir-vendeur')) ?>"><i class="bi bi-shop" aria-hidden="true"></i> Ouvrir ma boutique</a>
                <a class="btn btn-contour" href="<?= e(url_page('politique-vendeur')) ?>">Lire la politique vendeur</a>
            </p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
