<?php
/**
 * Cron Jobs — Scheduled task management page
 */
$pageTitle = 'Cron Jobs';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Content Area -->
<div class="content">

    <!-- Page Header -->
    <div class="content-header">
        <h1><i class="fas fa-clock"></i> Cron Jobs</h1>
        <div class="header-actions">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="table-search" placeholder="Search cron jobs...">
            </div>
            <button class="btn btn-primary" onclick="openModal('modal-add-cron')">
                <i class="fas fa-plus"></i> Add Cron Job
            </button>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Cron Jobs</strong> are scheduled tasks that run automatically at specified intervals. Use them for regular maintenance, backups, and automated processes.
            All cron jobs run in the server's timezone.
        </div>
    </div>

    <!-- Cron Jobs Table -->
    <div class="card">
        <div class="table-container" style="border: none; border-radius: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Command</th>
                        <th>Schedule</th>
                        <th>Next Run</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="cron-table-body">
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 40px;">
                            <div class="loading-overlay" style="padding: 10px;">
                                <div class="spinner"></div>
                                <span>Loading cron jobs...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Schedule Reference -->
    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h2><i class="fas fa-book"></i> Cron Expression Reference</h2>
        </div>
        <div class="card-body">
            <div class="table-container" style="border: none; border-radius: 0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Expression</th>
                            <th>Description</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>* * * * *</code></td>
                            <td>Every minute</td>
                            <td class="text-muted text-sm">Run a health check script every minute</td>
                        </tr>
                        <tr>
                            <td><code>0 * * * *</code></td>
                            <td>Every hour</td>
                            <td class="text-muted text-sm">Hourly analytics processing</td>
                        </tr>
                        <tr>
                            <td><code>0 0 * * *</code></td>
                            <td>Every day at midnight</td>
                            <td class="text-muted text-sm">Daily database backup</td>
                        </tr>
                        <tr>
                            <td><code>0 0 * * 0</code></td>
                            <td>Every Sunday at midnight</td>
                            <td class="text-muted text-sm">Weekly log rotation</td>
                        </tr>
                        <tr>
                            <td><code>0 0 1 * *</code></td>
                            <td>1st of every month at midnight</td>
                            <td class="text-muted text-sm">Monthly usage report generation</td>
                        </tr>
                        <tr>
                            <td><code>*/5 * * * *</code></td>
                            <td>Every 5 minutes</td>
                            <td class="text-muted text-sm">Frequent queue processing</td>
                        </tr>
                        <tr>
                            <td><code>0 */6 * * *</code></td>
                            <td>Every 6 hours</td>
                            <td class="text-muted text-sm">Certificate renewal check</td>
                        </tr>
                        <tr>
                            <td><code>30 2 * * *</code></td>
                            <td>2:30 AM daily</td>
                            <td class="text-muted text-sm">Off-peak maintenance tasks</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <p class="text-sm text-muted">
                    <strong>Format:</strong>
                    <code>minute hour day month weekday</code> — Each field accepts numbers, ranges (1-5), steps (*/2), and lists (1,3,5).
                </p>
            </div>
        </div>
    </div>

</div>

<!-- Add Cron Job Modal -->
<div class="modal-overlay" id="modal-add-cron">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-clock"></i> Add Cron Job</h3>
            <button class="modal-close" onclick="closeModal('modal-add-cron')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="add-cron-form">
            <div class="modal-body">
                <div class="form-group">
                    <label for="cron-command">Command</label>
                    <textarea id="cron-command" name="command" class="form-control" rows="3" placeholder="php /var/www/example.com/public_html/artisan schedule:run" required></textarea>
                    <p class="form-hint">Enter the full command to be executed at the scheduled time.</p>
                </div>

                <div class="form-group">
                    <label for="cron-schedule-type">Schedule Type</label>
                    <select id="cron-schedule-type" name="schedule_type" class="form-control">
                        <option value="every-minute">Every Minute</option>
                        <option value="hourly">Hourly</option>
                        <option value="daily" selected>Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="custom">Custom Expression</option>
                    </select>
                </div>

                <div class="form-group" id="cron-custom-group" style="display: none;">
                    <label for="cron-custom-expression">Cron Expression</label>
                    <input type="text" id="cron-custom-expression" name="custom_expression" class="form-control" placeholder="* * * * *">
                    <p class="form-hint">Use standard 5-field cron expression format: minute hour day month weekday</p>
                </div>

                <div class="form-group">
                    <label>Cron Expression Preview</label>
                    <div class="cron-preview" id="cron-schedule-preview">0 0 * * *</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-cron')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Cron Job
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
