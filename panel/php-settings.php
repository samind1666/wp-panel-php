<?php
/**
 * PHP Settings — PHP configuration page
 */
$pageTitle = 'PHP Settings';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Content Area -->
<div class="content">

    <!-- Page Header -->
    <div class="content-header">
        <h1><i class="fas fa-code"></i> PHP Settings</h1>
        <div class="header-actions">
            <button class="btn btn-secondary" id="php-info-btn">
                <i class="fas fa-info-circle"></i> PHP Info
            </button>
        </div>
    </div>

    <!-- Domain Selector -->
    <div class="card mb-4">
        <div class="form-group" style="margin-bottom: 0;">
            <label for="php-domain-select" style="font-size: 14px;">
                <i class="fas fa-globe"></i> Select Website to Configure
            </label>
            <select id="php-domain-select" class="form-control" style="max-width: 400px;">
                <option value="">Loading domains...</option>
            </select>
            <p class="form-hint">Select a website to view and modify its PHP configuration. Settings apply per-domain.</p>
        </div>
    </div>

    <!-- PHP Settings Form -->
    <form id="php-settings-form">
        <div class="card mb-4">
            <div class="card-header">
                <h2><i class="fab fa-php"></i> PHP Version</h2>
                <span class="badge badge-blue">Per-Domain</span>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>PHP Version</label>
                    <div class="radio-group">
                        <label class="radio-item active">
                            <input type="radio" name="php_version" value="8.3"> PHP 8.3
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="php_version" value="8.2" checked> PHP 8.2
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="php_version" value="8.1"> PHP 8.1
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="php_version" value="8.0"> PHP 8.0
                        </label>
                    </div>
                    <p class="form-hint">PHP 8.2 is the default and recommended version. PHP 8.0 reaches end of life in November 2023.</p>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2><i class="fas fa-memory"></i> Resource Limits</h2>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="memory-limit">Memory Limit</label>
                        <select id="memory-limit" name="memory_limit" class="form-control">
                            <option value="128M">128 MB</option>
                            <option value="256M" selected>256 MB</option>
                            <option value="512M">512 MB</option>
                            <option value="1G">1 GB</option>
                            <option value="2G">2 GB</option>
                        </select>
                        <p class="form-hint">Maximum amount of memory a script may consume.</p>
                    </div>
                    <div class="form-group">
                        <label for="max-execution-time">Max Execution Time (seconds)</label>
                        <input type="number" id="max-execution-time" name="max_execution_time" class="form-control"
                            value="30" min="1" max="3600">
                        <p class="form-hint">Maximum time in seconds a script is allowed to run.</p>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="upload-max">Max Upload Size</label>
                        <select id="upload-max" name="upload_max" class="form-control">
                            <option value="2M">2 MB</option>
                            <option value="8M">8 MB</option>
                            <option value="16M">16 MB</option>
                            <option value="32M" selected>32 MB</option>
                            <option value="64M">64 MB</option>
                            <option value="128M">128 MB</option>
                        </select>
                        <p class="form-hint">Maximum size of an uploaded file.</p>
                    </div>
                    <div class="form-group">
                        <label for="post-max-size">Post Max Size</label>
                        <select id="post-max-size" name="post_max_size" class="form-control">
                            <option value="8M">8 MB</option>
                            <option value="16M">16 MB</option>
                            <option value="32M">32 MB</option>
                            <option value="64M" selected>64 MB</option>
                            <option value="128M">128 MB</option>
                            <option value="256M">256 MB</option>
                        </select>
                        <p class="form-hint">Must be greater than or equal to upload_max_filesize.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h2><i class="fas fa-cog"></i> Other Settings</h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="display_errors" id="display-errors">
                        <span>Display Errors</span>
                    </label>
                    <p class="form-hint">When enabled, PHP errors and warnings will be displayed in the output. Disable this in production for security.</p>
                </div>

                <div class="form-group">
                    <label for="timezone">Timezone</label>
                    <select id="timezone" name="timezone" class="form-control" style="max-width: 300px;">
                        <option value="UTC">UTC</option>
                        <option value="America/New_York" selected>America/New_York</option>
                        <option value="America/Chicago">America/Chicago</option>
                        <option value="America/Denver">America/Denver</option>
                        <option value="America/Los_Angeles">America/Los_Angeles</option>
                        <option value="Europe/London">Europe/London</option>
                        <option value="Europe/Berlin">Europe/Berlin</option>
                        <option value="Asia/Tokyo">Asia/Tokyo</option>
                        <option value="Australia/Sydney">Australia/Sydney</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div style="display: flex; gap: 12px;">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Save Settings
            </button>
            <button type="button" class="btn btn-secondary btn-lg" onclick="location.reload()">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
