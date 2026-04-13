<?php
/**
 * File Manager — Browse, upload, edit files
 */
$pageTitle = 'File Manager';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Content Area -->
<div class="content">

    <!-- Page Header -->
    <div class="content-header">
        <h1><i class="fas fa-folder-open"></i> File Manager</h1>
    </div>

    <!-- Breadcrumb Navigation -->
    <div class="breadcrumb" id="file-breadcrumb">
        <a href="#" class="breadcrumb-item" data-path="/"><i class="fas fa-home"></i> Home</a>
    </div>

    <!-- Toolbar -->
    <div class="card">
        <div class="toolbar">
            <button class="btn btn-primary btn-sm" id="new-file-btn">
                <i class="fas fa-file-medical"></i> New File
            </button>
            <button class="btn btn-primary btn-sm" id="new-folder-btn">
                <i class="fas fa-folder-plus"></i> New Folder
            </button>
            <button class="btn btn-secondary btn-sm" onclick="loadFileList(document.getElementById('file-table-body'), document.getElementById('file-breadcrumb'))">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <div style="flex: 1;"></div>
            <button class="btn btn-secondary btn-sm" id="compress-btn">
                <i class="fas fa-file-archive"></i> Compress
            </button>
        </div>

        <!-- Upload Area -->
        <div class="upload-area" id="upload-area">
            <i class="fas fa-cloud-upload-alt"></i>
            <p>Drag and drop files here, or <strong>click to browse</strong></p>
            <p class="text-sm text-muted">Maximum upload size: 128 MB</p>
        </div>
        <input type="file" id="file-upload-input" multiple style="display: none;">
    </div>

    <!-- File Listing Table -->
    <div class="card" style="margin-top: 16px;">
        <div class="table-container" style="border: none; border-radius: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th style="width: 100px;">Size</th>
                        <th style="width: 150px;">Modified</th>
                        <th style="width: 100px;">Permissions</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="file-table-body">
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 40px;">
                            <div class="loading-overlay" style="padding: 10px;">
                                <div class="spinner"></div>
                                <span>Loading files...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- File Editor Modal -->
<div class="modal-overlay" id="modal-file-editor">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit File — <span id="editor-filename">untitled</span></h3>
            <button class="modal-close" onclick="closeModal('modal-file-editor')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 0;">
            <textarea class="code-editor" id="code-editor-content" spellcheck="false"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modal-file-editor')">Cancel</button>
            <button class="btn btn-primary" id="save-file-btn">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- New File Modal -->
<div class="modal-overlay" id="modal-new-file">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3><i class="fas fa-file-medical"></i> New File</h3>
            <button class="modal-close" onclick="closeModal('modal-new-file')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="new-file-form">
            <div class="modal-body">
                <div class="form-group">
                    <label for="new-filename">File Name</label>
                    <input type="text" id="new-filename" name="filename" class="form-control" placeholder="index.html" required>
                    <p class="form-hint">Enter the file name with extension (e.g., config.php, .htaccess)</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-new-file')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create File
                </button>
            </div>
        </form>
    </div>
</div>

<!-- New Folder Modal -->
<div class="modal-overlay" id="modal-new-folder">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3><i class="fas fa-folder-plus"></i> New Folder</h3>
            <button class="modal-close" onclick="closeModal('modal-new-folder')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="new-folder-form">
            <div class="modal-body">
                <div class="form-group">
                    <label for="new-foldername">Folder Name</label>
                    <input type="text" id="new-foldername" name="foldername" class="form-control" placeholder="my-folder" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-new-folder')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Folder
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
