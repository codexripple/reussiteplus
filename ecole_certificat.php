<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Certificats';
$pageActive = 'ecole_certificat';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$classes = dbAll("SELECT id, nom FROM classes_ecole WHERE admin_id=? AND actif=1 ORDER BY nom", [$user['id']]) ?? [];
$filtreClasse = $_GET['classe'] ?? ($classes[0]['id'] ?? '');

$classeActive = null;
foreach ($classes as $cl) { if ($cl['id'] === $filtreClasse) { $classeActive = $cl; break; } }

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'emettre_certificat') {
        $eleveId   = $_POST['eleve_id']    ?? '';
        $classeId  = $_POST['classe_id']   ?? '';
        $titre     = trim($_POST['titre']  ?? 'Certificat de réussite');
        $type      = $_POST['type']        ?? 'REUSSITE';
        $periode   = trim($_POST['periode'] ?? '');
        $mention   = trim($_POST['mention'] ?? '');
        $moyenne   = $_POST['moyenne'] !== '' ? (float)$_POST['moyenne'] : null;
        $msgPerso  = trim($_POST['message_perso'] ?? '');
        $emisLe    = $_POST['emis_le'] ?: date('Y-m-d');
        $validTypes = ['REUSSITE','EXCELLENCE','PARTICIPATION','MERITE','FIN_ANNEE','CUSTOM'];
        $type = in_array($type, $validTypes) ? $type : 'REUSSITE';

        // Vérifier propriété de la classe
        $c = dbRow("SELECT id FROM classes_ecole WHERE id=? AND admin_id=?", [$classeId, $user['id']]);
        if ($c && $eleveId) {
            // Code de vérification unique
            $code = strtoupper(substr(md5(uniqid($eleveId, true)), 0, 4)) . '-' .
                    strtoupper(substr(md5(uniqid()), 0, 4)) . '-' .
                    strtoupper(substr(md5(uniqid($classeId)), 0, 4));
            dbRun(
                "INSERT INTO certificats_ecole (ecole_admin_id, eleve_id, classe_id, titre, type, periode, mention, moyenne, message_perso, code_verif, emis_le)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [$user['id'], $eleveId, $classeId, $titre, $type, $periode ?: null,
                 $mention ?: null, $moyenne, $msgPerso ?: null, $code, $emisLe]
            );
            redirect('/reussiteplus/ecole_certificat.php?classe='.urlencode($filtreClasse).'&preview='.urlencode($code), 'success', 'Certificat émis avec succès.');
        }
    }

    if ($action === 'supprimer_certificat') {
        $id = $_POST['cert_id'] ?? '';
        dbRun("DELETE FROM certificats_ecole WHERE id=? AND ecole_admin_id=?", [$id, $user['id']]);
        redirect('/reussiteplus/ecole_certificat.php?classe='.urlencode($filtreClasse), 'success', 'Certificat supprimé.');
    }
    exit;
}

// ── Données ───────────────────────────────────────────────────
$eleves = [];
if ($classeActive) {
    $eleves = dbAll(
        "SELECT u.id, u.nom, u.prenom FROM classe_membres cm JOIN users u ON u.id=cm.user_id WHERE cm.classe_id=? ORDER BY u.nom, u.prenom",
        [$filtreClasse]
    ) ?? [];
}

$certificats = [];
if ($classeActive) {
    $certificats = dbAll(
        "SELECT cert.*, u.nom, u.prenom, u.email FROM certificats_ecole cert
         JOIN users u ON u.id=cert.eleve_id
         WHERE cert.ecole_admin_id=? AND cert.classe_id=?
         ORDER BY cert.created_at DESC",
        [$user['id'], $filtreClasse]
    ) ?? [];
}

// Aperçu d'un certificat spécifique
$previewCode = $_GET['preview'] ?? '';
$certPreview = null;
if ($previewCode) {
    $certPreview = dbRow(
        "SELECT cert.*, u.nom, u.prenom, c.nom as classe_nom, adm.nom as admin_nom, adm.prenom as admin_prenom
         FROM certificats_ecole cert
         JOIN users u ON u.id=cert.eleve_id
         JOIN classes_ecole c ON c.id=cert.classe_id
         JOIN users adm ON adm.id=cert.ecole_admin_id
         WHERE cert.code_verif=? AND cert.ecole_admin_id=?",
        [$previewCode, $user['id']]
    );
}

