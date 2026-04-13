# MATELCOM — Site web dynamique PHP/MySQL

## Description
Site web professionnel pour MATELCOM, spécialiste de la vente de matériel informatique au Maroc (RAM, SSD, HDD, logiciels Microsoft, antivirus Kaspersky).

## Fonctionnalités

### Front-end
- Page d'accueil avec hero, catégories, produits, avantages, témoignages, formulaire devis
- Catalogue produits avec filtres par catégorie
- Fiche produit détaillée avec spécifications techniques
- Formulaire de demande de devis avec upload fichier
- Bouton WhatsApp flottant avec message pré-rempli
- Design responsive (mobile, tablette, desktop)
- SEO optimisé (meta tags, sitemap XML, URLs propres)

### Panel Admin (/admin)
- **Tableau de bord** : stats produits, devis, visites
- **Produits** : CRUD complet avec image, spécifications JSON, tags, badges
- **Catégories** : gestion des catégories avec icônes
- **Devis** : consultation, changement de statut, notes admin
- **Témoignages** : gestion avec photo et note
- **Menu** : gestion dynamique du menu de navigation
- **Paramètres** : toutes les infos du site (hero, contact, réseaux, etc.)
- **Mon compte** : changement email/mot de passe
- **Utilisateurs** : gestion des admins (super admin uniquement)

### Sécurité
- Protection CSRF sur tous les formulaires
- Rate limiting sur les formulaires publics
- Sessions sécurisées (httponly, samesite, régénération)
- Honeypot anti-spam
- Validation et sanitisation de toutes les entrées
- Protection des dossiers sensibles via .htaccess

## Installation

### 1. Base de données
```sql
-- Créer la base de données
CREATE DATABASE matelcom_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Importer le schéma
mysql -u root -p matelcom_db < matelcom_db.sql
```

### 2. Configuration
Éditer `includes/config.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'matelcom_db');
define('DB_USER', 'votre_user');
define('DB_PASS', 'votre_password');
define('SITE_URL', 'https://matelcom.ma');
```

### 3. Permissions
```bash
chmod 755 uploads/
chmod 755 var/
```

### 4. Connexion admin
- URL : `/admin/login.php`
- Utilisateur : `admin`
- Mot de passe : `admin123`

### 5. Mailer (optionnel)
Éditer `includes/mailer.php` avec vos identifiants SMTP.

## Technologies
- PHP 8.x
- MySQL / MariaDB
- HTML5 / CSS3
- JavaScript (vanilla)
- Font Awesome 6
- Google Fonts (Poppins + Inter)
- PHPMailer

## Charte graphique
- **Rouge** : #D32F2F (principal)
- **Blanc** : #FFFFFF
- **Gris clair** : #F5F5F5
- **Typographie** : Poppins (titres) + Inter (texte)

## Structure des fichiers
```
matelcom/
├── index.php              # Page d'accueil
├── produit.php            # Fiche produit
├── sitemap.php            # Sitemap XML
├── 404.php                # Page erreur
├── .htaccess              # Réécriture URLs
├── matelcom_db.sql        # Base de données
├── includes/
│   ├── config.php         # Configuration DB
│   ├── functions.php      # Fonctions métier
│   ├── auth.php           # Authentification
│   ├── security.php       # Sécurité (CSRF, rate limit)
│   ├── tracker.php        # Analytics visites
│   └── mailer.php         # Envoi emails
├── admin/
│   ├── _top.php           # Template header admin
│   ├── _bottom.php        # Template footer admin
│   ├── login.php          # Connexion
│   ├── logout.php         # Déconnexion
│   ├── index.php          # Dashboard
│   ├── produits.php       # Liste produits
│   ├── produit_form.php   # Formulaire produit
│   ├── categories.php     # Catégories
│   ├── devis.php          # Devis reçus
│   ├── temoignages.php    # Témoignages
│   ├── menu.php           # Menu navigation
│   ├── parametres.php     # Paramètres site
│   ├── compte.php         # Mon compte
│   └── users.php          # Utilisateurs
├── uploads/               # Fichiers uploadés
│   ├── products/
│   ├── logo/
│   ├── temoignages/
│   └── devis_fichiers/
├── libs/                  # PHPMailer
└── var/                   # Rate limiting
```
