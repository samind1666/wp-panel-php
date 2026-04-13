<?php
/**
 * WP Hosting Panel - Server Setup API
 * =====================================
 * Install server components ON-DEMAND from panel UI.
 * Uses background shell scripts + log file polling for progress.
 *
 * Endpoints:
 *   GET  /api/server?action=status          - Get all component statuses
 *   POST /api/server?action=install         - Start installing a component
 *   GET  /api/server?action=progress&c=xxx  - Poll installation progress
 *   POST /api/server?action=install-all     - Install recommended stack
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Status endpoint (no auth needed for some checks)
if ($action === 'status' && $method === 'GET') {
    handleStatus();
    exit;
}

// All other endpoints need auth
$user = authMiddleware();
if ($user['role'] !== 'admin') {
    jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
}

switch ($action) {
    case 'install':
        if ($method !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleInstall();
        break;
    case 'progress':
        if ($method !== 'GET') jsonResponse(['success' => false, 'error' => 'GET required'], 405);
        handleProgress();
        break;
    case 'install-all':
        if ($method !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleInstallAll();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}

/**
 * Get status of all server components
 */
function handleStatus(): void {
    $db = getDB();
    $stmt = $db->query("SELECT component, status, version, installed_at FROM server_components ORDER BY component");
    $components = [];
    while ($row = $stmt->fetch()) {
        $components[$row['component']] = $row;
    }

    // Also do live checks
    $live = [
        'webserver' => file_exists('/usr/local/lsws/bin/lswsctrl'),
        'php' => file_exists('/usr/bin/php8.0') || file_exists('/usr/local/lsws'),
        'mariadb' => file_exists('/usr/bin/mysql') || file_exists('/usr/bin/mariadb'),
        'phpmyadmin' => is_dir('/opt/phpmyadmin'),
        'postfix' => file_exists('/usr/sbin/postfix'),
        'security' => file_exists('/usr/bin/certbot') && file_exists('/usr/bin/fail2ban-client'),
        'wpcli' => file_exists('/usr/local/bin/wp'),
    ];

    // Sync live status to DB
    foreach ($live as $comp => $installed) {
        if ($installed && isset($components[$comp]) && $components[$comp]['status'] === 'not_installed') {
            $db->prepare("UPDATE server_components SET status = 'installed' WHERE component = ?")
                ->execute([$comp]);
            $components[$comp]['status'] = 'installed';
        }
    }

    // Get PHP version if installed
    $phpVersion = '';
    if ($live['php']) {
        if (file_exists('/usr/bin/php8.0')) {
            exec('/usr/bin/php8.0 -v 2>&1 | head -1', $out);
            $phpVersion = trim($out[0] ?? '');
        }
    }

    // Get webserver version
    $webVersion = '';
    if ($live['webserver']) {
        exec('/usr/local/lsws/bin/lswsctrl -v 2>&1', $out);
        $webVersion = trim($out[0] ?? 'OpenLiteSpeed');
    }

    // Get MariaDB version
    $dbVersion = '';
    if ($live['mariadb']) {
        exec('mysql --version 2>&1', $out);
        $dbVersion = trim($out[0] ?? '');
    }

    // Server info
    $cpuLoad = sys_getloadavg();
    $memInfo = file_exists('/proc/meminfo') ? file('/proc/meminfo') : [];
    $totalMem = $freeMem = 0;
    foreach ($memInfo as $line) {
        if (strpos($line, 'MemTotal') === 0) $totalMem = (int) preg_replace('/\D/', '', $line);
        if (strpos($line, 'MemAvailable') === 0) $freeMem = (int) preg_replace('/\D/', '', $line);
    }

    jsonResponse([
        'success' => true,
        'components' => $components,
        'live' => $live,
        'versions' => [
            'php' => $phpVersion,
            'webserver' => $webVersion,
            'database' => $dbVersion,
        ],
        'server' => [
            'cpu_load' => $cpuLoad,
            'memory_total_mb' => round($totalMem / 1024),
            'memory_available_mb' => round($freeMem / 1024),
            'memory_used_percent' => $totalMem > 0 ? round((($totalMem - $freeMem) / $totalMem) * 100, 1) : 0,
        ],
    ]);
}

/**
 * Start installing a component in background
 */
