<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Révision IA';
$pageActive = 'revision';
$user = require_login();

// Plan info
$planData = PLANS[$user['plan']] ?? [];
$hasIA    = $planData['ia'] ?? false;

// Données pour le diagnostic (même si pas IA, pour l'upsell card)
$progressMatieres = dbAll(
    "SELECT up.*, m.nom, m.couleur, m.icone, m.code
     FROM user_progression up
     JOIN matieres m ON up.matiere_id = m.id
     WHERE up.user_id = ?
     ORDER BY up.score_moyen ASC",
    [$user['id']]
);

$faiblesses = array_filter($progressMatieres, fn($m) => (float)$m['score_moyen'] < 60);
$forces     = array_filter($progressMatieres, fn($m) => (float)$m['score_moyen'] >= 70);

// Stats globales
$totalExamens = (int)($user['total_examens'] ?? 0);
$scoreMoyen   = (float)($user['score_moyen'] ?? 0);

// Récupérer les matières actives pour les exercices ciblés
$matieres = dbAll("SELECT id, nom, code, couleur, icone FROM matieres WHERE actif=1 ORDER BY nom");

// Nombre d'erreurs récentes
$nbErreurs = (int)(dbRow(
    "SELECT COUNT(DISTINCT qb.id) as n
     FROM exam_answers ea
     JOIN exam_sessions es ON ea.session_id = es.id
     JOIN question_bank qb ON ea.question_id = qb.id
     WHERE es.user_id = ? AND ea.est_correcte = 0",
    [$user['id']]
)['n'] ?? 0);

include __DIR__ . '/includes/header_app.php';

$csrf = csrf_token();
?>

<!-- ══════════════════════════════════════════════════ -->
<!-- En-tête page                                       -->
<!-- ══════════════════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
  <div>
    <h1 style="font-size:22px;font-weight:800;margin:0;display:flex;align-items:center;gap:10px">
      <span style="background:linear-gradient(135deg,var(--primary),#7c3aed);width:38px;height:38px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0">
        <i data-lucide="cpu" style="width:18px;height:18px"></i>
      </span>
      Révision IA Personnalisée
    </h1>
    <p style="margin:6px 0 0 48px;font-size:13px;color:var(--gris-500)">
      Un coach intelligent qui analyse vos performances et vous guide vers la réussite
    </p>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <?php if ($hasIA): ?>
    <span style="background:linear-gradient(135deg,#7c3aed,var(--primary));color:#fff;padding:4px 14px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.5px">
      ✦ IA ACTIVÉE
    </span>
    <?php else: ?>
    <span style="background:var(--gris-200);color:var(--gris-600);padding:4px 14px;border-radius:20px;font-size:11px;font-weight:700">
      <i data-lucide="lock" style="width:11px;height:11px;vertical-align:-1px;margin-right:3px"></i> Plan Excellence requis
    </span>
    <?php endif; ?>
    <a href="/reussiteplus/progression.php" class="btn btn-ghost btn-sm"><i data-lucide="bar-chart-2" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px"></i> Ma progression</a>
  </div>
</div>

