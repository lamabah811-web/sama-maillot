<?php
/**
 * SAMA MAILLOT — page « À propos » (§1, §7.3).
 *
 * Présentation, engagements, fonctionnement pour les acheteurs et les vendeurs.
 * Les chiffres affichés sont RÉELS (lus en base) et n'apparaissent que s'ils existent :
 * aucun faux chiffre (§1, principe directeur ; §3.3).
 */
require __DIR__ . '/../../includes/config.php';

$chiffres = bdd()->query(
    "SELECT
        (SELECT COUNT(*) FROM maillots m JOIN boutiques b ON b.id = m.boutique_id WHERE " . SQL_MAILLOT_VISIBLE . ") AS maillots,
        (SELECT COUNT(*) FROM boutiques b WHERE b.statut IN ('active', 'grace') AND b.supprime_le IS NULL
            AND EXISTS (SELECT 1 FROM maillots m WHERE m.boutique_id = b.id AND " . SQL_MAILLOT_VISIBLE . ")) AS boutiques,
        (SELECT COUNT(DISTINCT m.equipe_id) FROM maillots m JOIN boutiques b ON b.id = m.boutique_id WHERE " . SQL_MAILLOT_VISIBLE . ") AS equipes"
)->fetch();

$titre_page = 'À propos';
$description_page = 'SAMA MAILLOT, la marketplace sénégalaise des maillots de football : équipes nationales, clubs et rétro, '
    . 'vendus par des boutiques du Sénégal. Paiement à la livraison, WhatsApp, prix en FCFA.';
$page_active = 'a-propos';

require __DIR__ . '/../../includes/entete.php';
?>

<!-- Présentation -->
<section class="sm-apropos-intro">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-12 col-lg-7">
                <span class="sm-sur-titre">À propos</span>
                <h1 class="sm-apropos-intro__titre">Le maillot de ton équipe, <span class="sm-texte-or">en toute confiance.</span></h1>
                <p class="sm-apropos-intro__texte">
                    SAMA MAILLOT est la marketplace sénégalaise dédiée aux maillots de football. Nous mettons en relation
                    les supporters et des boutiques indépendantes du Sénégal, qui ont chacune leur vitrine en ligne :
                    équipes nationales, grands clubs et maillots rétro.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-or btn-lg" href="<?= e(url_page('catalogue')) ?>">
                        <i class="bi bi-grid" aria-hidden="true"></i> Voir le catalogue
                    </a>
                    <a class="btn btn-contour btn-lg" href="<?= e(url_page('devenir-vendeur')) ?>">
                        <i class="bi bi-shop" aria-hidden="true"></i> Ouvrir ma boutique
                    </a>
                </div>
            </div>
            <?php if ((int) $chiffres['maillots'] > 0): ?>
                <div class="col-12 col-lg-5">
                    <!-- Chiffres réels, mis à jour en direct -->
                    <dl class="sm-apropos-chiffres">
                        <div>
                            <dt>Maillots en vente</dt>
                            <dd><?= e(number_format((int) $chiffres['maillots'], 0, ',', ' ')) ?></dd>
                        </div>
                        <div>
                            <dt>Boutiques</dt>
                            <dd><?= e($chiffres['boutiques']) ?></dd>
                        </div>
                        <div>
                            <dt>Équipes représentées</dt>
                            <dd><?= e($chiffres['equipes']) ?></dd>
                        </div>
                    </dl>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Pourquoi SAMA MAILLOT -->
<section class="sm-section">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-5">
                <span class="sm-sur-titre">Notre histoire</span>
                <h2 class="sm-titre-section">Acheter un maillot ne devrait pas être un pari</h2>
            </div>
            <div class="col-12 col-lg-7">
                <p class="sm-texte-section">
                    Au Sénégal, le maillot se vend surtout sur les réseaux sociaux et sur WhatsApp. C'est pratique, mais
                    l'acheteur ne sait pas toujours à qui il parle, si le maillot est officiel ou une réplique, ni ce qui
                    se passera s'il ne reçoit rien.
                </p>
                <p class="sm-texte-section mb-0">
                    SAMA MAILLOT garde ce qui marche, le paiement à la livraison, Wave, Orange Money et WhatsApp, et
                    ajoute ce qui manquait : des boutiques identifiées, une authenticité déclarée sur chaque maillot,
                    un suivi de commande clair et la confirmation de réception par le client.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Nos engagements (§1) -->
