<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Mon Profil';
$pageActive = 'profil';
$user = require_login();

$errors = [];
$success = '';

// ── Upload photo ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $file = $_FILES['photo'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_mime = ['image/jpeg','image/png','image/webp','image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed_mime)) {
            $errors[] = 'Format non autorisé. Utilisez JPG, PNG ou WebP.';
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $errors[] = 'La photo ne doit pas dépasser 3 Mo.';
        } else {
            $ext     = match($mime) { 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif', default=>'jpg' };
            $newName = 'photo_' . bin2hex(random_bytes(10)) . '.' . $ext;
            $destDir = __DIR__ . '/uploads/photos/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $destDir . $newName)) {
                // Supprimer ancienne photo si locale
                if ($user['photo_url'] && str_starts_with($user['photo_url'], APP_URL . '/uploads/')) {
                    $old = __DIR__ . str_replace(APP_URL, '', $user['photo_url']);
                    if (file_exists($old)) unlink($old);
                }
                $photoUrl = APP_URL . '/uploads/photos/' . $newName;
                dbQuery("UPDATE utilisateurs SET photo_url=?, updated_at=NOW() WHERE id=?", [$photoUrl, $user['id']]);
                $success = 'Photo de profil mise à jour !';
                $user = require_login(); // Refresh session
            } else {
                $errors[] = 'Erreur lors de l\'upload, réessayez.';
            }
        }
    }
}

// ── Mise à jour profil ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profil') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $nom    = trim($_POST['nom']    ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $ville  = trim($_POST['ville']  ?? '');
    $ecole  = trim($_POST['ecole']  ?? '');
    $classe = trim($_POST['classe'] ?? '');
    if (!$nom || !$prenom) { $errors[] = 'Nom et prénom sont requis.'; }
    else {
        dbQuery("UPDATE utilisateurs SET nom=?, prenom=?, ville=?, ecole=?, classe=?, updated_at=NOW() WHERE id=?",
            [$nom, $prenom, $ville ?: null, $ecole ?: null, $classe ?: null, $user['id']]);
        redirect('/reussiteplus/profil.php', 'success', 'Profil mis à jour avec succès !');
    }
}

// ── Changement mot de passe ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $cnf = $_POST['confirm_password'] ?? '';
    $dbUser = dbRow("SELECT password_hash FROM utilisateurs WHERE id=?", [$user['id']]);
    if (!password_verify($old, $dbUser['password_hash'])) {
        $errors[] = 'Mot de passe actuel incorrect.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    } elseif ($new !== $cnf) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    } else {
        dbQuery("UPDATE utilisateurs SET password_hash=?, updated_at=NOW() WHERE id=?",
            [password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]);
        redirect('/reussiteplus/profil.php', 'success', 'Mot de passe modifié avec succès !');
    }
}

// ── Stats utilisateur ─────────────────────────────────────
$stats = [
    'examens'    => (int)dbVal("SELECT COUNT(*) FROM exam_results WHERE user_id=? OR utilisateur_id=?", [$user['id'], $user['id']]) ?: (int)dbVal("SELECT total_examens FROM utilisateurs WHERE id=?", [$user['id']]),
    'score_moy'  => (float)($user['score_moyen'] ?? 0),
    'streak'     => (int)($user['streak_jours'] ?? 0),
    'classes'    => (int)dbVal("SELECT COUNT(*) FROM classe_membres WHERE eleve_id=? AND statut='ACTIF'", [$user['id']]),
];
$activites = dbAll(
    "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5",
    [$user['id']]
) ?? [];

$initials = strtoupper(substr($user['prenom']??'?', 0, 1) . substr($user['nom']??'', 0, 1));
$planColor = match($user['plan'] ?? 'GRATUIT') {
    'PREMIUM' => '#C9972A', 'ECOLE' => '#007A5E', 'BASIQUE' => '#1E5FAD', default => '#6B7280'
};

