<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Examen';
$pageActive = 'examen';
$user = require_login();

// API: Soumettre réponse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if (!csrf_verify()) { echo json_encode(['error' => 'Token invalide']); exit; }

    if ($_POST['action'] === 'submit_session') {
        $sessionId = $_POST['session_id'] ?? '';
        $answers   = $_POST['answers'] ?? [];
        $temps     = (int)($_POST['temps_passe'] ?? 0);

        if (!$sessionId) { echo json_encode(['error' => 'Session invalide']); exit; }

        $session = dbRow("SELECT * FROM exam_sessions WHERE id=? AND user_id=? AND statut='EN_COURS'",
                         [$sessionId, $user['id']]);
        if (!$session) { echo json_encode(['error' => 'Session introuvable']); exit; }

        $score = 0; $scoreMax = 0;
        foreach ($answers as $questionId => $optionId) {
            $q = dbRow("SELECT points FROM question_bank WHERE id=?", [$questionId]);
            if (!$q) continue;
            $scoreMax += (float)$q['points'];

            $opt = dbRow("SELECT est_correcte FROM question_options WHERE id=? AND question_id=?",
                         [$optionId, $questionId]);
            $correct = $opt && $opt['est_correcte'];
            $pts     = $correct ? (float)$q['points'] : 0;
            $score  += $pts;

            // Enregistrer la réponse
            dbInsert('exam_answers', [
                'session_id'     => $sessionId,
                'question_id'    => $questionId,
                'option_id'      => $optionId,
                'est_correcte'   => $correct ? 1 : 0,
                'points_obtenus' => $pts,
            ]);

            // Mettre à jour compteur option
            dbQuery("UPDATE question_options SET selection_count = selection_count + 1 WHERE id=?", [$optionId]);
        }

        $pct = $scoreMax > 0 ? round(($score / $scoreMax) * 100, 2) : 0;

        // Finaliser la session
        dbQuery(
            "UPDATE exam_sessions SET score=?, score_max=?, pourcentage=?, temps_passe=?, statut='TERMINE', finished_at=NOW() WHERE id=?",
            [$score, $scoreMax, $pct, $temps, $sessionId]
        );

        // Mettre à jour stats utilisateur (corrige race condition)
        $uStats = dbRow("SELECT total_examens, total_questions, score_moyen, examens_mois FROM utilisateurs WHERE id=?", [$user['id']]);
        $newTotalExamens = (int)$uStats['total_examens'] + 1;
        $newTotalQuestions = (int)$uStats['total_questions'] + count($answers);
        $newExamensMois = (int)$uStats['examens_mois'] + 1;
        $newScoreMoyen = $newTotalExamens > 0
            ? round((($uStats['score_moyen'] * (int)$uStats['total_examens']) + $pct) / $newTotalExamens, 2)
            : $pct;
        dbQuery(
            "UPDATE utilisateurs SET total_examens=?, total_questions=?, score_moyen=?, examens_mois=? WHERE id=?",
            [$newTotalExamens, $newTotalQuestions, $newScoreMoyen, $newExamensMois, $user['id']]
        );

        // Mettre à jour progression par matière
        if ($session['matiere_id']) {
            $bonnes = array_reduce((array)$answers, function($carry, $optId) use ($session) {
                $o = dbRow("SELECT est_correcte FROM question_options WHERE id=?", [$optId]);
                return $carry + ($o && $o['est_correcte'] ? 1 : 0);
            }, 0);
            $total = count($answers);
            $existing = dbRow("SELECT id, questions_vues, bonnes_reponses FROM user_progression WHERE user_id=? AND matiere_id=?",
                              [$user['id'], $session['matiere_id']]);
            if ($existing) {
                $newVues = $existing['questions_vues'] + $total;
                $newBonnes = $existing['bonnes_reponses'] + $bonnes;
                $newScore = $newVues > 0 ? round(($newBonnes / $newVues) * 100, 2) : 0;
                dbQuery("UPDATE user_progression SET questions_vues=?, bonnes_reponses=?, score_moyen=?, derniere_session=NOW() WHERE id=?",
                        [$newVues, $newBonnes, $newScore, $existing['id']]);
            } else {
                $newScore = $total > 0 ? round(($bonnes / $total) * 100, 2) : 0;
                dbInsert('user_progression', [
                    'user_id'        => $user['id'],
                    'matiere_id'     => $session['matiere_id'],
                    'questions_vues' => $total,
                    'bonnes_reponses' => $bonnes,
                    'score_moyen'    => $newScore,
                    'derniere_session' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        log_activite($user['id'], 1, count($answers));
        refresh_user();

        echo json_encode([
            'ok'         => true,
            'score'      => $score,
            'score_max'  => $scoreMax,
            'pourcentage' => $pct,
            'redirect'   => '/reussiteplus/resultat.php?session=' . $sessionId,
        ]);
        exit;
    }
    exit;
}

// Vérifier la limite du plan gratuit
if ($user['plan'] === 'GRATUIT' && !can_take_exam()) {
    redirect('/reussiteplus/tarifs.php', 'warning', 'Vous avez atteint la limite de ' . FREE_EXAMS_PER_MONTH . ' examens ce mois. Passez à Premium pour continuer.');
}

// Mode configuration ou mode examen
$sessionId  = $_GET['session'] ?? null;
$archiveId  = $_GET['archive'] ?? null;
$matiereId  = $_GET['matiere'] ?? null;
$examType   = $_GET['type'] ?? null;
$nbQ        = max(5, min(50, (int)($_GET['nb'] ?? 10)));

// Charger une session en cours
if ($sessionId) {
    $sessionActive = dbRow(
        "SELECT es.*, m.nom as matiere_nom FROM exam_sessions es
         LEFT JOIN matieres m ON es.matiere_id = m.id
         WHERE es.id=? AND es.user_id=? AND es.statut='EN_COURS'",
        [$sessionId, $user['id']]
    );
    if (!$sessionActive) {
        redirect('/reussiteplus/examen.php', 'error', 'Session introuvable ou déjà terminée.');
    }
    // Charger les questions de cette session
    $questions = dbAll(
        "SELECT qb.*, m.nom as matiere_nom,
                GROUP_CONCAT(qo.id,'|',qo.lettre,'|',qo.texte,'|',qo.est_correcte ORDER BY qo.ordre SEPARATOR '§§') as options_raw
         FROM exam_answers ea
         JOIN question_bank qb ON ea.question_id = qb.id
         LEFT JOIN matieres m ON qb.matiere_id = m.id
         LEFT JOIN question_options qo ON qo.question_id = qb.id
         WHERE ea.session_id = ?
         GROUP BY qb.id",
        [$sessionId]
    );
    // Si la session vient d'être créée (pas de réponses), charger les questions associées
    if (!$questions) {
        $questions = dbAll(
            "SELECT qb.*, m.nom as matiere_nom,
                    GROUP_CONCAT(qo.id,'|',qo.lettre,'|',qo.texte,'|',qo.est_correcte ORDER BY qo.ordre SEPARATOR '§§') as options_raw
             FROM question_bank qb
             LEFT JOIN matieres m ON qb.matiere_id = m.id
             LEFT JOIN question_options qo ON qo.question_id = qb.id
             WHERE qb.matiere_id = ? AND qb.status = 'PUBLIE' AND qb.type_question = 'QCM'
             GROUP BY qb.id
             ORDER BY RAND()
             LIMIT ?",
            [$sessionActive['matiere_id'], $sessionActive['nb_questions']]
        );
    }
    // Parser les options
    foreach ($questions as &$q) {
        $q['options'] = [];
        if ($q['options_raw']) {
            foreach (explode('§§', $q['options_raw']) as $opt) {
                [$oid, $lettre, $texte, $correct] = explode('|', $opt, 4);
                $q['options'][] = ['id' => $oid, 'lettre' => $lettre, 'texte' => $texte, 'est_correcte' => (bool)$correct];
            }
        }
    }
    unset($q);
    $matieres = [];
    include __DIR__ . '/includes/header_app.php';
    // Affichage session
    ?>
    <div id="exam-app" data-session="<?= e($sessionId) ?>" data-total="<?= count($questions) ?>"
         data-temps-limite="<?= (int)($sessionActive['temps_limite'] ?? 3600) ?>">

      <!-- Timer & Progress Header -->
      <div class="card" style="margin-bottom:20px;padding:16px 20px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
          <div>
            <div style="font-family:var(--font-display);font-size:16px;font-weight:700"><?= e($sessionActive['titre'] ?? 'Examen') ?></div>
            <div style="font-size:13px;color:var(--gris-500)"><?= e($sessionActive['matiere_nom'] ?? '') ?> • <?= count($questions) ?> questions</div>
          </div>
          <div style="display:flex;align-items:center;gap:20px">
            <div style="text-align:center">
              <div id="timer" style="font-family:var(--font-display);font-size:24px;font-weight:800;color:var(--primary)">00:00</div>
              <div style="font-size:10px;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px">Temps écoulé</div>
            </div>
            <div style="text-align:center">
              <div id="counter" style="font-family:var(--font-display);font-size:24px;font-weight:800">1/<?= count($questions) ?></div>
              <div style="font-size:10px;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px">Question</div>
            </div>
          </div>
        </div>
        <div class="progress-bar" style="margin-top:12px;height:4px">
          <div class="progress-bar-fill" id="exam-progress" style="width:<?= count($questions) > 0 ? (1/count($questions)*100) : 0 ?>%;transition:width .4s"></div>
        </div>
      </div>

      <!-- Questions -->
      <?php foreach ($questions as $idx => $q): ?>
      <div class="question-slide card" data-idx="<?= $idx ?>"
           style="margin-bottom:20px;<?= $idx > 0 ? 'display:none' : '' ?>">
        <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:20px">
          <div style="background:var(--primary);color:white;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0"><?= $idx + 1 ?></div>
          <div>
            <div style="font-size:15px;color:var(--gris-900);line-height:1.6"><?= nl2br(e($q['enonce'])) ?></div>
            <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap">
              <?= badge_difficulte($q['difficulte']) ?>
              <span class="badge badge-gray"><?= (int)$q['points'] ?> pt<?= $q['points'] > 1 ? 's' : '' ?></span>
              <?php if ($q['temps_suggere']): ?>
              <span class="badge badge-bleu"><i data-lucide="timer" style="width:11px;height:11px;vertical-align:-1px"></i> <?= (int)($q['temps_suggere']/60) ?>min</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if ($q['options']): ?>
        <div class="options-list" data-question-id="<?= e($q['id']) ?>">
          <?php foreach ($q['options'] as $opt): ?>
          <label class="option-item" data-option-id="<?= e($opt['id']) ?>"
                 style="display:flex;align-items:flex-start;gap:12px;padding:14px;border:2px solid var(--gris-200);border-radius:var(--radius);cursor:pointer;transition:all .15s;margin-bottom:8px">
            <span class="option-letter" style="width:28px;height:28px;border-radius:50%;background:var(--gris-100);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0"><?= e($opt['lettre']) ?></span>
            <span style="font-size:14px;color:var(--gris-800);line-height:1.5;padding-top:3px"><?= e($opt['texte']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <!-- Navigation bas -->
      <div class="card" style="display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 20px">
        <button class="btn btn-ghost" id="prevBtn" onclick="prevQuestion()" disabled><i data-lucide="chevron-left" style="width:14px;height:14px;vertical-align:-2px"></i> Précédent</button>

        <div id="question-dots" style="display:flex;gap:6px;flex-wrap:wrap;justify-content:center">
          <?php foreach ($questions as $idx => $q): ?>
          <button class="q-dot" data-idx="<?= $idx ?>"
                  onclick="goToQuestion(<?= $idx ?>)"
                  style="width:28px;height:28px;border-radius:50%;border:2px solid var(--gris-200);background:white;cursor:pointer;font-size:11px;font-weight:600;transition:all .15s">
            <?= $idx + 1 ?>
          </button>
          <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:10px">
          <button class="btn btn-primary" id="nextBtn" onclick="nextQuestion()">Suivant <i data-lucide="chevron-right" style="width:14px;height:14px;vertical-align:-2px"></i></button>
          <button class="btn btn-gold" id="submitBtn" style="display:none" onclick="submitExam()">
            <i data-lucide="send" style="width:14px;height:14px;vertical-align:-2px"></i> Soumettre l'examen
          </button>
        </div>
      </div>
    </div>

    <script>
    const TOTAL = <?= count($questions) ?>;
    const SESSION_ID = '<?= e($sessionId) ?>';
    const CSRF = '<?= e(csrf_token()) ?>';
    let current = 0;
    let answers = {};
    let timeStart = Date.now();
    let timerInterval;

    // Timer
    timerInterval = setInterval(() => {
      const sec = Math.floor((Date.now() - timeStart) / 1000);
      const m = String(Math.floor(sec / 60)).padStart(2, '0');
      const s = String(sec % 60).padStart(2, '0');
      document.getElementById('timer').textContent = m + ':' + s;
      // Alarme à 5min avant limite si applicable
    }, 1000);

    function goToQuestion(idx) {
      document.querySelector('.question-slide[data-idx="' + current + '"]').style.display = 'none';
      current = idx;
      document.querySelector('.question-slide[data-idx="' + current + '"]').style.display = 'block';
      document.getElementById('counter').textContent = (current+1) + '/' + TOTAL;
      document.getElementById('prevBtn').disabled = current === 0;
      document.getElementById('nextBtn').style.display = current < TOTAL - 1 ? '' : 'none';
      document.getElementById('submitBtn').style.display = current === TOTAL - 1 ? '' : 'none';
      document.getElementById('exam-progress').style.width = ((current + 1) / TOTAL * 100) + '%';
      updateDots();
    }

    function nextQuestion() { if (current < TOTAL - 1) goToQuestion(current + 1); }
    function prevQuestion() { if (current > 0) goToQuestion(current - 1); }

    function updateDots() {
      document.querySelectorAll('.q-dot').forEach((d, i) => {
        const id = document.querySelector('.question-slide[data-idx="' + i + '"] .options-list')?.dataset.questionId;
        if (i === current) {
          d.style.background = 'var(--primary)'; d.style.color = 'white'; d.style.borderColor = 'var(--primary)';
        } else if (id && answers[id]) {
          d.style.background = 'var(--primary-subtle)'; d.style.color = 'var(--primary-dark)'; d.style.borderColor = 'var(--primary)';
        } else {
          d.style.background = 'white'; d.style.color = 'var(--gris-700)'; d.style.borderColor = 'var(--gris-200)';
        }
      });
    }

    // Sélection d'option
    document.addEventListener('click', function(e) {
      const item = e.target.closest('.option-item');
      if (!item) return;
      const list = item.closest('.options-list');
      if (!list) return;
      const questionId = list.dataset.questionId;
      const optionId   = item.dataset.optionId;

      // Désélectionner toutes
      list.querySelectorAll('.option-item').forEach(o => {
        o.style.borderColor = 'var(--gris-200)';
        o.style.background  = 'white';
        o.querySelector('.option-letter').style.background = 'var(--gris-100)';
        o.querySelector('.option-letter').style.color      = 'var(--gris-700)';
      });

      // Sélectionner celle-ci
      item.style.borderColor = 'var(--primary)';
      item.style.background  = 'var(--primary-subtle)';
      item.querySelector('.option-letter').style.background = 'var(--primary)';
      item.querySelector('.option-letter').style.color      = 'white';

      answers[questionId] = optionId;
      updateDots();
    });

    function submitExam() {
      const unanswered = TOTAL - Object.keys(answers).length;
      if (unanswered > 0) {
        if (!confirm('Il reste ' + unanswered + ' question(s) sans réponse. Soumettre quand même ?')) return;
      }
      const btn = document.getElementById('submitBtn');
      btn.disabled = true; btn.innerHTML = '<i data-lucide="loader" style="width:14px;height:14px;vertical-align:-2px"></i> Envoi en cours...';
      clearInterval(timerInterval);

      const temps = Math.floor((Date.now() - timeStart) / 1000);
      const fd = new FormData();
      fd.append('action', 'submit_session');
      fd.append('session_id', SESSION_ID);
      fd.append('csrf_token', CSRF);
      fd.append('temps_passe', temps);
      for (const [qId, oId] of Object.entries(answers)) {
        fd.append('answers[' + qId + ']', oId);
      }

      fetch(window.location.href, {method:'POST', body:fd})
        .then(r => r.json())
        .then(data => {
          if (data.redirect) window.location = data.redirect;
          else alert(data.error || 'Erreur.');
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = '<i data-lucide="send" style="width:14px;height:14px;vertical-align:-2px"></i> Soumettre l\'examen'; });
    }

    updateDots();
    </script>
    <?php
    include __DIR__ . '/includes/footer_app.php';
    exit;
}

// ── PAGE DE CONFIGURATION ─────────────────────────────────
// Démarrer une nouvelle session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_exam'])) {
    if (!csrf_verify()) { redirect('/reussiteplus/examen.php', 'error', 'Token invalide.'); }

    $matiereId = $_POST['matiere_id'] ?? null;
    $nbQ       = max(5, min(50, (int)($_POST['nb_questions'] ?? 10)));
    $examType  = $_POST['exam_type'] ?? 'ENTRAINEMENT';
    $tempsLimite = (int)($_POST['temps_limite'] ?? 3600);

    $matiere = $matiereId ? dbRow("SELECT * FROM matieres WHERE id=?", [$matiereId]) : null;

    // Vérifier questions disponibles
    $nbDispo = dbRow(
        "SELECT COUNT(*) as c FROM question_bank WHERE matiere_id=? AND status='PUBLIE' AND type_question='QCM'",
        [$matiereId]
    )['c'] ?? 0;

    if ($nbDispo < 1) {
        redirect('/reussiteplus/examen.php', 'error', 'Aucune question disponible pour cette matière.');
    }

    $newSessionId = dbInsert('exam_sessions', [
        'user_id'      => $user['id'],
        'matiere_id'   => $matiereId,
        'exam_type'    => $examType,
        'titre'        => 'Entraînement — ' . ($matiere['nom'] ?? 'Général'),
        'nb_questions' => min($nbQ, $nbDispo),
        'temps_limite' => $tempsLimite,
        'statut'       => 'EN_COURS',
    ]);

    redirect('/reussiteplus/examen.php?session=' . $newSessionId);
}

// Charger les matières disponibles
$matieres = dbAll(
    "SELECT m.*, COUNT(qb.id) as nb_questions
     FROM matieres m
     LEFT JOIN question_bank qb ON qb.matiere_id = m.id AND qb.status = 'PUBLIE' AND qb.type_question = 'QCM'
     WHERE m.actif = 1
     GROUP BY m.id
     HAVING nb_questions > 0
     ORDER BY m.ordre, m.nom"
);

include __DIR__ . '/includes/header_app.php';
?>

<div style="max-width:680px;margin:0 auto">
  <?php if ($user['plan'] === 'GRATUIT'): ?>
  <div class="alert alert-warning" style="margin-bottom:24px">
    📊 Plan Gratuit : <?= $user['examens_mois'] ?? 0 ?>/<?= FREE_EXAMS_PER_MONTH ?> examens ce mois.
    <a href="/reussiteplus/tarifs.php" style="font-weight:600;color:var(--gold-dark)">Passer à Premium →</a>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-title" style="margin-bottom:4px;font-size:20px"><i data-lucide="settings-2" style="width:20px;height:20px;vertical-align:-4px;stroke:var(--primary)"></i> Configurer votre examen</div>
    <p style="color:var(--gris-600);font-size:14px;margin-bottom:24px">Choisissez une matière et le nombre de questions pour commencer.</p>

    <form method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="start_exam" value="1">

      <div class="form-group">
        <label class="form-label"><i data-lucide="book-open" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px"></i> Matière *</label>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
          <?php foreach ($matieres as $m): ?>
          <label style="cursor:pointer">
            <input type="radio" name="matiere_id" value="<?= e($m['id']) ?>" required style="display:none"
                   onchange="document.querySelectorAll('.matiere-card').forEach(c=>c.classList.remove('selected'));this.closest('.matiere-card').classList.add('selected')">
            <div class="matiere-card" style="border:2px solid var(--gris-200);border-radius:var(--radius);padding:12px;text-align:center;transition:all .15s;position:relative">
              <div style="margin-bottom:6px;display:flex;justify-content:center"><?= matiere_icon($m['icone'] ?? 'book', 28) ?></div>
              <div style="font-size:12px;font-weight:600;color:var(--gris-900)"><?= e($m['nom']) ?></div>
              <div style="font-size:10px;color:var(--gris-500);margin-top:2px"><?= number_format($m['nb_questions']) ?> questions</div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px">
        <div class="form-group">
          <label class="form-label"><i data-lucide="hash" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px"></i> Nombre de questions</label>
          <select class="form-control" name="nb_questions">
            <option value="5">5 questions (rapide)</option>
            <option value="10" selected>10 questions</option>
            <option value="20">20 questions</option>
            <option value="30">30 questions</option>
            <option value="50">50 questions (complet)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label"><i data-lucide="timer" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px"></i> Limite de temps</label>
          <select class="form-control" name="temps_limite">
            <option value="900">15 minutes</option>
            <option value="1800">30 minutes</option>
            <option value="3600" selected>1 heure</option>
            <option value="7200">2 heures</option>
            <option value="0">Sans limite</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label"><i data-lucide="target" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px"></i> Type d'examen</label>
        <select class="form-control" name="exam_type">
          <option value="ENTRAINEMENT">Entraînement libre</option>
          <option value="ENAFEP">Simulation ENAFEP</option>
          <option value="TENASOSP">Simulation TENASOSP</option>
          <option value="EXAMEN_ETAT">Simulation Examen d'État</option>
          <option value="DIOCESAIN">Simulation Test Diocésain</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px">
        <i data-lucide="play" style="width:16px;height:16px;vertical-align:-2px;margin-right:6px"></i> Commencer l'examen
      </button>
    </form>
  </div>

  <!-- Conseils -->
  <div class="card" style="margin-top:20px;background:var(--bleu-light);border-color:var(--bleu)">
    <div style="font-weight:700;color:var(--bleu);margin-bottom:8px"><i data-lucide="lightbulb" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px"></i> Conseils pour réussir</div>
    <ul style="font-size:13px;color:var(--gris-700);list-style:none">
      <li style="padding:4px 0"><i data-lucide="smartphone-nfc" style="width:13px;height:13px;vertical-align:-2px;margin-right:5px"></i> Éloignez les distractions pendant l'examen</li>
      <li style="padding:4px 0"><i data-lucide="clock" style="width:13px;height:13px;vertical-align:-2px;margin-right:5px"></i> Respectez le temps imparti comme en conditions réelles</li>
      <li style="padding:4px 0"><i data-lucide="file-text" style="width:13px;height:13px;vertical-align:-2px;margin-right:5px"></i> Lisez attentivement chaque question avant de répondre</li>
      <li style="padding:4px 0"><i data-lucide="refresh-cw" style="width:13px;height:13px;vertical-align:-2px;margin-right:5px"></i> Révisez les réponses incorrectes après l'examen</li>
    </ul>
  </div>
</div>

<style>
.matiere-card:hover { border-color: var(--primary); background: var(--primary-subtle); }
.matiere-card.selected { border-color: var(--primary); background: var(--primary-subtle); }
.matiere-card.selected::after { content:'✓'; position:absolute; top:6px; right:8px; color:var(--primary); font-weight:700; font-size:14px; }
</style>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
