<?php
/**
 * SAMA MAILLOT — politique de confidentialité (§7.3, §25 : loi n° 2008-12 sur les données personnelles).
 * Elle décrit les traitements RÉELS du site (données collectées, cookies, durées, destinataires).
 */
require __DIR__ . '/../../includes/config.php';

$titre_page = 'Politique de confidentialité';
$description_page = 'Comment SAMA MAILLOT collecte, utilise et protège vos données personnelles, et comment exercer vos droits.';
$page_active = 'confidentialite';
$email_support = parametre('email_support');
$connexion_sociale = fournisseurs_actifs() !== [];   // section Google / Facebook seulement si la connexion est proposée

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-texte">
    <div class="container">
        <article class="sm-texte-legal">
            <header class="sm-texte-legal__entete">
                <span class="sm-sur-titre">Vos données</span>
                <h1>Politique de confidentialité</h1>
                <p class="text-body-secondary mb-0">Dernière mise à jour : <?= e(DATE_DOCUMENTS_LEGAUX) ?></p>
            </header>

            <p class="sm-encadre-info">
                <strong>En bref :</strong> nous collectons uniquement ce qui est nécessaire pour faire fonctionner la marketplace
                (votre compte, vos commandes, la livraison). Nous ne vendons pas vos données et n'utilisons aucun traceur publicitaire.
                Vous pouvez à tout moment consulter, corriger ou supprimer vos données.
            </p>

            <nav class="sm-sommaire my-4" aria-label="Sommaire">
                <ol>
                    <li><a href="#responsable">Responsable du traitement</a></li>
                    <li><a href="#donnees">Données collectées</a></li>
                    <li><a href="#finalites">Pourquoi nous les utilisons</a></li>
                    <li><a href="#destinataires">Qui peut les voir</a></li>
                    <li><a href="#durees">Combien de temps</a></li>
                    <li><a href="#cookies">Cookies</a></li>
                    <li><a href="#securite">Sécurité</a></li>
                    <li><a href="#droits">Vos droits</a></li>
                    <?php if ($connexion_sociale): ?><li><a href="#google-facebook">Connexion avec Google ou Facebook</a></li><?php endif; ?>
                </ol>
            </nav>

            <h2 id="responsable">1. Responsable du traitement</h2>
            <p>
                Le responsable du traitement est <strong><?= e(nom_editeur()) ?></strong>
                (<?= info_legale('entreprise_adresse', 'Adresse du siège') ?>). Les traitements sont réalisés conformément à la
                loi n° 2008-12 du 25 janvier 2008 sur la protection des données à caractère personnel et déclarés à la Commission
                de protection des données personnelles (CDP) : <?= info_legale('cdp_declaration', 'Numéro de récépissé') ?>.
            </p>

            <h2 id="donnees">2. Données collectées</h2>
            <ul>
                <li><strong>Compte</strong> : prénom, nom, adresse e-mail, mot de passe (enregistré uniquement sous forme chiffrée irréversible), numéro de téléphone.</li>
                <li><strong>Livraison</strong> : adresses (ville, quartier, point de repère) et numéro à appeler pour la livraison.</li>
                <li><strong>Commandes</strong> : articles, montants, statuts, historique, mode de paiement. Pour un paiement en ligne,
                    nous recevons de l'opérateur la référence et le statut du paiement, <strong>jamais</strong> votre code secret ni vos données bancaires complètes.</li>
                <li><strong>Vendeurs</strong> : nom et ville de la boutique, logo, description, numéros WhatsApp et Wave,
                    paiements d'abonnement (identifiant Wave et capture d'écran).</li>
                <li><strong>Contenus</strong> : avis, réclamations (messages et photos), signalements, messages envoyés au support.</li>
                <?php if ($connexion_sociale): ?>
                <li><strong>Connexion avec Google ou Facebook</strong> (si vous la choisissez) : l'identifiant que Google ou Facebook attribue à votre compte
                    pour notre site, votre prénom, votre nom et votre adresse e-mail. Rien d'autre : ni vos amis, ni vos publications, ni vos photos.</li>
                <?php endif; ?>
                <li><strong>Données techniques</strong> : adresse IP et date lors des connexions et des actions sensibles (sécurité, lutte contre la fraude).</li>
            </ul>

            <h2 id="finalites">3. Pourquoi nous les utilisons</h2>
            <ul>
                <li>Créer et sécuriser votre compte (exécution du contrat).</li>
                <li>Transmettre votre commande au vendeur et permettre la livraison (exécution du contrat).</li>
                <li>Encaisser et reverser les paiements en ligne, rembourser en cas de problème (exécution du contrat).</li>
                <li>Vous envoyer les notifications de service : commandes, livraisons, réclamations, abonnement (exécution du contrat).</li>
                <li>Traiter les réclamations, avis et signalements ; prévenir la fraude et les abus (intérêt légitime).</li>
                <li>Respecter nos obligations légales, notamment comptables (obligation légale).</li>
            </ul>
            <p>Nous n'envoyons pas de publicité et ne faisons pas de profilage.</p>

            <h2 id="destinataires">4. Qui peut les voir</h2>
            <ul>
                <li><strong>Le vendeur</strong> chez qui vous commandez : votre nom, votre numéro de téléphone et votre adresse de livraison, uniquement pour cette commande.</li>
                <li><strong>Le public</strong> : vos avis, signés de votre prénom et de l'initiale de votre nom. Votre identité n'est jamais révélée au vendeur que vous signalez.</li>
                <li><strong>L'équipe <?= e(NOM_SITE) ?></strong>, pour le support, la modération et les litiges.</li>
                <li><strong>Nos prestataires</strong>, qui agissent sur nos instructions : l'hébergeur <?= e(HEBERGEUR['nom']) ?> (serveurs et e-mails),
                    l'opérateur de paiement en ligne (Wave, Orange Money, carte…) pour les paiements.</li>
                <li><strong>WhatsApp</strong>, uniquement si vous choisissez de contacter un vendeur ou de commander par WhatsApp.</li>
                <?php if ($connexion_sociale): ?>
                <li><strong>Google ou Facebook</strong>, uniquement si vous choisissez de vous connecter avec eux : ils savent alors que vous utilisez <?= e(NOM_SITE) ?>.</li>
                <?php endif; ?>
            </ul>
            <p>
                Notre hébergeur se trouve en Suisse : vos données sont donc transférées hors du Sénégal, vers un pays
                offrant un niveau de protection des données reconnu, dans le respect de la loi n° 2008-12.
            </p>

            <h2 id="durees">5. Combien de temps</h2>
            <ul>
                <li><strong>Compte</strong> : tant qu'il est actif. À sa suppression, vos données personnelles sont effacées ou anonymisées.</li>
                <li><strong>Commandes et paiements</strong> : 10 ans, durée légale de conservation des documents comptables ; de façon anonymisée si votre compte est supprimé.</li>
                <li><strong>Captures de paiement d'abonnement, photos de réclamation</strong> : le temps du traitement, puis archivées avec la commande ou le paiement concerné.</li>
                <li><strong>Journaux de sécurité</strong> (connexions, adresses IP) : 12 mois maximum.</li>
                <li><strong>Panier d'un visiteur non connecté</strong> : 7 jours.</li>
            </ul>

            <h2 id="cookies">6. Cookies</h2>
            <p>Le site utilise uniquement des cookies <strong>nécessaires à son fonctionnement</strong> ; aucun cookie publicitaire ni de mesure d'audience :</p>
            <ul>
                <li><code>sama_session</code> : garde votre connexion pendant la visite (supprimé à la fermeture du navigateur).</li>
                <li><code>sama_souvenir</code> : uniquement si vous cochez « Rester connecté » (30 jours maximum).</li>
                <li><code>sama_panier</code> : conserve le panier d'un visiteur non connecté (7 jours).</li>
                <li>Le choix du thème clair ou sombre est gardé dans votre navigateur (stockage local), jamais envoyé à nos serveurs.</li>
            </ul>
            <p>Ces cookies étant indispensables, ils ne nécessitent pas de consentement. Vous pouvez les supprimer depuis votre navigateur (vous serez alors déconnecté).</p>

            <h2 id="securite">7. Sécurité</h2>
            <p>
                Connexion chiffrée (HTTPS), mots de passe chiffrés de façon irréversible, codes de livraison chiffrés, protection
                contre les tentatives de connexion répétées, photos ré-encodées (métadonnées et localisation GPS supprimées),
                documents privés (captures de paiement, photos de réclamation) stockés hors du site public et visibles seulement
                par les personnes concernées.
            </p>

            <h2 id="droits">8. Vos droits</h2>
            <p>Vous disposez d'un droit d'accès, de rectification, d'opposition et de suppression de vos données :</p>
            <ul>
                <li>Modifier vos informations depuis <a href="<?= e(url_page('compte')) ?>">Mon compte</a>.</li>
                <li>Supprimer votre compte depuis Mon compte → « Supprimer mon compte ».</li>
                <li>Pour toute autre demande : <a href="<?= e(url_page('contact')) ?>">formulaire de contact</a><?php if ($email_support !== ''): ?>
                    ou <a href="mailto:<?= e($email_support) ?>"><?= e($email_support) ?></a><?php endif; ?>. Nous répondons sous 30 jours.</li>
            </ul>
            <p>
                Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir la Commission de protection des données
                personnelles du Sénégal (CDP).
            </p>
            <?php if ($connexion_sociale): ?>
            <h2 id="google-facebook">9. Connexion avec Google ou Facebook</h2>
            <p>
                Si vous choisissez « Continuer avec Google » ou « Continuer avec Facebook », Google ou Facebook nous confirme votre identité
                (identifiant propre à notre site, prénom, nom, adresse e-mail). Nous ne recevons jamais votre mot de passe Google ou Facebook,
                nous ne gardons aucun jeton d'accès à votre compte et nous ne publions rien en votre nom.
            </p>
            <ul>
                <li>Vous pouvez délier Google ou Facebook à tout moment depuis Mon compte → « Google et Facebook ».</li>
                <li><strong>Supprimer vos données Facebook</strong> : dans Facebook, ouvrez Paramètres → Applications et sites web, retirez
                    <?= e(NOM_SITE) ?> puis demandez la suppression des données. Facebook nous transmet la demande : la liaison avec votre compte
                    Facebook est supprimée immédiatement, et votre compte <?= e(NOM_SITE) ?> est anonymisé s'il n'a plus aucun autre moyen de connexion.
                    Si une commande est en cours, l'anonymisation a lieu après sa clôture ; l'historique des commandes est conservé de façon anonyme
                    (obligation comptable). Facebook vous donne un code et un lien pour suivre l'avancement de la demande.</li>
                <li>Vous pouvez aussi nous écrire depuis le <a href="<?= e(url_page("contact")) ?>">formulaire de contact</a>.</li>
            </ul>
            <?php endif; ?>
            <p>Le site s'adresse aux personnes majeures, ou mineures avec l'accord d'un parent.</p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
