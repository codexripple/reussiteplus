<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Abonnements & Paiements';
$pageActive = 'admin_paiements';
$user = require_admin();

// Actions confirmer / refuser
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $id     = $_GET['id'];
    $abon = dbRow("SELECT a.*, u.id as uid, u.prenom FROM abonnements a JOIN utilisateurs u ON a.user_id=u.id WHERE a.id=?", [$id]);
    if (!$abon) redirect('/reussiteplus/admin/paiements.php', 'error', 'Paiement introuvable.');
    if ($action === 'confirmer' && $abon['statut'] === 'EN_ATTENTE') {
        dbQuery("UPDATE abonnements SET statut='CONFIRME', confirmed_at=NOW(), confirmed_by=? WHERE id=?", [$user['id'], $id]);
        dbQuery("UPDATE utilisateurs SET plan=?, plan_expire_at=? WHERE id=?", [$abon['plan'], $abon['date_fin'], $abon['uid']]);
        $dateFinFmt = $abon['date_fin'] ? date('d/m/Y', strtotime($abon['date_fin'])) : '?';
        dbInsert('notifications', [
            'user_id' => $abon['uid'],
            'type'    => 'PAIEMENT',
            'titre'   => 'Abonnement ' . $abon['plan'] . ' activé !',
            'message' => 'Votre paiement a été confirmé. Votre abonnement ' . $abon['plan'] . ' est actif jusqu\'au ' . $dateFinFmt . '.',
            'lien'    => '/reussiteplus/abonnement.php',
        ]);
        dbInsert('admin_logs', ['admin_id'=>$user['id'],'action'=>'CONFIRMER_PAIEMENT','details'=>"ID=$id"]);
        redirect('/reussiteplus/admin/paiements.php', 'success', 'Paiement confirmé et plan activé.');
    } elseif ($action === 'refuser' && $abon['statut'] === 'EN_ATTENTE') {
        dbQuery("UPDATE abonnements SET statut='ECHEC' WHERE id=?", [$id]);
        dbInsert('notifications', [
            'user_id' => $abon['uid'],
            'type'    => 'PAIEMENT',
            'titre'   => 'Paiement non vérifié',
            'message' => 'Votre paiement n\'a pas pu être vérifié. Contactez support@reussiteplus.cd.',
            'lien'    => '/reussiteplus/abonnement.php',
        ]);
        dbInsert('admin_logs', ['admin_id'=>$user['id'],'action'=>'REFUSER_PAIEMENT','details'=>"ID=$id"]);
        redirect('/reussiteplus/admin/paiements.php', 'success', 'Paiement refusé.');
    }
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = dbAll("SELECT a.reference_paiement, u.prenom, u.nom, u.email, a.plan, a.montant, a.devise, a.methode_paiement, a.telephone, a.statut, a.date_debut, a.date_fin, a.created_at FROM abonnements a JOIN utilisateurs u ON a.user_id=u.id ORDER BY a.created_at DESC") ?? [];
    $tmp = fopen('php://temp', 'r+');
    fwrite($tmp, "\xEF\xBB\xBF");
    fputcsv($tmp, ['Reference','Prenom','Nom','Email','Plan','Montant','Devise','Methode','Telephone','Statut','Debut','Fin','Date'], ';');
    foreach ($rows as $r) fputcsv($tmp, array_values($r), ';');
    rewind($tmp); $csv = stream_get_contents($tmp); fclose($tmp);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="paiements_'.date('Y-m-d').'.csv"');
    header('Content-Length: '.strlen($csv));
    echo $csv; exit;
}

// Stats globales
$sg = dbRow("SELECT COUNT(*) AS total_paiements, SUM(CASE WHEN statut='CONFIRME' THEN 1 ELSE 0 END) AS total_confirmes, SUM(CASE WHEN statut='EN_ATTENTE' THEN 1 ELSE 0 END) AS en_attente, SUM(CASE WHEN statut='CONFIRME' THEN montant ELSE 0 END) AS rev_total, SUM(CASE WHEN statut='CONFIRME' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) THEN montant ELSE 0 END) AS rev_mois, SUM(CASE WHEN statut='CONFIRME' AND date_fin >= CURDATE() THEN 1 ELSE 0 END) AS abonnes_actifs FROM abonnements") ?? [];

