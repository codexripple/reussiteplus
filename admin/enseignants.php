<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Gestion des enseignants';
$pageActive = 'admin_enseignants';
$user       = require_admin();

if (empty($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_admin'];

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_admin'], $_POST['csrf'])) {
        redirect('/reussiteplus/admin/enseignants.php', 'error', 'Action non autorisée.');
    }
    $action = $_POST['action'] ?? '';
    $uid    = $_POST['user_id'] ?? '';

    if ($action === 'creer_enseignant') {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom    = trim($_POST['nom']    ?? '');
        $email  = trim($_POST['email']  ?? '');
        $pass   = trim($_POST['password'] ?? '');

        if (!$prenom || !$nom || !$email || strlen($pass) < 6) {
            redirect('/reussiteplus/admin/enseignants.php', 'error', 'Tous les champs sont requis (mot de passe min. 6 caractères).');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('/reussiteplus/admin/enseignants.php', 'error', 'Adresse e-mail invalide.');
        }
        $exists = dbRow("SELECT id FROM utilisateurs WHERE email=?", [$email]);
        if ($exists) redirect('/reussiteplus/admin/enseignants.php', 'error', 'Cette adresse e-mail est déjà utilisée.');

        $hash  = password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $newId = sprintf('%s-%s-%s-%s-%s', ...array_map(fn($l) => bin2hex(random_bytes($l)), [4,2,2,2,6]));
        dbQuery(
            "INSERT INTO utilisateurs (id,prenom,nom,email,password_hash,role,plan,is_active,created_at)
             VALUES (?,?,?,?,?,'ENSEIGNANT','ECOLE',1,NOW())",
            [$newId, $prenom, $nom, $email, $hash]
        );
        dbInsert('admin_logs', ['user_id'=>$user['id'],'action'=>'CREER_ENSEIGNANT','details'=>json_encode(['email'=>$email,'id'=>$newId])]);
        $_SESSION['new_ens_creds'] = ['email'=>$email,'pass'=>$pass,'nom'=>"$prenom $nom",'id'=>$newId];
        redirect('/reussiteplus/admin/enseignants.php?created=1', 'success', "Compte enseignant créé : $prenom $nom");
    }

    if ($action === 'toggle_actif' && $uid) {
        $u = dbRow("SELECT is_active, prenom, nom FROM utilisateurs WHERE id=? AND role='ENSEIGNANT'", [$uid]);
        if ($u) {
            $newVal = $u['is_active'] ? 0 : 1;
            dbQuery("UPDATE utilisateurs SET is_active=? WHERE id=?", [$newVal, $uid]);
            dbInsert('admin_logs', ['user_id'=>$user['id'],'action'=>'TOGGLE_ENSEIGNANT','details'=>json_encode(['id'=>$uid,'is_active'=>$newVal])]);
            redirect('/reussiteplus/admin/enseignants.php', 'success', ($newVal ? 'Enseignant activé.' : 'Enseignant suspendu.'));
        }
    }

    if ($action === 'reset_password' && $uid) {
        $newPass = bin2hex(random_bytes(5));
        $hash    = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $u = dbRow("SELECT prenom, nom, email FROM utilisateurs WHERE id=? AND role='ENSEIGNANT'", [$uid]);
        dbQuery("UPDATE utilisateurs SET password_hash=? WHERE id=?", [$hash, $uid]);
        dbInsert('admin_logs', ['user_id'=>$user['id'],'action'=>'RESET_MDP_ENSEIGNANT','details'=>json_encode(['id'=>$uid])]);
        $_SESSION['reset_creds'] = ['email'=>$u['email']??'','pass'=>$newPass,'nom'=>($u['prenom']??'').' '.($u['nom']??'')];
        redirect('/reussiteplus/admin/enseignants.php?reset=1', 'success', 'Mot de passe réinitialisé.');
    }

    if ($action === 'supprimer' && $uid && $user['role'] === 'SUPER_ADMIN') {
        dbQuery("UPDATE utilisateurs SET is_active=0, role='ELEVE' WHERE id=? AND role='ENSEIGNANT'", [$uid]);
        dbQuery("UPDATE enseignants_ecole SET statut='INACTIF', user_id=NULL WHERE user_id=?", [$uid]);
        dbInsert('admin_logs', ['user_id'=>$user['id'],'action'=>'SUPPRIMER_ENSEIGNANT','details'=>json_encode(['id'=>$uid])]);
        redirect('/reussiteplus/admin/enseignants.php', 'success', 'Compte enseignant supprimé.');
    }
    redirect('/reussiteplus/admin/enseignants.php');
}

