<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }

$errors = [];
$provinces = [];
try { $provinces = dbAll("SELECT id, nom FROM provinces ORDER BY nom"); } catch (Exception $e) {}

$refCode = trim($_GET['ref'] ?? '');
$referralUser = null;
if ($refCode) {
    try {
        $referralUser = dbRow(
            "SELECT id, prenom, nom FROM utilisateurs WHERE referral_code = ?",
            [$refCode]
        );
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $pass   = $_POST['password'] ?? '';
        $classe = trim($_POST['classe'] ?? '');
        $provId = $_POST['province_id'] ?? null;

        if (empty($nom) || empty($prenom))   $errors[] = 'Le nom et le prénom sont requis.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
        if (strlen($pass) < 8)               $errors[] = 'Mot de passe : minimum 8 caractères.';
        if ($_POST['password_confirm'] !== $pass) $errors[] = 'Les mots de passe ne correspondent pas.';
        if (empty($_POST['cgv']))            $errors[] = "Veuillez accepter les conditions d'utilisation.";

        if (!$errors) {
            $refParId = $referralUser ? $referralUser['id'] : null;
            $result = auth_register([
                'nom'          => $nom,
                'prenom'       => $prenom,
                'email'        => $email,
                'password'     => $pass,
                'classe'       => $classe,
                'province_id'  => $provId ?: null,
                'referral_par' => $refParId,
            ]);
            if ($result['ok']) {
                header('Location: /reussiteplus/dashboard.php?welcome=1');
                exit;
            } else {
                $errors[] = $result['msg'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inscription gratuite — RÉUSSITE+</title>
<link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,700;0,9..144,800;0,9..144,900;1,9..144,400;1,9..144,700&family=Manrope:wght@300;400;500;600;700;800&family=Newsreader:ital,opsz,wght@1,6..72,400;1,6..72,500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;-webkit-font-smoothing:antialiased;}
body{font-family:'Manrope',system-ui,sans-serif;display:flex;min-height:100vh;background:#0C0F0D;}
a{text-decoration:none;color:inherit;}

.page{display:flex;min-height:100vh;width:100%;}

/* ── PANNEAU GAUCHE ─────────────────────────────────────────── */
.left{
  width:420px;flex-shrink:0;min-height:100vh;
  background:#0C0F0D;position:relative;
  display:flex;flex-direction:column;justify-content:space-between;
  padding:48px 48px 40px;overflow:hidden;
}
.left-photo{
  position:absolute;inset:0;z-index:0;
  background:url('https://images.unsplash.com/photo-1531123897727-8f129e1688ce?w=800&auto=format&q=80&fit=crop&crop=center') center/cover no-repeat;
  opacity:.26;
}
.left-gradient{
  position:absolute;inset:0;z-index:1;
  background:
    radial-gradient(ellipse 100% 55% at 0% 10%,rgba(0,122,94,.48) 0%,transparent 60%),
    radial-gradient(ellipse 70% 50% at 100% 90%,rgba(201,151,42,.18) 0%,transparent 60%),
    linear-gradient(180deg,rgba(12,15,13,.55) 0%,rgba(12,15,13,.88) 100%);
  pointer-events:none;
}
.left-inner{position:relative;z-index:2;display:flex;flex-direction:column;height:100%;}

.left-logo{display:flex;align-items:center;gap:10px;margin-bottom:0;}
.left-logo img{display:block;border-radius:9px;}
.left-logo-text{font-family:'Fraunces',Georgia,serif;font-size:17px;font-weight:800;color:#fff;letter-spacing:-.3px;}
.left-logo-text em{color:#C9972A;font-style:normal;}

.left-mid{flex:1;display:flex;flex-direction:column;justify-content:center;padding:32px 0;}
.left-kicker{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(0,169,127,.85);margin-bottom:16px;}
.left-headline{
  font-family:'Fraunces',Georgia,serif;
  font-size:clamp(32px,3.5vw,52px);
  font-weight:900;color:#fff;
  line-height:1.05;letter-spacing:-.03em;margin-bottom:20px;
}
.left-headline em{color:#C9972A;font-style:italic;}
.left-sub{font-size:14px;color:rgba(255,255,255,.4);line-height:1.75;max-width:290px;margin-bottom:32px;}

/* Benefits list */
.left-benefits{display:flex;flex-direction:column;gap:14px;}
.left-benefit{display:flex;align-items:center;gap:12px;font-size:13px;color:rgba(255,255,255,.65);}
.left-benefit-check{
  width:22px;height:22px;border-radius:50%;flex-shrink:0;
  background:rgba(0,122,94,.2);border:1px solid rgba(0,169,127,.3);
  display:flex;align-items:center;justify-content:center;
}
.left-benefit-check svg{width:11px;height:11px;stroke:#00A97F;stroke-width:2.5;}

/* Quote */
.left-quote{margin-top:28px;padding-top:24px;border-top:1px solid rgba(255,255,255,.07);}
.left-quote-text{
  font-family:'Newsreader',Georgia,serif;font-style:italic;
  font-size:14px;color:rgba(255,255,255,.55);line-height:1.7;margin-bottom:10px;
}
.left-quote-byline{font-size:11px;font-weight:600;color:rgba(255,255,255,.3);letter-spacing:.5px;}

/* Stats */
.left-stats{display:flex;gap:0;border-top:1px solid rgba(255,255,255,.07);padding-top:20px;}
.left-stat{flex:1;padding:0 16px;}
.left-stat:first-child{padding-left:0;}
.left-stat-num{font-family:'Fraunces',Georgia,serif;font-size:20px;font-weight:800;color:#fff;margin-bottom:3px;}
.left-stat-label{font-size:10px;color:rgba(255,255,255,.3);font-weight:500;}
.left-stat-sep{width:1px;background:rgba(255,255,255,.07);align-self:stretch;}

/* ── PANNEAU DROIT ─────────────────────────────────────────── */
.right{
  flex:1;background:#FAFAF8;
  display:flex;flex-direction:column;justify-content:center;
  padding:48px 60px;overflow-y:auto;min-height:100vh;
}

.right-logo-mobile{display:none;align-items:center;gap:10px;margin-bottom:32px;}
.right-logo-mobile img{border-radius:8px;display:block;}
.right-logo-mobile span{font-family:'Fraunces',Georgia,serif;font-size:17px;font-weight:800;color:#0C0F0D;}

.form-eyebrow{
  display:inline-flex;align-items:center;gap:6px;font-size:11px;
  font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#007A5E;
  margin-bottom:12px;
}
.form-eyebrow::before{content:'';display:block;width:16px;height:1.5px;background:#007A5E;border-radius:2px;}
.form-title{
  font-family:'Fraunces',Georgia,serif;
  font-size:clamp(26px,2.8vw,36px);font-weight:800;
  color:#0D1117;line-height:1.1;letter-spacing:-.02em;margin-bottom:6px;
}
.form-desc{font-size:13.5px;color:#9CA3AF;line-height:1.65;margin-bottom:28px;}

/* Referral banner */
.referral-banner{
  display:flex;align-items:center;gap:10px;
  background:#FFF8EC;border:1px solid rgba(201,151,42,.25);
  border-radius:10px;padding:12px 16px;margin-bottom:20px;
  font-size:13px;color:#6B7280;
}
.referral-banner svg{width:16px;height:16px;stroke:#C9972A;flex-shrink:0;}
.referral-banner strong{color:#0D1117;}

/* Free badge */
.free-badge{
  display:inline-flex;align-items:center;gap:7px;
  background:#E8F5F1;border:1px solid rgba(0,122,94,.2);
  border-radius:8px;padding:8px 14px;
  font-size:12px;color:#005A45;font-weight:600;
  margin-bottom:22px;
}
.free-badge svg{width:14px;height:14px;stroke:#007A5E;}

/* Erreur */
.error-box{
  background:#FEF0EF;border:1px solid rgba(201,52,42,.2);
  border-left:3px solid #C9342A;border-radius:8px;
  padding:12px 16px;margin-bottom:22px;
  font-size:13px;color:#7F1D1D;display:flex;align-items:flex-start;gap:10px;line-height:1.5;
}
.error-box svg{width:16px;height:16px;stroke:#C9342A;flex-shrink:0;margin-top:1px;}
.error-box ul{margin:4px 0 0 16px;}

/* Grille de champs */
.fields-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 24px;}

/* Champs underline */
.field{margin-bottom:24px;}
.field-label{
  display:block;font-size:10px;font-weight:700;
  letter-spacing:1.2px;text-transform:uppercase;
  color:#B0B8C4;margin-bottom:8px;
}
.field-input{
  width:100%;background:transparent;border:none;
  border-bottom:1.5px solid #E2E8F0;
  padding:9px 0 11px;
  font-family:'Manrope',system-ui,sans-serif;
  font-size:15px;font-weight:500;color:#0D1117;
  outline:none;transition:border-color 200ms;
  -webkit-appearance:none;
}
.field-input:focus{border-bottom-color:#007A5E;}
.field-input::placeholder{color:#C8D0DA;font-weight:400;}
.field-input.has-error{border-bottom-color:#C9342A;}
select.field-input{cursor:pointer;background:transparent;}
select.field-input option{background:#fff;color:#0D1117;}

.field-input-wrap{position:relative;}
.field-input-wrap .field-input{padding-right:38px;}
.eye-btn{
  position:absolute;right:0;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;padding:6px;
  color:#C0C7D0;transition:color 200ms;
}
.eye-btn:hover{color:#6B7280;}
.eye-btn svg{width:17px;height:17px;stroke:currentColor;display:block;}

/* Barre de force */
.strength-track{height:3px;background:#E2E8F0;border-radius:10px;margin-top:8px;overflow:hidden;}
.strength-fill{height:100%;width:0;border-radius:10px;transition:width .3s ease,background .3s ease;}
.strength-text{font-size:11px;color:#B0B8C4;margin-top:5px;height:14px;}

/* Checkbox CGV */
.checkbox-field{display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:24px;}
.checkbox-field input{width:16px;height:16px;flex-shrink:0;margin-top:2px;accent-color:#007A5E;cursor:pointer;}
.checkbox-field span{font-size:13px;color:#6B7280;line-height:1.6;}
.checkbox-field a{color:#007A5E;font-weight:600;}
.checkbox-field a:hover{text-decoration:underline;}

/* Bouton */
.btn-submit{
  width:100%;padding:15px 24px;
  background:#0D1117;color:#fff;
  border:none;border-radius:10px;
  font-family:'Manrope',system-ui,sans-serif;
  font-size:14.5px;font-weight:700;letter-spacing:.2px;
  cursor:pointer;transition:all 200ms;
  display:flex;align-items:center;justify-content:center;gap:10px;
}
.btn-submit:hover:not(:disabled){background:#007A5E;transform:translateY(-1px);box-shadow:0 8px 24px rgba(0,122,94,.25);}
.btn-submit:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}
.btn-submit svg{width:15px;height:15px;stroke:currentColor;}

.form-foot{margin-top:22px;text-align:center;}
.form-foot p{font-size:13px;color:#9CA3AF;margin-bottom:10px;}
.form-foot a{color:#007A5E;font-weight:600;}
.form-foot a:hover{text-decoration:underline;}
.back-link{display:inline-flex;align-items:center;gap:5px;font-size:12px;color:#C0C7D0;font-weight:500;transition:color 200ms;}
.back-link:hover{color:#6B7280;}
.back-link svg{width:12px;height:12px;stroke:currentColor;}

@media(max-width:960px){
  .left{display:none;}
  .right{flex:1;padding:48px 28px;min-height:100vh;justify-content:flex-start;padding-top:56px;}
  .right-logo-mobile{display:flex;}
  .fields-grid{grid-template-columns:1fr;}
}
@media(max-width:480px){
  .right{padding:36px 18px;padding-top:44px;}
  .form-title{font-size:24px;}
}
</style>
</head>
<body>
<div class="page">

  <!-- ── PANNEAU GAUCHE ── -->
  <div class="left">
    <div class="left-photo"></div>
    <div class="left-gradient"></div>
    <div class="left-inner">

      <div class="left-logo">
        <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="30" height="30">
        <span class="left-logo-text">RÉUSSITE<em>+</em></span>
      </div>

      <div class="left-mid">
        <div class="left-kicker">Inscription gratuite</div>
        <h1 class="left-headline">Commence.<br>Le meilleur<br>moment, c'est<br><em>maintenant.</em></h1>
        <p class="left-sub">14 238 élèves de toute la RDC ont commencé ici. Gratuit, sans carte bancaire, sans engagement.</p>

        <div class="left-benefits">
          <?php
          $benefits = [
            'Archives ENAFEP, TENASOSP & Exam d\'État depuis 2005',
            '15 000+ QCM avec corrections détaillées',
            'Suivi de progression par matière',
            'Fonctionne sans connexion sur Android',
          ];
          foreach ($benefits as $b): ?>
          <div class="left-benefit">
            <div class="left-benefit-check">
              <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span><?= $b ?></span>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="left-quote">
          <div class="left-quote-text">"TENASOSP réussi du 1er coup. Sans répétiteur. Je ne comprends pas pourquoi tout le monde ne l'utilise pas encore."</div>
          <div class="left-quote-byline">Bénédicte N. — Lubumbashi · TENASOSP 2025</div>
        </div>
      </div>

      <div class="left-stats">
        <div class="left-stat">
          <div class="left-stat-num">14 238</div>
          <div class="left-stat-label">Élèves inscrits</div>
        </div>
        <div class="left-stat-sep"></div>
        <div class="left-stat">
          <div class="left-stat-num">100%</div>
          <div class="left-stat-label">Gratuit pour commencer</div>
        </div>
        <div class="left-stat-sep"></div>
        <div class="left-stat">
          <div class="left-stat-num">7j</div>
          <div class="left-stat-label">Garantie remboursement</div>
        </div>
      </div>

    </div>
  </div>

  <!-- ── PANNEAU DROIT ── -->
  <div class="right">

    <div class="right-logo-mobile">
      <img src="/reussiteplus/assets/img/logo-icon.svg" alt="RÉUSSITE+" width="28" height="28">
      <span>RÉUSSITE<em style="color:#C9972A;font-style:normal;">+</em></span>
    </div>

    <div class="form-eyebrow">Inscription gratuite</div>
    <h1 class="form-title">Créer mon compte</h1>
    <p class="form-desc">Rejoins 14 238 élèves qui se préparent sérieusement.<br>Aucune carte bancaire requise pour commencer.</p>

    <?php if ($referralUser): ?>
    <div class="referral-banner">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Tu es invité(e) par <strong><?= e($referralUser['prenom'] . ' ' . $referralUser['nom']) ?></strong>. Crée ton compte et démarre avec un bonus !</span>
    </div>
    <?php endif; ?>

    <div class="free-badge">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      Gratuit — 5 examens par mois, sans carte bancaire
    </div>

    <?php if ($errors): ?>
    <div class="error-box">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <div>
        <?php if (count($errors) === 1): ?>
          <?= e($errors[0]) ?>
        <?php else: ?>
          Veuillez corriger les erreurs suivantes :
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="" id="regForm">
      <?= csrf_field() ?>
      <?php if ($refCode): ?><input type="hidden" name="ref" value="<?= e($refCode) ?>"><?php endif; ?>

      <div class="fields-grid">
        <div class="field">
          <label class="field-label" for="prenom">Prénom *</label>
          <input class="field-input" type="text" id="prenom" name="prenom"
                 placeholder="Jean" value="<?= e($_POST['prenom'] ?? '') ?>"
                 required autocomplete="given-name">
        </div>
        <div class="field">
          <label class="field-label" for="nom">Nom *</label>
          <input class="field-input" type="text" id="nom" name="nom"
                 placeholder="Mukeba" value="<?= e($_POST['nom'] ?? '') ?>"
                 required autocomplete="family-name">
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="email">Adresse e-mail *</label>
        <input class="field-input" type="email" id="email" name="email"
               placeholder="vous@exemple.com" value="<?= e($_POST['email'] ?? '') ?>"
               required autocomplete="email">
      </div>

      <div class="fields-grid">
        <div class="field">
          <label class="field-label" for="province_id">Province</label>
          <select class="field-input" id="province_id" name="province_id">
            <option value="">Sélectionner…</option>
            <?php foreach ($provinces as $p): ?>
            <option value="<?= e($p['id']) ?>" <?= (($_POST['province_id'] ?? '') == $p['id']) ? 'selected' : '' ?>><?= e($p['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="field-label" for="classe">Classe</label>
          <select class="field-input" id="classe" name="classe">
            <option value="">Sélectionner…</option>
            <optgroup label="Primaire">
              <option value="5eme primaire"  <?= (($_POST['classe'] ?? '') === '5eme primaire')  ? 'selected' : '' ?>>5ème primaire</option>
              <option value="6eme primaire"  <?= (($_POST['classe'] ?? '') === '6eme primaire')  ? 'selected' : '' ?>>6ème primaire</option>
            </optgroup>
            <optgroup label="Secondaire">
              <option value="1ere secondaire" <?= (($_POST['classe'] ?? '') === '1ere secondaire') ? 'selected' : '' ?>>1ère secondaire</option>
              <option value="2eme secondaire" <?= (($_POST['classe'] ?? '') === '2eme secondaire') ? 'selected' : '' ?>>2ème secondaire</option>
              <option value="3eme secondaire" <?= (($_POST['classe'] ?? '') === '3eme secondaire') ? 'selected' : '' ?>>3ème secondaire</option>
              <option value="4eme secondaire" <?= (($_POST['classe'] ?? '') === '4eme secondaire') ? 'selected' : '' ?>>4ème secondaire</option>
              <option value="5eme secondaire" <?= (($_POST['classe'] ?? '') === '5eme secondaire') ? 'selected' : '' ?>>5ème secondaire</option>
              <option value="6eme secondaire" <?= (($_POST['classe'] ?? '') === '6eme secondaire') ? 'selected' : '' ?>>6ème secondaire</option>
            </optgroup>
          </select>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="password">Mot de passe * <span style="font-size:10px;font-weight:400;color:#C0C7D0;text-transform:none;letter-spacing:0">(min. 8 caractères)</span></label>
        <div class="field-input-wrap">
          <input class="field-input" type="password" id="password" name="password"
                 placeholder="Choisissez un mot de passe fort"
                 required autocomplete="new-password"
                 oninput="checkStrength(this.value)">
          <button type="button" class="eye-btn" onclick="togglePwd('password','eye1')" aria-label="Afficher">
            <svg id="eye1" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="strength-track"><div class="strength-fill" id="sBar"></div></div>
        <div class="strength-text" id="sLabel"></div>
      </div>

      <div class="field">
        <label class="field-label" for="password_confirm">Confirmer le mot de passe *</label>
        <div class="field-input-wrap">
          <input class="field-input" type="password" id="password_confirm" name="password_confirm"
                 placeholder="Répétez votre mot de passe"
                 required autocomplete="new-password">
          <button type="button" class="eye-btn" onclick="togglePwd('password_confirm','eye2')" aria-label="Afficher">
            <svg id="eye2" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <div class="checkbox-field">
        <input type="checkbox" name="cgv" id="cgv" <?= isset($_POST['cgv']) ? 'checked' : '' ?> required>
        <label for="cgv" style="font-size:13px;color:#6B7280;line-height:1.6;cursor:pointer">
          J'accepte les <a href="/reussiteplus/cgv.php" target="_blank">conditions d'utilisation</a>
          et la <a href="/reussiteplus/confidentialite.php" target="_blank">politique de confidentialité</a>.
        </label>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">
        <span id="btnLabel">Créer mon compte gratuitement</span>
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </button>
    </form>

    <div class="form-foot">
      <p>Déjà un compte ? <a href="/reussiteplus/connexion.php">Se connecter</a></p>
      <a href="/reussiteplus/index.php" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Retour à l'accueil
      </a>
    </div>

  </div>
</div>

<script>
function togglePwd(id, eyeId) {
  const el = document.getElementById(id);
  const isPass = el.type === 'password';
  el.type = isPass ? 'text' : 'password';
  const eye = document.getElementById(eyeId);
  eye.innerHTML = isPass
    ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
    : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}

function checkStrength(v) {
  let s = 0;
  if (v.length >= 8)           s++;
  if (/[A-Z]/.test(v))         s++;
  if (/[0-9]/.test(v))         s++;
  if (/[^A-Za-z0-9]/.test(v))  s++;
  const bar = document.getElementById('sBar');
  const lbl = document.getElementById('sLabel');
  if (!v) { bar.style.width='0'; lbl.textContent=''; return; }
  const colors = ['#C9342A','#C9972A','#1E5FAD','#007A5E'];
  const labels = ['Très faible','Moyen','Fort','Très fort'];
  const i = Math.max(0, s - 1);
  bar.style.background = colors[i];
  bar.style.width = (s * 25) + '%';
  lbl.style.color = colors[i];
  lbl.textContent = labels[i];
}

document.getElementById('regForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  const lbl = document.getElementById('btnLabel');
  btn.disabled = true;
  lbl.textContent = 'Création en cours…';
});
</script>
</body>
</html>