<?php if (!$hasIA): ?>
<!-- ══════════════════════════════════════════════════ -->
<!-- LOCK OVERLAY — Plan insuffisant                    -->
<!-- ══════════════════════════════════════════════════ -->
<div class="card" style="padding:0;overflow:hidden;border:2px solid #7c3aed33">
  <!-- Aperçu flou -->
  <div style="filter:blur(3px);pointer-events:none;padding:28px;background:var(--blanc);position:relative">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px">
      <?php foreach (['Diagnostic IA','Plan hebdo','Chat assistant'] as $t): ?>
      <div style="background:var(--gris-100);border-radius:10px;padding:18px;text-align:center">
        <div style="font-size:28px;margin-bottom:8px"><i data-lucide="bot" style="width:28px;height:28px"></i></div>
        <div style="font-weight:700;font-size:14px"><?= $t ?></div>
        <div style="height:8px;background:var(--gris-200);border-radius:4px;margin-top:10px"></div>
        <div style="height:6px;background:var(--gris-200);border-radius:4px;margin-top:6px;width:70%"></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Overlay CTA -->
  <div style="background:linear-gradient(135deg,#4f1d96ee,#7c3aedee);padding:32px;text-align:center;color:#fff">
    <div style="font-size:40px;margin-bottom:12px"><i data-lucide="rocket" style="width:44px;height:44px;stroke:#fff"></i></div>
    <div style="font-size:20px;font-weight:800;margin-bottom:8px">Débloquez la Révision IA</div>
    <p style="font-size:14px;opacity:.9;max-width:500px;margin:0 auto 20px">
      Accédez à votre coach IA personnel, un plan de révision hebdomadaire sur mesure, l'analyse de vos erreurs et un assistant disponible 24h/24.
    </p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:16px">
      <div style="background:#ffffff22;border-radius:8px;padding:10px 18px;font-size:13px">✦ Plan de révision 7 jours</div>
      <div style="background:#ffffff22;border-radius:8px;padding:10px 18px;font-size:13px">✦ Chat assistant IA</div>
      <div style="background:#ffffff22;border-radius:8px;padding:10px 18px;font-size:13px">✦ Analyse des erreurs</div>
      <div style="background:#ffffff22;border-radius:8px;padding:10px 18px;font-size:13px">✦ Exercices ciblés</div>
    </div>
    <a href="/reussiteplus/abonnement.php" class="btn" style="background:#fff;color:#7c3aed;font-weight:700;padding:12px 32px;font-size:15px;border-radius:10px">
      <i data-lucide="arrow-up-circle" style="width:16px;height:16px;vertical-align:-2px;margin-right:6px"></i> Passer au plan Excellence
    </a>
    <div style="font-size:12px;opacity:.7;margin-top:10px">À partir de 5$/mois · Annulation à tout moment</div>
  </div>
</div>

<!-- Diagnostic gratuit visible même sans IA -->
<div style="margin-top:24px">
  <div class="section-header"><div class="section-title"><i data-lucide="clipboard-list" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px"></i> Votre diagnostic (aperçu gratuit)</div></div>
  <?php if ($progressMatieres): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
    <?php foreach ($progressMatieres as $m): $pct=(float)$m['score_moyen']; ?>
    <div class="card" style="padding:14px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <span style="display:flex;align-items:center;gap:8px"><?= matiere_icon($m['icone']??'book', 18) ?>
        <span style="font-weight:600;font-size:13px"><?= e($m['nom']) ?></span></span>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <div style="flex:1;height:6px;background:var(--gris-200);border-radius:3px;overflow:hidden">
          <div style="width:<?= min(100,$pct) ?>%;height:100%;background:<?= score_couleur($pct) ?>;border-radius:3px;transition:width .5s"></div>
        </div>
        <span style="font-size:12px;font-weight:700;color:<?= score_couleur($pct) ?>"><?= number_format($pct,0) ?>%</span>
      </div>
      <?php if ($pct < 60): ?>
      <div style="font-size:10px;color:#ef4444;margin-top:4px">⚠ Point faible</div>
      <?php elseif ($pct >= 80): ?>
      <div style="font-size:10px;color:#22c55e;margin-top:4px">✓ Point fort</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="card" style="text-align:center;padding:32px;color:var(--gris-500)">
    <div style="font-size:36px;margin-bottom:10px"><i data-lucide="clipboard" style="width:36px;height:36px;stroke:var(--gris-400)"></i></div>
    <div>Passez vos premiers examens pour voir votre diagnostic.</div>
    <a href="/reussiteplus/examen.php" class="btn btn-primary btn-sm" style="margin-top:12px">Commencer un examen</a>
  </div>
  <?php endif; ?>
</div>

<?php else: // HAS IA ?>

