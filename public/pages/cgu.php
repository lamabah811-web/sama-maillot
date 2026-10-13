<?php
/**
 * SAMA MAILLOT — conditions générales d'utilisation (§7.3).
 * Règles d'usage de la plateforme pour tous les utilisateurs. Délais et seuils lus dans les paramètres.
 */
require __DIR__ . '/../../includes/config.php';

$titre_page = 'Conditions générales d\'utilisation';
$description_page = 'Conditions générales d\'utilisation de SAMA MAILLOT, la marketplace sénégalaise des maillots de football.';
$page_active = 'cgu';

$seuil = parametre('signalements_avant_suspension', '3');
$periode = parametre('signalements_periode_jours', '90');

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-texte">
    <div class="container">
        <article class="sm-texte-legal">
            <header class="sm-texte-legal__entete">
                <span class="sm-sur-titre">Informations légales</span>
                <h1>Conditions générales d'utilisation</h1>
                <p class="text-body-secondary mb-0">Dernière mise à jour : <?= e(DATE_DOCUMENTS_LEGAUX) ?></p>
            </header>

            <nav class="sm-sommaire mb-4" aria-label="Sommaire">
                <ol>
                    <li><a href="#objet">Objet</a></li>
                    <li><a href="#role">Rôle de <?= e(NOM_SITE) ?></a></li>
                    <li><a href="#compte">Compte</a></li>
                    <li><a href="#usage">Règles d'utilisation</a></li>
                    <li><a href="#avis">Avis</a></li>
                    <li><a href="#signalements">Signalements et sanctions</a></li>
                    <li><a href="#responsabilite">Responsabilité</a></li>
                    <li><a href="#droit">Modifications et droit applicable</a></li>
                </ol>
            </nav>

            <h2 id="objet">1. Objet</h2>
            <p>
                Les présentes conditions encadrent l'utilisation du site <?= e(NOM_SITE) ?>, édité par <?= e(nom_editeur()) ?>
                (voir les <a href="<?= e(url_page('mentions-legales')) ?>">mentions légales</a>). En créant un compte ou en
                utilisant le site, vous les acceptez. Les achats sont en plus régis par les
                <a href="<?= e(url_page('cgv')) ?>">conditions générales de vente</a>, et l'activité des vendeurs par la
                <a href="<?= e(url_page('politique-vendeur')) ?>">politique vendeur</a>.
            </p>

            <h2 id="role">2. Rôle de <?= e(NOM_SITE) ?></h2>
            <p>
                <?= e(NOM_SITE) ?> est une <strong>place de marché</strong> : elle permet à des boutiques indépendantes du Sénégal de
                présenter leurs maillots de football et aux acheteurs de les commander. Le contrat de vente est conclu entre
                l'acheteur et le vendeur. <?= e(NOM_SITE) ?> fournit l'outil, contrôle le respect des règles, fait la médiation
                en cas de problème<?= paiement_en_ligne_actif() ? ' et, pour les paiements en ligne, conserve l\'argent jusqu\'à la réception (paiement sécurisé)' : '' ?>.
            </p>

            <h2 id="compte">3. Compte</h2>
            <ul>
                <li>Les informations fournies doivent être exactes. Vous êtes responsable de la confidentialité de votre mot de passe.</li>
                <li>Un numéro de téléphone peut être lié à un seul compte client et à un seul compte vendeur.</li>
                <li>Un même compte peut acheter et, s'il ouvre une boutique, vendre ; un vendeur ne peut pas acheter dans sa propre boutique.</li>
                <li>Après plusieurs tentatives de connexion échouées, l'accès est bloqué temporairement pour votre sécurité.</li>
                <li>Vous pouvez supprimer votre compte à tout moment depuis « Mon compte », une fois vos commandes en cours terminées.</li>
            </ul>

            <h2 id="usage">4. Règles d'utilisation</h2>
            <p>Il est interdit :</p>
            <ul>
                <li>de publier des contenus faux, trompeurs, injurieux, discriminatoires ou contraires à la loi ;</li>
                <li>de présenter une réplique comme un maillot officiel, ou d'utiliser des photos qui ne vous appartiennent pas ;</li>
                <li>de passer des commandes sans intention de les recevoir, ou de refuser de façon répétée des commandes à la livraison ;</li>
                <li>de contourner la plateforme pour éviter ses règles (fausses commandes, faux avis, comptes multiples) ;</li>
                <li>de tenter d'accéder aux données d'autres utilisateurs ou de perturber le fonctionnement du site.</li>
            </ul>
            <p>
                Après <?= e(parametre('refus_livraison_avant_blocage', '3')) ?> refus de commandes à la livraison, le paiement à la
                livraison est désactivé sur le compte<?= paiement_en_ligne_actif() ? ' ; le paiement en ligne reste possible' : '' ?>.
            </p>

            <h2 id="avis">5. Avis</h2>
            <ul>
                <li>Seul un client ayant <strong>reçu</strong> une commande peut noter le vendeur, dans les <?= e(parametre('avis_delai_jours', '30')) ?> jours ; un avis par commande, modifiable une fois sous 7 jours.</li>
                <li>Les avis sont <strong>publics</strong> : ils s'affichent sur la page de la boutique avec le prénom et l'initiale du nom de l'auteur.</li>
                <li>Le vendeur peut publier une réponse publique. L'administration peut masquer un avis abusif (insulte, faux avis, données personnelles…).</li>
                <li>Il est interdit d'offrir une contrepartie en échange d'un avis.</li>
            </ul>

            <h2 id="signalements">6. Signalements et sanctions</h2>
            <p>
                Un client ayant commandé chez un vendeur peut le signaler, ou signaler l'un de ses maillots. Lorsque
                <strong><?= e($seuil) ?> clients différents</strong> signalent le même vendeur en <?= e($periode) ?> jours, sa boutique est
                <strong>suspendue automatiquement</strong> ; le vendeur peut contester, et seule l'administration peut lever la suspension.
                Un signalement jugé infondé ne compte plus.
            </p>
            <p>
                En cas de manquement à ces règles, <?= e(NOM_SITE) ?> peut, selon la gravité : avertir l'utilisateur, masquer ou
                supprimer un contenu, suspendre ou fermer le compte. Les décisions sont enregistrées et l'utilisateur est prévenu.
            </p>

            <h2 id="responsabilite">7. Responsabilité</h2>
            <p>
                Chaque vendeur est responsable de ses annonces (description, authenticité, prix, stock) et de la livraison.
                <?= e(NOM_SITE) ?> met tout en œuvre pour assurer un service fiable et sécurisé, mais ne peut garantir un accès
                ininterrompu au site. <?= e(NOM_SITE) ?> n'est pas responsable des échanges conclus en dehors de la plateforme
                (paiement direct non prévu, accord privé).
            </p>

            <h2 id="droit">8. Modifications et droit applicable</h2>
            <p>
                Ces conditions peuvent évoluer ; la version en vigueur est celle publiée sur cette page, avec sa date de mise à jour.
                En cas de changement important, les utilisateurs sont prévenus. Les présentes conditions sont soumises au droit
                sénégalais. En cas de litige, une solution amiable est recherchée en priorité ; à défaut, les tribunaux compétents
                de Dakar sont saisis.
            </p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
