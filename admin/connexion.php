<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Déjà connecté comme admin → dashboard admin
if (is_logged()) {
    if (is_admin()) { header('Location: /reussiteplus/admin/index.php'); exit; }
    else { header('Location: /reussiteplus/dashboard.php'); exit; }
}

$errors = [];
$email_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $result = auth_login(
            trim($_POST['email'] ?? ''),
            $_POST['password'] ?? ''
        );
        if ($result['ok']) {
            $role = $result['user']['role'] ?? '';
            if (in_array($role, ['SUPER_ADMIN', 'ADMIN', 'MODERATEUR'])) {
                header('Location: /reussiteplus/admin/index.php?welcome=1');
                exit;
            } else {
                // Compte non admin → déconnecter et afficher erreur
                session_unset();
                session_destroy();
                session_start();
                $errors[] = 'Accès refusé. Ce compte ne dispose pas des droits d\'administration.';
            }
        } else {
            $errors[] = $result['msg'];
        }
    }
    $email_val = htmlspecialchars($_POST['email'] ?? '');
}

$statsSidebar = [
    'users'    => (int)(dbRow("SELECT COUNT(*) as n FROM utilisateurs WHERE is_active=1") ?? ['n'=>0])['n'],
    'archives' => (int)(dbRow("SELECT COUNT(*) as n FROM archives") ?? ['n'=>0])['n'],
    'exams'    => (int)(dbRow("SELECT COUNT(*) as n FROM exam_sessions WHERE DATE(started_at)=CURDATE()") ?? ['n'=>0])['n'],
];
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '—';
$clientIp = htmlspecialchars(explode(',', $clientIp)[0]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portail Admin — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&family=JetBrains+Mono:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:#04080F;font-family:'Inter',sans-serif;color:#CBD5E1;overflow:hidden}

/* ── FOND GRILLE ────────────────────────────────── */
.bg{
  position:fixed;inset:0;
  background-image:
    linear-gradient(rgba(0,200,120,.035) 1px,transparent 1px),
    linear-gradient(90deg,rgba(0,200,120,.035) 1px,transparent 1px);
  background-size:42px 42px;
  background-position:center center;
}
.bg::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse 70% 60% at 50% 50%,rgba(0,122,94,.08) 0%,transparent 70%);
}
.bg::after{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse 40% 40% at 80% 10%,rgba(124,58,237,.07) 0%,transparent 60%);
}

/* ── SCANLINES déco ─────────────────────────────── */
.scanlines{
  position:fixed;inset:0;pointer-events:none;z-index:1;
  background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.04) 3px,rgba(0,0,0,.04) 4px);
}

/* ── LAYOUT ─────────────────────────────────────── */
.wrap{
  position:relative;z-index:2;
  height:100vh;display:flex;
}

/* ── COLONNE GAUCHE ─────────────────────────────── */
.col-left{
  width:340px;flex-shrink:0;
  display:flex;flex-direction:column;
  padding:40px 36px;
  border-right:1px solid rgba(0,200,120,.1);
  background:rgba(0,8,16,.6);
  backdrop-filter:blur(4px);
}

/* Terminal header */
.term-bar{
  display:flex;align-items:center;gap:8px;
  background:#0A1628;border:1px solid rgba(255,255,255,.06);
  border-radius:10px 10px 0 0;padding:10px 14px;margin-bottom:0;
}
.term-dots{display:flex;gap:6px}
.term-dot{width:10px;height:10px;border-radius:50%}
.term-title{font-family:'JetBrains Mono',monospace;font-size:10px;color:rgba(255,255,255,.25);flex:1;text-align:center;letter-spacing:.5px}