<section class="sm-section sm-section--surface">
    <div class="container">
        <span class="sm-sur-titre">Nos engagements</span>
        <h2 class="sm-titre-section mb-4">Trois promesses, sur chaque commande</h2>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="sm-tuile">
                    <span class="sm-icone-teintee mb-3" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                    <h3 class="h5 fw-bold">Confiance</h3>
                    <p class="mb-0">
                        Des boutiques identifiées, l'authenticité déclarée sur chaque maillot et un suivi clair de
                        chaque commande. Avec le paiement en ligne (bientôt), votre argent sera conservé par la
                        plateforme jusqu'à la livraison confirmée.
                    </p>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="sm-tuile">
                    <span class="sm-icone-teintee mb-3" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
                    <h3 class="h5 fw-bold">Vos habitudes respectées</h3>
                    <p class="mb-0">
                        Paiement à la livraison en espèces ou par Wave, commande via WhatsApp, livraison par le vendeur
                        ou son livreur, prix en FCFA. Vous achetez comme d'habitude, en plus sûr.
                    </p>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="sm-tuile">
                    <span class="sm-icone-teintee mb-3" aria-hidden="true"><i class="bi bi-lightning-charge"></i></span>
                    <h3 class="h5 fw-bold">Simplicité</h3>
                    <p class="mb-0">
                        Un site en français, rapide sur mobile et en 4G. Recherche par équipe, par taille ou par prix,
                        et un panier qui regroupe vos maillots par boutique.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Comment ça marche -->
<section class="sm-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="sm-tuile">
                    <span class="sm-sur-titre">Pour les supporters</span>
                    <h2 class="h4 fw-bold mb-3">Acheter un maillot</h2>
                    <ol class="sm-liste-numerotee">
                        <li><strong>Trouvez votre maillot</strong> par équipe, catégorie, taille ou prix.</li>
                        <li><strong>Commandez</strong> sur le site ou via WhatsApp, et payez à la livraison.</li>
                        <li><strong>Suivez votre commande</strong> : confirmée, expédiée, livrée.</li>
                        <li><strong>Payez à la réception</strong> et confirmez-la sur le site, une fois le maillot en main.</li>
                    </ol>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="sm-tuile">
                    <span class="sm-sur-titre">Pour les vendeurs</span>
                    <h2 class="h4 fw-bold mb-3">Vendre ses maillots</h2>
                    <ol class="sm-liste-numerotee">
                        <li><strong>Ouvrez votre boutique</strong> en quelques minutes, même avec votre compte client.</li>
                        <li><strong>Publiez vos maillots</strong> : photos, tailles, stock, prix et flocage.</li>
                        <li><strong>Fixez vos zones et vos frais de livraison</strong>, payés par le client.</li>
                        <li><strong>Recevez vos commandes</strong>, confirmez-les sous 24 h et livrez.</li>
                    </ol>
                    <p class="small text-body-secondary mb-0">
                        <?= e(ESSAI_GRATUIT_JOURS) ?> jours d'essai gratuit, puis <?= e(format_fcfa(PRIX_ABONNEMENT_FCFA)) ?> par mois.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Authenticité (§9.4) -->
<section class="sm-section sm-section--surface">
    <div class="container">
        <span class="sm-sur-titre">Transparence</span>
        <h2 class="sm-titre-section mb-2">Officiel, réplique ou version fan : vous savez ce que vous achetez</h2>
        <p class="sm-texte-section mb-4">
            Chaque vendeur doit déclarer l'authenticité de ses maillots, affichée clairement sur la fiche.
            Présenter une réplique comme officielle est interdit : le maillot est supprimé et le compte peut être suspendu.
        </p>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="sm-tuile">
                    <span class="sm-badge-authenticite sm-badge-authenticite--officiel sm-badge-authenticite--statique mb-2">Officiel</span>
                    <p class="mb-0">Maillot de la marque équipementière, vendu comme tel.</p>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="sm-tuile">
                    <span class="sm-badge-authenticite sm-badge-authenticite--replique sm-badge-authenticite--statique mb-2">Réplique</span>
                    <p class="mb-0">Reproduction du maillot, déclarée comme réplique et vendue moins cher.</p>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="sm-tuile">
                    <span class="sm-badge-authenticite sm-badge-authenticite--version_fan sm-badge-authenticite--statique mb-2">Version fan</span>
                    <p class="mb-0">Version supporter du maillot, différente de la version portée par les joueurs.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact -->
<section class="sm-section">
    <div class="container">
        <div class="sm-apropos-contact">
            <div>
                <h2 class="h4 fw-bold mb-1">Une question, une idée, un partenariat ?</h2>
                <p class="mb-0 text-body-secondary">Notre équipe vous répond, depuis Dakar.</p>
            </div>
            <a class="btn btn-or btn-lg" href="<?= e(url_page('contact')) ?>">
                <i class="bi bi-envelope" aria-hidden="true"></i> Nous contacter
            </a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
