<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Ma Progression';
$pageActive = 'progression';
$user = require_login();

$stats = get_user_stats($user['id']);

$progressMatieres = dbAll(
    "SELECT up.*, m.nom, m.couleur, m.icone, m.code
     FROM user_progression up
     JOIN matieres m ON up.matiere_id = m.id
     WHERE up.user_id = ?
     ORDER BY up.score_moyen DESC",
    [$user['id']]
);

$historique = dbAll(
    "SELECT es.*, m.nom as matiere_nom, m.couleur
     FROM exam_sessions es
     LEFT JOIN matieres m ON es.matiere_id = m.id
     WHERE es.user_id = ? AND es.statut = 'TERMINE'
     ORDER BY es.finished_at DESC LIMIT 20",
    [$user['id']]
);

$activite30j = dbAll(
    "SELECT date_act, examens, questions
     FROM activite_journaliere
     WHERE user_id = ? AND date_act >= DATE_SUB(CURDATE(), INTERVAL 83 DAY)
     ORDER BY date_act ASC",
    [$user['id']]
);
$actMap = [];
foreach ($activite30j as $a) $actMap[$a['date_act']] = $a;

$classement = null;
if ($user['province_id']) {
    $classement = dbRow(
        "SELECT COUNT(*) + 1 as rang FROM utilisateurs
         WHERE province_id = ? AND score_moyen > ? AND is_active = 1",
        [$user['province_id'], $user['score_moyen'] ?? 0]
    );
}

$hasIA   = (bool)(PLANS[$user['plan']]['ia'] ?? false);
$scoreM  = (float)($user['score_moyen'] ?? 0);
$prenom  = e($user['prenom']);
$initials = strtoupper(substr($user['prenom'], 0, 1));

// Détermination du niveau
$niveau = $scoreM >= 85 ? ['label'=>'Expert',       'color'=>'#007A5E', 'bg'=>'rgba(0,122,94,.12)',    'icon'=>'🏆']
       : ($scoreM >= 70 ? ['label'=>'Avancé',        'color'=>'#1E5FAD', 'bg'=>'rgba(30,95,173,.12)',   'icon'=>'⭐']
       : ($scoreM >= 55 ? ['label'=>'Intermédiaire', 'color'=>'#C9972A', 'bg'=>'rgba(201,151,42,.12)',  'icon'=>'📈']
       : ($scoreM >= 40 ? ['label'=>'Débutant',      'color'=>'#C9342A', 'bg'=>'rgba(201,52,42,.12)',   'icon'=>'🎯']
       :                  ['label'=>'Démarrage',     'color'=>'#9CA3AF', 'bg'=>'rgba(156,163,175,.12)', 'icon'=>'🚀'])));

$streak  = (int)($stats['streak_actuel'] ?? 0);
$jours   = floor((time() - strtotime($user['created_at'])) / 86400);

include __DIR__ . '/includes/header_app.php';
?>
<style>
/* ══ PROGRESSION PAGE — PREMIUM ══════════════════════════════ */

