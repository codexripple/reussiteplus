<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Mon abonnement';
$pageActive = 'abonnement';
$user = require_login();

$planActif  = PLANS[$user['plan']];
$hasIA      = (bool)($planActif['ia'] ?? false);
$isGratuit  = $user['plan'] === 'GRATUIT';
$isPremiumPlus = in_array($user['plan'], ['PREMIUM', 'ECOLE']);

// Stats du mois
$statsExamens  = (int)($user['examens_mois'] ?? 0);
$limitExamens  = $planActif['examens_mois'];

// Historique des abonnements
$abonnements = dbAll(
    "SELECT * FROM abonnements WHERE user_id = ? ORDER BY created_at DESC LIMIT 12",
    [$user['id']]
);

// Progression générale
$totalExamens  = (int)($user['total_examens'] ?? 0);
$scoreMoyen    = (float)($user['score_moyen'] ?? 0);
$streakJours   = (int)($user['streak_jours'] ?? 0);

$planIcons = ['GRATUIT' => 'backpack', 'BASIQUE' => 'zap', 'PREMIUM' => 'crown', 'ECOLE' => 'school'];
$icon = $planIcons[$user['plan']] ?? 'backpack';

include __DIR__ . '/includes/header_app.php';
?>

<style>
/* ── Mon Abonnement ─────────────────────────────────────── */
.abn-hero {
  background: linear-gradient(135deg, #0D1117 0%, #003D2E 100%);
  border-radius: var(--radius-xl); padding: 32px 28px;
  margin-bottom: 24px; position: relative; overflow: hidden;
}
.abn-hero::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse 60% 80% at 0% 50%, rgba(0,122,94,.4) 0%, transparent 70%);
  pointer-events: none;
}
.abn-hero-inner { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
.abn-plan-icon {
  width: 70px; height: 70px; border-radius: 20px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.abn-plan-label { font-size: 11px; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 4px; }
.abn-plan-name { font-family: var(--font-display); font-size: 28px; font-weight: 900; color: #fff; line-height: 1; margin-bottom: 6px; }
.abn-plan-status { font-size: 13px; color: rgba(255,255,255,.65); }
.abn-hero-actions { display: flex; gap: 10px; flex-wrap: wrap; }

/* ── Stats rapides ─────────────────────────────────────── */
.abn-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
@media(max-width: 700px) { .abn-stats { grid-template-columns: repeat(2, 1fr); } }
.abn-stat-card {
  background: var(--blanc); border: 1px solid var(--gris-200);
  border-radius: var(--radius-lg); padding: 18px 16px; text-align: center;
}
.abn-stat-val { font-family: var(--font-display); font-size: 28px; font-weight: 900; line-height: 1; }
.abn-stat-label { font-size: 11px; color: var(--gris-500); margin-top: 4px; text-transform: uppercase; letter-spacing: .4px; }

/* ── Grille features ───────────────────────────────────── */
.feat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 16px; }
@media(max-width: 600px) { .feat-grid { grid-template-columns: 1fr; } }
.feat-item {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 14px 16px; background: var(--gris-50); border-radius: var(--radius);
  border: 1px solid var(--gris-150,var(--gris-200));
}
.feat-item.locked { opacity: .55; }
.feat-icon-wrap {
  width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.feat-title { font-size: 13px; font-weight: 600; color: var(--gris-900); margin-bottom: 2px; }
.feat-desc { font-size: 11px; color: var(--gris-500); line-height: 1.5; }

/* ── Section IA ────────────────────────────────────────── */
.ia-section {
  background: linear-gradient(135deg, #0D1117 0%, #1a0a3d 100%);
  border-radius: var(--radius-xl); padding: 28px; margin-bottom: 24px;
  position: relative; overflow: hidden;
}
.ia-section::after {
  content: ''; position: absolute; top: -40px; right: -40px;
  width: 200px; height: 200px; border-radius: 50%;
  background: radial-gradient(circle, rgba(124,58,237,.4) 0%, transparent 70%);
  pointer-events: none;
}
.ia-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(124,58,237,.25); border: 1px solid rgba(124,58,237,.5);
  color: #C4B5FD; padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 700;
  margin-bottom: 14px;
}
.ia-title { font-family: var(--font-display); font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 8px; }
.ia-title span { color: #C4B5FD; }
.ia-desc { font-size: 13px; color: rgba(255,255,255,.6); line-height: 1.7; max-width: 540px; margin-bottom: 20px; }
.ia-features { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
@media(max-width: 600px) { .ia-features { grid-template-columns: 1fr; } }
.ia-feat {
  display: flex; align-items: flex-start; gap: 10px;
  background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
  border-radius: var(--radius); padding: 12px 14px;
}
.ia-feat-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(124,58,237,.35); flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.ia-feat-title { font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 2px; }
.ia-feat-desc { font-size: 11px; color: rgba(255,255,255,.5); line-height: 1.5; }
.ia-cta { display: flex; align-items: center; gap: 12px; margin-top: 20px; flex-wrap: wrap; }
.btn-ia { background: #7C3AED; color: #fff; border: none; padding: 12px 22px; border-radius: var(--radius); font-weight: 700; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: opacity .15s; }
.btn-ia:hover { opacity: .9; }

/* ── Upgrade banner ────────────────────────────────────── */
.upgrade-banner {
  background: linear-gradient(135deg, #FEF9EC 0%, #FFF3C4 100%);
  border: 1.5px solid #F59E0B; border-radius: var(--radius-lg);
  padding: 20px 24px; margin-bottom: 24px;
  display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.upgrade-banner-text { flex: 1; }
.upgrade-banner-title { font-family: var(--font-display); font-size: 16px; font-weight: 800; color: #92400E; margin-bottom: 4px; }
.upgrade-banner-desc { font-size: 13px; color: #B45309; }

/* ── Historique table ──────────────────────────────────── */
.abn-table th { font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: var(--gris-500); padding: 10px 14px; border-bottom: 2px solid var(--gris-200); }
.abn-table td { padding: 11px 14px; font-size: 13px; border-bottom: 1px solid var(--gris-100); }
.abn-table tr:last-child td { border-bottom: none; }
.status-pill { padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
</style>

<!-- ══ HERO PLAN ACTUEL ══════════════════════════════════════ -->
<div class="abn-hero">
  <div class="abn-hero-inner">
    <div style="display:flex;align-items:center;gap:18px">
      <div class="abn-plan-icon" style="background:<?= $planActif['couleur'] ?>">
        <i data-lucide="<?= $icon ?>" style="width:32px;height:32px;stroke:#fff;fill:none"></i>
      </div>
      <div>
        <div class="abn-plan-label">Mon abonnement actuel</div>
        <div class="abn-plan-name"><?= e($planActif['nom']) ?></div>
        <?php if (!$isGratuit && $user['plan_expire_at']): ?>
          <?php $joursRestants = max(0, (int)floor((strtotime($user['plan_expire_at']) - time()) / 86400)); ?>
          <div class="abn-plan-status">
            <?php if ($joursRestants <= 7): ?>
              <i data-lucide="alert-triangle" style="width:13px;height:13px;vertical-align:-2px;stroke:#FCA5A5"></i>
              <span style="color:#FCA5A5">Expire dans <?= $joursRestants ?> jour<?= $joursRestants>1?'s':'' ?> · <?= date('d/m/Y', strtotime($user['plan_expire_at'])) ?></span>
            <?php else: ?>
              <i data-lucide="calendar-check" style="width:13px;height:13px;vertical-align:-2px;stroke:#6EE7B7"></i>
              <span style="color:#6EE7B7">Actif jusqu'au <?= date('d/m/Y', strtotime($user['plan_expire_at'])) ?> (<?= $joursRestants ?> jours)</span>
            <?php endif; ?>
          </div>
        <?php elseif ($isGratuit): ?>
          <div class="abn-plan-status">
            <i data-lucide="zap" style="width:13px;height:13px;vertical-align:-2px;stroke:#FBBF24"></i>
            <span style="color:rgba(255,255,255,.6)"><?= $statsExamens ?>/<?= FREE_EXAMS_PER_MONTH ?> examens utilisés ce mois</span>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="abn-hero-actions">
      <?php if ($isGratuit): ?>
        <a href="/reussiteplus/paiement.php?plan=PREMIUM" class="btn" style="background:#FBBF24;color:#1C1C1C;font-weight:700;border:none">
          <i data-lucide="crown" style="width:14px;height:14px;vertical-align:-2px;stroke:#1C1C1C"></i> Passer à Premium
        </a>
      <?php elseif ($user['plan'] === 'BASIQUE'): ?>
        <a href="/reussiteplus/paiement.php?plan=PREMIUM" class="btn" style="background:#FBBF24;color:#1C1C1C;font-weight:700;border:none">
          <i data-lucide="zap" style="width:14px;height:14px;vertical-align:-2px;stroke:#1C1C1C"></i> Passer à Premium + IA
        </a>
      <?php endif; ?>
      <?php if (!$isGratuit): ?>
        <a href="/reussiteplus/paiement.php?plan=<?= e($user['plan']) ?>" class="btn" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);font-weight:600">
          <i data-lucide="refresh-cw" style="width:13px;height:13px;vertical-align:-2px;stroke:#fff"></i> Renouveler
        </a>
      <?php endif; ?>
      <a href="/reussiteplus/tarifs.php" class="btn" style="background:transparent;color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.25);font-size:13px">
        Voir tous les plans
      </a>
    </div>
  </div>
</div>

<!-- ══ STATS RAPIDES ══════════════════════════════════════════ -->
<div class="abn-stats">
  <div class="abn-stat-card">
    <div class="abn-stat-val" style="color:var(--primary)"><?= $statsExamens ?><?= $limitExamens > 0 ? '<span style="font-size:16px;color:var(--gris-400)">/'.$limitExamens.'</span>' : '<span style="font-size:16px;color:var(--gris-400)">∞</span>' ?></div>
    <div class="abn-stat-label">Examens ce mois</div>
  </div>
  <div class="abn-stat-card">
    <div class="abn-stat-val" style="color:#1E5FAD"><?= $totalExamens ?></div>
    <div class="abn-stat-label">Total examens</div>
  </div>
  <div class="abn-stat-card">
    <div class="abn-stat-val" style="color:var(--gold)"><?= number_format($scoreMoyen, 0) ?><span style="font-size:16px;color:var(--gris-400)">%</span></div>
    <div class="abn-stat-label">Score moyen</div>
  </div>
  <div class="abn-stat-card">
    <div class="abn-stat-val" style="color:#F97316"><?= $streakJours ?></div>
    <div class="abn-stat-label">Jours de suite <i data-lucide="flame" style="width:13px;height:13px;vertical-align:-2px;color:#F97316"></i></div>
  </div>
</div>

<!-- ══ SECTION IA (visible si Premium / École) ══════════════ -->
<?php if ($hasIA): ?>
<div class="ia-section" style="margin-bottom:24px">
  <div style="position:relative">
    <div class="ia-badge">
      <i data-lucide="sparkles" style="width:12px;height:12px;stroke:#C4B5FD"></i>
      Assistant IA activé · Powered by LLaMA 3
    </div>
    <div class="ia-title">Votre tuteur <span>intelligent</span> est prêt</div>
    <div class="ia-desc">
      RÉUSSITE+ IA analyse vos résultats, identifie vos lacunes et vous prépare de manière personnalisée
      aux examens ENAFEP, TENASOSP et Examen d'État. Posez n'importe quelle question, recevez une explication
      claire en français.
    </div>
    <div class="ia-features">
      <div class="ia-feat">
        <div class="ia-feat-icon">
          <i data-lucide="message-circle" style="width:16px;height:16px;stroke:#C4B5FD"></i>
        </div>
        <div>
          <div class="ia-feat-title">Chat IA en français</div>
          <div class="ia-feat-desc">Posez vos questions de cours et obtenez des réponses détaillées instantanément.</div>
        </div>
      </div>
      <div class="ia-feat">
        <div class="ia-feat-icon">
          <i data-lucide="brain" style="width:16px;height:16px;stroke:#C4B5FD"></i>
        </div>
        <div>
          <div class="ia-feat-title">Analyse des lacunes</div>
          <div class="ia-feat-desc">L'IA détecte vos points faibles et suggère des exercices ciblés.</div>
        </div>
      </div>
      <div class="ia-feat">
        <div class="ia-feat-icon">
          <i data-lucide="lightbulb" style="width:16px;height:16px;stroke:#C4B5FD"></i>
        </div>
        <div>
          <div class="ia-feat-title">Explications personnalisées</div>
          <div class="ia-feat-desc">Chaque réponse incorrecte est expliquée avec la méthode adaptée à votre niveau.</div>
        </div>
      </div>
      <div class="ia-feat">
        <div class="ia-feat-icon">
          <i data-lucide="calendar" style="width:16px;height:16px;stroke:#C4B5FD"></i>
        </div>
        <div>
          <div class="ia-feat-title">Plan de révision IA</div>
          <div class="ia-feat-desc">Planification automatique de vos révisions selon votre date d'examen.</div>
        </div>
      </div>
    </div>
    <div class="ia-cta">
      <a href="/reussiteplus/dashboard.php#ia-chat" class="btn-ia">
        <i data-lucide="sparkles" style="width:16px;height:16px;stroke:#fff"></i>
        Ouvrir l'Assistant IA
      </a>
      <span style="font-size:12px;color:rgba(255,255,255,.4)">Disponible 24h/24 · Modèle LLaMA 3 via Groq</span>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ UPGRADE BANNER (si pas IA) ════════════════════════════ -->
<?php if (!$hasIA): ?>
<div class="upgrade-banner">
  <div class="upgrade-banner-text">
    <div class="upgrade-banner-title">
      <i data-lucide="sparkles" style="width:16px;height:16px;vertical-align:-3px;stroke:#92400E"></i>
      Débloquez l'Assistant IA RÉUSSITE+
    </div>
    <div class="upgrade-banner-desc">
      Le plan Premium inclut un tuteur IA personnalisé, des plans de révision intelligents et des explications
      automatiques pour chaque question. Passez à Premium dès maintenant.
    </div>
  </div>
  <a href="/reussiteplus/paiement.php?plan=PREMIUM" class="btn" style="background:#F59E0B;color:#fff;font-weight:700;border:none;flex-shrink:0;white-space:nowrap">
    <i data-lucide="crown" style="width:14px;height:14px;vertical-align:-2px;stroke:#fff"></i> Passer à Premium
  </a>
</div>
<?php endif; ?>

<!-- ══ FONCTIONNALITÉS DE VOTRE PLAN ═════════════════════════ -->
<div class="card" style="margin-bottom:24px">
  <div style="font-family:var(--font-display);font-size:16px;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:8px">
    <i data-lucide="package-check" style="width:18px;height:18px;stroke:var(--primary)"></i>
    Inclus dans votre plan <?= e($planActif['nom']) ?>
  </div>
  <div style="font-size:13px;color:var(--gris-500);margin-bottom:16px">Toutes les fonctionnalités disponibles avec votre abonnement actuel.</div>
  <div class="feat-grid">
    <?php
    $features = [
      [
        'icon'   => 'pencil-line',
        'color'  => '#007A5E',
        'title'  => 'Examens par mois',
        'desc'   => $planActif['examens_mois'] === -1 ? 'Illimité — passez autant d\'examens que vous voulez' : "{$planActif['examens_mois']} examens par mois inclus ({$statsExamens} utilisés)",
        'ok'     => true,
      ],
      [
        'icon'   => 'book-open',
        'color'  => '#1E5FAD',
        'title'  => 'Questions par examen',
        'desc'   => $planActif['questions'] === -1 ? 'Illimité — toute la banque de +1 000 questions' : "{$planActif['questions']} questions max par examen",
        'ok'     => true,
      ],
      [
        'icon'   => 'archive',
        'color'  => '#7C3AED',
        'title'  => 'Archives officielles',
        'desc'   => 'ENAFEP, TENASOSP, Examen d\'État — accès à tous les sujets des années précédentes',
        'ok'     => (bool)$planActif['archives'],
      ],
      [
        'icon'   => 'file-check',
        'color'  => '#0891B2',
        'title'  => 'Corrigés et explications',
        'desc'   => 'Réponses détaillées avec méthode de résolution pour chaque question',
        'ok'     => (bool)$planActif['corrige'],
      ],
      [
        'icon'   => 'sparkles',
        'color'  => '#7C3AED',
        'title'  => 'Assistant IA personnalisé',
        'desc'   => 'Tuteur intelligent LLaMA 3 — explications, analyse des lacunes, plan de révision',
        'ok'     => (bool)$planActif['ia'],
      ],
      [
        'icon'   => 'trending-up',
        'color'  => '#059669',
        'title'  => 'Suivi de progression',
        'desc'   => 'Tableau de bord détaillé, graphiques de performance, historique complet',
        'ok'     => true,
      ],
      [
        'icon'   => 'bookmark',
        'color'  => '#D97706',
        'title'  => 'Signets et favoris',
        'desc'   => 'Sauvegardez les questions et archives pour les réviser plus tard',
        'ok'     => true,
      ],
      [
        'icon'   => 'bell',
        'color'  => '#DC2626',
        'title'  => 'Notifications intelligentes',
        'desc'   => 'Rappels de révision, alertes résultats et mises à jour des archives',
        'ok'     => true,
      ],
    ];
    if (isset($planActif['eleves_max'])) {
      $features[] = [
        'icon'  => 'users',
        'color' => '#007A5E',
        'title' => 'Gestion multi-élèves',
        'desc'  => "Gérez jusqu'à {$planActif['eleves_max']} élèves, suivez leur progression individuellement",
        'ok'    => true,
      ];
    }
    foreach ($features as $f):
    ?>
    <div class="feat-item <?= !$f['ok'] ? 'locked' : '' ?>">
      <div class="feat-icon-wrap" style="background:<?= $f['color'] ?><?= $f['ok'] ? '18' : '10' ?>">
        <i data-lucide="<?= $f['icon'] ?>" style="width:18px;height:18px;stroke:<?= $f['ok'] ? $f['color'] : 'var(--gris-400)' ?>"></i>
      </div>
      <div>
        <div class="feat-title" style="<?= !$f['ok'] ? 'color:var(--gris-400)' : '' ?>">
          <?= e($f['title']) ?>
          <?php if (!$f['ok']): ?>
            <span style="font-size:10px;background:var(--gris-200);color:var(--gris-500);padding:1px 7px;border-radius:10px;margin-left:4px;font-weight:600">
              <i data-lucide="lock" style="width:9px;height:9px;vertical-align:-1px"></i> Non inclus
            </span>
          <?php else: ?>
            <span style="font-size:10px;background:<?= $f['color'] ?>18;color:<?= $f['color'] ?>;padding:1px 7px;border-radius:10px;margin-left:4px;font-weight:600">✓</span>
          <?php endif; ?>
        </div>
        <div class="feat-desc"><?= e($f['desc']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php
  $lockedCount = count(array_filter($features, fn($f) => !$f['ok']));
  if ($lockedCount > 0):
  ?>
  <div style="margin-top:16px;padding:14px 16px;background:var(--gris-50);border-radius:var(--radius);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div style="font-size:13px;color:var(--gris-600)">
      <i data-lucide="lock" style="width:13px;height:13px;vertical-align:-2px;stroke:var(--gris-400)"></i>
      <?= $lockedCount ?> fonctionnalité<?= $lockedCount>1?'s':'' ?> verrouillée<?= $lockedCount>1?'s':'' ?> dans votre plan
    </div>
    <a href="/reussiteplus/tarifs.php" class="btn btn-ghost btn-sm">
      <i data-lucide="arrow-right" style="width:12px;height:12px;vertical-align:-2px"></i> Changer de plan
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- ══ HISTORIQUE DES PAIEMENTS ══════════════════════════════ -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <div style="font-family:var(--font-display);font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px">
      <i data-lucide="receipt" style="width:18px;height:18px;stroke:var(--primary)"></i>
      Historique des paiements
    </div>
    <a href="/reussiteplus/tarifs.php" class="btn btn-primary btn-sm">
      <i data-lucide="plus" style="width:12px;height:12px;vertical-align:-2px"></i> Nouveau paiement
    </a>
  </div>

  <?php if ($abonnements): ?>
  <div style="overflow-x:auto;margin-top:4px">
    <table style="width:100%;border-collapse:collapse" class="abn-table">
      <thead>
        <tr>
          <th style="text-align:left">Référence</th>
          <th style="text-align:left">Plan</th>
          <th style="text-align:right">Montant</th>
          <th>Méthode</th>
          <th>Période</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($abonnements as $ab):
        $statusConfig = [
          'EN_ATTENTE' => ['bg'=>'#FEF3C7','c'=>'#92400E','icon'=>'clock'],
          'CONFIRME'   => ['bg'=>'#D1FAE5','c'=>'#065F46','icon'=>'check-circle'],
          'REFUSE'     => ['bg'=>'#FEE2E2','c'=>'#7F1D1D','icon'=>'x-circle'],
          'EXPIRE'     => ['bg'=>'#F3F4F6','c'=>'#6B7280','icon'=>'calendar-x'],
        ];
        $sc = $statusConfig[$ab['statut']] ?? $statusConfig['EN_ATTENTE'];
        $methodNom = METHODES_PAIEMENT[$ab['methode_paiement']]['nom'] ?? $ab['methode_paiement'];
        $planNom   = PLANS[$ab['plan']]['nom'] ?? $ab['plan'];
      ?>
      <tr>
        <td style="font-family:var(--font-mono);font-size:11px;color:var(--gris-600)"><?= e($ab['reference_paiement']) ?></td>
        <td>
          <span style="font-size:13px;font-weight:600;color:<?= PLANS[$ab['plan']]['couleur'] ?? 'var(--gris-700)' ?>">
            <i data-lucide="<?= $planIcons[$ab['plan']] ?? 'package' ?>" style="width:12px;height:12px;vertical-align:-1px"></i>
            <?= e($planNom) ?>
          </span>
        </td>
        <td style="text-align:right;font-weight:700;font-size:14px;white-space:nowrap">
          <?= number_format((float)$ab['montant'], 0, ',', ' ') ?> <span style="font-size:11px;color:var(--gris-400)"><?= e($ab['devise']) ?></span>
        </td>
        <td style="text-align:center;font-size:12px;color:var(--gris-600)"><?= e($methodNom) ?></td>
        <td style="font-size:11px;color:var(--gris-500);white-space:nowrap">
          <?= date('d/m/y', strtotime($ab['date_debut'])) ?> → <?= date('d/m/y', strtotime($ab['date_fin'])) ?>
        </td>
        <td style="text-align:center">
          <span class="status-pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['c'] ?>">
            <i data-lucide="<?= $sc['icon'] ?>" style="width:10px;height:10px;vertical-align:-1px"></i>
            <?= e($ab['statut']) ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:40px 20px;color:var(--gris-400)">
    <i data-lucide="receipt" style="width:40px;height:40px;stroke:var(--gris-300);margin-bottom:12px"></i>
    <div style="font-size:15px;font-weight:600;color:var(--gris-600);margin-bottom:4px">Aucun paiement enregistré</div>
    <div style="font-size:13px;margin-bottom:20px">Choisissez un plan pour commencer votre abonnement.</div>
    <a href="/reussiteplus/tarifs.php" class="btn btn-primary btn-sm">Voir les plans</a>
  </div>
  <?php endif; ?>
</div>

<!-- ══ SUPPORT ════════════════════════════════════════════════ -->
<div style="background:var(--gris-50);border:1px solid var(--gris-200);border-radius:var(--radius-lg);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:8px">
  <div>
    <div style="font-size:14px;font-weight:600;margin-bottom:4px">
      <i data-lucide="headphones" style="width:15px;height:15px;vertical-align:-2px;stroke:var(--primary)"></i>
      Support paiement
    </div>
    <div style="font-size:13px;color:var(--gris-500)">Un problème avec votre abonnement ? Notre équipe répond sous 24h.</div>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="mailto:paiement@reussiteplus.cd" class="btn btn-ghost btn-sm">
      <i data-lucide="mail" style="width:13px;height:13px;vertical-align:-2px"></i> Email
    </a>
    <a href="https://wa.me/243977329184" target="_blank" rel="noopener" class="btn btn-sm" style="background:#25D366;color:#fff;border:none;font-weight:600">
      <i data-lucide="message-circle" style="width:13px;height:13px;vertical-align:-2px;stroke:#fff"></i> WhatsApp
    </a>
  </div>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
