<?php
/**
 * RÉUSSITE+ — Composant publicités
 * Affiche une pub segmentée selon le plan et la page courante
 */

function render_ad(string $position = 'FEED', string $page = '*'): string {
    if (!function_exists('current_user')) return '';
    $user = current_user();
    if (!$user) return '';

    $plan = $user['plan'] ?? 'GRATUIT';

    try {
        $pub = dbRow(
            "SELECT * FROM publicites
             WHERE actif = 1
               AND JSON_CONTAINS(plans_cibles, ?)
               AND (date_debut IS NULL OR date_debut <= CURDATE())
               AND (date_fin IS NULL OR date_fin >= CURDATE())
               AND position = ?
             ORDER BY RAND()
             LIMIT 1",
            [json_encode($plan), $position]
        );
    } catch (Exception $e) { return ''; }

    if (!$pub) return '';

    // Enregistrer l'impression silencieusement
    try {
        dbQuery(
            "INSERT INTO ad_impressions (pub_id, user_id, type, page, ip_address) VALUES (?,?,?,?,?)",
            [$pub['id'], $user['id'], 'IMPRESSION', $_SERVER['REQUEST_URI'] ?? $page, $_SERVER['REMOTE_ADDR'] ?? '']
        );
    } catch (Exception $e) {}

    $img   = $pub['image_url']  ? '<img src="' . htmlspecialchars($pub['image_url']) . '" alt="' . htmlspecialchars($pub['titre']) . '" style="width:100%;border-radius:8px;display:block;margin-bottom:10px">' : '';
    $lien  = $pub['lien_url']   ? htmlspecialchars($pub['lien_url']) : '#';
    $cta   = htmlspecialchars($pub['cta_texte'] ?? 'En savoir plus');
    $desc  = $pub['description'] ? '<p style="font-size:12.5px;color:var(--gris-600);line-height:1.55;margin:6px 0 10px">' . htmlspecialchars($pub['description']) . '</p>' : '';
    $pubId = htmlspecialchars($pub['id']);

    $html = "
<div class='ad-block ad-{$position}' data-pub-id='{$pubId}'
     style='background:linear-gradient(135deg,var(--gris-50),#fff);border:1px solid var(--gris-200);border-radius:12px;padding:14px 16px;margin:16px 0;position:relative;overflow:hidden'>
  <div style='position:absolute;top:7px;right:9px;font-size:9px;color:var(--gris-400);font-weight:600;letter-spacing:.5px;text-transform:uppercase'>Publicité</div>
  {$img}
  <div style='font-size:13.5px;font-weight:700;color:var(--gris-900);margin-bottom:3px'>" . htmlspecialchars($pub['titre']) . "</div>
  {$desc}
  <a href='{$lien}' onclick='trackAdClick(\"{$pubId}\")' style='display:inline-flex;align-items:center;gap:5px;background:var(--primary);color:#fff;border-radius:8px;padding:7px 14px;font-size:12.5px;font-weight:700;text-decoration:none;transition:.15s' onmouseover='this.style.opacity=\".85\"' onmouseout='this.style.opacity=\"1\"'>
    <svg width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5' stroke-linecap='round'><line x1='5' y1='12' x2='19' y2='12'/><polyline points='12 5 19 12 12 19'/></svg>
    {$cta}
  </a>
</div>
<script>
function trackAdClick(pubId) {
  fetch('/reussiteplus/api/ad_track.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({pub_id: pubId, type: 'CLICK'})
  }).catch(()=>{});
}
</script>";

    return $html;
}
