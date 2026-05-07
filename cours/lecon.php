<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();

// Paramètres
$matiereParam = $_GET['m'] ?? '';
$leconId      = $_GET['id'] ?? '';

// Charger la structure
$structure = @json_decode(@file_get_contents(__DIR__ . '/structure.json'), true);
if (!$structure) { header('Location: /reussiteplus/cours/index.php'); exit; }

// Trouver la matière et la leçon
$matiereData = null;
$leconData   = null;
$leconIndex  = 0;
$leconPrev   = null;
$leconNext   = null;

foreach ($structure['matieres'] as $mat) {
    if ($mat['nom'] === $matiereParam) {
        $matiereData = $mat;
        foreach ($mat['lecons'] as $i => $l) {
            if ($l['id'] === $leconId) {
                $leconData  = $l;
                $leconIndex = $i;
                $leconPrev  = $i > 0 ? $mat['lecons'][$i - 1] : null;
                $leconNext  = $i < count($mat['lecons']) - 1 ? $mat['lecons'][$i + 1] : null;
            }
        }
    }
}

if (!$leconData) { header('Location: /reussiteplus/cours/index.php?matiere=' . urlencode($matiereParam)); exit; }

$pageTitle  = e($leconData['titre']) . ' — ' . e($matiereParam);
$pageActive = 'cours';

include __DIR__ . '/../includes/header_app.php';
?>

<!-- Fil d'Ariane -->
<nav style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gris-500);margin-bottom:20px;flex-wrap:wrap">
  <a href="/reussiteplus/cours/index.php" style="color:var(--primary);text-decoration:none;font-weight:500">Cours</a>
  <span>›</span>
  <a href="/reussiteplus/cours/index.php?matiere=<?= urlencode($matiereParam) ?>" style="color:var(--primary);text-decoration:none;font-weight:500"><?= e($matiereParam) ?></a>
  <span>›</span>
  <span><?= e($leconData['titre']) ?></span>
</nav>

