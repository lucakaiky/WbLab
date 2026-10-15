<?php
declare(strict_types=1);

session_start();

$sitesRoot = '/var/www/sites';
$runtimeRoot = '/var/www/runtime';
$lanStateFile = $runtimeRoot . '/lan-enabled';

$webPort = (string) (getenv('WEB_PORT') ?: '8090');
$lanPort = (string) (getenv('LAN_PORT') ?: '8092');
$phpMyAdminPort = (string) (getenv('PHPMYADMIN_PORT') ?: '8091');
$databasePort = (string) (getenv('DB_PORT') ?: '3310');

$httpHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ('localhost:' . $webPort)));
$parsedHost = parse_url('http://' . $httpHost, PHP_URL_HOST);
$hostName = strtolower(is_string($parsedHost) ? $parsedHost : 'localhost');
$isLocalHost = in_array($hostName, ['localhost', '127.0.0.1', '::1'], true)
    || str_ends_with($hostName, '.localhost');
$lanBaseUrl = 'http://' . $httpHost;

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['csrf_token'];
$noticeMessage = '';
$errorMessage = '';
$lanEnabled = is_file($lanStateFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if ($action === 'set-lan-mode') {
        if (!hash_equals($csrfToken, $submittedToken)) {
            http_response_code(403);
            $errorMessage = 'The request could not be verified. Refresh the page and try again.';
        } else {
            $requestedState = (string) ($_POST['lan_enabled'] ?? '');

            try {
                if (!in_array($requestedState, ['0', '1'], true)) {
                    throw new RuntimeException('The requested LAN state is invalid.');
                }

                if ($requestedState === '1') {
                    if (!is_dir($runtimeRoot) && !mkdir($runtimeRoot, 0775, true) && !is_dir($runtimeRoot)) {
                        throw new RuntimeException('The runtime directory could not be created.');
                    }

                    $writtenBytes = file_put_contents(
                        $lanStateFile,
                        "enabled\n",
                        LOCK_EX
                    );

                    if ($writtenBytes === false) {
                        throw new RuntimeException('Could not enable LAN access. Check the runtime directory permissions.');
                    }

                    $noticeMessage = 'LAN access is enabled. Hosted websites can now be opened from devices on your local network.';
                } else {
                    if (is_file($lanStateFile) && !unlink($lanStateFile)) {
                        throw new RuntimeException('Could not disable LAN access. Check the runtime directory permissions.');
                    }

                    $noticeMessage = $isLocalHost
                        ? 'LAN access is disabled. Hosted websites are now restricted to this computer.'
                        : 'LAN access is disabled. This dashboard is now available only on the hosting computer; open it through localhost to enable LAN access again.';
                }
            } catch (RuntimeException $exception) {
                $errorMessage = $exception->getMessage();
            }
        }

        $lanEnabled = is_file($lanStateFile);
    }
}

$projects = [];

if (is_dir($sitesRoot)) {
    foreach (scandir($sitesRoot) ?: [] as $name) {
        if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
            continue;
        }

        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*$/', $name)) {
            continue;
        }

        $projectPath = $sitesRoot . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($projectPath)) {
            continue;
        }

        $hasPhpIndex = is_file($projectPath . '/index.php');
        $hasHtmlIndex = is_file($projectPath . '/index.html');

        $projects[] = [
            'name' => $name,
            'ready' => $hasPhpIndex || $hasHtmlIndex,
            'php' => $hasPhpIndex,
        ];
    }
}

usort(
    $projects,
    static fn (array $first, array $second): int => strcasecmp($first['name'], $second['name'])
);

