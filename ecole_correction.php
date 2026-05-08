<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Correction des devoirs';
$pageActive = 'ecole_devoirs';
$user = require_login();
if ($user['plan'] !== 'ECOLE') redirect('/reussiteplus/tarifs.php');

$devoirId = $_GET['devoir'] ?? '';
if (!$devoirId) redirect('/reussiteplus/ecole_devoirs.php');

// Vérifier que le devoir appartient à cet admin
$devoir = dbRow(
    "SELECT d.*, c.nom as classe_nom, c.id as classe_id,
            COUNT(DISTINCT cm.eleve_id) as nb_eleves
     FROM devoirs_ecole d
     JOIN classes_ecole c ON c.id=d.classe_id
     LEFT JOIN classe_membres cm ON cm.classe_id=c.id AND cm.statut='ACTIF'
     WHERE d.id=? AND d.admin_id=? AND d.actif=1
     GROUP BY d.id",
    [$devoirId, $user['id']]
);
if (!$devoir) redirect('/reussiteplus/ecole_devoirs.php', 'error', 'Devoir introuvable.');

// ── Export CSV ────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = dbAll(
        "SELECT u.prenom, u.nom, u.email,
                sd.statut, sd.note, sd.note_ia, sd.feedback, sd.feedback_ia,
                sd.reponse_texte, sd.fichier_nom, sd.soumis_le, sd.corrige_le
         FROM classe_membres cm
         JOIN utilisateurs u ON u.id=cm.eleve_id
         LEFT JOIN soumissions_devoirs sd ON sd.devoir_id=? AND sd.eleve_id=u.id
         WHERE cm.classe_id=? AND cm.statut='ACTIF'
         ORDER BY u.nom",
        [$devoirId, $devoir['classe_id']]
    ) ?? [];
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="corrections_'.date('Y-m-d').'.csv"');
    $tmp = fopen('php://output', 'w');
    fwrite($tmp, "\xEF\xBB\xBF");
    fputcsv($tmp, ['Prénom','Nom','Email','Statut','Note','Note IA','Feedback','Feedback IA','Réponse texte','Fichier','Soumis le','Corrigé le'], ';');
    foreach ($rows as $r) fputcsv($tmp, array_values($r), ';');
    fclose($tmp);
    exit;
}

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { http_response_code(403); exit; }
    $action = $_POST['action'] ?? '';

    // Correction manuelle
    if ($action === 'corriger') {
        $eleveId  = $_POST['eleve_id']  ?? '';
        $note     = $_POST['note']      !== '' ? (float)$_POST['note'] : null;
        $feedback = trim($_POST['feedback'] ?? '');
        if ($eleveId) {
            $existe = dbRow("SELECT id FROM soumissions_devoirs WHERE devoir_id=? AND eleve_id=?", [$devoirId, $eleveId]);
            if ($existe) {
                dbQuery(
                    "UPDATE soumissions_devoirs SET note=?, feedback=?, statut='CORRIGE', corrige_par=?, corrige_le=NOW()
                     WHERE devoir_id=? AND eleve_id=?",
                    [$note, $feedback ?: null, $user['id'], $devoirId, $eleveId]
                );
            }
            dbInsert('notifications', [
                'user_id' => $eleveId,
                'type'    => 'DEVOIR',
                'titre'   => 'Devoir corrigé',
                'message' => "Votre devoir « {$devoir['titre']} » a été corrigé." . ($note !== null ? " Note : {$note}/{$devoir['points_max']}." : ''),
                'lien'    => '/reussiteplus/mes_devoirs.php',
            ]);
        }
        redirect('/reussiteplus/ecole_correction.php?devoir=' . urlencode($devoirId), 'success', 'Correction enregistrée.');
    }

    // Correction IA automatique (pour une soumission)
    if ($action === 'ia_corriger_un') {
        $eleveId = $_POST['eleve_id'] ?? '';
        $sd = dbRow(
            "SELECT sd.*, u.prenom FROM soumissions_devoirs sd JOIN utilisateurs u ON u.id=sd.eleve_id
             WHERE sd.devoir_id=? AND sd.eleve_id=?",
            [$devoirId, $eleveId]
        );
        if ($sd) {
            $geminiKey = $_ENV['GEMINI_API_KEY'] ?? '';
            $ghToken   = $_ENV['GITHUB_TOKEN']   ?? '';
            $systemP   = "Tu es un enseignant expert pour le programme EPST en RDC. Analyse la réponse de l'élève et génère : 1) une note sur {$devoir['points_max']} (chiffre seul), 2) un feedback bienveillant de 2-3 phrases. Format : NOTE:X.X\nFEEDBACK:texte";
            $prompt    = "Devoir : {$devoir['titre']} ({$devoir['type_devoir']}, matière : {$devoir['matiere']}).\n"
                       . "Réponse de l'élève : " . ($sd['reponse_texte'] ?: '(fichier joint — analyse sans la réponse texte)') . "\n"
                       . "Commentaire : " . ($sd['commentaire'] ?? '');
            $messages  = [['role'=>'system','content'=>$systemP],['role'=>'user','content'=>$prompt]];
            $response  = null;
            if ($geminiKey) {
                $payload = json_encode(['systemInstruction'=>['parts'=>[['text'=>$systemP]]],'contents'=>[['role'=>'user','parts'=>[['text'=>$prompt]]]],'generationConfig'=>['maxOutputTokens'=>200,'temperature'=>0.4]]);
                $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}");
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload,CURLOPT_TIMEOUT=>20]);
                $raw = curl_exec($ch); $code = curl_getinfo($ch,CURLINFO_HTTP_CODE); unset($ch);
                if ($raw && $code!==429) { $d=json_decode($raw,true); $response=$d['candidates'][0]['content']['parts'][0]['text']??null; }
            }
            if (!$response && $ghToken && $ghToken!=='COLLE_TON_PAT_ICI') {
                $ch2=curl_init('https://models.inference.ai.azure.com/chat/completions');
                curl_setopt_array($ch2,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$ghToken],CURLOPT_POSTFIELDS=>json_encode(['model'=>'gpt-4o-mini','messages'=>$messages,'max_tokens'=>200,'temperature'=>0.4]),CURLOPT_TIMEOUT=>20]);
                $raw2=curl_exec($ch2); $code2=curl_getinfo($ch2,CURLINFO_HTTP_CODE); unset($ch2);
                if ($raw2&&$code2===200) { $response=json_decode($raw2,true)['choices'][0]['message']['content']??null; }
            }
            if ($response) {
                preg_match('/NOTE\s*:\s*([\d.,]+)/i', $response, $noteM);
                preg_match('/FEEDBACK\s*:\s*(.+)/is', $response, $fbM);
                $noteIA = isset($noteM[1]) ? min((float)str_replace(',','.',$noteM[1]), (float)$devoir['points_max']) : null;
                $feedbackIA = trim($fbM[1] ?? $response);
                dbQuery(
                    "UPDATE soumissions_devoirs SET feedback_ia=?, note_ia=? WHERE devoir_id=? AND eleve_id=?",
                    [$feedbackIA, $noteIA, $devoirId, $eleveId]
                );
            }
        }
        redirect('/reussiteplus/ecole_correction.php?devoir=' . urlencode($devoirId), 'success', 'Analyse IA générée.');
    }

    // Tout corriger avec l'IA
    if ($action === 'ia_tout') {
        redirect('/reussiteplus/ecole_correction.php?devoir=' . urlencode($devoirId) . '&ia_all=1', 'success', 'Analyse IA lancée pour toutes les soumissions.');
    }
}

