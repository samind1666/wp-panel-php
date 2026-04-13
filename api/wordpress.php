<?php
/**
 * WP Hosting Panel - WordPress Install API
 * ==========================================
 * POST /api/wordpress?action=install   - Install WordPress
 * POST /api/wordpress?action=uninstall - Remove WordPress
 * GET  /api/wordpress?action=check     - Check WP version
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'install':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleInstall();
        break;
    case 'uninstall':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleUninstall();
        break;
    case 'check':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(['success' => false, 'error' => 'GET required'], 405);
        handleCheck();
        break;
    case 'plugins':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handlePlugins();
        break;
    case 'theme':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleTheme();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}

function handleInstall(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');
    $siteTitle = input('site_title', 'My WordPress Site');
    $adminUser = input('admin_user', 'admin');
    $adminEmail = input('admin_email', 'admin@example.com');
    $adminPass = input('admin_password', '');
    $theme = input('theme', WP_DEFAULT_THEME);
    $plugins = input('plugins', []); // Array of plugin slugs

    if (!$websiteId) jsonResponse(['success' => false, 'error' => 'Website ID required'], 400);
    if (!$adminPass) $adminPass = randomPassword(16);

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM websites WHERE id = ? AND (user_id = ? OR ? = 'admin')");
    $stmt->execute([$websiteId, $user['id'], $user['role']]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    if ($website['wp_version']) {
        jsonResponse(['success' => false, 'error' => 'WordPress already installed (v' . $website['wp_version'] . ')'], 400);
    }

    $docRoot = $website['doc_root'];
    $domain = $website['domain'];
    $dbName = $website['db_name'];
    $dbUser = $website['db_user'];
    $dbPass = base64_decode($website['db_password']);
    $phpVersion = $website['php_version'];

    $log = [];
    $errors = [];

    // Check WP-CLI
    if (!file_exists(WP_CLI_PATH)) {
        jsonResponse(['success' => false, 'error' => 'WP-CLI not installed. Run: curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp && chmod +x /usr/local/bin/wp'], 500);
    }

    // Step 1: Download WordPress
    $log[] = ['step' => 'Downloading WordPress...', 'status' => 'running'];
    exec(WP_CLI_PATH . " core download --path={$docRoot} --version=latest --allow-root 2>&1", $out, $ret);
    $log[] = ['step' => 'WordPress downloaded', 'status' => $ret === 0 ? 'done' : 'error', 'output' => implode("\n", $out)];
    if ($ret !== 0) $errors[] = 'Download failed';

    // Step 2: Create wp-config.php
    $log[] = ['step' => 'Creating wp-config.php...', 'status' => 'running'];
    $saltUrl = 'https://api.wordpress.org/secret-key/1.1/salt/';
    $salt = @file_get_contents($saltUrl) ?: '';
    $configCmd = WP_CLI_PATH . " config create --path={$docRoot} --dbname={$dbName} --dbuser={$dbUser} --dbpass={$dbPass} --dbhost=localhost --allow-root 2>&1";
    exec($configCmd, $out, $ret);
    $log[] = ['step' => 'wp-config.php created', 'status' => $ret === 0 ? 'done' : 'error'];
    if ($ret !== 0) $errors[] = 'Config creation failed';

    // Step 3: Install WordPress
    $log[] = ['step' => 'Installing WordPress...', 'status' => 'running'];
    $installCmd = WP_CLI_PATH . " core install --path={$docRoot} --url=https://{$domain} --title=\"{$siteTitle}\" --admin_user={$adminUser} --admin_password=\"{$adminPass}\" --admin_email={$adminEmail} --allow-root 2>&1";
    exec($installCmd, $out, $ret);
    $log[] = ['step' => 'WordPress installed', 'status' => $ret === 0 ? 'done' : 'error', 'output' => implode("\n", $out)];
    if ($ret !== 0) $errors[] = 'WordPress install failed';

    // Step 4: Install theme
    if ($ret === 0 && $theme) {
        $log[] = ['step' => "Installing theme: {$theme}...", 'status' => 'running'];
        exec(WP_CLI_PATH . " theme install {$theme} --activate --path={$docRoot} --allow-root 2>&1", $out, $ret);
        $log[] = ['step' => "Theme {$theme} installed", 'status' => 'done'];
    }

    // Step 5: Install plugins
    if ($ret === 0 && is_array($plugins) && count($plugins) > 0) {
        foreach ($plugins as $plugin) {
            $log[] = ['step' => "Installing plugin: {$plugin}...", 'status' => 'running'];
            exec(WP_CLI_PATH . " plugin install {$plugin} --activate --path={$docRoot} --allow-root 2>&1", $out, $ret);
            $log[] = ['step' => "Plugin {$plugin} installed", 'status' => $ret === 0 ? 'done' : 'error'];
        }
    }

    // Step 6: Set permissions
    exec("chown -R www-data:www-data {$docRoot} 2>&1");
    exec("find {$docRoot} -type d -exec chmod 755 {} \; 2>&1");
    exec("find {$docRoot} -type f -exec chmod 644 {} \; 2>&1");

    // Step 7: Get installed version
    $wpVersion = '';
    exec(WP_CLI_PATH . " core version --path={$docRoot} --allow-root 2>&1", $out, $ret);
    if ($ret === 0) $wpVersion = trim($out[0]);

    // Update database
    if ($wpVersion) {
        $db->prepare("UPDATE websites SET wp_version = ? WHERE id = ?")->execute([$wpVersion, $websiteId]);
    }

    // Add cron for WordPress
    $cronCmd = "cd {$docRoot} && " . WP_CLI_PATH . " cron event run --due-now --allow-root";
    $cronExpr = "*/15 * * * *";
    $db->prepare("INSERT INTO cron_jobs (id, website_id, user_id, command, schedule, cron_expression, description, enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([generateId('cr'), $websiteId, $user['id'], $cronCmd, 'Every 15 minutes', $cronExpr, 'WordPress cron scheduler', 1]);

    logActivity($user['id'], 'WordPress Installed', "WordPress {$wpVersion} installed on {$domain}", 'success');

    jsonResponse([
        'success' => count($errors) === 0,
        'wp_version' => $wpVersion,
        'admin_url' => "https://{$domain}/wp-admin",
        'admin_user' => $adminUser,
        'admin_password' => $adminPass,
        'site_url' => "https://{$domain}",
        'log' => $log,
        'errors' => $errors,
        'message' => count($errors) === 0
            ? "WordPress {$wpVersion} installed successfully!"
            : "WordPress installed with some errors",
    ]);
}

