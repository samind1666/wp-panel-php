<?php
/**
 * Sidebar — Navigation menu with icons and active states
 * Requires: $user variable (set by auth.php)
 */

// Determine current page for active state
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isAdmin = (isset($user['role']) && $user['role'] === 'admin');
?>

<aside class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <i class="fab fa-wordpress"></i>
        <span>WP Hosting Panel</span>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>

        <a href="websites.php" class="nav-item <?php echo $currentPage === 'websites' ? 'active' : ''; ?>">
            <i class="fas fa-globe"></i>
            <span>Websites</span>
        </a>

        <a href="file-manager.php" class="nav-item <?php echo $currentPage === 'file-manager' ? 'active' : ''; ?>">
            <i class="fas fa-folder-open"></i>
            <span>File Manager</span>
        </a>

        <a href="database.php" class="nav-item <?php echo $currentPage === 'database' ? 'active' : ''; ?>">
            <i class="fas fa-database"></i>
            <span>Database</span>
        </a>

        <a href="ssl.php" class="nav-item <?php echo $currentPage === 'ssl' ? 'active' : ''; ?>">
            <i class="fas fa-lock"></i>
            <span>SSL Certificates</span>
        </a>

        <a href="php-settings.php" class="nav-item <?php echo $currentPage === 'php-settings' ? 'active' : ''; ?>">
            <i class="fas fa-code"></i>
            <span>PHP Settings</span>
        </a>

        <a href="cron-jobs.php" class="nav-item <?php echo $currentPage === 'cron-jobs' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i>
            <span>Cron Jobs</span>
        </a>

        <?php if ($isAdmin): ?>
            <div class="nav-separator"></div>
            <a href="users.php" class="nav-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        <?php endif; ?>

        <div class="nav-separator"></div>

        <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>
