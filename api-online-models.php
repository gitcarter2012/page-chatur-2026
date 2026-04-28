<?php
header('Content-Type: application/json; charset=utf-8');

$libraryRoot = __DIR__ . '/Chaturbate-affiliate-api-script';
$cacheDir = $libraryRoot . '/cache';
$listFile = __DIR__ . '/followed-models.txt';
$regions = ['northamerica', 'europe_russia', 'southamerica', 'asia', 'other'];

if (!file_exists($listFile)) {
    echo json_encode([
        'ok' => false,
        'error' => 'followed-models.txt not found',
        'models' => []
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$followedLines = file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$followed = [];
foreach ($followedLines as $line) {
    $username = strtolower(trim($line));
    if ($username === '' || strpos($username, '#') === 0) {
        continue;
    }
    $followed[$username] = true;
}

if (count($followed) === 0) {
    echo json_encode([
        'ok' => true,
        'updated_at' => date('c'),
        'models' => []
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$online = [];

function extract_iframe_src($iframeHtml)
{
    if (!is_string($iframeHtml) || $iframeHtml === '') {
        return '';
    }

    if (preg_match('/src=["\']([^"\']+)["\']/i', $iframeHtml, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return '';
}

foreach ($regions as $region) {
    $cacheFile = $cacheDir . '/cams_' . $region . '.json';
    if (!file_exists($cacheFile)) {
        continue;
    }

    $json = json_decode(file_get_contents($cacheFile), true);
    if (!is_array($json) || !isset($json['results']) || !is_array($json['results'])) {
        continue;
    }

    foreach ($json['results'] as $m) {
        if (!isset($m['username'])) {
            continue;
        }

        $username = strtolower($m['username']);
        if (!isset($followed[$username])) {
            continue;
        }

        if (empty($m['iframe_embed_revshare'])) {
            continue;
        }

        $embedSrc = extract_iframe_src($m['iframe_embed_revshare']);
        if ($embedSrc === '') {
            continue;
        }

        $online[$username] = [
            'username' => $m['username'],
            'display_name' => $m['display_name'] ?? $m['username'],
            'image_url' => $m['image_url_360x270'] ?? ($m['image_url'] ?? ''),
            'num_users' => (int)($m['num_users'] ?? 0),
            'room_subject' => $m['room_subject'] ?? '',
            'region' => $region,
            'gender' => $m['gender'] ?? '',
            'seconds_online' => (int)($m['seconds_online'] ?? 0),
            'embed_src' => $embedSrc
        ];
    }
}

$models = array_values($online);
usort($models, function ($a, $b) {
    return ($a['seconds_online'] <=> $b['seconds_online']);
});

echo json_encode([
    'ok' => true,
    'updated_at' => date('c'),
    'count' => count($models),
    'models' => $models
], JSON_UNESCAPED_SLASHES);
