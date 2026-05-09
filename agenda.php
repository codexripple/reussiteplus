<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Mon Agenda — Aujourd\'hui';
$pageActive = 'agenda';
$user       = require_login();

if ($user['plan'] === 'GRATUIT') {
    redirect('/reussiteplus/tarifs.php', 'warning', 'L\'agenda personnalisé est disponible à partir du plan Basique.');
}

$today     = date('Y-m-d');
$userId    = $user['id'];
$scoreM    = (float)($user['score_moyen'] ?? 0);

// ── Générer l'agenda du jour si absent ────────────────────────
$agendaExist = dbRow("SELECT id FROM agenda_quotidien WHERE user_id=? AND date_jour=? LIMIT 1", [$userId, $today]);
if (!$agendaExist) {
    genererAgenda($userId, $today, $user);
}

// ── Charger l'agenda du jour ──────────────────────────────────
$agendaItems = dbAll(
    "SELECT aq.*, m.nom as matiere_nom, m.couleur as matiere_couleur,
            qb.enonce, qb.difficulte,
            ar.reponse_texte, ar.option_choisie, ar.est_correcte
     FROM agenda_quotidien aq
     LEFT JOIN matieres m ON m.id = aq.matiere_id
     LEFT JOIN question_bank qb ON qb.id = aq.question_id
     LEFT JOIN agenda_reponses ar ON ar.agenda_id = aq.id AND ar.user_id = ?
     WHERE aq.user_id = ? AND aq.date_jour = ?
     ORDER BY aq.ordre ASC",
    [$userId, $userId, $today]
) ?? [];

// ── Devoirs en cours ──────────────────────────────────────────
$devoirsEnCours = dbAll(
    "SELECT d.*, c.nom as classe_nom,
            s.id as soumission_id, s.statut as soumis_statut
     FROM devoirs_ecole d
     JOIN classe_membres cm ON cm.classe_id=d.classe_id AND cm.eleve_id=? AND cm.statut='ACTIF'
     JOIN classes_ecole c ON c.id=d.classe_id
     LEFT JOIN soumissions_devoirs s ON s.devoir_id=d.id AND s.eleve_id=?
     WHERE d.actif=1 AND (s.id IS NULL OR s.statut != 'CORRIGE')
     ORDER BY d.date_limite ASC LIMIT 5",
    [$userId, $userId]
) ?? [];

// ── Stats du jour ─────────────────────────────────────────────
$faitAujourdhui    = count(array_filter($agendaItems, fn($a) => $a['statut'] === 'FAIT'));
$totalAujourdhui   = count($agendaItems);
$exercices         = array_filter($agendaItems, fn($a) => $a['type'] === 'EXERCICE');
$cours             = array_filter($agendaItems, fn($a) => $a['type'] === 'COURS');
$revisions         = array_filter($agendaItems, fn($a) => $a['type'] === 'REVISION');
$progressionJour   = $totalAujourdhui > 0 ? round($faitAujourdhui / $totalAujourdhui * 100) : 0;

// Options QCM pour les exercices
$optionsMap = [];
foreach ($exercices as $ex) {
    if ($ex['question_id']) {
        $opts = dbAll(
            "SELECT lettre, texte, est_correcte FROM question_options WHERE question_id=? ORDER BY lettre",
            [$ex['question_id']]
        ) ?? [];
        $optionsMap[$ex['id']] = $opts;
    }
}

// ── Jours et date ─────────────────────────────────────────────
$jours  = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
$mois   = ['January'=>'janvier','February'=>'février','March'=>'mars','April'=>'avril','May'=>'mai','June'=>'juin','July'=>'juillet','August'=>'août','September'=>'septembre','October'=>'octobre','November'=>'novembre','December'=>'décembre'];
$dateLabel = ($jours[date('l')] ?? date('l')) . ' ' . date('j') . ' ' . ($mois[date('F')] ?? date('F')) . ' ' . date('Y');

include __DIR__ . '/includes/header_app.php';