$parPlan = dbAll("SELECT plan, COUNT(*) as cnt, SUM(montant) as rev FROM abonnements WHERE statut='CONFIRME' AND date_fin >= CURDATE() GROUP BY plan") ?? [];
$parPlanMap = array_column($parPlan, null, 'plan');

$usersPlan = dbAll("SELECT plan, COUNT(*) as cnt FROM utilisateurs GROUP BY plan") ?? [];
$usersPlanMap = array_column($usersPlan, null, 'plan');
$totalUsers = array_sum(array_column($usersPlan, 'cnt')) ?: 1;

$parMethode = dbAll("SELECT methode_paiement, COUNT(*) as cnt, SUM(montant) as rev FROM abonnements WHERE statut='CONFIRME' GROUP BY methode_paiement ORDER BY rev DESC") ?? [];

$revMois = array_reverse(dbAll("SELECT DATE_FORMAT(created_at,'%Y-%m') as mois, DATE_FORMAT(created_at,'%b %y') as label, COUNT(*) as cnt, SUM(montant) as rev FROM abonnements WHERE statut='CONFIRME' GROUP BY mois ORDER BY mois DESC LIMIT 6") ?? []);
$maxRev = max(array_column($revMois, 'rev') ?: [1]);

$expiresSoon = dbAll("SELECT u.prenom, u.nom, u.email, a.plan, a.date_fin FROM abonnements a JOIN utilisateurs u ON a.user_id=u.id WHERE a.statut='CONFIRME' AND a.date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY a.date_fin ASC LIMIT 8") ?? [];

// Liste filtree
$statut = $_GET['statut'] ?? 'EN_ATTENTE';
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$validStatuts = ['EN_ATTENTE','CONFIRME','ECHEC','REMBOURSE','TOUS'];
if (!in_array($statut, $validStatuts, true)) $statut = 'EN_ATTENTE';

$conds = []; $params = [];
if ($statut !== 'TOUS') { $conds[] = "a.statut=?"; $params[] = $statut; }
if ($search !== '') {
    $conds[] = "(u.email LIKE ? OR u.prenom LIKE ? OR u.nom LIKE ? OR a.reference_paiement LIKE ?)";
    $s = "%$search%"; array_push($params, $s, $s, $s, $s);
}
$where = $conds ? 'WHERE '.implode(' AND ', $conds) : '';
$total = (int)dbRow("SELECT COUNT(*) as n FROM abonnements a JOIN utilisateurs u ON a.user_id=u.id $where", $params)['n'];
$paiements = dbAll("SELECT a.*, u.email, u.prenom, u.nom FROM abonnements a JOIN utilisateurs u ON a.user_id=u.id $where ORDER BY a.created_at DESC LIMIT $limit OFFSET ".(($page-1)*$limit), $params);
$pagination = paginate($total, $page, $limit);

$cntStatuts = [];
foreach ($validStatuts as $s) {
    $cntStatuts[$s] = $s === 'TOUS' ? (int)dbRow("SELECT COUNT(*) as n FROM abonnements")['n'] : (int)dbRow("SELECT COUNT(*) as n FROM abonnements WHERE statut=?", [$s])['n'];
}

$planColors  = ['BASIQUE'=>'#1E5FAD','PREMIUM'=>'#C9972A','ECOLE'=>'#007A5E','GRATUIT'=>'#6B7280'];
$methodIcons = ['MPESA'=>['M','#00A651'],'AIRTEL_MONEY'=>['A','#E40613'],'ORANGE_MONEY'=>['O','#FF6600'],'CARTE'=>['C','#5046E4'],'VIREMENT'=>['V','#0EA5E9'],'ADMIN'=>['X','#6B7280']];

