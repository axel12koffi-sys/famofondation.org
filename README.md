# famofondation.org

Site de la Fondation FAMO — HTML, CSS et JavaScript statiques, bilingue FR/EN.

## Structure

```
index.html, *.html          pages françaises
en/                         pages anglaises
assets/css, assets/js       feuille de style et scripts
assets/images/galerie       photos des actions sur le terrain
backend/                    tableau de bord PHP de suivi des dons
.cpanel.yml                 recette de déploiement cPanel
```

## Déploiement

Le dépôt est cloné sur le serveur dans `~/repositories/famofondation.org`.
À chaque déploiement depuis cPanel (Git Version Control → Manage → Deploy HEAD
Commit), `.cpanel.yml` synchronise la racine du dépôt vers `public_html`.

`backend/config.php` n'est pas versionné : il reste sur le serveur et contient
les identifiants de base de données.

## Développement

Aucune dépendance, aucune compilation. Ouvrir `index.html` dans un navigateur,
ou servir le dossier avec n'importe quel serveur statique.

## Modifier le CSS ou le JavaScript

`.htaccess` met en cache les feuilles de style, les scripts et les images pour
un an. Les liens vers `style.css`, `config.js` et `main.js` portent donc un
numéro de version (`?v=20261029`) : **incrémentez-le dans toutes les pages à
chaque modification de ces fichiers**, sinon les visiteurs déjà venus
continueront de charger l'ancienne version.

```bash
# remplacer AAAAMMJJ par la date du jour
sed -i 's/?v=[0-9]\{8\}/?v=AAAAMMJJ/g' *.html en/*.html
```
