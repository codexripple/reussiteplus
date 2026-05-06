<?php
// ============================================================
// RÉUSSITE+ | Authentification & Sessions
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

// ── Connexion ──────────────────────────────────────────────
function auth_login(string $email, string $password): array {
    $user = dbRow(
        "SELECT * FROM utilisateurs WHERE email = ? AND is_active = 1",
        [strtolower(trim($email))]
    );
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'msg' => 'Email ou mot de passe incorrect.'];
    }
    // Mettre à jour dernière activité
    dbQuery(
        "UPDATE utilisateurs SET derniere_activite = NOW() WHERE id = ?",
        [$user['id']]
    );
    // Stocker en session (sans le hash)
    unset($user['password_hash'], $user['token_verification'], $user['token_reset']);
    $_SESSION['user'] = $user;
    return ['ok' => true, 'user' => $user];
}

// ── Inscription ────────────────────────────────────────────
function auth_register(array $data): array {
    $email = strtolower(trim($data['email']));

    // Vérifier email unique
    if (dbRow("SELECT id FROM utilisateurs WHERE email = ?", [$email])) {
        return ['ok' => false, 'msg' => 'Cet email est déjà utilisé.'];
    }

    // Valider mot de passe
    if (strlen($data['password']) < 8) {
        return ['ok' => false, 'msg' => 'Le mot de passe doit contenir au moins 8 caractères.'];
    }

    // Générer code référral unique
    $refCode = strtoupper(substr(md5($email . time()), 0, 8));

    $userId = dbInsert('utilisateurs', [
        'email'          => $email,
        'password_hash'  => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
        'nom'            => trim($data['nom']),
        'prenom'         => trim($data['prenom']),
        'classe'         => $data['classe'] ?? null,
        'province_id'    => $data['province_id'] ?? null,
        'role'           => 'ELEVE',
        'plan'           => 'GRATUIT',
        'referral_code'  => $refCode,
        'referral_par'   => $data['referral_par'] ?? null,
        'derniere_activite' => date('Y-m-d H:i:s'),
    ]);

    // Notification de bienvenue
    dbInsert('notifications', [
        'user_id' => $userId,
        'type'    => 'SYSTEME',
        'titre'   => 'Bienvenue sur RÉUSSITE+ !',
        'message' => 'Votre compte a été créé avec succès. Commencez dès maintenant à préparer votre examen.',
        'lien'    => APP_URL . '/dashboard.php',
    ]);

    // Auto-login
    $user = dbRow("SELECT * FROM utilisateurs WHERE id = ?", [$userId]);
    unset($user['password_hash']);
    $_SESSION['user'] = $user;

    return ['ok' => true, 'user' => $user];
}

// ── Déconnexion ────────────────────────────────────────────
function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: /reussiteplus/connexion.php');
    exit;
}

// ── Utilisateur courant ────────────────────────────────────
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged(): bool {
    return isset($_SESSION['user']);
}

function is_admin(): bool {
    return isset($_SESSION['user']['role']) &&
           in_array($_SESSION['user']['role'], ['SUPER_ADMIN', 'MODERATEUR']);
}