function handleUninstall(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');
    $confirm = input('confirm');

    if (!$websiteId || $confirm !== 'DELETE') {
        jsonResponse(['success' => false, 'error' => 'Confirmation required (confirm=DELETE)'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM websites WHERE id = ? AND (user_id = ? OR ? = 'admin')");
    $stmt->execute([$websiteId, $user['id'], $user['role']]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    $docRoot = $website['doc_root'];

    // Remove WordPress files (keep directory)
    exec("rm -rf {$docRoot}/* {$docRoot}/.* 2>&1", $out, $ret);
    exec("mkdir -p {$docRoot}");

    // Drop WordPress tables
    $dbName = $website['db_name'];
    exec("mysql -N -e \"SHOW TABLES FROM \`${dbName}\` LIKE 'wp_%';\" 2>&1", $tables, $ret);
    if ($ret === 0) {
        foreach ($tables as $table) {
            $table = trim($table);
            if ($table) exec("mysql -e \"DROP TABLE \`${dbName}\`.`${table}\";\" 2>&1");
        }
    }

    // Remove WP cron
    $db->prepare("DELETE FROM cron_jobs WHERE website_id = ? AND description LIKE '%WordPress%'")->execute([$websiteId]);

    $db->prepare("UPDATE websites SET wp_version = NULL WHERE id = ?")->execute([$websiteId]);

    logActivity($user['id'], 'WordPress Removed', "WordPress removed from {$website['domain']}", 'warning');

    jsonResponse(['success' => true, 'message' => 'WordPress removed. Directory cleared.']);
}

function handleCheck(): void {
    $user = authMiddleware();
    $websiteId = $_GET['website_id'] ?? '';

    if (!$websiteId) jsonResponse(['success' => false, 'error' => 'Website ID required'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM websites WHERE id = ? AND (user_id = ? OR ? = 'admin')");
    $stmt->execute([$websiteId, $user['id'], $user['role']]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    $docRoot = $website['doc_root'];

    if (!file_exists($docRoot . '/wp-config.php')) {
        jsonResponse(['success' => true, 'installed' => false, 'message' => 'WordPress not installed']);
    }

    $wpVersion = '';
    exec(WP_CLI_PATH . " core version --path={$docRoot} --allow-root 2>&1", $out, $ret);
    if ($ret === 0) $wpVersion = trim($out[0]);

    // Check updates
    $updateCheck = '';
    exec(WP_CLI_PATH . " core check-update --path={$docRoot} --format=csv --allow-root 2>&1", $out, $ret);
    $hasUpdate = $ret === 0 && count($out) > 0;

    // Plugin list
    exec(WP_CLI_PATH . " plugin list --format=json --path={$docRoot} --allow-root 2>&1", $out, $ret);
    $pluginList = $ret === 0 ? json_decode(implode('', $out), true) : [];

    // Theme info
    exec(WP_CLI_PATH . " theme list --format=json --path={$docRoot} --status=active --allow-root 2>&1", $out, $ret);
    $themeInfo = $ret === 0 ? json_decode(implode('', $out), true) : [];

    jsonResponse([
        'success' => true,
        'installed' => true,
        'wp_version' => $wpVersion,
        'has_update' => $hasUpdate,
        'plugins' => $pluginList,
        'active_theme' => $themeInfo[0] ?? null,
    ]);
}

function handlePlugins(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');
    $pluginAction = input('plugin_action'); // install, activate, deactivate, remove
    $pluginSlug = input('plugin_slug');

    if (!$websiteId || !$pluginAction || !$pluginSlug) {
        jsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT doc_root FROM websites WHERE id = ? AND (user_id = ? OR ? = 'admin')");
    $stmt->execute([$websiteId, $user['id'], $user['role']]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    $cmd = WP_CLI_PATH . " plugin {$pluginAction} {$pluginSlug} --path={$website['doc_root']} --allow-root 2>&1";
    exec($cmd, $out, $ret);

    jsonResponse([
        'success' => $ret === 0,
        'output' => implode("\n", $out),
        'message' => $ret === 0 ? "Plugin {$pluginAction} successful" : "Plugin {$plugin_action} failed",
    ]);
}

function handleTheme(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');
    $themeAction = input('theme_action'); // install, activate
    $themeSlug = input('theme_slug');

    if (!$websiteId || !$themeAction || !$themeSlug) {
        jsonResponse(['success' => false, 'error' => 'Missing parameters'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT doc_root FROM websites WHERE id = ? AND (user_id = ? OR ? = 'admin')");
    $stmt->execute([$websiteId, $user['id'], $user['role']]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    $cmd = WP_CLI_PATH . " theme {$themeAction} {$themeSlug}" . ($themeAction === 'install' ? ' --activate' : '') . " --path={$website['doc_root']} --allow-root 2>&1";
    exec($cmd, $out, $ret);

    jsonResponse([
        'success' => $ret === 0,
        'output' => implode("\n", $out),
    ]);
}

function logActivity(string $userId, string $action, string $desc, string $type): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO activity_log (id, user_id, action, description, type, ip_address) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([generateId('a'), $userId, $action, $desc, $type, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
}