<div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start">

  <!-- Contenu principal -->
  <div>

    <!-- En-tête leçon -->
    <div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%);color:#fff;border:none">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px">
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap">
            <span style="background:rgba(255,255,255,.2);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600"><?= e($matiereParam) ?></span>
            <span style="background:rgba(255,255,255,.15);padding:3px 10px;border-radius:20px;font-size:12px"><?= e($leconData['niveau'] ?? '') ?></span>
            <span style="background:rgba(255,255,255,.15);padding:3px 10px;border-radius:20px;font-size:12px">
              <i data-lucide="clock" style="width:11px;height:11px;vertical-align:middle"></i>
              <?= e($leconData['duree'] ?? '') ?>
            </span>
          </div>
          <h1 style="font-family:var(--font-display);font-size:22px;font-weight:800;margin:0 0 8px;color:#fff"><?= e($leconData['titre']) ?></h1>
          <div style="font-size:13px;color:rgba(255,255,255,.8)">
            Leçon <?= $leconIndex + 1 ?> sur <?= count($matiereData['lecons']) ?>
          </div>
        </div>
        <div style="width:56px;height:56px;background:rgba(255,255,255,.15);border-radius:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <i data-lucide="book-open" style="width:28px;height:28px;color:#fff"></i>
        </div>
      </div>
    </div>

    <!-- Objectifs -->
    <?php if (!empty($leconData['objectifs'])): ?>
    <div class="card" style="margin-bottom:20px;border-left:4px solid var(--gold)">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <i data-lucide="target" style="width:18px;height:18px;color:var(--gold)"></i>
        <strong style="font-family:var(--font-display);font-size:14px;color:var(--gris-800)">Objectifs de la leçon</strong>
      </div>
      <ul style="margin:0;padding-left:0;list-style:none;display:flex;flex-direction:column;gap:6px">
        <?php foreach ($leconData['objectifs'] as $obj): ?>
          <li style="display:flex;align-items:flex-start;gap:8px;font-size:14px;color:var(--gris-700)">
            <span style="width:20px;height:20px;background:var(--gold-light);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px">
              <i data-lucide="check" style="width:11px;height:11px;color:var(--gold)"></i>
            </span>
            <?= e($obj) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- Contenu de la leçon -->
    <div class="card" style="margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--gris-200)">
        <i data-lucide="file-text" style="width:18px;height:18px;color:var(--primary)"></i>
        <strong style="font-family:var(--font-display);font-size:15px;color:var(--gris-800)">Contenu du cours</strong>
      </div>
      <div class="lecon-content" style="line-height:1.8;color:var(--gris-800);font-size:15px">
        <?= $leconData['contenu'] ?? '<p style="color:var(--gris-400)">Contenu en cours de rédaction.</p>' ?>
      </div>
      <?php if (!empty($leconData['exemple'])): ?>
        <div style="margin-top:16px;padding:12px 16px;background:var(--primary-subtle);border-radius:var(--radius);border-left:3px solid var(--primary)">
          <strong style="font-size:13px;color:var(--primary)"><i data-lucide="lightbulb" style="width:14px;height:14px;vertical-align:middle"></i> Exemple :</strong>
          <span style="font-size:14px;color:var(--gris-800);margin-left:6px"><?= e($leconData['exemple']) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <!-- QCM interactif -->
    <?php if (!empty($leconData['qcm'])): ?>
    <div class="card" style="margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--gris-200)">
        <i data-lucide="brain" style="width:18px;height:18px;color:var(--primary)"></i>
        <strong style="font-family:var(--font-display);font-size:15px;color:var(--gris-800)">Questions de vérification</strong>
        <span style="margin-left:auto;font-size:12px;color:var(--gris-500)"><?= count($leconData['qcm']) ?> question<?= count($leconData['qcm']) > 1 ? 's' : '' ?></span>
      </div>

      <div id="qcm-container">
        <?php foreach ($leconData['qcm'] as $qi => $q): ?>
          <div class="qcm-question" id="q-<?= $qi ?>" style="margin-bottom:20px;padding:16px;background:var(--gris-50);border-radius:var(--radius);border:1px solid var(--gris-200)">
            <p style="font-weight:600;font-size:14px;color:var(--gris-900);margin:0 0 12px"><?= ($qi + 1) ?>. <?= e($q['q']) ?></p>
            <div style="display:flex;flex-direction:column;gap:8px">
              <?php foreach ($q['options'] as $oi => $opt): ?>
                <button onclick="checkAnswer(<?= $qi ?>, <?= $oi ?>, <?= $q['rep'] ?>)"
                        id="btn-<?= $qi ?>-<?= $oi ?>"
                        style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:var(--radius-sm);border:1.5px solid var(--gris-200);background:#fff;cursor:pointer;text-align:left;font-size:14px;color:var(--gris-800);transition:var(--transition)">
                  <span style="width:24px;height:24px;border-radius:50%;background:var(--gris-100);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:var(--gris-600);flex-shrink:0"><?= chr(65+$oi) ?></span>
                  <?= e($opt) ?>
                </button>
              <?php endforeach; ?>
            </div>
            <div id="feedback-<?= $qi ?>" style="display:none;margin-top:10px;padding:8px 12px;border-radius:var(--radius-sm);font-size:13px;font-weight:500"></div>
          </div>
        <?php endforeach; ?>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px">
          <div id="score-display" style="font-size:14px;color:var(--gris-600)"></div>
          <button onclick="resetQCM()" style="padding:8px 16px;background:var(--gris-100);border:none;border-radius:var(--radius-sm);cursor:pointer;font-size:13px;color:var(--gris-700)">
            <i data-lucide="rotate-ccw" style="width:13px;height:13px;vertical-align:middle"></i> Recommencer
          </button>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Navigation entre leçons -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px">
      <?php if ($leconPrev): ?>
        <a href="?m=<?= urlencode($matiereParam) ?>&id=<?= e($leconPrev['id']) ?>"
           style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#fff;border:1px solid var(--gris-200);border-radius:var(--radius);text-decoration:none;color:var(--gris-800);transition:var(--transition)"
           onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
           onmouseout="this.style.borderColor='var(--gris-200)';this.style.color='var(--gris-800)'">
          <i data-lucide="arrow-left" style="width:18px;height:18px;flex-shrink:0"></i>
          <div><div style="font-size:11px;color:var(--gris-400);margin-bottom:2px">Leçon précédente</div><div style="font-size:13px;font-weight:600"><?= e($leconPrev['titre']) ?></div></div>
        </a>
      <?php else: ?>
        <div></div>
      <?php endif; ?>

      <?php if ($leconNext): ?>
        <a href="?m=<?= urlencode($matiereParam) ?>&id=<?= e($leconNext['id']) ?>"
           style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 16px;background:var(--primary);border:1px solid var(--primary);border-radius:var(--radius);text-decoration:none;color:#fff;transition:var(--transition)"
           onmouseover="this.style.background='var(--primary-dark)'"
           onmouseout="this.style.background='var(--primary)'">
          <div style="text-align:right"><div style="font-size:11px;color:rgba(255,255,255,.7);margin-bottom:2px">Leçon suivante</div><div style="font-size:13px;font-weight:600"><?= e($leconNext['titre']) ?></div></div>
          <i data-lucide="arrow-right" style="width:18px;height:18px;flex-shrink:0"></i>
        </a>
      <?php else: ?>
        <a href="/reussiteplus/cours/index.php?matiere=<?= urlencode($matiereParam) ?>"
           style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 16px;background:var(--gold);border:1px solid var(--gold);border-radius:var(--radius);text-decoration:none;color:#fff"
           onmouseover="this.style.background='var(--gold-dark)'" onmouseout="this.style.background='var(--gold)'">
          <div style="text-align:right"><div style="font-size:11px;color:rgba(255,255,255,.8);margin-bottom:2px">Terminé !</div><div style="font-size:13px;font-weight:600">Retour aux leçons</div></div>
          <i data-lucide="check-circle" style="width:18px;height:18px;flex-shrink:0"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Sidebar droite -->
  <aside style="position:sticky;top:88px;display:flex;flex-direction:column;gap:16px">

    <!-- Progression dans la matière -->
    <div class="card" style="padding:0;overflow:hidden">
      <div style="padding:12px 16px;background:var(--gris-50);border-bottom:1px solid var(--gris-200)">
        <span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--gris-500)">Plan du cours</span>
      </div>
      <nav style="padding:8px">
        <?php foreach ($matiereData['lecons'] as $i => $l): ?>
          <?php $isActive = ($l['id'] === $leconId); ?>
          <a href="?m=<?= urlencode($matiereParam) ?>&id=<?= e($l['id']) ?>"
             style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:var(--radius-sm);text-decoration:none;color:<?= $isActive ? 'var(--primary)' : 'var(--gris-700)' ?>;background:<?= $isActive ? 'var(--primary-subtle)' : 'transparent' ?>;font-size:13px;font-weight:<?= $isActive ? '600' : '400' ?>;transition:var(--transition)"
             onmouseover="if(!this.classList.contains('active')) this.style.background='var(--gris-100)'"
             onmouseout="if(!this.classList.contains('active')) this.style.background='<?= $isActive ? 'var(--primary-subtle)' : 'transparent' ?>'">
            <span style="width:20px;height:20px;border-radius:50%;background:<?= $isActive ? 'var(--primary)' : 'var(--gris-200)' ?>;color:<?= $isActive ? '#fff' : 'var(--gris-600)' ?>;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0"><?= $i + 1 ?></span>
            <span style="flex:1;line-height:1.3"><?= e($l['titre']) ?></span>
            <?php if ($isActive): ?><i data-lucide="chevron-right" style="width:13px;height:13px;flex-shrink:0"></i><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>

    <!-- Fichiers disponibles -->
    <?php if (!empty($leconData['fichiers'])): ?>
    <div class="card">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <i data-lucide="paperclip" style="width:16px;height:16px;color:var(--primary)"></i>
        <strong style="font-size:13px;color:var(--gris-800)">Ressources</strong>
      </div>
      <?php foreach ($leconData['fichiers'] as $file): ?>
        <?php
          $fp = __DIR__ . '/' . $matiereParam . '/' . $file;
          $exists = file_exists($fp);
          $ext = strtoupper(pathinfo($file, PATHINFO_EXTENSION));
          $fileUrl = '/reussiteplus/cours/fichier.php?m=' . urlencode($matiereParam) . '&f=' . urlencode($file);
        ?>
        <a href="<?= $exists ? $fileUrl : '#' ?>" <?= $exists ? 'target="_blank"' : 'onclick="return false"' ?>
           style="display:flex;align-items:center;gap:10px;padding:8px;border-radius:var(--radius-sm);text-decoration:none;color:<?= $exists ? 'var(--gris-800)' : 'var(--gris-400)' ?>;background:var(--gris-50);margin-bottom:6px;<?= $exists ? '' : 'opacity:.5;cursor:not-allowed' ?>"
           <?= $exists ? 'onmouseover="this.style.background=\'var(--primary-subtle)\';this.style.color=\'var(--primary)\'" onmouseout="this.style.background=\'var(--gris-50)\';this.style.color=\'var(--gris-800)\'"' : '' ?>>
          <i data-lucide="file-text" style="width:15px;height:15px;flex-shrink:0;color:<?= $exists ? '#C9342A' : 'var(--gris-300)' ?>"></i>
          <span style="flex:1;font-size:12px"><?= e($file) ?></span>
          <?php if ($exists): ?>
            <span style="font-size:10px;font-weight:700;color:#fff;background:#C9342A;padding:1px 5px;border-radius:3px"><?= $ext ?></span>
          <?php else: ?>
            <span style="font-size:10px;color:var(--gris-400)">bientôt</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </aside>
