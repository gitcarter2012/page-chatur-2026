<?php
$config = include 'config.php';
$requested_username = isset($_GET['username']) ? preg_replace('/[^\w\-]/', '', explode('?', $_GET['username'])[0]) : '';
$default_username = isset($config['featured_model_username']) ? preg_replace('/[^\w\-]/', '', (string)$config['featured_model_username']) : '';
$username = $requested_username !== '' ? $requested_username : $default_username;

if (!$username) {
    header('HTTP/1.0 400 Bad Request');
    echo 'Missing username. Set featured_model_username in config.php or pass ?username=';
    exit;
}

$cache_dir = __DIR__ . '/cache/';
$regions = ['northamerica', 'europe_russia', 'southamerica', 'asia', 'other'];
$model = null;
$model_online = false;

foreach ($regions as $region) {
    $file = $cache_dir . "cams_{$region}.json";
    if (!file_exists($file)) {
        continue;
    }

    $json = json_decode(file_get_contents($file), true);
    if (!$json || !isset($json['results']) || !is_array($json['results'])) {
        continue;
    }

    foreach ($json['results'] as $m) {
        if (!isset($m['username'])) {
            continue;
        }

        if (strtolower($m['username']) === strtolower($username)) {
            $model = $m;
            $model_online = !empty($m['iframe_embed_revshare']);
            break 2;
        }
    }
}

if (!$model) {
    $profile_file = $cache_dir . 'model_profiles.json';
    if (file_exists($profile_file)) {
        $profiles = json_decode(file_get_contents($profile_file), true);
        if (is_array($profiles)) {
            $key = strtolower($username);
            if (isset($profiles[$key]) && is_array($profiles[$key])) {
                $model = $profiles[$key];
            } else {
                foreach ($profiles as $m) {
                    if (isset($m['username']) && strtolower($m['username']) === $key) {
                        $model = $m;
                        break;
                    }
                }
            }
        }
    }
}

if (!$model) {
    header('HTTP/1.0 404 Not Found');
}

function chaturbate_whitelabel_replace($html, $wldomain)
{
    if (!$wldomain || $wldomain === 'chaturbate.com') {
        return $html;
    }

    return preg_replace_callback(
        '#(https?:)?//(www\.)?chaturbate\.com#i',
        function ($matches) use ($wldomain) {
            return ($matches[1] ? $matches[1] : 'https:') . '//' . $wldomain;
        },
        (string)$html
    );
}

function ensure_iframe_fullscreen($iframe_html)
{
    $iframe_html = preg_replace('/<iframe(?![^>]+allowfullscreen)/i', '<iframe allowfullscreen', $iframe_html);
    $iframe_html = preg_replace('/<iframe(?![^>]+allow="[^"]*fullscreen[^"]*")/i', '<iframe allow="autoplay; fullscreen"', $iframe_html);
    $iframe_html = preg_replace('/width\s*=\s*["\']?\d+["\']?/i', 'width="100%"', $iframe_html);
    $iframe_html = preg_replace('/height\s*=\s*["\']?\d+["\']?/i', 'height="100%"', $iframe_html);
    $iframe_html = preg_replace('/style=(["\']).*?\1/i', '', $iframe_html);
    $iframe_html = preg_replace('/<iframe/i', '<iframe class="cb-live-iframe" scrolling="no" style="overflow:hidden;border:0;"', $iframe_html);
    return $iframe_html;
}

$display_name = htmlspecialchars($model['username'] ?? $username, ENT_QUOTES, 'UTF-8');
$site_name = htmlspecialchars($config['site_name'] ?? 'Live Cams', ENT_QUOTES, 'UTF-8');
$embed = '';

if ($model_online && !empty($model['iframe_embed_revshare'])) {
    $embed = ensure_iframe_fullscreen(
        chaturbate_whitelabel_replace($model['iframe_embed_revshare'], $config['whitelabel_domain'] ?? '')
    );
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $display_name ?> live | <?= $site_name ?></title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #08090c;
            --panel: #11141a;
            --text: #f2f3f5;
            --muted: #a4aab7;
            --accent: #26c281;
            --line: #242b36;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(1200px 500px at 50% -10%, #1b2330, var(--bg));
            color: var(--text);
            font-family: Segoe UI, Tahoma, sans-serif;
        }

        .app {
            min-height: 100%;
            display: grid;
            grid-template-rows: auto 1fr;
        }

        .top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--line);
            background: rgba(8, 9, 12, 0.75);
            backdrop-filter: blur(8px);
        }

        .title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 0 rgba(38, 194, 129, 0.6);
            animation: pulse 1.8s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(38, 194, 129, 0.6); }
            70% { box-shadow: 0 0 0 10px rgba(38, 194, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(38, 194, 129, 0); }
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            color: var(--text);
            border: 1px solid var(--line);
            background: var(--panel);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
        }

        .btn:hover { border-color: #3a4659; }

        .stage {
            min-height: 0;
            padding: 12px;
        }

        .player {
            width: 100%;
            height: calc(100vh - 78px);
            background: #000;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
        }

        .cb-live-iframe {
            display: block;
            width: 100%;
            height: 100%;
        }

        .state {
            height: calc(100vh - 78px);
            display: grid;
            place-items: center;
            text-align: center;
            background: #0b0d12;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 24px;
        }

        .state p {
            margin: 0;
            color: var(--muted);
            max-width: 600px;
        }

        @media (max-width: 700px) {
            .title { font-size: 14px; }
            .btn { font-size: 13px; padding: 7px 10px; }
            .player, .state { height: calc(100vh - 116px); }
        }
    </style>
</head>
<body>
<div class="app">
    <div class="top">
        <div class="title">
            <span class="dot" aria-hidden="true"></span>
            <span><?= $display_name ?> ao vivo</span>
        </div>
        <div class="actions">
            <a class="btn" href="/model/<?= rawurlencode($username) ?>">Perfil completo</a>
            <a class="btn" href="/">Voltar</a>
        </div>
    </div>

    <div class="stage">
        <?php if (!empty($embed)): ?>
            <div class="player"><?= $embed ?></div>
        <?php else: ?>
            <div class="state">
                <p>
                    A transmissao nao esta disponivel agora para esse usuario. Atualize o cache com fetch-and-cache.php e tente novamente.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
