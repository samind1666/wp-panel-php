<?php
/**
 * Database — Database management page
 */
$pageTitle = 'Database Management';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Content Area -->
<div class="content">

    <!-- Page Header -->
    <div class="content-header">
        <h1><i class="fas fa-database"></i> Database Management</h1>
        <div class="header-actions">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="table-search" placeholder="Search databases...">
            </div>
            <button class="btn btn-primary" onclick="openModal('modal-create-database')">
                <i class="fas fa-plus"></i> Create Database
            </button>
        </div>
    </div>

    <!-- Empty State -->
    <div id="database-empty" class="empty-state hidden">
        <i class="fas fa-database"></i>
        <h3>No databases yet</h3>
        <p>Create a new MySQL database to store your application data. Each database will have its own user with full privileges.</p>
        <button class="btn btn-primary" onclick="openModal('modal-create-database')">
            <i class="fas fa-plus"></i> Create Your First Database
        </button>
    </div>

    <!-- Databases Table -->
    <div id="database-table-container" class="card">
        <div class="table-container" style="border: none; border-radius: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Database Name</th>
                        <th>User</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="database-table-body">
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 40px;">
                            <div class="loading-overlay" style="padding: 10px;">
                                <div class="spinner"></div>
                                <span>Loading databases...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Info Card -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h2><i class="fas fa-info-circle"></i> Database Information</h2>
        </div>
        <div class="card-body">
            <div class="grid-3">
                <div class="d-flex align-center gap-3">
                    <div class="stat-icon green" style="width: 40px; height: 40px; font-size: 16px;">
                        <i class="fas fa-server"></i>
                    </div>
                    <div>
                        <div class="text-sm text-muted">Database Host</div>
                        <div class="fw-semibold">localhost</div>
                    </div>
                </div>
                <div class="d-flex align-center gap-3">
                    <div class="stat-icon blue" style="width: 40px; height: 40px; font-size: 16px;">
                        <i class="fas fa-plug"></i>
                    </div>
                    <div>
                        <div class="text-sm text-muted">Port</div>
                        <div class="fw-semibold">3306</div>
                    </div>
                </div>
                <div class="d-flex align-center gap-3">
                    <div class="stat-icon yellow" style="width: 40px; height: 40px; font-size: 16px;">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div>
                        <div class="text-sm text-muted">phpMyAdmin</div>
                        <div class="fw-semibold"><a href="/phpmyadmin" target="_blank" class="text-primary">Open phpMyAdmin</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Create Database Modal -->
<div class="modal-overlay" id="modal-create-database">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-database"></i> Create Database</h3>
            <button class="modal-close" onclick="closeModal('modal-create-database')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="create-database-form">
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    A new MySQL database and user will be created with the specified credentials. The user will have full privileges on the database.
                </div>

                <div class="form-group">
                    <label for="db-name">Database Name</label>
                    <input type="text" id="db-name" name="db_name" class="form-control" placeholder="my_database" required pattern="[a-zA-Z0-9_]+">
                    <p class="form-hint">Use only letters, numbers, and underscores. No spaces allowed.</p>
                </div>

                <div class="form-group">
                    <label for="db-user">Username</label>
                    <input type="text" id="db-user" name="db_user" class="form-control" placeholder="db_user" required pattern="[a-zA-Z0-9_]+">
                </div>

                <div class="form-group">
                    <label for="db-password">Password</label>
                    <div style="position: relative;">
                        <input type="password" id="db-password" name="db_password" class="form-control" placeholder="Enter a strong password" required minlength="8">
                        <button type="button" class="btn btn-sm btn-outline" style="position: absolute; right: 4px; top: 4px;"
                            onclick="togglePasswordVisibility('db-password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p class="form-hint">Minimum 8 characters. Use a mix of letters, numbers, and symbols.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-database')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Database
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
