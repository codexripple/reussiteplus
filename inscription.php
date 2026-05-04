<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged()) { header('Location: /reussiteplus/dashboard.php'); exit; }

$errors = [];
$provinces = [];
try { $provinces = dbAll("SELECT id, nom FROM provinces ORDER BY nom"); } catch (Exception $e) {}

// Récupérer code referral depuis URL
$refCode = trim($_GET['ref'] ?? '');
$referralUser = null;
if ($refCode) {
    try {
        $referralUser = dbRow("SELECT id, prenom, nom FROM utilisateurs WHERE referral_code = ?", [$refCode]);
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $pass   = $_POST['password'] ?? '';
        $classe = trim($_POST['classe'] ?? '');
        $provId = $_POST['province_id'] ?? null;

        if (empty($nom) || empty($prenom)) $errors[] = 'Le nom et prénom sont requis.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        if (strlen($pass) < 8) $errors[] = 'Mot de passe : minimum 8 caractères.';
        if ($_POST['password_confirm'] !== $pass) $errors[] = 'Les mots de passe ne correspondent pas.';
        if (empty($_POST['cgv'])) $errors[] = 'Veuillez accepter les conditions d\'utilisation.';

        if (!$errors) {
            $refParId = $referralUser ? $referralUser['id'] : null;
            $result = auth_register([
                'nom'         => $nom,
                'prenom'      => $prenom,
                'email'       => $email,
                'password'    => $pass,
                'classe'      => $classe,
                'province_id' => $provId ?: null,
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
<link rel="stylesheet" href="/reussiteplus/assets/css/fonts.css">
<style>
:root{--primary:#007A5E;--primary-dark:#005A45;--primary-light:#00A97F;--primary-subtle:#E8F5F1;--gold:#C9972A;--rouge:#C9342A;--noir:#0D1117;--gris-900:#1C2433;--gris-700:#4A5568;--gris-600:#6B7280;--gris-400:#A0AEC0;--gris-200:#E2E8F0;--gris-100:#F1F5F9;--blanc:#FFFFFF;--font-display:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;--radius:10px;--radius-lg:16px;--shadow-lg:0 8px 32px rgba(0,0,0,.12);--transition:200ms cubic-bezier(0.4,0,0.2,1);}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:var(--font-body);background:var(--gris-100);min-height:100vh;display:flex;padding:30px 20px;}
.auth-wrap{width:100%;max-width:520px;margin:0 auto;}
.auth-logo{text-align:center;margin-bottom:28px;}
.auth-logo-icon{width:52px;height:52px;background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 10px;box-shadow:0 0 20px rgba(0,122,94,.2);}
.auth-logo-text{font-family:var(--font-display);font-size:24px;font-weight:800;color:var(--gris-900);}
.auth-logo-text span{color:var(--gold);}
.auth-card{background:var(--blanc);border-radius:var(--radius-lg);padding:36px;box-shadow:var(--shadow-lg);border:1px solid var(--gris-200);}
.auth-title{font-family:var(--font-display);font-size:22px;font-weight:800;color:var(--gris-900);margin-bottom:4px;}
.auth-desc{font-size:14px;color:var(--gris-600);margin-bottom:24px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.form-group{margin-bottom:16px;}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--gris-700);margin-bottom:6px;}
.form-control{width:100%;padding:11px 14px;border:1px solid var(--gris-200);border-radius:var(--radius);font-family:var(--font-body);font-size:14px;color:var(--gris-900);background:var(--blanc);transition:var(--transition);outline:none;}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,122,94,.1);}
.form-control::placeholder{color:var(--gris-400);}
select.form-control{cursor:pointer;}
.password-wrap{position:relative;}
.password-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--gris-400);}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:12px 20px;border-radius:var(--radius);font-size:14px;font-weight:600;cursor:pointer;border:none;transition:var(--transition);font-family:var(--font-body);width:100%;}
.btn-primary{background:var(--primary);color:var(--blanc);}
.btn-primary:hover{background:var(--primary-dark);}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;}
.error-box{background:#FEF0EF;border-left:4px solid var(--rouge);color:var(--rouge);padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;}
.error-box ul{margin:4px 0 0 16px;}
.referral-banner{background:var(--primary-subtle);border:1px solid rgba(0,122,94,.25);border-radius:10px;padding:12px 14px;font-size:13px;color:var(--primary-dark);margin-bottom:20px;display:flex;gap:10px;align-items:center;}
.free-badge{background:var(--primary-subtle);border:1px solid rgba(0,122,94,.3);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--primary-dark);margin-bottom:20px;text-align:center;}
.checkbox-wrap{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:var(--gris-700);}
.checkbox-wrap input{margin-top:2px;accent-color:var(--primary);width:16px;height:16px;flex-shrink:0;}
.auth-footer{text-align:center;margin-top:20px;font-size:13px;color:var(--gris-600);}
.auth-footer a{color:var(--primary);font-weight:600;}
@media(max-width:480px){.form-row{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-logo">
    <div class="auth-logo-icon">🎓</div>
    <div class="auth-logo-text">RÉUSSITE<span>+</span></div>
    <div style="font-size:13px;color:var(--gris-600);margin-top:4px">Examens officiels RDC — ENAFEP, TENASOSP, État</div>
  </div>

  <div class="auth-card">
    <div class="auth-title">Créer mon compte</div>
    <div class="auth-desc">Plus de 12 000 élèves préparent leurs examens ici. Rejoins-les, c'est gratuit.</div>

    <?php if ($referralUser): ?>
    <div class="referral-banner">
      🎁 <strong><?= e($referralUser['prenom']) ?></strong> vous a invité ! Vous recevrez 1 mois de Basique offert.
    </div>
    <?php endif; ?>

    <div class="free-badge">
      ✅ <strong>Compte gratuit</strong> — 5 examens par mois, sans carte bancaire
    </div>

    <?php if ($errors): ?>
    <div class="error-box">
      <?php if (count($errors) === 1): ?>
        ⚠️ <?= e($errors[0]) ?>
      <?php else: ?>
        ⚠️ Veuillez corriger les erreurs suivantes :
        <ul><?php foreach ($errors as $e2): ?><li><?= e($e2) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>
      <?php if ($refCode): ?><input type="hidden" name="ref" value="<?= e($refCode) ?>"><?php endif; ?>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="prenom">Prénom *</label>
          <input class="form-control" type="text" id="prenom" name="prenom"
                 placeholder="Jean" value="<?= e($_POST['prenom'] ?? '') ?>" required autocomplete="given-name">
        </div>
        <div class="form-group">
          <label class="form-label" for="nom">Nom *</label>
          <input class="form-control" type="text" id="nom" name="nom"
                 placeholder="Mukeba" value="<?= e($_POST['nom'] ?? '') ?>" required autocomplete="family-name">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Adresse email *</label>
        <input class="form-control" type="email" id="email" name="email"
               placeholder="vous@exemple.com" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="province_id">Province</label>
          <select class="form-control" id="province_id" name="province_id">
            <option value="">— Sélectionner —</option>
            <?php foreach ($provinces as $p): ?>
            <option value="<?= e($p['id']) ?>" <?= ($_POST['province_id'] ?? '') === $p['id'] ? 'selected' : '' ?>>
              <?= e($p['nom']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="classe">Classe</label>
          <select class="form-control" id="classe" name="classe">
            <option value="">— Sélectionner —</option>
            <optgroup label="Primaire">
              <option value="5ème primaire">5ème primaire</option>
              <option value="6ème primaire">6ème primaire</option>
            </optgroup>
            <optgroup label="Secondaire">
              <option value="1ère secondaire">1ère secondaire</option>
              <option value="2ème secondaire">2ème secondaire</option>
              <option value="3ème secondaire">3ème secondaire</option>
              <option value="4ème secondaire">4ème secondaire</option>
              <option value="5ème secondaire">5ème secondaire</option>
              <option value="6ème secondaire">6ème secondaire</option>
            </optgroup>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Mot de passe * <span style="font-size:11px;color:var(--gris-400)">(min. 8 caractères)</span></label>
        <div class="password-wrap">
          <input class="form-control" type="password" id="password" name="password"
                 placeholder="Choisissez un mot de passe fort" required autocomplete="new-password"
                 oninput="checkStrength(this.value)">
          <button type="button" class="password-toggle" onclick="togglePassword('password')">👁️</button>
        </div>
        <div id="strength-bar" style="height:3px;border-radius:3px;margin-top:6px;background:var(--gris-200);transition:all .3s"></div>
        <div id="strength-text" style="font-size:11px;color:var(--gris-400);margin-top:3px"></div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password_confirm">Confirmer le mot de passe *</label>
        <div class="password-wrap">
          <input class="form-control" type="password" id="password_confirm" name="password_confirm"
                 placeholder="Répétez votre mot de passe" required autocomplete="new-password">
          <button type="button" class="password-toggle" onclick="togglePassword('password_confirm')">👁️</button>
        </div>
      </div>

      <div class="form-group">
        <label class="checkbox-wrap">
          <input type="checkbox" name="cgv" <?= isset($_POST['cgv']) ? 'checked' : '' ?> required>
          <span>J'accepte les <a href="/reussiteplus/cgv.php" target="_blank" style="color:var(--primary)">conditions d'utilisation</a> et la <a href="/reussiteplus/confidentialite.php" target="_blank" style="color:var(--primary)">politique de confidentialité</a>.</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary" id="submitBtn">
        Créer mon compte gratuitement →
      </button>
    </form>
  </div>

  <div class="auth-footer">
    Déjà un compte ? <a href="/reussiteplus/connexion.php">Se connecter</a>
  </div>
  <div class="auth-footer" style="margin-top:8px">
    <a href="/reussiteplus/index.php" style="color:var(--gris-500)">← Retour à l'accueil</a>
  </div>
</div>

<script>
function togglePassword(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}
function checkStrength(val) {
  let s = 0;
  if (val.length >= 8) s++;
  if (/[A-Z]/.test(val)) s++;
  if (/[0-9]/.test(val)) s++;
  if (/[^A-Za-z0-9]/.test(val)) s++;
  const bar = document.getElementById('strength-bar');
  const txt = document.getElementById('strength-text');
  const colors = ['#C9342A','#C9972A','#1E5FAD','#007A5E'];
  const labels = ['Très faible','Moyen','Fort','Très fort'];
  if (val.length === 0) { bar.style.width='0'; txt.textContent=''; return; }
  bar.style.background = colors[s-1] || '#C9342A';
  bar.style.width = (s * 25) + '%';
  txt.style.color = colors[s-1] || '#C9342A';
  txt.textContent = labels[s-1] || 'Très faible';
}
document.querySelector('form').addEventListener('submit', function() {
  document.getElementById('submitBtn').disabled = true;
  document.getElementById('submitBtn').textContent = 'Création en cours...';
});
</script>
</body>
</html>