<!-- ══════════════════════════════════════════════════ -->
<!-- SECTION 1 — Diagnostic rapide                     -->
<!-- ══════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:28px">
  <!-- Score moyen -->
  <div class="card" style="padding:18px;border-left:4px solid <?= score_couleur($scoreMoyen) ?>;display:flex;flex-direction:column;gap:4px">
    <div style="font-size:11px;font-weight:600;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px">Score moyen</div>
    <div style="font-size:28px;font-weight:800;color:<?= score_couleur($scoreMoyen) ?>"><?= number_format($scoreMoyen,1) ?>%</div>
    <div style="font-size:12px;color:var(--gris-500)"><?= score_label($scoreMoyen) ?></div>
  </div>
  <!-- Examens passés -->
  <div class="card" style="padding:18px;border-left:4px solid var(--primary)">
    <div style="font-size:11px;font-weight:600;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px">Examens passés</div>
    <div style="font-size:28px;font-weight:800;color:var(--primary)"><?= $totalExamens ?></div>
    <div style="font-size:12px;color:var(--gris-500)">Session(s) terminée(s)</div>
  </div>
  <!-- Points faibles -->
  <div class="card" style="padding:18px;border-left:4px solid #ef4444">
    <div style="font-size:11px;font-weight:600;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px">Points faibles</div>
    <div style="font-size:28px;font-weight:800;color:#ef4444"><?= count($faiblesses) ?></div>
    <div style="font-size:12px;color:var(--gris-500)">Matière(s) < 60%</div>
  </div>
  <!-- Erreurs à corriger -->
  <div class="card" style="padding:18px;border-left:4px solid #f59e0b">
    <div style="font-size:11px;font-weight:600;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px">Erreurs à analyser</div>
    <div style="font-size:28px;font-weight:800;color:#f59e0b"><?= $nbErreurs ?></div>
    <div style="font-size:12px;color:var(--gris-500)">Questions ratées</div>
  </div>
  <!-- Points forts -->
  <div class="card" style="padding:18px;border-left:4px solid #22c55e">
    <div style="font-size:11px;font-weight:600;color:var(--gris-500);text-transform:uppercase;letter-spacing:.5px">Points forts</div>
    <div style="font-size:28px;font-weight:800;color:#22c55e"><?= count($forces) ?></div>
    <div style="font-size:12px;color:var(--gris-500)">Matière(s) ≥ 70%</div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════ -->