.term-body{
  background:#070F1A;border:1px solid rgba(255,255,255,.06);border-top:none;
  border-radius:0 0 10px 10px;padding:16px;
  font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.9;
  flex-shrink:0;
}
.t-dim{color:rgba(255,255,255,.2)}
.t-key{color:#4ade80}
.t-val{color:#93c5fd}
.t-str{color:#fbbf24}
.t-ok{color:#4ade80;font-weight:700}
.t-warn{color:#f59e0b}
.t-cursor{display:inline-block;width:7px;height:13px;background:#4ade80;vertical-align:-2px;animation:cur .9s step-end infinite}
@keyframes cur{0%,100%{opacity:1}50%{opacity:0}}

/* Stats live */
.stats-live{margin-top:24px;flex:1}
.stats-live-title{font-family:'JetBrains Mono',monospace;font-size:9px;color:rgba(0,200,120,.5);letter-spacing:2px;text-transform:uppercase;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.stats-live-title::after{content:'';flex:1;height:1px;background:rgba(0,200,120,.15)}

.sstat{
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 0;border-bottom:1px solid rgba(255,255,255,.04);
}
.sstat:last-child{border-bottom:none}
.sstat-lbl{font-size:11px;color:rgba(255,255,255,.35);display:flex;align-items:center;gap:8px}
.sstat-lbl svg{width:13px;height:13px;flex-shrink:0}
.sstat-val{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:700;color:#e2e8f0}

.col-left-foot{
  margin-top:auto;padding-top:20px;border-top:1px solid rgba(255,255,255,.05);
  font-family:'JetBrains Mono',monospace;font-size:9px;
  color:rgba(255,255,255,.15);line-height:2;
}

/* ── COLONNE CENTRALE (formulaire) ──────────────── */
.col-center{
  flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:40px 24px;
  background:rgba(0,4,10,.4);
  backdrop-filter:blur(2px);
}

.lock-wrap{
  width:72px;height:72px;margin:0 auto 28px;
  background:linear-gradient(135deg,rgba(0,122,94,.2),rgba(124,58,237,.2));
  border:1px solid rgba(0,200,120,.25);border-radius:20px;
  display:flex;align-items:center;justify-content:center;position:relative;
}
.lock-wrap::before{
  content:'';position:absolute;inset:-1px;border-radius:21px;
  background:linear-gradient(135deg,rgba(0,200,120,.3),transparent,rgba(124,58,237,.3));
  z-index:-1;
  animation:border-spin 4s linear infinite;
}
@keyframes border-spin{to{transform:rotate(360deg)}}
.lock-wrap svg{width:32px;height:32px;stroke:#4ade80;stroke-width:1.8}

.form-system-id{
  font-family:'JetBrains Mono',monospace;font-size:10px;
  color:rgba(0,200,120,.45);letter-spacing:3px;text-transform:uppercase;
  text-align:center;margin-bottom:10px;
}

.form-title{
  font-family:'Poppins',sans-serif;font-size:24px;font-weight:900;
  color:#f1f5f9;text-align:center;margin-bottom:6px;letter-spacing:-.3px;
}
.form-sub{
  font-size:12px;color:rgba(255,255,255,.3);text-align:center;
  line-height:1.6;margin-bottom:28px;max-width:340px;
}

.adm-form-box{width:100%;max-width:380px}

/* Badge IP */
.ip-row{
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.18);
  border-radius:8px;padding:8px 12px;margin-bottom:20px;
  font-family:'JetBrains Mono',monospace;font-size:10px;
}
.ip-label{color:rgba(245,158,11,.6);text-transform:uppercase;letter-spacing:1px}
.ip-val{color:#fbbf24;font-weight:700}

/* Champs */
.field{margin-bottom:16px}
.field-label{
  font-family:'JetBrains Mono',monospace;
  font-size:10px;font-weight:700;color:rgba(0,200,120,.5);
  text-transform:uppercase;letter-spacing:1.5px;margin-bottom:8px;
  display:flex;align-items:center;gap:6px;
}
.field-label::before{content:'>';color:rgba(0,200,120,.35)}
.field-wrap{position:relative}
.field-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.2);pointer-events:none}
.field-icon svg{width:15px;height:15px}
.field-input{
  width:100%;
  background:rgba(255,255,255,.03);
  border:1px solid rgba(255,255,255,.1);border-radius:8px;
  color:#e2e8f0;font-family:'JetBrains Mono',monospace;font-size:13px;
  padding:12px 14px 12px 40px;outline:none;
  transition:border-color .2s,background .2s,box-shadow .2s;
}
.field-input::placeholder{color:rgba(255,255,255,.15);font-size:12px}
.field-input:focus{
  border-color:rgba(0,200,120,.5);
  background:rgba(0,200,120,.04);
  box-shadow:0 0 0 3px rgba(0,200,120,.08);
}
.field-input.has-toggle{padding-right:40px}
.toggle-pass{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:rgba(255,255,255,.2);padding:0;
  transition:color .2s;
}
.toggle-pass:hover{color:rgba(255,255,255,.5)}
.toggle-pass svg{width:15px;height:15px}

/* Bouton */
.btn-adm{
  width:100%;padding:13px;border:none;border-radius:10px;cursor:pointer;
  font-family:'Poppins',sans-serif;font-size:14px;font-weight:800;
  background:linear-gradient(135deg,#007A5E 0%,#00563F 50%,#1a006f 100%);
  color:white;display:flex;align-items:center;justify-content:center;gap:10px;
  transition:transform .15s,box-shadow .15s,opacity .15s;
  box-shadow:0 4px 24px rgba(0,122,94,.35),0 0 0 1px rgba(0,200,120,.15);
  margin-top:6px;letter-spacing:.2px;position:relative;overflow:hidden;
}
.btn-adm::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.1),transparent);
  opacity:0;transition:opacity .2s;
}
.btn-adm:hover{transform:translateY(-1px);box-shadow:0 8px 32px rgba(0,122,94,.5),0 0 0 1px rgba(0,200,120,.25)}
.btn-adm:hover::before{opacity:1}
.btn-adm:active{transform:translateY(0)}
.btn-adm:disabled{opacity:.5;cursor:not-allowed;transform:none}

/* Erreur */
.alert-adm{
  display:flex;align-items:flex-start;gap:10px;
  background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);
  border-radius:8px;padding:12px 14px;margin-bottom:16px;
  font-size:12px;color:#fca5a5;line-height:1.5;
  font-family:'JetBrains Mono',monospace;
}
.alert-adm svg{flex-shrink:0;margin-top:1px}

/* Liens bas */
.form-links{
  text-align:center;margin-top:20px;
  font-family:'JetBrains Mono',monospace;font-size:10px;
  color:rgba(255,255,255,.2);
}
.form-links a{color:rgba(0,200,120,.4);text-decoration:none;transition:color .2s}
.form-links a:hover{color:#4ade80}

/* ── COLONNE DROITE ─────────────────────────────── */
.col-right{
  width:260px;flex-shrink:0;
  display:flex;flex-direction:column;
  padding:40px 28px;
  border-left:1px solid rgba(0,200,120,.08);
  background:rgba(0,4,10,.5);
  backdrop-filter:blur(4px);
}

.cr-section{margin-bottom:28px}
.cr-title{
  font-family:'JetBrains Mono',monospace;font-size:9px;
  color:rgba(0,200,120,.4);text-transform:uppercase;letter-spacing:2px;
  margin-bottom:12px;display:flex;align-items:center;gap:8px;
}
.cr-title::after{content:'';flex:1;height:1px;background:rgba(0,200,120,.1)}

.cr-item{
  display:flex;align-items:center;gap:10px;
  padding:8px 0;border-bottom:1px solid rgba(255,255,255,.03);
  font-size:11px;color:rgba(255,255,255,.4);
}
.cr-item:last-child{border-bottom:none}
.cr-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.cr-dot.green{background:#4ade80;box-shadow:0 0 6px #4ade80}
.cr-dot.amber{background:#fbbf24}
.cr-dot.dim{background:rgba(255,255,255,.1)}

.cr-access{
  background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);
  border-radius:8px;padding:12px;margin-bottom:28px;
}
.cr-access-title{font-size:11px;font-weight:700;color:#fca5a5;margin-bottom:6px;display:flex;align-items:center;gap:6px}
.cr-access-title svg{width:13px;height:13px;stroke:currentColor}
.cr-access-body{font-size:10px;color:rgba(255,255,255,.3);line-height:1.6}

.col-right-foot{
  margin-top:auto;font-family:'JetBrains Mono',monospace;
  font-size:9px;color:rgba(255,255,255,.1);line-height:2;
}

@media(max-width:1000px){.col-left,.col-right{display:none}.col-center{background:rgba(4,8,15,.95)}}
</style>
</head>
<body>
<div class="bg"></div>
<div class="scanlines"></div>

<div class="wrap">

  <!-- ═══ COLONNE GAUCHE — TERMINAL ═══════════════ -->
  <aside class="col-left">
    <div class="term-bar">
      <div class="term-dots">
        <div class="term-dot" style="background:#FF5F57"></div>
        <div class="term-dot" style="background:#FFBD2E"></div>
        <div class="term-dot" style="background:#28CA42"></div>
      </div>
      <div class="term-title">reussiteplus@admin ~ auth-portal</div>
    </div>
    <div class="term-body">
      <div><span class="t-dim">$</span> <span class="t-key">system</span> <span class="t-val">status</span></div>
      <div><span class="t-dim">→</span> <span class="t-str">RÉUSSITE+ Admin Portal</span></div>
      <div><span class="t-dim">→</span> version <span class="t-ok">v2.1.0</span></div>
      <div><span class="t-dim">→</span> env <span class="t-warn">PRODUCTION</span></div>
      <div><span class="t-dim">→</span> db <span class="t-ok">CONNECTED</span></div>
      <div><span class="t-dim">→</span> auth <span class="t-ok">ACTIVE</span></div>
      <div style="margin-top:4px"><span class="t-dim">$</span> <span class="t-key">auth</span> <span class="t-val">--require-role</span> SUPER_ADMIN<span class="t-cursor"></span></div>
    </div>

    <div class="stats-live">
      <div class="stats-live-title">live metrics</div>
      <div class="sstat">
        <span class="sstat-lbl">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(0,200,120,.5)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          utilisateurs actifs
        </span>
        <span class="sstat-val"><?= number_format($statsSidebar['users']) ?></span>
      </div>
      <div class="sstat">
        <span class="sstat-lbl">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(0,200,120,.5)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
          archives
        </span>
        <span class="sstat-val"><?= number_format($statsSidebar['archives']) ?></span>
      </div>
      <div class="sstat">
        <span class="sstat-lbl">
          <svg viewBox="0 0 24 24" fill="none" stroke="rgba(0,200,120,.5)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
          examens aujourd'hui
        </span>
        <span class="sstat-val"><?= number_format($statsSidebar['exams']) ?></span>
      </div>
    </div>

    <div class="col-left-foot">
      <div>platform: reussiteplus.cd</div>
      <div>region: DRC / Kinshasa</div>
      <div>ssl: TLS 1.3 &mdash; <span style="color:rgba(74,222,128,.3)">VALID</span></div>
      <div>session: encrypted</div>
    </div>
  </aside>

  <!-- ═══ COLONNE CENTRALE — FORMULAIRE ════════════ -->
  <main class="col-center">

    <div class="lock-wrap">
      <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke-linecap="round"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>

    <div class="form-system-id">ADMIN — CONTROL PORTAL</div>
    <h1 class="form-title">Authentification Requise</h1>
    <p class="form-sub">Accès réservé aux administrateurs autorisés de la plateforme RÉUSSITE+. Votre session sera journalisée.</p>

    <div class="adm-form-box">

      <!-- IP logged -->
      <div class="ip-row">
        <span class="ip-label">IP détectée</span>
        <span class="ip-val"><?= $clientIp ?></span>
      </div>

      <!-- Erreurs -->
      <?php if ($errors): ?>
      <div class="alert-adm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <div>ACCÈS REFUSÉ — <?= htmlspecialchars($errors[0]) ?></div>
      </div>
      <?php endif; ?>

      <form method="POST" id="adminLoginForm" novalidate>
        <?= csrf_field() ?>

        <div class="field">
          <div class="field-label">identifiant</div>
          <div class="field-wrap">
            <div class="field-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <input class="field-input" type="email" name="email" id="email"
                   value="<?= htmlspecialchars($email_val) ?>"
                   placeholder="admin@reussiteplus.cd"
                   autocomplete="username" required autofocus>
          </div>
        </div>

        <div class="field">
          <div class="field-label">code d'accès</div>
          <div class="field-wrap">
            <div class="field-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <input class="field-input has-toggle" type="password" name="password" id="password"
                   placeholder="••••••••••••"
                   autocomplete="current-password" required>
            <button type="button" class="toggle-pass" onclick="togglePwd()" title="Afficher">
              <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-adm" id="submitBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Valider l'acc&egrave;s
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </form>

      <div class="form-links">
        <a href="/reussiteplus/index.php">&#8592; site public</a>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <a href="/reussiteplus/connexion.php">espace &eacute;l&egrave;ves</a>
      </div>

    </div>
  </main>

  <!-- ═══ COLONNE DROITE — STATUT ══════════════════ -->
  <aside class="col-right">

    <div class="cr-access">
      <div class="cr-access-title">
        <svg viewBox="0 0 24 24" fill="none"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Zone Restreinte
      </div>
      <div class="cr-access-body">Acc&egrave;s autoris&eacute; uniquement aux r&ocirc;les SUPER_ADMIN, ADMIN et MOD&Eacute;RATEUR. Toute tentative non autoris&eacute;e est enregistr&eacute;e.</div>
    </div>

    <div class="cr-section">
      <div class="cr-title">services</div>
      <div class="cr-item"><div class="cr-dot green"></div>API Groq IA</div>
      <div class="cr-item"><div class="cr-dot green"></div>Base de donn&eacute;es</div>
      <div class="cr-item"><div class="cr-dot green"></div>Stockage fichiers</div>
      <div class="cr-item"><div class="cr-dot green"></div>Notifications</div>
      <div class="cr-item"><div class="cr-dot green"></div>Paiements</div>
    </div>

    <div class="cr-section">
      <div class="cr-title">permissions</div>
      <div class="cr-item"><div class="cr-dot green"></div>Utilisateurs</div>
      <div class="cr-item"><div class="cr-dot green"></div>Contenu p&eacute;dagogique</div>
      <div class="cr-item"><div class="cr-dot green"></div>Validation paiements</div>
      <div class="cr-item"><div class="cr-dot amber"></div>Config syst&egrave;me</div>
      <div class="cr-item"><div class="cr-dot dim"></div>D&eacute;ploiement (SSH)</div>
    </div>

    <div class="col-right-foot">
      <div>&copy; <?= date('Y') ?> R&Eacute;USSITE+</div>
      <div>EdTech Platform &mdash; RDC</div>
      <div>Build <?= date('Ymd') ?></div>
    </div>
  </aside>

</div>

<script>
function togglePwd() {
  const inp  = document.getElementById('password');
  const icon = document.getElementById('eye-icon');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    inp.type = 'password';
    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}
document.getElementById('adminLoginForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> V&eacute;rification...';
});
</script>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>
</body>
</html>