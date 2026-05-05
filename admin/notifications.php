<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle  = 'Alertes';
$pageActive = 'admin_notifs';
$user = require_admin();

// ── Actions ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    // Marquer un message contact comme lu
    if ($action === 'mark_msg_read' && !empty($_POST['id'])) {
        dbQuery("UPDATE contact_messages SET statut='LU' WHERE id=?", [$_POST['id']]);
        redirect('/reussiteplus/admin/notifications.php', 'success', 'Message marqu&eacute; comme lu.');
    }
    // Valider un paiement
    if ($action === 'confirm_pay' && !empty($_POST['id'])) {
        dbQuery(
            "UPDATE abonnements SET statut='CONFIRME', confirmed_by=?, confirmed_at=NOW() WHERE id=? AND statut='EN_ATTENTE'",
            [$user['id'], $_POST['id']]
        );
        redirect('/reussiteplus/admin/notifications.php', 'success', 'Paiement valid&eacute;.');
    }
    // Rejeter un paiement
    if ($action === 'reject_pay' && !empty($_POST['id'])) {
        dbQuery(
            "UPDATE abonnements SET statut='ECHEC', confirmed_by=?, confirmed_at=NOW() WHERE id=? AND statut='EN_ATTENTE'",
            [$user['id'], $_POST['id']]
        );
        redirect('/reussiteplus/admin/notifications.php', 'warning', 'Paiement refus&eacute;.');
    }
}

// ── Données ───────────────────────────────────────────────
// 1. Paiements en attente
$paiementsAtt = dbAll(
    "SELECT a.*, u.prenom, u.nom, u.email, u.plan as plan_actuel
     FROM abonnements a
     JOIN utilisateurs u ON u.id = a.user_id
     WHERE a.statut = 'EN_ATTENTE'
     ORDER BY a.created_at DESC
     LIMIT 30"
) ?? [];

// 2. Nouveaux inscrits (48h)
$newUsers = dbAll(
    "SELECT id, prenom, nom, email, plan, role, created_at
     FROM utilisateurs
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
     ORDER BY created_at DESC
     LIMIT 20"
) ?? [];

// 3. Messages contact non lus
$messages = dbAll(
    "SELECT * FROM contact_messages
     WHERE statut = 'NOUVEAU'
     ORDER BY created_at DESC
     LIMIT 20"
) ?? [];

// 4. Activité examens du jour
$examsAujourdhui = (int)(dbRow("SELECT COUNT(*) as n FROM exam_sessions WHERE DATE(started_at)=CURDATE()")['n'] ?? 0);
$examsHier       = (int)(dbRow("SELECT COUNT(*) as n FROM exam_sessions WHERE DATE(started_at)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)")['n'] ?? 0);

// Totaux pour le header
$totalAlertes = count($paiementsAtt) + count($messages) + count($newUsers);

$PLANS = PLANS;

include __DIR__ . '/../includes/header_app.php';
?>
<?= show_flash() ?>

<style>
.aln-wrap { max-width:900px; margin:0 auto; }

.aln-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.aln-title { font-family:var(--font-display); font-size:22px; font-weight:800; color:var(--gris-900); }
.aln-title small { display:block; font-size:13px; font-weight:400; color:var(--gris-500); margin-top:2px; }

.aln-summary { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:28px; }
.aln-stat {
  background:white; border:1px solid var(--gris-200); border-radius:14px;
  padding:16px 18px; display:flex; align-items:center; gap:14px;
}
.aln-stat-icon {
  width:42px; height:42px; border-radius:10px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
}
.aln-stat-icon svg { width:20px; height:20px; }
.aln-stat-val { font-family:var(--font-display); font-size:22px; font-weight:900; color:var(--gris-900); line-height:1; }
.aln-stat-lbl { font-size:11px; color:var(--gris-500); margin-top:3px; }

.aln-section { margin-bottom:28px; }
.aln-section-head {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:14px; padding-bottom:10px;
  border-bottom:1px solid var(--gris-200);
}
.aln-section-title {
  display:flex; align-items:center; gap:10px;
  font-family:var(--font-display); font-size:15px; font-weight:700; color:var(--gris-900);
}
.aln-badge {
  font-size:10px; font-weight:800; padding:2px 8px; border-radius:10px; min-width:22px; text-align:center;
}