$typeConfig = [
    'REUSSITE'      => ['label'=>'Certificat de Réussite',    'color'=>'#1E5FAD','accent'=>'#DBEAFE','icon'=>'award',        'ribbon'=>'Réussite'],
    'EXCELLENCE'    => ['label'=>'Certificat d\'Excellence',  'color'=>'#B45309','accent'=>'#FEF3C7','icon'=>'star',         'ribbon'=>'Excellence'],
    'PARTICIPATION' => ['label'=>'Certificat de Participation','color'=>'#059669','accent'=>'#D1FAE5','icon'=>'users',        'ribbon'=>'Participation'],
    'MERITE'        => ['label'=>'Certificat de Mérite',      'color'=>'#7C3AED','accent'=>'#EDE9FE','icon'=>'medal',        'ribbon'=>'Mérite'],
    'FIN_ANNEE'     => ['label'=>'Diplôme de Fin d\'Année',   'color'=>'#DC2626','accent'=>'#FEE2E2','icon'=>'graduation-cap','ribbon'=>'Diplôme'],
    'CUSTOM'        => ['label'=>'Attestation Personnalisée', 'color'=>'#374151','accent'=>'#F3F4F6','icon'=>'file-text',    'ribbon'=>'Attestation'],
];

$ecole = dbRow("SELECT * FROM ecoles WHERE admin_id=?", [$user['id']]);

include __DIR__ . '/includes/header_app.php';
?>

