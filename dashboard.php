<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle  = 'Tableau de bord';
$pageActive = 'dashboard';

$user  = require_login();
$stats = get_user_stats($user['id']);

// Message de bienvenue à la première connexion
$welcome = isset($_GET['welcome']);

// Derniers examens passés
$recentSessions = dbAll(
    "SELECT es.*, m.nom as matiere_nom, m.couleur as matiere_couleur
     FROM exam_sessions es
     LEFT JOIN matieres m ON es.matiere_id = m.id
     WHERE es.user_id = ? AND es.statut = 'TERMINE'
     ORDER BY es.finished_at DESC LIMIT 5",
    [$user['id']]
);

// Progression par matière
$progressMatieres = dbAll(
    "SELECT up.*, m.nom, m.couleur, m.icone
     FROM user_progression up
     JOIN matieres m ON up.matiere_id = m.id
     WHERE up.user_id = ?
     ORDER BY up.score_moyen DESC LIMIT 6",
    [$user['id']]
);

// Archives récentes (pour recommandations)
$archivesRec = dbAll(
    "SELECT a.*, m.nom as matiere_nom, m.couleur
     FROM archives a
     JOIN matieres m ON a.matiere_id = m.id
     WHERE a.status = 'PUBLIE' AND (a.premium_only = 0 OR ? != 'GRATUIT')
     ORDER BY a.vues DESC LIMIT 6",
    [$user['plan']]
);

// Activité des 7 derniers jours
$activite7j = dbAll(
    "SELECT date_act, examens, questions FROM activite_journaliere
     WHERE user_id = ? AND date_act >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     ORDER BY date_act ASC",
    [$user['id']]
);

// Notifications non lues
$notificationsRecentes = dbAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$user['id']]
);

// Vérifier expiration plan
$planExpire = $user['plan_expire_at'] ? strtotime($user['plan_expire_at']) : null;
$planJoursRestants = $planExpire ? max(0, (int)(($planExpire - time()) / 86400)) : null;

include __DIR__ . '/includes/header_app.php';
?>

<?php if ($welcome): ?>
<!-- ══════════════════════════════════════════════════════════
     ONBOARDING MODAL — affiché uniquement à la 1ère connexion
     ══════════════════════════════════════════════════════════ -->