.aln-empty {
  text-align:center; padding:28px; color:var(--gris-500);
  font-size:13px; background:var(--gris-50); border-radius:12px; border:1px dashed var(--gris-200);
}
.aln-empty svg { width:36px; height:36px; stroke:var(--gris-300); display:block; margin:0 auto 10px; }

/* Cartes paiements */
.pay-card {
  background:white; border:1px solid var(--gris-200); border-radius:12px;
  padding:16px 18px; display:flex; align-items:center; gap:14px;
  margin-bottom:8px; transition:.15s;
}
.pay-card:hover { border-color:var(--primary); box-shadow:0 2px 12px rgba(0,122,94,.08); }
.pay-avatar {
  width:38px; height:38px; border-radius:50%; flex-shrink:0;
  background:linear-gradient(135deg,var(--primary),var(--gold));
  display:flex; align-items:center; justify-content:center;
  font-size:14px; font-weight:700; color:white;
}
.pay-info { flex:1; min-width:0; }
.pay-name { font-size:13px; font-weight:700; color:var(--gris-900); }
.pay-meta { font-size:11px; color:var(--gris-500); margin-top:2px; }
.pay-amount {
  font-family:var(--font-display); font-size:15px; font-weight:900;
  color:var(--gris-900); white-space:nowrap;
}
.pay-plan { font-size:10px; color:var(--gris-500); text-align:right; margin-top:2px; }
.pay-actions { display:flex; gap:8px; flex-shrink:0; }
.btn-xs {
  display:inline-flex; align-items:center; gap:5px;
  padding:5px 12px; border-radius:8px; font-size:11px; font-weight:700;
  border:none; cursor:pointer; transition:.15s; font-family:var(--font-body);
  text-decoration:none;
}
.btn-xs-green { background:#dcfce7; color:#15803d; }
.btn-xs-green:hover { background:#bbf7d0; }
.btn-xs-red { background:#fee2e2; color:#b91c1c; }
.btn-xs-red:hover { background:#fecaca; }
.pay-method-badge {
  display:inline-flex; align-items:center; gap:4px;
  font-size:10px; font-weight:700; padding:2px 8px; border-radius:6px;
  background:rgba(201,151,42,.12); color:var(--gold);
}

/* Cartes utilisateurs */
.user-card {
  background:white; border:1px solid var(--gris-200); border-radius:12px;
  padding:12px 16px; display:flex; align-items:center; gap:12px;
  margin-bottom:6px;
}
.user-card-avatar {
  width:34px; height:34px; border-radius:50%; flex-shrink:0;
  background:linear-gradient(135deg,#60a5fa,#a78bfa);
  display:flex; align-items:center; justify-content:center;
  font-size:12px; font-weight:700; color:white;
}
.user-card-name { font-size:13px; font-weight:600; color:var(--gris-900); }
.user-card-email { font-size:11px; color:var(--gris-500); }
.user-card-time { margin-left:auto; font-size:10px; color:var(--gris-400); white-space:nowrap; }

/* Cartes messages */
.msg-card {
  background:white; border:1px solid var(--gris-200); border-radius:12px;
  padding:14px 16px; margin-bottom:8px;
}
.msg-card-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
.msg-card-name { font-size:13px; font-weight:700; color:var(--gris-900); }
.msg-card-sujet { font-size:10px; font-weight:600; padding:2px 8px; border-radius:8px; background:var(--gris-100); color:var(--gris-600); }
.msg-card-body { font-size:12px; color:var(--gris-600); line-height:1.55; margin-bottom:10px; }
.msg-card-foot { display:flex; align-items:center; justify-content:space-between; gap:10px; }
.msg-card-time { font-size:10px; color:var(--gris-400); }
</style>

<div class="aln-wrap">

  <div class="aln-header">
    <div>
      <h1 class="aln-title">
        Alertes &amp; Activit&eacute;
        <small>Paiements, inscriptions, messages — tout ce qui n&eacute;cessite votre attention.</small>
      </h1>
    </div>
    <a href="/reussiteplus/admin/index.php" class="btn btn-ghost btn-sm">
      <i data-lucide="arrow-left" style="width:14px;height:14px"></i>
      Retour au tableau de bord
    </a>
  </div>

  <!-- Résumé -->
  <div class="aln-summary">
    <div class="aln-stat">
      <div class="aln-stat-icon" style="background:#FEF3C7">
        <svg viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      </div>
      <div>
        <div class="aln-stat-val" style="color:<?= count($paiementsAtt) > 0 ? '#D97706' : 'var(--gris-900)' ?>"><?= count($paiementsAtt) ?></div>
        <div class="aln-stat-lbl">Paiements en attente</div>
      </div>
    </div>
    <div class="aln-stat">
      <div class="aln-stat-icon" style="background:#EFF6FF">
        <svg viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div>
        <div class="aln-stat-val"><?= count($newUsers) ?></div>
        <div class="aln-stat-lbl">Nouveaux inscrits (48h)</div>
      </div>
    </div>
    <div class="aln-stat">
      <div class="aln-stat-icon" style="background:#FEF2F2">
        <svg viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      </div>
      <div>
        <div class="aln-stat-val" style="color:<?= count($messages) > 0 ? '#DC2626' : 'var(--gris-900)' ?>"><?= count($messages) ?></div>
        <div class="aln-stat-lbl">Messages non lus</div>
      </div>
    </div>
    <div class="aln-stat">
      <div class="aln-stat-icon" style="background:#F0FDF4">
        <svg viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
      </div>
      <div>
        <div class="aln-stat-val"><?= $examsAujourdhui ?></div>
        <div class="aln-stat-lbl">Examens aujourd'hui
          <?php if ($examsHier > 0): ?>
          <span style="font-size:9px;color:<?= $examsAujourdhui >= $examsHier ? '#16A34A' : '#DC2626' ?>">
            (<?= $examsHier ?> hier)
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ SECTION 1 : PAIEMENTS EN ATTENTE ═══ -->
  <div class="aln-section">
    <div class="aln-section-head">
      <div class="aln-section-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Paiements en attente de validation
        <?php if (count($paiementsAtt) > 0): ?>
        <span class="aln-badge" style="background:#FEF3C7;color:#B45309"><?= count($paiementsAtt) ?></span>
        <?php endif; ?>
      </div>
      <a href="/reussiteplus/admin/paiements.php" class="btn btn-ghost btn-sm">
        Tous les paiements &rarr;
      </a>
    </div>

    <?php if (empty($paiementsAtt)): ?>
    <div class="aln-empty">
      <svg viewBox="0 0 24 24" fill="none"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      Aucun paiement en attente &mdash; tout est &agrave; jour.
    </div>
    <?php else: ?>
    <?php foreach ($paiementsAtt as $p): ?>
    <?php
      $initiales = strtoupper(substr($p['prenom'],0,1).substr($p['nom'],0,1));
      $planNom   = PLANS[$p['plan']]['nom'] ?? $p['plan'];
      $methodes  = ['MPESA'=>'M-Pesa','AIRTEL_MONEY'=>'Airtel','ORANGE_MONEY'=>'Orange','CARTE'=>'Carte','VIREMENT'=>'Virement','ADMIN'=>'Admin'];
      $methLabel = $methodes[$p['methode_paiement']] ?? $p['methode_paiement'];
    ?>
    <div class="pay-card">
      <div class="pay-avatar"><?= $initiales ?></div>
      <div class="pay-info">
        <div class="pay-name"><?= e($p['prenom'] . ' ' . $p['nom']) ?></div>
        <div class="pay-meta">
          <?= e($p['email']) ?>
          &nbsp;&middot;&nbsp;
          <span class="pay-method-badge"><?= $methLabel ?></span>
          <?php if ($p['reference_paiement']): ?>
          &nbsp;&middot;&nbsp;<code style="font-size:10px;background:var(--gris-100);padding:1px 6px;border-radius:4px"><?= e($p['reference_paiement']) ?></code>
          <?php endif; ?>
          &nbsp;&middot;&nbsp;
          <span style="color:var(--gris-400)"><?= time_ago($p['created_at']) ?></span>
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div class="pay-amount"><?= number_format($p['montant'],0,',','.')  ?> <?= e($p['devise'] ?? 'CDF') ?></div>
        <div class="pay-plan">Plan <?= e($planNom) ?> &mdash; <?= $p['duree_mois'] ?>mois</div>
      </div>
      <div class="pay-actions">
        <form method="POST" style="display:inline" onsubmit="return confirm('Valider ce paiement ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="confirm_pay">
          <input type="hidden" name="id" value="<?= e($p['id']) ?>">
          <button type="submit" class="btn-xs btn-xs-green">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Valider
          </button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Refuser ce paiement ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reject_pay">
          <input type="hidden" name="id" value="<?= e($p['id']) ?>">
          <button type="submit" class="btn-xs btn-xs-red">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Refuser
          </button>
        </form>
        <a href="/reussiteplus/admin/paiements.php?user=<?= urlencode($p['user_id']) ?>" class="btn-xs" style="background:var(--gris-100);color:var(--gris-700)">
          D&eacute;tail
        </a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ═══ SECTION 2 : MESSAGES NON LUS ═══ -->
  <div class="aln-section">
    <div class="aln-section-head">
      <div class="aln-section-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        Messages contact non lus
        <?php if (count($messages) > 0): ?>
        <span class="aln-badge" style="background:#FEE2E2;color:#B91C1C"><?= count($messages) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($messages)): ?>
    <div class="aln-empty">
      <svg viewBox="0 0 24 24" fill="none"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      Aucun message en attente.
    </div>
    <?php else: ?>
    <?php
    $sujets = ['PLAN'=>'Plan tarifaire','TECHNIQUE'=>'Probl&egrave;me technique','PARTENARIAT'=>'Partenariat','PRESSE'=>'Presse','AUTRE'=>'Autre'];
    foreach ($messages as $m):
    ?>
    <div class="msg-card">
      <div class="msg-card-head">
        <div>
          <span class="msg-card-name"><?= e($m['nom']) ?></span>
          <span style="font-size:11px;color:var(--gris-500);margin-left:8px"><?= e($m['email']) ?></span>
        </div>
        <span class="msg-card-sujet"><?= $sujets[$m['sujet']] ?? e($m['sujet']) ?></span>
      </div>
      <div class="msg-card-body"><?= e(mb_substr($m['message'], 0, 200)) ?><?= mb_strlen($m['message']) > 200 ? '…' : '' ?></div>
      <div class="msg-card-foot">
        <span class="msg-card-time"><?= time_ago($m['created_at']) ?> &mdash; <?= $m['telephone'] ? e($m['telephone']) : '—' ?></span>
        <div style="display:flex;gap:8px">
          <a href="mailto:<?= e($m['email']) ?>?subject=Re: <?= urlencode($sujets[$m['sujet']] ?? 'Votre message') ?>" class="btn-xs" style="background:#EFF6FF;color:#1D4ED8">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            R&eacute;pondre
          </a>
          <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_msg_read">
            <input type="hidden" name="id" value="<?= e($m['id']) ?>">
            <button type="submit" class="btn-xs" style="background:#F0FDF4;color:#15803D">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Marquer lu
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ═══ SECTION 3 : NOUVEAUX INSCRITS ═══ -->
  <div class="aln-section">
    <div class="aln-section-head">
      <div class="aln-section-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        Nouveaux inscrits (derni&egrave;res 48h)
        <?php if (count($newUsers) > 0): ?>
        <span class="aln-badge" style="background:#DBEAFE;color:#1D4ED8"><?= count($newUsers) ?></span>
        <?php endif; ?>
      </div>
      <a href="/reussiteplus/admin/users.php" class="btn btn-ghost btn-sm">
        Tous les utilisateurs &rarr;
      </a>
    </div>

    <?php if (empty($newUsers)): ?>
    <div class="aln-empty">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Aucune nouvelle inscription dans les 48 derni&egrave;res heures.
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
    <?php foreach ($newUsers as $u): ?>
    <?php $initiales = strtoupper(substr($u['prenom'],0,1).substr($u['nom'],0,1)); ?>
    <div class="user-card">
      <div class="user-card-avatar"><?= $initiales ?></div>
      <div style="flex:1;min-width:0">
        <div class="user-card-name"><?= e($u['prenom'] . ' ' . $u['nom']) ?></div>
        <div class="user-card-email"><?= e($u['email']) ?></div>
        <div style="margin-top:4px">
          <span style="font-size:9px;font-weight:700;padding:2px 7px;border-radius:6px;background:var(--primary-subtle);color:var(--primary)"><?= $PLANS[$u['plan']]['nom'] ?? e($u['plan']) ?></span>
        </div>
      </div>
      <div class="user-card-time"><?= time_ago($u['created_at']) ?></div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer_app.php'; ?>
