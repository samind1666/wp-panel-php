<?php
/**
 * Authentication Check
 * Starts session, validates JWT token, redirects if not authenticated
 */

session_start();

// Redirect to login if no token exists
if (!isset($_SESSION['token']) && empty($_SESSION['token'])) {
    header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/index.php');
    exit;
}

$token = $_SESSION['token'];
$user = null;

// Decode JWT payload (simple base64 decode, no signature verification for session tokens)
function decodeJWT($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }

    $payload = $parts[1];
    // Replace URL-safe characters
    $payload = str_replace(['-', '_'], ['+', '/'], $payload);
    // Pad with = if needed
    $padding = strlen($payload) % 4;
    if ($padding) {
        $payload .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($payload, true);
    if ($decoded === false) {
        return null;
    }

    $data = json_decode($decoded, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $data;
}

// Validate token and extract user data
$jwtData = decodeJWT($token);

if ($jwtData === null) {
    // Invalid token — destroy session and redirect
    session_destroy();
    header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/index.php');
    exit;
}

// Check token expiry
if (isset($jwtData['exp']) && $jwtData['exp'] < time()) {
    // Token expired
    session_destroy();
    header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/index.php?expired=1');
    exit;
}

// Store user info in session for easy access
$user = [
    'id'     => $jwtData['id'] ?? $jwtData['sub'] ?? null,
    'name'   => $jwtData['name'] ?? $_SESSION['user_name'] ?? 'User',
    'email'  => $jwtData['email'] ?? $_SESSION['user_email'] ?? '',
    'role'   => $jwtData['role'] ?? $_SESSION['user_role'] ?? 'customer',
];

// Update session if we have new data
if (isset($jwtData['name'])) $_SESSION['user_name'] = $jwtData['name'];
if (isset($jwtData['email'])) $_SESSION['user_email'] = $jwtData['email'];
if (isset($jwtData['role'])) $_SESSION['user_role'] = $jwtData['role'];
