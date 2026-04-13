<?php
/**
 * WP Hosting Panel - Authentication API
 * ======================================
 * POST /api/auth/login      - Login user
 * POST /api/auth/register   - Register user (admin only)
 * GET  /api/auth/me         - Get current user
 * PUT  /api/auth/password   - Change password
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        if ($method !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleLogin();
        break;
    case 'register':
        if ($method !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleRegister();
        break;
    case 'me':
        if ($method !== 'GET') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleMe();
        break;
    case 'password':
        if ($method !== 'PUT' && $method !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleChangePassword();
        break;
    case 'users':
        if ($method !== 'GET') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleListUsers();
        break;
    case 'users-update':
        if ($method !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleUpdateUser();
        break;
    case 'users-delete':
        if ($method !== 'POST') jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
        handleDeleteUser();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}

function handleLogin(): void {
    $email = input('email');
    $password = input('password');

    if (!$email || !$password) {
        jsonResponse(['success' => false, 'error' => 'Email and password required'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, password, role, status, max_websites, created_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['success' => false, 'error' => 'Invalid email or password'], 401);
    }

    if ($user['status'] !== 'active') {
        jsonResponse(['success' => false, 'error' => 'Account is ' . $user['status']], 403);
    }

    $token = jwtEncode([
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'role' => $user['role'],
    ]);

    // Log activity
    logActivity($user['id'], 'Login', 'User logged in successfully', 'success');

    // Get website count
    $stmt2 = $db->prepare("SELECT COUNT(*) as total FROM websites WHERE user_id = ?");
    $stmt2->execute([$user['id']]);
    $websiteCount = $stmt2->fetch()['total'];

    unset($user['password']);
    $user['websites_count'] = (int) $websiteCount;

    jsonResponse([
        'success' => true,
        'token' => $token,
        'user' => $user,
    ]);
}

function handleRegister(): void {
    $currentUser = authMiddleware();
    if ($currentUser['role'] !== 'admin') {
        jsonResponse(['success' => false, 'error' => 'Only admins can create users'], 403);
    }

    $name = input('name');
    $email = input('email');
    $password = input('password');
    $role = input('role', 'customer');
    $maxWebsites = (int) input('max_websites', 1);

    if (!$name || !$email || !$password) {
        jsonResponse(['success' => false, 'error' => 'Name, email, and password required'], 400);
    }

    if (!in_array($role, ['admin', 'customer', 'reseller'])) {
        jsonResponse(['success' => false, 'error' => 'Invalid role'], 400);
    }

    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Email already exists'], 409);
    }

    $hashedPass = password_hash($password, PASSWORD_BCRYPT);
    $userId = generateId('u');

    $db->prepare("INSERT INTO users (id, name, email, password, role, max_websites) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$userId, $name, $email, $hashedPass, $role, $maxWebsites]);

    logActivity($currentUser['id'], 'User Created', "Created user: $name ($role)", 'success');

    jsonResponse([
        'success' => true,
        'user_id' => $userId,
        'message' => 'User created successfully',
    ]);
}

function handleMe(): void {
    $user = authMiddleware();
    $db = getDB();

    $stmt = $db->prepare("SELECT id, name, email, role, status, max_websites, created_at FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch();

    if (!$userData) {
        jsonResponse(['success' => false, 'error' => 'User not found'], 404);
    }

    $stmt2 = $db->prepare("SELECT COUNT(*) as total FROM websites WHERE user_id = ?");
    $stmt2->execute([$user['id']]);
    $userData['websites_count'] = (int) $stmt2->fetch()['total'];

    jsonResponse(['success' => true, 'user' => $userData]);
}

function handleChangePassword(): void {
    $currentUser = authMiddleware();
    $currentPass = input('current_password');
    $newPass = input('new_password');

    if (!$currentPass || !$newPass) {
        jsonResponse(['success' => false, 'error' => 'Both passwords required'], 400);
    }

    if (strlen($newPass) < 8) {
        jsonResponse(['success' => false, 'error' => 'Password must be at least 8 characters'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$currentUser['id']]);
    $user = $stmt->fetch();

    if (!password_verify($currentPass, $user['password'])) {
        jsonResponse(['success' => false, 'error' => 'Current password is incorrect'], 401);
    }

    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
    $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $currentUser['id']]);

    logActivity($currentUser['id'], 'Password Changed', 'User changed their password', 'info');

    jsonResponse(['success' => true, 'message' => 'Password changed successfully']);
}

function handleListUsers(): void {
    $currentUser = authMiddleware();
    if ($currentUser['role'] !== 'admin') {
        jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
    }

    $db = getDB();
    $stmt = $db->query("
        SELECT u.id, u.name, u.email, u.role, u.status, u.max_websites, u.created_at,
               (SELECT COUNT(*) FROM websites WHERE user_id = u.id) as websites_count
        FROM users u ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();

    jsonResponse(['success' => true, 'users' => $users]);
}

function handleUpdateUser(): void {
    $currentUser = authMiddleware();
    if ($currentUser['role'] !== 'admin') {
        jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
    }

    $userId = input('user_id');
    $role = input('role');
    $status = input('status');
    $maxWebsites = input('max_websites');

    if (!$userId) {
        jsonResponse(['success' => false, 'error' => 'User ID required'], 400);
    }

    $db = getDB();
    if ($role && in_array($role, ['admin', 'customer', 'reseller'])) {
        $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $userId]);
    }
    if ($status && in_array($status, ['active', 'inactive', 'suspended'])) {
        $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$status, $userId]);
    }
    if ($maxWebsites !== null) {
        $db->prepare("UPDATE users SET max_websites = ? WHERE id = ?")->execute([(int) $maxWebsites, $userId]);
    }

    logActivity($currentUser['id'], 'User Updated', "Updated user: $userId", 'info');

    jsonResponse(['success' => true, 'message' => 'User updated']);
}

function handleDeleteUser(): void {
    $currentUser = authMiddleware();
    if ($currentUser['role'] !== 'admin') {
        jsonResponse(['success' => false, 'error' => 'Admin access required'], 403);
    }

    $userId = input('user_id');
    if (!$userId) {
        jsonResponse(['success' => false, 'error' => 'User ID required'], 400);
    }

    if ($userId === $currentUser['id']) {
        jsonResponse(['success' => false, 'error' => 'Cannot delete yourself'], 400);
    }

    $db = getDB();
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

    logActivity($currentUser['id'], 'User Deleted', "Deleted user: $userId", 'warning');

    jsonResponse(['success' => true, 'message' => 'User deleted']);
}

function logActivity(string $userId, string $action, string $desc, string $type): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO activity_log (id, user_id, action, description, type, ip_address) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([generateId('a'), $userId, $action, $desc, $type, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Silent fail - don't break main flow
    }
}