// ── Fonction génération agenda ────────────────────────────────
function genererAgenda(string $userId, string $today, array $user): void {
    $ordre = 0;

    // 1. Cours du jour — matières prioritaires (les plus faibles)
    $matièresFaibles = dbAll(
        "SELECT m.id, m.nom, m.couleur, up.score_moyen
         FROM user_progression up JOIN matieres m ON m.id=up.matiere_id
         WHERE up.user_id=? ORDER BY up.score_moyen ASC LIMIT 3",
        [$userId]
    ) ?? [];

    if (empty($matièresFaibles)) {
        $matièresFaibles = dbAll("SELECT id, nom, couleur FROM matieres WHERE actif=1 LIMIT 3") ?? [];
    }

    foreach ($matièresFaibles as $mat) {
        dbQuery(
            "INSERT IGNORE INTO agenda_quotidien (user_id, date_jour, type, titre, contenu, matiere_id, ordre)
             VALUES (?,?,?,?,?,?,?)",
            [$userId, $today, 'COURS', 'Cours du jour — ' . $mat['nom'],
             'Révisez les notions clés de ' . $mat['nom'] . ' avec votre professeur IA.',
             $mat['id'], $ordre++]
        );
    }

    // 2. Exercices du jour — questions aléatoires des matières faibles
    foreach (array_slice($matièresFaibles, 0, 2) as $mat) {
        $questions = dbAll(
            "SELECT id, enonce, difficulte FROM question_bank
             WHERE matiere_id=? AND status='PUBLIE' AND type_question='QCM'
             ORDER BY RAND() LIMIT 3",
            [$mat['id']]
        ) ?? [];

        foreach ($questions as $q) {
            dbQuery(
                "INSERT IGNORE INTO agenda_quotidien (user_id, date_jour, type, titre, matiere_id, question_id, ordre)
                 VALUES (?,?,?,?,?,?,?)",
                [$userId, $today, 'EXERCICE', $mat['nom'] . ' — Exercice du jour',
                 $mat['id'], $q['id'], $ordre++]
            );
        }
    }

    // 3. Révisions recommandées
    $matiereRevision = $matièresFaibles[0] ?? null;
    if ($matiereRevision) {
        dbQuery(
            "INSERT IGNORE INTO agenda_quotidien (user_id, date_jour, type, titre, contenu, matiere_id, ordre)
             VALUES (?,?,?,?,?,?,?)",
            [$userId, $today, 'REVISION',
             'Révision — ' . $matiereRevision['nom'],
             'Passez un examen blanc sur ' . $matiereRevision['nom'] . ' pour renforcer vos acquis.',
             $matiereRevision['id'], $ordre++]
        );
    }
}
?>

