<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Rejoindre une classe';
$pageActive = '';
$user = require_login();

$code    = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$success = false;
$error   = null;
$classe  = null;

if ($code) {
    $classe = dbRow("SELECT c.*, u.nom as admin_nom, u.prenom as admin_prenom FROM classes_ecole c JOIN users u ON u.id=c.admin_id WHERE c.code_invitation=? AND c.actif=1", [strtoupper($code)]);
    if (!$classe) { $error = "Code d'invitation invalide ou classe introuvable."; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $classe && !$error) {
    if (!csrf_verify()) { http_response_code(403); exit; }
    // Vérifier que l'élève n'est pas déjà dans la classe
    $already = dbRow("SELECT id FROM classe_membres WHERE classe_id=? AND user_id=?", [$classe['id'], $user['id']]);
    if ($already) {
        $error = "Vous êtes déjà inscrit dans cette classe.";
    } else {
        dbRun("INSERT INTO classe_membres (classe_id, user_id) VALUES (?,?)", [$classe['id'], $user['id']]);
        $success = true;
    }
}

include __DIR__ . '/includes/header_app.php';
?>

<style>
.join-container { max-width: 520px; margin: 0 auto; padding: 20px 0; }
.join-card { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:24px; overflow:hidden; box-shadow:0 8px 40px rgba(0,0,0,.08); }
.join-hero { background:linear-gradient(135deg,#1e3a5f,#2563EB 50%,#4f46e5); padding:32px; text-align:center; }
.code-input { font-family:var(--font-display); font-size:28px; font-weight:900; letter-spacing:8px; text-align:center; text-transform:uppercase; }
.classe-preview { background:linear-gradient(135deg,#EDE9FE,#DBEAFE); border:1.5px solid #C4B5FD; border-radius:14px; padding:20px; margin:20px 0; text-align:center; }
</style>

<div class="join-container">
  <!-- Retour au dashboard -->
  <a href="/reussiteplus/dashboard.php" style="display:inline-flex;align-items:center;gap:6px;color:var(--gris-500);font-size:12px;font-weight:600;text-decoration:none;margin-bottom:16px;transition:.15s" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--gris-500)'">
    <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Retour au tableau de bord
  </a>

  <div class="join-card">
    <div class="join-hero">
      <div style="width:56px;height:56px;background:rgba(255,255,255,.15);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
        <i data-lucide="school" style="width:28px;height:28px;stroke:#fff"></i>
      </div>
      <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#fff;margin-bottom:6px">Rejoindre une classe</div>
      <div style="font-size:13px;color:rgba(255,255,255,.6)">Entrez le code d'invitation fourni par votre enseignant</div>
    </div>

    <div style="padding:28px">
      <?php if ($success): ?>
      <!-- Succès -->
      <div style="text-align:center;padding:20px 0">
        <div style="width:64px;height:64px;background:#D1FAE5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
          <i data-lucide="check-circle" style="width:30px;height:30px;stroke:#059669"></i>
        </div>
        <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:var(--gris-900);margin-bottom:6px">Bienvenue dans la classe !</div>
        <div style="font-size:14px;color:var(--gris-600);margin-bottom:6px">Vous avez rejoint <strong><?= e($classe['nom']) ?></strong></div>
        <div style="font-size:12px;color:var(--gris-400);margin-bottom:24px">Enseignant : <?= e(($classe['admin_prenom']??'').' '.($classe['admin_nom']??'')) ?></div>
        <div style="display:flex;flex-direction:column;gap:10px">
          <a href="/reussiteplus/dashboard.php" class="btn btn-primary" style="justify-content:center">
            <i data-lucide="layout-dashboard" style="width:14px;height:14px;vertical-align:-2px"></i> Mon tableau de bord
          </a>
          <a href="/reussiteplus/rejoindre.php" class="btn btn-ghost" style="justify-content:center">
            <i data-lucide="plus" style="width:14px;height:14px;vertical-align:-2px"></i> Rejoindre une autre classe
          </a>
        </div>
      </div>

      <?php elseif ($classe && !$error): ?>
      <!-- Confirmation rejoindre -->
      <div class="classe-preview">
        <div style="width:48px;height:48px;background:#7C3AED;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
          <i data-lucide="layout-list" style="width:22px;height:22px;stroke:#fff"></i>
        </div>
        <div style="font-family:var(--font-display);font-size:18px;font-weight:900;color:#3730A3;margin-bottom:4px"><?= e($classe['nom']) ?></div>
        <?php if ($classe['niveau']): ?>
        <div style="font-size:13px;color:#5B21B6;margin-bottom:4px"><?= e($classe['niveau']) ?></div>
        <?php endif; ?>
        <div style="font-size:12px;color:#6D28D9;">Classe de <?= e(($classe['admin_prenom']??'').' '.($classe['admin_nom']??'')) ?></div>
      </div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="code" value="<?= e($code) ?>">
        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:15px;justify-content:center;background:#7C3AED;border-color:#7C3AED">
          <i data-lucide="check" style="width:16px;height:16px;vertical-align:-2px"></i>
          Confirmer et rejoindre cette classe
        </button>
        <a href="/reussiteplus/rejoindre.php" class="btn btn-ghost" style="width:100%;margin-top:8px;justify-content:center">
          Annuler
        </a>
      </form>

      <?php else: ?>
      <!-- Formulaire code -->
      <?php if ($error): ?>
      <div style="background:#FEE2E2;border:1.5px solid #FECACA;border-radius:10px;padding:12px 16px;color:#DC2626;font-size:13px;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:8px">
        <i data-lucide="alert-circle" style="width:15px;height:15px;flex-shrink:0"></i> <?= e($error) ?>
      </div>
      <?php endif; ?>
      <form method="GET" action="/reussiteplus/rejoindre.php">
        <div class="form-group">
          <label class="form-label" style="text-align:center;display:block;font-size:13px;margin-bottom:12px;color:var(--gris-600)">
            Le code est composé de lettres et chiffres — Ex : <strong>6EME123</strong>
          </label>
          <input type="text" name="code" class="form-control code-input"
                 value="<?= e($code) ?>"
                 placeholder="XXXXX000"
                 maxlength="12"
                 oninput="this.value=this.value.toUpperCase()"
                 required
                 autofocus
                 style="height:60px">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:15px;justify-content:center">
          <i data-lucide="search" style="width:16px;height:16px;vertical-align:-2px"></i>
          Vérifier le code
        </button>
      </form>

      <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--gris-100);text-align:center">
        <div style="font-size:12px;color:var(--gris-500)">
          Vous n'avez pas de code ? Contactez votre enseignant ou directeur d'école.
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