include __DIR__ . '/includes/header_app.php';
?>

<style>
.profil-hero { background:linear-gradient(135deg,#0f172a,#1e3a5f 60%,#0f172a); border-radius:var(--radius-xl); padding:32px; margin-bottom:22px; position:relative; overflow:hidden; }
.profil-hero::before { content:''; position:absolute; top:-40px; right:-40px; width:200px; height:200px; background:rgba(255,255,255,.04); border-radius:50%; }
.avatar-ring { width:88px; height:88px; border-radius:50%; border:3px solid <?= $planColor ?>; padding:3px; background:var(--gris-100); position:relative; flex-shrink:0; }
.avatar-img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
.avatar-initials { width:100%; height:100%; border-radius:50%; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#1E5FAD,#2563EB); font-family:var(--font-display); font-size:28px; font-weight:900; color:#fff; }
.avatar-upload-btn { position:absolute; bottom:0; right:0; width:26px; height:26px; background:<?= $planColor ?>; border:2px solid #fff; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; }
.stat-pill { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12); border-radius:12px; padding:10px 18px; text-align:center; min-width:90px; }
.profil-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-lg); overflow:hidden; }
.profil-card-header { padding:16px 20px; border-bottom:1px solid var(--gris-100); background:var(--gris-50); display:flex; align-items:center; gap:10px; }
.profil-card-body { padding:20px; }
.profil-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media (max-width:600px) { .profil-grid { grid-template-columns:1fr; } }
.strength-bar { height:6px; border-radius:3px; background:var(--gris-200); overflow:hidden; margin-top:6px; }
.strength-fill { height:100%; border-radius:3px; transition:width .4s; }
</style>

<!-- Hero Profil -->
<div class="profil-hero">
  <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;position:relative">
    <!-- Avatar + upload -->
    <form method="POST" enctype="multipart/form-data" id="photo-form">
      <?= csrf_field() ?>
      <label style="cursor:pointer;display:block" title="Changer la photo">
        <div class="avatar-ring" style="border-color:<?= $planColor ?>">
          <?php if ($user['photo_url']): ?>
          <img src="<?= e($user['photo_url']) ?>" alt="Photo" class="avatar-img">
          <?php else: ?>
          <div class="avatar-initials"><?= e($initials) ?></div>
          <?php endif; ?>
          <div class="avatar-upload-btn">
            <i data-lucide="camera" style="width:13px;height:13px;stroke:#fff"></i>
          </div>
        </div>
        <input type="file" name="photo" accept="image/*" style="display:none" onchange="document.getElementById('photo-form').submit()">
      </label>
    </form>

    <!-- Infos -->
    <div style="flex:1;min-width:200px">
      <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#fff;margin-bottom:3px">
        <?= e(($user['prenom']??'').' '.strtoupper($user['nom']??'')) ?>
      </div>
      <div style="font-size:13px;color:rgba(255,255,255,.55);margin-bottom:8px"><?= e($user['email']??'') ?></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?= badge_plan($user['plan'] ?? 'GRATUIT') ?>
        <?php if ($user['role']!=='ELEVE'): ?>
        <span style="background:rgba(255,255,255,.12);color:rgba(255,255,255,.7);font-size:11px;font-weight:700;padding:2px 10px;border-radius:20px"><?= e($user['role']) ?></span>
        <?php endif; ?>
        <?php if ($user['ville']): ?>
        <span style="color:rgba(255,255,255,.4);font-size:11px;display:flex;align-items:center;gap:4px"><i data-lucide="map-pin" style="width:11px;height:11px"></i><?= e($user['ville']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats rapides -->
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <div class="stat-pill">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= $stats['examens'] ?></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Examens</div>
      </div>
      <div class="stat-pill">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= number_format($stats['score_moy'],0) ?>%</div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Moy. score</div>
      </div>
      <div class="stat-pill">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#f59e0b">🔥<?= $stats['streak'] ?></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Streak</div>
      </div>
      <div class="stat-pill">
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= $stats['classes'] ?></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Classes</div>
      </div>
    </div>
  </div>

  <?php if (!$user['photo_url']): ?>
  <div style="margin-top:14px;background:rgba(249,115,22,.15);border:1px solid rgba(249,115,22,.3);border-radius:10px;padding:10px 14px;font-size:12px;color:#FED7AA;display:flex;align-items:center;gap:8px">
    <i data-lucide="alert-circle" style="width:14px;height:14px;flex-shrink:0"></i>
    Ajoutez une photo de profil pour personnaliser votre compte.
  </div>
  <?php endif; ?>
</div>

<?php foreach ($errors as $e): ?>
<div class="flash-message flash-error" style="margin-bottom:12px"><?= e($e) ?></div>
<?php endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">

  <!-- Colonne 1 : infos perso + mdp -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Informations personnelles -->
    <div class="profil-card">
      <div class="profil-card-header">
        <div style="width:32px;height:32px;background:#DBEAFE;border-radius:8px;display:flex;align-items:center;justify-content:center">
          <i data-lucide="user" style="width:16px;height:16px;stroke:#1E5FAD"></i>
        </div>
        <span style="font-family:var(--font-display);font-size:14px;font-weight:800">Informations personnelles</span>
      </div>
      <div class="profil-card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profil">
          <div class="profil-grid">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Prénom *</label>
              <input type="text" name="prenom" class="form-control" value="<?= e($user['prenom']??'') ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Nom *</label>
              <input type="text" name="nom" class="form-control" value="<?= e($user['nom']??'') ?>" required>
            </div>
          </div>
          <div class="form-group" style="margin-top:12px">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="<?= e($user['email']??'') ?>" disabled style="opacity:.5;cursor:not-allowed">
            <div style="font-size:11px;color:var(--gris-400);margin-top:3px">L'email ne peut pas être modifié.</div>
          </div>
          <div class="profil-grid" style="margin-top:12px">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Ville</label>
              <input type="text" name="ville" class="form-control" value="<?= e($user['ville']??'') ?>" placeholder="Ex: Kinshasa">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Classe</label>
              <input type="text" name="classe" class="form-control" value="<?= e($user['classe']??'') ?>" placeholder="Ex: 6ème primaire">
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;margin-bottom:16px">
            <label class="form-label">Établissement scolaire</label>
            <input type="text" name="ecole" class="form-control" value="<?= e($user['ecole']??'') ?>" placeholder="Ex: École Primaire de Gombe">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;background:#1E5FAD;border-color:#1E5FAD">
            <i data-lucide="save" style="width:13px;height:13px;vertical-align:-2px;stroke:#fff"></i> Enregistrer les modifications
          </button>
        </form>
      </div>
    </div>

    <!-- Sécurité / mot de passe -->
    <div class="profil-card">
      <div class="profil-card-header">
        <div style="width:32px;height:32px;background:#FEE2E2;border-radius:8px;display:flex;align-items:center;justify-content:center">
          <i data-lucide="lock" style="width:16px;height:16px;stroke:#DC2626"></i>
        </div>
        <span style="font-family:var(--font-display);font-size:14px;font-weight:800">Sécurité & Mot de passe</span>
      </div>
      <div class="profil-card-body">
        <form method="POST" id="pwd-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="form-group">
            <label class="form-label">Mot de passe actuel</label>
            <div style="position:relative">
              <input type="password" name="old_password" class="form-control" id="pwd-old" required autocomplete="current-password">
              <button type="button" onclick="togglePwd('pwd-old',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer">
                <i data-lucide="eye" style="width:15px;height:15px;stroke:var(--gris-400)"></i>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Nouveau mot de passe</label>
            <div style="position:relative">
              <input type="password" name="new_password" class="form-control" id="pwd-new" required minlength="8" autocomplete="new-password" oninput="checkStrength(this.value)">
              <button type="button" onclick="togglePwd('pwd-new',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer">
                <i data-lucide="eye" style="width:15px;height:15px;stroke:var(--gris-400)"></i>
              </button>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="strength-fill" style="width:0%;background:#DC2626"></div></div>
            <div id="strength-text" style="font-size:10px;color:var(--gris-400);margin-top:3px">Minimum 8 caractères</div>
          </div>
          <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Confirmer le nouveau mot de passe</label>
            <input type="password" name="confirm_password" class="form-control" id="pwd-cnf" required minlength="8" autocomplete="new-password">
          </div>
          <button type="submit" class="btn" style="width:100%;background:#DC2626;border-color:#DC2626;color:#fff;font-weight:700;padding:12px">
            <i data-lucide="shield" style="width:13px;height:13px;vertical-align:-2px;stroke:#fff"></i> Changer le mot de passe
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Colonne 2 : photo + abonnement + activité -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Photo de profil -->
    <div class="profil-card">
      <div class="profil-card-header">
        <div style="width:32px;height:32px;background:#D1FAE5;border-radius:8px;display:flex;align-items:center;justify-content:center">
          <i data-lucide="camera" style="width:16px;height:16px;stroke:#059669"></i>
        </div>
        <span style="font-family:var(--font-display);font-size:14px;font-weight:800">Photo de profil</span>
      </div>
      <div class="profil-card-body" style="text-align:center;padding:24px">
        <!-- Grande préview -->
        <div style="width:110px;height:110px;border-radius:50%;border:4px solid <?= $planColor ?>;margin:0 auto 16px;overflow:hidden;background:var(--gris-100)">
          <?php if ($user['photo_url']): ?>
          <img src="<?= e($user['photo_url']) ?>" style="width:100%;height:100%;object-fit:cover" alt="Photo">
          <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1E5FAD,#2563EB);font-family:var(--font-display);font-size:36px;font-weight:900;color:#fff"><?= e($initials) ?></div>
          <?php endif; ?>
        </div>
        <div style="font-size:12px;color:var(--gris-500);margin-bottom:16px">JPG, PNG ou WebP · Max 3 Mo<br>Recommandé : 400×400 px</div>
        <form method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <label class="btn btn-primary" style="background:#059669;border-color:#059669;cursor:pointer;display:inline-flex;align-items:center;gap:7px;width:100%;justify-content:center">
            <i data-lucide="upload" style="width:14px;height:14px;stroke:#fff"></i>
            <?= $user['photo_url'] ? 'Changer la photo' : 'Téléverser une photo' ?>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="this.form.submit()">
          </label>
        </form>
        <?php if ($user['photo_url']): ?>
        <div style="margin-top:8px;font-size:11px;color:#059669;display:flex;align-items:center;justify-content:center;gap:4px">
          <i data-lucide="check-circle" style="width:12px;height:12px"></i> Photo définie
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Abonnement actuel -->
    <div class="profil-card" style="border-color:<?= $planColor ?>40">
      <div class="profil-card-header" style="background:<?= $planColor ?>10">
        <div style="width:32px;height:32px;background:<?= $planColor ?>20;border-radius:8px;display:flex;align-items:center;justify-content:center">
          <i data-lucide="credit-card" style="width:16px;height:16px;stroke:<?= $planColor ?>"></i>
        </div>
        <span style="font-family:var(--font-display);font-size:14px;font-weight:800">Mon abonnement</span>
      </div>
      <div class="profil-card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
          <?= badge_plan($user['plan'] ?? 'GRATUIT') ?>
          <?php if ($user['plan_expire_at']): ?>
          <span style="font-size:11px;color:var(--gris-500)">Expire le <?= date('d/m/Y', strtotime($user['plan_expire_at'])) ?></span>
          <?php elseif (($user['plan']??'GRATUIT')!=='GRATUIT'): ?>
          <span style="font-size:11px;color:#059669">✓ Actif</span>
          <?php endif; ?>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px">
          <?php
          $features = match($user['plan']??'GRATUIT') {
              'PREMIUM' => ['Examens illimités','Toutes les matières','Mode offline','Stats avancées'],
              'ECOLE'   => ['Gestion de classe','Certificats','IA Pédagogique','50 élèves'],
              'BASIQUE' => ['20 examens/mois','Matières principales','Progression basique','Support email'],
              default   => ['5 examens/mois','Matières de base','Progression simple','—'],
          };
          foreach ($features as $f):
          ?>
          <div style="font-size:11px;color:var(--gris-600);display:flex;align-items:center;gap:5px">
            <span style="color:<?= $planColor ?>">✓</span> <?= e($f) ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (($user['plan']??'GRATUIT') !== 'PREMIUM'): ?>
        <a href="/reussiteplus/tarifs.php" class="btn" style="width:100%;background:<?= $planColor ?>;color:#fff;border:none;font-weight:700;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:7px;padding:10px">
          <i data-lucide="arrow-up-circle" style="width:14px;height:14px;stroke:#fff"></i> Mettre à niveau
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Activité récente -->
    <div class="profil-card">
      <div class="profil-card-header">
        <div style="width:32px;height:32px;background:#EDE9FE;border-radius:8px;display:flex;align-items:center;justify-content:center">
          <i data-lucide="activity" style="width:16px;height:16px;stroke:#7C3AED"></i>
        </div>
        <span style="font-family:var(--font-display);font-size:14px;font-weight:800">Activité récente</span>
      </div>
      <div class="profil-card-body" style="padding:0">
        <?php if ($activites): ?>
        <?php foreach ($activites as $act): ?>
        <div style="padding:12px 16px;border-bottom:1px solid var(--gris-100);display:flex;align-items:flex-start;gap:10px">
          <div style="width:30px;height:30px;border-radius:8px;background:var(--gris-100);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px">
            <?= match($act['type']??'') { 'SYSTEME'=>'🔔','EXAMEN'=>'📝','RESULTAT'=>'📊','BADGE'=>'🏅', default=>'💬' } ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:12px;font-weight:700;color:var(--gris-800);margin-bottom:2px"><?= e($act['titre']??'') ?></div>
            <div style="font-size:11px;color:var(--gris-400)"><?= temps_relatif($act['created_at']??'') ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;padding:30px;color:var(--gris-400);font-size:13px">Aucune activité récente</div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- Code référral -->
<div class="profil-card" style="margin-top:16px">
  <div class="profil-card-body">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:40px;height:40px;background:#FEF3C7;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px">🎁</div>
        <div>
          <div style="font-family:var(--font-display);font-size:14px;font-weight:800">Mon code de parrainage</div>
          <div style="font-size:12px;color:var(--gris-500)">Partagez ce code et gagnez des avantages</div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#B45309;letter-spacing:3px;background:#FEF3C7;padding:8px 18px;border-radius:10px"><?= e($user['referral_code']??'—') ?></div>
        <button onclick="navigator.clipboard.writeText('<?= e($user['referral_code']??'') ?>').then(()=>{this.textContent='✓ Copié!';setTimeout(()=>this.textContent='Copier',1500)})" class="btn btn-ghost btn-sm">Copier</button>
      </div>
    </div>
  </div>
</div>

<script>
function togglePwd(id, btn) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}
function checkStrength(val) {
  let score = 0;
  if (val.length >= 8) score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const fill = document.getElementById('strength-fill');
  const text = document.getElementById('strength-text');
  const colors = ['#DC2626','#F59E0B','#F59E0B','#059669','#059669'];
  const labels = ['Très faible','Faible','Moyen','Fort','Très fort'];
  fill.style.width = (score * 20) + '%';
  fill.style.background = colors[score-1] || '#DC2626';
  text.textContent = labels[score-1] || 'Trop court';
  text.style.color = colors[score-1] || '#DC2626';
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