<!-- SECTION 2 — Plan de révision IA (7 jours)         -->
<!-- ══════════════════════════════════════════════════ -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:24px">
  <div style="background:linear-gradient(135deg,#4f1d96,#7c3aed);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div style="color:#fff">
      <div style="font-size:16px;font-weight:700;margin-bottom:2px"><i data-lucide="calendar-days" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px"></i> Plan de révision 7 jours</div>
      <div style="font-size:12px;opacity:.8">Généré par IA sur mesure selon vos performances</div>
    </div>
    <button id="btnPlanIA" onclick="genererPlan()" class="btn" style="background:#fff;color:#7c3aed;font-weight:700;gap:6px">
      <i data-lucide="sparkles" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px"></i> Générer mon plan
    </button>
  </div>
  <div id="planRevisionResult" style="padding:22px;min-height:80px">
    <div style="text-align:center;color:var(--gris-400);padding:24px 0;font-size:13px">
      <i data-lucide="sparkles" style="width:28px;height:28px;display:block;margin-bottom:8px"></i>
      Cliquez sur « Générer mon plan » pour obtenir un programme personnalisé sur 7 jours.
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════ -->
<!-- SECTION 3 — Exercices ciblés                      -->
<!-- ══════════════════════════════════════════════════ -->
<div style="margin-bottom:28px">
  <div class="section-header">
    <div class="section-title"><i data-lucide="crosshair" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px"></i> Exercices ciblés sur vos faiblesses</div>
    <a href="/reussiteplus/examen.php" class="btn btn-ghost btn-sm"><i data-lucide="edit" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Tous les examens</a>
  </div>
  <?php if ($faiblesses): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px">
    <?php foreach (array_slice(array_values($faiblesses), 0, 6) as $m):
      $pct=(float)$m['score_moyen']; $gap=60-$pct; ?>
    <div class="card" style="padding:16px;border-top:3px solid #ef4444">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
          <?= matiere_icon($m['icone']??'book', 22) ?>
          <div>
            <div style="font-weight:700;font-size:14px"><?= e($m['nom']) ?></div>
            <div style="font-size:11px;color:var(--gris-500)"><?= (int)$m['questions_vues'] ?> questions vues</div>
          </div>
        </div>
        <span style="background:#fef2f2;color:#ef4444;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700"><?= number_format($pct,0) ?>%</span>
      </div>
      <div style="font-size:12px;color:var(--gris-600);margin-bottom:10px">
        <i data-lucide="trending-up" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;stroke:#ef4444"></i>
        Il vous faut <strong>+<?= number_format($gap, 0) ?>%</strong> pour atteindre 60%
      </div>
      <div style="height:6px;background:var(--gris-200);border-radius:3px;margin-bottom:12px;overflow:hidden">
        <div style="width:<?= min(100,$pct) ?>%;height:100%;background:#ef4444;border-radius:3px"></div>
      </div>
      <a href="/reussiteplus/examen.php?matiere=<?= (int)$m['matiere_id'] ?>" class="btn btn-primary btn-sm" style="width:100%;justify-content:center">
        <i data-lucide="edit" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> S'entraîner maintenant
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="card" style="text-align:center;padding:32px">
    <?php if ($totalExamens > 0): ?>
    <div style="font-size:36px;margin-bottom:10px"><i data-lucide="party-popper" style="width:36px;height:36px;stroke:#22C55E"></i></div>
    <div style="font-weight:700;margin-bottom:6px">Excellente performance !</div>
    <div style="font-size:13px;color:var(--gris-500)">Toutes vos matières sont au-dessus de 60%. Continuez comme ça !</div>
    <?php else: ?>
    <div style="font-size:36px;margin-bottom:10px"><i data-lucide="edit" style="width:36px;height:36px;stroke:var(--gris-400)"></i></div>
    <div style="font-weight:700;margin-bottom:6px">Aucune donnée disponible</div>
    <div style="font-size:13px;color:var(--gris-500);margin-bottom:16px">Passez quelques examens pour que l'IA puisse analyser vos faiblesses.</div>
    <a href="/reussiteplus/examen.php" class="btn btn-primary btn-sm">Commencer un examen</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════ -->
