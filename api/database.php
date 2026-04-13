<?php
/**
 * WP Hosting Panel - Database API
 * =================================
 * POST /api/database?action=create   - Create MySQL database
 * POST /api/database?action=delete   - Delete database
 * GET  /api/database?action=list     - List databases
 * POST /api/database?action=password - Change DB password
 * GET  /api/database?action=size     - Get DB size
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleCreate();
        break;
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleDelete();
        break;
    case 'list':
        handleList();
        break;
    case 'password':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handlePassword();
        break;
    case 'size':
        handleSize();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}

function handleCreate(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');
    $dbName = input('db_name');
    $dbUser = input('db_user');
    $dbPass = input('db_password') ?: randomPassword(16);

    if (!$websiteId || !$dbName || !$dbUser) {
        jsonResponse(['success' => false, 'error' => 'website_id, db_name, db_user required'], 400);
    }

    // Sanitize
    $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
    $dbUser = preg_replace('/[^a-zA-Z0-9_]/', '', $dbUser);

    $db = getDB();
    $stmt = $db->prepare("SELECT id, user_id FROM websites WHERE id = ? AND (user_id = ? OR ? = 'admin')");
    $stmt->execute([$websiteId, $user['id'], $user['role']]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    // Check duplicate
    $stmt2 = $db->prepare("SELECT id FROM databases WHERE db_name = ?");
    $stmt2->execute([$dbName]);
    if ($stmt2->fetch()) jsonResponse(['success' => false, 'error' => 'Database name already exists'], 409);

    // Create database
    exec("mysql -e \"CREATE DATABASE \`${dbName}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\" 2>&1", $out, $ret);
    if ($ret !== 0) jsonResponse(['success' => false, 'error' => 'Database creation failed: ' . implode("\n", $out)], 500);

    // Create user
    exec("mysql -e \"CREATE USER \`${dbUser}\`@'localhost' IDENTIFIED BY '${dbPass}';\" 2>&1", $out, $ret);
    exec("mysql -e \"GRANT ALL PRIVILEGES ON \`${dbName}\`.* TO \`${dbUser}\`@'localhost';\" 2>&1", $out, $ret);
    exec("mysql -e \"FLUSH PRIVILEGES;\" 2>&1");

    // Save to panel DB
    $dbId = generateId('db');
    $db->prepare("INSERT INTO databases (id, website_id, db_name, db_user, db_host) VALUES (?, ?, ?, ?, ?)")
        ->execute([$dbId, $websiteId, $dbName, $dbUser, 'localhost']);

    logActivity($user['id'], 'Database Created', "Created database: {$dbName}", 'success');

    jsonResponse([
        'success' => true,
        'database' => [
            'id' => $dbId,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_password' => $dbPass,
            'db_host' => 'localhost',
        ],
        'message' => "Database {$dbName} created successfully",
    ]);
}

function handleDelete(): void {
    $user = authMiddleware();
    $dbId = input('database_id');
    $confirm = input('confirm');

    if (!$dbId || $confirm !== 'DELETE') {
        jsonResponse(['success' => false, 'error' => 'database_id and confirm=DELETE required'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM databases WHERE id = ?");
    $stmt->execute([$dbId]);
    $database = $stmt->fetch();
    if (!$database) jsonResponse(['success' => false, 'error' => 'Database not found'], 404);

    // Drop tables and database
    exec("mysql -e \"DROP DATABASE IF EXISTS \`${database['db_name']}\`;\" 2>&1");
    exec("mysql -e \"DROP USER IF EXISTS \`${database['db_user']}\`@'localhost';\" 2>&1");
    exec("mysql -e \"FLUSH PRIVILEGES;\" 2>&1");

    $db->prepare("DELETE FROM databases WHERE id = ?")->execute([$dbId]);

    logActivity($user['id'], 'Database Deleted', "Deleted database: {$database['db_name']}", 'warning');

    jsonResponse(['success' => true, 'message' => "Database {$database['db_name']} deleted"]);
}

function handleList(): void {
    $user = authMiddleware();
    $websiteId = $_GET['website_id'] ?? '';

    $db = getDB();
    if ($websiteId) {
        $stmt = $db->prepare("SELECT id, website_id, db_name, db_user, db_host, size_mb, created_at FROM databases WHERE website_id = ?");
        $stmt->execute([$websiteId]);
    } elseif ($user['role'] === 'admin') {
        $stmt = $db->query("SELECT d.*, w.domain FROM databases d LEFT JOIN websites w ON d.website_id = w.id ORDER BY d.created_at DESC");
    } else {
        $stmt = $db->prepare("SELECT d.*, w.domain FROM databases d LEFT JOIN websites w ON d.website_id = w.id WHERE w.user_id = ? ORDER BY d.created_at DESC");
        $stmt->execute([$user['id']]);
    }

    $databases = $stmt->fetchAll();

    // Get real sizes
    foreach ($databases as &$dbItem) {
        exec("mysql -N -e \"SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = '{$dbItem['db_name']}';\" 2>&1", $out, $ret);
        $dbItem['size_mb'] = $ret === 0 && $out[0] ? (float) $out[0] : 0;
    }

    jsonResponse(['success' => true, 'databases' => $databases]);
}

function handlePassword(): void {
    $user = authMiddleware();
    $dbId = input('database_id');
    $newPass = input('new_password') ?: randomPassword(16);

    if (!$dbId) jsonResponse(['success' => false, 'error' => 'database_id required'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM databases WHERE id = ?");
    $stmt->execute([$dbId]);
    $database = $stmt->fetch();
    if (!$database) jsonResponse(['success' => false, 'error' => 'Database not found'], 404);

    exec("mysql -e \"ALTER USER \`${database['db_user']}\`@'localhost' IDENTIFIED BY '${newPass}';\" 2>&1", $out, $ret);
    exec("mysql -e \"FLUSH PRIVILEGES;\" 2>&1");

    if ($ret !== 0) {
        jsonResponse(['success' => false, 'error' => 'Password change failed'], 500);
    }

    logActivity($user['id'], 'DB Password Changed', "Password changed for {$database['db_name']}", 'info');

    jsonResponse([
        'success' => true,
        'message' => 'Database password changed',
        'new_password' => $newPass,
    ]);
}

function handleSize(): void {
    $user = authMiddleware();
    $dbName = $_GET['db_name'] ?? '';

    if (!$dbName) jsonResponse(['success' => false, 'error' => 'db_name required'], 400);

    exec("mysql -N -e \"
        SELECT
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb,
            COUNT(*) as tables
        FROM information_schema.tables
        WHERE table_schema = '{$dbName}';
    \" 2>&1", $out, $ret);

    if ($ret === 0 && $out[0]) {
        $parts = preg_split('/\s+/', trim($out[0]));
        jsonResponse([
            'success' => true,
            'db_name' => $dbName,
            'size_mb' => (float) ($parts[0] ?? 0),
            'tables' => (int) ($parts[1] ?? 0),
        ]);
    }

    jsonResponse(['success' => false, 'error' => 'Could not get database size'], 500);
}

function logActivity(string $userId, string $action, string $desc, string $type): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO activity_log (id, user_id, action, description, type, ip_address) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([generateId('a'), $userId, $action, $desc, $type, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
}