include __DIR__ . '/../includes/header_app.php';
?>
<style>
.ps-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.ps-card{background:#fff;border-radius:14px;padding:20px 22px;border:1px solid var(--gris-200);position:relative;overflow:hidden}
.ps-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--cc,var(--primary))}
.ps-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:var(--cb,rgba(0,122,94,.1));margin-bottom:14px}
.ps-val{font-size:26px;font-weight:800;color:var(--gris-900);line-height:1}
.ps-lbl{font-size:12px;color:var(--gris-500);margin-top:4px}
.ps-sub{font-size:11px;color:var(--gris-400);margin-top:6px}
.ps-2col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
.pcard{background:#fff;border-radius:14px;border:1px solid var(--gris-200);overflow:hidden}
.pcard-h{padding:14px 20px;border-bottom:1px solid var(--gris-100);display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:var(--gris-900)}
.pcard-b{padding:18px 20px}
.pbar{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.pbar-lbl{width:70px;font-size:12px;font-weight:600}
.pbar-track{flex:1;height:7px;background:var(--gris-100);border-radius:4px;overflow:hidden}
.pbar-fill{height:100%;border-radius:4px}
.rbar-row{display:flex;align-items:flex-end;gap:5px;height:90px}
.rbar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px}
.rbar-block{width:100%;background:var(--primary);border-radius:5px 5px 0 0;min-height:4px}
.rbar-lbl{font-size:9px;color:var(--gris-500);text-align:center;line-height:1.2}
.rbar-v{font-size:9px;font-weight:700;color:var(--gris-600)}
.tab-pills{display:flex;gap:6px;flex-wrap:wrap;padding:12px 20px 0;border-bottom:1px solid var(--gris-100)}
.tab-p{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;border:1.5px solid var(--gris-200);color:var(--gris-600);background:#fff}
.tab-p:hover{border-color:var(--primary);color:var(--primary)}
.tab-p.act{background:var(--primary);color:#fff;border-color:var(--primary)}
.tab-p.act-y{background:#F59E0B;color:#fff;border-color:#F59E0B}
.tab-p.act-r{background:#EF4444;color:#fff;border-color:#EF4444}
.exp-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gris-100)}
.exp-row:last-child{border-bottom:none}
@media(max-width:1024px){.ps-grid{grid-template-columns:repeat(2,1fr)}.ps-2col{grid-template-columns:1fr}}
</style>

<?= show_flash() ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h2 style="font-size:20px;font-weight:800;color:var(--gris-900);margin:0">Abonnements &amp; Paiements</h2>
    <p style="font-size:13px;color:var(--gris-500);margin:4px 0 0">Vue d&apos;ensemble des abonn&eacute;s, revenus et transactions</p>
  </div>
  <a href="?export=csv" class="btn btn-ghost btn-sm" style="gap:6px">
    <i data-lucide="download" style="width:14px;height:14px"></i> Exporter CSV
  </a>
</div>

<!-- 4 stat cards -->
<div class="ps-grid">
  <div class="ps-card" style="--cc:#007A5E;--cb:rgba(0,122,94,.1)">
    <div class="ps-icon"><i data-lucide="users" style="width:20px;height:20px;stroke:#007A5E"></i></div>
    <div class="ps-val"><?php echo number_format((int)$sg['abonnes_actifs']); ?></div>
    <div class="ps-lbl">Abonn&eacute;s actifs</div>
    <div class="ps-sub"><?php echo (int)($usersPlanMap['GRATUIT']['cnt'] ?? 0); ?> utilisateurs gratuits</div>
  </div>
  <div class="ps-card" style="--cc:#C9972A;--cb:rgba(201,151,42,.1)">
    <div class="ps-icon"><i data-lucide="trending-up" style="width:20px;height:20px;stroke:#C9972A"></i></div>
    <div class="ps-val"><?php echo number_format((float)$sg['rev_mois'], 0, ',', ' '); ?></div>
    <div class="ps-lbl">Revenus ce mois (CDF)</div>
    <div class="ps-sub">Total : <?php echo number_format((float)$sg['rev_total'], 0, ',', ' '); ?> CDF</div>
  </div>
  <div class="ps-card" style="--cc:#F59E0B;--cb:rgba(245,158,11,.1)">
    <div class="ps-icon"><i data-lucide="clock" style="width:20px;height:20px;stroke:#F59E0B"></i></div>
    <div class="ps-val"><?php echo (int)$sg['en_attente']; ?></div>
    <div class="ps-lbl">En attente de confirmation</div>
    <?php if ((int)$sg['en_attente'] > 0): ?>
    <div class="ps-sub" style="color:#F59E0B;font-weight:700">Action requise !</div>
    <?php else: ?><div class="ps-sub">Tout est &agrave; jour</div><?php endif; ?>
  </div>
  <div class="ps-card" style="--cc:#5046E4;--cb:rgba(80,70,228,.1)">
    <div class="ps-icon"><i data-lucide="credit-card" style="width:20px;height:20px;stroke:#5046E4"></i></div>
    <div class="ps-val"><?php echo (int)$sg['total_confirmes']; ?></div>
    <div class="ps-lbl">Paiements confirm&eacute;s</div>
    <div class="ps-sub">Sur <?php echo (int)$sg['total_paiements']; ?> total</div>
  </div>
</div>

<!-- 2 col section -->
<div class="ps-2col">
  <!-- Plans -->
  <div class="pcard">
    <div class="pcard-h"><i data-lucide="pie-chart" style="width:15px;height:15px;stroke:var(--primary)"></i> Abonn&eacute;s par plan</div>
    <div class="pcard-b">
      <?php foreach (['ECOLE','PREMIUM','BASIQUE','GRATUIT'] as $pk):
        $cnt = (int)($usersPlanMap[$pk]['cnt'] ?? 0);
        $pct = round($cnt / $totalUsers * 100);
        $color = $planColors[$pk] ?? '#888';
        $nom = PLANS[$pk]['nom'] ?? $pk;
      ?>
      <div class="pbar">
        <div class="pbar-lbl" style="color:<?php echo $color; ?>"><?php echo $nom; ?></div>
        <div class="pbar-track"><div class="pbar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $color; ?>"></div></div>
        <div style="width:30px;text-align:right;font-size:12px;font-weight:700;color:var(--gris-700)"><?php echo $cnt; ?></div>
        <div style="width:30px;text-align:right;font-size:10px;color:var(--gris-400)"><?php echo $pct; ?>%</div>
      </div>
      <?php endforeach; ?>
      <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--gris-100)">
        <div style="font-size:11px;font-weight:700;color:var(--gris-400);text-transform:uppercase;margin-bottom:8px">Revenus confirm&eacute;s par plan</div>
        <?php foreach (['ECOLE','PREMIUM','BASIQUE'] as $pk):
          $rev = (float)($parPlanMap[$pk]['rev'] ?? 0);
          if ($rev == 0) continue;
        ?>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
          <span style="color:var(--gris-600)"><?php echo PLANS[$pk]['nom']; ?></span>
          <span style="font-weight:700;color:<?php echo $planColors[$pk]; ?>"><?php echo number_format($rev, 0, ',', ' '); ?> CDF</span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($parPlanMap)): ?>
        <div style="font-size:12px;color:var(--gris-400)">Aucun revenu encore</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Revenus -->
  <div class="pcard">
    <div class="pcard-h"><i data-lucide="bar-chart-2" style="width:15px;height:15px;stroke:var(--primary)"></i> Revenus 6 derniers mois</div>
    <div class="pcard-b">
      <?php if ($revMois): ?>
      <div class="rbar-row">
        <?php foreach ($revMois as $m):
          $h = $maxRev > 0 ? max(4, round((float)$m['rev'] / $maxRev * 80)) : 4;
        ?>
        <div class="rbar-col">
          <div class="rbar-v"><?php echo number_format((float)$m['rev']/1000,0); ?>k</div>
          <div class="rbar-block" style="height:<?php echo $h; ?>px"></div>
          <div class="rbar-lbl"><?php echo $m['label']; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:20px;color:var(--gris-400);font-size:13px">Aucun revenu confirm&eacute;</div>
      <?php endif; ?>

      <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--gris-100)">
        <div style="font-size:11px;font-weight:700;color:var(--gris-400);text-transform:uppercase;margin-bottom:8px">Par m&eacute;thode</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($parMethode as $m):
          $mi = $methodIcons[$m['methode_paiement']] ?? ['?','#888'];
          $nomM = METHODES_PAIEMENT[$m['methode_paiement']]['nom'] ?? $m['methode_paiement'];
        ?>
          <div style="background:var(--gris-50);border:1px solid var(--gris-200);border-radius:10px;padding:8px 12px;flex:1;min-width:100px">
            <div style="display:flex;align-items:center;gap:5px;margin-bottom:3px">
              <span style="width:20px;height:20px;border-radius:50%;background:<?php echo $mi[1]; ?>;color:#fff;font-size:10px;font-weight:900;display:flex;align-items:center;justify-content:center"><?php echo $mi[0]; ?></span>
              <span style="font-size:11px;font-weight:600;color:var(--gris-700)"><?php echo $nomM; ?></span>
            </div>
            <div style="font-size:13px;font-weight:800;color:var(--gris-900)"><?php echo number_format((float)$m['rev'],0,',',' '); ?> <span style="font-size:9px;color:var(--gris-400)">CDF</span></div>
            <div style="font-size:10px;color:var(--gris-400)"><?php echo $m['cnt']; ?> transactions</div>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Expirations -->
