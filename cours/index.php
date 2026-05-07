<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Cours & Leçons';
$pageActive = 'cours';
$user = require_login();

// Cours réservés aux plans payants
if ($user['plan'] === 'GRATUIT') {
    redirect('/reussiteplus/tarifs.php', 'warning', 'Les cours sont disponibles à partir du plan Basique. Passez à un abonnement pour y accéder.');
}

// Charger la structure JSON (matière > leçon > fichiers)
$structure = @json_decode(@file_get_contents(__DIR__ . '/structure.json'), true);
$cours = [];
$coursRaw = []; // Garder les données complètes (id, niveau, etc.)
if ($structure && isset($structure['matieres'])) {
    foreach ($structure['matieres'] as $mat) {
        $cours[$mat['nom']] = $mat['lecons'];
        $coursRaw[$mat['nom']] = $mat;
    }
}

// Filtre matière active
$matiereActive = $_GET['matiere'] ?? (count($cours) ? array_key_first($cours) : '');

include __DIR__ . '/../includes/header_app.php';
?>

<!-- En-tête page -->
<div class="page-header" style="margin-bottom:24px">
  <div>
    <h1 style="font-family:var(--font-display);font-size:24px;font-weight:800;color:var(--gris-900);margin:0 0 4px">
      <i data-lucide="book-open" style="width:24px;height:24px;vertical-align:middle;margin-right:8px;color:var(--primary)"></i>
      Cours &amp; Leçons
    </h1>
    <p style="color:var(--gris-600);font-size:14px;margin:0">Navigue par matière et accède aux leçons disponibles</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:240px 1fr;gap:24px;align-items:start">

  <!-- Sidebar matières -->
  <aside class="card" style="padding:0;overflow:hidden;position:sticky;top:88px">
    <div style="padding:14px 16px;border-bottom:1px solid var(--gris-200);background:var(--gris-50)">
      <span style="font-family:var(--font-display);font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--gris-500)">Matières</span>
    </div>
    <nav style="padding:8px">
      <?php if ($cours): ?>
        <?php foreach (array_keys($cours) as $nom): ?>
          <?php $isActive = ($nom === $matiereActive); ?>
          <a href="?matiere=<?= urlencode($nom) ?>"
             style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius-sm);text-decoration:none;font-size:14px;font-weight:<?= $isActive ? '600' : '400' ?>;color:<?= $isActive ? 'var(--primary)' : 'var(--gris-700)' ?>;background:<?= $isActive ? 'var(--primary-subtle)' : 'transparent' ?>;transition:var(--transition)">
            <i data-lucide="book" style="width:15px;height:15px;flex-shrink:0"></i>
            <?= e($nom) ?>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="padding:12px;font-size:13px;color:var(--gris-500)">Aucune matière disponible.</p>
      <?php endif; ?>
    </nav>
  </aside>

  <!-- Contenu leçons -->
  <div>
    <?php if ($matiereActive && isset($cours[$matiereActive])): ?>
      <?php $matRaw = $coursRaw[$matiereActive] ?? []; ?>
      <div class="card" style="margin-bottom:20px;padding:18px 20px;display:flex;align-items:center;gap:12px">
        <div style="width:44px;height:44px;border-radius:var(--radius);background:var(--primary-subtle);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i data-lucide="<?= e($matRaw['icone'] ?? 'book') ?>" style="width:22px;height:22px;color:var(--primary)"></i>
        </div>
        <div>
          <h2 style="font-family:var(--font-display);font-size:18px;font-weight:700;margin:0 0 2px;color:var(--gris-900)"><?= e($matiereActive) ?></h2>
          <p style="margin:0;font-size:13px;color:var(--gris-500)"><?= count($cours[$matiereActive]) ?> leçon<?= count($cours[$matiereActive]) > 1 ? 's' : '' ?> · Clique sur une leçon pour commencer</p>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach ($cours[$matiereActive] as $i => $lecon): ?>
          <?php $leconUrl = '/reussiteplus/cours/lecon.php?m=' . urlencode($matiereActive) . '&id=' . urlencode($lecon['id'] ?? ''); ?>
          <a href="<?= $leconUrl ?>" style="display:block;text-decoration:none" class="lecon-card">
            <div class="card" style="padding:0;overflow:hidden;transition:var(--transition);border:1.5px solid var(--gris-200)"
                 onmouseover="this.style.borderColor='var(--primary)';this.style.boxShadow='var(--shadow)'"
                 onmouseout="this.style.borderColor='var(--gris-200)';this.style.boxShadow='var(--shadow-sm)'">
              <div style="padding:16px 18px;display:flex;align-items:center;gap:14px">
                <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-subtle);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-family:var(--font-display);font-size:13px;font-weight:800;color:var(--primary)"><?= $i + 1 ?></div>
                <div style="flex:1;min-width:0">
                  <div style="font-weight:600;font-size:15px;color:var(--gris-900);margin-bottom:4px"><?= e($lecon['titre']) ?></div>
                  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <?php if (!empty($lecon['niveau'])): ?>
                      <span style="font-size:12px;color:var(--gris-500)"><i data-lucide="graduation-cap" style="width:11px;height:11px;vertical-align:middle"></i> <?= e($lecon['niveau']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($lecon['duree'])): ?>
                      <span style="font-size:12px;color:var(--gris-500)"><i data-lucide="clock" style="width:11px;height:11px;vertical-align:middle"></i> <?= e($lecon['duree']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($lecon['qcm'])): ?>
                      <span style="font-size:12px;color:var(--primary)"><i data-lucide="brain" style="width:11px;height:11px;vertical-align:middle"></i> <?= count($lecon['qcm']) ?> questions</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                  <span style="padding:4px 10px;background:var(--primary);color:#fff;border-radius:20px;font-size:12px;font-weight:600">Voir la leçon</span>
                  <i data-lucide="arrow-right" style="width:16px;height:16px;color:var(--gris-400)"></i>
                </div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

    <?php elseif (!$cours): ?>
      <div class="card" style="text-align:center;padding:48px 24px">
        <i data-lucide="book-x" style="width:48px;height:48px;color:var(--gris-300);margin-bottom:12px"></i>
        <p style="color:var(--gris-500);margin:0">Aucun cours disponible pour le moment.</p>
      </div>
    <?php else: ?>
      <div class="card" style="text-align:center;padding:48px 24px">
        <i data-lucide="mouse-pointer-click" style="width:48px;height:48px;color:var(--gris-300);margin-bottom:12px"></i>
        <p style="color:var(--gris-500);margin:0">Sélectionne une matière pour voir les leçons.</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