<!-- SECTION 4 — Analyse des erreurs                   -->
<!-- ══════════════════════════════════════════════════ -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:24px">
  <div style="background:linear-gradient(135deg,#92400e,#f59e0b);padding:16px 22px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div style="color:#fff">
      <div style="font-size:15px;font-weight:700"><i data-lucide="bug" style="width:14px;height:14px;vertical-align:-2px;margin-right:6px"></i> Analyse de vos erreurs récurrentes</div>
      <div style="font-size:12px;opacity:.8"><?= $nbErreurs ?> question(s) ratée(s) — L'IA explique pourquoi et comment progresser</div>
    </div>
    <button onclick="analyserErreurs()" id="btnErreurs" class="btn" style="background:#fff;color:#92400e;font-weight:700">
      <i data-lucide="lightbulb" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px"></i> Analyser mes erreurs
    </button>
  </div>
  <div id="erreursResult" style="padding:22px;min-height:60px">
    <div style="text-align:center;color:var(--gris-400);padding:16px 0;font-size:13px">
      <i data-lucide="lightbulb" style="width:24px;height:24px;display:block;margin-bottom:6px"></i>
      Cliquez pour recevoir une explication personnalisée de vos erreurs les plus fréquentes.
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════ -->
<!-- SECTION 5 — Chat assistant IA                     -->
<!-- ══════════════════════════════════════════════════ -->
<div class="card" style="padding:0;overflow:hidden;margin-bottom:24px" id="chatSection">
  <!-- Header chat -->
  <div style="background:var(--gris-50);border-bottom:1px solid var(--gris-200);padding:14px 20px;display:flex;align-items:center;gap:12px">
    <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,var(--primary));display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0">
      <i data-lucide="cpu" style="width:18px;height:18px"></i>
    </div>
    <div>
      <div style="font-weight:700;font-size:14px">RÉUSSITE+IA</div>
      <div style="font-size:11px;color:var(--gris-500)">Votre assistant pédagogique · Disponible 24h/24</div>
    </div>
    <div style="margin-left:auto;display:flex;align-items:center;gap:6px">
      <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block"></span>
      <span style="font-size:11px;color:#22c55e;font-weight:600">En ligne</span>
    </div>
  </div>

  <!-- Zone messages -->
  <div id="chatMessages" style="height:380px;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:12px;scroll-behavior:smooth">
    <!-- Message de bienvenue -->
    <div class="chat-msg ai" style="display:flex;gap:10px;align-items:flex-start;max-width:80%">
      <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,var(--primary));display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0">
        <i data-lucide="cpu" style="width:13px;height:13px"></i>
      </div>
      <div style="background:var(--gris-100);border-radius:0 12px 12px 12px;padding:12px 14px;font-size:13px;line-height:1.6">
        Bonjour <?= e($user['prenom']) ?> ! Je suis votre assistant IA RÉUSSITE+.<br><br>
        Je suis ici pour vous aider à réviser toutes les matières : <strong>Mathématiques, Français, Sciences, Histoire-Géographie, Physique, Chimie, Biologie, Anglais</strong>.<br><br>
        Posez-moi une question sur n'importe quel sujet, demandez-moi d'expliquer un concept, de vous poser une série de questions, ou de vous donner des astuces pour réussir votre examen !
      </div>
    </div>
    <!-- Suggestions rapides -->
    <div style="display:flex;flex-wrap:wrap;gap:8px;padding-left:38px">
      <?php
      $suggestions = [
          'Explique-moi les fractions',
          'Quelles sont les règles de grammaire française ?',
          'Donne-moi 5 questions de maths niveau ENAFEP',
          'Comment mémoriser le tableau périodique ?',
          'Qu\'est-ce que la photosynthèse ?',
      ];
      foreach ($suggestions as $s):
      ?>
      <button onclick="sendSuggestion(this)" data-msg="<?= e($s) ?>"
        style="background:var(--gris-100);border:1px solid var(--gris-300);border-radius:16px;padding:5px 12px;font-size:11px;cursor:pointer;color:var(--gris-700);transition:background .2s"
        onmouseover="this.style.background='var(--gris-200)'" onmouseout="this.style.background='var(--gris-100)'">
        <?= e($s) ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Input zone -->
  <div style="border-top:1px solid var(--gris-200);padding:14px 16px;display:flex;gap:10px;align-items:flex-end;background:var(--blanc)">
    <textarea id="chatInput" rows="1" placeholder="Posez votre question ici… (Shift+Entrée pour saut de ligne)"
      style="flex:1;border:1px solid var(--gris-300);border-radius:10px;padding:10px 14px;font-size:13px;resize:none;outline:none;background:var(--blanc);color:var(--gris-800);font-family:inherit;max-height:120px;transition:border-color .2s"
      onfocus="this.style.borderColor='var(--primary)'"
      onblur="this.style.borderColor='var(--gris-300)'"
      onkeydown="handleChatKey(event)"></textarea>
    <button id="btnSendChat" onclick="sendChatMessage()" 
      style="background:linear-gradient(135deg,#7c3aed,var(--primary));color:#fff;border:none;border-radius:10px;width:42px;height:42px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;font-size:18px;transition:opacity .2s">
      <i data-lucide="send" style="width:18px;height:18px"></i>
    </button>
  </div>

  <!-- Footer note -->
  <div style="background:var(--gris-50);border-top:1px solid var(--gris-200);padding:8px 16px;font-size:11px;color:var(--gris-400);text-align:center">
    <i data-lucide="info" style="width:12px;height:12px;vertical-align:-1px;margin-right:4px"></i> L'IA peut faire des erreurs. Vérifiez les informations importantes avec votre professeur.
  </div>
