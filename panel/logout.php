<?php
/**
 * Logout — Destroy session and redirect to login
 */
session_start();

// Clear all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Clear localStorage redirect (client-side JS will handle this)
// Redirect to login page
header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/index.php');
exit;