function handleInstall(): void {
    $component = input('component');
    $validComponents = ['webserver', 'php', 'mariadb', 'phpmyadmin', 'postfix', 'security', 'wpcli'];

    if (!$component || !in_array($component, $validComponents)) {
        jsonResponse(['success' => false, 'error' => 'Invalid component. Valid: ' . implode(', ', $validComponents)], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT status FROM server_components WHERE component = ?");
    $stmt->execute([$component]);
    $current = $stmt->fetch();

    if ($current && ($current['status'] === 'installing' || $current['status'] === 'installed')) {
        jsonResponse(['success' => false, 'error' => "Component is {$current['status']}"], 400);
    }

    // Mark as installing
    $logFile = __DIR__ . '/../logs/install_' . $component . '.log';
    file_put_contents($logFile, '');

    $db->prepare("UPDATE server_components SET status = 'installing', install_log = '' WHERE component = ?")
        ->execute([$component]);

    // Run install script in background
    $scriptPath = __DIR__ . '/../scripts/install-component.sh';
    $phpBin = PHP_BINARY ?: '/usr/bin/php8.0';
    $cmd = "nohup bash {$scriptPath} {$component} {$logFile} > /dev/null 2>&1 & echo $!";
    exec($cmd, $out, $ret);
    $pid = trim($out[0] ?? '0');

    jsonResponse([
        'success' => true,
        'message' => "Installation started for {$component}",
        'pid' => (int) $pid,
        'poll_url' => "/api/server?action=progress&c={$component}",
    ]);
}

/**
 * Poll installation progress
 */
function handleProgress(): void {
    $component = $_GET['c'] ?? '';
    if (!$component) {
        jsonResponse(['success' => false, 'error' => 'Component name required (c=)'], 400);
    }

    $logFile = __DIR__ . '/../logs/install_' . $component . '.log';
    $log = '';
    $percent = 0;
    $status = 'installing';

    if (file_exists($logFile)) {
        $log = file_get_contents($logFile);
        // Count lines to estimate progress
        $lines = array_filter(explode("\n", trim($log)));
        $totalSteps = 0;

        // Estimate total steps based on component
        $stepsMap = [
            'webserver' => 5,
            'php' => 4,
            'mariadb' => 4,
            'phpmyadmin' => 3,
            'postfix' => 2,
            'security' => 4,
            'wpcli' => 2,
        ];
        $totalSteps = $stepsMap[$component] ?? 5;
        $completedLines = count(array_filter($lines, function($l) { return strpos($l, '[DONE]') !== false || strpos($l, '[OK]') !== false; }));
        $percent = min(95, round(($completedLines / $totalSteps) * 100));
    }

    // Check DB status
    $db = getDB();
    $stmt = $db->prepare("SELECT status, version, installed_at FROM server_components WHERE component = ?");
    $stmt->execute([$component]);
    $row = $stmt->fetch();

    if ($row) {
        $status = $row['status'];
        if ($status === 'installed') {
            $percent = 100;
        } elseif ($status === 'failed') {
            $percent = 0;
        }
    }

    jsonResponse([
        'success' => true,
        'component' => $component,
        'status' => $status,
        'percent' => $percent,
        'log' => $log,
    ]);
}

/**
 * Install all recommended components
 */
function handleInstallAll(): void {
    $components = ['webserver', 'php', 'mariadb', 'security', 'wpcli'];
    $started = [];

    foreach ($components as $comp) {
        $db = getDB();
        $stmt = $db->prepare("SELECT status FROM server_components WHERE component = ?");
        $stmt->execute([$comp]);
        $current = $stmt->fetch();

        if (!$current || $current['status'] === 'not_installed' || $current['status'] === 'failed') {
            $logFile = __DIR__ . '/../logs/install_' . $comp . '.log';
            file_put_contents($logFile, '');

            $db->prepare("UPDATE server_components SET status = 'installing', install_log = '' WHERE component = ?")
                ->execute([$comp]);

            $scriptPath = __DIR__ . '/../scripts/install-component.sh';
            exec("nohup bash {$scriptPath} {$comp} {$logFile} > /dev/null 2>&1 &", $out);
            $started[] = $comp;
        }
    }

    jsonResponse([
        'success' => true,
        'message' => 'Installation started for ' . count($started) . ' components',
        'started' => $started,
    ]);
}
