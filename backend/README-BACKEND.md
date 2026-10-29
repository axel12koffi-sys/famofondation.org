# Backend de suivi des dons — Fondation FAMO

PHP 8 + MySQL, conçu pour un hébergement mutualisé cPanel — aucune dépendance,
aucun Composer, aucun Node. Le site public reste 100 % statique ; ce backend
vit à côté (dossier `/backend/`) et sert uniquement au suivi.

## Contenu

| Fichier              | Rôle                                                     |
|----------------------|----------------------------------------------------------|
| `config.example.php` | Modèle de configuration → copier en `config.php`         |
| `install.php`        | Crée les tables + le compte admin (à supprimer ensuite)  |
| `index.php`          | Connexion + tableau de bord (stats, liste, ajout, CSV)   |
| `webhook-stripe.php` | Enregistrement automatique des paiements Stripe          |
| `db.php`, `auth.php` | Connexion PDO, session, CSRF                             |

## Installation (cPanel)

1. **Base de données** : cPanel → Bases de données MySQL → créer une base
   (ex. `famo_dons`), un utilisateur, et lier les deux (tous privilèges).
2. **Fichiers** : téléverser le dossier `backend/` dans `public_html/backend/`.
3. **Configuration** : copier `config.example.php` en `config.php`, renseigner
   les accès MySQL et un vrai mot de passe admin.
4. **Installer** : ouvrir `https://votre-domaine/backend/install.php` une fois,
   puis **supprimer ce fichier** du serveur.
5. **Accéder** : `https://votre-domaine/backend/` → connexion admin.

## Fonctionnalités

- **Tableau de bord** : total collecté, total du mois, nombre de dons,
  donateurs uniques, don moyen, graphique des 12 derniers mois.
- **Saisie manuelle** : dons en espèces, chèques, Zeffy, etc. — avec donateur
  (créé ou retrouvé automatiquement par e-mail).
- **Filtres** par source et par mois ; **export CSV** (compatible Excel).
- **Base donateurs** : nom / e-mail / téléphone, dédupliquée par e-mail.

## Enregistrement automatique par canal

- **Stripe** → automatique via `webhook-stripe.php` :
  Stripe → Développeurs → Webhooks → ajouter l'endpoint
  `https://votre-domaine/backend/webhook-stripe.php` sur l'événement
  `checkout.session.completed`, puis coller le *Signing secret* (`whsec_…`)
  dans `config.php`. Idempotent : un même paiement rejoué n'est jamais compté
  deux fois.
- **Zeffy** → le tableau de bord Zeffy fournit déjà le détail ; pour le suivi
  unifié, saisie manuelle ou import du CSV Zeffy (mêmes colonnes).
- **PayPal** → saisie manuelle depuis l'historique PayPal (un webhook PayPal
  pourra être ajouté plus tard sur le même modèle que Stripe).

## Sécurité

- Mots de passe hachés (`password_hash`), sessions durcies, jetons CSRF sur
  chaque formulaire, requêtes préparées (PDO) partout.
- Signature des webhooks Stripe vérifiée (HMAC SHA-256, tolérance 5 min).
- Recommandé : activer le HTTPS (AutoSSL cPanel) avant la mise en service.
