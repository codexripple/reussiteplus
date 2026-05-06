# REVIEW COMPLET — RÉUSSITE+
> Généré le 2025-07-12 | Projet PHP vanilla, MySQL/MariaDB (Laragon), pas de framework

---

## 📊 1. STATUT GÉNÉRAL DU PROJET

### Résumé exécutif
| Dimension          | État         | Note  |
|--------------------|--------------|-------|
| Fonctionnalité core | ✅ Solide    | 7/10  |
| Sécurité            | ⚠️ Risques   | 5/10  |
| Qualité du code     | ✅ Correct   | 6/10  |
| Cohérence BDD       | 🔴 Critique  | 3/10  |
| Tests               | ❌ Absents   | 0/10  |
| Documentation       | ⚠️ Partielle | 5/10  |

### Ce qui fonctionne
- Système d'authentification complet (login, register, reset password)
- Passage d'examens avec timer, résultats, sessionisation
- Abonnements multi-plans avec codes promo et remises durée
- Tableau de bord avec progression et statistiques
- Révision IA via Groq API (Llama)
- Notifications en temps réel (polling)
- Interface admin fonctionnelle (users, paiements, archives)
- CSRF implémenté sur tous les formulaires POST importants
- Mots de passe hashés bcrypt (cost 12)

### Ce qui manque / bloquant
- **Aucun paiement réel** : les abonnements sont créés en statut `EN_ATTENTE` sans webhook de confirmation
- **Schema SQL Postgres** livré avec une app MySQL — aucun moyen de déployer proprement
- Zéro test automatisé
- Aucune gestion des erreurs centralisée (pas de handler global)

---

## 🏗 2. ARCHITECTURE

```
reussiteplus/
├── includes/           ← Config, DB singleton, Auth, Helpers, Icons
├── api/                ← Endpoints AJAX (JSON) : révision IA, notifications, signets, archives
├── admin/              ← Interface backoffice
├── assets/             ← CSS, JS, fonts, images
└── *.php               ← Pages publiques/connectées (MVC-light inline)
```

### Points positifs
- Séparation includes / pages / api propre pour du PHP sans framework
- PDO partout, requêtes préparées systématiques
- Helpers centralisés (`e()`, `csrf_*`, `dbQuery`, `dbAll`, `dbInsert`)
- Constantes de configuration dans un seul fichier (`config.php`)

### Points faibles
- Logique métier mélangée avec le HTML dans chaque page (pas de séparation)
- Pas de routeur — chaque page est un fichier PHP indépendant
- Pas de système de template (duplication header/footer)
- Pas de namespace ni de chargement automatique des classes
- Les seeds SQL (`seed.php`, `seed_questions.php`, etc.) sont exposés publiquement

---

## 🔴 3. SÉCURITÉ — PROBLÈMES ET CORRECTIONS

### [P0 — CRITIQUE] Mismatch schéma Postgres vs app MySQL

**Problème :** `schema.sql` est un script PostgreSQL (uuid-ossp, RLS Supabase, `auth.users`). L'application tourne sur MySQL/MariaDB. Si quelqu'un exécute ce fichier sur le bon moteur il obtient une base vide différente ; si exécuté sur MySQL il échoue.

**Correction :**
```bash
# Supprimer ou renommer le fichier PostgreSQL
mv schema.sql schema.supabase.sql.bak

# Créer un schema.mysql.sql à partir de setup_db.sql et de la structure réelle
```
Recréer `schema.sql` en exécutant `mysqldump --no-data reussiteplus > schema.mysql.sql`.

---

### [P0 — CRITIQUE] Open Redirect dans connexion.php

**Fichier :** `connexion.php`  
**Code problématique :**
```php
$redirect = $_GET['redirect'] ?? 'dashboard.php';
// ... après login réussi :
header('Location: ' . $redirect);
```

**Impact :** Un attaquant peut forger `connexion.php?redirect=https://malware.site` — l'utilisateur se connecte et est redirigé vers un site tiers. Phishing, vol de session, etc.