function require_login(string $redirect = '/reussiteplus/connexion.php'): array {
    if (!is_logged()) {
        header('Location: ' . $redirect . '?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    // Vérifier que le user existe toujours en DB (évite les sessions obsolètes)
    $fresh = dbRow("SELECT * FROM utilisateurs WHERE id = ?", [$_SESSION['user']['id']]);
    if (!$fresh) {
        session_unset();
        session_destroy();
        header('Location: ' . $redirect . '?msg=session_expired');
        exit;
    }
    unset($fresh['password_hash']);
    $_SESSION['user'] = $fresh; // Rafraîchir la session
    return $fresh;
}

function require_admin(): array {
    if (!is_logged()) {
        header('Location: /reussiteplus/admin/connexion.php');
        exit;
    }
    $user = require_login('/reussiteplus/admin/connexion.php');
    if (!is_admin()) {
        header('Location: /reussiteplus/dashboard.php');
        exit;
    }
    return $user;
}

// ── Rafraîchir les données utilisateur en session ──────────
function refresh_user(): void {
    if (!is_logged()) return;
    $user = dbRow("SELECT * FROM utilisateurs WHERE id = ?", [$_SESSION['user']['id']]);
    if ($user) {
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
    }
}

// ── Vérifier limite plan gratuit ───────────────────────────
function can_take_exam(): bool {
    $user = current_user();
    if (!$user) return false;
    if ($user['plan'] !== 'GRATUIT') return true;

    // Réinitialiser compteur mensuel si nouveau mois
    $today = date('Y-m-d');
    $resetDate = $user['examens_mois_reset'] ?? null;
    if (!$resetDate || date('Y-m', strtotime($resetDate)) !== date('Y-m')) {
        dbQuery(
            "UPDATE utilisateurs SET examens_mois = 0, examens_mois_reset = ? WHERE id = ?",
            [$today, $user['id']]
        );
        refresh_user();
        return true;
    }
    return ($user['examens_mois'] ?? 0) < FREE_EXAMS_PER_MONTH;
}

// ── Demande de réinitialisation de mot de passe ────────────
function auth_request_password_reset(string $email): array {
    $email = strtolower(trim($email));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'msg' => 'Adresse email invalide.'];
    }

    $user = dbRow(
        "SELECT id, prenom, nom, email FROM utilisateurs WHERE email = ? AND is_active = 1",
        [$email]
    );

    // Sécurité : ne pas révéler si l'email existe ou non
    if (!$user) {
        return ['ok' => true, 'prenom' => ''];
    }

    // Rate-limit : max 1 demande toutes les 5 minutes
    $recent = dbRow(
        "SELECT token_reset_expire FROM utilisateurs WHERE id = ? AND token_reset_expire > DATE_ADD(NOW(), INTERVAL -5 MINUTE)",
        [$user['id']]
    );
    if ($recent) {
        return ['ok' => false, 'msg' => 'Une demande a déjà été envoyée. Attendez 5 minutes avant de réessayer.'];
    }

    // Générer token sécurisé
    $token      = bin2hex(random_bytes(32)); // 64 hex chars
    $tokenHash  = hash('sha256', $token);
    $expiry     = date('Y-m-d H:i:s', strtotime('+1 hour'));

    dbQuery(
        "UPDATE utilisateurs SET token_reset = ?, token_reset_expire = ? WHERE id = ?",
        [$tokenHash, $expiry, $user['id']]
    );

    $resetUrl = APP_URL . '/reinitialiser_mot_de_passe.php'
              . '?token=' . urlencode($token)
              . '&email=' . urlencode($email);

    // ── Envoi email ──────────────────────────────────────────
    $emailSent = false;
    $subject   = 'Réinitialisation de votre mot de passe — RÉUSSITE+';
    $btnHtml   = email_btn($resetUrl, 'Créer un nouveau mot de passe');
    $html      = email_template(
        'Réinitialisation de mot de passe',
        "<p style=\"margin:0 0 16px;font-size:15px;color:#4A5568\">Bonjour <strong>{$user['prenom']}</strong>,</p>"
        . "<p style=\"margin:0 0 16px;font-size:15px;color:#4A5568\">Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte RÉUSSITE+.</p>"
        . $btnHtml
        . "<p style=\"margin:0;font-size:13px;color:#A0AEC0\">Ce lien est valable <strong>1 heure</strong>. Si vous n'avez pas fait cette demande, ignorez cet email — votre mot de passe restera inchangé.</p>",
        'Réinitialisez votre mot de passe RÉUSSITE+ en un clic.'
    );
    $text  = "Bonjour {$user['prenom']},\n\nCliquez sur ce lien pour créer un nouveau mot de passe :\n"
           . $resetUrl . "\n\nCe lien est valable 1 heure.\n\n— L'équipe RÉUSSITE+";

    if (APP_ENV !== 'development') {
        $emailSent = send_email($user['email'], $user['prenom'] . ' ' . $user['nom'], $subject, $html, $text);
    }

    return [
        'ok'      => true,
        'prenom'  => $user['prenom'],
        // En mode développement ou si mail() échoue, on expose l'URL
        'dev_url' => (APP_ENV === 'development' || !$emailSent) ? $resetUrl : null,
    ];
}

// ── Confirmation de réinitialisation de mot de passe ───────
function auth_confirm_password_reset(string $email, string $token, string $newPass): array {
    $email = strtolower(trim($email));

    if (strlen($newPass) < 8) {
        return ['ok' => false, 'msg' => 'Le mot de passe doit contenir au moins 8 caractères.'];
    }

    $user = dbRow(
        "SELECT id, token_reset, token_reset_expire FROM utilisateurs
         WHERE email = ? AND is_active = 1 AND token_reset IS NOT NULL",
        [$email]
    );

    if (!$user) {
        return ['ok' => false, 'msg' => 'Lien invalide.'];
    }

    // Vérifier expiration
    if (strtotime($user['token_reset_expire']) < time()) {
        return ['ok' => false, 'msg' => 'Ce lien a expiré. Faites une nouvelle demande.'];
    }

    // Vérifier le token
    if (!hash_equals($user['token_reset'], hash('sha256', $token))) {
        return ['ok' => false, 'msg' => 'Lien invalide.'];
    }

    // Mettre à jour le mot de passe et invalider le token
    dbQuery(
        "UPDATE utilisateurs
         SET password_hash = ?, token_reset = NULL, token_reset_expire = NULL
         WHERE id = ?",
        [password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), $user['id']]
    );

    return ['ok' => true];
}

// ── Changer le mot de passe ────────────────────────────────
function auth_change_password(string $userId, string $oldPass, string $newPass): array {
    $user = dbRow("SELECT password_hash FROM utilisateurs WHERE id = ?", [$userId]);
    if (!$user || !password_verify($oldPass, $user['password_hash'])) {
        return ['ok' => false, 'msg' => 'Mot de passe actuel incorrect.'];
    }
    if (strlen($newPass) < 8) {
        return ['ok' => false, 'msg' => 'Minimum 8 caractères requis.'];
    }
    dbQuery(
        "UPDATE utilisateurs SET password_hash = ? WHERE id = ?",
        [password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), $userId]
    );
    return ['ok' => true];
}
