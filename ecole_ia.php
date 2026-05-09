<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'IA Pédagogique';
$pageActive = 'ecole_ia';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

// ── Collecte des stats pour l'IA ─────────────────────────────
$classes = dbAll("SELECT id, nom FROM classes_ecole WHERE admin_id=? AND actif=1 ORDER BY nom", [$user['id']]) ?? [];
$filtreClasse = $_GET['classe'] ?? ($classes[0]['id'] ?? '');

$classeActive = null;
foreach ($classes as $cl) { if ($cl['id'] === $filtreClasse) { $classeActive = $cl; break; } }

$ecoleStats = [
    'nb_classes'     => count($classes),
    'nb_enseignants' => (int)dbScalar("SELECT COUNT(*) FROM enseignants_ecole WHERE ecole_admin_id=? AND statut='ACTIF'", [$user['id']]),
    'nb_eleves'      => (int)dbScalar("SELECT COUNT(DISTINCT cm.eleve_id) FROM classe_membres cm JOIN classes_ecole c ON c.id=cm.classe_id WHERE c.admin_id=?", [$user['id']]),
    'nb_devoirs'     => (int)dbScalar("SELECT COUNT(*) FROM devoirs_ecole WHERE admin_id=? AND actif=1", [$user['id']]),
    'nb_ressources'  => (int)dbScalar("SELECT COUNT(*) FROM bibliotheque_ecole WHERE ecole_admin_id=?", [$user['id']]),
    'nb_absences'    => (int)dbScalar("SELECT COUNT(*) FROM absences_ecole a JOIN classes_ecole c ON c.id=a.classe_id WHERE c.admin_id=? AND a.justifiee=0 AND a.date_absence >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$user['id']]),
];

// Stats de la classe sélectionnée
$classeStats = [];
if ($classeActive) {
    $elevesClasse = dbAll(
        "SELECT u.id, u.nom, u.prenom,
                COUNT(DISTINCT es.id) as nb_examens,
                COALESCE(ROUND(AVG(er.score_pct),1),0) as score_moyen,
                COUNT(DISTINCT a.id) as nb_absences
         FROM classe_membres cm
         JOIN utilisateurs u ON u.id=cm.eleve_id
         LEFT JOIN exam_sessions es ON es.user_id=u.id AND es.statut='COMPLETE'
         LEFT JOIN exam_results er ON er.session_id=es.id
         LEFT JOIN absences_ecole a ON a.eleve_id=u.id AND a.classe_id=cm.classe_id
         WHERE cm.classe_id=?
         GROUP BY u.id ORDER BY score_moyen DESC",
        [$filtreClasse]
    ) ?? [];
    $classeStats = $elevesClasse;
}

// Scores par matière (exam_sessions)
$matiereStats = dbAll(
    "SELECT m.nom as matiere, COUNT(DISTINCT es.id) as nb_sessions,
            ROUND(AVG(es.pourcentage), 1) as avg_score
     FROM exam_sessions es
     JOIN matieres m ON m.id = es.matiere_id
     JOIN classe_membres cm ON cm.eleve_id = es.user_id
     JOIN classes_ecole c ON c.id = cm.classe_id
     WHERE c.admin_id = ? AND es.statut = 'TERMINE'
     GROUP BY m.id ORDER BY avg_score ASC LIMIT 8",
    [$user['id']]
) ?? [];

// ── Requête IA (GET ?generate=1) ─────────────────────────────
$iaReponse      = null;
$iaPromptType   = $_GET['type'] ?? 'analyse';
$iaError        = null;
$iaLoading      = false;

if (isset($_GET['generate'])) {
    $groqKey = $_ENV['GROQ_API_KEY'] ?? '';
    if (!$groqKey) {
        $iaError = "Clé API Groq non configurée. Vérifiez le fichier .env.";
    } else {
        // Construire le contexte
        $ctx = "École avec {$ecoleStats['nb_classes']} classes, {$ecoleStats['nb_enseignants']} enseignants, {$ecoleStats['nb_eleves']} élèves.";
        if ($classeActive) {
            $top3    = array_slice($classeStats, 0, 3);
            $bottom3 = array_slice($classeStats, -3);
            $scoreMoyen = count($classeStats) ? round(array_sum(array_column($classeStats,'score_moyen'))/count($classeStats),1) : 0;
            $ctx .= "\n\nClasse analysée : {$classeActive['nom']}.";
            $ctx .= "\nNombre d'élèves : ".count($classeStats).".";
            $ctx .= "\nScore moyen de classe : {$scoreMoyen}%.";
            $ctx .= "\nAbsences injustifiées ce mois : {$ecoleStats['nb_absences']}.";
            $top3names = implode(', ', array_map(fn($e)=>($e['prenom']??'').' '.($e['nom']??'').' ('.$e['score_moyen'].'%)', $top3));
            $ctx .= "\nTop 3 élèves : {$top3names}.";
            $bottom3names = implode(', ', array_map(fn($e)=>($e['prenom']??'').' '.($e['nom']??'').' ('.$e['score_moyen'].'%)', $bottom3));
            $ctx .= "\nÉlèves en difficulté : {$bottom3names}.";
        }
        if ($matiereStats) {
            $matList = implode(', ', array_map(fn($m)=>($m['matiere']??'Autre').' ('.$m['avg_score'].'%)', $matiereStats));
            $ctx .= "\nPerformances par matière : {$matList}.";
        }

        $prompts = [
            'analyse'      => "Tu es un conseiller pédagogique expert. Analyse les données suivantes et fournis une analyse complète et professionnelle de la situation pédagogique de cette école. Donne des insights précis, identifie les points forts et les points faibles, et formule des recommandations actionnables.",
            'risque'       => "Tu es un expert en prévention du décrochage scolaire. Analyse ces données et identifie les élèves et les matières à risque. Propose un plan d'intervention prioritaire avec des actions concrètes à mettre en place dans les 4 prochaines semaines.",
            'methodes'     => "Tu es un expert en pédagogie active et en méthodes d'enseignement innovantes. Sur la base de ces données, propose 5 méthodes pédagogiques concrètes et adaptées au contexte africain pour améliorer les résultats scolaires dans les matières faibles.",
            'motivation'   => "Tu es un psychologue scolaire spécialisé dans la motivation des élèves. Analyse ces données et propose des stratégies concrètes pour augmenter l'engagement et la motivation des élèves, notamment ceux en difficulté.",
            'communication'=> "Tu es un expert en communication école-famille. Propose un plan de communication structuré pour informer les parents des résultats scolaires et les impliquer dans l'amélioration des performances de leurs enfants.",
        ];

        $systemPrompt = $prompts[$iaPromptType] ?? $prompts['analyse'];
        $systemPrompt .= "\n\nRéponds en français, de manière structurée avec des titres et des points. Sois direct et actionnable. Maximum 600 mots.";

        $payload = json_encode([
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => "Voici les données de mon école :\n\n" . $ctx . "\n\nFournis ton analyse maintenant."],
            ],
            'max_tokens'  => 1200,
            'temperature' => 0.7,
        ]);

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $groqKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp && $httpCode === 200) {
            $data = json_decode($resp, true);
            $iaReponse = $data['choices'][0]['message']['content'] ?? null;
        } else {
            $err = json_decode($resp, true);
            $iaError = "Erreur API Groq ({$httpCode}): " . ($err['error']['message'] ?? 'Inconnue');
        }
    }
}

