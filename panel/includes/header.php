<?php
/**
 * Header — Top navbar with logo, user menu, and HTML head
 * Requires: $user and $token variables (set by auth.php)
 */

// Ensure auth was included
if (!isset($user) || !isset($token)) {
    header('Location: index.php');
    exit;
}

$pageTitle = $pageTitle ?? 'WP Hosting Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="token" content="<?php echo htmlspecialchars($token); ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌐</text></svg>">
</head>
<body data-page="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'], '.php')); ?>">

<!-- Mobile Sidebar Toggle -->
<button class="sidebar-toggle" id="sidebar-toggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Top Navbar -->
<nav class="topbar">
    <div class="topbar-left">
        <span class="page-title"><?php echo htmlspecialchars($pageTitle); ?></span>
    </div>
    <div class="topbar-right">
        <div class="topbar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                <span class="user-role"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
            </div>
        </div>
        <a href="logout.php" class="btn btn-sm btn-outline" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</nav>