</div>

<!-- ══════════════════════════════════════════════════ -->
<!-- SECTION 6 — Progression par matière (résumé)      -->
<!-- ══════════════════════════════════════════════════ -->
<?php if ($progressMatieres): ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div class="section-title"><i data-lucide="grid" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px"></i> Progression par matière</div>
    <a href="/reussiteplus/progression.php" class="btn btn-ghost btn-sm">Voir les détails</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
    <?php foreach ($progressMatieres as $m): $pct=(float)$m['score_moyen']; ?>
    <div class="card" style="padding:14px;display:flex;flex-direction:column;gap:8px">
      <div style="display:flex;align-items:center;justify-content:space-between">
      <div style="display:flex;align-items:center;gap:8px">
          <?= matiere_icon($m['icone']??'book', 20) ?>
          <span style="font-weight:600;font-size:13px"><?= e($m['nom']) ?></span>
        </div>
        <span style="font-size:14px;font-weight:800;color:<?= score_couleur($pct) ?>"><?= number_format($pct,0) ?>%</span>
      </div>
      <div style="height:6px;background:var(--gris-200);border-radius:3px;overflow:hidden">
        <div style="width:<?= min(100,$pct) ?>%;height:100%;background:<?= score_couleur($pct) ?>;border-radius:3px;transition:width .8s ease"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--gris-500)">
        <span><?= (int)$m['bonnes_reponses'] ?>/<?= (int)$m['questions_vues'] ?> correctes</span>
        <a href="/reussiteplus/examen.php?matiere=<?= (int)$m['matiere_id'] ?>" style="color:var(--primary);text-decoration:none;font-weight:600">S'entraîner →</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php endif; // end hasIA ?>

<!-- ══════════════════════════════════════════════════ -->
<!-- JAVASCRIPT                                         -->
<!-- ══════════════════════════════════════════════════ -->
<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
let chatHistory = [];

