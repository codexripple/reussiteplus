<?php
// ============================================================
// RÉUSSITE+ | Configuration Centrale
// ============================================================

// Charger le fichier .env s'il existe (développement local)
$_envFile = dirname(__DIR__) . '/.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if ($_line[0] === '#' || strpos($_line, '=') === false) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        $_ENV[trim($_k)] = trim($_v);
    }
    unset($_envFile, $_line, $_k, $_v);
}

define('APP_NAME',    'RÉUSSITE+');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/reussiteplus');
define('APP_ENV',     $_ENV['APP_ENV'] ?? 'development'); // 'production' en ligne

// Base de données
define('DB_HOST',     $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT',     (int)($_ENV['DB_PORT'] ?? 3306));
define('DB_NAME',     $_ENV['DB_NAME'] ?? 'reussiteplus');
define('DB_USER',     $_ENV['DB_USER'] ?? 'root');
define('DB_PASS',     $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET',  'utf8mb4');

// Sécurité
define('SECRET_KEY',  $_ENV['SECRET_KEY'] ?? 'changeme');
define('BCRYPT_COST', (int)($_ENV['BCRYPT_COST'] ?? 12));

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
        'icone'        => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
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
        'icone'        => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
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
        'icone'        => '<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
        'populaire'    => true,
    ],
    'ECOLE' => [
        'nom'             => 'École',
        'prix'            => 50000,
        'prix_affiche'    => '50 000 CDF/mois',
        'examens_mois'    => -1,
        'questions'       => -1,
        'archives'        => true,
        'corrige'         => true,
        'ia'              => true,
        'eleves_max'      => 50,
        'enseignants_max' => 10,
        'classes_max'     => 5,
        'couleur'         => '#007A5E',
        'icone'           => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'features'        => [
            '5 classes'                   => 'Gestion multi-classes',
            '50 élèves'                   => 'Suivi individuel complet',
            '10 enseignants'              => 'Comptes enseignants dédiés',
            'Emploi du temps'             => 'Calendrier interactif',
            'Devoirs & évaluations'       => 'Assignation & correction',
            'Bibliothèque pédagogique'    => 'Ressources partagées',
            'Bulletins automatiques'      => 'PDF imprimable',
            'IA pédagogique'              => 'Analyse & recommandations',
            'Messagerie interne'          => 'Annonces & communication',
            'Rapports analytics'          => 'Statistiques avancées',
        ],
    ],
]);

// Méthodes de paiement Mobile Money
define('METHODES_PAIEMENT', [
    'MPESA'        => ['nom' => 'M-Pesa',       'numero' => '+243 83 150 8853', 'icone' => 'M', 'couleur' => '#00A651'],
    'AIRTEL_MONEY' => ['nom' => 'Airtel Money',  'numero' => '+243 99X XXX XXX', 'icone' => 'A', 'couleur' => '#E40613'],
    'ORANGE_MONEY' => ['nom' => 'Orange Money',  'numero' => '+243 84 020 4331', 'icone' => 'O', 'couleur' => '#FF6600'],
]);

// Contacts support
define('CONTACT_ORANGE', '+243840204331');
define('CONTACT_MPESA',  '+243831508853');
define('CONTACT_EMAIL',  'support@reussiteplus.cd');

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
