<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
// Page publique — aucune authentification requise

$code = trim(strtoupper($_GET['code'] ?? ''));
$cert = null;

if ($code) {
    $cert = dbRow(
        "SELECT cert.*, u.nom as eleve_nom, u.prenom as eleve_prenom, u.email as eleve_email,
                c.nom AS classe_nom,
                adm.nom AS admin_nom, adm.prenom AS admin_prenom,
                ec.nom AS ecole_nom
         FROM certificats_ecole cert
         JOIN utilisateurs u   ON u.id  = cert.eleve_id
         JOIN classes_ecole c ON c.id = cert.classe_id
         JOIN utilisateurs adm ON adm.id = cert.ecole_admin_id
         LEFT JOIN ecoles ec ON ec.admin_id = cert.ecole_admin_id
         WHERE cert.code_verif = ?",
        [$code]
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
$tc = $cert ? ($typeConfig[$cert['type']] ?? $typeConfig['REUSSITE']) : null;
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vérification de certificat · RÉUSSITE+</title>
  <meta name="robots" content="noindex">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Space+Grotesk:wght@500;700;900&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0 }
  body { font-family: 'Inter', sans-serif; background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 30px 16px; }
  .page-wrap { max-width: 660px; width: 100%; }
  .logo-bar { text-align: center; margin-bottom: 36px; }
  .logo-badge { display: inline-flex; align-items: center; gap: 10px; background: rgba(255,255,255,.06); border: 1.5px solid rgba(255,255,255,.1); padding: 10px 20px; border-radius: 30px; }
  .logo-icon { width: 32px; height: 32px; background: linear-gradient(135deg,#1E5FAD,#2563EB); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
  .logo-name { font-family: 'Space Grotesk', sans-serif; font-weight: 900; font-size: 16px; color: #fff; letter-spacing: 1px; }
  .logo-tag { font-size: 10px; color: rgba(255,255,255,.35); letter-spacing: .5px; }

  /* Search box */
  .search-card { background: rgba(255,255,255,.05); border: 1.5px solid rgba(255,255,255,.1); border-radius: 20px; padding: 28px; margin-bottom: 24px; }
  .search-title { font-family: 'Space Grotesk', sans-serif; font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 6px; }
  .search-sub { font-size: 13px; color: rgba(255,255,255,.4); margin-bottom: 20px; }
  .search-row { display: flex; gap: 10px; }
  .search-input { flex: 1; padding: 12px 16px; border-radius: 12px; border: 1.5px solid rgba(255,255,255,.15); background: rgba(255,255,255,.08); color: #fff; font-family: 'Space Grotesk', sans-serif; font-size: 15px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; outline: none; }
  .search-input::placeholder { color: rgba(255,255,255,.2); letter-spacing: 1px; text-transform: none; font-weight: 400; }
  .search-input:focus { border-color: #2563EB; }
  .search-btn { padding: 12px 22px; border-radius: 12px; background: linear-gradient(135deg,#1E5FAD,#2563EB); border: none; color: #fff; font-weight: 800; font-size: 13px; cursor: pointer; white-space: nowrap; transition: .15s; }
  .search-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(37,99,235,.5); }

  /* Result cards */
  .result-valid { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 30px 80px rgba(0,0,0,.5); }
  .result-header { padding: 26px 28px; }
  .valid-badge { display: inline-flex; align-items: center; gap: 8px; background: #D1FAE5; color: #065F46; border-radius: 30px; padding: 8px 18px; font-size: 12px; font-weight: 800; margin-bottom: 16px; }
  .invalid-badge { display: inline-flex; align-items: center; gap: 8px; background: #FEE2E2; color: #991B1B; border-radius: 30px; padding: 8px 18px; font-size: 12px; font-weight: 800; margin-bottom: 16px; }
  .result-name { font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 900; color: #1e293b; line-height: 1.1; margin-bottom: 4px; }
  .result-sub { font-size: 13px; color: #64748b; }
  .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 0 28px 28px; }
  .detail-item { background: #f8fafc; border-radius: 12px; padding: 14px; }
  .detail-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 4px; }
  .detail-value { font-size: 14px; font-weight: 700; color: #1e293b; }
  .code-section { margin: 0 28px 28px; background: #f1f5f9; border-radius: 12px; padding: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
  .code-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 4px; }
  .code-val { font-family: 'Space Grotesk', sans-serif; font-size: 18px; font-weight: 900; letter-spacing: 3px; color: #059669; }
  .copy-btn { padding: 8px 16px; border-radius: 8px; background: #059669; border: none; color: #fff; font-size: 11px; font-weight: 800; cursor: pointer; }

  .error-card { background: rgba(255,255,255,.05); border: 1.5px solid rgba(220,38,38,.3); border-radius: 20px; padding: 40px; text-align: center; }
  .error-icon { font-size: 56px; margin-bottom: 16px; }
  .error-title { font-family: 'Space Grotesk', sans-serif; font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 8px; }
  .error-text { font-size: 13px; color: rgba(255,255,255,.4); line-height: 1.6; }

  .footer-note { text-align: center; margin-top: 24px; font-size: 11px; color: rgba(255,255,255,.2); }
  .footer-note a { color: rgba(255,255,255,.35); text-decoration: none; }

  @media (max-width: 480px) {
    .detail-grid { grid-template-columns: 1fr; }
    .search-row { flex-direction: column; }
    .result-name { font-size: 22px; }
  }
  @media print {
    body { background: #fff; display: block; padding: 0; }
    .search-card, .logo-bar, .footer-note, .copy-btn { display: none; }
    .result-valid { box-shadow: none; border: 1.5px solid #e2e8f0; }
  }
  </style>
</head>
<body>
<div class="page-wrap">

  <!-- Logo -->
  <div class="logo-bar">
    <a href="<?= APP_URL ?>" class="logo-badge" style="text-decoration:none">
      <div class="logo-icon">🎓</div>
      <div>
        <div class="logo-name">RÉUSSITE+</div>
        <div class="logo-tag">Vérification de certificat</div>
      </div>
    </a>
  </div>

  <!-- Formulaire de recherche -->
  <div class="search-card">
    <div class="search-title">Vérifier l'authenticité d'un certificat</div>
    <div class="search-sub">Saisissez le code de vérification imprimé sur le certificat</div>
    <form method="GET" class="search-row">
      <input type="text" name="code" class="search-input" placeholder="Ex : A1B2-C3D4-E5F6"
             value="<?= e($code) ?>" maxlength="20" pattern="[A-Z0-9\-]+" title="Code alphanumérique" required>
      <button type="submit" class="search-btn">
        <span id="lucide-search-icon"></span> Vérifier
      </button>
    </form>
  </div>

  <?php if ($code && !$cert): ?>
  <!-- Certificat invalide -->
  <div class="error-card">
    <div class="error-icon">❌</div>
    <div class="error-title">Certificat introuvable</div>
    <div class="error-text">
      Le code <strong style="color:#fff;letter-spacing:2px"><?= e($code) ?></strong> ne correspond à aucun certificat dans notre base de données.<br><br>
      Vérifiez que vous avez saisi le code correctement (majuscules, tirets inclus). Ce certificat peut avoir été révoqué par l'établissement émetteur.
    </div>
  </div>

  <?php elseif ($cert): ?>
  <!-- Certificat valide -->
  <div class="result-valid">
    <!-- Header coloré -->
    <div class="result-header" style="background:linear-gradient(135deg,<?= $tc['color'] ?>,<?= $tc['color'] ?>bb)">
      <div class="valid-badge">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        Certificat authentique et vérifié
      </div>
      <div class="result-name" style="color:#fff"><?= e(($cert['eleve_prenom']??'').' '.strtoupper($cert['eleve_nom']??'')) ?></div>
      <div class="result-sub" style="color:rgba(255,255,255,.7)"><?= e($cert['eleve_email']??'') ?></div>
    </div>

    <!-- Grille de détails -->
    <div class="detail-grid" style="padding-top:28px">
      <div class="detail-item" style="border-left:3px solid <?= $tc['color'] ?>">
        <div class="detail-label">Type de certificat</div>
        <div class="detail-value"><?= $tc['label'] ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Titre</div>
        <div class="detail-value"><?= e($cert['titre']) ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Classe</div>
        <div class="detail-value"><?= e($cert['classe_nom']??'N/A') ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Établissement</div>
        <div class="detail-value"><?= e($cert['ecole_nom'] ?? (($cert['admin_prenom']??'').' '.strtoupper($cert['admin_nom']??''))) ?></div>
      </div>
      <?php if ($cert['periode']): ?>
      <div class="detail-item">
        <div class="detail-label">Période</div>
        <div class="detail-value"><?= e($cert['periode']) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($cert['mention']): ?>
      <div class="detail-item" style="border-left:3px solid #F59E0B">
        <div class="detail-label">Mention</div>
        <div class="detail-value" style="color:#B45309">🏅 <?= e($cert['mention']) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($cert['moyenne'] !== null): ?>
      <div class="detail-item" style="border-left:3px solid #059669">
        <div class="detail-label">Moyenne générale</div>
        <div class="detail-value" style="color:#059669">📊 <?= number_format($cert['moyenne'],2) ?> / 20</div>
      </div>
      <?php endif; ?>
      <div class="detail-item">
        <div class="detail-label">Date d'émission</div>
        <div class="detail-value"><?= date('d/m/Y', strtotime($cert['emis_le'])) ?></div>
      </div>
    </div>

    <?php if ($cert['message_perso']): ?>
    <div style="margin:0 28px;padding:14px;background:#f8fafc;border-radius:12px;border-left:4px solid <?= $tc['color'] ?>;margin-bottom:16px">
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:6px">Message de l'établissement</div>
      <div style="font-size:14px;color:#334155;line-height:1.6;font-style:italic"><?= nl2br(e($cert['message_perso'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Code de vérification -->
    <div class="code-section">
      <div>
        <div class="code-label">Code de vérification authentifié</div>
        <div class="code-val"><?= e($cert['code_verif']) ?></div>
      </div>
      <button class="copy-btn" onclick="navigator.clipboard.writeText(this.dataset.url).then(()=>{this.textContent='✓ Copié';setTimeout(()=>this.textContent='Copier',1500)})"
              data-url="<?= APP_URL ?>/verifier_certificat.php?code=<?= urlencode($cert['code_verif']) ?>">
        Copier
      </button>
    </div>
  </div>

  <?php else: ?>
  <!-- Aucune recherche lancée -->
  <div style="text-align:center;padding:30px 20px">
    <div style="font-size:52px;margin-bottom:16px">🔍</div>
    <div style="font-family:'Space Grotesk',sans-serif;font-size:17px;font-weight:700;color:rgba(255,255,255,.7);margin-bottom:8px">Saisissez un code pour commencer</div>
    <div style="font-size:12px;color:rgba(255,255,255,.3);line-height:1.6;max-width:360px;margin:0 auto">
      Le code de vérification se trouve en bas du certificat imprimé, sous le QR code ou la signature.
    </div>
  </div>
  <?php endif; ?>

  <div class="footer-note">
    Ce service de vérification est fourni gratuitement par <a href="<?= APP_URL ?>">RÉUSSITE+</a> · République Démocratique du Congo<br>
    Les certificats émis via notre plateforme sont cryptographiquement traçables.
  </div>
</div>

<script>
lucide.createIcons();
</script>
</body>
</html>