// ── Données ───────────────────────────────────────────────────
$soumissions = dbAll(
    "SELECT u.id as eleve_id, u.prenom, u.nom, u.email,
            sd.id as sd_id, sd.statut, sd.note, sd.note_ia,
            sd.feedback, sd.feedback_ia, sd.reponse_texte,
            sd.fichier_url, sd.fichier_nom, sd.commentaire,
            sd.soumis_le, sd.corrige_le
     FROM classe_membres cm
     JOIN utilisateurs u ON u.id=cm.eleve_id
     LEFT JOIN soumissions_devoirs sd ON sd.devoir_id=? AND sd.eleve_id=u.id
     WHERE cm.classe_id=? AND cm.statut='ACTIF'
     ORDER BY sd.statut DESC, u.nom",
    [$devoirId, $devoir['classe_id']]
) ?? [];

$nbSoumis   = count(array_filter($soumissions, fn($s)=>$s['sd_id']));
$nbCorriges = count(array_filter($soumissions, fn($s)=>$s['statut']==='CORRIGE'));
$nbIa       = count(array_filter($soumissions, fn($s)=>$s['feedback_ia']));
$noteMoyenne = $nbCorriges ? round(array_sum(array_filter(array_column($soumissions,'note'),fn($n)=>$n!==null)) / max(1,count(array_filter(array_column($soumissions,'note'),fn($n)=>$n!==null))),1) : null;

$typeConfig = ['DEVOIR'=>'#1E5FAD','CONTROLE'=>'#7C3AED','EXAM'=>'#C9342A','PROJET'=>'#059669','EXPOSE'=>'#C9972A'];
$typeColor  = $typeConfig[$devoir['type_devoir'] ?? 'DEVOIR'] ?? '#1E5FAD';