**Correction :**
```php
function safe_redirect(string $url): string {
    // N'accepter que les chemins relatifs du site
    $parsed = parse_url($url);
    if (!empty($parsed['scheme']) || !empty($parsed['host'])) {
        return 'dashboard.php'; // URL absolue externe → refusé
    }
    // Nettoyer les tentatives ../ 
    $clean = ltrim(preg_replace('#\.\.+/#', '', $url), '/');
    return $clean ?: 'dashboard.php';
}

$redirect = safe_redirect($_GET['redirect'] ?? 'dashboard.php');
header('Location: ' . $redirect);
```

---

### [P0 — HAUTE] Fuite de l'URL de reset mot de passe en production

**Fichier :** `mot_de_passe_oublie.php` / `includes/auth.php`  
**Code problématique :**
```php
$result = auth_request_password_reset($email);
// En mode dev :
if (APP_ENV === 'development') {
    $devUrl = $result['dev_url']; // exposé dans la réponse HTML
}
```

**Risque :** Si `APP_ENV` n'est pas correctement défini en production (ou si quelqu'un laisse `'development'`), l'URL de reset est renvoyée au client, permettant à n'importe qui de prendre le contrôle d'un compte.

**Correction :**
```php
// Dans config.php : forcer la vérification
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // jamais 'development' par défaut

// Dans la page, supprimer toute exposition de dev_url
// Ne jamais mettre de lien de reset dans la réponse HTTP — uniquement par email
if (APP_ENV === 'development') {
    error_log('[DEV] Reset URL: ' . ($result['dev_url'] ?? ''));
    // Afficher uniquement dans les logs serveur, PAS dans la page
}
```

---

### [P1 — HAUTE] Credentials hardcodés dans config.php

**Fichier :** `includes/config.php`  
**Code problématique :**
```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('SECRET_KEY', getenv('SECRET_KEY') ?: 'reussiteplus_secret_2024_very_long_key');
```

**Risque :** La clé secrète hardcodée est présente dans le dépôt. Si le repo est public ou partagé, tous les CSRF tokens et sessions sont compromis.

**Correction :**
1. Créer un fichier `.env` (jamais commité) :
```
DB_HOST=localhost
DB_USER=root
DB_PASS=
SECRET_KEY=<générer avec: php -r "echo bin2hex(random_bytes(32));")>
GROQ_API_KEY=sk-...
APP_ENV=development
```
2. Dans `config.php`, supprimer les fallbacks hardcodés :
```php
define('SECRET_KEY', getenv('SECRET_KEY') ?: die('SECRET_KEY manquante'));
```
3. Ajouter `.env` dans `.gitignore`.

---

### [P1 — HAUTE] Absence de rate limiting (brute force)

**Fichiers :** `connexion.php`, `inscription.php`, `mot_de_passe_oublie.php`

**Impact :** Un attaquant peut tester des milliers de mots de passe, créer des comptes en masse, ou spammer le reset password.

**Correction minimale (sans Redis) — table SQL :**
```sql
CREATE TABLE rate_limits (
    ip VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempts INT DEFAULT 1,
    window_start DATETIME DEFAULT NOW(),
    PRIMARY KEY (ip, action)
);
```
```php
function check_rate_limit(string $action, int $max = 5, int $window = 300): bool {
    $ip = $_SERVER['REMOTE_ADDR'];
    $row = dbOne("SELECT attempts, window_start FROM rate_limits WHERE ip=? AND action=?", [$ip, $action]);
    if ($row && (time() - strtotime($row['window_start'])) < $window) {
        if ($row['attempts'] >= $max) return false; // bloqué
        dbQuery("UPDATE rate_limits SET attempts = attempts+1 WHERE ip=? AND action=?", [$ip, $action]);
    } else {
        dbQuery("INSERT INTO rate_limits (ip,action) VALUES (?,?) ON DUPLICATE KEY UPDATE attempts=1, window_start=NOW()", [$ip, $action]);
    }
    return true;
}

// Utilisation dans connexion.php :
if (!check_rate_limit('login', 5, 300)) {
    $errors[] = 'Trop de tentatives. Attendez 5 minutes.';
}
```

---

### [P1] Seeds exposés publiquement

