<?php
// ============================================================
// RÉUSSITE+ | Fonctions Utilitaires
// ============================================================

// ── Sécurité sortie HTML ───────────────────────────────────
function e(mixed $val): string {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Formatage prix ─────────────────────────────────────────
function format_prix(int $montant, string $devise = 'CDF'): string {
    return number_format($montant, 0, ',', ' ') . ' ' . $devise;
}

// ── Badge plan utilisateur ─────────────────────────────────
function badge_plan(string $plan): string {
    $map = [
        'GRATUIT' => ['🎒 Gratuit', '#6B7280'],
        'BASIQUE' => ['📘 Basique', '#1E5FAD'],
        'PREMIUM' => ['⭐ Premium', '#C9972A'],
        'ECOLE'   => ['🏫 École',   '#007A5E'],
    ];
    [$label, $color] = $map[$plan] ?? ['Gratuit', '#6B7280'];
    return "<span style=\"background:{$color}20;color:{$color};padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600\">{$label}</span>";
}

// ── Durée relative ─────────────────────────────────────────
function temps_relatif(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return "À l'instant";
    if ($diff < 3600)    return floor($diff / 60) . ' min';
    if ($diff < 86400)   return floor($diff / 3600) . 'h';
    if ($diff < 604800)  return floor($diff / 86400) . 'j';
    return date('d/m/Y', strtotime($datetime));
}

// ── Slug ──────────────────────────────────────────────────
function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c',
    ]);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', trim($text));
    return $text;
}

// ── Icône Lucide pour une matière ─────────────────────────
// $icone contient le nom Lucide (ex: 'calculator') ou un fallback emoji
function matiere_icon(string $icone, int $size = 16, string $style = ''): string {
    // Si c'est déjà un nom Lucide (pas emoji), on rend l'icône
    if (preg_match('/^[a-z][a-z0-9\-]+$/', $icone)) {
        $s = $style ? " style=\"{$style}\"" : '';
        return "<i data-lucide=\"{$icone}\" style=\"width:{$size}px;height:{$size}px;vertical-align:middle{$style}\"></i>";
    }
    // Sinon fallback — on cherche le mapping par le nom Lucide par défaut
    return "<i data-lucide=\"book\" style=\"width:{$size}px;height:{$size}px;vertical-align:middle\"></i>";
}

// ── Formatage score ────────────────────────────────────────
function score_couleur(float $pct): string {
    if ($pct >= 80) return '#007A5E';
    if ($pct >= 60) return '#C9972A';
    if ($pct >= 40) return '#1E5FAD';
    return '#C9342A';
}

function score_label(float $pct): string {
    if ($pct >= 80) return 'Excellent';
    if ($pct >= 60) return 'Bien';
    if ($pct >= 40) return 'Moyen';
    return 'À revoir';
}

// ── Difficulté badge ───────────────────────────────────────
function badge_difficulte(string $niv): string {
    $map = [
        'DEBUTANT'      => ['Débutant',      '#22C55E'],
        'ELEMENTAIRE'   => ['Élémentaire',   '#84CC16'],
        'INTERMEDIAIRE' => ['Intermédiaire', '#F59E0B'],
        'AVANCE'        => ['Avancé',        '#EF4444'],
        'EXPERT'        => ['Expert',        '#7C3AED'],
    ];
    [$label, $color] = $map[$niv] ?? ['Moyen', '#F59E0B'];
    return "<span style=\"background:{$color}20;color:{$color};padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600\">{$label}</span>";
}

// ── Redirection avec message flash ────────────────────────
function redirect(string $url, string $type = 'success', string $msg = ''): void {
    if ($msg) {
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    }
    header('Location: ' . $url);
    exit;
}

// ── Récupérer et effacer le flash ──────────────────────────
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ── Afficher le flash ──────────────────────────────────────
function show_flash(): string {
    $f = get_flash();
    if (!$f) return '';
    $colors = [
        'success' => '#007A5E',
        'error'   => '#C9342A',
        'info'    => '#1E5FAD',
        'warning' => '#C9972A',
    ];
    $bg = $colors[$f['type']] ?? '#007A5E';
    return "<div class=\"flash flash-{$f['type']}\" style=\"background:{$bg}15;border-left:4px solid {$bg};color:{$bg};padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px\">"
         . e($f['msg']) . "</div>";
}

// ── CSRF Token ─────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// ── Pagination ─────────────────────────────────────────────
function paginate(int $total, int $page, int $perPage): array {
    $pages    = max(1, (int)ceil($total / $perPage));
    $page     = max(1, min($page, $pages));
    $offset   = ($page - 1) * $perPage;
    return compact('total', 'page', 'pages', 'perPage', 'offset');
}

// ── Statistiques utilisateur ───────────────────────────────
function get_user_stats(string $userId): array {
    $stats = dbRow(
        "SELECT total_examens, total_questions, score_moyen, streak_jours FROM utilisateurs WHERE id = ?",
        [$userId]
    ) ?? [];

    // Calcul streak actuel
    $streak = 0;
    $rows   = dbAll(
        "SELECT date_act FROM activite_journaliere WHERE user_id = ? ORDER BY date_act DESC LIMIT 30",
        [$userId]
    );
    $prev   = null;
    foreach ($rows as $r) {
        $d = $r['date_act'];
        if ($prev === null) {
            if ($d === date('Y-m-d') || $d === date('Y-m-d', strtotime('-1 day'))) {
                $streak = 1;
                $prev   = $d;
            } else { break; }
        } else {
            $expected = date('Y-m-d', strtotime($prev . ' -1 day'));
            if ($d === $expected) { $streak++; $prev = $d; }
            else break;
        }
    }
    $stats['streak_actuel'] = $streak;

    // Notifications non lues
    $notif = dbRow(
        "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND lu = 0",
        [$userId]
    );
    $stats['notifs_non_lues'] = (int)($notif['cnt'] ?? 0);

    return $stats;
}

// ── Vérifier si plan actif ─────────────────────────────────
function plan_actif(array $user): bool {
    if ($user['plan'] === 'GRATUIT') return true;
    if (!$user['plan_expire_at']) return false;
    return strtotime($user['plan_expire_at']) > time();
}

// ── Enregistrer activité journalière ──────────────────────
function log_activite(string $userId, int $examens = 0, int $questions = 0): void {
    $today = date('Y-m-d');
    $exist = dbRow(
        "SELECT id FROM activite_journaliere WHERE user_id = ? AND date_act = ?",
        [$userId, $today]
    );
    if ($exist) {
        dbQuery(
            "UPDATE activite_journaliere SET examens = examens + ?, questions = questions + ? WHERE user_id = ? AND date_act = ?",
            [$examens, $questions, $userId, $today]
        );
    } else {
        dbInsert('activite_journaliere', [
            'user_id'  => $userId,
            'date_act' => $today,
            'examens'  => $examens,
            'questions' => $questions,
        ]);
    }
    dbQuery(
        "UPDATE utilisateurs SET derniere_activite = NOW() WHERE id = ?",
        [$userId]
    );
}
