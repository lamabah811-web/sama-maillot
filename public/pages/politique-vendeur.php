<?php
/**
 * SAMA MAILLOT — politique vendeur (§7.3, §9, §15, §18, §21) : abonnement, règles de publication,
 * répliques, commandes, versements et sanctions. Montants et délais lus dans les paramètres.
 */
require __DIR__ . '/../../includes/config.php';

$titre_page = 'Politique vendeur';
$description_page = 'Règles pour vendre sur SAMA MAILLOT : abonnement, publication des maillots, authenticité, commandes, versements et sanctions.';
$page_active = 'politique-vendeur';

$prix = prix_abonnement();
$essai = parametre('essai_gratuit_jours', (string) ESSAI_GRATUIT_JOURS);
$grace = parametre('abonnement_grace_jours', '3');
$seuil = parametre('signalements_avant_suspension', '3');
$periode = parametre('signalements_periode_jours', '90');

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-texte">
    <div class="container">
        <article class="sm-texte-legal">
            <header class="sm-texte-legal__entete">
                <span class="sm-sur-titre">Vendre sur <?= e(NOM_SITE) ?></span>
                <h1>Politique vendeur</h1>
                <p class="text-body-secondary mb-0">Dernière mise à jour : <?= e(DATE_DOCUMENTS_LEGAUX) ?></p>
            </header>

            <p class="sm-encadre-info">
                <strong>L'essentiel :</strong> décrivez honnêtement vos maillots (surtout leur authenticité), gardez votre stock à jour,
                confirmez vite vos commandes et livrez ce qui est annoncé. La confiance des clients fait vivre toutes les boutiques.
            </p>

            <h2>1. Abonnement</h2>
            <ul>
                <li><strong>Essai gratuit de <?= e($essai) ?> jours</strong> à l'ouverture de la boutique (un seul essai par numéro de téléphone).</li>
                <li>Ensuite : <strong><?= e(format_fcfa($prix)) ?> par mois</strong>, payables pour 1, 3 ou 6 mois par Wave depuis « Mon abonnement ».
                    Le paiement est vérifié par l'administration ; la boutique reste visible pendant la vérification.</li>
                <li>Rappel 2 jours avant la fin. Après la fin : <?= e($grace) ?> jours de grâce (maillots visibles, ajout de maillots bloqué),
                    puis la boutique est masquée jusqu'au renouvellement. Vos commandes en cours restent toujours accessibles.</li>
                <li>Un identifiant de transaction Wave ne peut être utilisé qu'une fois.</li>
            </ul>

            <h2>2. Publication des maillots</h2>
            <ul>
                <li><strong>Photos réelles</strong> du maillot vendu, prises par vous (<?= e(parametre('photos_par_maillot', '8')) ?> au maximum, <?= e(PHOTO_MAILLOT_COTE_MIN) ?> × <?= e(PHOTO_MAILLOT_COTE_MIN) ?> px minimum). Les photos volées sont interdites.</li>
                <li>Titre, équipe, saison, type, état, prix en FCFA et stock par taille <strong>exacts et à jour</strong>.</li>
                <li><strong>Authenticité obligatoire</strong> : <em>Officiel</em> (maillot de l'équipementier), <em>Réplique</em> ou <em>Version fan</em>.</li>
                <li>Interdits : contenus trompeurs, injurieux ou sans rapport avec les maillots de football ; prix d'appel fictifs ; coordonnées dans les photos pour contourner la plateforme.</li>
            </ul>

            <h2>3. Répliques et contrefaçon</h2>
            <p>
                Les répliques sont autorisées <strong>uniquement si elles sont déclarées comme telles</strong>. Présenter une réplique comme
                « Officiel » est interdit : le maillot est supprimé et l'infraction compte comme un <strong>manquement grave</strong>,
                pouvant entraîner la suspension du compte.
            </p>

            <h2>4. Commandes et livraison</h2>
            <ul>
                <li>Confirmez ou refusez chaque commande dans les <strong><?= e(parametre('confirmation_vendeur_heures', '24')) ?> heures</strong> ; sinon elle est annulée automatiquement.</li>
                <li>Appelez le client pour organiser la livraison ; respectez les délais et tarifs affichés pour vos zones.</li>
                <?php if (paiement_en_ligne_actif()): ?>
                <li>Paiement en ligne : demandez au client son <strong>code de livraison</strong> à la remise et saisissez-le. Ne demandez jamais de paiement en plus.</li>
                <?php endif; ?>
                <li>Paiement à la livraison : encaissez le montant de la commande à la remise, puis marquez-la livrée.</li>
                <li>En cas de réclamation, répondez sous <?= e(parametre('litige_reponse_vendeur_heures', '48')) ?> heures et cherchez un accord avec le client.</li>
            </ul>

            <?php if (!paiement_en_ligne_actif()): ?>
            <h2>5. Paiement de vos ventes</h2>
            <p>
                Le client vous paie <strong>directement à la livraison</strong>, en espèces ou par Wave. <?= e(NOM_SITE) ?> n'encaisse
                rien pour vous et ne prend aucune commission sur vos ventes.
            </p>
            <?php else: ?>
            <h2>5. Paiements en ligne et versements</h2>
            <ul>
                <li>Vous donnez mandat à <?= e(NOM_SITE) ?> d'encaisser en votre nom le prix des commandes payées en ligne
                    (voir les <a href="<?= e(url_page('cgv')) ?>#paiement">conditions générales de vente</a>).</li>
                <li>L'argent apparaît « en attente » dans votre portefeuille, devient « disponible » à la réception (ou
                    <?= e(parametre('deblocage_avec_code_heures', '24')) ?> h après une livraison prouvée par le code,
                    <?= e(parametre('deblocage_sans_code_heures', '48')) ?> h sans code), et est « bloqué » en cas de réclamation.</li>
                <li>Les versements sont faits le <strong><?= e(str_replace(',', ' et le ', parametre('versement_jours', 'mardi,vendredi'))) ?></strong>
                    sur votre numéro Wave, à partir de <?= e(format_fcfa((int) parametre('versement_minimum_fcfa', '1000'))) ?> disponibles.</li>
                <li>Montant reversé = prix des maillots + frais de livraison − frais de transaction éventuels ; le détail est affiché pour chaque commande.</li>
            </ul>
            <?php endif; ?>

            <h2>6. Avis</h2>
            <p>Les clients ayant reçu une commande peuvent vous noter publiquement. Vous pouvez répondre à chaque avis, avec courtoisie. Demander ou acheter des avis est interdit.</p>

            <h2>7. Signalements et sanctions</h2>
            <ul>
                <li>Un signalement vous est notifié automatiquement (sans révéler le nom du client).</li>
                <li><strong><?= e($seuil) ?> clients différents</strong> en <?= e($periode) ?> jours : votre boutique est <strong>suspendue automatiquement</strong>
                    (maillots masqués, plus de nouvelles commandes). Vous pouvez envoyer une contestation depuis votre tableau de bord ;
                    seule l'administration lève la suspension.</li>
                <li>Une réclamation jugée fondée compte comme un signalement.</li>
                <li>Selon la gravité : avertissement, maillot masqué ou supprimé, suspension ou fermeture de la boutique. Chaque décision est motivée et enregistrée.</li>
            </ul>

            <h2>8. Fermeture</h2>
            <p>
                Vous pouvez fermer votre boutique en supprimant votre compte, une fois vos commandes terminées<?= paiement_en_ligne_actif() ? ' et votre portefeuille versé' : '' ?>.
                L'abonnement déjà payé n'est pas remboursé.
            </p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
