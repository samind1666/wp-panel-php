<?php
/**
 * SSL Certificates — SSL management page
 */
$pageTitle = 'SSL Certificates';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Content Area -->
<div class="content">

    <!-- Page Header -->
    <div class="content-header">
        <h1><i class="fas fa-lock"></i> SSL Certificates</h1>
        <div class="header-actions">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="table-search" placeholder="Search domains...">
            </div>
            <button class="btn btn-primary" onclick="openModal('modal-install-ssl')">
                <i class="fas fa-plus"></i> Install SSL
            </button>
        </div>
    </div>

    <!-- SSL Overview Cards -->
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number" id="ssl-active">0</div>
                <div class="stat-label">Active Certificates</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number" id="ssl-expired">0</div>
                <div class="stat-label">Expired</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gray">
                <i class="fas fa-unlock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number" id="ssl-none">0</div>
                <div class="stat-label">No SSL</div>
            </div>
        </div>
    </div>

    <!-- SSL Table -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Certificate Status</h2>
            <button class="btn btn-sm btn-outline" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
        <div class="table-container" style="border: none; border-radius: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Issuer</th>
                        <th>Expiry Date</th>
                        <th>Auto-Renew</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ssl-table-body">
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 40px;">
                            <div class="loading-overlay" style="padding: 10px;">
                                <div class="spinner"></div>
                                <span>Loading SSL status...</span>
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
            <h2><i class="fas fa-info-circle"></i> About SSL Certificates</h2>
        </div>
        <div class="card-body">
            <div class="grid-2">
                <div>
                    <h4 style="margin-bottom: 8px; font-size: 14px;"><i class="fas fa-check-circle text-success"></i> Let's Encrypt</h4>
                    <p class="text-sm text-muted">
                        We use Let's Encrypt to provide free SSL/TLS certificates. Certificates are automatically issued via ACME protocol and are valid for 90 days. Enable auto-renewal to ensure your sites stay secure.
                    </p>
                </div>
                <div>
                    <h4 style="margin-bottom: 8px; font-size: 14px;"><i class="fas fa-bolt text-warning"></i> Auto-Renewal</h4>
                    <p class="text-sm text-muted">
                        When auto-renewal is enabled, the system will automatically attempt to renew your SSL certificate 30 days before expiration. Make sure your domain's DNS is correctly configured to point to this server.
                    </p>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Install SSL Modal -->
<div class="modal-overlay" id="modal-install-ssl">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-lock"></i> Install SSL Certificate</h3>
            <button class="modal-close" onclick="closeModal('modal-install-ssl')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="install-ssl-form">
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    A free Let's Encrypt SSL certificate will be issued for your domain. Make sure your domain is already pointing to this server.
                </div>

                <div class="form-group">
                    <label for="ssl-domain">Domain</label>
                    <select id="ssl-domain" name="domain" class="form-control" required>
                        <option value="">Select a domain...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ssl-email">Email Address (for certificate notifications)</label>
                    <input type="email" id="ssl-email" name="email" class="form-control"
                        placeholder="admin@example.com" required
                        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                    <p class="form-hint">This email will receive certificate expiration notifications from Let's Encrypt.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-install-ssl')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-lock"></i> Install SSL
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SSL Certificate Details Modal -->
<div class="modal-overlay" id="modal-ssl-details">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-certificate"></i> Certificate Details</h3>
            <button class="modal-close" onclick="closeModal('modal-ssl-details')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="ssl-details-body">
            <!-- Populated dynamically -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-ssl-details')">Close</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
