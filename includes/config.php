<?php
// ============================================================
// RÉUSSITE+ | Configuration Centrale
// ============================================================

define('APP_NAME',    'RÉUSSITE+');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/reussiteplus');
define('APP_ENV',     'development'); // 'production' en ligne

// Base de données
define('DB_HOST',     'localhost');
define('DB_PORT',     3306);
define('DB_NAME',     'reussiteplus');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// Sécurité
define('SECRET_KEY',  'rp_secret_2025_rdc_edtech_!@#$%^');
define('BCRYPT_COST', 12);

// Plans & Tarifs (en CDF)
define('PLANS', [
    'GRATUIT' => [
        'nom'          => 'Gratuit',
        'prix'         => 0,
        'prix_affiche' => 'Gratuit',
        'examens_mois' => 5,
        'questions'    => 20,
        'archives'     => false,
        'corrige'      => false,
        'ia'           => false,
        'couleur'      => '#6B7280',
        'icone'        => '🎒',
    ],
    'BASIQUE' => [
        'nom'          => 'Basique',
        'prix'         => 5000,
        'prix_affiche' => '5 000 CDF/mois',
        'examens_mois' => 30,
        'questions'    => 200,
        'archives'     => true,
        'corrige'      => true,
        'ia'           => false,
        'couleur'      => '#1E5FAD',
        'icone'        => '📘',
        'populaire'    => false,
    ],
    'PREMIUM' => [
        'nom'          => 'Premium',
        'prix'         => 10000,
        'prix_affiche' => '10 000 CDF/mois',
        'examens_mois' => -1, // illimité
        'questions'    => -1,
        'archives'     => true,
        'corrige'      => true,
        'ia'           => true,
        'couleur'      => '#C9972A',
        'icone'        => '⭐',
        'populaire'    => true,
    ],
    'ECOLE' => [
        'nom'          => 'École',
        'prix'         => 50000,
        'prix_affiche' => '50 000 CDF/mois',
        'examens_mois' => -1,
        'questions'    => -1,
        'archives'     => true,
        'corrige'      => true,
        'ia'           => true,
        'eleves_max'   => 50,
        'couleur'      => '#007A5E',
        'icone'        => '🏫',
    ],
]);

// Méthodes de paiement Mobile Money
define('METHODES_PAIEMENT', [
    'MPESA'        => ['nom' => 'M-Pesa',       'numero' => '+243 81X XXX XXX', 'icone' => '💚'],
    'AIRTEL_MONEY' => ['nom' => 'Airtel Money',  'numero' => '+243 99X XXX XXX', 'icone' => '🔴'],
    'ORANGE_MONEY' => ['nom' => 'Orange Money',  'numero' => '+243 84X XXX XXX', 'icone' => '🟠'],
]);

// Limites plan gratuit
define('FREE_EXAMS_PER_MONTH', 5);

// Upload
define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
define('UPLOAD_URL',  APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB

// Session
define('SESSION_LIFETIME', 86400 * 7); // 7 jours

// Groq AI (gratuit sur console.groq.com)
// Définir GROQ_API_KEY dans .env ou dans les variables d'environnement du serveur
define('GROQ_API_KEY',   $_ENV['GROQ_API_KEY'] ?? (getenv('GROQ_API_KEY') ?: ''));
define('GROQ_API_URL',   'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL',     'llama-3.1-8b-instant');
define('GROQ_MAX_TOKENS', 1024);

// Timezone RDC (Kinshasa)
date_default_timezone_set('Africa/Kinshasa');

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
