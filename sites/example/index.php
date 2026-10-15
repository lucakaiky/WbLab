<?php
declare(strict_types=1);

$host = (string) ($_SERVER['HTTP_HOST'] ?? 'example.localhost');
$phpVersion = PHP_VERSION;
$webPort = (string) (getenv('WEB_PORT') ?: '8090');
$lanPort = (string) (getenv('LAN_PORT') ?: '8092');
$phpMyAdminPort = (string) (getenv('PHPMYADMIN_PORT') ?: '8091');
$parsedHost = parse_url('http://' . $host, PHP_URL_HOST);
$hostName = strtolower(is_string($parsedHost) ? $parsedHost : 'localhost');
$isLocalHost = in_array($hostName, ['localhost', '127.0.0.1', '::1'], true)
    || str_ends_with($hostName, '.localhost');
$dashboardUrl = $isLocalHost
    ? 'http://localhost:' . $webPort . '/'
    : 'http://' . $host . '/';
$phpMyAdminUrl = 'http://localhost:' . $phpMyAdminPort . '/';

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1020">
    <title>Example Website — Local Host Lab</title>
    <style>
        :root {
            color-scheme: dark;
            --background: #0b1020;
            --panel: #111a2d;
            --border: #273b55;
            --text: #edf3ff;
            --muted: #a8b6cc;
            --accent: #75e0b2;
            --blue: #8ab4ff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            display: grid;
            min-height: 100vh;
            place-items: center;
            margin: 0;
            background: var(--background);
            color: var(--text);
            padding: 24px;
            font: 16px/1.6 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .card {
            width: min(720px, 100%);
            border: 1px solid var(--border);
            border-radius: 22px;
            background: var(--panel);
            padding: clamp(24px, 5vw, 48px);
            box-shadow: 0 24px 90px #02061766;
        }

        .eyebrow {
            color: var(--accent);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        h1 {
            margin: 12px 0;
            font-size: clamp(32px, 6vw, 50px);
            line-height: 1.08;
            letter-spacing: -0.05em;
        }

        p {
            color: var(--muted);
        }

        a {
            color: var(--blue);
        }

        code {
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            border: 1px solid #265b4b;
            border-radius: 999px;
            background: #18352f;
            color: var(--accent);
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 750;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin: 26px 0;
        }

        .detail {
            min-width: 0;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #0c1425;
            padding: 14px;
        }

        .label {
            color: var(--muted);
            font-size: 12px;
        }

        .value {
            overflow-wrap: anywhere;
            font-weight: 700;
        }

        @media (max-width: 520px) {
            .details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="card">
    <div class="eyebrow">Local Host / Example Website</div>
    <h1>PHP is working.</h1>
    <p>
        This is the example website included with Local Host Lab. Replace these
        files with your own PHP, HTML, CSS, and JavaScript files, or create a new
        folder inside <code>sites/</code>. The dashboard will detect it automatically.
    </p>

    <span class="status">● Apache is responding</span>

    <div class="details">
        <div class="detail">
            <div class="label">Current request host</div>
            <div class="value"><?= escapeHtml($host) ?></div>
        </div>
        <div class="detail">
            <div class="label">PHP version</div>
            <div class="value"><?= escapeHtml($phpVersion) ?></div>
        </div>
        <div class="detail">
            <div class="label">Database host from PHP</div>
            <div class="value">db:3306</div>
        </div>
        <div class="detail">
            <div class="label">LAN endpoint port</div>
            <div class="value"><?= escapeHtml($lanPort) ?></div>
        </div>
        <div class="detail">
            <div class="label">Database manager</div>
            <div class="value">
                <?php if ($isLocalHost): ?>
                    <a href="<?= escapeHtml($phpMyAdminUrl) ?>">Open phpMyAdmin</a>
                <?php else: ?>
                    Available on the hosting computer only (localhost:<?= escapeHtml($phpMyAdminPort) ?>)
                <?php endif; ?>
            </div>
        </div>
    </div>

    <p><a href="<?= escapeHtml($dashboardUrl) ?>">← Back to the hosting dashboard</a></p>
</main>
</body>
</html>