// ── Filtres ───────────────────────────────────────────────────
$search  = trim($_GET['q'] ?? '');
$filActif= $_GET['actif'] ?? '';
$page    = max(1,(int)($_GET['page']??1));
$limit   = 20;

$where  = "u.role = 'ENSEIGNANT'";
$params = [];
if ($search)   { $where .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($filActif !== '') { $where .= " AND u.is_active=?"; $params[] = (int)$filActif; }

$total   = (int)(dbScalar("SELECT COUNT(*) FROM utilisateurs u WHERE $where", $params) ?? 0);
$pages   = max(1, ceil($total / $limit));
$offset  = ($page-1) * $limit;

$enseignants = dbAll(
    "SELECT u.id, u.prenom, u.nom, u.email, u.is_active, u.created_at,
            ee.id as ee_id, ee.statut_compte, ee.ecole_admin_id,
            eu.prenom as ecole_prenom, eu.nom as ecole_nom,
            COUNT(DISTINCT ec.classe_id) as nb_classes,
            GROUP_CONCAT(DISTINCT m.nom ORDER BY m.nom SEPARATOR ', ') as matieres
     FROM utilisateurs u
     LEFT JOIN enseignants_ecole ee ON ee.user_id=u.id
     LEFT JOIN utilisateurs eu ON eu.id=ee.ecole_admin_id
     LEFT JOIN enseignant_classes ec ON ec.enseignant_id=ee.id
     LEFT JOIN matieres m ON m.id=ec.matiere_id
     WHERE $where
     GROUP BY u.id
     ORDER BY u.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
) ?? [];

$ensStats = [
    'total'   => (int)(dbScalar("SELECT COUNT(*) FROM utilisateurs WHERE role='ENSEIGNANT'") ?? 0),
    'actifs'  => (int)(dbScalar("SELECT COUNT(*) FROM utilisateurs WHERE role='ENSEIGNANT' AND is_active=1") ?? 0),
    'suspens' => (int)(dbScalar("SELECT COUNT(*) FROM utilisateurs WHERE role='ENSEIGNANT' AND is_active=0") ?? 0),
    'avecEcole'=> (int)(dbScalar("SELECT COUNT(DISTINCT u.id) FROM utilisateurs u JOIN enseignants_ecole ee ON ee.user_id=u.id WHERE u.role='ENSEIGNANT'") ?? 0),
];

// Credentials à afficher
$newCreds   = isset($_GET['created']) ? ($_SESSION['new_ens_creds'] ?? null) : null;
$resetCreds = isset($_GET['reset'])   ? ($_SESSION['reset_creds']   ?? null) : null;
if ($newCreds)   unset($_SESSION['new_ens_creds']);
if ($resetCreds) unset($_SESSION['reset_creds']);

include __DIR__ . '/../includes/header_app.php';
?>

<style>
.adm-ens-kpi { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px; }
@media(max-width:768px){ .adm-ens-kpi{grid-template-columns:repeat(2,1fr)} }
.creds-box { background:linear-gradient(135deg,#0d1120,#111827);border:1px solid rgba(74,222,128,.25);border-radius:14px;padding:20px 24px;margin-bottom:20px; }
.creds-row { display:flex;align-items:center;gap:10px;padding:8px 12px;background:rgba(255,255,255,.05);border-radius:8px;margin-bottom:6px; }
.creds-label { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.35);width:60px;flex-shrink:0; }
.creds-val { flex:1;font-family:monospace;font-size:13px;color:#fff;font-weight:700; }
.copy-btn { background:rgba(255,255,255,.1);border:none;border-radius:5px;padding:3px 9px;color:rgba(255,255,255,.7);font-size:11px;cursor:pointer;font-family:inherit;transition:.15s; }
.copy-btn:hover { background:rgba(255,255,255,.2);color:#fff; }
.ens-status-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0;display:inline-block; }
</style>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px">
  <div>
    <h1 style="font-size:20px;font-weight:800;color:var(--gris-900);margin-bottom:3px">Gestion des enseignants</h1>
    <div style="font-size:12.5px;color:var(--gris-500)"><?= $ensStats['total'] ?> compte<?= $ensStats['total']!=1?'s':'' ?> enseignant enregistré<?= $ensStats['total']!=1?'s':'' ?></div>
  </div>
  <button onclick="document.getElementById('modal-creer').style.display='flex'" class="btn btn-primary">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;margin-right:5px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Créer un enseignant
  </button>
</div>

<!-- Credentials nouvellement créés -->
<?php if ($newCreds || $resetCreds): $creds = $newCreds ?? $resetCreds; ?>
<div class="creds-box">
  <div style="font-size:13px;font-weight:800;color:#4ade80;margin-bottom:12px;display:flex;align-items:center;gap:7px">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
    <?= $newCreds ? 'Compte créé' : 'Mot de passe réinitialisé' ?> — <?= e($creds['nom']) ?>
  </div>
  <div style="font-size:11px;color:rgba(255,255,255,.4);margin-bottom:10px">Communiquez ces identifiants à l'enseignant. Ils ne seront affichés qu'une seule fois.</div>
  <div class="creds-row">
    <span class="creds-label">URL</span>
    <span class="creds-val" style="font-size:12px;color:rgba(255,255,255,.7)"><?= APP_URL ?>/enseignant/connexion.php</span>
    <button class="copy-btn" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/enseignant/connexion.php')">Copier</button>
  </div>
  <div class="creds-row">
    <span class="creds-label">Email</span>
    <span class="creds-val"><?= e($creds['email']) ?></span>
    <button class="copy-btn" onclick="navigator.clipboard.writeText('<?= e($creds['email']) ?>')">Copier</button>
  </div>
  <div class="creds-row">
    <span class="creds-label">Mot de passe</span>
    <span class="creds-val" style="color:#4ade80"><?= e($creds['pass']) ?></span>
    <button class="copy-btn" onclick="navigator.clipboard.writeText('<?= e($creds['pass']) ?>')">Copier</button>
  </div>
</div>
<?php endif; ?>

<!-- KPI -->
<div class="adm-ens-kpi">
  <?php foreach ([
    ['Total',          $ensStats['total'],    '#007A5E', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['Actifs',         $ensStats['actifs'],   '#1E5FAD', '<polyline points="20 6 9 17 4 12"/>'],
    ['Suspendus',      $ensStats['suspens'],  '#C9342A', '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>'],
    ['Avec école',     $ensStats['avecEcole'],'#C9972A', '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'],
  ] as [$lbl, $val, $col, $ic]): ?>
  <div class="db-kpi-card" style="border-top:2px solid <?= $col ?>;padding:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
      <span style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--gris-500)"><?= $lbl ?></span>
      <div style="width:34px;height:34px;border-radius:9px;background:<?= $col ?>18;display:flex;align-items:center;justify-content:center">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?= $col ?>" stroke-width="2.5" stroke-linecap="round"><?= $ic ?></svg>
      </div>
    </div>
    <div style="font-size:26px;font-weight:900;color:<?= $col ?>;letter-spacing:-.5px"><?= $val ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filtres -->
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex:1">
    <div style="position:relative;flex:1;max-width:320px">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--gris-400)" stroke-width="2.5" stroke-linecap="round" style="position:absolute;left:11px;top:50%;transform:translateY(-50%)"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Nom, prénom ou email…" style="padding-left:32px">
    </div>
    <select name="actif" class="form-control" style="width:auto" onchange="this.form.submit()">
      <option value="">Tous statuts</option>
      <option value="1" <?= $filActif==='1'?'selected':'' ?>>Actifs</option>
      <option value="0" <?= $filActif==='0'?'selected':'' ?>>Suspendus</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
    <?php if ($search || $filActif !== ''): ?>
    <a href="/reussiteplus/admin/enseignants.php" class="btn btn-ghost btn-sm">Effacer</a>
    <?php endif; ?>
  </form>
</div>

<!-- Table -->
<div style="background:var(--blanc);border:1px solid var(--card-border,rgba(0,0,0,.06));border-radius:14px;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--gris-100);display:flex;align-items:center;justify-content:space-between">
    <div style="font-size:13.5px;font-weight:700;color:var(--gris-900)"><?= $total ?> enseignant<?= $total!=1?'s':'' ?></div>
    <a href="/reussiteplus/admin/users.php?role=ENSEIGNANT" style="font-size:12px;color:var(--primary);text-decoration:none;font-weight:600">Vue globale utilisateurs</a>
  </div>
  <?php if ($enseignants): ?>
  <div style="overflow-x:auto">
    <table class="table" style="margin:0">
      <thead>
        <tr>
          <th style="text-align:left">Enseignant</th>
          <th>École associée</th>
          <th>Matières / Classes</th>
          <th>Statut</th>
          <th>Inscrit le</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($enseignants as $ens): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1E5FAD,#0369A1);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex-shrink:0"><?= strtoupper(substr($ens['prenom'],0,1)) ?></div>
            <div>
              <div style="font-weight:700;font-size:13.5px;color:var(--gris-900)"><?= e($ens['prenom'] . ' ' . $ens['nom']) ?></div>
              <div style="font-size:11.5px;color:var(--gris-500)"><?= e($ens['email']) ?></div>
            </div>
          </div>
        </td>
        <td style="font-size:12.5px;color:var(--gris-700)">
          <?php if ($ens['ecole_prenom']): ?>
          <div style="font-weight:600"><?= e($ens['ecole_prenom'].' '.$ens['ecole_nom']) ?></div>
          <div style="font-size:11px;color:var(--gris-400)">Admin École</div>
          <?php else: ?>
          <span style="color:var(--gris-300);font-size:12px">Non associé</span>
          <?php endif; ?>
        </td>
        <td>
          <div style="font-size:12px;color:var(--gris-700)"><?= $ens['matieres'] ? e($ens['matieres']) : '<span style="color:var(--gris-300)">—</span>' ?></div>
          <?php if ($ens['nb_classes'] > 0): ?>
          <div style="font-size:11px;color:var(--gris-400);margin-top:2px"><?= $ens['nb_classes'] ?> classe<?= $ens['nb_classes']!=1?'s':'' ?></div>
          <?php endif; ?>
        </td>
        <td>
          <span style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:20px;<?= $ens['is_active'] ? 'background:#D1FAE5;color:#065F46' : 'background:#FEE2E2;color:#7F1D1D' ?>">
            <span class="ens-status-dot" style="background:<?= $ens['is_active']?'#007A5E':'#C9342A' ?>"></span>
            <?= $ens['is_active'] ? 'Actif' : 'Suspendu' ?>
          </span>
          <?php if ($ens['statut_compte']): ?>
          <div style="font-size:10px;color:var(--gris-400);margin-top:3px"><?= e($ens['statut_compte']) ?></div>
          <?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--gris-500)"><?= date('d/m/Y', strtotime($ens['created_at'])) ?></td>
        <td style="text-align:right">
          <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap">
            <!-- Dashboard enseignant -->
            <?php if ($ens['ee_id']): ?>
            <a href="/reussiteplus/enseignant/dashboard.php?ens=<?= e($ens['ee_id']) ?>" title="Espace enseignant"
               style="background:var(--primary-subtle);border:1px solid rgba(0,122,94,.2);border-radius:7px;padding:5px 9px;display:inline-flex;align-items:center;color:var(--primary);text-decoration:none;transition:.15s;font-size:11.5px;gap:4px"
               onmouseover="this.style.background='rgba(0,122,94,.15)'" onmouseout="this.style.background='var(--primary-subtle)'">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
              Espace
            </a>
            <?php endif; ?>
            <!-- Reset MDP -->
            <form method="POST" style="display:inline" onsubmit="return confirm('Réinitialiser le mot de passe de <?= e(addslashes($ens['prenom'])) ?> ?')">
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="user_id" value="<?= e($ens['id']) ?>">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <button type="submit" title="Réinitialiser MDP" style="background:var(--gris-100);border:1px solid var(--gris-200);border-radius:7px;padding:5px 9px;cursor:pointer;display:inline-flex;align-items:center;color:var(--gris-700);transition:.15s;font-size:11.5px;gap:4px;font-family:inherit" onmouseover="this.style.background='#FEF3C7';this.style.borderColor='#C9972A';this.style.color='#92400E'" onmouseout="this.style.background='var(--gris-100)';this.style.borderColor='var(--gris-200)';this.style.color='var(--gris-700)'">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                MDP
              </button>
            </form>
            <!-- Suspendre / Activer -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle_actif">
              <input type="hidden" name="user_id" value="<?= e($ens['id']) ?>">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <button type="submit" title="<?= $ens['is_active']?'Suspendre':'Activer' ?>"
                style="background:<?= $ens['is_active']?'#FEE2E2':'#D1FAE5' ?>;border:1px solid <?= $ens['is_active']?'#FECACA':'#A7F3D0' ?>;border-radius:7px;padding:5px 9px;cursor:pointer;display:inline-flex;align-items:center;color:<?= $ens['is_active']?'#7F1D1D':'#065F46' ?>;transition:.15s;font-size:11.5px;gap:4px;font-family:inherit"
                onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                  <?= $ens['is_active'] ? '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>' : '<polyline points="20 6 9 17 4 12"/>' ?>
                </svg>
                <?= $ens['is_active'] ? 'Suspendre' : 'Activer' ?>
              </button>
            </form>
            <!-- Supprimer (SUPER_ADMIN seulement) -->
            <?php if ($user['role'] === 'SUPER_ADMIN'): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer définitivement ce compte enseignant ?')">
              <input type="hidden" name="action" value="supprimer">
              <input type="hidden" name="user_id" value="<?= e($ens['id']) ?>">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <button type="submit" title="Supprimer" style="background:#FEE2E2;border:1px solid #FECACA;border-radius:7px;padding:5px 9px;cursor:pointer;color:#7F1D1D;display:inline-flex;align-items:center;font-family:inherit;transition:.15s" onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div style="padding:12px 20px;border-top:1px solid var(--gris-100);display:flex;justify-content:space-between;align-items:center">
    <div style="font-size:12px;color:var(--gris-400)"><?= $total ?> résultats — page <?= $page ?>/<?= $pages ?></div>
    <div style="display:flex;gap:4px">
      <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?page=<?= $i ?><?= $search?"&q=".urlencode($search):'' ?><?= $filActif!==''?"&actif=$filActif":'' ?>" class="btn <?= $i==$page?'btn-primary':'btn-ghost' ?> btn-sm" style="min-width:32px;text-align:center"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php else: ?>
  <div style="text-align:center;padding:48px 24px">
    <div style="width:56px;height:56px;border-radius:14px;background:var(--gris-100);margin:0 auto 14px;display:flex;align-items:center;justify-content:center">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--gris-400)" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    </div>
    <div style="font-size:15px;font-weight:700;margin-bottom:6px">Aucun enseignant trouvé</div>
    <p style="font-size:13px;color:var(--gris-500);margin-bottom:18px"><?= $search ? 'Aucun résultat pour cette recherche.' : 'Aucun compte enseignant créé.' ?></p>
    <button onclick="document.getElementById('modal-creer').style.display='flex'" class="btn btn-primary btn-sm">Créer le premier enseignant</button>
  </div>
  <?php endif; ?>