// ── Rendu Markdown simple ────────────────────────────
function renderMarkdown(text) {
  return text
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>')
    .replace(/\*(.+?)\*/g,'<em>$1</em>')
    .replace(/`(.+?)`/g,'<code style="background:var(--gris-100);padding:1px 5px;border-radius:3px;font-size:12px">$1</code>')
    .replace(/^### (.+)$/gm,'<h3 style="font-size:15px;font-weight:700;margin:12px 0 6px">$1</h3>')
    .replace(/^## (.+)$/gm,'<h2 style="font-size:16px;font-weight:700;margin:14px 0 6px">$1</h2>')
    .replace(/^### /gm,'')
    .replace(/^- (.+)$/gm,'<li style="margin-bottom:3px">$1</li>')
    .replace(/(<li[^>]*>.*<\/li>)+/gs, m => `<ul style="padding-left:18px;margin:6px 0">${m}</ul>`)
    .replace(/\n\n/g,'<br><br>')
    .replace(/\n/g,'<br>');
}

// ── Indicateur de chargement IA ──────────────────────
function showIALoading(containerId, msg) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = `
    <div style="display:flex;align-items:center;gap:12px;padding:12px 0">
      <div style="display:flex;gap:4px">
        <span style="width:8px;height:8px;background:linear-gradient(135deg,#7c3aed,var(--primary));border-radius:50%;animation:bounce .7s ease infinite"></span>
        <span style="width:8px;height:8px;background:linear-gradient(135deg,#7c3aed,var(--primary));border-radius:50%;animation:bounce .7s ease infinite;animation-delay:.15s"></span>
        <span style="width:8px;height:8px;background:linear-gradient(135deg,#7c3aed,var(--primary));border-radius:50%;animation:bounce .7s ease infinite;animation-delay:.3s"></span>
      </div>
      <span style="font-size:13px;color:var(--gris-500)">${msg || 'L\'IA analyse vos données…'}</span>
    </div>`;
}

// ── Générer le plan de révision 7 jours ─────────────
async function genererPlan() {
  const btn = document.getElementById('btnPlanIA');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="hourglass" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Génération en cours…';
  showIALoading('planRevisionResult', 'L\'IA construit votre programme personnalisé…');

  try {
    const fd = new FormData();
    fd.append('action', 'plan_revision');
    fd.append('csrf_token', CSRF_TOKEN);
    const resp = await fetch('/reussiteplus/api/revision.php', { method: 'POST', body: fd });
    const data = await resp.json();
    const el = document.getElementById('planRevisionResult');
    if (data.ok) {
      el.innerHTML = `<div style="line-height:1.7;font-size:13px">${renderMarkdown(data.content)}</div>`;
    } else if (data.error === 'no_key') {
      el.innerHTML = renderNoKeyError();
    } else {
      el.innerHTML = `<div class="alert alert-danger">${data.msg || 'Erreur inconnue.'}</div>`;
    }
  } catch (e) {
    document.getElementById('planRevisionResult').innerHTML = `<div class="alert alert-danger">Erreur réseau. Vérifiez votre connexion.</div>`;
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="sparkles" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Régénérer';
  }
}

// ── Analyser les erreurs ─────────────────────────────
async function analyserErreurs() {
  const btn = document.getElementById('btnErreurs');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="hourglass" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Analyse en cours…';
  showIALoading('erreursResult', 'L\'IA analyse vos erreurs récurrentes…');

  try {
    const fd = new FormData();
    fd.append('action', 'analyse_erreurs');
    fd.append('csrf_token', CSRF_TOKEN);
    const resp = await fetch('/reussiteplus/api/revision.php', { method: 'POST', body: fd });
    const data = await resp.json();
    const el = document.getElementById('erreursResult');
    if (data.ok) {
      el.innerHTML = `<div style="line-height:1.7;font-size:13px">${renderMarkdown(data.content)}</div>`;
    } else if (data.error === 'no_key') {
      el.innerHTML = renderNoKeyError();
    } else {
      el.innerHTML = `<div class="alert alert-danger">${data.msg || 'Erreur inconnue.'}</div>`;
    }
  } catch (e) {
    document.getElementById('erreursResult').innerHTML = `<div class="alert alert-danger">Erreur réseau.</div>`;
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="lightbulb" style="width:13px;height:13px;vertical-align:-2px;margin-right:4px"></i> Relancer l\'analyse';
  }
}

// ── Chat ─────────────────────────────────────────────
function appendChatMessage(role, html, isStreaming = false) {
  const container = document.getElementById('chatMessages');
  const wrap = document.createElement('div');
  wrap.style.cssText = 'display:flex;gap:10px;align-items:flex-start';
  const isAI = role === 'ai';
  if (isAI) {
    wrap.innerHTML = `
      <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,var(--primary));display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0">
        <i data-lucide="cpu" style="width:13px;height:13px"></i>
      </div>
      <div class="chat-bubble-ai" style="background:var(--gris-100);border-radius:0 12px 12px 12px;padding:12px 14px;font-size:13px;line-height:1.7;max-width:80%">${html}</div>`;
  } else {
    wrap.style.justifyContent = 'flex-end';
    wrap.innerHTML = `
      <div style="background:linear-gradient(135deg,#7c3aed,var(--primary));color:#fff;border-radius:12px 0 12px 12px;padding:10px 14px;font-size:13px;line-height:1.6;max-width:80%">${html}</div>`;
  }
  container.appendChild(wrap);
  container.scrollTop = container.scrollHeight;
  return wrap;
}

function showTypingIndicator() {
  const container = document.getElementById('chatMessages');
  const indicator = document.createElement('div');
  indicator.id = 'typingIndicator';
  indicator.style.cssText = 'display:flex;gap:10px;align-items:flex-start';
  indicator.innerHTML = `
    <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,var(--primary));display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0">
      <i data-lucide="cpu" style="width:13px;height:13px"></i>
    </div>
    <div style="background:var(--gris-100);border-radius:0 12px 12px 12px;padding:12px 16px;display:flex;gap:4px;align-items:center">
      <span style="width:6px;height:6px;background:var(--gris-400);border-radius:50%;animation:bounce .7s ease infinite"></span>
      <span style="width:6px;height:6px;background:var(--gris-400);border-radius:50%;animation:bounce .7s ease infinite;animation-delay:.15s"></span>
      <span style="width:6px;height:6px;background:var(--gris-400);border-radius:50%;animation:bounce .7s ease infinite;animation-delay:.3s"></span>
    </div>`;
  container.appendChild(indicator);
  container.scrollTop = container.scrollHeight;
}

function removeTypingIndicator() {
  const el = document.getElementById('typingIndicator');
  if (el) el.remove();
}

async function sendChatMessage() {
  const input = document.getElementById('chatInput');
  const msg = input.value.trim();
  if (!msg) return;

  const btn = document.getElementById('btnSendChat');
  input.value = '';
  input.style.height = 'auto';
  btn.disabled = true;

  // Message utilisateur
  appendChatMessage('user', renderMarkdown(msg));
  chatHistory.push({ role: 'user', content: msg });
  showTypingIndicator();

  try {
    const fd = new FormData();
    fd.append('action', 'chat');
    fd.append('message', msg);
    fd.append('history', JSON.stringify(chatHistory.slice(0, -1)));
    fd.append('csrf_token', CSRF_TOKEN);

    const resp = await fetch('/reussiteplus/api/revision.php', { method: 'POST', body: fd });
    const data = await resp.json();
    removeTypingIndicator();

    if (data.ok) {
      appendChatMessage('ai', renderMarkdown(data.content));
      chatHistory.push({ role: 'assistant', content: data.content });
    } else if (data.error === 'no_key') {
      appendChatMessage('ai', renderNoKeyError());
    } else {
      appendChatMessage('ai', `<span style="color:#ef4444">${data.msg || 'Erreur.'}</span>`);
    }
  } catch (e) {
    removeTypingIndicator();
    appendChatMessage('ai', '<span style="color:#ef4444">Erreur réseau. Vérifiez votre connexion.</span>');
  } finally {
    btn.disabled = false;
    input.focus();
  }
}

function sendSuggestion(btn) {
  const msg = btn.getAttribute('data-msg');
  document.getElementById('chatInput').value = msg;
  // Masquer les suggestions
  btn.closest('div').style.display = 'none';
  sendChatMessage();
}

function handleChatKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendChatMessage();
  }
  // Auto-resize
  const ta = e.target;
  ta.style.height = 'auto';
  ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
}

// ── Message erreur clé API manquante ─────────────────
function renderNoKeyError() {
  return `<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:14px;font-size:13px">
    <strong>⚙️ Configuration requise</strong><br><br>
    La clé API Groq n'est pas encore configurée. Pour activer l'IA :<br>
    <ol style="margin:8px 0 0 16px;line-height:1.8">
      <li>Créez un compte gratuit sur <strong>console.groq.com</strong></li>
      <li>Générez une clé API</li>
      <li>Définissez la variable d'environnement : <code style="background:#fff;padding:1px 4px;border-radius:3px">GROQ_API_KEY=votre_cle</code></li>
      <li>Ou modifiez directement <code style="background:#fff;padding:1px 4px;border-radius:3px">includes/config.php</code></li>
    </ol>
  </div>`;
}

// ── Animation bounce ─────────────────────────────────
const style = document.createElement('style');
style.textContent = `@keyframes bounce{0%,80%,100%{transform:scale(0)}40%{transform:scale(1)}}`;
document.head.appendChild(style);
</script>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
