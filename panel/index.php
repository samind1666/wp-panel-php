<?php
/**
 * Login Page — Standalone login/register page
 * No sidebar, no header — centered card on dark background
 */
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['token']) && !empty($_SESSION['token'])) {
    header('Location: dashboard.php');
    exit;
}

// Check for expired session message
$expired = isset($_GET['expired']) && $_GET['expired'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — WP Hosting Panel</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌐</text></svg>">
</head>
<body data-page="login" style="background: #0f1117;">

<div class="login-page">
    <div class="login-card">
        <!-- Logo -->
        <div class="login-logo">
            <i class="fab fa-wordpress"></i>
            <h1>WP Hosting Panel</h1>
            <p>Manage your WordPress websites with ease</p>
        </div>

        <!-- Expired Session Alert -->
        <?php if ($expired): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Your session has expired. Please log in again.
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form id="login-form" class="login-form">
            <div class="form-group">
                <label for="login-email">Email Address</label>
                <input type="email" id="login-email" name="email" class="form-control" placeholder="Enter your email" required autocomplete="email" autofocus>
            </div>

            <div class="form-group">
                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <!-- Divider -->
        <div class="login-divider">or</div>

        <!-- Register Link -->
        <div class="login-footer">
            <p>Don't have an account? <a id="toggle-register">Register now</a></p>
        </div>

        <!-- Register Form (Hidden by default) -->
        <form id="register-form" class="login-form hidden" style="margin-top: 20px;">
            <h3 style="margin-bottom: 20px; font-size: 16px; font-weight: 600;">Create Account</h3>

            <div class="form-group">
                <label for="reg-name">Full Name</label>
                <input type="text" id="reg-name" name="name" class="form-control" placeholder="Enter your name" required>
            </div>

            <div class="form-group">
                <label for="reg-email">Email Address</label>
                <input type="email" id="reg-email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label for="reg-password">Password</label>
                <input type="password" id="reg-password" name="password" class="form-control" placeholder="Create a password" required minlength="6">
            </div>

            <div class="form-group">
                <label for="reg-confirm-password">Confirm Password</label>
                <input type="password" id="reg-confirm-password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
            </div>

            <div class="form-group">
                <label for="reg-role">Account Type</label>
                <select id="reg-role" name="role" class="form-control" required>
                    <option value="customer">Customer</option>
                    <option value="reseller">Reseller</option>
                </select>
            </div>

            <button type="submit" class="btn btn-success btn-block btn-lg">
                <i class="fas fa-user-plus"></i> Register
            </button>

            <div class="login-footer">
                <p>Already have an account? <a id="toggle-login">Sign in</a></p>
            </div>
        </form>
    </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>