**Fichiers :** `seed.php`, `seed_questions.php`, `seed_questions_batch2.php`, `add_questions.php`, `check_questions.php`

Ces scripts peuvent être appelés depuis un navigateur et réinitialiser/corrompre la base de données.

**Correction :**
```php
// Déjà présent dans certains fichiers, à généraliser :
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1','::1'])) {
    http_response_code(403); die('Accès refusé.');
}
```
Ou mieux : déplacer dans un dossier `scripts/` hors de la racine web, ou protéger via `.htaccess`.

---

### [P2 — MOYENNE] UUIDs non cryptographiques

**Fichier :** `includes/db.php`  
**Code problématique :**
```php
function generate_uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), // ← mt_rand prévisible
        ...
    );
}
```

**Correction :**
```php
function generate_uuid(): string {
    $data = random_bytes(16); // Cryptographiquement sûr
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
```

---

### [P2] Référence de paiement prévisible

**Fichier :** `paiement.php`  
```php
$ref = 'RP-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
```
MD5 + mt_rand = prévisible. Utiliser `bin2hex(random_bytes(6))`.

---

### [P2] Code de parrainage faible

**Fichier :** `includes/auth.php`  
```php
$referralCode = strtoupper(substr(md5(uniqid()), 0, 8));
```
Remplacer par `strtoupper(bin2hex(random_bytes(4)))`.

---

### [P2] Pas de protection contre la double-soumission de paiement

**Fichier :** `paiement.php`

Un double-clic ou une re-soumission peut créer deux abonnements `EN_ATTENTE`. Ajouter une contrainte UNIQUE sur `(user_id, statut='EN_ATTENTE')` ou un token de soumission unique côté formulaire.

---

## 🐛 4. BUGS ACTUELS ET CORRECTIONS

### Bug #1 — matiere_icon() : style HTML malformé

**Fichier :** `includes/helpers.php`  
**Symptôme :** Les icônes de matières affichent probablement un style cassé ou une couleur incorrecte.

**Code problématique (approximatif) :**
```php
function matiere_icon(string $code): string {
    $icons = [...];
    $icon = $icons[$code] ?? $icons['default'];
    return '<span class="matiere-icon" style="color:' . $icon['color'] . $icon['icon'] . '</span>';
    // ↑ fermeture de l'attribut style manquante → HTML invalide
}
```

**Correction :**
```php
return '<span class="matiere-icon" style="color:' . e($icon['color']) . '">' . e($icon['icon']) . '</span>';
```

---

### Bug #2 — Race condition sur score_moyen dans examen.php

**Fichier :** `examen.php`  
**Symptôme :** Avec des requêtes simultanées, le score moyen peut être incohérent.

**Code problématique :**
```php
// Lecture puis calcul puis écriture — non atomique
$current = dbOne("SELECT score_moyen, total_examens FROM users WHERE id=?", [$userId]);
$newScore = ($current['score_moyen'] * $current['total_examens'] + $score) / ($current['total_examens'] + 1);
dbQuery("UPDATE users SET score_moyen=?, total_examens=total_examens+1 WHERE id=?", [$newScore, $userId]);
```

**Correction — UPDATE atomique :**
```php
dbQuery(
    "UPDATE users SET 
        score_moyen = (score_moyen * total_examens + ?) / (total_examens + 1),
        total_examens = total_examens + 1
     WHERE id=?",
    [$score, $userId]
);
```

---

### Bug #3 — lastInsertId() ne fonctionne pas avec UUID

**Fichier :** `add_questions.php`  
**Code :**
```php
$stQ->execute([...]);
$qid = $pdo->lastInsertId(); // Retourne '' avec une PK UUID (pas auto-increment)
if (!$qid) {
    // Fallback par SELECT — peut retourner le mauvais enregistrement en cas de concurrence
    $r = $pdo->prepare("SELECT id FROM question_bank WHERE enonce=? ORDER BY created_at DESC LIMIT 1");
    $r->execute([$enonce]);
    $qid = $r->fetchColumn();
}
```

