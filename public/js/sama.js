/*
 * SAMA MAILLOT — JavaScript commun (vanilla, sans dépendance, sans compilation).
 *
 * Le thème initial est déjà posé par js/theme-init.js (script inline dans le <head>).
 * Ce fichier gère :
 *   1. le bouton de bascule clair / sombre (choix mémorisé dans localStorage) ;
 *   2. le suivi en direct du thème du système tant que le visiteur n'a rien choisi ;
 *   3. la loupe de l'en-tête et le bouton « Recherche » de la barre basse, qui ouvrent la recherche ;
 *   4. le bouton œil des champs mot de passe (afficher / masquer) ;
 *   5. l'ouverture des menus déroulants au survol sur ordinateur ;
 *   6. l'ombre de l'en-tête quand la page défile ;
 *   7. l'envoi automatique des listes « Trier par » ;
 *   8. le carrousel photos de la fiche maillot ;
 *   9. l'ajout de photos en plusieurs fois dans le formulaire maillot du vendeur ;
 *  10. le bouton « Revenir en haut » ;
 *  11. l'ouverture automatique de WhatsApp après une commande via WhatsApp ;
 *  12. la quantité limitée au stock de la taille choisie (fiche maillot) ;
 *  13. les carrousels de cartes (accueil : derniers maillots) ;
 *  14. le lien de paiement Wave de l'abonnement, qui suit la durée choisie ;
 *  15. la vitrine des maillots à l'affiche de l'accueil (défilement automatique) ;
 *  16. les notifications flottantes (messages de succès et d'erreur), qui disparaissent seules.
 */
