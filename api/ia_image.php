<?php
// Recherche d'image éducative via Wikipedia — gratuit, sans clé, fiable
chdir(dirname(__DIR__));
require_once 'includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = trim($_GET['q'] ?? '');
if (!$query) { http_response_code(400); echo json_encode(['error' => 'Requête vide']); exit; }

// 1. Chercher le titre Wikipedia le plus pertinent (fr puis en)
function wikiSearch(string $query, string $lang): ?string {
    $url = "https://{$lang}.wikipedia.org/w/api.php?"
         . http_build_query(['action'=>'query','list'=>'search','srsearch'=>$query,'format'=>'json','srlimit'=>1,'utf8'=>1]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_USERAGENT=>'ReussitePlus/1.0 (educational app)']);
    $r = curl_exec($ch); unset($ch);
    $d = json_decode($r, true);
    return $d['query']['search'][0]['title'] ?? null;
}

// 2. Récupérer l'image principale + résumé de la page
function wikiImage(string $title, string $lang): ?array {
    $url = "https://{$lang}.wikipedia.org/w/api.php?"
         . http_build_query(['action'=>'query','titles'=>$title,'prop'=>'pageimages|extracts',
           'pithumbsize'=>700,'exintro'=>1,'exchars'=>300,'format'=>'json','utf8'=>1]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_USERAGENT=>'ReussitePlus/1.0 (educational app)']);
    $r = curl_exec($ch); unset($ch);
    $d    = json_decode($r, true);
    $page = array_values($d['query']['pages'] ?? [])[0] ?? [];
    $img  = $page['thumbnail']['source'] ?? null;
    // Nettoyer le résumé HTML
    $desc = strip_tags($page['extract'] ?? '');
    $desc = preg_replace('/\s+/', ' ', $desc);
    $desc = mb_substr(trim($desc), 0, 220);
    return $img ? ['url' => $img, 'title' => $title, 'desc' => $desc, 'lang' => $lang,
                   'wiki' => "https://{$lang}.wikipedia.org/wiki/" . rawurlencode($title)] : null;
}

// Essayer FR d'abord, puis EN
$title  = wikiSearch($query, 'fr') ?? wikiSearch($query, 'en');
$result = $title ? wikiImage($title, strpos($title, ' ') !== false ? 'fr' : 'fr') : null;

// Si pas d'image en FR, essayer EN
if (!$result && $title) {
    $titleEn = wikiSearch($query, 'en');
    $result  = $titleEn ? wikiImage($titleEn, 'en') : null;
}

if (!$result) {
    echo json_encode(['error' => 'Aucune image trouvée pour : ' . $query]);
    exit;
}

echo json_encode([
    'success' => true,
    'url'     => $result['url'],
    'title'   => $result['title'],
    'desc'    => $result['desc'],
    'source'  => 'Wikipedia',
    'wiki'    => $result['wiki'],
]);