<style>
.ob-backdrop{
  position:fixed;inset:0;background:rgba(0,0,0,.55);
  backdrop-filter:blur(4px);z-index:9000;
  display:flex;align-items:center;justify-content:center;padding:20px;
  animation:ob-fade-in .3s ease;
}
@keyframes ob-fade-in{from{opacity:0}to{opacity:1}}
.ob-card{
  background:#fff;border-radius:20px;width:100%;max-width:540px;
  max-height:90vh;overflow-y:auto;
  box-shadow:0 24px 80px rgba(0,0,0,.25);overflow:hidden;
  animation:ob-slide-up .35s cubic-bezier(.4,0,.2,1);
}
[data-theme="dark"] .ob-card{background:#1E293B;}
@keyframes ob-slide-up{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
/* Scroll sur le contenu intérieur, footer fixe */
#obSteps{overflow-y:auto;max-height:calc(90vh - 80px - 4px);}
/* Hero compact */
.ob-hero{padding:28px 36px 22px;}
.ob-icon-wrap{width:60px;height:60px;border-radius:16px;margin-bottom:14px;}
.ob-icon-wrap svg{width:28px;height:28px;}
.ob-hero h2{font-size:clamp(17px,3.5vw,22px);margin-bottom:8px;}
.ob-hero p{font-size:13px;}
.ob-body{padding:20px 36px;}
.ob-footer{padding:16px 36px 22px;}

/* Barre de progression en haut */
.ob-progress-bar{height:4px;background:#E2E8F0;position:relative;}
[data-theme="dark"] .ob-progress-bar{background:#334155;}
.ob-progress-fill{height:100%;background:linear-gradient(90deg,#007A5E,#00C896);border-radius:4px;transition:width .4s cubic-bezier(.4,0,.2,1);}

/* Header coloré */
.ob-hero{
  padding:40px 40px 32px;text-align:center;position:relative;
  background:linear-gradient(160deg,#0D1117 0%,#003D2E 100%);
  overflow:hidden;
}
.ob-hero-photo{
  position:absolute;inset:0;
  background:url('/reussiteplus/assets/img/hero-students.jpg') center/cover no-repeat;
  opacity:.12;
}
.ob-hero::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse 80% 70% at 50% 0%,rgba(0,122,94,.5) 0%,transparent 70%);
}
.ob-hero-inner{position:relative;}
.ob-step-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);
  border-radius:20px;padding:5px 14px;margin-bottom:16px;
  font-family:'Poppins',sans-serif;font-size:11px;font-weight:700;
  letter-spacing:.8px;text-transform:uppercase;color:rgba(255,255,255,.7);
}
.ob-step-badge .ob-step-dot{
  width:6px;height:6px;border-radius:50%;background:#FBBF24;
}
.ob-icon-wrap{
  width:72px;height:72px;border-radius:20px;margin:0 auto 20px;
  display:flex;align-items:center;justify-content:center;
  border:1px solid rgba(255,255,255,.15);
}
.ob-icon-wrap svg{width:34px;height:34px;stroke:#fff;stroke-width:1.8;}
.ob-hero h2{
  font-family:'Poppins',sans-serif;font-size:clamp(20px,4vw,26px);
  font-weight:800;color:#fff;line-height:1.2;margin-bottom:10px;
}
.ob-hero h2 span{color:#FBBF24;}
.ob-hero p{font-family:'Inter',sans-serif;font-size:14px;color:rgba(255,255,255,.65);line-height:1.65;max-width:380px;margin:0 auto;}

/* Corps */
.ob-body{padding:28px 40px;}
.ob-features{display:flex;flex-direction:column;gap:14px;}
.ob-feat{
  display:flex;align-items:flex-start;gap:14px;
  padding:14px 16px;border-radius:12px;
  background:#F8FAFC;border:1px solid #E2E8F0;
}
[data-theme="dark"] .ob-feat{background:#0F172A;border-color:#334155;}
.ob-feat-icon{
  width:38px;height:38px;border-radius:10px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.ob-feat-icon svg{width:18px;height:18px;stroke:currentColor;}
.ob-feat-text strong{display:block;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;color:#1C2433;margin-bottom:2px;}
[data-theme="dark"] .ob-feat-text strong{color:#F1F5F9;}
.ob-feat-text span{font-family:'Inter',sans-serif;font-size:12px;color:#6B7280;line-height:1.5;}
[data-theme="dark"] .ob-feat-text span{color:#94A3B8;}

/* Mock preview dans le corps */
.ob-preview{
  background:#F8FAFC;border:1px solid #E2E8F0;border-radius:14px;
  padding:16px;margin-top:4px;
}
[data-theme="dark"] .ob-preview{background:#0F172A;border-color:#334155;}
.ob-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
.ob-bar-row:last-child{margin-bottom:0;}
.ob-bar-label{font-size:11px;color:#6B7280;width:70px;font-family:'Inter',sans-serif;}
.ob-bar-track{flex:1;height:6px;border-radius:6px;background:#E2E8F0;overflow:hidden;}
[data-theme="dark"] .ob-bar-track{background:#334155;}
.ob-bar-fill{height:100%;border-radius:6px;}
.ob-bar-pct{font-size:11px;color:#6B7280;width:32px;text-align:right;font-family:'Inter',sans-serif;}

/* Navigation */
.ob-footer{
  display:flex;align-items:center;justify-content:space-between;
  padding:20px 40px 28px;gap:12px;
}
.ob-dots{display:flex;gap:6px;}
.ob-dot{width:7px;height:7px;border-radius:50%;background:#E2E8F0;transition:all .3s;cursor:pointer;border:none;}
[data-theme="dark"] .ob-dot{background:#334155;}
.ob-dot.active{background:#007A5E;width:22px;border-radius:6px;}
.ob-btn-back{
  display:flex;align-items:center;gap:6px;
  background:none;border:1.5px solid #E2E8F0;
  border-radius:10px;padding:9px 16px;
  font-family:'Poppins',sans-serif;font-size:13px;font-weight:600;
  color:#4A5568;cursor:pointer;transition:all .2s;
}
[data-theme="dark"] .ob-btn-back{border-color:#334155;color:#94A3B8;}
.ob-btn-back:hover{border-color:#007A5E;color:#007A5E;}
.ob-btn-next{
  display:flex;align-items:center;gap:8px;
  background:#007A5E;border:none;border-radius:10px;
  padding:10px 22px;
  font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;
  color:#fff;cursor:pointer;transition:all .2s;
}
.ob-btn-next:hover{background:#005A45;transform:translateY(-1px);box-shadow:0 4px 16px rgba(0,122,94,.3);}
.ob-btn-next svg{width:15px;height:15px;stroke:#fff;transition:transform .2s;}
.ob-btn-next:hover svg{transform:translateX(3px);}
.ob-btn-next.ob-btn-finish{background:linear-gradient(135deg,#007A5E,#00A97F);}
.ob-btn-finish:hover{background:linear-gradient(135deg,#005A45,#007A5E) !important;}
</style>

<div class="ob-backdrop" id="obBackdrop">
  <div class="ob-card" id="obCard">
    <!-- Barre de progression -->
    <div class="ob-progress-bar">
      <div class="ob-progress-fill" id="obFill" style="width:16.66%"></div>
    </div>

    <!-- Étapes -->
    <div id="obSteps">

      <!-- Étape 1 : Félicitations -->
      <div class="ob-step" data-step="0">
        <div class="ob-hero">
          <div class="ob-hero-photo"></div>
          <div class="ob-hero-inner">
            <div class="ob-step-badge"><span class="ob-step-dot"></span>Bienvenue</div>
            <div class="ob-icon-wrap" style="background:rgba(0,169,127,.25)">
              <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
              </svg>
            </div>
            <h2>Félicitations,<br><span><?= e($user['prenom']) ?> !</span></h2>
            <p>Ton compte est prêt. En 2 minutes, tu vas découvrir tout ce que RÉUSSITE+ peut faire pour toi.</p>
          </div>
        </div>
        <div class="ob-body">
          <div class="ob-features">
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#E8F5F1;color:#007A5E">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>Archives officielles</strong><span>Examen d'État, TENASOSP, ENAFEP depuis 2010</span></div>
            </div>
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#EFF6FF;color:#3B82F6">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>QCM chronométrés</strong><span>Entraîne-toi dans les conditions de l'examen réel</span></div>
            </div>
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#F3F0FF;color:#7C3AED">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>Révision IA personnalisée</strong><span>Un plan de révision adapté à tes lacunes</span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Étape 2 : Le tableau de bord -->
      <div class="ob-step" data-step="1" style="display:none">
        <div class="ob-hero">
          <div class="ob-hero-photo"></div>
          <div class="ob-hero-inner">
            <div class="ob-step-badge"><span class="ob-step-dot"></span>Étape 1 sur 5</div>
            <div class="ob-icon-wrap" style="background:rgba(96,165,250,.25)">
              <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
              </svg>
            </div>
            <h2>Ton <span>tableau de bord</span></h2>
            <p>Cette page est ton point de départ. Elle te montre tes statistiques, ta progression et ton activité en temps réel.</p>
          </div>
        </div>
        <div class="ob-body">
          <div class="ob-preview">
            <div class="ob-bar-row">
              <span class="ob-bar-label">Score moyen</span>
              <div class="ob-bar-track"><div class="ob-bar-fill" style="width:0%;background:#007A5E" id="ob-bar-score"></div></div>
              <span class="ob-bar-pct">0%</span>
            </div>
            <div class="ob-bar-row">
              <span class="ob-bar-label">Examens passés</span>
              <div class="ob-bar-track"><div class="ob-bar-fill" style="width:0%;background:#3B82F6"></div></div>
              <span class="ob-bar-pct">0</span>
            </div>
            <div class="ob-bar-row">
              <span class="ob-bar-label">Série active</span>
              <div class="ob-bar-track"><div class="ob-bar-fill" style="width:0%;background:#FBBF24"></div></div>
              <span class="ob-bar-pct">0j</span>
            </div>
          </div>
          <p style="font-size:12px;color:#6B7280;margin-top:12px;font-family:'Inter',sans-serif;line-height:1.6">
            💡 <strong>Astuce :</strong> Reviens chaque jour pour maintenir ta série et améliorer ton score moyen progressivement.
          </p>
        </div>
      </div>

      <!-- Étape 3 : Archives -->
      <div class="ob-step" data-step="2" style="display:none">
        <div class="ob-hero">
          <div class="ob-hero-photo"></div>
          <div class="ob-hero-inner">
            <div class="ob-step-badge"><span class="ob-step-dot"></span>Étape 2 sur 5</div>
            <div class="ob-icon-wrap" style="background:rgba(251,191,36,.25)">
              <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
              </svg>
            </div>
            <h2>Les <span>Archives officielles</span></h2>
            <p>Accède aux vrais sujets d'examen depuis 2010 avec leurs corrigés détaillés, organisés par matière et par année.</p>
          </div>
        </div>
        <div class="ob-body">
          <div class="ob-features">
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#FFFBEB;color:#D97706">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>Comment l'utiliser ?</strong><span>Va dans <strong>Archives</strong> → choisis une matière → sélectionne une année → lis le sujet et son corrigé.</span></div>
            </div>
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#F0FDF4;color:#16A34A">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>Plan gratuit</strong><span>Accès à 5 archives par mois. <strong>Premium</strong> = archives illimitées + corrigés complets.</span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Étape 4 : QCM / Examens -->
      <div class="ob-step" data-step="3" style="display:none">
        <div class="ob-hero">
          <div class="ob-hero-photo"></div>
          <div class="ob-hero-inner">
            <div class="ob-step-badge"><span class="ob-step-dot"></span>Étape 3 sur 5</div>
            <div class="ob-icon-wrap" style="background:rgba(239,68,68,.2)">
              <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
            </div>
            <h2>S'entraîner avec<br>les <span>QCM</span></h2>
            <p>Passe des examens blancs dans les conditions réelles : chronomètre, score instantané, explication de chaque réponse.</p>
          </div>
        </div>
        <div class="ob-body">
          <div class="ob-features">
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#FFF1F2;color:#E11D48">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>Comment démarrer ?</strong><span>Va dans <strong>Examens</strong> → choisis une matière et une durée → réponds aux questions → consulte ton score.</span></div>
            </div>
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#FFFBEB;color:#D97706">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>Limite gratuite</strong><span><strong>5 examens par mois</strong> en plan Gratuit. Pas de limite en Premium.</span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Étape 5 : Révision IA -->
      <div class="ob-step" data-step="4" style="display:none">
        <div class="ob-hero">
          <div class="ob-hero-photo"></div>
          <div class="ob-hero-inner">
            <div class="ob-step-badge"><span class="ob-step-dot"></span>Étape 4 sur 5</div>
            <div class="ob-icon-wrap" style="background:rgba(139,92,246,.25)">
              <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
              </svg>
            </div>
            <h2>La <span>Révision IA</span></h2>
            <p>L'intelligence artificielle analyse tes résultats et génère un plan de révision semaine par semaine pour combler tes lacunes.</p>
          </div>
        </div>
        <div class="ob-body">
          <div class="ob-features">
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#F3F0FF;color:#7C3AED">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 6v6l4 2"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>Comment l'activer ?</strong><span>Va dans <strong>Progression</strong> → clique sur <em>"Générer mon plan IA"</em> → le plan s'adapte chaque semaine.</span></div>
            </div>
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#F0FDF4;color:#16A34A">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>Fonctionnalité Premium</strong><span>Disponible dès le plan Basique. Plus tu passes d'examens, plus l'IA est précise.</span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Étape 6 : C'est parti ! -->
      <div class="ob-step" data-step="5" style="display:none">
        <div class="ob-hero">
          <div class="ob-hero-photo"></div>
          <div class="ob-hero-inner">
            <div class="ob-step-badge"><span class="ob-step-dot"></span>Étape 5 sur 5</div>
            <div class="ob-icon-wrap" style="background:rgba(251,191,36,.3)">
              <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
              </svg>
            </div>
            <h2>Tu es <span>prêt(e) !</span></h2>
            <p>Commence par passer ton premier examen blanc pour établir ton niveau de départ.</p>
          </div>
        </div>
        <div class="ob-body">
          <div class="ob-features">
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#E8F5F1;color:#007A5E">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>1. Commence maintenant</strong><span>Passe ton premier examen — 5 minutes suffisent pour voir ton niveau.</span></div>
            </div>
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#EFF6FF;color:#3B82F6">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>2. Reviens chaque jour</strong><span>15 min par jour valent mieux que 3h le week-end. Ta série compte !</span></div>
            </div>
            <div class="ob-feat">
              <div class="ob-feat-icon" style="background:#FFFBEB;color:#D97706">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
              </div>
              <div class="ob-feat-text"><strong>3. Passe en Premium</strong><span>Archives illimitées, corrigés complets, plan IA — tout pour maximiser tes chances.</span></div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /ob-steps -->

    <!-- Navigation -->
    <div class="ob-footer">
      <div class="ob-dots">
        <button class="ob-dot active" onclick="obGo(0)"></button>
        <button class="ob-dot" onclick="obGo(1)"></button>
        <button class="ob-dot" onclick="obGo(2)"></button>
        <button class="ob-dot" onclick="obGo(3)"></button>
        <button class="ob-dot" onclick="obGo(4)"></button>
        <button class="ob-dot" onclick="obGo(5)"></button>
      </div>
      <div style="display:flex;gap:10px">
        <button class="ob-btn-back" id="obBack" onclick="obPrev()" style="display:none">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          Retour
        </button>
        <button class="ob-btn-next" id="obNext" onclick="obNext()">
          Suivant
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
  const TOTAL = 6;
  let cur = 0;

  function obGo(n) {
    // Cacher étape actuelle
    document.querySelectorAll('.ob-step')[cur].style.display = 'none';
    document.querySelectorAll('.ob-dot')[cur].classList.remove('active');
    cur = n;
    // Afficher nouvelle étape
    document.querySelectorAll('.ob-step')[cur].style.display = '';
    document.querySelectorAll('.ob-dot')[cur].classList.add('active');
    // Barre de progression
    document.getElementById('obFill').style.width = ((cur + 1) / TOTAL * 100) + '%';
    // Bouton retour
    document.getElementById('obBack').style.display = cur > 0 ? '' : 'none';
    // Bouton suivant / terminer
    const btn = document.getElementById('obNext');
    if (cur === TOTAL - 1) {
      btn.innerHTML = 'Commencer <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
      btn.classList.add('ob-btn-finish');
      btn.onclick = obClose;
    } else {
      btn.innerHTML = 'Suivant <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
      btn.classList.remove('ob-btn-finish');
      btn.onclick = obNext;
    }
  }

  window.obNext = function() { if (cur < TOTAL - 1) obGo(cur + 1); };
  window.obPrev = function() { if (cur > 0) obGo(cur - 1); };
  window.obGo   = obGo;

  window.obClose = function() {
    const bd = document.getElementById('obBackdrop');
    bd.style.transition = 'opacity .3s';
    bd.style.opacity = '0';
    setTimeout(() => bd.remove(), 320);
    // Nettoyer l'URL sans recharger la page
    if (history.replaceState) {
      const url = new URL(window.location.href);
      url.searchParams.delete('welcome');
      history.replaceState({}, '', url.toString());
    }
  };

  // Fermer en cliquant sur le backdrop (pas la carte)
  document.getElementById('obBackdrop').addEventListener('click', function(e) {
    if (e.target === this) obClose();
  });

  // Clavier
  document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowRight') obNext();
    if (e.key === 'ArrowLeft')  obPrev();
    if (e.key === 'Escape')     obClose();
  });

  // Charger les fonts si pas déjà présentes
  if (!document.querySelector('link[href*="Poppins"]')) {
    const l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = 'https://fonts.googleapis.com/css2?family=Poppins:wght@700;800&family=Inter:wght@400;500&display=swap';
    document.head.appendChild(l);
  }
})();
</script>
<?php endif; ?>

<?php if ($user['plan'] === 'GRATUIT'): ?>
<div style="background:linear-gradient(135deg,#F5E6C0,#FFF7E6);border:1px solid rgba(201,151,42,0.3);border-radius:var(--radius-lg);padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
  <div>
    <strong style="color:var(--gold-dark)"><i data-lucide="star" style="width:14px;height:14px;vertical-align:-2px;stroke:var(--gold-dark)"></i> Passez à Premium pour un accès illimité</strong>
    <div style="font-size:13px;color:var(--gris-600);margin-top:3px">
      <?= $user['examens_mois'] ?? 0 ?>/<?= FREE_EXAMS_PER_MONTH ?> examens utilisés ce mois •
      Archives complètes • Corrigés détaillés • Plan de révision IA
    </div>
  </div>
  <a href="/reussiteplus/tarifs.php" class="btn btn-gold btn-sm">Voir les offres →</a>
</div>
<?php elseif ($planJoursRestants !== null && $planJoursRestants <= 7): ?>
<div class="alert alert-warning">
  ⏰ Votre plan <?= e($user['plan']) ?> expire dans <strong><?= $planJoursRestants ?> jour(s)</strong>.
  <a href="/reussiteplus/abonnement.php" style="font-weight:600;margin-left:8px">Renouveler →</a>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card green">
    <div class="stat-label"><i data-lucide="bar-chart-2"></i> Score moyen</div>
    <div class="stat-value" style="color:<?= score_couleur((float)($user['score_moyen'] ?? 0)) ?>">
      <?= number_format((float)($user['score_moyen'] ?? 0), 1) ?>%
    </div>
    <div class="stat-sub"><?= score_label((float)($user['score_moyen'] ?? 0)) ?></div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label"><i data-lucide="file-check"></i> Examens passés</div>
    <div class="stat-value"><?= number_format((int)($user['total_examens'] ?? 0)) ?></div>
    <div class="stat-sub">Total cumulé</div>
  </div>
  <div class="stat-card bleu">
    <div class="stat-label"><i data-lucide="lightbulb"></i> Questions répondues</div>
    <div class="stat-value"><?= number_format((int)($user['total_questions'] ?? 0)) ?></div>
    <div class="stat-sub">Total cumulé</div>
  </div>
  <div class="stat-card rouge">
    <div class="stat-label"><i data-lucide="flame"></i> Série actuelle</div>
    <div class="stat-value"><?= (int)($stats['streak_actuel'] ?? 0) ?></div>
    <div class="stat-sub">jours consécutifs</div>
  </div>
</div>

<!-- Activité 7 jours + Progression Matières -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Activité -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i data-lucide="calendar-days"></i> Activité (7 derniers jours)</div>
    </div>
    <div id="activity-chart" style="display:flex;align-items:flex-end;gap:8px;height:80px">
      <?php
      $actMap = [];
      foreach ($activite7j as $a) $actMap[$a['date_act']] = $a;
      $maxEx  = max(1, max(array_map(fn($a) => $a['examens'], $activite7j ?: [['examens'=>0]])));
      for ($i = 6; $i >= 0; $i--):
          $d    = date('Y-m-d', strtotime("-{$i} days"));
          $ex   = $actMap[$d]['examens'] ?? 0;
          $pct  = $ex > 0 ? max(10, (int)(($ex / $maxEx) * 100)) : 4;
          $day  = date('D', strtotime($d));
          $days = ['Mon'=>'L','Tue'=>'M','Wed'=>'M','Thu'=>'J','Fri'=>'V','Sat'=>'S','Sun'=>'D'];
          $dl   = $days[$day] ?? $day;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
        <div style="width:100%;height:<?= $pct ?>%;background:<?= $ex > 0 ? 'var(--primary)' : 'var(--gris-200)' ?>;border-radius:4px;min-height:4px;transition:height .5s" title="<?= $ex ?> exam"></div>
        <div style="font-size:10px;color:var(--gris-500)"><?= $dl ?></div>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Progression par matière -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i data-lucide="trending-up"></i> Progression par matière</div>
      <a href="/reussiteplus/progression.php" class="section-link">Tout voir →</a>
    </div>
    <?php if ($progressMatieres): ?>
      <?php foreach (array_slice($progressMatieres, 0, 4) as $pm): ?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
          <span style="color:var(--gris-700);display:flex;align-items:center;gap:5px"><?= matiere_icon($pm['icone'] ?? 'book', 13) ?> <?= e($pm['nom']) ?></span>
          <span style="font-weight:600;color:<?= score_couleur((float)$pm['score_moyen']) ?>"><?= number_format((float)$pm['score_moyen'],1) ?>%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-bar-fill" style="width:<?= min(100, (float)$pm['score_moyen']) ?>%;background:<?= $pm['couleur'] ?? 'var(--primary)' ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center;padding:20px;color:var(--gris-500);font-size:13px">
        <i data-lucide="bar-chart" style="width:16px;height:16px;margin-right:6px;vertical-align:-3px"></i> Passez vos premiers examens pour voir votre progression
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Derniers examens -->
<?php if ($recentSessions): ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div class="section-title"><i data-lucide="clock"></i> Derniers examens passés</div>
    <a href="/reussiteplus/progression.php" class="section-link">Historique complet →</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Matière</th><th>Type</th><th>Score</th><th>Temps</th><th>Date</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentSessions as $s):
          $pct = (float)($s['pourcentage'] ?? 0);
          $mins = floor(($s['temps_passe'] ?? 0) / 60);
          $secs = ($s['temps_passe'] ?? 0) % 60;
        ?>
        <tr>
          <td>
            <span style="display:inline-flex;align-items:center;gap:6px">
              <span style="width:10px;height:10px;border-radius:50%;background:<?= e($s['matiere_couleur'] ?? '#007A5E') ?>;display:inline-block"></span>
              <?= e($s['matiere_nom'] ?? $s['titre'] ?? 'Examen') ?>
            </span>
          </td>
          <td><span class="badge badge-gray"><?= e($s['exam_type'] ?? '—') ?></span></td>
          <td>
            <span style="font-weight:700;color:<?= score_couleur($pct) ?>"><?= number_format($pct,1) ?>%</span>
            <span style="font-size:11px;color:var(--gris-500)"> <?= score_label($pct) ?></span>
          </td>
          <td style="font-size:12px;color:var(--gris-600)"><?= $mins ?>m <?= $secs ?>s</td>
          <td style="font-size:12px;color:var(--gris-500)"><?= date('d/m/Y', strtotime($s['finished_at'] ?? $s['started_at'])) ?></td>
          <td><a href="/reussiteplus/resultat.php?session=<?= e($s['id']) ?>" class="btn btn-ghost btn-sm">Résultats</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Archives recommandées -->
<div>
  <div class="section-header">
    <div class="section-title"><i data-lucide="folder-open"></i> Archives recommandées</div>
    <a href="/reussiteplus/archives.php" class="section-link">Toutes les archives →</a>
  </div>
  <?php if ($archivesRec): ?>
  <div class="exams-grid">
    <?php foreach ($archivesRec as $arc): ?>
    <div class="exam-card" onclick="window.location='/reussiteplus/archives.php?id=<?= e($arc['id']) ?>'">
      <div class="exam-card-header">
        <span class="badge badge-green"><?= e($arc['exam_type']) ?></span>
        <span style="font-size:11px;color:var(--gris-500)"><?= e($arc['annee']) ?></span>
      </div>
      <div class="exam-card-body">
        <div class="exam-card-title"><?= e($arc['titre']) ?></div>
        <div class="exam-meta">
          <span class="exam-meta-item"><i data-lucide="book-open" style="width:12px;height:12px;vertical-align:-2px"></i> <?= e($arc['matiere_nom']) ?></span>
          <span class="exam-meta-item"><i data-lucide="eye" style="width:12px;height:12px;vertical-align:-2px"></i> <?= number_format($arc['vues']) ?> vues</span>
        </div>
        <?php if ($arc['premium_only'] && $user['plan'] === 'GRATUIT'): ?>
        <div style="margin-top:8px;font-size:11px;color:var(--gold-dark)"><i data-lucide="star" style="width:12px;height:12px;vertical-align:-2px;stroke:var(--gold-dark)"></i> Réservé aux membres Premium</div>
        <?php endif; ?>
      </div>
      <div class="exam-card-footer">
        <a href="/reussiteplus/archives.php?id=<?= e($arc['id']) ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">
          Consulter
        </a>
        <?php if ($arc['corrige_url']): ?>
        <a href="<?= e($arc['corrige_url']) ?>" class="btn btn-ghost btn-sm" target="_blank">Corrigé</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="card" style="text-align:center;padding:40px">
    <div style="margin-bottom:16px;display:flex;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--gris-300)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
    <div style="font-size:15px;font-weight:600;margin-bottom:8px">Aucune archive disponible</div>
    <div style="font-size:13px;color:var(--gris-500)">Les archives seront ajoutées prochainement par l'équipe.</div>
  </div>
  <?php endif; ?>
</div>

<!-- Actions rapides -->
<div style="margin-top:24px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
  <a href="/reussiteplus/examen.php" class="card" style="text-align:center;cursor:pointer;text-decoration:none;transition:all .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="margin-bottom:16px;display:flex;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>
    <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px">Passer un examen</div>
    <div style="font-size:12px;color:var(--gris-500)">Simuler les conditions réelles</div>
  </a>
  <a href="/reussiteplus/questions.php" class="card" style="text-align:center;cursor:pointer;text-decoration:none;transition:all .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="margin-bottom:16px;display:flex;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--bleu)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px">S'entraîner</div>
    <div style="font-size:12px;color:var(--gris-500)">Banque de 15 000+ questions</div>
  </a>
  <a href="/reussiteplus/archives.php" class="card" style="text-align:center;cursor:pointer;text-decoration:none;transition:all .2s" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
    <div style="margin-bottom:16px;display:flex;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
    <div style="font-family:var(--font-display);font-weight:700;margin-bottom:4px">Archives</div>
    <div style="font-size:12px;color:var(--gris-500)">Sujets & corrigés officiels</div>
  </a>
</div>

<?php if ($welcome): ?>
<!-- ═══════════════════════════════════════════════════════
     MODAL ONBOARDING — Bienvenue sur RÉUSSITE+
     Affiché à la première connexion / inscription
     ═══════════════════════════════════════════════════════ -->
<div id="onboardingOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9000;display:flex;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(4px)">
  <div id="onboardingModal" style="background:var(--blanc);border-radius:20px;width:100%;max-width:620px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,.3);position:relative">

    <!-- Barre de progression -->
    <div style="height:4px;background:var(--gris-200);border-radius:20px 20px 0 0;overflow:hidden">
      <div id="obProgress" style="height:100%;background:linear-gradient(90deg,var(--primary),#7c3aed);border-radius:4px;transition:width .4s ease;width:25%"></div>
    </div>

    <!-- Étape 1 — Bienvenue -->
    <div class="ob-step" id="ob-step-1" style="padding:36px 40px">
      <div style="text-align:center;margin-bottom:28px">
        <div style="width:72px;height:72px;background:linear-gradient(135deg,var(--primary),#7c3aed);border-radius:20px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px">
          <i data-lucide="graduation-cap" style="width:36px;height:36px;stroke:#fff"></i>
        </div>
        <h2 style="font-family:var(--font-display);font-size:24px;font-weight:800;margin-bottom:8px">
          Bienvenue sur RÉUSSITE+, <?= e($user['prenom']) ?> !
        </h2>
        <p style="color:var(--gris-600);font-size:14px;max-width:420px;margin:0 auto;line-height:1.7">
          Votre plateforme de révision intelligente pour réussir l'<strong>ENAFEP</strong>, le <strong>TENASOSP</strong> et l'<strong>Examen d'État</strong> en République Démocratique du Congo.
        </p>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:28px">
        <div style="background:var(--gris-50);border-radius:12px;padding:16px;display:flex;gap:12px;align-items:flex-start">
          <div style="width:36px;height:36px;background:var(--primary-subtle);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i data-lucide="file-check" style="width:18px;height:18px;stroke:var(--primary)"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:13px;margin-bottom:3px">Examens blancs</div>
            <div style="font-size:12px;color:var(--gris-500)">Simulez les conditions réelles avec des vrais sujets</div>
          </div>
        </div>
        <div style="background:var(--gris-50);border-radius:12px;padding:16px;display:flex;gap:12px;align-items:flex-start">
          <div style="width:36px;height:36px;background:#EEF4FD;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i data-lucide="archive" style="width:18px;height:18px;stroke:#1E5FAD"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:13px;margin-bottom:3px">Archives officielles</div>
            <div style="font-size:12px;color:var(--gris-500)">Accédez aux anciens sujets avec corrigés PDF</div>
          </div>
        </div>
        <div style="background:var(--gris-50);border-radius:12px;padding:16px;display:flex;gap:12px;align-items:flex-start">
          <div style="width:36px;height:36px;background:#F5E6C0;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i data-lucide="brain" style="width:18px;height:18px;stroke:#8C6A1A"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:13px;margin-bottom:3px">IA Personnalisée</div>
            <div style="font-size:12px;color:var(--gris-500)">Un coach qui analyse vos erreurs et crée votre plan</div>
          </div>
        </div>
        <div style="background:var(--gris-50);border-radius:12px;padding:16px;display:flex;gap:12px;align-items:flex-start">
          <div style="width:36px;height:36px;background:#FEF0EF;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i data-lucide="trending-up" style="width:18px;height:18px;stroke:var(--rouge)"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:13px;margin-bottom:3px">Suivi de progression</div>
            <div style="font-size:12px;color:var(--gris-500)">Visualisez vos points forts et faiblesses</div>
          </div>
        </div>
      </div>

      <button onclick="obNext(2)" class="btn btn-primary btn-full btn-lg">
        Découvrir comment ça marche <i data-lucide="arrow-right" style="width:16px;height:16px;vertical-align:-2px;margin-left:6px"></i>
      </button>
    </div>

    <!-- Étape 2 — Comment ça marche -->
    <div class="ob-step" id="ob-step-2" style="padding:36px 40px;display:none">
      <h3 style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:6px;text-align:center">Comment ça marche ?</h3>
      <p style="text-align:center;color:var(--gris-500);font-size:13px;margin-bottom:24px">3 étapes simples pour progresser rapidement</p>

      <div style="display:flex;flex-direction:column;gap:16px;margin-bottom:28px">
        <div style="display:flex;gap:16px;align-items:flex-start">
          <div style="width:40px;height:40px;background:linear-gradient(135deg,var(--primary),#00A97F);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:16px;flex-shrink:0">1</div>
          <div style="flex:1;padding-top:6px">
            <div style="font-weight:700;font-size:15px;margin-bottom:4px">Choisissez votre matière et passez un examen</div>
            <div style="font-size:13px;color:var(--gris-500)">Mathématiques, Français, Sciences, Histoire-Géo, Physique, Chimie, Biologie ou Anglais — choisissez la durée et le niveau.</div>
          </div>
        </div>
        <div style="width:1px;height:20px;background:var(--gris-200);margin-left:20px"></div>
        <div style="display:flex;gap:16px;align-items:flex-start">
          <div style="width:40px;height:40px;background:linear-gradient(135deg,#1E5FAD,#7c3aed);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:16px;flex-shrink:0">2</div>
          <div style="flex:1;padding-top:6px">
            <div style="font-weight:700;font-size:15px;margin-bottom:4px">Consultez vos résultats détaillés</div>
            <div style="font-size:13px;color:var(--gris-500)">Chaque bonne et mauvaise réponse est expliquée. Vous comprenez où vous avez fait des erreurs et pourquoi.</div>
          </div>
        </div>
        <div style="width:1px;height:20px;background:var(--gris-200);margin-left:20px"></div>
        <div style="display:flex;gap:16px;align-items:flex-start">
          <div style="width:40px;height:40px;background:linear-gradient(135deg,#C9972A,#F59E0B);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:16px;flex-shrink:0">3</div>
          <div style="flex:1;padding-top:6px">
            <div style="font-weight:700;font-size:15px;margin-bottom:4px">Progressez grâce au suivi et à l'IA</div>
            <div style="font-size:13px;color:var(--gris-500)">Votre tableau de bord suit votre évolution. L'IA génère un plan de révision 7 jours sur mesure selon vos performances.</div>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px">
        <button onclick="obNext(1)" class="btn btn-ghost" style="flex:0 0 auto">
          <i data-lucide="arrow-left" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px"></i> Retour
        </button>
        <button onclick="obNext(3)" class="btn btn-primary" style="flex:1">
          Voir les plans <i data-lucide="arrow-right" style="width:16px;height:16px;vertical-align:-2px;margin-left:6px"></i>
        </button>
      </div>
    </div>

    <!-- Étape 3 — Plans tarifaires -->
    <div class="ob-step" id="ob-step-3" style="padding:36px 40px;display:none">
      <h3 style="font-family:var(--font-display);font-size:20px;font-weight:800;margin-bottom:6px;text-align:center">Choisissez votre plan</h3>
      <p style="text-align:center;color:var(--gris-500);font-size:13px;margin-bottom:24px">Commencez gratuitement, évoluez quand vous voulez</p>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:24px">
        <!-- GRATUIT -->
        <div style="border:2px solid var(--gris-200);border-radius:14px;padding:16px;text-align:center">
          <div style="font-weight:800;font-size:14px;margin-bottom:4px">Gratuit</div>
          <div style="font-size:22px;font-weight:900;color:var(--gris-700);margin-bottom:8px">0 $</div>
          <div style="font-size:11px;color:var(--gris-500);text-align:left;display:flex;flex-direction:column;gap:5px">
            <span><i data-lucide="check" style="width:11px;height:11px;stroke:var(--primary);vertical-align:-1px;margin-right:3px"></i> 2 examens/mois</span>
            <span><i data-lucide="check" style="width:11px;height:11px;stroke:var(--primary);vertical-align:-1px;margin-right:3px"></i> Questions basiques</span>
            <span style="color:var(--gris-400)"><i data-lucide="x" style="width:11px;height:11px;vertical-align:-1px;margin-right:3px"></i> Archives</span>
            <span style="color:var(--gris-400)"><i data-lucide="x" style="width:11px;height:11px;vertical-align:-1px;margin-right:3px"></i> IA Coach</span>
          </div>
        </div>
        <!-- PREMIUM — mis en avant -->
        <div style="border:2px solid var(--primary);border-radius:14px;padding:16px;text-align:center;background:var(--primary-subtle);position:relative">
          <div style="position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:var(--primary);color:#fff;font-size:10px;font-weight:700;padding:2px 12px;border-radius:10px;white-space:nowrap">POPULAIRE</div>
          <div style="font-weight:800;font-size:14px;margin-bottom:4px;color:var(--primary)">Premium</div>
          <div style="font-size:22px;font-weight:900;color:var(--primary);margin-bottom:8px">5 $<span style="font-size:11px;font-weight:400">/mois</span></div>
          <div style="font-size:11px;color:var(--gris-700);text-align:left;display:flex;flex-direction:column;gap:5px">
            <span><i data-lucide="check" style="width:11px;height:11px;stroke:var(--primary);vertical-align:-1px;margin-right:3px"></i> Examens illimités</span>
            <span><i data-lucide="check" style="width:11px;height:11px;stroke:var(--primary);vertical-align:-1px;margin-right:3px"></i> Toutes les archives</span>
            <span><i data-lucide="check" style="width:11px;height:11px;stroke:var(--primary);vertical-align:-1px;margin-right:3px"></i> Corrigés PDF</span>
            <span><i data-lucide="check" style="width:11px;height:11px;stroke:var(--primary);vertical-align:-1px;margin-right:3px"></i> IA Coach 24h/24</span>
          </div>
        </div>
        <!-- ÉCOLE -->
        <div style="border:2px solid #C9972A;border-radius:14px;padding:16px;text-align:center;background:#FFFBF0">
          <div style="font-weight:800;font-size:14px;margin-bottom:4px;color:#8C6A1A">École</div>
          <div style="font-size:22px;font-weight:900;color:#8C6A1A;margin-bottom:8px">50 $<span style="font-size:11px;font-weight:400">/an</span></div>
          <div style="font-size:11px;color:var(--gris-700);text-align:left;display:flex;flex-direction:column;gap:5px">
            <span><i data-lucide="check" style="width:11px;height:11px;stroke:#8C6A1A;vertical-align:-1px;margin-right:3px"></i> Classe entière</span>
            <span><i data-lucide="check" style="width:11px;height:11px;stroke:#8C6A1A;vertical-align:-1px;margin-right:3px"></i> Tableau prof</span>
            <span><i data-lucide="check" style="width:11px;height:11px;stroke:#8C6A1A;vertical-align:-1px;margin-right:3px"></i> Tout Premium inclus</span>
            <span><i data-lucide="check" style="width:11px;height:11px;stroke:#8C6A1A;vertical-align:-1px;margin-right:3px"></i> Rapport mensuel</span>
          </div>
        </div>
      </div>

      <div style="background:linear-gradient(135deg,#4f1d9610,#007A5E10);border:1px solid var(--primary);border-radius:12px;padding:14px 16px;font-size:13px;color:var(--gris-700);margin-bottom:20px;display:flex;gap:10px;align-items:center">
        <i data-lucide="shield-check" style="width:20px;height:20px;stroke:var(--primary);flex-shrink:0"></i>
        <span>Paiement via <strong>M-Pesa</strong>, <strong>Airtel Money</strong> ou <strong>Orange Money</strong>. Annulation à tout moment.</span>
      </div>

      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <button onclick="obNext(2)" class="btn btn-ghost" style="flex:0 0 auto">
          <i data-lucide="arrow-left" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px"></i> Retour
        </button>
        <a href="/reussiteplus/tarifs.php" class="btn btn-primary" style="flex:1;justify-content:center;text-decoration:none">
          <i data-lucide="crown" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px"></i> Voir tous les plans
        </a>
        <button onclick="obClose()" class="btn btn-ghost" style="flex:0 0 auto;font-size:12px;color:var(--gris-400)">
          Commencer gratuitement
        </button>
      </div>
    </div>

    <!-- Étape 4 — C'est parti ! (masquée, utilisée pour la complétion) -->
    <div class="ob-step" id="ob-step-4" style="padding:48px 40px;display:none;text-align:center">
      <div style="width:72px;height:72px;background:linear-gradient(135deg,#22C55E,#16A34A);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:20px">
        <i data-lucide="check" style="width:36px;height:36px;stroke:#fff"></i>
      </div>
      <h3 style="font-family:var(--font-display);font-size:22px;font-weight:800;margin-bottom:10px">Vous êtes prêt, <?= e($user['prenom']) ?> !</h3>
      <p style="color:var(--gris-500);font-size:14px;margin-bottom:28px;max-width:380px;margin-left:auto;margin-right:auto;line-height:1.7">
        Votre tableau de bord est prêt. Passez votre premier examen dès maintenant et commencez à progresser.
      </p>
      <button onclick="obClose()" class="btn btn-primary btn-lg" style="min-width:220px">
        <i data-lucide="play" style="width:16px;height:16px;vertical-align:-2px;margin-right:6px"></i> Commencer maintenant
      </button>
    </div>

    <!-- Indicateurs de navigation -->
    <div style="display:flex;justify-content:center;gap:8px;padding:16px 0 24px;position:sticky;bottom:0;background:var(--blanc)">
      <?php for ($i = 1; $i <= 4; $i++): ?>
      <button onclick="obNext(<?= $i ?>)" id="ob-dot-<?= $i ?>"
        style="width:8px;height:8px;border-radius:50%;border:none;cursor:pointer;transition:all .3s;background:<?= $i === 1 ? 'var(--primary)' : 'var(--gris-200)' ?>;padding:0"></button>
      <?php endfor; ?>
    </div>

  </div>
</div>

<script>
let obCurrentStep = 1;
const obTotalSteps = 4;

function obNext(step) {
  // Masquer l'étape courante
  document.getElementById('ob-step-' + obCurrentStep).style.display = 'none';
  document.getElementById('ob-dot-' + obCurrentStep).style.background = 'var(--gris-200)';
  document.getElementById('ob-dot-' + obCurrentStep).style.width = '8px';

  obCurrentStep = step;

  // Afficher la nouvelle étape
  document.getElementById('ob-step-' + obCurrentStep).style.display = 'block';
  document.getElementById('ob-dot-' + obCurrentStep).style.background = 'var(--primary)';
  document.getElementById('ob-dot-' + obCurrentStep).style.width = '20px';
  document.getElementById('ob-dot-' + obCurrentStep).style.borderRadius = '4px';

  // Mettre à jour la barre de progression
  document.getElementById('obProgress').style.width = (obCurrentStep / obTotalSteps * 100) + '%';

  // Re-initialiser les icônes Lucide pour les étapes chargées
  if (typeof lucide !== 'undefined') lucide.createIcons();

  // Scroller en haut du modal
  document.getElementById('onboardingModal').scrollTo({ top: 0, behavior: 'smooth' });
}

function obClose() {
  const overlay = document.getElementById('onboardingOverlay');
  overlay.style.opacity = '0';
  overlay.style.transition = 'opacity .3s ease';
  setTimeout(() => overlay.remove(), 300);
  // Nettoyer l'URL
  if (history.replaceState) history.replaceState({}, '', window.location.pathname);
}

// Fermer en cliquant sur l'overlay (hors modal)
document.getElementById('onboardingOverlay').addEventListener('click', function(e) {
  if (e.target === this) obClose();
});

// Initialiser les icônes
if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_app.php'; ?>