(function () {
    'use strict';

    var CLE_STOCKAGE = 'sm-theme';
    var COULEURS_BARRE = { light: '#FFFFFF', dark: '#111111' };
    var racine = document.documentElement;
    var requeteSysteme = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    /* Lecture et écriture protégées : localStorage peut être bloqué (navigation privée...). */
    function lireChoix() {
        try {
            var valeur = localStorage.getItem(CLE_STOCKAGE);
            return valeur === 'light' || valeur === 'dark' ? valeur : null;
        } catch (erreur) {
            return null;
        }
    }

    function enregistrerChoix(theme) {
        try {
            localStorage.setItem(CLE_STOCKAGE, theme);
        } catch (erreur) {
            // Pas de mémorisation possible : le thème s'applique quand même sur cette page.
        }
    }

    function themeSysteme() {
        return requeteSysteme && requeteSysteme.matches ? 'dark' : 'light';
    }

    function themeActuel() {
        return racine.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    }

    /* Met à jour les boutons de bascule : état pressé et bulle d'aide. */
    function mettreAJourBoutons(theme) {
        var sombre = theme === 'dark';
        document.querySelectorAll('[data-bascule-theme]').forEach(function (bouton) {
            bouton.setAttribute('aria-pressed', sombre ? 'true' : 'false');
            bouton.setAttribute('title', sombre ? 'Passer en mode clair' : 'Passer en mode sombre');
        });
    }

    function appliquerTheme(theme) {
        racine.setAttribute('data-bs-theme', theme);

        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', COULEURS_BARRE[theme]);
        }

        mettreAJourBoutons(theme);
    }

    function quandPret(action) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', action);
        } else {
            action();
        }
    }

    quandPret(function () {
        // État initial des boutons (le thème a déjà été choisi avant l'affichage).
        mettreAJourBoutons(themeActuel());

        // Les transitions de couleur ne sont activées qu'après le premier affichage :
        // le chargement de la page n'est jamais animé, seule la bascule l'est.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                racine.classList.add('sm-transitions');
            });
        });

        // 1. Bascule manuelle : le choix est mémorisé et prend le pas sur le système.
        document.querySelectorAll('[data-bascule-theme]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                var nouveau = themeActuel() === 'dark' ? 'light' : 'dark';
                enregistrerChoix(nouveau);
                appliquerTheme(nouveau);
            });
        });

        // 5. Menus déroulants de l'en-tête : ouverture au SURVOL sur ordinateur (souris, ≥ 1200 px).
        //    Le clic, le clavier (Entrée, flèches, Échap) et le tactile fonctionnent toujours
        //    grâce à Bootstrap. Un court délai évite que le menu se ferme quand la souris
        //    passe du bouton au menu.
        var survolPossible = window.matchMedia
            && window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 1200px)');

        document.querySelectorAll('.sm-entete .dropdown').forEach(function (zone) {
            var bouton = zone.querySelector('[data-bs-toggle="dropdown"]');
            var minuterie = null;

            if (!bouton || !window.bootstrap || !window.bootstrap.Dropdown) {
                return;
            }

            zone.addEventListener('mouseenter', function () {
                if (!survolPossible || !survolPossible.matches) {
                    return;
                }
                window.clearTimeout(minuterie);
                window.bootstrap.Dropdown.getOrCreateInstance(bouton).show();
            });

            // Menu déjà ouvert par le survol : le clic ne doit pas le refermer aussitôt.
            bouton.addEventListener('click', function (evenement) {
                if (survolPossible && survolPossible.matches && zone.querySelector('.dropdown-menu.show')
                    && zone.matches(':hover')) {
                    evenement.preventDefault();
                    evenement.stopPropagation();
                }
            });

            zone.addEventListener('mouseleave', function () {
                if (!survolPossible || !survolPossible.matches) {
                    return;
                }
                minuterie = window.setTimeout(function () {
                    window.bootstrap.Dropdown.getOrCreateInstance(bouton).hide();
                    bouton.blur();
                }, 180);
            });
        });

        // 7. Listes « Trier par » : le formulaire est envoyé dès qu'on change la valeur.
        //    (Sans JavaScript, le bouton « Appliquer » des filtres fonctionne toujours.)
        document.querySelectorAll('[data-envoi-auto]').forEach(function (champ) {
            champ.addEventListener('change', function () {
                var formulaire = champ.form;
                if (formulaire) {
                    if (formulaire.requestSubmit) {
                        formulaire.requestSubmit();
                    } else {
                        formulaire.submit();
                    }
                }
            });
        });

        // 8. Carrousel de la fiche maillot (Bootstrap) : miniature et compteur suivent la photo
        //    affichée ; le zoom montre la photo affichée.
        var carrousel = document.getElementById('carrousel-maillot');
        if (carrousel) {
            var miniatures = document.querySelectorAll('[data-bs-target="#carrousel-maillot"][data-bs-slide-to]');
            var position = carrousel.querySelector('[data-carrousel-position]');

            carrousel.addEventListener('slid.bs.carousel', function (evenement) {
                miniatures.forEach(function (miniature, rang) {
                    if (rang === evenement.to) {
                        miniature.setAttribute('aria-current', 'true');
                        miniature.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                    } else {
                        miniature.removeAttribute('aria-current');
                    }
                });
                if (position) {
                    position.textContent = String(evenement.to + 1);
                }
            });

            var zoom = document.getElementById('zoom-photo');
            var photoZoom = document.querySelector('[data-galerie-zoom]');
            if (zoom && photoZoom) {
                zoom.addEventListener('show.bs.modal', function () {
                    var affichee = carrousel.querySelector('.carousel-item.active img');
                    if (affichee) {
                        photoZoom.src = affichee.currentSrc || affichee.src;
                        photoZoom.alt = affichee.alt;
                    }
                });
            }
        }

        // 9. Photos d'un maillot (espace vendeur) : les photos choisies en plusieurs fois
        //    s'ajoutent au lieu de se remplacer, avec un aperçu et un bouton « Retirer ».
        var champPhotos = document.querySelector('[data-ajout-photos]');
        if (champPhotos && typeof DataTransfer === 'function') {
            var apercu = document.getElementById(champPhotos.getAttribute('data-ajout-photos'));
            var aideRestante = document.querySelector('[data-photos-restantes]');
            var maximum = parseInt(champPhotos.getAttribute('data-photos-max'), 10) || 1;
            var selection = [];

            var placesLibres = function () {
                return maximum - document.querySelectorAll('[data-photo-existante]:not(:checked)').length;
            };

            var afficherSelection = function () {
                var transfert = new DataTransfer();
                selection.forEach(function (fichier) {
                    transfert.items.add(fichier);
                });
                champPhotos.files = transfert.files;

                apercu.textContent = '';
                selection.forEach(function (fichier, rang) {
                    var carte = document.createElement('div');
                    carte.className = 'sm-photo-vendeur';

                    var image = document.createElement('img');
                    image.alt = '';
                    image.width = 400;
                    image.height = 400;
                    var lecteur = new FileReader();
                    lecteur.onload = function () {
                        image.src = lecteur.result;   // data: (autorisé par la politique de sécurité)
                    };
                    lecteur.readAsDataURL(fichier);

                    var retirer = document.createElement('button');
                    retirer.type = 'button';
                    retirer.className = 'btn btn-sm btn-contour w-100';
                    retirer.textContent = 'Retirer';
                    retirer.setAttribute('aria-label', 'Retirer la photo ' + fichier.name);
                    retirer.addEventListener('click', function () {
                        selection.splice(rang, 1);
                        afficherSelection();
                    });

                    var nouvelle = document.createElement('span');
                    nouvelle.className = 'small text-body-secondary d-block mb-1';
                    nouvelle.textContent = 'Nouvelle photo';

                    carte.appendChild(image);
                    carte.appendChild(nouvelle);
                    carte.appendChild(retirer);
                    apercu.appendChild(carte);
                });
                apercu.hidden = selection.length === 0;

                if (aideRestante) {
                    var reste = Math.max(0, placesLibres() - selection.length);
                    aideRestante.textContent = reste > 0
                        ? 'Vous pouvez encore ajouter ' + reste + ' photo' + (reste > 1 ? 's' : '') + '.'
                        : 'Nombre maximum de photos atteint.';
                }
            };

            champPhotos.addEventListener('change', function () {
                Array.prototype.forEach.call(champPhotos.files, function (fichier) {
                    var dejaChoisie = selection.some(function (autre) {
                        return autre.name === fichier.name && autre.size === fichier.size;
                    });
                    if (!dejaChoisie && selection.length < placesLibres()) {
                        selection.push(fichier);
                    }
                });
                afficherSelection();
            });

            document.querySelectorAll('[data-photo-existante]').forEach(function (caseRetirer) {
                caseRetirer.addEventListener('change', afficherSelection);
            });
        }

        // 10. Bouton « Revenir en haut » : visible après un écran et demi de défilement.
        var boutonHaut = document.querySelector('[data-haut-de-page]');
        if (boutonHaut) {
            var mouvementReduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var majBoutonHaut = function () {
                var visible = window.scrollY > window.innerHeight * 1.5;
                if (visible) {
                    boutonHaut.hidden = false;
                    window.requestAnimationFrame(function () {
                        boutonHaut.classList.add('sm-haut-de-page--visible');
                    });
                } else if (!boutonHaut.hidden) {
                    boutonHaut.classList.remove('sm-haut-de-page--visible');
                    boutonHaut.hidden = true;
                }
            };
            window.addEventListener('scroll', majBoutonHaut, { passive: true });
            majBoutonHaut();

            boutonHaut.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: mouvementReduit ? 'auto' : 'smooth' });
                // Le clavier repart du début de la page (lien d'évitement ou logo).
                var premier = document.querySelector('a[href], button');
                if (premier) {
                    premier.focus({ preventScroll: true });
                }
            });
        }

        // 13. Carrousels de cartes (accueil) : flèches = défilement d'une « page » ; elles se
        //     désactivent au début et à la fin de la piste.
        document.querySelectorAll('[data-carrousel-cartes]').forEach(function (carrousel) {
            var piste = carrousel.querySelector('.sm-carrousel-cartes__piste');
            var precedente = carrousel.querySelector('[data-defiler="-1"]');
            var suivante = carrousel.querySelector('[data-defiler="1"]');
            if (!piste) {
                return;
            }
            var mouvementReduit = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            var majFleches = function () {
                var fin = piste.scrollWidth - piste.clientWidth - 4;
                if (precedente) {
                    precedente.disabled = piste.scrollLeft <= 4;
                }
                if (suivante) {
                    suivante.disabled = piste.scrollLeft >= fin;
                }
            };

            [precedente, suivante].forEach(function (fleche) {
                if (!fleche) {
                    return;
                }
                fleche.addEventListener('click', function () {
                    var sens = parseInt(fleche.getAttribute('data-defiler'), 10) || 1;
                    piste.scrollBy({ left: sens * piste.clientWidth * 0.85, behavior: mouvementReduit ? 'auto' : 'smooth' });
                });
            });

            piste.addEventListener('scroll', majFleches, { passive: true });
            window.addEventListener('resize', majFleches);
            majFleches();
        });

        // 12. Fiche maillot : la liste « Quantité » ne propose pas plus que le stock de la taille choisie.
        var champQuantite = document.querySelector('[data-quantite-max]');
        if (champQuantite) {
            var plafond = parseInt(champQuantite.getAttribute('data-quantite-max'), 10) || 10;
            var limiterQuantite = function (stock) {
                var choisie = parseInt(champQuantite.value, 10) || 1;
                var maximum = Math.max(1, Math.min(plafond, stock));
                champQuantite.textContent = '';
                for (var q = 1; q <= maximum; q++) {
                    var option = document.createElement('option');
                    option.value = String(q);
                    option.textContent = String(q);
                    option.selected = q === Math.min(choisie, maximum);
                    champQuantite.appendChild(option);
                }
            };
            document.querySelectorAll('input[name="taille"][data-stock]').forEach(function (taille) {
                taille.addEventListener('change', function () {
                    limiterQuantite(parseInt(taille.getAttribute('data-stock'), 10) || 1);
                });
                if (taille.checked) {
                    limiterQuantite(parseInt(taille.getAttribute('data-stock'), 10) || 1);
                }
            });
        }

        // 14. Abonnement vendeur : le lien Wave suit la durée choisie (montant pré-rempli).
        var boutonWave = document.querySelector('[data-bouton-wave]');
        if (boutonWave) {
            document.querySelectorAll('input[name="mois"][data-lien-wave]').forEach(function (duree) {
                duree.addEventListener('change', function () {
                    if (duree.getAttribute('data-lien-wave')) {
                        boutonWave.href = duree.getAttribute('data-lien-wave');
                        boutonWave.querySelector('[data-montant-wave]').textContent = duree.getAttribute('data-montant');
                    }
                });
            });
        }

        // 15. Vitrine de l'accueil : les maillots à l'affiche défilent tout seuls (toutes les 2 s) :
        // chacun arrive par le bas à droite, se pose au centre puis repart vers le haut à gauche.
        // Pause seulement quand un visiteur au clavier est sur le lien (le temps de l'utiliser).
        var vitrine = document.querySelector('[data-vitrine]');
        var maillotsVitrine = vitrine ? vitrine.querySelectorAll('[data-vitrine-maillot]') : [];
        if (maillotsVitrine.length > 1) {
            var rangVitrine = 0;
            var pointsVitrine = vitrine.querySelectorAll('[data-vitrine-point]');
            var vitrineEnPause = false;

            var afficherMaillot = function (rang) {
                var sortant = maillotsVitrine[rangVitrine];
                rangVitrine = rang % maillotsVitrine.length;
                if (sortant !== maillotsVitrine[rangVitrine]) {
                    sortant.classList.add('sm-vitrine__maillot--sortant');
                    // Une fois invisible, il retourne en attente (en bas à droite) sans traverser l'écran.
                    window.setTimeout(function () {
                        sortant.classList.add('sm-vitrine__maillot--sans-transition');
                        sortant.classList.remove('sm-vitrine__maillot--sortant');
                        void sortant.offsetWidth;
                        sortant.classList.remove('sm-vitrine__maillot--sans-transition');
                    }, 650);
                }
                maillotsVitrine.forEach(function (maillot, i) {
                    var actif = i === rangVitrine;
                    maillot.classList.toggle('sm-vitrine__maillot--actif', actif);
                    maillot.setAttribute('aria-hidden', actif ? 'false' : 'true');
                    var lien = maillot.querySelector('a');
                    if (lien) {
                        if (actif) {
                            lien.removeAttribute('tabindex');
                        } else {
                            lien.setAttribute('tabindex', '-1');
                        }
                    }
                });
                pointsVitrine.forEach(function (point, i) {
                    point.classList.remove('sm-vitrine__point--actif');
                    if (i === rangVitrine) {
                        void point.offsetWidth; // relance l'animation de remplissage
                        point.classList.add('sm-vitrine__point--actif');
                    }
                });
            };

            // Bannière hors de l'écran : animations et défilement en pause (économise le processeur).
            var vitrineHorsEcran = false;
            if ('IntersectionObserver' in window) {
                new IntersectionObserver(function (entrees) {
                    vitrineHorsEcran = !entrees[0].isIntersecting;
                    vitrine.classList.toggle('sm-visuel--pause', vitrineHorsEcran);
                }).observe(vitrine);
            }

            window.setInterval(function () {
                if (!vitrineEnPause && !vitrineHorsEcran) {
                    afficherMaillot(rangVitrine + 1);
                }
            }, 2000);
            vitrine.addEventListener('focusin', function () { vitrineEnPause = true; });
            vitrine.addEventListener('focusout', function () { vitrineEnPause = false; });
        }

        // 16. Notifications flottantes (messages de succès / d'erreur) : disparition automatique et croix.
        var fermerNotification = function (toast) {
            toast.classList.add('sm-toast--sortie');
            window.setTimeout(function () {
                toast.remove();
            }, 300);
        };
        document.querySelectorAll('[data-toast]').forEach(function (toast) {
            var duree = parseInt(toast.getAttribute('data-toast-duree'), 10);
            var minuterie = duree > 0 ? window.setTimeout(function () { fermerNotification(toast); }, duree) : null;
            // Survol ou clavier : le message reste le temps de le lire.
            toast.addEventListener('mouseenter', function () { window.clearTimeout(minuterie); });
            toast.addEventListener('focusin', function () { window.clearTimeout(minuterie); });
            var bouton = toast.querySelector('[data-toast-fermer]');
            if (bouton) {
                bouton.addEventListener('click', function () { fermerNotification(toast); });
            }
        });

        // 11. Commande via WhatsApp : ouverture automatique de la discussion (le bouton reste en secours).
        var ouvertureAuto = document.querySelector('[data-ouverture-auto]');
        if (ouvertureAuto) {
            window.setTimeout(function () {
                window.location.href = ouvertureAuto.href;
            }, 1200);
        }

        // 6. Ombre sous l'en-tête dès que la page défile.
        var entete = document.querySelector('.sm-entete');
        if (entete) {
            var surDefilement = function () {
                entete.classList.toggle('sm-entete--defile', window.scrollY > 8);
            };
            window.addEventListener('scroll', surDefilement, { passive: true });
            surDefilement();
        }

        // 4. Bouton œil : afficher / masquer un mot de passe (§8.6).
        document.querySelectorAll('[data-afficher-mdp]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                var champ = document.getElementById(bouton.getAttribute('data-afficher-mdp'));
                if (!champ) {
                    return;
                }
                var visible = champ.type === 'password';
                champ.type = visible ? 'text' : 'password';
                bouton.setAttribute('aria-pressed', visible ? 'true' : 'false');
                bouton.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
                var icone = bouton.querySelector('.bi');
                if (icone) {
                    icone.className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
                }
            });
        });

        // 3. Recherche : la loupe de l'en-tête ouvre la barre sous l'en-tête (curseur placé dans le champ,
        //    Échap la referme) ; « Recherche » dans la barre basse (mobile) l'ouvre aussi.
        var voletRecherche = document.getElementById('volet-recherche');
        var champRecherche = document.getElementById('champ-recherche');
        if (voletRecherche && champRecherche && window.bootstrap) {
            var repliRecherche = window.bootstrap.Collapse.getOrCreateInstance(voletRecherche, { toggle: false });
            voletRecherche.addEventListener('shown.bs.collapse', function () {
                champRecherche.focus();
            });
            voletRecherche.addEventListener('keydown', function (evenement) {
                if (evenement.key === 'Escape') {
                    repliRecherche.hide();
                    var loupe = document.querySelector('[data-bs-target="#volet-recherche"]');
                    if (loupe) {
                        loupe.focus();
                    }
                }
            });
            document.querySelectorAll('[data-aller-recherche]').forEach(function (lien) {
                lien.addEventListener('click', function (evenement) {
                    evenement.preventDefault();
                    window.scrollTo({ top: 0 });
                    if (voletRecherche.classList.contains('show')) {
                        champRecherche.focus();
                    } else {
                        repliRecherche.show();
                    }
                });
            });
        }
    });

    // 2. Le visiteur n'a rien choisi : on suit le système en direct (ex. mode nuit automatique).
    function surChangementSysteme() {
        if (lireChoix() === null) {
            appliquerTheme(themeSysteme());
        }
    }

    if (requeteSysteme) {
        if (requeteSysteme.addEventListener) {
            requeteSysteme.addEventListener('change', surChangementSysteme);
        } else if (requeteSysteme.addListener) {
            requeteSysteme.addListener(surChangementSysteme); // anciens Safari iOS
        }
    }

    // Choix fait dans un autre onglet : on l'applique aussi ici.
    window.addEventListener('storage', function (evenement) {
        if (evenement.key === CLE_STOCKAGE) {
            appliquerTheme(lireChoix() || themeSysteme());
        }
    });
})();