**Correction :** Générer l'UUID côté PHP avant l'INSERT et le passer explicitement :
```php
function addQ(..., string $enonce, ...): void {
    $uuid = generate_uuid(); // Votre fonction existante
    $stQ->execute([$uuid, $matId, $enonce, $diff, $src]);
    foreach ($opts as [$l,$t,$ok]) $stO->execute([generate_uuid(), $uuid, $l, $t, $ok]);
}
```

---

### Bug #4 — GROQ_API_KEY non définie → erreur fatale possible

**Fichier :** `api/revision.php`  
Si `GROQ_API_KEY` n'est pas définie dans l'environnement ET qu'il n'y a pas de fallback dans `config.php`, tout appel à la révision IA retourne une erreur silencieuse.

**Correction dans `config.php` :**
```php
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
// La vérification dans api/revision.php est correcte, mais ajouter une alerte en dev :
if (APP_ENV === 'development' && !GROQ_API_KEY) {
    error_log('[WARN] GROQ_API_KEY non configurée - fonctions IA désactivées');
}
```

---

### Bug #5 — Paiement sans confirmation réelle

**Fichier :** `paiement.php`  
Les abonnements sont créés avec `statut = 'EN_ATTENTE'` mais il n'existe pas de webhook / callback de l'opérateur mobile money pour confirmer le paiement. L'utilisateur peut avoir payé sans que son compte soit activé, ou inversement un admin doit manuellement valider.

**État :** Feature incomplète (voir section 5).

---

## ❌ 5. FONCTIONNALITÉS MANQUANTES

### [CRITIQUE] Système de paiement incomplet
- Aucun webhook d'opérateur (CinetPay, FedaPay, Flutterwave) pour confirmer les paiements mobile money
- Pas d'endpoint `api/paiement/callback.php`
- L'admin doit manuellement changer le statut de `EN_ATTENTE` → `CONFIRME`
- Aucune notification utilisateur quand l'abonnement est activé

### [HAUTE] Envoi d'email non implémenté
- `auth_request_password_reset()` génère un token mais aucun email n'est envoyé (seulement un `dev_url`)
- Aucune confirmation d'inscription par email
- Aucune facture par email après paiement

**Solution recommandée :** Intégrer PHPMailer + Mailgun/Brevo (anciennement Sendinblue) :
```bash
composer require phpmailer/phpmailer
```

### [HAUTE] Vérification d'email manquante
- Un utilisateur peut créer un compte avec n'importe quelle adresse sans vérification
- Risque de spam, de faux comptes

### [MOYENNE] Limitation des examens gratuits non vérifiée en temps réel
- Le plan GRATUIT permet 5 examens/mois mais la vérification se fait au démarrage d'un examen
- Pas de compteur visible dans le dashboard

### [MOYENNE] Export/rapports absents
- L'admin ne peut pas exporter les utilisateurs, paiements, ou résultats en CSV/Excel
- Aucun rapport d'activité périodique

### [MOYENNE] Gestion des remboursements
- Aucune procédure de remboursement dans l'interface admin
- Table `abonnements` n'a pas de champ `remboursement_*`

### [FAIBLE] Profil utilisateur non modifiable
- L'utilisateur ne peut pas changer son nom, email, ou mot de passe depuis le dashboard
- Pas de page `profil.php`

### [FAIBLE] Pagination absente
- Les listes (questions, utilisateurs, paiements admin) chargent toutes les entrées sans limite
- Risque de timeout / memory overflow avec beaucoup de données

---

## ⚡ 6. AMÉLIORATIONS POSSIBLES

### Performance
1. **Cache des questions** : Les questions de la banque sont reloadées à chaque examen. Ajouter un cache APCu ou fichier JSON.
2. **Index manquants** : Vérifier et ajouter des index sur `exam_sessions(user_id, statut)`, `user_progression(user_id, matiere_id)`, `question_bank(matiere_id, difficulte, status)`.
3. **Lazy loading des notifications** : Le polling actuel interroge la BDD toutes les X secondes pour tous les utilisateurs connectés — préférer WebSocket ou Server-Sent Events.