<?php if ($expiresSoon): ?>
<div class="pcard" style="margin-bottom:24px">
  <div class="pcard-h" style="background:rgba(245,158,11,.06)">
    <i data-lucide="alert-triangle" style="width:15px;height:15px;stroke:#F59E0B"></i>
    <span style="color:#92400E">Expirations dans 30 jours (<?php echo count($expiresSoon); ?>)</span>
  </div>
  <div style="padding:8px 20px">
  <?php foreach ($expiresSoon as $e):
    $jours = max(0, (int)round((strtotime($e['date_fin']) - time()) / 86400));
    $color = $planColors[$e['plan']] ?? '#888';
  ?>
  <div class="exp-row">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:32px;height:32px;border-radius:50%;background:<?php echo $color; ?>22;color:<?php echo $color; ?>;font-weight:800;font-size:13px;display:flex;align-items:center;justify-content:center"><?php echo strtoupper(substr($e['prenom'],0,1)); ?></div>
      <div>
        <div style="font-size:13px;font-weight:600"><?php echo e($e['prenom'].' '.$e['nom']); ?></div>
        <div style="font-size:11px;color:var(--gris-400)"><?php echo e($e['email']); ?></div>
      </div>
    </div>
    <div style="text-align:right">
      <?php echo badge_plan($e['plan']); ?>
      <div style="font-size:11px;margin-top:3px;color:<?php echo $jours<=7?'#EF4444':'#F59E0B'; ?>;font-weight:700"><?php echo $jours===0?'Expir&eacute; !':"Dans $jours j."; ?></div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Liste transactions -->