include __DIR__ . '/includes/header_app.php';
?>

<style>
.ia-hero { background:linear-gradient(135deg,#0a0a1a,#581c87 40%,#1e1b4b 80%,#0a0a1a); border-radius:var(--radius-xl); padding:32px; margin-bottom:24px; position:relative; overflow:hidden; }
.ia-hero::before { content:''; position:absolute; top:-50px; right:-50px; width:200px; height:200px; background:radial-gradient(circle,rgba(168,85,247,.3),transparent 70%); border-radius:50%; }
.ia-hero::after { content:''; position:absolute; bottom:-30px; left:30%; width:160px; height:160px; background:radial-gradient(circle,rgba(99,102,241,.2),transparent 70%); border-radius:50%; }
.ia-prompt-btn { padding:10px 16px; border-radius:12px; border:1.5px solid rgba(255,255,255,.15); background:rgba(255,255,255,.08); color:rgba(255,255,255,.7); font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; transition:all .2s; display:flex; align-items:center; gap:7px; }
.ia-prompt-btn:hover, .ia-prompt-btn.active { background:rgba(168,85,247,.4); border-color:rgba(168,85,247,.7); color:#fff; }
.ia-stat-card { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:12px; padding:14px; text-align:center; }
.ia-response { background:var(--blanc); border:1.5px solid var(--gris-200); border-radius:var(--radius-xl); padding:28px; line-height:1.75; }
.ia-response h2, .ia-response h3 { font-family:var(--font-display); color:var(--gris-900); margin-top:16px; }
.ia-response h2 { font-size:16px; font-weight:800; }
.ia-response h3 { font-size:14px; font-weight:700; color:var(--primary); }
.ia-response ul { padding-left:20px; }
.ia-response li { margin-bottom:4px; }
.ia-response strong { color:var(--gris-900); }
.classe-tab { padding:7px 14px; border-radius:20px; font-size:12px; font-weight:700; text-decoration:none; transition:.15s; white-space:nowrap; }
</style>

<!-- Hero -->
<div class="ia-hero">
  <div style="position:relative;z-index:1">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:24px">
      <div>
        <div style="font-size:11px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">
          <a href="/reussiteplus/ecole.php" style="color:rgba(255,255,255,.35);text-decoration:none">Mon École</a> / IA Pédagogique
        </div>
        <div style="font-family:var(--font-display);font-size:24px;font-weight:900;color:#fff;margin-bottom:4px;display:flex;align-items:center;gap:10px">
          <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;background:linear-gradient(135deg,#7c3aed,#4f46e5);border-radius:10px">✨</span>
          IA Pédagogique
        </div>
        <div style="font-size:13px;color:rgba(255,255,255,.5)">Analyse intelligente de votre école — Propulsée par Groq Llama 3.1</div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
        <div class="ia-stat-card"><div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= $ecoleStats['nb_eleves'] ?></div><div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Élèves</div></div>
        <div class="ia-stat-card"><div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:#fff"><?= $ecoleStats['nb_classes'] ?></div><div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Classes</div></div>
        <div class="ia-stat-card"><div style="font-family:var(--font-display);font-size:20px;font-weight:900;color:<?= $ecoleStats['nb_absences']>5?'#FCA5A5':'#86EFAC' ?>"><?= $ecoleStats['nb_absences'] ?></div><div style="font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase">Absences injust.</div></div>
      </div>
    </div>

    <!-- Types d'analyse -->
    <div style="margin-bottom:16px">
      <div style="font-size:11px;color:rgba(255,255,255,.4);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">Type d'analyse</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php
        $types = [
            'analyse'       => ['label'=>'Analyse globale',      'icon'=>'bar-chart-2'],
            'risque'        => ['label'=>'Élèves à risque',      'icon'=>'alert-triangle'],
            'methodes'      => ['label'=>'Méthodes pédago',      'icon'=>'lightbulb'],
            'motivation'    => ['label'=>'Motivation élèves',    'icon'=>'zap'],
            'communication' => ['label'=>'Communication parents', 'icon'=>'message-square'],
        ];
        foreach ($types as $k => $t): ?>
        <a href="/reussiteplus/ecole_ia.php?classe=<?= urlencode($filtreClasse) ?>&type=<?= $k ?>"
           class="ia-prompt-btn <?= $iaPromptType===$k?'active':'' ?>">
          <i data-lucide="<?= $t['icon'] ?>" style="width:12px;height:12px"></i>
          <?= $t['label'] ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Classe + bouton générer -->
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <div style="display:flex;gap:5px;flex-wrap:wrap">
        <?php foreach ($classes as $cl): ?>
        <a href="/reussiteplus/ecole_ia.php?classe=<?= urlencode($cl['id']) ?>&type=<?= urlencode($iaPromptType) ?>"
           class="classe-tab" style="<?= $filtreClasse===$cl['id']?'background:rgba(255,255,255,.9);color:#581c87':'background:rgba(255,255,255,.12);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.2)' ?>">
          <?= e($cl['nom']) ?>
        </a>
        <?php endforeach; ?>
      </div>
      <a href="/reussiteplus/ecole_ia.php?classe=<?= urlencode($filtreClasse) ?>&type=<?= urlencode($iaPromptType) ?>&generate=1"
         style="margin-left:auto;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;padding:11px 22px;border-radius:var(--radius);font-size:14px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:8px;transition:all .2s;box-shadow:0 4px 15px rgba(124,58,237,.4)"
         onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 20px rgba(124,58,237,.6)'"
         onmouseout="this.style.transform='';this.style.boxShadow='0 4px 15px rgba(124,58,237,.4)'">
        <span style="font-size:16px">✨</span>
        <?= $iaReponse ? 'Régénérer l\'analyse' : 'Générer l\'analyse IA' ?>
      </a>
    </div>
  </div>
</div>

<!-- Contenu principal -->
<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start">
  <!-- Réponse IA -->
  <div>
    <?php if ($iaError): ?>
    <div style="background:#FEE2E2;border:1.5px solid #FECACA;border-radius:var(--radius-lg);padding:16px 20px;color:#DC2626;font-size:13px">
      <strong>Erreur :</strong> <?= e($iaError) ?>
    </div>
    <?php elseif ($iaReponse): ?>
    <div class="ia-response">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--gris-100)">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,#7c3aed,#4f46e5);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px">✨</div>
        <div>
          <div style="font-family:var(--font-display);font-size:14px;font-weight:800;color:var(--gris-900)"><?= $types[$iaPromptType]['label'] ?? 'Analyse IA' ?></div>
          <div style="font-size:11px;color:var(--gris-400)">Classe <?= e($classeActive['nom'] ?? 'Toutes') ?> · Généré le <?= date('d/m/Y à H:i') ?></div>
        </div>
        <a href="/reussiteplus/ecole_ia.php?classe=<?= urlencode($filtreClasse) ?>&type=<?= urlencode($iaPromptType) ?>&generate=1"
           class="btn btn-ghost btn-sm" style="margin-left:auto">
          <i data-lucide="refresh-cw" style="width:12px;height:12px;vertical-align:-2px"></i> Régénérer
        </a>
      </div>
      <div id="ia-content">
        <?= nl2br(e($iaReponse)) ?>
      </div>
    </div>
    <?php else: ?>
    <!-- État initial -->
    <div style="background:var(--blanc);border:1.5px dashed var(--gris-300);border-radius:var(--radius-xl);padding:60px 30px;text-align:center">
      <div style="font-size:48px;margin-bottom:16px">🤖</div>
      <div style="font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--gris-800);margin-bottom:8px">Votre conseiller pédagogique IA</div>
      <p style="color:var(--gris-500);max-width:400px;margin:0 auto 24px;font-size:14px;line-height:1.6">
        L'IA analyse vos données pédagogiques et génère des recommandations personnalisées, des alertes sur les élèves en difficulté et des stratégies d'enseignement adaptées.
      </p>
      <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-bottom:24px">
        <?php foreach ($types as $k => $t): ?>
        <a href="/reussiteplus/ecole_ia.php?classe=<?= urlencode($filtreClasse) ?>&type=<?= $k ?>&generate=1"
           style="padding:9px 16px;border:1.5px solid var(--gris-200);border-radius:12px;font-size:12px;font-weight:700;color:var(--gris-700);text-decoration:none;transition:.15s;display:flex;align-items:center;gap:6px"
           onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)';this.style.background='var(--primary-subtle)'"
           onmouseout="this.style.borderColor='var(--gris-200)';this.style.color='var(--gris-700)';this.style.background=''">
          <i data-lucide="<?= $t['icon'] ?>" style="width:13px;height:13px"></i> <?= $t['label'] ?>
        </a>
        <?php endforeach; ?>
      </div>
      <a href="/reussiteplus/ecole_ia.php?classe=<?= urlencode($filtreClasse) ?>&type=analyse&generate=1"
         style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;padding:13px 28px;border-radius:var(--radius);font-size:14px;font-weight:800;text-decoration:none;box-shadow:0 4px 15px rgba(124,58,237,.4)">
        ✨ Lancer l'analyse complète
      </a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Sidebar stats -->
  <div style="display:flex;flex-direction:column;gap:14px">

    <?php if ($classeStats): ?>
    <!-- Top & Difficultés -->
    <div class="card">
      <div style="font-family:var(--font-display);font-size:13px;font-weight:800;margin-bottom:12px;display:flex;align-items:center;gap:6px">
        <i data-lucide="trophy" style="width:13px;height:13px;stroke:#D97706"></i> Top 5 de <?= e($classeActive['nom'] ?? '') ?>
      </div>
      <?php foreach (array_slice($classeStats, 0, 5) as $i => $el): ?>
      <?php $sc=(float)$el['score_moyen'];$bc=$sc>=70?'#059669':($sc>=50?'#D97706':'#DC2626'); ?>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px">
        <div style="font-size:12px;font-weight:700;color:<?= $i<3?'#D97706':'var(--gris-400)' ?>;width:18px">
          <?= $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':($i+1))) ?>
        </div>
        <div style="flex:1;font-size:12px;font-weight:600;color:var(--gris-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e(($el['prenom']??'').' '.($el['nom']??'')) ?></div>
        <span style="font-size:11px;font-weight:800;color:<?= $bc ?>"><?= $sc ?>%</span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Élèves en difficulté -->
    <?php $endanger = array_filter($classeStats, fn($e) => $e['score_moyen'] < 50); ?>
    <?php if ($endanger): ?>
    <div class="card" style="border:1.5px solid #FEE2E2">
      <div style="font-family:var(--font-display);font-size:13px;font-weight:800;margin-bottom:12px;color:#DC2626;display:flex;align-items:center;gap:6px">
        <i data-lucide="alert-triangle" style="width:13px;height:13px;stroke:#DC2626"></i> En difficulté
      </div>
      <?php foreach (array_slice($endanger, 0, 5) as $el): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px">
        <div style="font-size:12px;font-weight:600;color:var(--gris-800)"><?= e(($el['prenom']??'').' '.($el['nom']??'')) ?></div>
        <div style="display:flex;align-items:center;gap:5px">
          <?php if ($el['nb_absences'] > 2): ?><span style="font-size:10px;background:#FEF3C7;color:#D97706;padding:1px 5px;border-radius:5px"><?= $el['nb_absences'] ?>×</span><?php endif; ?>
          <span style="font-size:11px;font-weight:800;color:#DC2626"><?= $el['score_moyen'] ?>%</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Matières faibles -->
    <?php if ($matiereStats): ?>
    <div class="card">
      <div style="font-family:var(--font-display);font-size:13px;font-weight:800;margin-bottom:12px;display:flex;align-items:center;gap:6px">
        <i data-lucide="trending-down" style="width:13px;height:13px;stroke:#DC2626"></i> Matières difficiles
      </div>
      <?php foreach (array_slice($matiereStats, 0, 5) as $ms): ?>
      <?php $pct=(float)$ms['avg_score'];$bc=$pct>=70?'#059669':($pct>=50?'#D97706':'#DC2626'); ?>
      <div style="margin-bottom:9px">
        <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px">
          <span style="font-weight:600;color:var(--gris-700)"><?= e($ms['matiere']??'—') ?></span>
          <span style="font-weight:700;color:<?= $bc ?>"><?= $pct ?>%</span>
        </div>
        <div style="height:4px;background:var(--gris-200);border-radius:3px">
          <div style="width:<?= $pct ?>%;height:100%;background:<?= $bc ?>;border-radius:3px;transition:.5s"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Info modèle -->
    <div style="background:linear-gradient(135deg,#1a0a2e,#2d1b69);border-radius:12px;padding:14px">
      <div style="font-size:11px;color:rgba(255,255,255,.5);margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Modèle IA</div>
      <div style="font-size:13px;font-weight:700;color:#fff">Groq Llama 3.1</div>
      <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px">8B Instant · Ultra-rapide</div>
      <div style="height:1px;background:rgba(255,255,255,.1);margin:10px 0"></div>
      <div style="font-size:10px;color:rgba(255,255,255,.3);line-height:1.5">Les analyses IA sont indicatives et doivent être combinées au jugement professionnel de l'enseignant.</div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
