/*
 * SAMA MAILLOT — choix du thème AVANT l'affichage (évite le flash blanc).
 * Ce fichier est recopié tel quel dans le <head> (script inline) par includes/entete.php.
 * Son empreinte SHA-256 est calculée par includes/securite.php pour l'autoriser dans la CSP :
 * toute modification de ce fichier est donc prise en compte automatiquement.
 */
(function () {
    var racine = document.documentElement;
    var theme = null;

    // 1. Le choix mémorisé par le visiteur (peut échouer : navigation privée, stockage bloqué).
    try {
        theme = localStorage.getItem('sm-theme');
    } catch (erreur) {
        theme = null;
    }

    // 2. Sinon, le thème du téléphone ou de l'ordinateur.
    if (theme !== 'light' && theme !== 'dark') {
        var systemeSombre = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        theme = systemeSombre ? 'dark' : 'light';
    }

    // 3. Bootstrap 5.3 lit cet attribut pour choisir ses couleurs.
    racine.setAttribute('data-bs-theme', theme);

    // 4. Couleur de la barre du navigateur mobile.
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', theme === 'dark' ? '#111111' : '#FFFFFF');
    }
})();