<div class="card">
  <div class="card-header" style="flex-wrap:wrap;gap:10px">
    <div class="card-title"><i data-lucide="list" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px"></i> Transactions</div>
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="statut" value="<?php echo e($statut); ?>">
      <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Email, nom, r&eacute;f&eacute;rence..."
        style="border:1px solid var(--gris-200);border-radius:8px;padding:6px 12px;font-size:12px;width:200px;outline:none">
      <button type="submit" class="btn btn-ghost btn-sm">Rechercher</button>
      <?php if ($search): ?><a href="?statut=<?php echo $statut; ?>" class="btn btn-ghost btn-sm">&#x2715;</a><?php endif; ?>
    </form>
  </div>

  <div class="tab-pills">
    <?php
    $tabCls = ['EN_ATTENTE'=>'act-y','CONFIRME'=>'act','ECHEC'=>'act-r','REMBOURSE'=>'act','TOUS'=>'act'];
    $tabNom = ['EN_ATTENTE'=>'En attente','CONFIRME'=>'Confirm&eacute;s','ECHEC'=>'&Eacute;chou&eacute;s','REMBOURSE'=>'Rembours&eacute;s','TOUS'=>'Tous'];
    foreach ($validStatuts as $s):
      $cls = 'tab-p'.($statut===$s ? ' '.($tabCls[$s]??'act') : '');
    ?>
    <a href="?statut=<?php echo $s; ?><?php echo $search?"&q=".urlencode($search):''; ?>" class="<?php echo $cls; ?>">
      <?php echo $tabNom[$s]; ?> <span style="opacity:.65">(<?php echo $cntStatuts[$s]; ?>)</span>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($paiements): ?>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>R&eacute;f&eacute;rence</th><th>Utilisateur</th><th>Plan</th><th>Montant</th><th>M&eacute;thode</th><th>P&eacute;riode</th><th>Statut</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($paiements as $p):
        $sc = ['EN_ATTENTE'=>['#FEF3C7','#92400E'],'CONFIRME'=>['#D1FAE5','#064E3B'],'ECHEC'=>['#FEE2E2','#7F1D1D'],'REMBOURSE'=>['#EDE9FE','#4C1D95']];
        $c  = $sc[$p['statut']] ?? $sc['EN_ATTENTE'];
        $mi = $methodIcons[$p['methode_paiement']] ?? ['?','#888'];
        $nomM = METHODES_PAIEMENT[$p['methode_paiement']]['nom'] ?? $p['methode_paiement'];
      ?>
      <tr>
        <td style="font-family:monospace;font-size:11px;color:var(--gris-500)"><?php echo e($p['reference_paiement'] ?: '---'); ?></td>
        <td>
          <div style="font-size:13px;font-weight:600"><?php echo e($p['prenom'].' '.$p['nom']); ?></div>
          <div style="font-size:11px;color:var(--gris-400)"><?php echo e($p['email']); ?></div>
        </td>
        <td><?php echo badge_plan($p['plan']); ?></td>
        <td style="font-weight:700;white-space:nowrap">
          <?php echo number_format((float)$p['montant'],0,',',' '); ?> <span style="font-size:10px;color:var(--gris-400)"><?php echo e($p['devise']); ?></span>
          <?php if ((float)$p['remise'] > 0): ?><div style="font-size:10px;color:var(--primary)">-<?php echo number_format((float)$p['remise'],0); ?> promo</div><?php endif; ?>
        </td>
        <td>
          <span style="display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:700;background:<?php echo $mi[1]; ?>22;color:<?php echo $mi[1]; ?>">
            <?php echo $mi[0]; ?> <?php echo $nomM; ?>
          </span>
          <?php if ($p['telephone']): ?><div style="font-size:10px;color:var(--gris-400);margin-top:2px"><?php echo e($p['telephone']); ?></div><?php endif; ?>
        </td>
        <td style="font-size:11px;color:var(--gris-500);white-space:nowrap">
          <?php echo $p['date_debut'] ? date('d/m/Y', strtotime($p['date_debut'])) : '---'; ?>
          <?php if ($p['date_fin']): ?><br><span style="color:var(--gris-400)">&#8594; <?php echo date('d/m/Y', strtotime($p['date_fin'])); ?></span><?php endif; ?>
          <?php if ($p['duree_mois']): ?><br><span style="color:var(--primary);font-weight:700"><?php echo $p['duree_mois']; ?> mois</span><?php endif; ?>
        </td>
        <td>
          <span style="background:<?php echo $c[0]; ?>;color:<?php echo $c[1]; ?>;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:800"><?php echo $p['statut']; ?></span>
          <div style="font-size:10px;color:var(--gris-400);margin-top:3px"><?php echo temps_relatif($p['created_at']); ?></div>
        </td>
        <td>
          <?php if ($p['statut'] === 'EN_ATTENTE'): ?>
          <div style="display:flex;gap:4px">
            <a href="?action=confirmer&id=<?php echo e($p['id']); ?>&statut=<?php echo $statut; ?>" class="btn btn-primary btn-sm" onclick="return confirm('Confirmer ce paiement ?')" title="Confirmer">
              <i data-lucide="check" style="width:13px;height:13px"></i>
            </a>
            <a href="?action=refuser&id=<?php echo e($p['id']); ?>&statut=<?php echo $statut; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Refuser ce paiement ?')" title="Refuser">
              <i data-lucide="x" style="width:13px;height:13px"></i>
            </a>
          </div>
          <?php else: ?><span style="color:var(--gris-300)">---</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pagination['pages'] > 1): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-top:1px solid var(--gris-100)">
    <div style="font-size:12px;color:var(--gris-400)"><?php echo $total; ?> r&eacute;sultats &mdash; page <?php echo $page; ?>/<?php echo $pagination['pages']; ?></div>
    <div style="display:flex;gap:4px">
      <?php for ($i=1; $i<=$pagination['pages']; $i++): ?>
      <a href="?statut=<?php echo $statut; ?>&page=<?php echo $i; ?><?php echo $search?"&q=".urlencode($search):''; ?>" class="btn <?php echo $i==$page?'btn-primary':'btn-ghost'; ?> btn-sm" style="min-width:32px"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php else: ?>
  <div style="text-align:center;padding:48px 20px">
    <i data-lucide="inbox" style="width:36px;height:36px;stroke:var(--gris-300);margin-bottom:10px"></i>
    <div style="font-size:14px;color:var(--gris-400)">Aucun paiement pour ce filtre</div>
    <?php if ($search): ?><a href="?statut=<?php echo $statut; ?>" style="font-size:12px;color:var(--primary);margin-top:8px;display:inline-block">Effacer la recherche</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>