include __DIR__ . '/includes/header_app.php';
?>

<style>
.corr-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px; }
.corr-kpi { background:var(--blanc);border:1px solid var(--gris-200);border-radius:12px;padding:14px 16px;text-align:center; }
.corr-kpi-val { font-size:24px;font-weight:900;line-height:1.1; }
.corr-kpi-lbl { font-size:10.5px;color:var(--gris-500);margin-top:3px;text-transform:uppercase;letter-spacing:.4px; }
.student-card { background:var(--blanc);border:1px solid var(--gris-200);border-radius:14px;overflow:hidden;margin-bottom:14px;transition:box-shadow .2s; }
.student-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.08); }
.student-head { padding:14px 18px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--gris-100); }
.student-av { width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex-shrink:0; }
.student-body { padding:14px 18px; }
.response-box { background:var(--gris-50);border:1px solid var(--gris-200);border-radius:8px;padding:12px;font-size:13px;line-height:1.6;color:var(--gris-800);white-space:pre-wrap;max-height:120px;overflow-y:auto;margin-bottom:10px; }
.ia-feedback-box { background:#F5F3FF;border:1px solid rgba(124,58,237,.2);border-radius:8px;padding:12px;font-size:13px;line-height:1.6;color:#4B1D9B;margin-bottom:10px; }
.form-note { display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-top:8px; }
.form-note input[type=number] { width:80px; }
.status-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
</style>

<!-- Breadcrumb + Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:var(--radius-xl);padding:22px 24px;margin-bottom:20px">
  <div style="font-size:11px;color:rgba(255,255,255,.4);margin-bottom:6px">
    <a href="/reussiteplus/ecole_devoirs.php" style="color:rgba(255,255,255,.4);text-decoration:none">Devoirs</a>
    <span style="margin:0 6px">›</span> Correction
  </div>
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
    <div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
        <span style="background:<?= $typeColor ?>22;color:<?= $typeColor ?>;font-size:11px;font-weight:800;padding:2px 10px;border-radius:6px"><?= e($devoir['type_devoir'] ?? 'DEVOIR') ?></span>
        <span style="font-size:11px;color:rgba(255,255,255,.45)"><?= e($devoir['classe_nom']) ?></span>
      </div>
      <div style="font-size:20px;font-weight:900;color:#fff"><?= e($devoir['titre']) ?></div>
      <?php if ($devoir['date_remise']): ?>
      <div style="font-size:12px;color:rgba(255,255,255,.5);margin-top:3px">Date limite : <?= date('d/m/Y', strtotime($devoir['date_remise'])) ?></div>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="?devoir=<?= urlencode($devoirId) ?>&export=csv" style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.85);border-radius:8px;padding:8px 14px;font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:5px">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="13" x2="12" y2="19"/><polyline points="9 16 12 19 15 16"/></svg>
        Export CSV
      </a>
    </div>
  </div>
</div>

<!-- KPI -->
<div class="corr-grid">
  <div class="corr-kpi">
    <div class="corr-kpi-val" style="color:var(--gris-700)"><?= count($soumissions) ?></div>
    <div class="corr-kpi-lbl">Élèves</div>
  </div>
  <div class="corr-kpi">
    <div class="corr-kpi-val" style="color:#1E5FAD"><?= $nbSoumis ?></div>
    <div class="corr-kpi-lbl">Soumissions</div>
  </div>
  <div class="corr-kpi">
    <div class="corr-kpi-val" style="color:#007A5E"><?= $nbCorriges ?></div>
    <div class="corr-kpi-lbl">Corrigés</div>
  </div>
  <div class="corr-kpi">
    <div class="corr-kpi-val" style="color:#C9972A"><?= $noteMoyenne !== null ? $noteMoyenne . '/' . $devoir['points_max'] : '—' ?></div>
    <div class="corr-kpi-lbl">Moyenne classe</div>
  </div>
</div>

<?php foreach ($soumissions as $s):
    $initial   = strtoupper(substr($s['prenom'],0,1));
    $hasSoumis = (bool)$s['sd_id'];
    $statusStyle = match($s['statut']) {
        'CORRIGE'   => ['bg'=>'#D1FAE5','c'=>'#065F46','dot'=>'#007A5E','label'=>'Corrigé'],
        'EN_RETARD' => ['bg'=>'#FEE2E2','c'=>'#7F1D1D','dot'=>'#C9342A','label'=>'En retard'],
        'SOUMIS'    => ['bg'=>'#DBEAFE','c'=>'#1e40af','dot'=>'#1E5FAD','label'=>'Soumis'],
        default     => ['bg'=>'#F3F4F6','c'=>'#6B7280','dot'=>'#9CA3AF','label'=>'Non soumis'],
    };