<style>
@media print {
  .sidebar,.main-header,header,.btn,.cert-actions,.cert-sidebar,.cert-list-section,
  nav,.flash-message { display:none!important }
  .main-content { margin:0!important; padding:0!important }
  body { background:#fff!important }
  .cert-paper { box-shadow:none!important; border:none!important; page-break-inside:avoid }
}

.cert-hero { background:linear-gradient(135deg,#0f172a,#1e3a5f 50%,#0f172a); border-radius:var(--radius-xl); padding:28px; margin-bottom:22px; }
.cert-type-btn { padding:9px 14px; border-radius:10px; border:1.5px solid var(--gris-200); background:var(--blanc); font-size:12px; font-weight:700; color:var(--gris-600); cursor:pointer; transition:.2s; display:flex; align-items:center; gap:7px; }
.cert-type-btn:hover, .cert-type-btn.active { border-color:var(--primary); color:var(--primary); background:var(--primary-subtle); }

/* ── Le certificat lui-même ── */
.cert-paper {
  width: 100%;
  max-width: 760px;
  margin: 0 auto;
  background: #fff;
  border-radius: 4px;
  box-shadow: 0 20px 60px rgba(0,0,0,.18);
  overflow: hidden;
  position: relative;
  font-family: Georgia, 'Times New Roman', serif;
  aspect-ratio: 1.414 / 1;
  min-height: 500px;
}
.cert-outer-border {
  position: absolute; inset: 10px;
  border: 3px double currentColor;
  border-radius: 2px;
  pointer-events: none;
  z-index: 2;
}
.cert-inner-border {
  position: absolute; inset: 16px;
  border: 1px solid currentColor;
  opacity: .3;
  border-radius: 1px;
  pointer-events: none;
  z-index: 2;
}
.cert-corner {
  position: absolute; width: 36px; height: 36px;
  background: radial-gradient(circle at center, currentColor 20%, transparent 70%);
  opacity: .25;
  z-index: 3;
}
.cert-corner.tl { top: 18px; left: 18px; }
.cert-corner.tr { top: 18px; right: 18px; }
.cert-corner.bl { bottom: 18px; left: 18px; }
.cert-corner.br { bottom: 18px; right: 18px; }
.cert-watermark {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  font-size: 120px; opacity: .035; z-index: 1; font-weight: 900;
  transform: rotate(-20deg); letter-spacing: -4px;
  user-select: none; pointer-events: none;
}
.cert-body { position: relative; z-index: 4; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 52px 60px; text-align: center; }
.cert-org { font-size: 11px; text-transform: uppercase; letter-spacing: 4px; opacity: .6; margin-bottom: 4px; font-family: var(--font-display); }
.cert-school-name { font-size: 22px; font-weight: 900; font-family: var(--font-display); letter-spacing: 1px; margin-bottom: 2px; }
.cert-country { font-size: 9px; text-transform: uppercase; letter-spacing: 3px; opacity: .45; margin-bottom: 20px; font-family: var(--font-display); }
.cert-divider { width: 80px; height: 2px; margin: 0 auto 18px; border-radius: 2px; }
.cert-type-title { font-size: 32px; font-weight: 900; font-family: var(--font-display); text-transform: uppercase; letter-spacing: 3px; margin-bottom: 14px; line-height: 1; }
.cert-certifie { font-size: 11px; text-transform: uppercase; letter-spacing: 3px; opacity: .55; margin-bottom: 6px; font-family: var(--font-display); }
.cert-name { font-size: 28px; font-weight: 400; font-style: italic; margin-bottom: 12px; }
.cert-desc { font-size: 13px; line-height: 1.7; max-width: 480px; opacity: .7; margin-bottom: 16px; }
.cert-note-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; border-radius: 30px; font-family: var(--font-display); font-size: 13px; font-weight: 800; margin-bottom: 14px; }
.cert-footer { display: flex; justify-content: space-between; width: 100%; margin-top: auto; padding-top: 16px; }
.cert-sig { text-align: center; flex: 1; }
.cert-sig-line { height: 1px; width: 80%; margin: 0 auto 5px; }
.cert-sig-title { font-size: 9px; text-transform: uppercase; letter-spacing: 2px; opacity: .5; font-family: var(--font-display); }
.cert-qr-area { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.cert-qr-box { width: 40px; height: 40px; border: 1px solid currentColor; opacity: .3; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 7px; }
.cert-ribbon {
  position: absolute; top: 0; right: 0; z-index: 5;
  width: 0; height: 0;
  border-style: solid;
  border-width: 0 80px 80px 0;
}
.cert-ribbon-text {
  position: absolute; top: 10px; right: 2px;
  font-size: 9px; font-weight: 900; color: #fff;
  transform: rotate(45deg);
  font-family: var(--font-display);
  text-transform: uppercase;
  letter-spacing: 1px;
  width: 60px; text-align: center; line-height: 1.1;
}
</style>

<!-- Hero -->
<div class="cert-hero">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">
        <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.35);text-decoration:none">Mon École</a> / Certificats
      </div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff;display:flex;align-items:center;gap:10px">
        <span style="font-size:22px">🎓</span> Certificats & Diplômes
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.45);margin-top:3px">Émettez des certificats officiels vérifiables pour vos élèves</div>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <div style="text-align:center;padding:10px 18px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px">
        <div style="font-family:var(--font-display);font-size:22px;font-weight:900;color:#fff"><?= count($certificats) ?></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase">Émis</div>
      </div>
      <button onclick="document.getElementById('modal-emettre').style.display='flex'"
              style="background:linear-gradient(135deg,#1E5FAD,#2563EB);border:none;color:#fff;padding:11px 20px;border-radius:var(--radius);font-size:13px;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(37,99,235,.5);transition:.2s"
              onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
        <span style="font-size:16px">🎓</span> Émettre un certificat
      </button>
    </div>
  </div>
</div>

<!-- Sélecteur classe -->
<div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($classes as $cl): ?>
  <a href="/reussiteplus/ecole_certificat.php?classe=<?= urlencode($cl['id']) ?>"
     style="padding:7px 14px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;transition:.15s;<?= $filtreClasse===$cl['id']?'background:#1E5FAD;color:#fff':'background:var(--blanc);color:var(--gris-600);border:1.5px solid var(--gris-200)' ?>">
    <?= e($cl['nom']) ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($certPreview): ?>
<?php $tc = $typeConfig[$certPreview['type']] ?? $typeConfig['REUSSITE']; ?>

<!-- ── APERÇU CERTIFICAT ──────────────────────────────────── -->
<div class="cert-actions" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px">
  <a href="/reussiteplus/ecole_certificat.php?classe=<?= urlencode($filtreClasse) ?>" class="btn btn-ghost btn-sm">
    <i data-lucide="arrow-left" style="width:13px;height:13px;vertical-align:-2px"></i> Retour à la liste
  </a>
  <div style="display:flex;gap:8px">
    <a href="/reussiteplus/verifier_certificat.php?code=<?= urlencode($certPreview['code_verif']) ?>" target="_blank"
       class="btn btn-ghost btn-sm">
      <i data-lucide="external-link" style="width:12px;height:12px;vertical-align:-2px"></i> Page de vérification
    </a>
    <button onclick="window.print()" class="btn btn-primary" style="background:#1E5FAD;border-color:#1E5FAD">
      <i data-lucide="printer" style="width:13px;height:13px;vertical-align:-2px"></i> Imprimer / PDF
    </button>
  </div>
</div>

<!-- Le certificat -->
<div class="cert-paper" style="color:<?= $tc['color'] ?>">
  <!-- Watermark -->
  <div class="cert-watermark" style="color:<?= $tc['color'] ?>">✦</div>
  <!-- Borders -->
  <div class="cert-outer-border" style="color:<?= $tc['color'] ?>"></div>
  <div class="cert-inner-border" style="color:<?= $tc['color'] ?>"></div>
  <!-- Corners -->
  <?php foreach (['tl','tr','bl','br'] as $pos): ?>
  <div class="cert-corner <?= $pos ?>" style="color:<?= $tc['color'] ?>"></div>
  <?php endforeach; ?>
  <!-- Ribbon -->
  <div class="cert-ribbon" style="border-color:transparent <?= $tc['color'] ?> transparent transparent">
    <div class="cert-ribbon-text"><?= $tc['ribbon'] ?></div>
  </div>

  <div class="cert-body" style="color:<?= $tc['color'] ?>">
    <!-- Header organisation -->
    <div class="cert-org">République Démocratique du Congo</div>
    <div class="cert-school-name"><?= e($ecole['nom'] ?? 'RÉUSSITE+') ?></div>
    <div class="cert-country">Plateforme d'éducation numérique · reussiteplus.cd</div>
    <div class="cert-divider" style="background:<?= $tc['color'] ?>"></div>

    <!-- Type de certificat -->
    <div class="cert-type-title" style="color:<?= $tc['color'] ?>"><?= $tc['label'] ?></div>

    <!-- Certifie que -->
    <div class="cert-certifie">certifie que</div>
    <div class="cert-name" style="color:<?= $tc['color'] ?>;font-size:32px">
      <?= e(strtoupper($certPreview['nom']??'')) ?> <?= e($certPreview['prenom']??'') ?>
    </div>

    <!-- Description -->
    <?php if ($certPreview['message_perso']): ?>
    <div class="cert-desc"><?= nl2br(e($certPreview['message_perso'])) ?></div>
    <?php else: ?>
    <div class="cert-desc">
      a satisfait aux exigences et conditions requises pour la délivrance du présent certificat
      <?php if ($certPreview['classe_nom']): ?>, dans la classe de <strong><?= e($certPreview['classe_nom']) ?></strong><?php endif; ?>
      <?php if ($certPreview['periode']): ?>, période : <strong><?= e($certPreview['periode']) ?></strong><?php endif; ?>.
    </div>
    <?php endif; ?>

    <!-- Note + mention -->
    <?php if ($certPreview['moyenne'] !== null || $certPreview['mention']): ?>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:14px">
      <?php if ($certPreview['moyenne'] !== null): ?>
      <div class="cert-note-badge" style="background:<?= $tc['accent'] ?>;color:<?= $tc['color'] ?>">
        📊 Moyenne : <?= number_format($certPreview['moyenne'],2) ?> / 20
      </div>
      <?php endif; ?>
      <?php if ($certPreview['mention']): ?>
      <div class="cert-note-badge" style="background:<?= $tc['accent'] ?>;color:<?= $tc['color'] ?>">
        🏅 Mention : <?= e($certPreview['mention']) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Footer signatures + QR -->
    <div class="cert-footer">
      <div class="cert-sig">
        <div class="cert-sig-line" style="background:<?= $tc['color'] ?>"></div>
        <div class="cert-sig-title">Le Directeur</div>
        <div style="font-size:10px;opacity:.6;font-family:var(--font-display);margin-top:2px"><?= e(strtoupper($certPreview['admin_nom']??'')) ?></div>
      </div>
      <div class="cert-qr-area" style="color:<?= $tc['color'] ?>">
        <div class="cert-qr-box">QR</div>
        <div style="font-size:7px;letter-spacing:1px;opacity:.5;font-family:var(--font-display);text-transform:uppercase;max-width:60px;text-align:center;line-height:1.3"><?= e($certPreview['code_verif']) ?></div>
      </div>
      <div class="cert-sig">
        <div class="cert-sig-line" style="background:<?= $tc['color'] ?>"></div>
        <div class="cert-sig-title">Date d'émission</div>
        <div style="font-size:10px;opacity:.6;font-family:var(--font-display);margin-top:2px"><?= date('d/m/Y', strtotime($certPreview['emis_le'])) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Info vérification -->
<div class="cert-actions" style="max-width:760px;margin:14px auto 0;background:var(--gris-50);border:1.5px solid var(--gris-200);border-radius:12px;padding:12px 18px;display:flex;align-items:center;gap:12px;justify-content:space-between;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:10px">
    <div style="width:34px;height:34px;background:#D1FAE5;border-radius:8px;display:flex;align-items:center;justify-content:center">
      <i data-lucide="shield-check" style="width:16px;height:16px;stroke:#059669"></i>
    </div>
    <div>
      <div style="font-size:12px;font-weight:700;color:var(--gris-800)">Code de vérification</div>
      <div style="font-family:var(--font-display);font-size:16px;font-weight:900;color:#059669;letter-spacing:2px"><?= e($certPreview['code_verif']) ?></div>
    </div>
  </div>
  <div style="display:flex;gap:8px">
    <button onclick="navigator.clipboard.writeText('<?= APP_URL ?>/verifier_certificat.php?code=<?= urlencode($certPreview['code_verif']) ?>').then(()=>{this.textContent='✓ Copié!';setTimeout(()=>this.textContent='Copier le lien',1500)})"
            class="btn btn-ghost btn-sm">Copier le lien</button>
    <a href="/reussiteplus/verifier_certificat.php?code=<?= urlencode($certPreview['code_verif']) ?>" target="_blank" class="btn btn-sm" style="background:#059669;color:#fff;border:none;font-weight:700">
      <i data-lucide="external-link" style="width:12px;height:12px;vertical-align:-2px;stroke:#fff"></i> Voir public
    </a>
  </div>
</div>

<?php else: ?>

<!-- Liste des certificats émis -->
<?php if ($certificats): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
  <?php foreach ($certificats as $cert): ?>
  <?php $tc = $typeConfig[$cert['type']] ?? $typeConfig['REUSSITE']; ?>
  <div style="background:var(--blanc);border:1.5px solid var(--gris-200);border-radius:var(--radius-lg);overflow:hidden;transition:all .2s" onmouseover="this.style.boxShadow='0 6px 24px rgba(0,0,0,.1)';this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='';this.style.transform=''">
    <!-- Bande couleur + icône -->
    <div style="background:linear-gradient(135deg,<?= $tc['color'] ?>,<?= $tc['color'] ?>99);padding:16px;position:relative;overflow:hidden">
      <div style="position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:rgba(255,255,255,.1);border-radius:50%"></div>
      <div style="display:flex;align-items:center;gap:10px;position:relative">
        <div style="width:40px;height:40px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center">
          <i data-lucide="<?= $tc['icon'] ?>" style="width:20px;height:20px;stroke:#fff"></i>
        </div>
        <div>
          <div style="font-size:10px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.5px"><?= $tc['ribbon'] ?></div>
          <div style="font-family:var(--font-display);font-size:13px;font-weight:800;color:#fff"><?= e($cert['titre']) ?></div>
        </div>
      </div>
    </div>
    <div style="padding:14px">
      <div style="font-size:15px;font-weight:800;color:var(--gris-900);margin-bottom:4px"><?= e(($cert['prenom']??'').' '.strtoupper($cert['nom']??'')) ?></div>
      <div style="font-size:11px;color:var(--gris-500);margin-bottom:8px"><?= e($cert['email']??'') ?></div>
      <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:10px">
        <?php if ($cert['mention']): ?>
        <span style="background:<?= $tc['accent'] ?>;color:<?= $tc['color'] ?>;font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px">🏅 <?= e($cert['mention']) ?></span>
        <?php endif; ?>
        <?php if ($cert['moyenne'] !== null): ?>
        <span style="background:var(--gris-100);color:var(--gris-600);font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px">📊 <?= number_format($cert['moyenne'],2) ?>/20</span>
        <?php endif; ?>
        <?php if ($cert['periode']): ?>
        <span style="background:var(--primary-subtle);color:var(--primary);font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px"><?= e($cert['periode']) ?></span>
        <?php endif; ?>
      </div>
      <div style="font-size:11px;color:var(--gris-400);margin-bottom:12px;display:flex;align-items:center;gap:5px">
        <i data-lucide="calendar" style="width:11px;height:11px"></i>
        Émis le <?= date('d/m/Y', strtotime($cert['emis_le'])) ?>
        &nbsp;·&nbsp;
        <span style="font-family:var(--font-display);font-weight:700;color:var(--gris-500);letter-spacing:1px"><?= e($cert['code_verif']) ?></span>
      </div>
      <div style="display:flex;gap:6px">
        <a href="/reussiteplus/ecole_certificat.php?classe=<?= urlencode($filtreClasse) ?>&preview=<?= urlencode($cert['code_verif']) ?>"
           class="btn btn-sm" style="flex:1;justify-content:center;background:<?= $tc['color'] ?>;color:#fff;border:none;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:5px">
          <i data-lucide="eye" style="width:12px;height:12px;stroke:#fff"></i> Voir / Imprimer
        </a>
        <a href="/reussiteplus/verifier_certificat.php?code=<?= urlencode($cert['code_verif']) ?>" target="_blank"
           class="btn btn-ghost btn-sm" title="Page de vérification publique">
          <i data-lucide="external-link" style="width:12px;height:12px"></i>
        </a>
        <form method="POST" onsubmit="return confirm('Supprimer ce certificat définitivement ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="supprimer_certificat">
          <input type="hidden" name="cert_id" value="<?= e($cert['id']) ?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#FECACA">
            <i data-lucide="trash-2" style="width:12px;height:12px"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:60px 30px">
  <div style="font-size:56px;margin-bottom:16px">🎓</div>
  <div style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:8px">Aucun certificat émis</div>
  <p style="color:var(--gris-500);font-size:13px;max-width:380px;margin:0 auto 24px;line-height:1.6">
    Récompensez vos élèves avec des certificats officiels vérifiables : réussite, excellence, participation, fin d'année…
  </p>
  <button onclick="document.getElementById('modal-emettre').style.display='flex'" class="btn btn-primary" style="background:#1E5FAD;border-color:#1E5FAD">
    🎓 Émettre le premier certificat
  </button>
</div>
<?php endif; ?>

<?php endif; // fin aperçu ?>

<!-- ══ MODAL Émettre certificat ════════════════════════════ -->
<div id="modal-emettre" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);align-items:center;justify-content:center;z-index:1000;padding:20px;backdrop-filter:blur(5px)" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--blanc);border-radius:22px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto">
    <div style="padding:22px 26px 18px;border-bottom:1px solid var(--gris-100);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--blanc);z-index:2">
      <span style="font-family:var(--font-display);font-size:17px;font-weight:900;display:flex;align-items:center;gap:9px">
        🎓 Émettre un certificat
      </span>
      <button onclick="document.getElementById('modal-emettre').style.display='none'" style="background:none;border:none;cursor:pointer"><i data-lucide="x" style="width:20px;height:20px;stroke:var(--gris-400)"></i></button>
    </div>
    <div style="padding:22px 26px">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="emettre_certificat">

        <!-- Type de certificat -->
        <div class="form-group">
          <label class="form-label">Type de certificat</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px" id="type-grid">
            <?php foreach ($typeConfig as $k => $tc): ?>
            <label style="display:flex;align-items:center;gap:8px;padding:9px 12px;border:1.5px solid var(--gris-200);border-radius:10px;cursor:pointer;transition:.15s" id="type-lbl-<?= $k ?>"
                   onmouseover="this.style.borderColor='<?= $tc['color'] ?>'" onmouseout="updateTypeStyle()">
              <input type="radio" name="type" value="<?= $k ?>" <?= $k==='REUSSITE'?'checked':'' ?> onchange="updateTypeStyle()" style="accent-color:<?= $tc['color'] ?>">
              <i data-lucide="<?= $tc['icon'] ?>" style="width:14px;height:14px;stroke:<?= $tc['color'] ?>"></i>
              <span style="font-size:11px;font-weight:700;color:var(--gris-700)"><?= $tc['ribbon'] ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Classe *</label>
            <select name="classe_id" class="form-control" required onchange="loadEleves(this.value)">
              <option value="">-- Sélectionner --</option>
              <?php foreach ($classes as $cl): ?>
              <option value="<?= e($cl['id']) ?>" <?= $filtreClasse===$cl['id']?'selected':'' ?>><?= e($cl['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Élève *</label>
            <select name="eleve_id" class="form-control" required id="eleve-select">
              <option value="">-- Choisir un élève --</option>
              <?php foreach ($eleves as $el): ?>
              <option value="<?= e($el['id']) ?>"><?= e(($el['prenom']??'').' '.($el['nom']??'')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Titre du certificat</label>
          <input type="text" name="titre" class="form-control" value="Certificat de réussite" placeholder="Ex : Certificat de fin d'année 2025-2026">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Période</label>
            <input type="text" name="periode" class="form-control" placeholder="Ex : 1er Trimestre 2026">
          </div>
          <div class="form-group">
            <label class="form-label">Mention</label>
            <select name="mention" class="form-control">
              <option value="">-- Aucune --</option>
              <option>Très Bien</option>
              <option>Bien</option>
              <option>Assez Bien</option>
              <option>Passable</option>
              <option>Félicitations</option>
              <option>Très Honorable</option>
            </select>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Moyenne générale (/20)</label>
            <input type="number" name="moyenne" class="form-control" placeholder="Ex : 15.50" min="0" max="20" step="0.01">
          </div>
          <div class="form-group">
            <label class="form-label">Date d'émission</label>
            <input type="date" name="emis_le" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Message personnalisé (optionnel)</label>
          <textarea name="message_perso" class="form-control" rows="3" placeholder="Ex : A brillamment réussi l'examen national de fin d'année scolaire avec les félicitations du jury…"></textarea>
          <div style="font-size:11px;color:var(--gris-400);margin-top:4px">Si vide, un message standard sera généré automatiquement.</div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:15px;background:#1E5FAD;border-color:#1E5FAD">
          🎓 Générer et émettre le certificat
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function updateTypeStyle() {
  document.querySelectorAll('[id^="type-lbl-"]').forEach(lbl => {
    const inp = lbl.querySelector('input[type=radio]');
    lbl.style.borderColor = inp.checked ? inp.style.accentColor : 'var(--gris-200)';
    lbl.style.background  = inp.checked ? inp.style.accentColor + '15' : '';
  });
}
updateTypeStyle();

function loadEleves(classeId) {
  const sel = document.getElementById('eleve-select');
  if (!classeId) return;
  // Reload avec le nouveau filtreClasse
  const url = new URL(window.location.href);
  url.searchParams.set('classe', classeId);
  window.location.href = url.toString();
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