```sql
-- Index recommandés
ALTER TABLE exam_sessions ADD INDEX idx_user_statut (user_id, statut);
ALTER TABLE user_progression ADD INDEX idx_user_matiere (user_id, matiere_id);
ALTER TABLE question_bank ADD INDEX idx_matiere_diff (matiere_id, difficulte, status);
ALTER TABLE abonnements ADD INDEX idx_user_statut (user_id, statut);
```

### Code Quality
1. **Gestion d'erreurs centralisée** :
```php
// Dans config.php ou un bootstrap :
set_exception_handler(function(\Throwable $e) {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (APP_ENV !== 'development') {
        http_response_code(500);
        include 'includes/error_500.php';
        exit;
    }
    throw $e;
});
```

2. **Headers de sécurité HTTP** à ajouter dans chaque page (ou `.htaccess`) :
```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
```

3. **Logger structuré** : Remplacer les `error_log()` éparpillés par un logger PSR-3 minimal.

### UX / Fonctionnalités
1. **Mode révision hors-ligne** : Mettre les questions en cache localStorage pour continuer sans connexion
2. **Partage de résultats** : Permettre à l'utilisateur de partager son score (carte image générée)
3. **Classement (leaderboard)** : Motiver les élèves avec un tableau des meilleurs scores par matière
4. **Rappels email** : Envoyer un email si l'utilisateur n'a pas fait d'examen depuis 7 jours

---

## 📋 7. PLAN D'ACTION PRIORISÉ

### 🔴 P0 — À faire immédiatement (Sécurité critique)
- [ ] **Corriger l'Open Redirect** dans `connexion.php` (voir correction section 3)
- [ ] **Supprimer la fuite dev_url** en production dans `mot_de_passe_oublie.php`
- [ ] **Externaliser les secrets** dans `.env` + ajouter `.gitignore`
- [ ] **Recréer schema.sql** en MySQL (supprimer la version PostgreSQL)
- [ ] **Protéger les seeds** avec vérification CLI/IP

### 🟠 P1 — Cette semaine (Fonctionnel bloquant)
- [ ] **Implémenter l'envoi d'email** (PHPMailer + Brevo/Mailgun)
  - Reset password fonctionnel
  - Confirmation d'inscription
- [ ] **Ajouter le rate limiting** sur login, register, reset (table SQL)
- [ ] **Finaliser le paiement** : intégrer un webhook opérateur OU créer une interface admin de validation explicite avec notification utilisateur
- [ ] **Corriger la race condition** score_moyen (UPDATE atomique)

### 🟡 P2 — Prochaines 2 semaines (Stabilité)
- [ ] **Corriger les UUIDs** : remplacer `mt_rand()` par `random_bytes()`
- [ ] **Corriger le bug lastInsertId + UUID** dans les seeds
- [ ] **Corriger matiere_icon()** style HTML
- [ ] **Ajouter les index SQL** (performance)
- [ ] **Ajouter les headers HTTP de sécurité**
- [ ] **Ajouter la pagination** dans les listes admin
- [ ] **Créer la page profil utilisateur** (nom, email, mot de passe)

### 🟢 P3 — Backlog (Améliorations)
- [ ] Vérification d'email à l'inscription
- [ ] Export CSV admin (utilisateurs, paiements)
- [ ] Gestion des remboursements
- [ ] Compteur d'examens restants visible dans le dashboard
- [ ] Cache des questions de la banque
- [ ] Leaderboard / classement
- [ ] Rappels email d'inactivité
- [ ] Centraliser la gestion d'erreurs PHP

---

## 📁 8. FICHIERS À SURVEILLER EN PRIORITÉ

| Fichier | Raison |
|---------|--------|
| `includes/config.php` | Secrets hardcodés, APP_ENV |
| `connexion.php` | Open Redirect |
| `mot_de_passe_oublie.php` | Fuite dev_url |
| `includes/db.php` | UUID non sécurisé |
| `examen.php` | Race condition score |
| `paiement.php` | Paiement incomplet, ref prévisible |
| `schema.sql` | PostgreSQL vs MySQL |
| `seed*.php`, `add_questions.php` | Accès public dangereux |

---

*Rapport généré automatiquement par revue de code statique. Les corrections de code fournies sont des exemples — adapter selon le contexte exact de chaque fichier.*