?>
<div class="student-card">
  <div class="student-head">
    <div class="student-av"><?= $initial ?></div>
    <div style="flex:1">
      <div style="font-size:14px;font-weight:700;color:var(--gris-900)"><?= e($s['prenom'] . ' ' . $s['nom']) ?></div>
      <div style="font-size:11px;color:var(--gris-500)"><?= e($s['email']) ?></div>
    </div>
    <span style="background:<?= $statusStyle['bg'] ?>;color:<?= $statusStyle['c'] ?>;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;display:inline-flex;align-items:center;gap:5px">
      <span class="status-dot" style="background:<?= $statusStyle['dot'] ?>"></span>
      <?= $statusStyle['label'] ?>
    </span>
    <?php if ($hasSoumis && $s['soumis_le']): ?>
    <span style="font-size:11px;color:var(--gris-400)"><?= date('d/m H:i', strtotime($s['soumis_le'])) ?></span>
    <?php endif; ?>
  </div>

  <?php if ($hasSoumis): ?>
  <div class="student-body">

    <?php if ($s['reponse_texte']): ?>
    <div style="font-size:11.5px;font-weight:700;color:var(--gris-600);margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px">Réponse de l'élève</div>
    <div class="response-box"><?= nl2br(e($s['reponse_texte'])) ?></div>
    <?php endif; ?>

    <?php if ($s['commentaire']): ?>
    <div style="font-size:11.5px;color:var(--gris-500);margin-bottom:8px">
      <strong>Commentaire :</strong> <?= e($s['commentaire']) ?>
    </div>
    <?php endif; ?>

    <?php if ($s['fichier_url']): ?>
    <a href="<?= e($s['fichier_url']) ?>" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:var(--gris-50);border:1px solid var(--gris-200);border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;color:var(--gris-700);text-decoration:none;margin-bottom:10px">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <?= e($s['fichier_nom'] ?? 'Fichier joint') ?>
    </a>
    <?php endif; ?>

    <!-- Feedback IA si disponible -->
    <?php if ($s['feedback_ia']): ?>
    <div style="font-size:11.5px;font-weight:700;color:#7C3AED;margin-bottom:5px;display:flex;align-items:center;gap:5px;text-transform:uppercase;letter-spacing:.4px">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
      Analyse IA <?= $s['note_ia'] !== null ? '— Note suggérée : <strong>'.$s['note_ia'].'/'.$devoir['points_max'].'</strong>' : '' ?>
    </div>
    <div class="ia-feedback-box"><?= nl2br(e($s['feedback_ia'])) ?></div>
    <?php endif; ?>

    <!-- Correction enseignant ou bouton IA -->
    <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap">
      <!-- Formulaire correction manuelle -->
      <form method="POST" style="flex:1;min-width:280px">
        <?= csrf_field() ?>
        <input type="hidden" name="action"   value="corriger">
        <input type="hidden" name="eleve_id" value="<?= e($s['eleve_id']) ?>">
        <div class="form-note">
          <div>
            <label style="font-size:11px;font-weight:700;color:var(--gris-600);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Note / <?= $devoir['points_max'] ?></label>
            <input type="number" name="note" class="form-control" step="0.5" min="0" max="<?= $devoir['points_max'] ?>"
                   value="<?= $s['note'] !== null ? $s['note'] : ($s['note_ia'] !== null ? $s['note_ia'] : '') ?>"
                   placeholder="—" style="width:80px">
          </div>
          <div style="flex:1">
            <label style="font-size:11px;font-weight:700;color:var(--gris-600);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px">Feedback enseignant</label>
            <textarea name="feedback" class="form-control" rows="2" placeholder="Commentaire de correction…" style="resize:vertical;font-size:13px"><?= e($s['feedback'] ?? $s['feedback_ia'] ?? '') ?></textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-1px;margin-right:4px"><polyline points="20 6 9 17 4 12"/></svg>
          Valider la correction
        </button>
      </form>

      <!-- Bouton IA auto-correction -->
      <?php if (!$s['feedback_ia']): ?>
      <form method="POST" style="align-self:flex-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action"   value="ia_corriger_un">
        <input type="hidden" name="eleve_id" value="<?= e($s['eleve_id']) ?>">
        <button type="submit" style="background:linear-gradient(135deg,#7C3AED,#4F46E5);color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;font-family:inherit;white-space:nowrap">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
          Analyser avec l'IA
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div style="padding:14px 18px;font-size:13px;color:var(--gris-400);font-style:italic">Aucune soumission pour ce devoir.</div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (!$soumissions): ?>
<div class="card" style="text-align:center;padding:48px">
  <div style="font-size:14px;font-weight:600;color:var(--gris-600)">Aucun élève dans cette classe.</div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
