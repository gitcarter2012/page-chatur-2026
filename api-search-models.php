<?php
header('Content-Type: application/json; charset=utf-8');

$libraryRoot = __DIR__ . '/Chaturbate-affiliate-api-script';
$cacheDir = $libraryRoot . '/cache';
$validRegions = ['northamerica', 'europe_russia', 'southamerica', 'asia', 'other'];

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

function bool_from_query($value)
{
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower((string)$value);
    return $normalized === '1' || $normalized === 'true' || $normalized === 'yes';
}

function extract_tokens_remaining($roomSubject)
{
    if (!is_string($roomSubject) || $roomSubject === '') {
        return null;
    }

    if (preg_match('/\[(\d+)\s+tokens?\s+remaining\]/i', $roomSubject, $matches)) {
        return (int)$matches[1];
    }

    return null;
}

$requestedRegions = [];
if (isset($_GET['region'])) {
    $input = is_array($_GET['region']) ? $_GET['region'] : [$_GET['region']];
    foreach ($input as $entry) {
        foreach (explode(',', (string)$entry) as $region) {
            $requestedRegions[] = trim($region);
        }
    }
}
$requestedRegions = array_values(array_intersect($requestedRegions, $validRegions));
if (count($requestedRegions) === 0) {
    $requestedRegions = $validRegions;
}

$modelMap = [];
foreach ($requestedRegions as $region) {
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

        $currentShow = strtolower((string)($m['current_show'] ?? ''));
        if ($currentShow === 'offline') {
            continue;
        }

        if (empty($m['iframe_embed_revshare'])) {
            continue;
        }

        $embedSrc = extract_iframe_src($m['iframe_embed_revshare']);
        if ($embedSrc === '') {
            continue;
        }

        $usernameKey = strtolower((string)$m['username']);
        $modelMap[$usernameKey] = [
            'username' => $m['username'],
            'display_name' => $m['display_name'] ?? $m['username'],
            'image_url' => $m['image_url_360x270'] ?? ($m['image_url'] ?? ''),
            'num_users' => (int)($m['num_users'] ?? 0),
            'room_subject' => $m['room_subject'] ?? '',
            'tokens_remaining' => extract_tokens_remaining($m['room_subject'] ?? ''),
            'region' => $region,
            'gender' => $m['gender'] ?? '',
            'is_hd' => !empty($m['is_hd']),
            'age' => isset($m['age']) ? (int)$m['age'] : null,
            'tags' => isset($m['tags']) && is_array($m['tags']) ? $m['tags'] : [],
            'current_show' => $m['current_show'] ?? '',
            'is_new' => !empty($m['is_new']),
            'seconds_online' => (int)($m['seconds_online'] ?? 0),
            'embed_src' => $embedSrc
        ];
    }
}

$results = array_values($modelMap);

if (isset($_GET['gender'])) {
    $values = is_array($_GET['gender']) ? $_GET['gender'] : [$_GET['gender']];
    $expanded = [];
    foreach ($values as $entry) {
        foreach (explode(',', (string)$entry) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $expanded[] = $item;
            }
        }
    }
    if (count($expanded) > 0) {
        $results = array_values(array_filter($results, function ($m) use ($expanded) {
            return in_array($m['gender'], $expanded, true);
        }));
    }
}

if (isset($_GET['tag'])) {
    $values = is_array($_GET['tag']) ? $_GET['tag'] : [$_GET['tag']];
    $expanded = [];
    foreach ($values as $entry) {
        foreach (explode(',', (string)$entry) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $expanded[] = strtolower($item);
            }
        }
    }

    if (count($expanded) > 0) {
        $results = array_values(array_filter($results, function ($m) use ($expanded) {
            $tags = array_map('strtolower', $m['tags']);
            foreach ($expanded as $needle) {
                if (in_array($needle, $tags, true)) {
                    return true;
                }
            }
            return false;
        }));
    }
}

if (isset($_GET['hd'])) {
    $wantHd = bool_from_query($_GET['hd']);
    $results = array_values(array_filter($results, function ($m) use ($wantHd) {
        return $m['is_hd'] === $wantHd;
    }));
}

$minAge = isset($_GET['minAge']) ? (int)$_GET['minAge'] : 18;
$maxAge = isset($_GET['maxAge']) ? (int)$_GET['maxAge'] : 99;
if (isset($_GET['minAge']) || isset($_GET['maxAge'])) {
    $results = array_values(array_filter($results, function ($m) use ($minAge, $maxAge) {
        if ($m['age'] === null) {
            return false;
        }
        return $m['age'] >= $minAge && $m['age'] <= $maxAge;
    }));
}

if (isset($_GET['size'])) {
    $sizes = [
        'intimate' => [0, 40],
        'mid' => [41, 120],
        'high' => [121, 999999]
    ];
    $size = (string)$_GET['size'];
    if (isset($sizes[$size])) {
        $min = $sizes[$size][0];
        $max = $sizes[$size][1];
        $results = array_values(array_filter($results, function ($m) use ($min, $max) {
            return $m['num_users'] >= $min && $m['num_users'] <= $max;
        }));
    }
}

if (isset($_GET['current_show'])) {
    $values = is_array($_GET['current_show']) ? $_GET['current_show'] : [$_GET['current_show']];
    $expanded = [];
    foreach ($values as $entry) {
        foreach (explode(',', (string)$entry) as $item) {
            $item = strtolower(trim($item));
            if ($item !== '') {
                $expanded[] = $item;
            }
        }
    }

    if (count($expanded) > 0) {
        $results = array_values(array_filter($results, function ($m) use ($expanded) {
            return in_array(strtolower((string)$m['current_show']), $expanded, true);
        }));
    }
}

if (isset($_GET['is_new'])) {
    $isNew = bool_from_query($_GET['is_new']);
    $results = array_values(array_filter($results, function ($m) use ($isNew) {
        return $m['is_new'] === $isNew;
    }));
}

if (isset($_GET['tokenMin']) || isset($_GET['tokenMax'])) {
    $tokenMin = isset($_GET['tokenMin']) && $_GET['tokenMin'] !== '' ? max(0, (int)$_GET['tokenMin']) : null;
    $tokenMax = isset($_GET['tokenMax']) && $_GET['tokenMax'] !== '' ? max(0, (int)$_GET['tokenMax']) : null;

    $results = array_values(array_filter($results, function ($m) use ($tokenMin, $tokenMax) {
        if (!isset($m['tokens_remaining']) || $m['tokens_remaining'] === null) {
            return false;
        }

        if ($tokenMin !== null && $m['tokens_remaining'] < $tokenMin) {
            return false;
        }

        if ($tokenMax !== null && $m['tokens_remaining'] > $tokenMax) {
            return false;
        }

        return true;
    }));
}

$sort = isset($_GET['sort']) ? strtolower((string)$_GET['sort']) : 'viewers';
if ($sort === 'recent') {
    usort($results, function ($a, $b) {
        return $a['seconds_online'] <=> $b['seconds_online'];
    });
} else {
    usort($results, function ($a, $b) {
        return $b['num_users'] <=> $a['num_users'];
    });
}

$total = count($results);
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : $total;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$results = array_slice($results, $offset, $limit);

echo json_encode([
    'ok' => true,
    'updated_at' => date('c'),
    'count' => $total,
    'results' => $results
], JSON_UNESCAPED_SLASHES);
