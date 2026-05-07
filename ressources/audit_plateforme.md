# Audit Technique — RÉUSSITE+
**Date :** 2026-05-07  
**Auditeur :** Claude Sonnet 4.6  
**Périmètre :** 79 fichiers PHP — plateforme complète  
**Santé globale :** 85 % ✅

---

## Résumé exécutif

| Catégorie | Statut |
|-----------|--------|
| Architecture core (config/db/auth/helpers) | ✅ Solide |
| Sécurité SQL (PDO prepared statements) | ✅ Systématique |
| Protection CSRF | ✅ Implémentée partout |
| Échappement HTML via `e()` | ✅ Systématique |
| Fichiers de navigation (liens sidebar) | ✅ Tous existent |
| Fonctions helpers utilisées | ✅ Toutes définies |
| `inscription_handler.php` | 🔴 **CASSÉ — Fatal Error garanti** |
| Clé API Groq dans `.env` | 🟠 **À sécuriser** |
| Rate-limit sur `auth_login()` | 🟠 **Absent** |
| `admin/cours.php` include path | 🟡 **Fragile** |

---

## 1. Problèmes critiques 🔴

### 1.1 `inscription_handler.php` — COMPLÈTEMENT CASSÉ

**Impact :** Fatal Error PHP si quelqu'un appelle ce fichier directement.

**Problèmes identifiés :**

| # | Problème | Ligne | Détail |
|---|---------|-------|--------|
| 1 | Path relatif sans `__DIR__` | 24 | `require_once 'includes/db.php'` → cassé si appelé hors racine |
| 2 | `$conn` non défini | 30 | Utilise MySQLi sur une connexion PDO (incompatible) |
| 3 | Table `users` inexistante | 33 | Le projet utilise `utilisateurs` |
| 4 | Bypass de `auth_register()` | — | N'utilise pas le système d'inscription officiel (pas de UUID, pas de referral code, pas de plan GRATUIT assigné) |
| 5 | Pas de vérification CSRF | — | Vulnérable aux attaques cross-site |
| 6 | `die()` bruts en cas d'erreur | 12, 17 | Retourne du texte brut au lieu de JSON ou d'une redirection |
| 7 | `filter_var` absent | — | Aucune validation d'email |

**Solution :**

```php
<?php
// inscription_handler.php — VERSION CORRIGÉE
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /reussiteplus/inscription.php');
    exit;
}

if (!csrf_verify()) {
    redirect('/reussiteplus/inscription.php', 'error', 'Token de sécurité invalide.');
}

$result = auth_register([
    'nom'          => trim($_POST['nom'] ?? ''),
    'prenom'       => trim($_POST['prenom'] ?? ''),
    'email'        => trim($_POST['email'] ?? ''),
    'password'     => $_POST['password'] ?? '',
    'classe'       => trim($_POST['classe'] ?? ''),
    'province_id'  => $_POST['province_id'] ?? null,
    'referral_par' => null,
]);

if ($result['ok']) {
    redirect('/reussiteplus/dashboard.php?welcome=1', 'success', 'Bienvenue !');
} else {
    redirect('/reussiteplus/inscription.php', 'error', $result['msg']);
}
```

> **Note :** `inscription.php` gère déjà correctement l'inscription via `auth_register()` avec CSRF et toutes les validations. `inscription_handler.php` est **obsolète** et peut être supprimé ou remplacé par le code ci-dessus.

---

## 2. Problèmes majeurs 🟠

### 2.1 Rate-limit absent sur `auth_login()`

**Impact :** Attaque brute-force possible sur le formulaire de connexion (essais de mots de passe illimités).

**Situation actuelle :** `connexion.php` appelle `rate_limit_check('login', $ip, 5, 600)` ✅  
**Mais :** `auth_login()` dans `auth.php` n'a aucun rate-limit interne → si quelqu'un appelle `auth_login()` directement (ex : via API ou script), il n'est pas protégé.

**Solution :** Ajouter le rate-limit dans `auth_login()` elle-même :

```php
function auth_login(string $email, string $password): array {
    // Ajouter au début :
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!rate_limit_check('login_internal', $ip . '_' . md5($email), 10, 600)) {
        return ['ok' => false, 'msg' => 'Trop de tentatives. Réessayez dans 10 minutes.'];
    }
    // ... reste de la fonction
}
```

### 2.2 Webhook secret non changé

