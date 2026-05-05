# RÉUSSITE+

Plateforme d'apprentissage et de préparation aux examens officiels congolais — **ENAFEP**, **TENASOSP** et **Examen d'État**.

> Ciblant les élèves du primaire et du secondaire de la République Démocratique du Congo, avec un modèle **freemium** basé sur Mobile Money.

---

## Aperçu

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.2 (sans framework) |
| Base de données | MariaDB 10.4 |
| Frontend | HTML5 · CSS3 · Vanilla JS |
| Icônes | [Lucide](https://lucide.dev) (CDN) |
| Typographies | Poppins (titres) · Inter (corps) |
| IA | Groq API — `llama-3.1-8b-instant` |
| Serveur | Apache via XAMPP |

Aucune dépendance Composer, npm ou framework CSS — le projet est **100 % autonome**.

---

## Fonctionnalités

- **Examens blancs** par matière, niveau et type (ENAFEP / TENASOSP / Examen d'État)
- **Correction détaillée** avec explications par question
- **Progression** — suivi des scores par matière avec graphiques
- **Archives** — annales officielles téléchargeables (plans payants)
- **IA pédagogique** — conseils de révision personnalisés via Groq (plans Premium / École)
- **Notifications** en temps réel
- **Onboarding** modal au premier accès
- **Administration** — gestion des utilisateurs, paiements et archives
- **Freemium** — 4 plans : Gratuit · Basique (5 000 CDF) · Premium (10 000 CDF) · École (50 000 CDF)
- **Mobile Money** — M-Pesa · Airtel Money · Orange Money

---

## Prérequis

- [XAMPP](https://www.apachefriends.org/) avec PHP 8.2 et MariaDB 10.4
- PHP extension `pdo_mysql` activée

---

## Installation

```bash
# 1. Cloner dans le dossier htdocs de XAMPP
git clone https://github.com/codexripple/reussiteplus.git C:/xampp/htdocs/reussiteplus

# 2. Créer la base de données
# Dans phpMyAdmin ou via MySQL CLI :
# mysql -u root -p < setup_db.sql
# mysql -u root -p reussiteplus < schema.sql

# 3. Configurer les variables d'environnement
cp .env.example .env
# Éditer .env et renseigner votre clé Groq API

# 4. (Optionnel) Insérer les données de démonstration
# Ouvrir http://localhost/reussiteplus/seed.php
```

### Fichier `.env`

```env
GROQ_API_KEY=gsk_votre_cle_ici
```

Obtenir une clé gratuite sur [console.groq.com](https://console.groq.com).

---

## Structure du projet

```
reussiteplus/
├── index.php            # Landing page publique
├── connexion.php        # Authentification
├── inscription.php      # Création de compte
├── dashboard.php        # Tableau de bord élève
├── examen.php           # Lancer un examen
├── resultat.php         # Résultats & corrections
├── questions.php        # Banque de questions
├── archives.php         # Annales
├── progression.php      # Suivi de progression + IA
├── notifications.php    # Centre de notifications
├── abonnement.php       # Gestion de l'abonnement
├── paiement.php         # Paiement Mobile Money
├── tarifs.php           # Plans & tarifs
├── admin/               # Interface d'administration
│   ├── index.php
│   ├── users.php
│   ├── paiements.php
│   └── archives.php
├── api/                 # Endpoints AJAX internes
│   ├── notifications.php
│   ├── archives.php
│   └── signets.php
├── includes/
│   ├── config.php       # Configuration centrale + plans
│   ├── db.php           # Connexion PDO
│   ├── auth.php         # Vérification session / plan
│   ├── helpers.php      # Fonctions utilitaires
│   ├── header_app.php   # En-tête commun
│   └── footer_app.php   # Pied de page + Lucide + JS
├── assets/
│   ├── css/app.css      # Design system (variables CSS)
│   └── js/app.js        # Scripts globaux
├── schema.sql           # Schéma complet de la BDD
├── setup_db.sql         # Création de la base
├── seed.php             # Données de démonstration
└── .env.example         # Template des variables d'environnement
```

---

## Plans & Tarifs

| Plan | Prix | Examens/mois | Archives | IA |
|------|------|:---:|:---:|:---:|
| Gratuit | 0 CDF | 5 | — | — |
| Basique | 5 000 CDF/mois | 30 | ✓ | — |
| Premium | 10 000 CDF/mois | Illimité | ✓ | ✓ |
| École | 50 000 CDF/mois | Illimité (50 élèves) | ✓ | ✓ |

---

## Compte de démonstration

| Champ | Valeur |
|-------|--------|
| Email | `demo@reussiteplus.cd` |
| Mot de passe | `demo1234` |
| Plan | Premium |
| Nom | Amani Kanda |

---

## Sécurité

- Mots de passe hachés en **bcrypt** (coût 12)
- Requêtes SQL via **PDO prepared statements** (pas d'injection SQL)
- Token **CSRF** sur tous les formulaires
- Cookies de session `HttpOnly` + `SameSite=Lax`
- Clé API hors du dépôt (fichier `.env` ignoré par Git)

---

## Licence

Projet éducatif — usage libre pour les établissements scolaires de la RDC.
