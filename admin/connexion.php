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

// Stats rapides pour la sidebar (sans auth)
$statsSidebar = [
    'users'    => (int)(dbRow("SELECT COUNT(*) as n FROM utilisateurs WHERE is_active=1") ?? ['n'=>0])['n'],
    'archives' => (int)(dbRow("SELECT COUNT(*) as n FROM archives") ?? ['n'=>0])['n'],
    'exams'    => (int)(dbRow("SELECT COUNT(*) as n FROM exam_sessions WHERE DATE(started_at)=CURDATE()") ?? ['n'=>0])['n'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Espace Administration — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:#060B14;color:#E2E8F0;font-family:'Inter',sans-serif;overflow:hidden}

/* ── LAYOUT ─────────────────────────────────────── */
.adm-login-wrap{display:flex;height:100vh;overflow:hidden}

/* ── PANNEAU GAUCHE (info) ──────────────────────── */
.adm-panel{
  width:440px;flex-shrink:0;
  background:linear-gradient(160deg,#0D1F2D 0%,#0A1628 40%,#071020 100%);
  border-right:1px solid rgba(255,255,255,.06);
  display:flex;flex-direction:column;justify-content:space-between;
  padding:40px 44px;position:relative;overflow:hidden;
}
.adm-panel::before{
  content:'';position:absolute;top:-80px;left:-80px;
  width:350px;height:350px;
  background:radial-gradient(circle,rgba(0,122,94,.18) 0%,transparent 70%);
  pointer-events:none;
}
.adm-panel::after{
  content:'';position:absolute;bottom:-60px;right:-60px;
  width:280px;height:280px;
  background:radial-gradient(circle,rgba(124,58,237,.12) 0%,transparent 70%);
  pointer-events:none;
}
.panel-logo{display:flex;align-items:center;gap:12px;position:relative}
.panel-logo img{width:38px;height:38px;border-radius:10px}
.panel-logo-text{font-family:'Poppins',sans-serif;font-size:20px;font-weight:900;color:white;letter-spacing:-.3px}
.panel-logo-text span{color:#C9972A}
.panel-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(0,122,94,.2);border:1px solid rgba(0,122,94,.4);
  color:#4ade80;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;
  padding:4px 12px;border-radius:20px;margin-top:4px;
}
.panel-badge .dot{width:6px;height:6px;background:#4ade80;border-radius:50%;animation:blink 1.5s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

.panel-main{position:relative}
.panel-eyebrow{
  font-size:10px;font-weight:700;color:#007A5E;text-transform:uppercase;
  letter-spacing:2px;margin-bottom:14px;
  display:flex;align-items:center;gap:8px;
}
.panel-eyebrow::after{content:'';flex:1;height:1px;background:rgba(0,122,94,.3)}
.panel-title{
  font-family:'Poppins',sans-serif;font-size:28px;font-weight:900;
  line-height:1.25;color:white;margin-bottom:16px;
}
.panel-title span{
  background:linear-gradient(135deg,#00A97F,#C9972A);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.panel-desc{font-size:14px;color:rgba(255,255,255,.5);line-height:1.7;margin-bottom:32px}

/* Stats cards */
.panel-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:32px}
.pstat{
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);
  border-radius:12px;padding:14px 12px;text-align:center;
}
.pstat-val{font-family:'Poppins',sans-serif;font-size:22px;font-weight:900;color:white;line-height:1}
.pstat-lbl{font-size:10px;color:rgba(255,255,255,.35);margin-top:4px;font-weight:500}

/* Fonctions */
.panel-features{display:flex;flex-direction:column;gap:10px}
.pfeat{display:flex;align-items:flex-start;gap:12px}
.pfeat-icon{
  width:32px;height:32px;border-radius:8px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;
}
.pfeat-title{font-size:13px;font-weight:700;color:rgba(255,255,255,.85)}
.pfeat-sub{font-size:11px;color:rgba(255,255,255,.35);line-height:1.5;margin-top:2px}

/* Panel footer */
.panel-foot{position:relative;font-size:11px;color:rgba(255,255,255,.2);border-top:1px solid rgba(255,255,255,.06);padding-top:20px}
.panel-foot a{color:rgba(255,255,255,.3);text-decoration:none}
.panel-foot a:hover{color:rgba(255,255,255,.6)}

/* ── PANNEAU DROIT (formulaire) ─────────────────── */
.adm-form-side{
  flex:1;display:flex;align-items:center;justify-content:center;
  background:#060B14;padding:40px 20px;overflow-y:auto;
}
.adm-form-box{width:100%;max-width:420px}

.form-header{margin-bottom:36px}
.form-eyebrow{
  display:inline-flex;align-items:center;gap:7px;
  background:rgba(201,151,42,.12);border:1px solid rgba(201,151,42,.25);
  color:#C9972A;font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:1.5px;padding:5px 14px;border-radius:20px;margin-bottom:16px;
}
.form-title{font-family:'Poppins',sans-serif;font-size:26px;font-weight:900;color:white;margin-bottom:8px}
.form-sub{font-size:13px;color:rgba(255,255,255,.4);line-height:1.6}

/* Sécurité badge */
.sec-badge{
  display:flex;align-items:center;gap:10px;
  background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2);
  border-radius:10px;padding:10px 14px;margin-bottom:24px;
}
.sec-badge svg{flex-shrink:0;color:#f87171}
.sec-badge-text{font-size:11px;color:rgba(255,255,255,.5);line-height:1.5}
.sec-badge-text strong{color:#f87171;font-weight:700}

/* Champs */
.field{margin-bottom:20px}
.field-label{
  display:flex;align-items:center;justify-content:space-between;
  font-size:12px;font-weight:600;color:rgba(255,255,255,.5);
  text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;
}
.field-wrap{position:relative}
.field-icon{
  position:absolute;left:14px;top:50%;transform:translateY(-50%);
  color:rgba(255,255,255,.2);pointer-events:none;
}
.field-input{
  width:100%;background:rgba(255,255,255,.04);
  border:1.5px solid rgba(255,255,255,.1);border-radius:10px;
  color:white;font-family:'Inter',sans-serif;font-size:14px;
  padding:13px 14px 13px 42px;outline:none;
  transition:border-color .2s,background .2s;
}
.field-input::placeholder{color:rgba(255,255,255,.2)}
.field-input:focus{border-color:#007A5E;background:rgba(0,122,94,.06)}
.field-input.has-toggle{padding-right:42px}
.toggle-pass{
  position:absolute;right:14px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:rgba(255,255,255,.25);padding:0;
  transition:color .2s;
}
.toggle-pass:hover{color:rgba(255,255,255,.6)}

/* Bouton submit */
.btn-admin-submit{
  width:100%;padding:14px;border:none;border-radius:12px;cursor:pointer;
  font-family:'Poppins',sans-serif;font-size:15px;font-weight:800;
  background:linear-gradient(135deg,#007A5E,#005A45);color:white;
  display:flex;align-items:center;justify-content:center;gap:10px;
  transition:transform .15s,box-shadow .15s;
  box-shadow:0 4px 20px rgba(0,122,94,.3);
  margin-top:8px;
}
.btn-admin-submit:hover{transform:translateY(-1px);box-shadow:0 8px 28px rgba(0,122,94,.4)}
.btn-admin-submit:active{transform:translateY(0)}
.btn-admin-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}
.btn-admin-submit .btn-arrow{
  width:22px;height:22px;background:rgba(255,255,255,.15);border-radius:6px;
  display:flex;align-items:center;justify-content:center;
}

/* Erreur */
.alert-admin{
  display:flex;align-items:flex-start;gap:10px;
  background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);
  border-radius:10px;padding:12px 14px;margin-bottom:20px;
  font-size:13px;color:#fca5a5;line-height:1.5;
}

/* Footer form */
.form-foot{
  text-align:center;margin-top:28px;
  font-size:11px;color:rgba(255,255,255,.2);line-height:1.7;
}
.form-foot a{color:rgba(255,255,255,.3);text-decoration:none;font-weight:600}
.form-foot a:hover{color:#007A5E}

/* Code monospace déco */
.code-deco{
  font-family:'JetBrains Mono',monospace;font-size:10px;
  color:rgba(255,255,255,.1);margin-top:20px;line-height:1.8;
  border-left:2px solid rgba(255,255,255,.06);padding-left:12px;
}
.code-deco .k{color:rgba(0,122,94,.5)}
.code-deco .s{color:rgba(201,151,42,.4)}

/* Responsive */
@media(max-width:860px){
  .adm-panel{display:none}
  .adm-form-side{background:linear-gradient(160deg,#0D1F2D,#060B14)}
}
</style>
</head>
<body>
<div class="adm-login-wrap">

  <!-- ═══ PANNEAU GAUCHE ═══════════════════════════════════ -->
  <aside class="adm-panel">
    <!-- Logo -->
    <div>
      <div class="panel-logo">
        <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+">
        <div>
          <div class="panel-logo-text">RÉUSSITE<span>+</span></div>
        </div>
      </div>
      <div class="panel-badge"><span class="dot"></span> Espace Administration</div>
    </div>

    <!-- Contenu central -->
    <div class="panel-main">
      <div class="panel-eyebrow">Centre de contrôle</div>
      <h1 class="panel-title">Gérez votre<br>plateforme <span>EdTech</span><br>en temps réel.</h1>
      <p class="panel-desc">Depuis ce tableau de bord, vous supervisez les utilisateurs, validez les paiements, gérez le contenu pédagogique et analysez les performances avec l'IA.</p>

      <!-- Stats live -->
      <div class="panel-stats">
        <div class="pstat">
          <div class="pstat-val"><?= number_format($statsSidebar['users']) ?></div>
          <div class="pstat-lbl">Utilisateurs</div>
        </div>
        <div class="pstat">
          <div class="pstat-val"><?= number_format($statsSidebar['archives']) ?></div>
          <div class="pstat-lbl">Archives</div>
        </div>
        <div class="pstat">
          <div class="pstat-val"><?= number_format($statsSidebar['exams']) ?></div>
          <div class="pstat-lbl">Examens/jour</div>
        </div>
      </div>

      <!-- Fonctionnalités -->
      <div class="panel-features">
        <div class="pfeat">
          <div class="pfeat-icon" style="background:rgba(0,122,94,.15)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div>
            <div class="pfeat-title">Gestion des utilisateurs</div>
            <div class="pfeat-sub">Créez, modifiez et bloquez des comptes. Exportez les données en CSV.</div>
          </div>
        </div>
        <div class="pfeat">
          <div class="pfeat-icon" style="background:rgba(201,151,42,.12)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9972A" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
          </div>
          <div>
            <div class="pfeat-title">Validation des paiements</div>
            <div class="pfeat-sub">Confirmez ou refusez les abonnements M-Pesa, Orange, Airtel Money.</div>
          </div>
        </div>
        <div class="pfeat">
          <div class="pfeat-icon" style="background:rgba(124,58,237,.12)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          </div>
          <div>
            <div class="pfeat-title">Analyse IA (Groq)</div>
            <div class="pfeat-sub">Insights automatiques sur la conversion, revenus et engagement.</div>
          </div>
        </div>
        <div class="pfeat">
          <div class="pfeat-icon" style="background:rgba(30,95,173,.12)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
          </div>
          <div>
            <div class="pfeat-title">Contenu pédagogique</div>
            <div class="pfeat-sub">Gérez les archives d'examens et la banque de questions.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="panel-foot">
      <div style="margin-bottom:6px">
        &copy; <?= date('Y') ?> RÉUSSITE+ &mdash; Plateforme EdTech RDC &mdash; <a href="/reussiteplus/index.php">Retour au site</a>
      </div>
      <div>Accès sécurisé &middot; Sessions chiffrées &middot; Logs d'activité activés</div>
    </div>
  </aside>

  <!-- ═══ PANNEAU FORMULAIRE ════════════════════════════════ -->
  <main class="adm-form-side">
    <div class="adm-form-box">

      <div class="form-header">
        <div class="form-eyebrow">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Espace réservé &mdash; Admins uniquement
        </div>
        <h2 class="form-title">Connexion<br>Administration</h2>
        <p class="form-sub">Bienvenue. Identifiez-vous pour accéder au panneau de contrôle de la plateforme RÉUSSITE+.</p>
      </div>

      <!-- Avertissement sécurité -->
      <div class="sec-badge">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <div class="sec-badge-text">
          <strong>Zone sécurisée.</strong> Cet accès est réservé aux administrateurs autorisés de RÉUSSITE+. Toute tentative d'accès non autorisée est enregistrée.
        </div>
      </div>

      <!-- Erreurs -->
      <?php if ($errors): ?>
      <div class="alert-admin">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div><?= htmlspecialchars($errors[0]) ?></div>
      </div>
      <?php endif; ?>

      <!-- Formulaire -->
      <form method="POST" id="adminLoginForm" novalidate>
        <?= csrf_field() ?>

        <div class="field">
          <div class="field-label">
            <span>Adresse e-mail</span>
          </div>
          <div class="field-wrap">
            <div class="field-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <input class="field-input" type="email" name="email" id="email"
                   value="<?= htmlspecialchars($email_val) ?>"
                   placeholder="admin@reussiteplus.cd"
                   autocomplete="email" required autofocus>
          </div>
        </div>

        <div class="field">
          <div class="field-label">
            <span>Mot de passe</span>
          </div>
          <div class="field-wrap">
            <div class="field-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <input class="field-input has-toggle" type="password" name="password" id="password"
                   placeholder="••••••••••••"
                   autocomplete="current-password" required>
            <button type="button" class="toggle-pass" onclick="togglePwd()" title="Afficher/masquer">
              <svg id="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-admin-submit" id="submitBtn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Accéder au panneau d'administration
          <div class="btn-arrow">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="9 18 15 12 9 6"/></svg>
          </div>
        </button>
      </form>

      <!-- Code décoratif -->
      <div class="code-deco">
        <div><span class="k">const</span> user = <span class="k">await</span> auth.verify(credentials);</div>
        <div><span class="k">if</span> (user.role === <span class="s">'SUPER_ADMIN'</span>) {</div>
        <div>&nbsp;&nbsp;redirect(<span class="s">'/admin/dashboard'</span>);</div>
        <div>}</div>
      </div>

      <div class="form-foot">
        <a href="/reussiteplus/index.php">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px"><polyline points="15 18 9 12 15 6"/></svg>
          Retour au site public
        </a>
        &nbsp;&middot;&nbsp;
        <a href="/reussiteplus/connexion.php">Connexion élève</a>
      </div>
    </div>
  </main>

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
  btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Vérification en cours...';
});
</script>
<style>
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</body>
</html>