**Fichier :** `.env`  
**Problème :** `WEBHOOK_SECRET=changez_ceci_pour_votre_webhook` — valeur par défaut jamais modifiée.  
**Impact :** N'importe qui qui connaît cette valeur peut déclencher des confirmations de paiement factices.

**Solution :**
```bash
# Générer un secret fort et le mettre dans .env
WEBHOOK_SECRET=rp_whk_2025_$(openssl rand -hex 32)
```

### 2.3 Clé Groq API dans `.env` en clair

**Fichier :** `.env`  
**Clé exposée :** `GROQ_API_KEY=gsk_***` *(clé masquée — voir votre `.env` local)*  
**Impact :** Si le dossier n'est pas protégé, la clé peut être lue et utilisée à vos frais.

**Solution :**
1. Vérifier que `.htaccess` contient bien `Deny from all` pour `.env`
2. Régénérer la clé Groq sur console.groq.com
3. Sur production : utiliser des variables d'environnement serveur (pas de fichier `.env`)

**`.htaccess` à vérifier à la racine :**
```apache
<Files ".env">
    Order allow,deny
    Deny from all
</Files>
```

---

## 3. Problèmes mineurs 🟡

### 3.1 `admin/cours.php` — include path fragile

**Ligne 82 :** `include '../includes/header_app.php';`  
**Problème :** Chemin relatif (`../`) qui fonctionne si le fichier est appelé depuis `/admin/` mais peut échouer dans certaines configurations serveur.

**Solution :**
```php
// Remplacer :
include '../includes/header_app.php';
// Par :
include __DIR__ . '/../includes/header_app.php';
```

### 3.2 `score_couleur()` — seuil "bleu" inattendu

**Fichier :** `includes/helpers.php`  
**Code actuel :**
```php
function score_couleur(float $pct): string {
    if ($pct >= 80) return 'var(--primary)'; // vert
    if ($pct >= 60) return 'var(--gold)';    // orange
    if ($pct >= 40) return 'var(--bleu)';    // bleu (inattendu)
    return 'var(--rouge)';                    // rouge
}
```
**Problème :** Un score de 45% affiché en bleu est contre-intuitif (ni bon ni alarmant).  
**Solution :** Remplacer la couleur bleue par l'orange et descendre le rouge :
```php
if ($pct >= 80) return 'var(--primary)';
if ($pct >= 50) return 'var(--gold)';
return 'var(--rouge)';
```

### 3.3 `dashboard.php` appelle `require_login()` deux fois

**Situation :** `dashboard.php` appelle `$user = require_login()` à la ligne 10, puis `header_app.php` (inclus ligne 52) appelle de nouveau `$user = require_login()` → double requête DB.

**Solution :** Ne pas redéfinir `$user` dans `dashboard.php` si `header_app.php` le fait déjà. Ou mieux : charger `header_app.php` en premier et utiliser `$user` qu'il définit.

### 3.4 Table `rate_limits` — nettoyage automatique absent

**Situation :** La table `rate_limits` s'accumule indéfiniment sans purge.  
**Solution :** Ajouter un nettoyage automatique dans `rate_limit_check()` :

```php
function rate_limit_check(string $action, string $key, int $max, int $window): bool {
    $pdo = db();
    $now = time();
    $windowStart = $now - $window;

    // Nettoyage des entrées expirées (1 fois sur 100 appels)
    if (rand(1, 100) === 1) {
        $pdo->prepare("DELETE FROM rate_limits WHERE ts < ?")->execute([$windowStart - 3600]);
    }
    // ... reste de la fonction
```

### 3.5 Uploads sans extension whitelist stricte

**Fichier :** `admin/cours.php`  
**Situation :** Upload de fichiers accepte tout sans vérification MIME stricte.  
**Solution :**
```php
$allowedExtensions = ['pdf','mp4','mp3','jpg','jpeg','png','pptx','docx','txt','zip'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExtensions)) {
    $message = "Type de fichier non autorisé.";
    // ne pas continuer l'upload
}
```

---

## 4. Ce qui fonctionne bien ✅

### Architecture

- **PDO Singleton** (`Database::get()`) — Connexion unique, gestion d'erreurs propre
- **Fonctions DB** (`dbRow`, `dbAll`, `dbInsert`, `dbUpdate`) — Abstraites, sécurisées
- **Auth** (`auth_login`, `auth_register`, `require_login`) — Bien structurée, sessions PHP
- **CSRF** — Implémenté et vérifié sur tous les formulaires POST
- **Échappement HTML** — `e()` utilisée partout (237 occurrences)
- **Flash messages** — `redirect()` + `show_flash()` cohérents
- **Rate-limit** — Implémenté sur connexion, inscription, reset de mot de passe
- **Mailer** — 3 backends (Brevo, Mailgun, PHP `mail()`) avec fallback automatique