</div>

<!-- ══ MODAL Créer enseignant ══════════════════════════════════ -->
<div id="modal-creer" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--blanc);border-radius:18px;padding:28px;width:100%;max-width:480px;margin:20px;box-shadow:0 24px 64px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <div>
        <div style="font-size:16px;font-weight:800;color:var(--gris-900)">Créer un compte enseignant</div>
        <div style="font-size:12px;color:var(--gris-500);margin-top:2px">Le compte sera immédiatement actif</div>
      </div>
      <button onclick="this.closest('#modal-creer').style.display='none'" style="background:none;border:none;cursor:pointer;color:var(--gris-400);font-size:20px;padding:4px">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="creer_enseignant">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div>
          <label class="form-label">Prénom *</label>
          <input type="text" name="prenom" class="form-control" placeholder="Jean" required>
        </div>
        <div>
          <label class="form-label">Nom *</label>
          <input type="text" name="nom" class="form-control" placeholder="Mutombo" required>
        </div>
      </div>
      <div style="margin-bottom:14px">
        <label class="form-label">Adresse e-mail *</label>
        <input type="email" name="email" class="form-control" placeholder="enseignant@ecole.cd" required>
        <div style="font-size:11px;color:var(--gris-400);margin-top:4px">Servira d'identifiant de connexion</div>
      </div>
      <div style="margin-bottom:20px">
        <label class="form-label">Mot de passe *</label>
        <div style="position:relative">
          <input type="text" name="password" id="new-pass" class="form-control" placeholder="Minimum 6 caractères" minlength="6" required style="padding-right:100px">
          <button type="button" onclick="genPwd()" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:var(--gris-100);border:1px solid var(--gris-200);border-radius:6px;padding:4px 9px;font-size:11px;font-weight:600;cursor:pointer;color:var(--gris-700);font-family:inherit">Générer</button>
        </div>
        <div style="font-size:11px;color:var(--gris-400);margin-top:4px">Communiquez ce mot de passe à l'enseignant après création.</div>
      </div>
      <div style="background:var(--primary-subtle);border:1px solid rgba(0,122,94,.15);border-radius:9px;padding:12px 14px;margin-bottom:18px;font-size:12px;color:var(--primary-dark)">
        <strong>URL de connexion :</strong> <?= APP_URL ?>/enseignant/connexion.php
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary" style="flex:1">Créer le compte</button>
        <button type="button" onclick="document.getElementById('modal-creer').style.display='none'" class="btn btn-ghost">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function genPwd() {
  const c='abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789@#!';
  let p='';for(let i=0;i<10;i++)p+=c[Math.floor(Math.random()*c.length)];
  document.getElementById('new-pass').value=p;
}
// Ouvrir modale si erreur de création
<?php if (isset($_SESSION['flash']) && strpos($_SESSION['flash']['msg']??'','requis')!==false): ?>
document.getElementById('modal-creer').style.display='flex';
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
