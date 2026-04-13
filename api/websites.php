<?php
/**
 * WP Hosting Panel - Websites API
 * =================================
 * GET  /api/websites?action=list        - List all websites
 * GET  /api/websites?action=get&id=xxx  - Get single website
 * POST /api/websites?action=create      - Create new website
 * POST /api/websites?action=delete      - Delete website
 * POST /api/websites?action=toggle      - Toggle website status
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        if ($method !== 'GET') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleList();
        break;
    case 'get':
        if ($method !== 'GET') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleGet();
        break;
    case 'create':
        if ($method !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleCreate();
        break;
    case 'delete':
        if ($method !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleDelete();
        break;
    case 'toggle':
        if ($method !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleToggle();
        break;
    case 'stats':
        if ($method !== 'GET') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleStats();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}

function handleList(): void {
    $user = authMiddleware();
    $db = getDB();

    if ($user['role'] === 'admin') {
        $stmt = $db->query("
            SELECT w.*, u.name as owner_name, u.email as owner_email,
                   (SELECT COUNT(*) FROM databases WHERE website_id = w.id) as db_count,
                   (SELECT COUNT(*) FROM ftp_accounts WHERE website_id = w.id) as ftp_count,
                   (SELECT COUNT(*) FROM ssl_certs WHERE website_id = w.id) as ssl_count
            FROM websites w
            LEFT JOIN users u ON w.user_id = u.id
            ORDER BY w.created_at DESC
        ");
    } else {
        $stmt = $db->prepare("
            SELECT w.*,
                   (SELECT COUNT(*) FROM databases WHERE website_id = w.id) as db_count,
                   (SELECT COUNT(*) FROM ftp_accounts WHERE website_id = w.id) as ftp_count,
                   (SELECT COUNT(*) FROM ssl_certs WHERE website_id = w.id) as ssl_count
            FROM websites w
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$user['id']]);
    }

    $websites = $stmt->fetchAll();

    // Get server IP
    $serverIp = $_SERVER['SERVER_ADDR'] ?? '192.168.1.100';

    foreach ($websites as &$site) {
        $site['ip'] = $site['ip'] ?: $serverIp;
        $site['disk_total_mb'] = 10240; // 10 GB default
        $site['bandwidth_total_mb'] = 102400; // 100 GB default
    }

    jsonResponse(['success' => true, 'websites' => $websites]);
}

function handleGet(): void {
    $user = authMiddleware();
    $id = $_GET['id'] ?? '';

    if (!$id) jsonResponse(['success' => false, 'error' => 'Website ID required'], 400);

    $db = getDB();

    if ($user['role'] !== 'admin') {
        $stmt = $db->prepare("SELECT * FROM websites WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user['id']]);
    } else {
        $stmt = $db->prepare("SELECT * FROM websites WHERE id = ?");
        $stmt->execute([$id]);
    }

    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    // Get related data
    $dbs = $db->prepare("SELECT * FROM databases WHERE website_id = ?");
    $dbs->execute([$id]);
    $website['databases'] = $dbs->fetchAll();

    $ftps = $db->prepare("SELECT id, username, directory, quota_mb, status, created_at FROM ftp_accounts WHERE website_id = ?");
    $ftps->execute([$id]);
    $website['ftp_accounts'] = $ftps->fetchAll();

    $ssls = $db->prepare("SELECT * FROM ssl_certs WHERE website_id = ?");
    $ssls->execute([$id]);
    $website['ssl_certs'] = $ssls->fetchAll();

    $crons = $db->prepare("SELECT * FROM cron_jobs WHERE website_id = ? ORDER BY created_at DESC");
    $crons->execute([$id]);
    $website['cron_jobs'] = $crons->fetchAll();

    $backups = $db->prepare("SELECT * FROM backups WHERE website_id = ? ORDER BY created_at DESC");
    $backups->execute([$id]);
    $website['backups'] = $backups->fetchAll();

    $website['disk_total_mb'] = 10240;
    $website['bandwidth_total_mb'] = 102400;

    jsonResponse(['success' => true, 'website' => $website]);
}

function handleCreate(): void {
    $user = authMiddleware();
    $db = getDB();

    $domain = sanitizeDomain(input('domain', ''));
    $docRoot = input('doc_root', '');
    $phpVersion = input('php_version', DEFAULT_PHP);
    $dbName = input('db_name', '');
    $dbUser = input('db_user', '');
    $dbPass = input('db_password', '');

    // Validate
    if (!validateDomain($domain)) {
        jsonResponse(['success' => false, 'error' => 'Invalid domain format. Use: example.com'], 400);
    }

    // Check domain uniqueness
    $stmt = $db->prepare("SELECT id FROM websites WHERE domain = ?");
    $stmt->execute([$domain]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Domain already exists'], 409);
    }

    // Check user limit
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM websites WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $count = $stmt->fetch()['total'];

    $maxSites = $db->prepare("SELECT max_websites FROM users WHERE id = ?");
    $maxSites->execute([$user['id']]);
    $max = $maxSites->fetch()['max_websites'];
    if ($count >= $max) {
        jsonResponse(['success' => false, 'error' => "Website limit reached ($max)"], 403);
    }

    // Auto-generate paths
    if (!$docRoot) {
        $cleanDomain = preg_replace('/\.[^.]+$/', '', $domain);
        $docRoot = SITES_HOME . '/' . $cleanDomain . '/public_html';
    }
    if (!$dbName) $dbName = 'site_' . str_replace('.', '_', $domain);
    if (!$dbUser) $dbUser = str_replace('.', '_', $domain) . '_user';
    if (!$dbPass) $dbPass = randomPassword();

    $websiteId = generateId('w');
    $dbPassHash = base64_encode($dbPass); // Store encoded, not plain

    // === SERVER OPERATIONS ===

    // 1. Create directory structure
    $commands = [
        "mkdir -p {$docRoot}",
        "mkdir -p " . dirname($docRoot) . "/logs",
        "mkdir -p " . dirname($docRoot) . "/backups",
        "chown -R www-data:www-data " . dirname($docRoot),
        "chmod -R 755 " . dirname($docRoot),
    ];

    $errors = [];
    foreach ($commands as $cmd) {
        exec("{$cmd} 2>&1", $output, $return);
        if ($return !== 0) {
            $errors[] = implode("\n", $output);
        }
    }

    // 2. Create OpenLiteSpeed virtual host config
    $serverIp = $_SERVER['SERVER_ADDR'] ?? '192.168.1.100';
    $vhostDir = LSWS_VHOSTS_DIR . '/' . $domain;
    exec("mkdir -p {$vhostDir} 2>&1", $out, $ret);

    $lswsConfig = <<<LSWS
<?xml version="1.0" encoding="UTF-8"?>
<virtualHostConfig>
  <docRoot>{$docRoot}</docRoot>
  <vhDomain>{$domain} www.{$domain}</vhDomain>
  <enableGzip>1</enableGzip>
  <errorlog>{$vhostDir}/error.log</errorlog>
  <accesslog>{$vhostDir}/access.log</accesslog>
  <indexFiles>index.php index.html</indexFiles>
  <rewrite>
    <enable>1</enable>
    <autoLoadHtaccess>1</autoLoadHtaccess>
    <rules><![CDATA[
      RewriteRule ^/?$ /index.php [L]
      RewriteCond %{REQUEST_FILENAME} !-f
      RewriteCond %{REQUEST_FILENAME} !-d
      RewriteRule ^(.*)$ /index.php?$1 [L,QSA]
    ]]></rules>
  </rewrite>
  <phpIniOverride>
    <memory_limit>{$memoryLimit}</memory_limit>
    <max_execution_time>300</max_execution_time>
    <upload_max_filesize>64M</upload_max_filesize>
    <post_max_size>128M</post_max_size>
  </phpIniOverride>
  <extProcessor>
    <type>lsapi</type>
    <name>lsphp-{$domain}</name>
    <path>lsphp{$phpVersion}</path>
    <maxConns>35</maxConns>
    <env>PHP_LSAPI_CHILDREN=35</env>
    <env>LSAPI_MAX_IDLE=300</env>
    <initTimeout>60</initTimeout>
    <retryTimeout>0</retryTimeout>
    <persistentConn>1</persistentConn>
    <respBuffer>0</respBuffer>
    <autoStart>1</autoStart>
    <path>/usr/local/lsws/lsphp{$phpVersion}/bin/lsphp</path>
    <backlog>100</backlog>
    <instances>1</instances>
    <extMaxIdleTime>300</extMaxIdleTime>
    <priority>0</priority>
    <memSoftLimit>2047M</memSoftLimit>
    <memHardLimit>2047M</memHardLimit>
    <procSoftLimit>400</procSoftLimit>
    <procHardLimit>500</procHardLimit>
  </extProcessor>
  <context>
    <type>lsapi</type>
    <handler>lsphp-{$domain}</handler>
    <uri>/</uri>
    <allowList>1</allowList>
  </context>
  <expires>
    <enableExpires>1</enableExpires>
    <expiresByType>image/*=A2592000</expiresByType>
    <expiresByType>text/css=A2592000</expiresByType>
    <expiresByType>application/javascript=A2592000</expiresByType>
  </expires>
</virtualHostConfig>
LSWS;

    $vhostConf = $vhostDir . '/vhost.conf';
    file_put_contents($vhostConf, $lswsConfig);
    exec("chmod 644 {$vhostConf} 2>&1");
    exec(LSWS_BIN . ' restart 2>&1', $out, $ret);
    if ($ret !== 0) {
        $errors[] = "OpenLiteSpeed restart failed: " . implode("\n", $out);
    }

    // 3. Create placeholder page
    $placeholder = <<<HTML
<!DOCTYPE html>
<html>
<head><title>{$domain} - Coming Soon</title></head>
<body style="font-family:Arial;margin:0;display:flex;align-items:center;justify-content:center;height:100vh;background:#f0f4f8;">
<div style="text-align:center;">
<h1 style="color:#3b82f6;">{$domain}</h1>
<p style="color:#64748b;">Site is ready. Install WordPress from WP Hosting Panel.</p>
</div>
</body>
</html>
HTML;
    file_put_contents($docRoot . '/index.html', $placeholder);

    // 4. Create MySQL database
    exec("mysql -e \"CREATE DATABASE IF NOT EXISTS \`${dbName}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\" 2>&1", $out, $ret);
    if ($ret !== 0) {
        $errors[] = "Database creation failed: " . implode("\n", $out);
    }
    exec("mysql -e \"CREATE USER IF NOT EXISTS \`${dbUser}\`@'localhost' IDENTIFIED BY '${dbPass}';\" 2>&1", $out, $ret);
    exec("mysql -e \"GRANT ALL PRIVILEGES ON \`${dbName}\`.* TO \`${dbUser}\`@'localhost';\" 2>&1", $out, $ret);
    exec("mysql -e \"FLUSH PRIVILEGES;\" 2>&1");

    // 5. Save to database
    try {
        $db->prepare("INSERT INTO websites (id, user_id, domain, doc_root, php_version, db_name, db_user, db_password, ip, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$websiteId, $user['id'], $domain, $docRoot, $phpVersion, $dbName, $dbUser, $dbPassHash, $serverIp, 'active']);

        $db->prepare("INSERT INTO databases (id, website_id, db_name, db_user, db_host) VALUES (?, ?, ?, ?, ?)")
            ->execute([generateId('db'), $websiteId, $dbName, $dbUser, 'localhost']);

        logActivity($user['id'], 'Website Created', "Created website: $domain", 'success');

        jsonResponse([
            'success' => true,
            'website_id' => $websiteId,
            'domain' => $domain,
            'doc_root' => $docRoot,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password' => $dbPass, // Return only once
            'php_version' => $phpVersion,
            'lsws' => $ret === 0 ? 'configured' : 'warning',
            'database' => 'created',
            'errors' => $errors,
            'message' => count($errors) > 0
                ? "Website created with some warnings"
                : "Website created successfully",
        ]);
    } catch (Exception $e) {
        jsonResponse([
            'success' => false,
            'error' => 'Database save failed: ' . $e->getMessage(),
            'partial' => true,
            'domain' => $domain,
            'note' => 'Site may have been partially created on server. Check manually.',
        ], 500);
    }
}

function handleDelete(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');
    $confirmDomain = input('confirm_domain');

    if (!$websiteId) jsonResponse(['success' => false, 'error' => 'Website ID required'], 400);

    $db = getDB();

    if ($user['role'] !== 'admin') {
        $stmt = $db->prepare("SELECT * FROM websites WHERE id = ? AND user_id = ?");
        $stmt->execute([$websiteId, $user['id']]);
    } else {
        $stmt = $db->prepare("SELECT * FROM websites WHERE id = ?");
        $stmt->execute([$websiteId]);
    }
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    $domain = $website['domain'];
    $docRoot = $website['doc_root'];
    $dbName = $website['db_name'];
    $dbUser = $website['db_user'];

    // Remove OpenLiteSpeed vhost config
    $vhostDir = LSWS_VHOSTS_DIR . '/' . $domain;
    if (is_dir($vhostDir)) {
        exec("rm -rf {$vhostDir} 2>&1");
    }
    exec(LSWS_BIN . ' restart 2>&1', $out, $ret);

    // Remove SSL
    exec("certbot delete --cert-name {$domain} --non-interactive 2>&1", $out, $ret);

    // Remove directory
    $homeDir = dirname($docRoot);
    if (is_dir($homeDir) && strpos($homeDir, SITES_HOME) === 0) {
        exec("rm -rf {$homeDir} 2>&1");
    }

    // Remove database
    if ($dbName) {
        exec("mysql -e \"DROP DATABASE IF EXISTS \`${dbName}\`;\" 2>&1");
    }
    if ($dbUser) {
        exec("mysql -e \"DROP USER IF EXISTS \`${dbUser}\`@'localhost';\" 2>&1");
        exec("mysql -e \"FLUSH PRIVILEGES;\" 2>&1");
    }

    // Remove from panel DB
    $db->prepare("DELETE FROM websites WHERE id = ?")->execute([$websiteId]);

    logActivity($user['id'], 'Website Deleted', "Deleted website: $domain", 'warning');

    jsonResponse(['success' => true, 'message' => "Website $domain deleted successfully"]);
}

function handleToggle(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');
    $status = input('status'); // active or suspended

    if (!$websiteId || !in_array($status, ['active', 'suspended'])) {
        jsonResponse(['success' => false, 'error' => 'Invalid parameters'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT domain, doc_root FROM websites WHERE id = ?");
    $stmt->execute([$websiteId]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    if ($status === 'suspended') {
        // Show suspended page
        $suspended = "<html><body><h1>Website Suspended</h1></body></html>";
        file_put_contents($website['doc_root'] . '/index.html', $suspended);
    }

    $db->prepare("UPDATE websites SET status = ? WHERE id = ?")->execute([$status, $websiteId]);

    logActivity($user['id'], 'Website ' . ucfirst($status), "Website {$website['domain']} " . $status, $status === 'active' ? 'success' : 'warning');

    jsonResponse(['success' => true, 'message' => "Website {$website['domain']} is now $status"]);
}

function handleStats(): void {
    $user = authMiddleware();
    $db = getDB();

    $totalSites = $db->query("SELECT COUNT(*) FROM websites")->fetchColumn();
    $activeSites = $db->query("SELECT COUNT(*) FROM websites WHERE status = 'active'")->fetchColumn();
    $activeSSL = $db->query("SELECT COUNT(*) FROM ssl_certs WHERE status = 'active'")->fetchColumn();
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalDbs = $db->query("SELECT COUNT(*) FROM databases")->fetchColumn();

    // Server stats
    $cpuLoad = sys_getloadavg();
    $memInfo = file_exists('/proc/meminfo') ? file('/proc/meminfo') : [];
    $totalMem = 0;
    $freeMem = 0;
    foreach ($memInfo as $line) {
        if (strpos($line, 'MemTotal') === 0) $totalMem = (int) preg_replace('/\D/', '', $line);
        if (strpos($line, 'MemAvailable') === 0) $freeMem = (int) preg_replace('/\D/', '', $line);
    }
    $usedMemPercent = $totalMem > 0 ? round((($totalMem - $freeMem) / $totalMem) * 100, 1) : 0;

    // Disk usage
    $diskFree = (int) shell_exec("df -BG " . SITES_HOME . " 2>/dev/null | tail -1 | awk '{print $4}' | tr -d 'G'");
    $diskTotal = (int) shell_exec("df -BG " . SITES_HOME . " 2>/dev/null | tail -1 | awk '{print $2}' | tr -d 'G'");
    $diskUsed = $diskTotal - $diskFree;

    // Service status
    $services = ['lsws', 'lsphp8', 'mariadb', 'postfix'];
    $serviceStatus = [];
    foreach ($services as $svc) {
        exec("systemctl is-active {$svc} 2>&1", $out, $ret);
        $serviceStatus[$svc] = $ret === 0 ? 'active' : 'inactive';
    }

    jsonResponse([
        'success' => true,
        'stats' => [
            'total_websites' => (int) $totalSites,
            'active_websites' => (int) $activeSites,
            'active_ssl' => (int) $activeSSL,
            'total_users' => (int) $totalUsers,
            'total_databases' => (int) $totalDbs,
            'cpu_load' => $cpuLoad,
            'memory' => [
                'total_mb' => round($totalMem / 1024),
                'used_percent' => $usedMemPercent,
            ],
            'disk' => [
                'total_gb' => $diskTotal,
                'used_gb' => $diskUsed,
                'free_gb' => $diskFree,
            ],
            'services' => $serviceStatus,
        ],
    ]);
}
