<?php
/**
 * WP Hosting Panel - PHP Settings API
 * ====================================
 * GET  /api/php-settings?action=get     - Get PHP settings
 * POST /api/php-settings?action=update  - Update PHP version & settings
 * GET  /api/php-settings?action=versions - List available versions
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get':
        handleGet();
        break;
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleUpdate();
        break;
    case 'versions':
        handleVersions();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}

function handleGet(): void {
    $user = authMiddleware();
    $websiteId = $_GET['website_id'] ?? '';

    if (!$websiteId) jsonResponse(['success' => false, 'error' => 'website_id required'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT php_version, doc_root FROM websites WHERE id = ? AND (user_id = ? OR ? = 'admin')");
    $stmt->execute([$websiteId, $user['id'], $user['role']]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    $phpVersion = $website['php_version'];
    $domain = basename(dirname($website['doc_root']));

    // Get PHP-FPM pool config
    $poolConf = "/etc/php/{$phpVersion}/fpm/pool.d/{$domain}.conf";
    $settings = [
        'version' => $phpVersion,
        'memory_limit' => '256M',
        'max_execution_time' => '300',
        'upload_max_filesize' => '64M',
        'post_max_size' => '128M',
        'max_input_vars' => '3000',
        'display_errors' => 'Off',
        'opcache_enable' => '1',
    ];

    if (file_exists($poolConf)) {
        $content = file_get_contents($poolConf);
        if (preg_match('/php_admin_value\[memory_limit\]\s*=\s*(.+)/', $content, $m)) $settings['memory_limit'] = trim($m[1]);
        if (preg_match('/php_admin_value\[max_execution_time\]\s*=\s*(.+)/', $content, $m)) $settings['max_execution_time'] = trim($m[1]);
        if (preg_match('/php_admin_value\[upload_max_filesize\]\s*=\s*(.+)/', $content, $m)) $settings['upload_max_filesize'] = trim($m[1]);
        if (preg_match('/php_admin_value\[post_max_size\]\s*=\s*(.+)/', $content, $m)) $settings['post_max_size'] = trim($m[1]);
    }

    // Also check php.ini
    $iniPath = "/etc/php/{$phpVersion}/fpm/php.ini";
    if (file_exists($iniPath)) {
        $iniContent = file_get_contents($iniPath);
        if (preg_match('/max_input_vars\s*=\s*(.+)/', $iniContent, $m)) $settings['max_input_vars'] = trim($m[1]);
        if (preg_match('/display_errors\s*=\s*(.+)/', $iniContent, $m)) $settings['display_errors'] = trim($m[1]);
        if (preg_match('/opcache\.enable\s*=\s*(.+)/', $iniContent, $m)) $settings['opcache_enable'] = trim($m[1]);
    }

    // PHP info summary
    $phpBinary = "/usr/bin/php{$phpVersion}";
    $phpInfo = [];
    if (file_exists($phpBinary)) {
        exec("{$phpBinary} -v 2>&1", $out);
        $phpInfo['version'] = trim($out[0]);
    }

    jsonResponse(['success' => true, 'settings' => $settings, 'php_info' => $phpInfo]);
}

function handleUpdate(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');
    $phpVersion = input('php_version');
    $memoryLimit = input('memory_limit', '256M');
    $maxExecutionTime = input('max_execution_time', '300');
    $uploadMaxFilesize = input('upload_max_filesize', '64M');
    $postMaxSize = input('post_max_size', '128M');

    if (!$websiteId) jsonResponse(['success' => false, 'error' => 'website_id required'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM websites WHERE id = ? AND (user_id = ? OR ? = 'admin')");
    $stmt->execute([$websiteId, $user['id'], $user['role']]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    $domain = basename(dirname($website['doc_root']));
    $newVersion = $phpVersion ?: $website['php_version'];

    // Validate PHP version
    $availableVersions = explode(',', PHP_VERSIONS);
    if (!in_array($newVersion, $availableVersions)) {
        jsonResponse(['success' => false, 'error' => "PHP {$newVersion} not available. Available: " . implode(', ', $availableVersions)], 400);
    }

    // Check PHP-FPM exists
    if (!file_exists("/etc/php/{$newVersion}/fpm")) {
        jsonResponse(['success' => false, 'error' => "PHP {$newVersion} FPM not installed. Install with: apt install php{$newVersion}-fpm php{$newVersion}-mysql"], 500);
    }

    $errors = [];

    // Create/update PHP-FPM pool config
    $poolConf = "/etc/php/{$newVersion}/fpm/pool.d/{$domain}.conf";
    $poolConfig = <<<POOL
[{$domain}]
user = www-data
group = www-data
listen = /run/php/php-{$newVersion}-fpm-\{$domain}.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

php_admin_value[memory_limit] = {$memoryLimit}
php_admin_value[max_execution_time] = {$maxExecutionTime}
php_admin_value[upload_max_filesize] = {$uploadMaxFilesize}
php_admin_value[post_max_size] = {$postMaxSize}
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen
php_flag[display_errors] = Off
POOL;

    $result = file_put_contents($poolConf, $poolConfig);
    if ($result === false) {
        $errors[] = "Failed to write pool config";
    }

    // Update Nginx config to use correct PHP socket
    $nginxConf = NGINX_SITES_AVAILABLE . '/' . $website['domain'];
    if (file_exists($nginxConf)) {
        $nginxContent = file_get_contents($nginxConf);
        // Replace PHP socket version
        $nginxContent = preg_replace(
            '/php-\d+\.\d+-fpm\.sock/',
            "php-{$newVersion}-fpm-{$domain}.sock",
            $nginxContent
        );
        // Also replace domain-specific socket with generic one if pool uses domain socket
        $nginxContent = preg_replace(
            '/php-' . preg_quote($newVersion, '/') . '-fpm-[^.]+\.sock/',
            "php-{$newVersion}-fpm-{$domain}.sock",
            $nginxContent
        );
        file_put_contents($nginxConf, $nginxContent);

        exec("nginx -t 2>&1", $out, $ret);
        if ($ret === 0) {
            exec("systemctl reload nginx 2>&1");
        } else {
            $errors[] = "Nginx config test failed";
        }
    }

    // Restart PHP-FPM for the new version
    exec("systemctl restart php{$newVersion}-fpm 2>&1", $out, $ret);

    // Update database
    $db->prepare("UPDATE websites SET php_version = ? WHERE id = ?")->execute([$newVersion, $websiteId]);

    logActivity($user['id'], 'PHP Settings Updated', "PHP {$newVersion} configured for {$website['domain']}", 'success');

    jsonResponse([
        'success' => count($errors) === 0,
        'php_version' => $newVersion,
        'settings' => [
            'memory_limit' => $memoryLimit,
            'max_execution_time' => $maxExecutionTime,
            'upload_max_filesize' => $uploadMaxFilesize,
            'post_max_size' => $postMaxSize,
        ],
        'errors' => $errors,
        'message' => count($errors) === 0
            ? "PHP {$newVersion} configured successfully"
            : "PHP configured with some warnings",
    ]);
}

function handleVersions(): void {
    authMiddleware();

    $versions = [];
    foreach (explode(',', PHP_VERSIONS) as $v) {
        $installed = file_exists("/etc/php/{$v}/fpm");
        $binary = "/usr/bin/php{$v}";
        $versionStr = '';
        if ($installed && file_exists($binary)) {
            exec("{$binary} -v 2>&1", $out);
            $versionStr = trim($out[0] ?? '');
        }
        $versions[] = [
            'version' => $v,
            'installed' => $installed,
            'full_version' => $versionStr,
        ];
    }

    jsonResponse(['success' => true, 'versions' => $versions]);
}

function logActivity(string $userId, string $action, string $desc, string $type): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO activity_log (id, user_id, action, description, type, ip_address) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([generateId('a'), $userId, $action, $desc, $type, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
}
