<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
// Page publique � aucune authentification requise

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
    'REUSSITE'      => ['label'=>'Certificat de Reussite',     'couleur'=>'#1E5FAD','accent'=>'#DBEAFE','icone'=>'award',         'ruban'=>'Reussite'],
    'EXCELLENCE'    => ['label'=>"Certificat d'Excellence",    'couleur'=>'#B45309','accent'=>'#FEF3C7','icone'=>'star',          'ruban'=>'Excellence'],
    'PARTICIPATION' => ['label'=>'Certificat de Participation','couleur'=>'#059669','accent'=>'#D1FAE5','icone'=>'users',         'ruban'=>'Participation'],
    'MERITE'        => ['label'=>"Certificat de Merite",       'couleur'=>'#7C3AED','accent'=>'#EDE9FE','icone'=>'medal',         'ruban'=>'Merite'],
    'FIN_ANNEE'     => ['label'=>"Diplome de Fin d'Annee",     'couleur'=>'#DC2626','accent'=>'#FEE2E2','icone'=>'graduation-cap','ruban'=>'Diplome'],
    'CUSTOM'        => ['label'=>'Attestation Personnalisee',  'couleur'=>'#374151','accent'=>'#F3F4F6','icone'=>'file-text',     'ruban'=>'Attestation'],
];
$tc = $cert ? ($typeConfig[$cert['type']] ?? $typeConfig['REUSSITE']) : null;
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vérification de certificat — RÉUSSITE+</title>
  <meta name="robots" content="noindex">
  <link rel="icon" type="image/svg+xml" href="/reussiteplus/assets/img/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      background-color: #0a1628;
      background-image:
        radial-gradient(circle at 20% 50%, rgba(0,122,94,.18) 0%, transparent 55%),
        radial-gradient(circle at 80% 20%, rgba(30,95,173,.22) 0%, transparent 50%),
        radial-gradient(circle at 60% 80%, rgba(124,58,237,.12) 0%, transparent 45%);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 48px 16px 64px;
    }
    .barre-nav {
      width: 100%; max-width: 700px;
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 48px;
    }
    .logo-lien { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; }
    .logo-icone { width: 42px; height: 42px; border-radius: 12px; overflow: hidden; flex-shrink: 0; box-shadow: 0 4px 16px rgba(0,122,94,.4); }
    .logo-icone img { width: 100%; height: 100%; display: block; }
    .logo-texte { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 20px; color: #fff; letter-spacing: .5px; }
    .logo-texte span { color: #C9972A; }
    .logo-sous { font-size: 11px; color: rgba(255,255,255,.4); letter-spacing: .5px; line-height: 1; }
    .badge-public { font-size: 11px; font-weight: 600; color: rgba(255,255,255,.5); background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1); border-radius: 20px; padding: 6px 14px; }
    .conteneur { width: 100%; max-width: 700px; }
    .carte-recherche {
      background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.09);
      border-radius: 20px; padding: 36px 32px; margin-bottom: 24px; backdrop-filter: blur(12px);
    }
    .recherche-titre { font-family: 'Poppins', sans-serif; font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 6px; }
    .recherche-sous { font-size: 13px; color: rgba(255,255,255,.4); margin-bottom: 24px; line-height: 1.6; }
    .recherche-ligne { display: flex; gap: 10px; }
    .champ-code {
      flex: 1; padding: 14px 18px; border-radius: 12px;
      border: 1.5px solid rgba(255,255,255,.12); background: rgba(255,255,255,.06);
      color: #fff; font-family: 'Poppins', sans-serif; font-size: 16px; font-weight: 700;
      letter-spacing: 3px; text-transform: uppercase; outline: none; transition: border-color .2s;
    }
    .champ-code::placeholder { color: rgba(255,255,255,.2); letter-spacing: 1px; font-weight: 400; font-size: 14px; }
    .champ-code:focus { border-color: #007A5E; }
    .bouton-verifier {
      padding: 14px 24px; border-radius: 12px;
      background: linear-gradient(135deg, #007A5E, #059669);
      border: none; color: #fff; font-family: 'Poppins', sans-serif;
      font-weight: 700; font-size: 14px; cursor: pointer; white-space: nowrap;
      transition: transform .15s, box-shadow .15s;
      display: flex; align-items: center; gap: 8px;
    }
    .bouton-verifier:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,122,94,.45); }
    .carte-resultat {
      background: #fff; border-radius: 20px; overflow: hidden;
      box-shadow: 0 32px 80px rgba(0,0,0,.5); margin-bottom: 24px;
      animation: glisserHaut .35s ease-out;
    }
    @keyframes glisserHaut { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
    .entete-resultat { padding: 28px 32px 24px; position: relative; }
    .badge-valide { display: inline-flex; align-items: center; gap: 7px; background: #D1FAE5; color: #065F46; border-radius: 30px; padding: 7px 16px; font-size: 12px; font-weight: 700; margin-bottom: 18px; }
    .badge-invalide { display: inline-flex; align-items: center; gap: 7px; background: #FEE2E2; color: #991B1B; border-radius: 30px; padding: 7px 16px; font-size: 12px; font-weight: 700; margin-bottom: 18px; }
    .nom-eleve { font-family: 'Poppins', sans-serif; font-size: 32px; font-weight: 900; color: #fff; line-height: 1.1; margin-bottom: 5px; }
    .email-eleve { font-size: 13px; color: rgba(255,255,255,.65); }
    .grille-details { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 28px 32px; }
    .detail { background: #f8fafc; border-radius: 12px; padding: 14px 16px; }
    .detail-etiquette { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 5px; font-weight: 600; }
    .detail-valeur { font-size: 14px; font-weight: 700; color: #1e293b; line-height: 1.3; }
    .message-etablissement { margin: 0 32px 20px; padding: 16px; background: #f8fafc; border-radius: 12px; }
    .message-etablissement p { font-size: 14px; color: #334155; line-height: 1.6; font-style: italic; }
    .section-code { margin: 0 32px 32px; background: #f1f5f9; border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .code-etiquette { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 5px; font-weight: 600; }
    .code-valeur { font-family: 'Poppins', sans-serif; font-size: 20px; font-weight: 900; letter-spacing: 4px; color: #059669; }
    .bouton-copier { padding: 9px 18px; border-radius: 9px; background: #059669; border: none; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: background .2s; display: flex; align-items: center; gap: 6px; }
    .bouton-copier:hover { background: #047857; }
    .carte-erreur { background: rgba(255,255,255,.04); border: 1px solid rgba(220,38,38,.3); border-radius: 20px; padding: 48px 32px; text-align: center; margin-bottom: 24px; animation: glisserHaut .35s ease-out; }
    .erreur-icone { width: 64px; height: 64px; background: rgba(220,38,38,.12); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: #ef4444; }
    .erreur-titre { font-family: 'Poppins', sans-serif; font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 10px; }
    .erreur-texte { font-size: 13px; color: rgba(255,255,255,.45); line-height: 1.7; max-width: 420px; margin: 0 auto; }
    .etat-vide { text-align: center; padding: 40px 20px; }
    .vide-icone { width: 72px; height: 72px; background: rgba(255,255,255,.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: rgba(255,255,255,.3); }
    .vide-titre { font-family: 'Poppins', sans-serif; font-size: 17px; font-weight: 700; color: rgba(255,255,255,.6); margin-bottom: 8px; }
    .vide-texte { font-size: 13px; color: rgba(255,255,255,.3); line-height: 1.7; max-width: 360px; margin: 0 auto; }
    .pied-page { text-align: center; margin-top: 8px; font-size: 11.5px; color: rgba(255,255,255,.2); line-height: 1.8; }
    .pied-page a { color: rgba(255,255,255,.35); text-decoration: none; }
    .pied-page a:hover { color: rgba(255,255,255,.6); }
    @media print {
      body { background: #fff; padding: 0; }
      .barre-nav, .carte-recherche, .pied-page, .bouton-copier { display: none; }
      .carte-resultat { box-shadow: none; border: 1px solid #e2e8f0; }
    }
    @media (max-width: 520px) {
      .grille-details { grid-template-columns: 1fr; }
      .recherche-ligne { flex-direction: column; }
      .nom-eleve { font-size: 24px; }
      .entete-resultat, .grille-details { padding: 20px; }
      .section-code, .message-etablissement { margin-left: 20px; margin-right: 20px; }
      .carte-recherche { padding: 24px 20px; }
    }
  </style>
</head>
<body>

<!-- Barre de navigation -->
<nav class="barre-nav">
  <a href="<?= APP_URL ?>" class="logo-lien">
    <div class="logo-icone">
      <img src="/reussiteplus/assets/img/logo-icon.svg" alt="REUSSITE+">
    </div>
    <div>
      <div class="logo-texte">RÉUSSITE<span>+</span></div>
      <div class="logo-sous">Plateforme EdTech RDC</div>
    </div>
  </a>
  <span class="badge-public">Service public gratuit</span>
</nav>

<!-- Bouton retour -->
  <div style="margin-bottom: 20px;">
    <a href="/reussiteplus/index.php" class="bouton-verifier" style="text-decoration: none;">
      <i data-lucide="arrow-left" style="width:16px;height:16px"></i>
      Retour
    </a>
  </div>

<!-- Conteneur principal -->
<div class="conteneur">

  <!-- Formulaire de recherche -->
  <div class="carte-recherche">
    <div class="recherche-titre">Vérifier l'authenticité d'un certificat</div>
    <div class="recherche-sous">Saisissez le code imprimé sur le certificat — en bas du document, sous le QR code ou la signature de l'établissement.</div>
    <form method="GET" class="recherche-ligne">
      <input type="text" name="code" class="champ-code"
             placeholder="Ex : A1B2-C3D4-E5F6"
             value="<?= e($code) ?>"
             maxlength="20" autocomplete="off" spellcheck="false" required>
      <button type="submit" class="bouton-verifier">
        <i data-lucide="search" style="width:16px;height:16px"></i>
        Vérifier
      </button>
    </form>
  </div>

  <?php if ($code && !$cert): ?>
  <!-- Certificat introuvable -->
  <div class="carte-erreur">
    <div class="erreur-icone">
      <i data-lucide="x-circle" style="width:32px;height:32px"></i>
    </div>
    <div class="erreur-titre">Certificat introuvable</div>
    <div class="erreur-texte">
      Le code <strong style="color:#fca5a5;letter-spacing:2px;font-family:monospace"><?= e($code) ?></strong>
      ne correspond à aucun certificat dans notre base de données.<br><br>
      Vérifiez que vous avez saisi le code correctement (majuscules, tirets inclus).
      Ce certificat peut également avoir été révoqué par l'établissement émetteur.
    </div>
  </div>

  <?php elseif ($cert): ?>
  <!-- Certificat valide -->
  <div class="carte-resultat">
    <div class="entete-resultat" style="background:linear-gradient(135deg,<?= $tc['couleur'] ?>,<?= $tc['couleur'] ?>cc)">
      <div class="badge-valide">
        <i data-lucide="shield-check" style="width:13px;height:13px"></i>
        Certificat authentique et vérifié
      </div>
      <div class="nom-eleve"><?= e(($cert['eleve_prenom'] ?? '') . ' ' . strtoupper($cert['eleve_nom'] ?? '')) ?></div>
      <div class="email-eleve"><?= e($cert['eleve_email'] ?? '') ?></div>
    </div>

    <div class="grille-details" style="padding-top:28px">
      <div class="detail" style="border-left:3px solid <?= $tc['couleur'] ?>">
        <div class="detail-etiquette">Type de certificat</div>
        <div class="detail-valeur"><?= $tc['label'] ?></div>
      </div>
      <div class="detail">
        <div class="detail-etiquette">Titre</div>
        <div class="detail-valeur"><?= e($cert['titre']) ?></div>
      </div>
      <div class="detail">
        <div class="detail-etiquette">Classe</div>
        <div class="detail-valeur"><?= e($cert['classe_nom'] ?? 'N/A') ?></div>
      </div>
      <div class="detail">
        <div class="detail-etiquette">Établissement</div>
        <div class="detail-valeur"><?= e($cert['ecole_nom'] ?? (($cert['admin_prenom'] ?? '') . ' ' . strtoupper($cert['admin_nom'] ?? ''))) ?></div>
      </div>
      <?php if (!empty($cert['periode'])): ?>
      <div class="detail">
        <div class="detail-etiquette">Période</div>
        <div class="detail-valeur"><?= e($cert['periode']) ?></div>
      </div>
      <?php endif; ?>
      <?php if (!empty($cert['mention'])): ?>
      <div class="detail" style="border-left:3px solid #F59E0B">
        <div class="detail-etiquette">Mention</div>
        <div class="detail-valeur" style="color:#B45309"><?= e($cert['mention']) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($cert['moyenne'] !== null): ?>
      <div class="detail" style="border-left:3px solid #059669">
        <div class="detail-etiquette">Moyenne générale</div>
        <div class="detail-valeur" style="color:#059669"><?= number_format($cert['moyenne'], 2) ?> / 20</div>
      </div>
      <?php endif; ?>
      <div class="detail">
        <div class="detail-etiquette">Date d'émission</div>
        <div class="detail-valeur"><?= date('d/m/Y', strtotime($cert['emis_le'])) ?></div>
      </div>
    </div>

    <?php if (!empty($cert['message_perso'])): ?>
    <div class="message-etablissement" style="border-left:4px solid <?= $tc['couleur'] ?>">
      <div class="detail-etiquette">Message de l'établissement</div>
      <p><?= nl2br(e($cert['message_perso'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="section-code">
      <div>
        <div class="code-etiquette">Code de vérification authentifié</div>
        <div class="code-valeur"><?= e($cert['code_verif']) ?></div>
      </div>
      <button class="bouton-copier"
        onclick="navigator.clipboard.writeText(this.dataset.url).then(()=>{this.textContent='Copie !';setTimeout(()=>this.textContent='Copier',2000)})"
        data-url="<?= APP_URL ?>/verifier_certificat.php?code=<?= urlencode($cert['code_verif']) ?>">
        Copier
      </button>
    </div>
  </div>

  <?php else: ?>
  <!-- Etat initial -->
  <div class="etat-vide">
    <div class="vide-icone">
      <i data-lucide="shield" style="width:36px;height:36px"></i>
    </div>
    <div class="vide-titre">Saisissez un code pour commencer</div>
    <div class="vide-texte">Le code de vérification se trouve en bas du certificat imprimé, sous le QR code ou la signature de l'établissement.</div>
  </div>
  <?php endif; ?>

  <div class="pied-page">
    Ce service de vérification est fourni gratuitement par <a href="<?= APP_URL ?>">RÉUSSITE+</a> · République Démocratique du Congo<br>
    Les certificats émis via notre plateforme sont cryptographiquement traçables et horodatés.
  </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>