function h(string $value): string
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
    <title>WbLab</title>
    <style>
        :root {
            color-scheme: dark;
            --background: #0b1020;
            --panel: rgba(17, 26, 45, 0.9);
            --panel-soft: #151f34;
            --border: #27344b;
            --text: #edf3ff;
            --muted: #9aa9c2;
            --accent: #75e0b2;
            --blue: #8ab4ff;
            --warning: #ffd28a;
            --danger: #ff9b9b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background:
                radial-gradient(ellipse at top left, #172744 0, transparent 42%),
                var(--background);
            color: var(--text);
            font: 15px/1.55 Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input {
            font: inherit;
        }

        code {
            overflow-wrap: anywhere;
            border-radius: 5px;
            background: #0a1221;
            color: var(--accent);
            padding: 2px 5px;
            font-size: 0.92em;
        }

        .wrap {
            width: min(1120px, 100%);
            margin: 0 auto;
            padding: 34px 22px 60px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 46px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .brand-icon {
            display: grid;
            width: 42px;
            height: 42px;
            place-items: center;
            border: 1px solid #3a506d;
            border-radius: 13px;
            background: #172740;
            color: var(--accent);
            font-size: 22px;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            width: fit-content;
            border: 1px solid #265b4b;
            border-radius: 999px;
            background: #18352f;
            color: var(--accent);
            padding: 6px 11px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .pill.off {
            border-color: #59492d;
            background: #332b1e;
            color: var(--warning);
        }

        .hero {
            max-width: 760px;
            margin-bottom: 28px;
        }

        .eyebrow {
            color: var(--accent);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        h1 {
            margin: 12px 0;
            font-size: clamp(34px, 5vw, 52px);
            line-height: 1.08;
            letter-spacing: -0.05em;
        }

        .hero p {
            max-width: 680px;
            margin: 0;
            color: var(--muted);
            font-size: 16px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin: 30px 0 28px;
        }

        .stat,
        .panel {
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--panel);
        }

        .stat {
            padding: 18px 20px;
        }

        .stat-value {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
        }

        .panel {
            padding: 21px;
            margin-bottom: 24px;
        }

        .panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 13px;
        }

        .panel-heading h2,
        .section-heading h2 {
            margin: 0;
            font-size: 19px;
            letter-spacing: -0.025em;
        }

        .panel-heading p,
        .section-heading p,
        .muted {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .lan-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 17px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 38px;
            border: 1px solid #3b4e6a;
            border-radius: 10px;
            background: #1a2b45;
            color: var(--text);
            padding: 9px 13px;
            font-size: 13px;
            font-weight: 750;
            white-space: nowrap;
            cursor: pointer;
            transition: border-color 120ms ease, transform 120ms ease;
        }

        .button:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .button:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 3px;
        }

        .button.primary {
            border-color: var(--accent);
            background: var(--accent);
            color: #092318;
        }

        .button.primary:hover {
            border-color: #a1f0ce;
            background: #a1f0ce;
            color: #092318;
        }

        .button.danger {
            border-color: #70434b;
            background: #3a222d;
            color: #ffd8df;
        }

        .button.small {
            min-height: 33px;
            padding: 7px 10px;
            font-size: 12px;
        }

        .notice {
            border: 1px solid #265b4b;
            border-radius: 10px;
            background: #102b25;
            color: #baf5dc;
            padding: 11px 13px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .notice.error {
            border-color: #70434b;
            background: #321f29;
            color: #ffd8df;
        }

        .lan-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 18px;
        }

        .detail-box {
            min-width: 0;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: rgba(9, 17, 32, 0.55);
            padding: 13px;
        }

        .detail-label {
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .detail-value {
            overflow-wrap: anywhere;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
        }

        .section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin: 0 0 14px;
        }

        .project-list {
            display: grid;
            gap: 10px;
        }

        .project {
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: rgba(17, 26, 45, 0.8);
            padding: 16px 17px;
        }

        .project-icon {
            display: grid;
            flex: 0 0 42px;
            width: 42px;
            height: 42px;
            place-items: center;
            border-radius: 12px;
            background: #1a2a43;
            color: var(--blue);
            font-size: 17px;
            font-weight: 800;
        }

        .project-main {
            min-width: 0;
            flex: 1;
        }

        .project-title {
            font-size: 15px;
            font-weight: 800;
        }

        .project-url {
            overflow-wrap: anywhere;
            color: var(--muted);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
        }

        .project-status {
            display: inline-flex;
            margin-top: 6px;
            border-radius: 99px;
            background: #18352f;
            color: var(--accent);
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .project-status.waiting {
            background: #3b3020;
            color: var(--warning);
        }

        .project-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 7px;
        }

        .empty {
            border: 1px dashed #3a4b66;
            border-radius: 14px;
            color: var(--muted);
            padding: 30px 20px;
            text-align: center;
        }

        .empty strong {
            display: block;
            margin-bottom: 7px;
            color: var(--text);
        }

        .lower-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 25px;
        }

        .steps {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: var(--muted);
            font-size: 13px;
        }

        .step-number {
            display: grid;
            flex: 0 0 22px;
            width: 22px;
            height: 22px;
            place-items: center;
            border-radius: 7px;
            background: #1c2d48;
            color: var(--blue);
            font-size: 11px;
            font-weight: 800;
        }

        footer {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            margin-top: 30px;
            color: #70819d;
            font-size: 12px;
        }

        @media (max-width: 760px) {
            .wrap {
                padding: 22px 14px 40px;
            }

            .topbar {
                align-items: flex-start;
                margin-bottom: 34px;
            }

            .stats {
                gap: 8px;
            }

            .stat {
                padding: 13px;
            }

            .stat-value {
                font-size: 21px;
            }

            .stat-label {
                font-size: 11px;
            }

            .project {
                align-items: flex-start;
                flex-wrap: wrap;
                padding: 14px;
            }

            .project-main {
                min-width: calc(100% - 62px);
            }

            .project-actions {
                width: 100%;
                justify-content: flex-start;
                padding-left: 57px;
            }

            .lan-details,
            .lower-grid {
                grid-template-columns: 1fr;
            }

            .section-heading,
            .panel-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            footer {
                flex-direction: column;
            }
        }

        @media (max-width: 430px) {
            .topbar {
                flex-direction: column;
            }

            .brand {
                font-size: 17px;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header class="topbar">
        <a class="brand" href="/" aria-label="Local Host Lab home">
            <span class="brand-icon">⌘</span>
            <span>WbLab <span style="color: var(--accent);">/</span> ^_^</span>
        </a>
        <span class="pill<?= $lanEnabled ? '' : ' off' ?>">
            <?= $lanEnabled ? '● LAN hosting enabled' : '● LAN hosting disabled' ?>
        </span>
    </header>

    <main>
        <section class="hero">
            <div class="eyebrow">Local development environment</div>
            <h1>Your websites.<br>Your PHP. Your databases.</h1>
            <p>
                Put each website in its own folder inside <code>sites/</code>.
                Apache detects the folders automatically, so you do not need to
                create a separate container for each PHP website.
            </p>
        </section>

        <section class="stats" aria-label="Hosting overview">
            <div class="stat">
                <div class="stat-value"><?= count($projects) ?></div>
                <div class="stat-label">Website projects</div>
            </div>
            <div class="stat">
                <div class="stat-value">PHP 8.3</div>
                <div class="stat-label">Apache · MySQLi · PDO</div>
            </div>
            <div class="stat">
                <div class="stat-value">MariaDB</div>
                <div class="stat-label">Database management via phpMyAdmin</div>
            </div>
        </section>

        <section class="panel" aria-labelledby="lan-heading">
            <div class="panel-heading">
                <div>
                    <h2 id="lan-heading">LAN access</h2>
                    <p>
                        Control whether hosted websites can be opened from other
                        devices on your home or office network.
                    </p>
                </div>
                <span class="pill<?= $lanEnabled ? '' : ' off' ?>">
                    <?= $lanEnabled ? 'Enabled' : 'Disabled' ?>
                </span>
            </div>

            <?php if ($noticeMessage !== ''): ?>
                <div class="notice" role="status"><?= h($noticeMessage) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="notice error" role="alert"><?= h($errorMessage) ?></div>
            <?php endif; ?>

            <p class="muted">
                <?= $lanEnabled
                    ? 'Other devices can open hosted websites using this computer’s LAN IP address. Turn LAN access off when you finish testing.'
                    : 'Hosted websites and the dashboard are restricted to this computer. Open the dashboard through localhost to enable LAN access.' ?>
            </p>

            <?php if ($isLocalHost || $lanEnabled): ?>
                <form class="lan-controls" method="post" action="/">
                    <input type="hidden" name="action" value="set-lan-mode">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="lan_enabled" value="<?= $lanEnabled ? '0' : '1' ?>">
                    <?php if ($lanEnabled): ?>
                        <button class="button danger" type="submit">Disable LAN access</button>
                    <?php else: ?>
                        <button class="button primary" type="submit">Enable LAN access</button>
                    <?php endif; ?>
                    <span class="muted">
                        This changes the access rule immediately; no container restart is needed.
                    </span>
                </form>
            <?php else: ?>
                <p class="muted">
                    LAN access is off. Open the dashboard on the hosting computer using localhost to enable it again.
                </p>
            <?php endif; ?>

            <div class="lan-details">
                <div class="detail-box">
                    <div class="detail-label">LAN website address pattern</div>
                    <div class="detail-value">
                        <?php if ($isLocalHost): ?>
                            http://&lt;computer-lan-ip&gt;:<?= h($lanPort) ?>/sites/&lt;site-name&gt;/
                        <?php else: ?>
                            <?= h($lanBaseUrl) ?>/sites/&lt;site-name&gt;/
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detail-box">
                    <div class="detail-label">Database and phpMyAdmin</div>
                    <div class="detail-value">
                        phpMyAdmin: localhost:<?= h($phpMyAdminPort) ?><br>
                        Database: 127.0.0.1:<?= h($databasePort) ?>
                    </div>
                </div>
            </div>
            <p class="muted">
                The LAN endpoint uses port <?= h($lanPort) ?>. The local endpoint and phpMyAdmin
                remain restricted to the hosting computer.
            </p>
        </section>

        <section aria-labelledby="sites-heading">
            <div class="section-heading">
                <div>
                    <h2 id="sites-heading">Your websites</h2>
                    <p>Each valid folder inside <code>sites/</code> is listed here.</p>
                </div>
                <a class="button primary" href="#add-website">How to add a website ↓</a>
            </div>

            <?php if ($projects === []): ?>
                <div class="empty">
                    <strong>No websites found yet.</strong>
                    Create a folder such as <code>sites/my-website/index.php</code> and refresh this page.
                </div>
            <?php else: ?>
                <div class="project-list">
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $slug = $project['name'];
                        $encodedSlug = rawurlencode($slug);
                        $localDomainUrl = 'http://' . $slug . '.localhost:' . $webPort . '/';
                        $pathUrl = '/sites/' . $encodedSlug . '/';
                        $lanUrl = $lanBaseUrl . $pathUrl;
                        $canOpenLanSite = $isLocalHost || $lanEnabled;
                        ?>
                        <article class="project">
                            <div class="project-icon">&lt;/&gt;</div>
                            <div class="project-main">
                                <div class="project-title"><?= h($slug) ?></div>
                                <?php if ($isLocalHost): ?>
                                    <div class="project-url"><?= h($slug) ?>.localhost:<?= h($webPort) ?></div>
                                <?php else: ?>
                                    <div class="project-url"><?= h($lanUrl) ?></div>
                                <?php endif; ?>
                                <span class="project-status<?= $project['ready'] ? '' : ' waiting' ?>">
                                    <?= $project['ready'] ? 'READY' : 'MISSING index.php / index.html' ?>
                                </span>
                            </div>
                            <div class="project-actions">
                                <?php if ($project['ready'] && $isLocalHost): ?>
                                    <a class="button primary small" href="<?= h($localDomainUrl) ?>" target="_blank" rel="noopener noreferrer">
                                        Open website ↗
                                    </a>
                                    <a class="button small" href="<?= h($pathUrl) ?>" target="_blank" rel="noopener noreferrer">
                                        Open by path ↗
                                    </a>
                                <?php elseif ($project['ready'] && $canOpenLanSite): ?>
                                    <a class="button primary small" href="<?= h($lanUrl) ?>" target="_blank" rel="noopener noreferrer">
                                        Open on LAN ↗
                                    </a>
                                <?php elseif ($project['ready']): ?>
                                    <span class="button small" aria-disabled="true" title="Enable LAN access to open this website from another device">
                                        LAN access is off
                                    </span>
                                <?php endif; ?>
                                <button
                                    class="button small"
                                    type="button"
                                    data-copy-url="<?= h($isLocalHost ? $localDomainUrl : $lanUrl) ?>"
                                    onclick="copyUrl(this)"
                                >
                                    Copy URL
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="lower-grid">
            <div class="panel">
                <h2>Databases and phpMyAdmin</h2>
                <p class="muted">
                    Create databases and users, edit tables, and import or export SQL files
                    using the phpMyAdmin interface.
                </p>
                <?php if ($isLocalHost): ?>
                    <div class="lan-controls">
                        <a
                            class="button primary"
                            href="http://localhost:<?= h($phpMyAdminPort) ?>/"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Open phpMyAdmin ↗
                        </a>
                        <span class="button" aria-disabled="true">DB port <?= h($databasePort) ?></span>
                    </div>
                <?php else: ?>
                    <p class="muted">
                        phpMyAdmin is available only on the hosting computer at
                        <code>http://localhost:<?= h($phpMyAdminPort) ?>/</code>.
                    </p>
                <?php endif; ?>
            </div>

            <div class="panel" id="add-website">
                <h2>How to add a website</h2>
                <div class="steps">
                    <div class="step">
                        <span class="step-number">1</span>
                        <span>Create a folder, for example <code>sites/my-website/</code>.</span>
                    </div>
                    <div class="step">
                        <span class="step-number">2</span>
                        <span>Copy the website files into it, including <code>index.php</code> or <code>index.html</code>.</span>
                    </div>
                    <div class="step">
                        <span class="step-number">3</span>
                        <span>Refresh this dashboard. On this computer, open <code>http://my-website.localhost:<?= h($webPort) ?>/</code>.</span>
                    </div>
                    <div class="step">
                        <span class="step-number">4</span>
                        <span>For another device, enable LAN access and use the computer's IP with <code>/sites/my-website/</code>.</span>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <span>WbLab · Docker Compose</span>
        <span>For local development and testing. Use trusted website code only.</span>
    </footer>
</div>

<script>
    async function copyUrl(button) {
        const url = button.dataset.copyUrl;

        if (!url) {
            return;
        }

        try {
            await navigator.clipboard.writeText(url);
        } catch (error) {
            window.prompt('Copy this URL:', url);
            return;
        }

        const originalLabel = button.textContent;
        button.textContent = 'Copied';

        window.setTimeout(() => {
            button.textContent = originalLabel;
        }, 1200);
    }
</script>
</body>
</html>
