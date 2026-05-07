<?php
/**
 * RÉUSSITE+ — Serveur de fichiers de cours sécurisé
 * URL: /reussiteplus/cours/fichier.php?m=Histoire&f=prehistoire.pdf
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Authentification requise
require_login();

// Paramètres
$matiere  = $_GET['m'] ?? '';
$filename = $_GET['f'] ?? '';

// Sécurité : interdire traversal de chemin
if (
    empty($matiere) || empty($filename) ||
    strpos($matiere, '..') !== false || strpos($matiere, '/') !== false || strpos($matiere, '\\') !== false ||
    strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false
) {
    http_response_code(400);
    die('Paramètre invalide.');
}

$basePath  = __DIR__;
$filePath  = $basePath . '/' . $matiere . '/' . $filename;

// Vérifier que le fichier existe
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Fichier introuvable</title>
    <style>body{font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#F1F5F9}
    .box{text-align:center;padding:48px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.1);max-width:400px}
    h1{font-size:20px;color:#1C2433;margin:16px 0 8px}p{color:#6B7280;font-size:14px}
    a{color:#007A5E;font-weight:600;text-decoration:none}</style></head>
    <body><div class="box">
    <div style="font-size:48px">📂</div>
    <h1>Fichier introuvable</h1>
    <p>Le fichier <strong>' . htmlspecialchars($filename, ENT_QUOTES) . '</strong> n\'a pas encore été uploadé par l\'équipe.</p>
    <a href="javascript:history.back()">← Retour</a>
    </div></body></html>';
    exit;
}

// Types MIME autorisés
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf'  => 'application/pdf',
    'mp4'  => 'video/mp4',
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'zip'  => 'application/zip',
    'txt'  => 'text/plain',
];

if (!isset($mimeTypes[$ext])) {
    http_response_code(403);
    die('Type de fichier non autorisé.');
}

$mime = $mimeTypes[$ext];
$size = filesize($filePath);

// Pour PDF, MP4, MP3 : afficher dans le navigateur (inline)
// Pour autres : forcer le téléchargement
$inline = in_array($ext, ['pdf', 'mp4', 'mp3', 'wav', 'jpg', 'jpeg', 'png', 'gif']);
$disposition = $inline ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"');
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

readfile($filePath);
exit;
