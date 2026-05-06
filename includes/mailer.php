<?php
// ============================================================
// RÉUSSITE+ | Envoi d'email
// Supporte : Brevo (API HTTP) ou mail() PHP natif en fallback
// Variables .env : BREVO_API_KEY, MAIL_FROM, MAIL_FROM_NAME
// ============================================================

/**
 * Envoie un email transactionnel.
 *
 * @param string $to       Adresse destinataire
 * @param string $name     Nom du destinataire
 * @param string $subject  Sujet
 * @param string $html     Corps HTML
 * @param string $text     Corps texte brut (fallback)
 * @return bool
 */
function send_email(string $to, string $name, string $subject, string $html, string $text = ''): bool {
    $from     = $_ENV['MAIL_FROM']      ?? 'noreply@reussiteplus.cd';
    $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'RÉUSSITE+';
    $apiKey   = $_ENV['BREVO_API_KEY']  ?? '';

    if ($apiKey) {
        return _send_brevo($to, $name, $subject, $html, $text, $from, $fromName, $apiKey);
    }

    // Fallback : mail() PHP natif
    if (!$text) $text = strip_tags($html);
    $boundary = md5(uniqid());
    $headers  = implode("\r\n", [
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        "From: $fromName <$from>",
        "Reply-To: $from",
        "X-Mailer: PHP/" . PHP_VERSION,
    ]);
    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $text . "\r\n\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $html . "\r\n\r\n";
    $body .= "--$boundary--";

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/**
 * Envoi via l'API Brevo (ex-Sendinblue) — aucune dépendance externe.
 */
function _send_brevo(
    string $to, string $name, string $subject, string $html, string $text,
    string $from, string $fromName, string $apiKey
): bool {
    $payload = json_encode([
        'sender'      => ['name' => $fromName, 'email' => $from],
        'to'          => [['email' => $to, 'name'  => $name]],
        'subject'     => $subject,
        'htmlContent' => $html,
        'textContent' => $text ?: strip_tags($html),
    ], JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", [
                'Content-Type: application/json',
                'Accept: application/json',
                'api-key: ' . $apiKey,
            ]),
            'content' => $payload,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);

    $resp = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $ctx);
    if ($resp === false) return false;

    $data = json_decode($resp, true);
    // Brevo renvoie {"messageId":"..."} en succès
    return isset($data['messageId']);
}

/**
 * Template HTML d'email générique avec couleurs RÉUSSITE+.
 *
 * @param string $title     Titre principal dans l'email
 * @param string $body      Contenu HTML central (paragraphes, boutons…)
 * @param string $preheader Texte de prévisualisation (avant l'ouverture)
 */
function email_template(string $title, string $body, string $preheader = ''): string {
    $year = date('Y');
    $preheaderHtml = $preheader
        ? '<div style="display:none;max-height:0;overflow:hidden;color:transparent;">' . htmlspecialchars($preheader) . '</div>'
        : '';
    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#F1F5F9;font-family:'Segoe UI',Arial,sans-serif;">
  {$preheaderHtml}
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F1F5F9;padding:32px 16px">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)">
        <!-- Header -->
        <tr>
          <td style="background:#007A5E;padding:28px 40px">
            <div style="font-family:'Segoe UI',Arial,sans-serif;font-size:22px;font-weight:900;color:#fff;letter-spacing:-0.5px">RÉUSSITE<span style="color:#C9972A">+</span></div>
            <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:2px;text-transform:uppercase;letter-spacing:1.5px">La plateforme éducative du Congo</div>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:36px 40px 28px">
            <h1 style="margin:0 0 20px;font-size:22px;font-weight:800;color:#1C2433;line-height:1.3">{$title}</h1>
            {$body}
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#F8FAFC;padding:20px 40px;border-top:1px solid #E2E8F0">
            <p style="margin:0;font-size:12px;color:#A0AEC0;line-height:1.6">
              Vous recevez cet email car votre adresse est associée à un compte RÉUSSITE+.<br>
              Si vous n'avez pas effectué cette action, ignorez simplement ce message.<br>
              &copy; {$year} RÉUSSITE+ — Tous droits réservés
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Génère le HTML d'un bouton CTA standard.
 */
function email_btn(string $url, string $label, string $color = '#007A5E'): string {
    return "<p style=\"margin:24px 0\"><a href=\"{$url}\" style=\"display:inline-block;background:{$color};color:#fff;font-weight:700;font-size:15px;text-decoration:none;padding:14px 28px;border-radius:8px\">{$label}</a></p>";
}
