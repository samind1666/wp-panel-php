<?php
/**
 * Dashboard — Overview page with stats, recent websites, system info
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Content Area -->
<div class="content">

    <!-- Page Header -->
    <div class="content-header">
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
        <div class="header-actions">
            <span class="text-muted text-sm">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</span>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="stats-grid">
        <!-- Total Websites -->
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-globe"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number" id="stat-websites">0</div>
                <div class="stat-label">Total Websites</div>
            </div>
        </div>

        <!-- Databases -->
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-database"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number" id="stat-databases">0</div>
                <div class="stat-label">Databases</div>
            </div>
        </div>

        <!-- SSL Certificates -->
        <div class="stat-card">
            <div class="stat-icon yellow">
                <i class="fas fa-lock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number" id="stat-ssl">0</div>
                <div class="stat-label">SSL Certificates</div>
            </div>
        </div>

        <!-- Disk Usage -->
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-hdd"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number" id="stat-disk">—</div>
                <div class="stat-label">Disk Usage</div>
            </div>
        </div>
    </div>

    <!-- Two Column Row: Recent Websites + System Info -->
    <div class="grid-2">
        <!-- Recent Websites -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-globe"></i> Recent Websites</h2>
                <a href="websites.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="table-container" style="border: none;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Status</th>
                            <th>WordPress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="websites-table-body">
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 40px;">
                                <div class="loading-overlay" style="padding: 10px;">
                                    <div class="spinner"></div>
                                    <span>Loading websites...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Information -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-server"></i> System Information</h2>
                <span class="badge badge-green"><span class="badge-dot green"></span> Online</span>
            </div>
            <ul class="info-list" id="system-info-list">
                <li>
                    <span class="info-label">Server IP</span>
                    <span class="info-value">Loading...</span>
                </li>
                <li>
                    <span class="info-label">PHP Version</span>
                    <span class="info-value">Loading...</span>
                </li>
                <li>
                    <span class="info-label">MySQL Version</span>
                    <span class="info-value">Loading...</span>
                </li>
                <li>
                    <span class="info-label">LiteSpeed Version</span>
                    <span class="info-value">Loading...</span>
                </li>
                <li>
                    <span class="info-label">Disk Space</span>
                    <span class="info-value">Loading...</span>
                </li>
                <li>
                    <span class="info-label">Uptime</span>
                    <span class="info-value">Loading...</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <button class="btn btn-primary" onclick="document.querySelector('[href*=websites]')?.click() || (window.location.href='websites.php')">
                    <i class="fas fa-plus"></i> Create Website
                </button>
                <button class="btn btn-success" onclick="window.location.href='websites.php'">
                    <i class="fab fa-wordpress"></i> Install WordPress
                </button>
                <button class="btn btn-warning" onclick="window.location.href='ssl.php'">
                    <i class="fas fa-lock"></i> Install SSL
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='database.php'">
                    <i class="fas fa-database"></i> Create Database
                </button>
                <button class="btn btn-outline" onclick="window.location.href='file-manager.php'">
                    <i class="fas fa-folder-open"></i> File Manager
                </button>
                <button class="btn btn-outline" onclick="window.location.href='php-settings.php'">
                    <i class="fas fa-code"></i> PHP Settings
                </button>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
