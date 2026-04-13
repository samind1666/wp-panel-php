<?php
/**
 * Users — User management page (Admin only)
 */
$pageTitle = 'User Management';
require_once __DIR__ . '/includes/auth.php';

// Admin check — redirect non-admin users
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Content Area -->
<div class="content">

    <!-- Page Header -->
    <div class="content-header">
        <h1><i class="fas fa-users"></i> User Management</h1>
        <div class="header-actions">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="table-search" placeholder="Search users...">
            </div>
            <button class="btn btn-primary" onclick="openModal('modal-add-user')">
                <i class="fas fa-user-plus"></i> Add User
            </button>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-warning">
        <i class="fas fa-shield-alt"></i>
        <div>
            <strong>Admin Area</strong> — You have full access to manage all users. Deleting a user will remove their websites, databases, and all associated data. This action cannot be undone.
        </div>
    </div>

    <!-- User Stats -->
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number" id="stat-admins">—</div>
                <div class="stat-label">Admins</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number" id="stat-resellers">—</div>
                <div class="stat-label">Resellers</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-user"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number" id="stat-customers">—</div>
                <div class="stat-label">Customers</div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="table-container" style="border: none; border-radius: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 40px;">
                            <div class="loading-overlay" style="padding: 10px;">
                                <div class="spinner"></div>
                                <span>Loading users...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Role Legend -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h2><i class="fas fa-info-circle"></i> User Roles</h2>
        </div>
        <div class="card-body">
            <div class="grid-3">
                <div>
                    <div class="d-flex align-center gap-2 mb-2">
                        <span class="badge badge-red">Admin</span>
                    </div>
                    <p class="text-sm text-muted">
                        Full access to all features. Can manage users, websites, databases, and system settings. Only admins can access the User Management page.
                    </p>
                </div>
                <div>
                    <div class="d-flex align-center gap-2 mb-2">
                        <span class="badge badge-blue">Reseller</span>
                    </div>
                    <p class="text-sm text-muted">
                        Can manage their own websites and databases, and create sub-accounts. Suitable for web agencies or resellers who manage multiple client websites.
                    </p>
                </div>
                <div>
                    <div class="d-flex align-center gap-2 mb-2">
                        <span class="badge badge-green">Customer</span>
                    </div>
                    <p class="text-sm text-muted">
                        Standard user access. Can manage their own websites, files, databases, and settings. Cannot manage other users or access admin features.
                    </p>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="modal-add-user">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add User</h3>
            <button class="modal-close" onclick="closeModal('modal-add-user')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="add-user-form">
            <div class="modal-body">
                <div class="form-group">
                    <label for="add-user-name">Full Name</label>
                    <input type="text" id="add-user-name" name="name" class="form-control" placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label for="add-user-email">Email Address</label>
                    <input type="email" id="add-user-email" name="email" class="form-control" placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label for="add-user-password">Password</label>
                    <div style="position: relative;">
                        <input type="password" id="add-user-password" name="password" class="form-control"
                            placeholder="Minimum 8 characters" required minlength="8">
                        <button type="button" class="btn btn-sm btn-outline" style="position: absolute; right: 4px; top: 4px;"
                            onclick="togglePasswordVisibility('add-user-password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="add-user-role">Role</label>
                    <select id="add-user-role" name="role" class="form-control" required>
                        <option value="customer">Customer</option>
                        <option value="reseller">Reseller</option>
                        <option value="admin">Admin</option>
                    </select>
                    <p class="form-hint">
                        <span class="badge badge-red">Admin</span> — Full access &nbsp;
                        <span class="badge badge-blue">Reseller</span> — Manage own clients &nbsp;
                        <span class="badge badge-green">Customer</span> — Standard access
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-user')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Create User
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
