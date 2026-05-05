<?php
// ============================================================
// RÉUSSITE+ | Authentification & Sessions
// ============================================================

require_once __DIR__ . '/db.php';

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
        'lien'    => '/dashboard.php',
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

// Alias pour compatibilité
function get_user(): ?array {
    return current_user();
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
    // Rafraîchir les données + gérer expiry à chaque requête
    refresh_user();
    if (!is_logged()) {
        header('Location: ' . $redirect . '?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    return current_user();
}

function require_admin(): array {
    $user = require_login();
    if (!is_admin()) {
        header('Location: /reussiteplus/dashboard.php');
        exit;
    }
    return $user;
}

// ── Rafraîchir les données utilisateur en session ──────────
function refresh_user(): void {
    if (!is_logged()) return;
    $user = dbRow("SELECT * FROM utilisateurs WHERE id = ? AND is_active = 1", [$_SESSION['user']['id']]);
    if (!$user) {
        session_unset();
        session_destroy();
        return;
    }
    // Auto-downgrade si plan expiré
    if ($user['plan'] !== 'GRATUIT' && $user['plan_expire_at'] && strtotime($user['plan_expire_at']) < time()) {
        $oldPlan = $user['plan'];
        dbQuery("UPDATE utilisateurs SET plan='GRATUIT', plan_expire_at=NULL WHERE id=?", [$user['id']]);
        // Notifier une seule fois (max 1 notif d'expiry par jour)
        $alreadyNotif = dbRow(
            "SELECT id FROM notifications WHERE user_id=? AND type='SYSTEME' AND titre='Abonnement expiré' AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)",
            [$user['id']]
        );
        if (!$alreadyNotif) {
            dbInsert('notifications', [
                'user_id' => $user['id'],
                'type'    => 'SYSTEME',
                'titre'   => 'Abonnement expiré',
                'message' => "Votre abonnement " . (PLANS[$oldPlan]['nom'] ?? $oldPlan) . " a expiré. Renouvelez pour continuer à bénéficier de tous les avantages.",
                'lien'    => '/reussiteplus/tarifs.php',
            ]);
        }
        $user['plan']           = 'GRATUIT';
        $user['plan_expire_at'] = null;
    }
    unset($user['password_hash'], $user['token_verification'], $user['token_reset']);
    $_SESSION['user'] = $user;
}

// ── Vérifier limite mensuelle (tous plans limités) ────────────
function can_take_exam(): bool {
    $user = current_user();
    if (!$user) return false;

    $plan     = $user['plan'];
    $planData = PLANS[$plan] ?? [];
    $maxExams = $planData['examens_mois'] ?? -1;

    // Plans illimités (PREMIUM, ECOLE)
    if ($maxExams === -1) return true;

    // Réinitialiser compteur si nouveau mois
    $today     = date('Y-m-d');
    $resetDate = $user['examens_mois_reset'] ?? null;
    if (!$resetDate || date('Y-m', strtotime($resetDate)) !== date('Y-m')) {
        dbQuery(
            "UPDATE utilisateurs SET examens_mois = 0, examens_mois_reset = ? WHERE id = ?",
            [$today, $user['id']]
        );
        refresh_user();
        return true;
    }
    return ($user['examens_mois'] ?? 0) < $maxExams;
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
// ── Demande de réinitialisation de mot de passe ────────
function auth_request_password_reset(string $email): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'msg' => 'Adresse email invalide.'];
    }
    $user = dbRow(
        "SELECT id, nom, prenom, email FROM utilisateurs WHERE email = ? AND is_active = 1",
        [$email]
    );
    // Always return ok to prevent email enumeration
    if (!$user) {
        return ['ok' => true, 'msg' => 'Si cet email existe, un lien vous a été envoyé.', 'sent' => false];
    }

    // Rate limit: pas plus d'une demande toutes les 5 minutes
    $recent = dbRow(
        "SELECT token_reset_expire FROM utilisateurs WHERE id = ? AND token_reset_expire > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
        [$user['id']]
    );
    if ($recent) {
        return ['ok' => false, 'msg' => 'Une demande a déjà été envoyée récemment. Patientez 5 minutes.'];
    }

    $token   = bin2hex(random_bytes(32)); // 64 chars hex
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 heure

    dbQuery(
        "UPDATE utilisateurs SET token_reset = ?, token_reset_expire = ? WHERE id = ?",
        [hash('sha256', $token), $expires, $user['id']]
    );

    $resetUrl   = APP_URL . '/reinitialiser_mot_de_passe.php?token=' . urlencode($token) . '&email=' . urlencode($email);
    $isLocalhost = in_array(($_SERVER['HTTP_HOST'] ?? ''), ['localhost', '127.0.0.1']);

    // Tentative d'envoi email
    $sent = false;
    if (!$isLocalhost) {
        $subject = '=?UTF-8?B?' . base64_encode('Réinitialisation de votre mot de passe — RÉUSSITE+') . '?=';
        $body    = "Bonjour {$user['prenom']},\n\n"
                 . "Vous avez demandé à réinitialiser votre mot de passe sur RÉUSSITE+.\n\n"
                 . "Cliquez sur le lien ci-dessous (valable 1 heure) :\n\n"
                 . $resetUrl . "\n\n"
                 . "Si vous n'avez pas fait cette demande, ignorez cet email.\n\n"
                 . "L'équipe RÉUSSITE+";
        $headers = "From: no-reply@reussiteplus.cd\r\nContent-Type: text/plain; charset=UTF-8";
        $sent    = @mail($user['email'], $subject, $body, $headers);
    }

    return [
        'ok'        => true,
        'sent'      => $sent,
        'dev_url'   => $isLocalhost ? $resetUrl : null,
        'prenom'    => $user['prenom'],
        'msg'       => 'Lien de réinitialisation généré.',
    ];
}

// ── Confirmer la réinitialisation ──────────────────────
function auth_confirm_password_reset(string $email, string $token, string $newPass): array {
    if (strlen($newPass) < 8) {
        return ['ok' => false, 'msg' => 'Le mot de passe doit contenir au moins 8 caractères.'];
    }
    $email = strtolower(trim($email));
    $user  = dbRow(
        "SELECT id, token_reset, token_reset_expire FROM utilisateurs WHERE email = ? AND is_active = 1",
        [$email]
    );
    if (!$user || !$user['token_reset'] || !$user['token_reset_expire']) {
        return ['ok' => false, 'msg' => 'Lien invalide ou expiré.'];
    }
    if (strtotime($user['token_reset_expire']) < time()) {
        return ['ok' => false, 'msg' => 'Ce lien a expiré. Faites une nouvelle demande.'];
    }
    if (!hash_equals($user['token_reset'], hash('sha256', $token))) {
        return ['ok' => false, 'msg' => 'Lien invalide.'];
    }

    dbQuery(
        "UPDATE utilisateurs SET password_hash = ?, token_reset = NULL, token_reset_expire = NULL WHERE id = ?",
        [password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), $user['id']]
    );
    return ['ok' => true];
}