/* ── Hero ── */
.pg-hero {
  background: linear-gradient(135deg, #007A5E 0%, #005A45 50%, #004035 100%);
  border-radius: 18px;
  padding: 28px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 24px;
  position: relative;
  overflow: hidden;
}
.pg-hero::before {
  content: '';
  position: absolute; top: -40px; right: -40px;
  width: 200px; height: 200px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,.07) 0%, transparent 70%);
}
.pg-hero::after {
  content: '';
  position: absolute; bottom: -30px; left: 30%;
  width: 140px; height: 140px; border-radius: 50%;
  background: radial-gradient(circle, rgba(201,151,42,.1) 0%, transparent 70%);
}
.pg-hero-left { flex: 1; min-width: 0; position: relative; z-index: 1; }
.pg-hero-greeting {
  font-size: 22px; font-weight: 800; color: #fff;
  letter-spacing: -.3px; margin-bottom: 4px;
}
.pg-hero-sub { font-size: 13.5px; color: rgba(255,255,255,.6); margin-bottom: 14px; }
.pg-hero-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.pg-hero-badge {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
  border-radius: 8px; padding: 5px 12px;
  font-size: 12px; font-weight: 600; color: rgba(255,255,255,.88);
  backdrop-filter: blur(4px);
}
.pg-hero-right { display: flex; align-items: center; gap: 20px; position: relative; z-index: 1; }
.pg-score-circle {
  width: 100px; height: 100px; border-radius: 50%;
  background: rgba(255,255,255,.12); border: 2px solid rgba(255,255,255,.2);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  backdrop-filter: blur(8px); box-shadow: inset 0 1px 0 rgba(255,255,255,.15);
}
.pg-score-num { font-size: 22px; font-weight: 900; color: #fff; line-height: 1; }
.pg-score-lbl { font-size: 9.5px; color: rgba(255,255,255,.55); margin-top: 2px; text-transform: uppercase; letter-spacing: .5px; }
.pg-level-badge {
  display: flex; flex-direction: column; align-items: center; gap: 5px; text-align: center;
}
.pg-level-icon { font-size: 24px; line-height: 1; }
.pg-level-name {
  font-size: 11px; font-weight: 800; text-transform: uppercase;
  letter-spacing: .6px; color: rgba(255,255,255,.9);
}
.pg-level-sub { font-size: 10px; color: rgba(255,255,255,.4); }

/* ── KPI row ── */
.pg-kpi { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
.pg-kpi-card {
  background: var(--card-bg, #fff);
  border: 1px solid var(--card-border, rgba(0,0,0,.06));
  border-radius: 14px; padding: 16px 18px;
  position: relative; overflow: hidden;
  transition: box-shadow .2s, transform .2s;
}
.pg-kpi-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.08); transform: translateY(-2px); }
.pg-kpi-card::after {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
  border-radius: 14px 14px 0 0;
}
.pg-kpi-card.c-green::after  { background: #007A5E; }
.pg-kpi-card.c-blue::after   { background: #1E5FAD; }
.pg-kpi-card.c-gold::after   { background: #C9972A; }
.pg-kpi-card.c-red::after    { background: #C9342A; }
.pg-kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.pg-kpi-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: var(--gris-500, #9CA3AF); }
.pg-kpi-icon { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; }
.pg-kpi-icon.c-green { background: rgba(0,122,94,.1); }
.pg-kpi-icon.c-blue  { background: rgba(30,95,173,.1); }
.pg-kpi-icon.c-gold  { background: rgba(201,151,42,.1); }
.pg-kpi-icon.c-red   { background: rgba(201,52,42,.1); }
.pg-kpi-val { font-size: 26px; font-weight: 800; letter-spacing: -.5px; line-height: 1.1; margin-bottom: 4px; }
.pg-kpi-sub { font-size: 11.5px; color: var(--gris-500, #9CA3AF); }
.pg-kpi-trend { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 600; margin-top: 4px; }
.pg-kpi-trend.up { color: #007A5E; }
.pg-kpi-trend.down { color: #C9342A; }

/* ── 2-col grid ── */
.pg-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }

/* ── Heatmap ── */
.pg-heatmap-grid {
  display: grid; grid-template-rows: repeat(7, 1fr);
  grid-auto-flow: column; gap: 3px;
  margin-top: 8px; overflow-x: auto;
}
.pg-heat-cell {
  width: 13px; height: 13px; border-radius: 2px;
  transition: transform .15s;
  cursor: default;
}
.pg-heat-cell:hover { transform: scale(1.3); }
.pg-heat-0 { background: var(--gris-100, #F1F5F9); }
.pg-heat-1 { background: rgba(0,122,94,.25); }
.pg-heat-2 { background: rgba(0,122,94,.50); }
.pg-heat-3 { background: rgba(0,122,94,.75); }
.pg-heat-4 { background: #007A5E; }
.pg-heat-legend { display: flex; align-items: center; gap: 6px; margin-top: 10px; font-size: 10.5px; color: var(--gris-400, #9CA3AF); }
.pg-heat-legend-cells { display: flex; gap: 3px; }

/* ── Matières progression ── */
.pg-matiere-item { margin-bottom: 16px; }
.pg-matiere-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.pg-matiere-name { font-size: 13px; font-weight: 600; color: var(--gris-800, #2E3A4A); display: flex; align-items: center; gap: 7px; }
.pg-matiere-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.pg-matiere-score { font-size: 13px; font-weight: 800; }
.pg-matiere-questions { font-size: 10.5px; color: var(--gris-400); margin-left: 6px; }
.pg-progress-wrap { height: 6px; background: var(--gris-100, #F1F5F9); border-radius: 99px; overflow: hidden; }
.pg-progress-fill { height: 100%; border-radius: 99px; transition: width .8s cubic-bezier(.16,1,.3,1); }

/* ── Forces / Faiblesses ── */
.pg-force-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 12px; border-radius: 8px; margin-bottom: 6px;
}
.pg-force-item.top { background: rgba(0,122,94,.06); }
.pg-force-item.low { background: rgba(201,52,42,.06); }
.pg-force-name { font-size: 12.5px; font-weight: 600; color: var(--gris-800); }
.pg-force-score { font-size: 12.5px; font-weight: 800; }
.pg-force-item.top .pg-force-score { color: #007A5E; }
.pg-force-item.low .pg-force-score { color: #C9342A; }

/* ══ IA SECTION ═══════════════════════════════════════════════ */
.ia-section { margin-bottom: 24px; }

.ia-panel {
  background: linear-gradient(160deg, #0d1120 0%, #111827 60%, #0d1120 100%);
  border: 1px solid rgba(124,58,237,.2);
  border-radius: 20px; overflow: hidden;
  box-shadow: 0 8px 32px rgba(0,0,0,.2);
}

/* Topbar IA */
.ia-topbar {
  padding: 14px 20px;
  border-bottom: 1px solid rgba(255,255,255,.07);
  display: flex; align-items: center; justify-content: space-between;
  background: rgba(124,58,237,.08);
}
.ia-topbar-left { display: flex; align-items: center; gap: 10px; }
.ia-bot-av {
  width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
  background: linear-gradient(135deg,#7C3AED,#4F46E5);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 10px rgba(124,58,237,.45);
}
.ia-bot-name { font-size: 14px; font-weight: 700; color: #fff; }
.ia-bot-sub  { font-size: 11px; color: rgba(255,255,255,.4); margin-top: 1px; }
.ia-status-dot {
  width: 7px; height: 7px; border-radius: 50%; background: #22c55e;
  animation: iaPulse 2s ease-in-out infinite;
}
@keyframes iaPulse { 0%,100%{box-shadow:0 0 0 0 rgba(34,197,94,.4)} 50%{box-shadow:0 0 0 5px rgba(34,197,94,0)} }
.ia-topbar-actions { display: flex; gap: 6px; }
.ia-top-btn {
  background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
  border-radius: 8px; padding: 7px 12px; cursor: pointer;
  font-size: 12px; font-weight: 600; color: rgba(255,255,255,.7);
  display: inline-flex; align-items: center; gap: 5px;
  transition: background .15s, color .15s; font-family: inherit;
}
.ia-top-btn:hover { background: rgba(255,255,255,.12); color: #fff; }
.ia-top-btn.ia-primary-btn {
  background: linear-gradient(135deg,#7C3AED,#4F46E5); border-color: transparent;
  color: #fff; box-shadow: 0 2px 8px rgba(124,58,237,.35);
}
.ia-top-btn.ia-primary-btn:hover { box-shadow: 0 4px 16px rgba(124,58,237,.5); }
.ia-top-btn:disabled { opacity: .45; cursor: not-allowed; }
@keyframes iaSpin { to { transform: rotate(360deg); } }
.ia-spinner { animation: iaSpin .7s linear infinite; display: inline-block; }

/* Quick chips */
.ia-chips-row { display: flex; gap: 7px; flex-wrap: wrap; padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,.05); }
.ia-chip {
  font-size: 11.5px; font-weight: 600; padding: 5px 12px; border-radius: 20px;
  background: rgba(124,58,237,.12); border: 1px solid rgba(124,58,237,.28);
  color: #C4B5FD; cursor: pointer; transition: all .15s; white-space: nowrap;
  font-family: inherit;
}
.ia-chip:hover { background: rgba(124,58,237,.28); border-color: #7C3AED; transform: translateY(-1px); }

/* Messages */
.ia-messages {
  min-height: 300px; max-height: 440px; overflow-y: auto;
  padding: 16px 20px; display: flex; flex-direction: column; gap: 14px;
  scroll-behavior: smooth;
}
.ia-messages::-webkit-scrollbar { width: 4px; }
.ia-messages::-webkit-scrollbar-thumb { background: rgba(124,58,237,.3); border-radius: 4px; }

.ia-msg-row { display: flex; gap: 10px; max-width: 88%; animation: iaMsg .3s ease forwards; }
@keyframes iaMsg { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
.ia-msg-row.user { align-self: flex-end; flex-direction: row-reverse; }
.ia-msg-av {
  width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 800; color: #fff; margin-top: 2px;
}
.ia-msg-row.bot  .ia-msg-av { background: linear-gradient(135deg,#7C3AED,#4F46E5); }
.ia-msg-row.user .ia-msg-av { background: rgba(255,255,255,.12); }
.ia-msg-inner { flex: 1; min-width: 0; }
.ia-bubble {
  padding: 10px 14px; border-radius: 12px;
  font-size: 13.5px; line-height: 1.7; word-break: break-word;
  white-space: pre-wrap;
}
.ia-msg-row.bot  .ia-bubble {
  background: rgba(124,58,237,.15); color: rgba(255,255,255,.88);
  border-radius: 4px 12px 12px 12px;
  border: 1px solid rgba(124,58,237,.2);
}
.ia-msg-row.user .ia-bubble {
  background: rgba(255,255,255,.1); color: rgba(255,255,255,.85);
  border-radius: 12px 4px 12px 12px;
}
/* Message markdown */
.ia-bubble strong { font-weight: 700; color: #fff; }
.ia-bubble em { font-style: italic; color: rgba(255,255,255,.7); }
.ia-bubble code {
  background: rgba(255,255,255,.1); border-radius: 4px;
  padding: 1px 5px; font-family: monospace; font-size: 12.5px;
}

/* Message tools */
.ia-msg-tools {
  display: flex; gap: 4px; margin-top: 5px; opacity: 0;
  transition: opacity .15s;
}
.ia-msg-row:hover .ia-msg-tools { opacity: 1; }
.ia-msg-row.user .ia-msg-tools { justify-content: flex-end; }
.ia-tool-btn {
  background: rgba(255,255,255,.06); border: none; border-radius: 6px;
  padding: 4px 7px; cursor: pointer; color: rgba(255,255,255,.45);
  font-size: 11px; display: inline-flex; align-items: center; gap: 4px;
  transition: background .15s, color .15s; font-family: inherit;
}
.ia-tool-btn:hover { background: rgba(255,255,255,.12); color: rgba(255,255,255,.85); }
.ia-tool-btn.copied { color: #22c55e; }

/* Typing dots */
.ia-typing-row {
  display: flex; gap: 10px; max-width: 88%;
  animation: iaMsg .3s ease forwards;
}
.ia-dots {
  padding: 10px 14px; background: rgba(124,58,237,.15);
  border-radius: 4px 12px 12px 12px; border: 1px solid rgba(124,58,237,.2);
  display: flex; gap: 4px; align-items: center;
}
.ia-dot {
  width: 6px; height: 6px; background: #C4B5FD; border-radius: 50%;
  animation: iaBounce .9s ease-in-out infinite;
}
.ia-dot:nth-child(2) { animation-delay: .15s; }
.ia-dot:nth-child(3) { animation-delay: .30s; }
@keyframes iaBounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-6px)} }

/* Input area */
.ia-input-area {
  padding: 12px 16px; border-top: 1px solid rgba(255,255,255,.07);
  display: flex; gap: 8px; align-items: flex-end;
}
.ia-input {
  flex: 1; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12);
  border-radius: 12px; padding: 10px 14px; font-size: 13.5px; color: #fff;
  resize: none; font-family: inherit; line-height: 1.5; max-height: 100px;
  transition: border-color .15s; outline: none;
}
.ia-input::placeholder { color: rgba(255,255,255,.28); }
.ia-input:focus { border-color: rgba(124,58,237,.55); background: rgba(255,255,255,.09); }
.ia-send {
  width: 40px; height: 40px; border-radius: 11px; border: none; flex-shrink: 0;
  background: linear-gradient(135deg,#7C3AED,#4F46E5);
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  transition: transform .15s, box-shadow .15s;
}
.ia-send:hover { transform: scale(1.07); box-shadow: 0 4px 14px rgba(124,58,237,.5); }
.ia-send:disabled { opacity: .4; cursor: not-allowed; transform: none; box-shadow: none; }

/* Premium lock */
.ia-lock-panel {
  background: linear-gradient(160deg, #0d1120, #111827);
  border: 1px solid rgba(124,58,237,.2);
  border-radius: 20px; padding: 36px 28px;
  display: flex; align-items: center; gap: 24px; flex-wrap: wrap;
}
.ia-lock-icon {
  width: 60px; height: 60px; border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg,#7C3AED,#4F46E5);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 20px rgba(124,58,237,.4);
}
.ia-lock-title { font-size: 18px; font-weight: 800; color: #fff; margin-bottom: 6px; }
.ia-lock-desc  { font-size: 13px; color: rgba(255,255,255,.5); line-height: 1.6; }
.ia-lock-cta {
  display: inline-flex; align-items: center; gap: 8px;
  background: linear-gradient(135deg,#7C3AED,#4F46E5);
  color: #fff; padding: 12px 22px; border-radius: 12px;
  font-size: 14px; font-weight: 700; text-decoration: none;
  transition: box-shadow .18s, transform .18s; white-space: nowrap;
  box-shadow: 0 4px 16px rgba(124,58,237,.35);
}
.ia-lock-cta:hover { box-shadow: 0 8px 28px rgba(124,58,237,.55); transform: translateY(-1px); }

/* History table */
.pg-history-wrap { background: var(--card-bg,#fff); border: 1px solid var(--card-border,rgba(0,0,0,.06)); border-radius: 14px; overflow: hidden; }
.pg-history-hd {
  padding: 16px 20px; border-bottom: 1px solid var(--card-border,rgba(0,0,0,.06));
  display: flex; align-items: center; justify-content: space-between;
}

/* Toast */
.pg-toast {
  position: fixed; bottom: 24px; right: 24px; z-index: 999;
  background: #1a2535; border: 1px solid rgba(255,255,255,.12);
  border-radius: 10px; padding: 10px 16px;
  font-size: 13px; color: #fff;
  animation: pgToast .3s ease;
  display: flex; align-items: center; gap: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,.3);
}
@keyframes pgToast { from{opacity:0;transform:translateX(16px)} to{opacity:1;transform:translateX(0)} }

/* Responsive */
@media(max-width:1024px) {
  .pg-grid2 { grid-template-columns: 1fr; }
  .pg-kpi   { grid-template-columns: repeat(2,1fr); }
}
@media(max-width:640px) {
  .pg-hero { flex-direction: column; }
  .pg-hero-right { justify-content: center; }
  .pg-kpi { grid-template-columns: repeat(2,1fr); }
  .ia-topbar { flex-wrap: wrap; gap: 10px; }
  .ia-topbar-actions { flex-wrap: wrap; }
}
</style>

<!-- ══ HERO ═══════════════════════════════════════════════════ -->
<div class="pg-hero">
  <div class="pg-hero-left">
    <div class="pg-hero-greeting">Bonjour, <?= $prenom ?></div>
    <div class="pg-hero-sub">
      <?php
        $h = (int)date('H');
        echo $h < 12 ? 'Bonne matinée' : ($h < 18 ? 'Bon après-midi' : 'Bonne soirée');
      ?> — voici l'état de ta progression
    </div>
    <div class="pg-hero-badges">
      <span class="pg-hero-badge" style="background:<?= $niveau['bg'] ?>;border-color:<?= $niveau['color'] ?>40;color:<?= $niveau['color'] ?>">
        <?= $niveau['icon'] ?> Niveau <?= $niveau['label'] ?>
      </span>
      <?php if ($streak > 0): ?>
      <span class="pg-hero-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
        <?= $streak ?> jour<?= $streak > 1 ? 's' : '' ?> de suite
      </span>
      <?php endif; ?>
      <span class="pg-hero-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Membre depuis <?= $jours ?> jours
      </span>
    </div>
  </div>
  <div class="pg-hero-right">
    <div class="pg-score-circle">
      <div class="pg-score-num"><?= number_format($scoreM, 1) ?>%</div>
      <div class="pg-score-lbl">Score moyen</div>
    </div>
    <?php if ($classement): ?>
    <div class="pg-level-badge">
      <div class="pg-level-icon">🏅</div>
      <div class="pg-level-name">#<?= (int)$classement['rang'] ?></div>
      <div class="pg-level-sub">Province</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ KPI ════════════════════════════════════════════════════ -->
<div class="pg-kpi">
  <div class="pg-kpi-card c-green">
    <div class="pg-kpi-top">
      <span class="pg-kpi-label">Score moyen</span>
      <div class="pg-kpi-icon c-green">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
    </div>
    <div class="pg-kpi-val" style="color:#007A5E"><?= number_format($scoreM,1) ?>%</div>
    <div class="pg-kpi-sub"><?= score_label($scoreM) ?></div>
    <div class="pg-kpi-trend <?= $scoreM >= 50 ? 'up' : 'down' ?>">
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
        <?= $scoreM >= 50 ? '<polyline points="18 15 12 9 6 15"/>' : '<polyline points="6 9 12 15 18 9"/>' ?>
      </svg>
      <?= $scoreM >= 50 ? 'Au-dessus de la moyenne' : 'En dessous de 50 %' ?>
    </div>
  </div>
  <div class="pg-kpi-card c-blue">
    <div class="pg-kpi-top">
      <span class="pg-kpi-label">Examens</span>
      <div class="pg-kpi-icon c-blue">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1E5FAD" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </div>
    </div>
    <div class="pg-kpi-val" style="color:#1E5FAD"><?= number_format((int)($user['total_examens']??0)) ?></div>
    <div class="pg-kpi-sub">Au total</div>
  </div>
  <div class="pg-kpi-card c-gold">
    <div class="pg-kpi-top">
      <span class="pg-kpi-label">Questions</span>
      <div class="pg-kpi-icon c-gold">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9972A" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
    </div>
    <div class="pg-kpi-val" style="color:#C9972A"><?= number_format((int)($user['total_questions']??0)) ?></div>
    <div class="pg-kpi-sub">Répondues</div>
  </div>
  <div class="pg-kpi-card c-red">
    <div class="pg-kpi-top">
      <span class="pg-kpi-label">Série actuelle</span>
      <div class="pg-kpi-icon c-red">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9342A" stroke-width="2.5" stroke-linecap="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
      </div>
    </div>
    <div class="pg-kpi-val" style="color:<?= $streak > 0 ? '#C9342A' : 'var(--gris-400,#A0AEC0)' ?>"><?= $streak ?></div>
    <div class="pg-kpi-sub">jours consécutifs</div>
  </div>
</div>

<!-- ══ HEATMAP + PROGRESSION ══════════════════════════════════ -->
<div class="pg-grid2">

  <!-- Heatmap GitHub-style -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Activité — 12 dernières semaines
      </div>
      <span style="font-size:11.5px;background:rgba(0,122,94,.1);color:#007A5E;border-radius:6px;padding:3px 9px;font-weight:600"><?= count($activite30j) ?> jours actifs</span>
    </div>
    <?php
    // Build 84-day heatmap (12 weeks × 7 days)
    $maxEx2 = max(1, max(array_map(fn($a)=>$a['examens'], $activite30j ?: [['examens'=>0]])));
    ?>
    <div class="pg-heatmap-grid" style="grid-template-rows:repeat(7,13px)">
      <?php for ($i = 83; $i >= 0; $i--):
        $d   = date('Y-m-d', strtotime("-{$i} days"));
        $act = $actMap[$d] ?? null;
        $ex  = $act ? (int)$act['ex'] : ($act ? (int)$act['examens'] : 0);
        // fix: actMap uses date_act key
        $ex  = isset($actMap[$d]) ? (int)$actMap[$d]['examens'] : 0;
        $lvl = $ex === 0 ? 0 : ($ex <= 1 ? 1 : ($ex <= 2 ? 2 : ($ex <= 4 ? 3 : 4)));
        $title = date('d/m', strtotime($d)) . ' — ' . $ex . ' examen(s)';
      ?>
      <div class="pg-heat-cell pg-heat-<?= $lvl ?>" title="<?= e($title) ?>"></div>
      <?php endfor; ?>
    </div>
    <div class="pg-heat-legend">
      <span>Moins</span>
      <div class="pg-heat-legend-cells">
        <div class="pg-heat-cell pg-heat-0" style="width:11px;height:11px"></div>
        <div class="pg-heat-cell pg-heat-1" style="width:11px;height:11px"></div>
        <div class="pg-heat-cell pg-heat-2" style="width:11px;height:11px"></div>
        <div class="pg-heat-cell pg-heat-3" style="width:11px;height:11px"></div>
        <div class="pg-heat-cell pg-heat-4" style="width:11px;height:11px"></div>
      </div>
      <span>Plus</span>
    </div>
  </div>

  <!-- Progression par matière -->
  <?php if ($progressMatieres): ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        Progression par matière
      </div>
      <a href="/reussiteplus/questions.php" class="btn btn-primary btn-sm">S'entraîner</a>
    </div>
    <?php foreach (array_slice($progressMatieres, 0, 6) as $pm):
      $pct = min(100, (float)$pm['score_moyen']);
    ?>
    <div class="pg-matiere-item">
      <div class="pg-matiere-row">
        <span class="pg-matiere-name">
          <span class="pg-matiere-dot" style="background:<?= e($pm['couleur']??'#007A5E') ?>"></span>
          <?= e($pm['nom']) ?>
          <span class="pg-matiere-questions"><?= number_format($pm['questions_vues']) ?> q.</span>
        </span>
        <span class="pg-matiere-score" style="color:<?= score_couleur($pct) ?>"><?= number_format($pct,1) ?>%</span>
      </div>
      <div class="pg-progress-wrap">
        <div class="pg-progress-fill" style="width:<?= $pct ?>%;background:<?= e($pm['couleur']??'#007A5E') ?>"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ══ FORCES & FAIBLESSES ════════════════════════════════════ -->
<?php if (count($progressMatieres) >= 2): ?>
<div class="pg-grid2" style="margin-bottom:24px">
  <?php
  $top3 = array_slice($progressMatieres, 0, 3);
  $low3 = array_slice(array_reverse($progressMatieres), 0, 3);
  ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title" style="color:#007A5E">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        Points forts
      </div>
    </div>
    <?php foreach ($top3 as $t): ?>
    <div class="pg-force-item top">
      <span class="pg-force-name"><?= e($t['nom']) ?></span>
      <span class="pg-force-score"><?= number_format((float)$t['score_moyen'],1) ?>%</span>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <div class="card-header">
      <div class="card-title" style="color:#C9342A">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        À renforcer
      </div>
    </div>
    <?php foreach ($low3 as $l): ?>
    <div class="pg-force-item low">
      <span class="pg-force-name"><?= e($l['nom']) ?></span>
      <span class="pg-force-score"><?= number_format((float)$l['score_moyen'],1) ?>%</span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ══ ASSISTANT IA ══════════════════════════════════════════ -->
<div class="ia-section">

<?php if ($hasIA): ?>
<div class="ia-panel">
  <!-- Topbar -->
  <div class="ia-topbar">
    <div class="ia-topbar-left">
      <div class="ia-bot-av">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M19 3v4"/><path d="M21 5h-4"/></svg>
      </div>
      <div>
        <div class="ia-bot-name">Assistant IA — RÉUSSITE+</div>
        <div class="ia-bot-sub" style="display:flex;align-items:center;gap:5px">
          <div class="ia-status-dot"></div>
          En ligne · Gemini 2.5 Flash
        </div>
      </div>
    </div>
    <div class="ia-topbar-actions">
      <button class="ia-top-btn ia-primary-btn" onclick="genererPlan()" id="btn-plan">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg>
        Plan 7 jours
      </button>
      <button class="ia-top-btn" onclick="analyserErreurs()" id="btn-erreurs">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
        Analyser erreurs
      </button>
    </div>
  </div>

  <!-- Quick chips -->
  <div class="ia-chips-row">
    <?php
    $chips = [
      'Explique-moi les fractions',
      'La loi d\'Ohm en Physique',
      'Les temps de conjugaison',
      'Aide-moi pour l\'Examen d\'État',
      'Révision rapide Mathématiques',
      'Photosynthèse — Biologie',
    ];
    foreach ($chips as $chip):
    ?>
    <button class="ia-chip" onclick="sendChip(this)"><?= e($chip) ?></button>
    <?php endforeach; ?>
  </div>

  <!-- Messages -->
  <div class="ia-messages" id="ia-messages">
    <div class="ia-msg-row bot">
      <div class="ia-msg-av">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M19 3v4"/><path d="M21 5h-4"/></svg>
      </div>
      <div class="ia-msg-inner">
        <div class="ia-bubble">Bonjour <?= $prenom ?> ! Je suis ton assistant IA.

Je me base sur tes <?= number_format((int)($user['total_examens']??0)) ?> examens et ton score moyen de <strong><?= number_format($scoreM,1) ?>%</strong> pour te donner des conseils personnalisés.

Tu peux me demander :
— Un plan de révision sur 7 jours
— L'analyse de tes erreurs récurrentes
— Des explications sur n'importe quelle matière

Que veux-tu travailler aujourd'hui ?</div>
        <div class="ia-msg-tools">
          <button class="ia-tool-btn" onclick="copyMsg(this)" data-text="Bonjour <?= $prenom ?> ! Je suis ton assistant IA.">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            Copier
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Input -->
  <div class="ia-input-area">
    <textarea class="ia-input" id="ia-input" placeholder="Pose ta question… (Entrée pour envoyer)" rows="1"
      onkeydown="iaKeydown(event)" oninput="autoResize(this)"></textarea>
    <button class="ia-send" id="ia-send" onclick="sendMsg()" title="Envoyer">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </div>
</div>

<?php else: ?>
<div class="ia-lock-panel">
  <div class="ia-lock-icon">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M19 3v4"/><path d="M21 5h-4"/></svg>
  </div>
  <div style="flex:1;min-width:200px">
    <div class="ia-lock-title">Débloquer l'Assistant IA</div>
    <div class="ia-lock-desc">Plan de révision personnalisé, analyse de tes erreurs, tuteur intelligent 24h/24. Disponible avec le plan <strong style="color:#C4B5FD">Premium</strong>.</div>
  </div>
  <a href="/reussiteplus/tarifs.php" class="ia-lock-cta">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    Passer à Premium
  </a>
</div>
<?php endif; ?>
</div>

<!-- ══ HISTORIQUE ════════════════════════════════════════════ -->
<?php if ($historique): ?>
<div class="pg-history-wrap">
  <div class="pg-history-hd">
    <div class="card-title">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:6px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Historique des examens
    </div>
    <span style="font-size:12px;color:var(--gris-500)"><?= count($historique) ?> sessions</span>
  </div>
  <div class="table-wrap" style="margin:0;border-radius:0">
    <table class="table">
      <thead>
        <tr><th>Matière</th><th>Type</th><th>Score</th><th>Questions</th><th>Durée</th><th>Date</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($historique as $s):
        $pct3 = (float)($s['pourcentage']??0);
        $m    = floor(($s['temps_passe']??0)/60);
        $sec  = ($s['temps_passe']??0)%60;
      ?>
      <tr>
        <td>
          <span style="display:flex;align-items:center;gap:7px">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= e($s['couleur']??'var(--primary)') ?>;display:inline-block;flex-shrink:0"></span>
            <span style="font-weight:500"><?= e($s['matiere_nom'] ?? $s['titre'] ?? 'Examen') ?></span>
          </span>
        </td>
        <td><span class="badge badge-gray" style="font-size:10px"><?= e($s['exam_type']??'—') ?></span></td>
        <td>
          <span style="font-weight:800;color:<?= score_couleur($pct3) ?>"><?= number_format($pct3,1) ?>%</span>
          <div style="font-size:10px;color:var(--gris-500)"><?= score_label($pct3) ?></div>
        </td>
        <td style="color:var(--gris-600)"><?= (int)$s['nb_questions'] ?> q.</td>
        <td style="color:var(--gris-600)"><?= $m ?>m <?= str_pad($sec,2,'0',STR_PAD_LEFT) ?>s</td>
        <td style="color:var(--gris-500)"><?= date('d/m/Y', strtotime($s['finished_at']??$s['started_at'])) ?></td>
        <td><a href="/reussiteplus/resultat.php?session=<?= e($s['id']) ?>" class="btn btn-ghost btn-sm">Voir</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:40px">
  <div style="width:56px;height:56px;border-radius:14px;background:var(--gris-100);margin:0 auto 14px;display:flex;align-items:center;justify-content:center">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--gris-400)" stroke-width="2" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
  </div>
  <div style="font-size:15px;font-weight:700;margin-bottom:6px;color:var(--gris-800)">Aucun examen passé</div>
  <div style="font-size:13px;color:var(--gris-500);margin-bottom:20px">Commence par passer ton premier examen pour voir ta progression ici.</div>
  <a href="/reussiteplus/examen.php" class="btn btn-primary">Passer un examen maintenant</a>
</div>
<?php endif; ?>

<?php if ($hasIA): ?>
<script>
const CSRF_TOKEN = '<?= e(csrf_token()) ?>';
const USER_INITIAL = '<?= e($initials) ?>';
let chatHistory = [];
let iaLoading   = false;
let lastBotText = '';

// ── API call ──────────────────────────────────────────────────
async function callIA(action, extra = {}) {
  const fd = new FormData();
  fd.append('action',     action);
  fd.append('csrf_token', CSRF_TOKEN);
  for (const [k, v] of Object.entries(extra)) fd.append(k, v);
  const r = await fetch('/reussiteplus/api/revision.php', { method:'POST', body:fd });
  return r.json();
}

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, color = '#22c55e') {
  const t = document.createElement('div');
  t.className = 'pg-toast';
  t.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> ${msg}`;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='.3s'; setTimeout(()=>t.remove(),300); }, 2200);
}

// ── Copier un message ─────────────────────────────────────────
function copyMsg(btn) {
  const text = btn.dataset.text || btn.closest('.ia-msg-inner')?.querySelector('.ia-bubble')?.textContent || '';
  navigator.clipboard?.writeText(text).then(() => {
    btn.classList.add('copied');
    btn.querySelector('svg')?.setAttribute('stroke','#22c55e');
    showToast('Message copié');
    setTimeout(() => { btn.classList.remove('copied'); btn.querySelector('svg')?.setAttribute('stroke','currentColor'); }, 2000);
  });
}

// ── Régénérer la dernière réponse ────────────────────────────
async function regenerate(btn) {
  if (iaLoading || chatHistory.length < 2) return;
  // Retirer le dernier message bot et relancer
  chatHistory.pop();
  const lastUser = [...chatHistory].reverse().find(m => m.role === 'user');
  if (!lastUser) return;
  // Supprimer la dernière bulle bot du DOM
  const msgs = document.getElementById('ia-messages');
  const rows = msgs.querySelectorAll('.ia-msg-row.bot');
  if (rows.length > 1) rows[rows.length-1].remove();
  await sendToIA(lastUser.content, false);
}

// ── Append message ────────────────────────────────────────────
function appendMsg(role, text, animate = true) {
  const box    = document.getElementById('ia-messages');
  const typing = document.getElementById('ia-typing-row');
  const div    = document.createElement('div');
  div.className = 'ia-msg-row ' + role;
  const safe  = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
                    .replace(/\n/g,'<br>');
  div.innerHTML = `
    <div class="ia-msg-av" ${role==='bot'?'style="background:linear-gradient(135deg,#7C3AED,#4F46E5)"':''}>
      ${role==='bot'
        ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M19 3v4"/><path d="M21 5h-4"/></svg>'
        : USER_INITIAL}
    </div>
    <div class="ia-msg-inner">
      <div class="ia-bubble">${safe}</div>
      <div class="ia-msg-tools">
        <button class="ia-tool-btn" onclick="copyMsg(this)" data-text="${text.replace(/"/g,'&quot;')}">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          Copier
        </button>
        ${role==='bot' ? `<button class="ia-tool-btn" onclick="regenerate(this)">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Régénérer
        </button>` : ''}
      </div>
    </div>`;
  if (typing) box.insertBefore(div, typing);
  else box.appendChild(div);
  box.scrollTop = box.scrollHeight;
}

function showTyping(show) {
  let t = document.getElementById('ia-typing-row');
  if (show && !t) {
    t = document.createElement('div');
    t.id = 'ia-typing-row';
    t.className = 'ia-typing-row';
    t.innerHTML = `<div class="ia-msg-av" style="background:linear-gradient(135deg,#7C3AED,#4F46E5)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/><path d="M19 3v4"/><path d="M21 5h-4"/></svg></div><div class="ia-dots"><div class="ia-dot"></div><div class="ia-dot"></div><div class="ia-dot"></div></div>`;
    document.getElementById('ia-messages').appendChild(t);
  } else if (!show && t) {
    t.remove();
  }
  const box = document.getElementById('ia-messages');
  if (box) box.scrollTop = box.scrollHeight;
}

// ── Envoyer message ───────────────────────────────────────────
async function sendMsg() {
  const input = document.getElementById('ia-input');
  const text  = input?.value?.trim();
  if (!text || iaLoading) return;
  input.value = '';
  input.style.height = 'auto';
  appendMsg('user', text);
  chatHistory.push({ role:'user', content:text });
  await sendToIA(text, true);
}

async function sendToIA(text, addToHistory) {
  iaLoading = true;
  document.getElementById('ia-send').disabled = true;
  showTyping(true);
  try {
    const d = await callIA('chat', {
      message: text,
      history: JSON.stringify(chatHistory.filter(m=>m.role!=='user'||m.content!==text).slice(-18))
    });
    showTyping(false);
    const reply = d.ok ? d.content : ('[Erreur] ' + (d.msg || d.error || 'Réessaie.'));
    appendMsg('bot', reply);
    if (addToHistory) chatHistory.push({ role:'assistant', content:reply });
    if (chatHistory.length > 20) chatHistory = chatHistory.slice(-20);
  } catch(e) {
    showTyping(false);
    appendMsg('bot', '[Erreur réseau] Vérifie ta connexion.');
  } finally {
    iaLoading = false;
    document.getElementById('ia-send').disabled = false;
  }
}

// ── Plan + Analyse ────────────────────────────────────────────
function setBtnLoading(id, label) {
  const btn = document.getElementById(id);
  if (!btn) return;
  btn.disabled = true;
  btn.innerHTML = `<span class="ia-spinner"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span> ${label}`;
}

async function genererPlan() {
  setBtnLoading('btn-plan', 'Génération…');
  try {
    const d = await callIA('plan_revision');
    if (d.ok) {
      appendMsg('user', 'Génère mon plan de révision personnalisé sur 7 jours');
      appendMsg('bot', d.content);
      chatHistory.push({ role:'user', content:'Plan de révision 7 jours' });
      chatHistory.push({ role:'assistant', content:d.content });
      showToast('Plan généré');
    } else {
      appendMsg('bot', '[Erreur] ' + (d.msg || d.error));
    }
  } catch(e) { appendMsg('bot', '[Erreur réseau]'); }
  finally {
    const btn = document.getElementById('btn-plan');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/></svg> Plan 7 jours';
    }
  }
}

async function analyserErreurs() {
  setBtnLoading('btn-erreurs', 'Analyse…');
  try {
    const d = await callIA('analyse_erreurs');
    if (d.ok) {
      appendMsg('user', 'Analyse mes erreurs récurrentes');
      appendMsg('bot', d.content);
      chatHistory.push({ role:'user', content:'Analyse erreurs' });
      chatHistory.push({ role:'assistant', content:d.content });
      showToast('Analyse terminée');
    } else {
      appendMsg('bot', '[Erreur] ' + (d.msg || d.error));
    }
  } catch(e) { appendMsg('bot', '[Erreur réseau]'); }
  finally {
    const btn = document.getElementById('btn-erreurs');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg> Analyser erreurs';
    }
  }
}

// ── Chip ──────────────────────────────────────────────────────
function sendChip(el) {
  const input = document.getElementById('ia-input');
  if (input) { input.value = el.textContent; autoResize(input); input.focus(); }
  setTimeout(sendMsg, 80);
}

function iaKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
}
function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}

// Auto-scroll messages on load
document.getElementById('ia-messages')?.scrollTo(0, 999999);
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