<style>
/* ── Agenda premium ── */
.agenda-hero {
  background: linear-gradient(135deg, #0a1628 0%, #003D2E 100%);
  border-radius: 18px; padding: 26px 28px; margin-bottom: 22px;
  display: flex; align-items: center; justify-content: space-between;
  gap: 20px; flex-wrap: wrap; position: relative; overflow: hidden;
}
.agenda-hero::before {
  content: ''; position: absolute; top: -40px; right: -40px;
  width: 200px; height: 200px; border-radius: 50%;
  background: radial-gradient(circle, rgba(0,122,94,.15) 0%, transparent 70%);
}
.agenda-grid { display: grid; grid-template-columns: 1fr 320px; gap: 20px; }
@media(max-width:900px){ .agenda-grid{ grid-template-columns:1fr; } }
.agenda-section { margin-bottom: 20px; }
.agenda-section-hd {
  display: flex; align-items: center; gap: 10px; margin-bottom: 12px;
}
.agenda-section-icon {
  width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.agenda-section-title { font-size: 14px; font-weight: 800; color: var(--gris-900); letter-spacing: -.2px; }
.agenda-section-line { flex: 1; height: 1px; background: var(--gris-200); }

/* Item agenda */
.agenda-item {
  background: var(--blanc); border: 1px solid var(--gris-200);
  border-radius: 12px; padding: 14px 16px; margin-bottom: 10px;
  transition: box-shadow .2s, transform .2s; position: relative;
}
.agenda-item:hover { box-shadow: 0 4px 14px rgba(0,0,0,.07); transform: translateY(-1px); }
.agenda-item.fait { opacity: .65; }
.agenda-item.fait .agenda-item-title { text-decoration: line-through; }
.agenda-item-top { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.agenda-type-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.agenda-item-title { font-size: 14px; font-weight: 700; color: var(--gris-900); flex: 1; }
.agenda-item-badge {
  font-size: 9.5px; font-weight: 700; padding: 2px 9px; border-radius: 20px;
  text-transform: uppercase; letter-spacing: .4px; flex-shrink: 0;
}
.agenda-item-enonce { font-size: 13.5px; color: var(--gris-800); line-height: 1.55; margin-bottom: 10px; }
.agenda-options { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
.agenda-option {
  display: flex; align-items: center; gap: 10px; padding: 8px 12px;
  border: 1.5px solid var(--gris-200); border-radius: 9px; cursor: pointer;
  transition: all .15s; font-size: 13px; color: var(--gris-800); background: var(--blanc);
  text-align: left; width: 100%; font-family: inherit;
}
.agenda-option:hover { border-color: var(--primary); background: var(--primary-subtle); color: var(--primary-dark); }
.agenda-option.correct { border-color: #007A5E; background: #E8F5F1; color: #005A45; }
.agenda-option.wrong   { border-color: #C9342A; background: #FEF0EF; color: #C9342A; }
.agenda-option-letter {
  width: 24px; height: 24px; border-radius: 6px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 800;
  background: var(--gris-100); color: var(--gris-700);
}
.agenda-option.correct .agenda-option-letter { background: #007A5E; color: #fff; }
.agenda-option.wrong   .agenda-option-letter { background: #C9342A; color: #fff; }
.agenda-check-btn {
  background: var(--primary); color: #fff; border: none; border-radius: 8px;
  padding: 7px 16px; font-size: 12.5px; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; gap: 5px; font-family: inherit;
  transition: background .15s;
}
.agenda-check-btn:hover { background: var(--primary-dark); }
.agenda-action-btn {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--primary-subtle); color: var(--primary-dark);
  border: 1px solid rgba(0,122,94,.2); border-radius: 8px;
  padding: 7px 14px; font-size: 12.5px; font-weight: 600;
  cursor: pointer; text-decoration: none; transition: .15s; font-family: inherit;
}
.agenda-action-btn:hover { background: rgba(0,122,94,.15); }

/* Sidebar agenda */
.sidebar-card { background: var(--blanc); border: 1px solid var(--gris-200); border-radius: 14px; overflow: hidden; margin-bottom: 16px; }
.sidebar-card-hd { padding: 13px 16px; border-bottom: 1px solid var(--gris-100); display: flex; align-items: center; gap: 8px; }
.sidebar-card-hd-title { font-size: 13px; font-weight: 700; color: var(--gris-900); }
.sidebar-card-body { padding: 12px 16px; }

/* Progress ring */
.prog-ring { position: relative; display: inline-flex; }
.prog-ring-val { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }

/* Devoir chip */
.devoir-chip {
  display: flex; align-items: center; gap: 10px; padding: 9px 12px;
  border-radius: 9px; margin-bottom: 7px; border: 1px solid var(--gris-200);
  transition: .15s;
}
.devoir-chip:hover { border-color: var(--primary); background: var(--primary-subtle); }
.devoir-chip-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
</style>

<!-- Hero agenda -->
<div class="agenda-hero">
  <div style="position:relative;z-index:1">
    <div style="font-size:11px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px"><?= $dateLabel ?></div>
    <div style="font-size:22px;font-weight:900;color:#fff;margin-bottom:4px">Bonjour, <?= $prenom ?></div>
    <div style="font-size:13px;color:rgba(255,255,255,.55)">
      <?= $totalAujourdhui > 0
          ? "{$faitAujourdhui}/{$totalAujourdhui} tâches accomplies aujourd'hui"
          : "Votre programme est prêt" ?>
    </div>
  </div>
  <div style="position:relative;z-index:1;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <!-- Progression ring -->
    <div class="prog-ring">
      <svg width="72" height="72" viewBox="0 0 72 72">
        <circle cx="36" cy="36" r="28" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="6"/>
        <circle cx="36" cy="36" r="28" fill="none" stroke="#6EE7B7" stroke-width="6"
          stroke-dasharray="<?= round(2*M_PI*28) ?>" stroke-dashoffset="<?= round(2*M_PI*28*(1-$progressionJour/100)) ?>"
          stroke-linecap="round" transform="rotate(-90 36 36)"/>
      </svg>
      <div class="prog-ring-val">
        <div style="font-size:14px;font-weight:900;color:#fff"><?= $progressionJour ?>%</div>
        <div style="font-size:8.5px;color:rgba(255,255,255,.45);text-transform:uppercase">Fait</div>
      </div>
    </div>
    <div>
      <div style="font-size:11px;color:rgba(255,255,255,.4);margin-bottom:5px">Score global</div>
      <div style="font-size:20px;font-weight:900;color:<?= $scoreM>=70?'#6EE7B7':($scoreM>=50?'#FCD34D':'#FCA5A5') ?>"><?= $scoreM ?>%</div>
    </div>
    <a href="?regenerer=1" style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.75);border-radius:9px;padding:7px 14px;font-size:12px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:.15s" onmouseover="this.style.background='rgba(255,255,255,.18)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
      Régénérer
    </a>
  </div>
</div>

<?php if (isset($_GET['regenerer'])):
    dbQuery("DELETE FROM agenda_quotidien WHERE user_id=? AND date_jour=?", [$userId, $today]);
    redirect('/reussiteplus/agenda.php', 'success', 'Agenda régénéré.');
endif; ?>

<div class="agenda-grid">
  <!-- Colonne principale -->
  <div>

    <?php /* ── COURS DU JOUR ── */ if (!empty($cours)): ?>
    <div class="agenda-section">
      <div class="agenda-section-hd">
        <div class="agenda-section-icon" style="background:#E8F5F1">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5" stroke-linecap="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        </div>
        <div class="agenda-section-title">Cours du jour</div>
        <div class="agenda-section-line"></div>
        <span style="font-size:11px;color:var(--gris-400)"><?= count($cours) ?> matière<?= count($cours)>1?'s':'' ?></span>
      </div>
      <?php foreach ($cours as $item): ?>
      <div class="agenda-item <?= $item['statut']==='FAIT'?'fait':'' ?>">
        <div class="agenda-item-top">
          <div class="agenda-type-dot" style="background:<?= $item['matiere_couleur'] ?? '#007A5E' ?>"></div>
          <div class="agenda-item-title"><?= e($item['titre']) ?></div>
          <span class="agenda-item-badge" style="background:<?= $item['matiere_couleur'] ?? '#007A5E' ?>18;color:<?= $item['matiere_couleur'] ?? '#007A5E' ?>"><?= e($item['matiere_nom'] ?? '') ?></span>
        </div>
        <?php if ($item['contenu']): ?>
        <div style="font-size:13px;color:var(--gris-600);margin-bottom:10px;line-height:1.5"><?= e($item['contenu']) ?></div>
        <?php endif; ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <a href="/reussiteplus/ia.php" class="agenda-action-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Cours avec le Prof IA
          </a>
          <a href="/reussiteplus/examen.php?matiere=<?= urlencode($item['matiere_id'] ?? '') ?>" class="agenda-action-btn" style="background:var(--bleu-light);color:var(--bleu);border-color:rgba(30,95,173,.2)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Quiz rapide
          </a>
          <?php if ($item['statut'] !== 'FAIT'): ?>
          <form method="POST" action="/reussiteplus/api/agenda_statut.php" style="margin:0">
            <input type="hidden" name="agenda_id" value="<?= e($item['id']) ?>">
            <input type="hidden" name="statut" value="FAIT">
            <?= csrf_field() ?>
            <button type="submit" class="agenda-action-btn" style="background:var(--gris-100);color:var(--gris-700);border-color:var(--gris-200)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              Marquer fait
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php /* ── EXERCICES DU JOUR ── */ if (!empty($exercices)): ?>
    <div class="agenda-section">
      <div class="agenda-section-hd">
        <div class="agenda-section-icon" style="background:#EEF4FD">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1E5FAD" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="agenda-section-title">Exercices du jour</div>
        <div class="agenda-section-line"></div>
        <span style="font-size:11px;color:var(--gris-400)"><?= count($exercices) ?> exercice<?= count($exercices)>1?'s':'' ?></span>
      </div>
      <?php foreach ($exercices as $ex): ?>
      <div class="agenda-item <?= $ex['statut']==='FAIT'?'fait':'' ?>" id="ex-<?= e($ex['id']) ?>">
        <div class="agenda-item-top">
          <div class="agenda-type-dot" style="background:#1E5FAD"></div>
          <div class="agenda-item-title"><?= e($ex['titre']) ?></div>
          <?php if ($ex['difficulte']): ?>
          <span class="agenda-item-badge" style="background:#EEF4FD;color:#1E5FAD"><?= e($ex['difficulte']) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($ex['enonce']): ?>
        <div class="agenda-item-enonce"><?= nl2br(e($ex['enonce'])) ?></div>
        <?php endif; ?>
        <?php if ($ex['question_id'] && isset($optionsMap[$ex['id']])): ?>
        <div class="agenda-options" id="opts-<?= e($ex['id']) ?>">
          <?php foreach ($optionsMap[$ex['id']] as $opt): ?>
          <button type="button" class="agenda-option <?= $ex['option_choisie']!=null?($opt['lettre']===$ex['option_choisie']?($ex['est_correcte']?'correct':'wrong'):($opt['est_correcte']&&$ex['est_correcte']===0?'correct':'')):''; ?>"
            onclick="repondreExercice('<?= e($ex['id']) ?>','<?= e($opt['lettre']) ?>','<?= e($ex['question_id']) ?>')"
            <?= $ex['est_correcte']!==null?'disabled':'' ?>>
            <span class="agenda-option-letter"><?= e($opt['lettre']) ?></span>
            <span><?= e($opt['texte']) ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <?php if ($ex['est_correcte'] !== null): ?>
        <div style="padding:8px 12px;border-radius:8px;font-size:12.5px;font-weight:600;<?= $ex['est_correcte'] ? 'background:#E8F5F1;color:#005A45' : 'background:#FEF0EF;color:#C9342A' ?>">
          <?= $ex['est_correcte'] ? 'Bonne réponse !' : 'Réponse incorrecte — continuez à pratiquer.' ?>
        </div>
        <?php endif; ?>
        <?php elseif (!$ex['question_id']): ?>
        <a href="/reussiteplus/examen.php?matiere=<?= urlencode($ex['matiere_id'] ?? '') ?>" class="agenda-check-btn">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/></svg>
          Commencer l'exercice
        </a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php /* ── RÉVISIONS ── */ if (!empty($revisions)): ?>
    <div class="agenda-section">
      <div class="agenda-section-hd">
        <div class="agenda-section-icon" style="background:#FEF3C7">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C9972A" stroke-width="2.5" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        </div>
        <div class="agenda-section-title">Révisions recommandées</div>
        <div class="agenda-section-line"></div>
      </div>
      <?php foreach ($revisions as $rev): ?>
      <div class="agenda-item">
        <div class="agenda-item-top">
          <div class="agenda-type-dot" style="background:#C9972A"></div>
          <div class="agenda-item-title"><?= e($rev['titre']) ?></div>
        </div>
        <?php if ($rev['contenu']): ?>
        <div style="font-size:13px;color:var(--gris-600);margin-bottom:10px"><?= e($rev['contenu']) ?></div>
        <?php endif; ?>
        <a href="/reussiteplus/progression.php" class="agenda-action-btn" style="background:#FEF3C7;color:#92400E;border-color:rgba(201,151,42,.25)">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
          Voir ma progression
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>

  <!-- Sidebar -->
  <div>

    <!-- Devoirs en cours -->
    <div class="sidebar-card">
      <div class="sidebar-card-hd">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#007A5E" stroke-width="2.5" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <div class="sidebar-card-hd-title">Devoirs en cours</div>
        <a href="/reussiteplus/mes_devoirs.php" style="font-size:11px;color:var(--primary);text-decoration:none;margin-left:auto">Voir tout</a>
      </div>
      <div class="sidebar-card-body">
        <?php if ($devoirsEnCours): ?>
        <?php foreach ($devoirsEnCours as $dv):
          $isLate    = $dv['date_limite'] && strtotime($dv['date_limite']) < time();
          $daysLeft  = $dv['date_limite'] ? (int)ceil((strtotime($dv['date_limite']) - time()) / 86400) : null;
        ?>
        <a href="/reussiteplus/mes_devoirs.php" class="devoir-chip" style="text-decoration:none">
          <div class="devoir-chip-dot" style="background:<?= $isLate?'#C9342A':($daysLeft!==null&&$daysLeft<=2?'#C9972A':'#007A5E') ?>"></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:12.5px;font-weight:600;color:var(--gris-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($dv['titre']) ?></div>
            <div style="font-size:11px;color:var(--gris-500)"><?= e($dv['classe_nom']) ?></div>
          </div>
          <div style="font-size:10.5px;font-weight:700;color:<?= $isLate?'#C9342A':($daysLeft!==null&&$daysLeft<=2?'#C9972A':'#6B7280') ?>;flex-shrink:0">
            <?= $isLate ? 'Expiré' : ($daysLeft === 0 ? 'Auj.' : ($daysLeft !== null ? 'J-'.$daysLeft : '—')) ?>
          </div>
        </a>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;padding:18px;font-size:12.5px;color:var(--gris-400)">Aucun devoir en cours.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Accès rapides -->
    <div class="sidebar-card">
      <div class="sidebar-card-hd">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1E5FAD" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <div class="sidebar-card-hd-title">Accès rapides</div>
      </div>
      <div class="sidebar-card-body" style="display:flex;flex-direction:column;gap:7px">
        <?php foreach ([
          ['/reussiteplus/examen.php',       'Passer un examen blanc',   '#E8F5F1', '#007A5E', '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'],
          ['/reussiteplus/ia.php',            'Chat avec le Prof IA',     '#EDE9FE', '#7C3AED', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
          ['/reussiteplus/progression.php',   'Ma progression',           '#FEF3C7', '#C9972A', '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>'],
          ['/reussiteplus/archives.php',      'Archives officielles',     '#EEF4FD', '#1E5FAD', '<path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/>'],
        ] as [$url, $label, $bg, $color, $iconPath]): ?>
        <a href="<?= $url ?>" style="display:flex;align-items:center;gap:10px;padding:9px 11px;background:<?= $bg ?>;border-radius:9px;text-decoration:none;transition:.15s;font-size:12.5px;font-weight:600;color:<?= $color ?>" onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2.5" stroke-linecap="round"><?= $iconPath ?></svg>
          <?= $label ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<script>
const CSRF = '<?= e(csrf_token()) ?>';

async function repondreExercice(agendaId, lettre, questionId) {
  const resp = await fetch('/reussiteplus/api/agenda_repondre.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ agenda_id: agendaId, lettre, question_id: questionId, csrf_token: CSRF }),
  });
  const data = await resp.json();
  if (data.ok) {
    const optsDiv = document.getElementById('opts-' + agendaId);
    if (optsDiv) {
      optsDiv.querySelectorAll('.agenda-option').forEach(btn => {
        btn.disabled = true;
        const l = btn.querySelector('.agenda-option-letter');
        if (l && l.textContent.trim() === lettre) {
          btn.classList.add(data.correct ? 'correct' : 'wrong');
          if (data.correct) l.style.background = '#007A5E';
          else l.style.background = '#C9342A';
        }
        if (data.bonne_reponse && l && l.textContent.trim() === data.bonne_reponse && !data.correct) {
          btn.classList.add('correct');
        }
      });
      const feedback = document.createElement('div');
      feedback.style.cssText = 'padding:8px 12px;border-radius:8px;font-size:12.5px;font-weight:600;margin-top:8px;' + (data.correct ? 'background:#E8F5F1;color:#005A45' : 'background:#FEF0EF;color:#C9342A');
      feedback.textContent = data.correct ? 'Bonne réponse !' : 'Réponse incorrecte — continuez à pratiquer.';
      optsDiv.after(feedback);
    }
  }
}
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