</div>

<style>
.lecon-content h3 { font-family:var(--font-display);font-size:17px;color:var(--primary);margin:20px 0 8px;font-weight:700 }
.lecon-content h3:first-child { margin-top:0 }
.lecon-content p { margin:0 0 10px }
.lecon-content ul, .lecon-content ol { padding-left:20px;margin:0 0 10px }
.lecon-content li { margin-bottom:4px }
.lecon-content strong { color:var(--gris-900) }
.lecon-content table { width:100%;border-collapse:collapse;margin:12px 0;font-size:13px }
.lecon-content table th { background:var(--primary);color:#fff;padding:8px;text-align:left }
.lecon-content table td { padding:8px;border:1px solid var(--gris-200) }
.lecon-content table tr:nth-child(even) td { background:var(--gris-50) }
</style>

<script>
const qcmAnswers = {};
const qcmTotal = <?= count($leconData['qcm'] ?? []) ?>;

function checkAnswer(qi, selected, correct) {
  if (qcmAnswers[qi] !== undefined) return; // Déjà répondu
  qcmAnswers[qi] = selected;

  const btns = document.querySelectorAll(`[id^="btn-${qi}-"]`);
  btns.forEach((btn, i) => {
    btn.style.cursor = 'default';
    if (i === correct) {
      btn.style.borderColor = '#007A5E';
      btn.style.background = '#E8F5F1';
      btn.style.color = '#007A5E';
    } else if (i === selected && selected !== correct) {
      btn.style.borderColor = '#C9342A';
      btn.style.background = '#FEF0EF';
      btn.style.color = '#C9342A';
    }
  });

  const fb = document.getElementById(`feedback-${qi}`);
  fb.style.display = 'block';
  if (selected === correct) {
    fb.style.background = '#E8F5F1';
    fb.style.color = '#007A5E';
    fb.innerHTML = '✓ Bonne réponse !';
  } else {
    fb.style.background = '#FEF0EF';
    fb.style.color = '#C9342A';
    fb.innerHTML = `✗ Mauvaise réponse. La bonne réponse est : <strong>${document.getElementById('btn-' + qi + '-' + correct).textContent.trim()}</strong>`;
  }

  updateScore();
}

function updateScore() {
  if (Object.keys(qcmAnswers).length === qcmTotal) {
    const correct = Object.entries(qcmAnswers).filter(([qi, sel]) => {
      const qcm = <?= json_encode($leconData['qcm'] ?? []) ?>;
      return sel === qcm[qi].rep;
    }).length;
    const sd = document.getElementById('score-display');
    const pct = Math.round(correct / qcmTotal * 100);
    sd.innerHTML = `Score : <strong style="color:${pct >= 70 ? '#007A5E' : '#C9342A'}">${correct}/${qcmTotal} (${pct}%)</strong>`;
  }
}

function resetQCM() {
  Object.keys(qcmAnswers).forEach(k => delete qcmAnswers[k]);
  document.getElementById('score-display').innerHTML = '';
  document.querySelectorAll('[id^="btn-"]').forEach(btn => {
    btn.style.borderColor = 'var(--gris-200)';
    btn.style.background = '#fff';
    btn.style.color = 'var(--gris-800)';
    btn.style.cursor = 'pointer';
  });
  document.querySelectorAll('[id^="feedback-"]').forEach(fb => fb.style.display = 'none');
}
</script>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