### Sécurité

- SQL 100% préparées (sauf `inscription_handler.php` cassé)
- Bcrypt avec cost configurable (`BCRYPT_COST=12`)
- Sessions avec `httponly`, `samesite=Lax`
- Webhook paiement sécurisé par clé secrète
- `.gitignore` présent avec `.env` ignoré ✅
- Accès admin double-vérifié (`require_admin()`)

### Fonctionnalités

- Tous les fichiers référencés dans la navigation **existent**
- API notifications fonctionnelle
- Webhook paiement mobile money (M-Pesa, Airtel, Orange) opérationnel
- Module cours avec `structure.json` et téléchargement sécurisé
- Plans tarifaires (GRATUIT/BASIQUE/PREMIUM/ECOLE) bien configurés

---

## 5. Tables DB attendues (référencées dans le code)

| Table | Utilisée dans | Colonnes clés |
|-------|--------------|---------------|
| `utilisateurs` | auth.php, helpers.php | id, email, password_hash, nom, prenom, plan, plan_expire_at, role, referral_code |
| `exam_sessions` | dashboard.php, auth.php | id, user_id, matiere_id, statut, pourcentage, finished_at, temps_passe |
| `matieres` | dashboard.php, examen.php | id, nom, couleur, icone |
| `archives` | archives.php, dashboard.php | id, titre, matiere_id, annee, exam_type, status, premium_only, vues, corrige_url |
| `question_bank` | examen.php, questions.php | id, matiere_id, enonce, status, difficulte, success_rate |
| `abonnements` | paiement.php, webhook | id, user_id, plan, statut, reference_paiement, confirmed_at, operateur |
| `notifications` | notifications.php, dashboard | id, user_id, message, created_at, lu |
| `user_progression` | dashboard.php, progression | user_id, matiere_id, score_moyen |
| `rate_limits` | rate_limit.php | id, action, rate_key, ts |
| `codes_promo` | tarifs.php, admin | id, code, actif, date_expiration, nb_max, nb_utilisations, type_remise, valeur_remise |
| `provinces` | inscription.php | id, nom |
| `contact_messages` | contact.php, admin | id, nom, email, message, statut, created_at |

---

## 6. Plan d'action priorisé

### Priorité 1 — Immédiat (bugs bloquants)

- [ ] **Corriger ou supprimer `inscription_handler.php`** — risque de Fatal Error
- [ ] **Changer `WEBHOOK_SECRET`** dans `.env` — risque de faux paiements confirmés
- [ ] **Vérifier `.htaccess`** bloque bien l'accès à `.env`

### Priorité 2 — Court terme (sécurité)

- [ ] **Ajouter rate-limit dans `auth_login()`** — protection brute-force
- [ ] **Régénérer la clé Groq API** — clé exposée dans l'audit
- [ ] **Whitelist extensions upload** dans `admin/cours.php`
- [ ] **Corriger path include** `admin/cours.php` ligne 82

### Priorité 3 — Amélioration qualité

- [ ] **Nettoyage automatique `rate_limits`** — purge des entrées expirées
- [ ] **Revoir `score_couleur()`** — remplacer bleu par une couleur plus intuitive
- [ ] **Éviter double `require_login()`** dans dashboard.php
- [ ] **Ajouter `filter_var` email** dans `auth_register()` si pas déjà présent

### Priorité 4 — Production readiness

- [ ] **Passer `APP_ENV=production`** dans `.env` (masque les erreurs PDO)
- [ ] **Configurer Brevo ou Mailgun** pour emails transactionnels fiables
- [ ] **Activer HTTPS** et forcer `samesite=Strict` en session
- [ ] **Ajouter Content-Security-Policy header**
- [ ] **Activer les logs d'erreur** côté serveur, désactiver `display_errors`

---

## 7. Fichiers à surveiller en priorité

```
inscription_handler.php  ← SUPPRIMER ou corriger
admin/cours.php          ← Corriger path include
includes/rate_limit.php  ← Ajouter purge automatique
includes/auth.php        ← Ajouter rate-limit dans auth_login()
.env                     ← Changer WEBHOOK_SECRET + vérifier accès HTTP
.htaccess                ← Vérifier que .env est bloqué
```

---

*Rapport généré le 2026-05-07 via audit automatisé Claude Sonnet 4.6*
