<?php
/**
 * Websites — Website management page
 */
$pageTitle = 'Website Management';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Content Area -->
<div class="content">

    <!-- Page Header -->
    <div class="content-header">
        <h1><i class="fas fa-globe"></i> Website Management</h1>
        <div class="header-actions">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="table-search" placeholder="Search websites...">
            </div>
            <button class="btn btn-primary" onclick="openModal('modal-create-website')">
                <i class="fas fa-plus"></i> Create Website
            </button>
        </div>
    </div>

    <!-- Empty State (shown when no websites) -->
    <div id="websites-empty" class="empty-state hidden">
        <i class="fas fa-globe"></i>
        <h3>No websites yet</h3>
        <p>Get started by creating your first website. You can add a domain, install WordPress, and configure SSL.</p>
        <button class="btn btn-primary" onclick="openModal('modal-create-website')">
            <i class="fas fa-plus"></i> Create Your First Website
        </button>
    </div>

    <!-- Websites Table -->
    <div id="websites-table-container" class="card">
        <div class="table-container" style="border: none; border-radius: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>PHP Version</th>
                        <th>WordPress</th>
                        <th>SSL Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="websites-table-body">
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 40px;">
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

</div>

<!-- Create Website Modal -->
<div class="modal-overlay" id="modal-create-website">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-globe"></i> Create Website</h3>
            <button class="modal-close" onclick="closeModal('modal-create-website')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="create-website-form">
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Enter the domain name for your new website. A virtual host and document root will be created automatically.
                </div>

                <div class="form-group">
                    <label for="website-domain">Domain Name</label>
                    <input type="text" id="website-domain" name="domain" class="form-control" placeholder="example.com" required>
                    <p class="form-hint">Enter the full domain name without http:// or www.</p>
                </div>

                <div class="form-group">
                    <label for="website-php-version">PHP Version</label>
                    <select id="website-php-version" name="php_version" class="form-control" required>
                        <option value="8.3">PHP 8.3 (Latest)</option>
                        <option value="8.2" selected>PHP 8.2 (Recommended)</option>
                        <option value="8.1">PHP 8.1</option>
                        <option value="8.0">PHP 8.0</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-website')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Website
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
