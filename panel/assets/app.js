/* ============================================
   WP Hosting Panel — Main JavaScript
   ============================================ */

(function () {
    'use strict';

    /* --- Configuration --- */
    const API_BASE = '/api/';
    let authToken = null;

    /* Get token from meta tag or localStorage */
    function getToken() {
        if (!authToken) {
            const meta = document.querySelector('meta[name="token"]');
            if (meta) {
                authToken = meta.getAttribute('content');
            } else {
                authToken = localStorage.getItem('panel_token');
            }
        }
        return authToken;
    }

    /* --- AJAX Helper --- */
    async function apiCall(url, options = {}) {
        const token = getToken();
        const headers = {
            'Content-Type': 'application/json',
            ...(options.headers || {})
        };
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }
        const config = {
            ...options,
            headers,
            credentials: 'include'
        };
        try {
            const response = await fetch(API_BASE + url, config);
            const data = await response.json();
            if (response.status === 401) {
                window.location.href = '/panel/index.php';
                return null;
            }
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    /* --- Notification System --- */
    function initNotifications() {
        let container = document.querySelector('.notification-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'notification-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function showNotification(message, type = 'info', duration = 4000) {
        const container = initNotifications();
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        const notification = document.createElement('div');
        notification.className = 'notification ' + type;
        notification.innerHTML = `
            <i class="fas ${icons[type] || icons.info}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(notification);
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100px)';
                notification.style.transition = 'all 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }
        }, duration);
    }

    /* --- Modal Functions --- */
    function openModal(modalId) {
        const overlay = document.getElementById(modalId);
        if (overlay) {
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            /* Focus first input */
            const firstInput = overlay.querySelector('input, select, textarea');
            if (firstInput) setTimeout(() => firstInput.focus(), 100);
        }
    }

    function closeModal(modalId) {
        const overlay = document.getElementById(modalId);
        if (overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    function closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.classList.remove('active');
        });
        document.body.style.overflow = '';
    }

    /* --- Sidebar Toggle (Mobile) --- */
    function initSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.querySelector('.sidebar-toggle');
        const content = document.querySelector('.content');

        if (toggle && sidebar) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
            });
        }

        /* Close sidebar when clicking outside on mobile */
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    /* --- Dropdown Menus --- */
    function initDropdowns() {
        document.addEventListener('click', (e) => {
            const toggle = e.target.closest('.dropdown-toggle');
            const menu = e.target.closest('.dropdown-menu');

            /* Close all open dropdowns */
            document.querySelectorAll('.dropdown-menu.active').forEach(d => {
                if (!d.contains(e.target)) {
                    d.classList.remove('active');
                }
            });

            if (toggle) {
                e.preventDefault();
                const parent = toggle.closest('.dropdown');
                const dropdownMenu = parent.querySelector('.dropdown-menu');
                if (dropdownMenu) {
                    dropdownMenu.classList.toggle('active');
                }
            }
        });
    }

    /* --- Table Search/Filter --- */
    function initTableSearch() {
        const searchInput = document.querySelector('#table-search');
        if (!searchInput) return;

        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            const table = document.querySelector('.table');
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    /* --- Loading States --- */
    function setLoading(element, loading) {
        if (!element) return;
        if (loading) {
            element.disabled = true;
            const originalHTML = element.innerHTML;
            element.dataset.originalHtml = originalHTML;
            element.innerHTML = '<span class="spinner spinner-sm"></span> Loading...';
        } else {
            element.disabled = false;
            if (element.dataset.originalHtml) {
                element.innerHTML = element.dataset.originalHtml;
            }
        }
    }

    function showTableLoading(tableBody) {
        if (!tableBody) return;
        const cols = tableBody.closest('table')?.querySelector('thead tr')?.children.length || 1;
        tableBody.innerHTML = `<tr><td colspan="${cols}" class="text-center" style="padding:40px">
            <div class="loading-overlay" style="padding:20px">
                <div class="spinner"></div>
                <span>Loading data...</span>
            </div>
        </td></tr>`;
    }

    /* --- Confirm Dialog --- */
    function confirmAction(message, onConfirm) {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.innerHTML = `
            <div class="modal modal-sm">
                <div class="modal-header">
                    <h3><i class="fas fa-exclamation-triangle text-warning"></i> Confirm Action</h3>
                    <button class="modal-close" onclick="this.closest('.modal-overlay').remove();document.body.style.overflow='';">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                    <p class="text-sm text-muted mt-2">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove();document.body.style.overflow='';">Cancel</button>
                    <button class="btn btn-danger" id="confirm-action-btn">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        overlay.querySelector('#confirm-action-btn').addEventListener('click', () => {
            overlay.remove();
            document.body.style.overflow = '';
            if (onConfirm) onConfirm();
        });
    }

    /* ================================================
       PAGE-SPECIFIC: Login
       ================================================ */
    function initLoginPage() {
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');
        const toggleRegister = document.getElementById('toggle-register');
        const toggleLogin = document.getElementById('toggle-login');
        const loginCard = document.querySelector('.login-card');

        if (toggleRegister && loginCard) {
            toggleRegister.addEventListener('click', () => {
                loginForm.classList.add('hidden');
                registerForm.classList.remove('hidden');
            });
        }

        if (toggleLogin && loginCard) {
            toggleLogin.addEventListener('click', () => {
                registerForm.classList.add('hidden');
                loginForm.classList.remove('hidden');
            });
        }

        /* Login Submit */
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = loginForm.querySelector('button[type="submit"]');
                setLoading(btn, true);

                try {
                    const email = loginForm.querySelector('[name="email"]').value;
                    const password = loginForm.querySelector('[name="password"]').value;

                    const data = await apiCall('auth.php?action=login', {
                        method: 'POST',
                        body: JSON.stringify({ email, password })
                    });

                    if (data && data.token) {
                        localStorage.setItem('panel_token', data.token);
                        localStorage.setItem('panel_user', JSON.stringify(data.user));
                        showNotification('Login successful!', 'success');
                        setTimeout(() => {
                            window.location.href = 'dashboard.php';
                        }, 500);
                    } else {
                        showNotification(data?.error || 'Login failed. Check your credentials.', 'error');
                    }
                } catch (err) {
                    showNotification('Network error. Please try again.', 'error');
                }
                setLoading(btn, false);
            });
        }

        /* Register Submit */
        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = registerForm.querySelector('button[type="submit"]');
                const password = registerForm.querySelector('[name="password"]').value;
                const confirmPassword = registerForm.querySelector('[name="confirm_password"]').value;

                if (password !== confirmPassword) {
                    showNotification('Passwords do not match.', 'error');
                    return;
                }

                setLoading(btn, true);

                try {
                    const name = registerForm.querySelector('[name="name"]').value;
                    const email = registerForm.querySelector('[name="email"]').value;
                    const role = registerForm.querySelector('[name="role"]').value;

                    const data = await apiCall('auth.php?action=register', {
                        method: 'POST',
                        body: JSON.stringify({ name, email, password, role })
                    });

                    if (data && data.success) {
                        showNotification('Registration successful! Please login.', 'success');
                        registerForm.classList.add('hidden');
                        loginForm.classList.remove('hidden');
                        registerForm.reset();
                    } else {
                        showNotification(data?.error || 'Registration failed.', 'error');
                    }
                } catch (err) {
                    showNotification('Network error. Please try again.', 'error');
                }
                setLoading(btn, false);
            });
        }
    }

    /* ================================================
       PAGE-SPECIFIC: Dashboard
       ================================================ */
    async function initDashboard() {
        const websitesBody = document.getElementById('websites-table-body');
        const sysInfoList = document.getElementById('system-info-list');

        /* Load stats */
        try {
            const websitesData = await apiCall('websites.php');
            const databasesData = await apiCall('database.php');

            const websiteCount = (websitesData && Array.isArray(websitesData)) ? websitesData.length : 0;
            const dbCount = (databasesData && Array.isArray(databasesData)) ? databasesData.length : 0;

            animateCounter('stat-websites', websiteCount);
            animateCounter('stat-databases', dbCount);
            animateCounter('stat-ssl', 0);
            document.getElementById('stat-disk').textContent = '—';
        } catch (err) {
            console.error('Failed to load dashboard stats:', err);
        }

        /* Load recent websites */
        if (websitesBody) {
            showTableLoading(websitesBody);
            try {
                const data = await apiCall('websites.php');
                if (data && Array.isArray(data) && data.length > 0) {
                    const recent = data.slice(0, 5);
                    websitesBody.innerHTML = recent.map(site => `
                        <tr>
                            <td><i class="fas fa-globe text-primary"></i> ${escapeHtml(site.domain)}</td>
                            <td><span class="badge badge-green"><span class="badge-dot green"></span> Active</span></td>
                            <td><span class="badge ${site.wordpress ? 'badge-blue' : 'badge-gray'}">${site.wordpress ? 'Installed' : 'Not Installed'}</span></td>
                            <td><a href="websites.php" class="btn btn-sm btn-outline-primary">Manage</a></td>
                        </tr>
                    `).join('');
                } else {
                    websitesBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted" style="padding:30px">No websites found</td></tr>`;
                }
            } catch (err) {
                websitesBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger" style="padding:30px">Failed to load websites</td></tr>`;
            }
        }

        /* Load system info */
        if (sysInfoList) {
            try {
                const data = await apiCall('system-info.php');
                if (data) {
                    const items = [
                        { label: 'Server IP', value: data.server_ip || '—' },
                        { label: 'PHP Version', value: data.php_version || '—' },
                        { label: 'MySQL Version', value: data.mysql_version || '—' },
                        { label: 'LiteSpeed Version', value: data.litespeed_version || '—' },
                        { label: 'Disk Space', value: data.disk_space || '—' },
                        { label: 'Uptime', value: data.uptime || '—' }
                    ];
                    sysInfoList.innerHTML = items.map(item => `
                        <li>
                            <span class="info-label">${item.label}</span>
                            <span class="info-value">${escapeHtml(item.value)}</span>
                        </li>
                    `).join('');
                } else {
                    sysInfoList.innerHTML = '<li class="text-muted">Unable to load system information</li>';
                }
            } catch (err) {
                sysInfoList.innerHTML = '<li class="text-muted">Unable to load system information</li>';
            }
        }
    }

    function animateCounter(elementId, target) {
        const el = document.getElementById(elementId);
        if (!el) return;
        let current = 0;
        const increment = Math.max(1, Math.ceil(target / 20));
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            el.textContent = current;
        }, 50);
    }

    /* ================================================
       PAGE-SPECIFIC: Websites
       ================================================ */
    async function initWebsites() {
        const tableBody = document.getElementById('websites-table-body');
        const emptyState = document.getElementById('websites-empty');
        const tableContainer = document.getElementById('websites-table-container');
        const createForm = document.getElementById('create-website-form');

        await loadWebsites(tableBody, emptyState, tableContainer);

        /* Create Website */
        if (createForm) {
            createForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = createForm.querySelector('button[type="submit"]');
                setLoading(btn, true);

                try {
                    const domain = createForm.querySelector('[name="domain"]').value;
                    const phpVersion = createForm.querySelector('[name="php_version"]').value;

                    const data = await apiCall('websites.php?action=create', {
                        method: 'POST',
                        body: JSON.stringify({ domain, php_version: phpVersion })
                    });

                    if (data && data.success) {
                        showNotification('Website created successfully!', 'success');
                        closeModal('modal-create-website');
                        createForm.reset();
                        await loadWebsites(tableBody, emptyState, tableContainer);
                    } else {
                        showNotification(data?.error || 'Failed to create website.', 'error');
                    }
                } catch (err) {
                    showNotification('Network error. Please try again.', 'error');
                }
                setLoading(btn, false);
            });
        }

        /* Delete Website ( delegated event ) */
        if (tableBody) {
            tableBody.addEventListener('click', async (e) => {
                const deleteBtn = e.target.closest('.delete-website-btn');
                if (!deleteBtn) return;
                const domain = deleteBtn.dataset.domain;
                confirmAction(`Are you sure you want to delete <strong>${escapeHtml(domain)}</strong>? This will remove all files and databases.`, async () => {
                    try {
                        const data = await apiCall('websites.php?action=delete', {
                            method: 'POST',
                            body: JSON.stringify({ domain })
                        });
                        if (data && data.success) {
                            showNotification('Website deleted.', 'success');
                            await loadWebsites(tableBody, emptyState, tableContainer);
                        } else {
                            showNotification(data?.error || 'Failed to delete website.', 'error');
                        }
                    } catch (err) {
                        showNotification('Network error.', 'error');
                    }
                });
            });
        }
    }

    async function loadWebsites(tableBody, emptyState, tableContainer) {
        if (!tableBody) return;
        showTableLoading(tableBody);

        try {
            const data = await apiCall('websites.php');
            if (data && Array.isArray(data) && data.length > 0) {
                if (emptyState) emptyState.classList.add('hidden');
                if (tableContainer) tableContainer.classList.remove('hidden');
                tableBody.innerHTML = data.map(site => `
                    <tr>
                        <td><i class="fas fa-globe text-primary"></i> <strong>${escapeHtml(site.domain)}</strong></td>
                        <td><span class="badge badge-blue">PHP ${escapeHtml(site.php_version || '8.2')}</span></td>
                        <td><span class="badge ${site.wordpress ? 'badge-green' : 'badge-gray'}">${site.wordpress ? 'Installed' : 'Not Installed'}</span></td>
                        <td><span class="badge ${site.ssl ? 'badge-green' : 'badge-yellow'}">${site.ssl ? 'Active' : 'None'}</span></td>
                        <td class="text-muted text-sm">${escapeHtml(site.created_at || '—')}</td>
                        <td>
                            <div class="actions">
                                ${!site.wordpress ? `<button class="btn btn-sm btn-success install-wp-btn" data-domain="${escapeHtml(site.domain)}"><i class="fas fa-download"></i> WP</button>` : ''}
                                ${!site.ssl ? `<button class="btn btn-sm btn-warning install-ssl-btn" data-domain="${escapeHtml(site.domain)}"><i class="fas fa-lock"></i> SSL</button>` : ''}
                                <button class="btn btn-sm btn-danger delete-website-btn" data-domain="${escapeHtml(site.domain)}"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                `).join('');

                /* Wire install WP buttons */
                tableBody.querySelectorAll('.install-wp-btn').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const domain = btn.dataset.domain;
                        setLoading(btn, true);
                        try {
                            const data = await apiCall(`wordpress.php?action=install&domain=${encodeURIComponent(domain)}`);
                            if (data && data.success) {
                                showNotification('WordPress installed!', 'success');
                                await loadWebsites(tableBody, emptyState, tableContainer);
                            } else {
                                showNotification(data?.error || 'Install failed.', 'error');
                            }
                        } catch (err) {
                            showNotification('Network error.', 'error');
                        }
                        setLoading(btn, false);
                    });
                });

                /* Wire install SSL buttons */
                tableBody.querySelectorAll('.install-ssl-btn').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const domain = btn.dataset.domain;
                        setLoading(btn, true);
                        try {
                            const user = JSON.parse(localStorage.getItem('panel_user') || '{}');
                            const data = await apiCall('ssl.php?action=install', {
                                method: 'POST',
                                body: JSON.stringify({ domain, email: user.email || 'admin@example.com' })
                            });
                            if (data && data.success) {
                                showNotification('SSL certificate installed!', 'success');
                                await loadWebsites(tableBody, emptyState, tableContainer);
                            } else {
                                showNotification(data?.error || 'SSL install failed.', 'error');
                            }
                        } catch (err) {
                            showNotification('Network error.', 'error');
                        }
                        setLoading(btn, false);
                    });
                });

            } else {
                if (emptyState) emptyState.classList.remove('hidden');
                if (tableContainer) tableContainer.classList.add('hidden');
            }
        } catch (err) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger" style="padding:30px">Failed to load websites</td></tr>`;
        }
    }

    /* ================================================
       PAGE-SPECIFIC: File Manager
       ================================================ */
    let currentPath = '/';

    function initFileManager() {
        const fileBody = document.getElementById('file-table-body');
        const breadcrumb = document.getElementById('file-breadcrumb');
        const uploadArea = document.getElementById('upload-area');
        const uploadInput = document.getElementById('file-upload-input');
        const newFileBtn = document.getElementById('new-file-btn');
        const newFolderBtn = document.getElementById('new-folder-btn');

        loadFileList(fileBody, breadcrumb);

        if (fileBody) {
            fileBody.addEventListener('click', async (e) => {
                const row = e.target.closest('.file-row');
                if (!row) return;
                const name = row.dataset.name;
                const type = row.dataset.type;

                if (type === 'folder') {
                    currentPath = currentPath === '/' ? '/' + name : currentPath + '/' + name;
                    loadFileList(fileBody, breadcrumb);
                } else {
                    openFileEditor(name);
                }
            });
        }

        /* Breadcrumb navigation */
        if (breadcrumb) {
            breadcrumb.addEventListener('click', (e) => {
                const crumb = e.target.closest('.breadcrumb-item');
                if (crumb) {
                    e.preventDefault();
                    currentPath = crumb.dataset.path || '/';
                    loadFileList(fileBody, breadcrumb);
                }
            });
        }

        /* Upload */
        if (uploadArea && uploadInput) {
            uploadArea.addEventListener('click', () => uploadInput.click());
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                handleFileUpload(e.dataTransfer.files, fileBody, breadcrumb);
            });
            uploadInput.addEventListener('change', () => {
                if (uploadInput.files.length > 0) {
                    handleFileUpload(uploadInput.files, fileBody, breadcrumb);
                    uploadInput.value = '';
                }
            });
        }

        /* New File */
        if (newFileBtn) {
            newFileBtn.addEventListener('click', () => {
                openNewFileModal(fileBody, breadcrumb);
            });
        }

        /* New Folder */
        if (newFolderBtn) {
            newFolderBtn.addEventListener('click', () => {
                openNewFolderModal(fileBody, breadcrumb);
            });
        }
    }

    async function loadFileList(fileBody, breadcrumb) {
        if (!fileBody) return;
        showTableLoading(fileBody);

        try {
            const data = await apiCall(`file-manager.php?path=${encodeURIComponent(currentPath)}`);
            if (data && Array.isArray(data)) {
                const sorted = data.sort((a, b) => {
                    if (a.type === 'folder' && b.type !== 'folder') return -1;
                    if (a.type !== 'folder' && b.type === 'folder') return 1;
                    return a.name.localeCompare(b.name);
                });

                fileBody.innerHTML = sorted.map(file => {
                    const icon = file.type === 'folder' ? 'fa-folder' : getFileIcon(file.name);
                    const iconClass = file.type === 'folder' ? 'folder' : getFileIconClass(file.name);
                    return `
                        <tr class="file-row" data-name="${escapeHtml(file.name)}" data-type="${file.type}">
                            <td>
                                <div class="file-icon ${iconClass}"><i class="fas ${icon}"></i></div>
                                <span class="file-name">${escapeHtml(file.name)}</span>
                            </td>
                            <td class="file-size">${file.type === 'folder' ? '—' : formatSize(file.size)}</td>
                            <td class="file-modified">${escapeHtml(file.modified || '—')}</td>
                            <td class="file-permissions">${escapeHtml(file.permissions || '—')}</td>
                            <td>
                                <div class="actions">
                                    ${file.type === 'file' ? `<button class="btn btn-sm btn-outline edit-file-btn" data-name="${escapeHtml(file.name)}"><i class="fas fa-edit"></i></button>` : ''}
                                    <button class="btn btn-sm btn-danger delete-file-btn" data-name="${escapeHtml(file.name)}" data-type="${file.type}"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');

                /* Wire delete buttons */
                fileBody.querySelectorAll('.delete-file-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const name = btn.dataset.name;
                        confirmAction(`Delete <strong>${escapeHtml(name)}</strong>?`, async () => {
                            try {
                                const data = await apiCall('file-manager.php?action=delete', {
                                    method: 'POST',
                                    body: JSON.stringify({ path: currentPath === '/' ? '/' + name : currentPath + '/' + name })
                                });
                                if (data && data.success) {
                                    showNotification('File deleted.', 'success');
                                    loadFileList(fileBody, breadcrumb);
                                } else {
                                    showNotification(data?.error || 'Delete failed.', 'error');
                                }
                            } catch (err) {
                                showNotification('Network error.', 'error');
                            }
                        });
                    });
                });

                /* Wire edit buttons */
                fileBody.querySelectorAll('.edit-file-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        openFileEditor(btn.dataset.name);
                    });
                });

            } else {
                fileBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted" style="padding:30px">Directory is empty</td></tr>`;
            }
        } catch (err) {
            fileBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger" style="padding:30px">Failed to load files</td></tr>`;
        }

        updateBreadcrumb(breadcrumb);
    }

    function updateBreadcrumb(breadcrumb) {
        if (!breadcrumb) return;
        const parts = currentPath.split('/').filter(Boolean);
        let html = `<a href="#" class="breadcrumb-item" data-path="/"><i class="fas fa-home"></i> Home</a>`;
        let path = '';
        parts.forEach((part, i) => {
            path += '/' + part;
            if (i < parts.length - 1) {
                html += `<span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <a href="#" class="breadcrumb-item" data-path="${escapeHtml(path)}">${escapeHtml(part)}</a>`;
            } else {
                html += `<span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <span class="current">${escapeHtml(part)}</span>`;
            }
        });
        breadcrumb.innerHTML = html;
    }

    async function openFileEditor(filename) {
        openModal('modal-file-editor');
        const editor = document.getElementById('code-editor-content');
        const filenameEl = document.getElementById('editor-filename');
        const saveBtn = document.getElementById('save-file-btn');

        if (filenameEl) filenameEl.textContent = filename;
        if (editor) editor.value = 'Loading file content...';

        try {
            const filePath = currentPath === '/' ? '/' + filename : currentPath + '/' + filename;
            const data = await apiCall(`file-manager.php?action=read&path=${encodeURIComponent(filePath)}`);
            if (data && data.content !== undefined) {
                editor.value = data.content;
            } else {
                editor.value = '';
                showNotification('Failed to load file.', 'error');
            }
        } catch (err) {
            editor.value = '';
            showNotification('Failed to load file.', 'error');
        }

        if (saveBtn) {
            saveBtn.onclick = async () => {
                setLoading(saveBtn, true);
                try {
                    const filePath = currentPath === '/' ? '/' + filename : currentPath + '/' + filename;
                    const data = await apiCall('file-manager.php?action=save', {
                        method: 'POST',
                        body: JSON.stringify({ path: filePath, content: editor.value })
                    });
                    if (data && data.success) {
                        showNotification('File saved!', 'success');
                        closeModal('modal-file-editor');
                    } else {
                        showNotification(data?.error || 'Save failed.', 'error');
                    }
                } catch (err) {
                    showNotification('Network error.', 'error');
                }
                setLoading(saveBtn, false);
            };
        }
    }

    function openNewFileModal(fileBody, breadcrumb) {
        openModal('modal-new-file');
        const form = document.getElementById('new-file-form');
        if (!form) return;
        form.onsubmit = async (e) => {
            e.preventDefault();
            const name = form.querySelector('[name="filename"]').value;
            const btn = form.querySelector('button[type="submit"]');
            setLoading(btn, true);
            try {
                const path = currentPath === '/' ? '/' + name : currentPath + '/' + name;
                const data = await apiCall('file-manager.php?action=create-file', {
                    method: 'POST',
                    body: JSON.stringify({ path, content: '' })
                });
                if (data && data.success) {
                    showNotification('File created.', 'success');
                    closeModal('modal-new-file');
                    form.reset();
                    loadFileList(fileBody, breadcrumb);
                } else {
                    showNotification(data?.error || 'Failed to create file.', 'error');
                }
            } catch (err) {
                showNotification('Network error.', 'error');
            }
            setLoading(btn, false);
        };
    }

    function openNewFolderModal(fileBody, breadcrumb) {
        openModal('modal-new-folder');
        const form = document.getElementById('new-folder-form');
        if (!form) return;
        form.onsubmit = async (e) => {
            e.preventDefault();
            const name = form.querySelector('[name="foldername"]').value;
            const btn = form.querySelector('button[type="submit"]');
            setLoading(btn, true);
            try {
                const path = currentPath === '/' ? '/' + name : currentPath + '/' + name;
                const data = await apiCall('file-manager.php?action=create-folder', {
                    method: 'POST',
                    body: JSON.stringify({ path })
                });
                if (data && data.success) {
                    showNotification('Folder created.', 'success');
                    closeModal('modal-new-folder');
                    form.reset();
                    loadFileList(fileBody, breadcrumb);
                } else {
                    showNotification(data?.error || 'Failed to create folder.', 'error');
                }
            } catch (err) {
                showNotification('Network error.', 'error');
            }
            setLoading(btn, false);
        };
    }

    async function handleFileUpload(files, fileBody, breadcrumb) {
        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('path', currentPath);
            const token = getToken();
            try {
                const response = await fetch(API_BASE + 'file-manager.php?action=upload', {
                    method: 'POST',
                    headers: token ? { 'Authorization': 'Bearer ' + token } : {},
                    body: formData,
                    credentials: 'include'
                });
                const data = await response.json();
                if (data && data.success) {
                    showNotification(`${file.name} uploaded.`, 'success');
                } else {
                    showNotification(`Failed to upload ${file.name}.`, 'error');
                }
            } catch (err) {
                showNotification(`Failed to upload ${file.name}.`, 'error');
            }
        }
        loadFileList(fileBody, breadcrumb);
    }

    /* ================================================
       PAGE-SPECIFIC: Database
       ================================================ */
    async function initDatabase() {
        const tableBody = document.getElementById('database-table-body');
        const emptyState = document.getElementById('database-empty');
        const tableContainer = document.getElementById('database-table-container');
        const createForm = document.getElementById('create-database-form');

        await loadDatabases(tableBody, emptyState, tableContainer);

        if (createForm) {
            createForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = createForm.querySelector('button[type="submit"]');
                setLoading(btn, true);
                try {
                    const name = createForm.querySelector('[name="db_name"]').value;
                    const user = createForm.querySelector('[name="db_user"]').value;
                    const password = createForm.querySelector('[name="db_password"]').value;
                    const data = await apiCall('database.php?action=create', {
                        method: 'POST',
                        body: JSON.stringify({ name, user, password })
                    });
                    if (data && data.success) {
                        showNotification('Database created!', 'success');
                        closeModal('modal-create-database');
                        createForm.reset();
                        await loadDatabases(tableBody, emptyState, tableContainer);
                    } else {
                        showNotification(data?.error || 'Failed to create database.', 'error');
                    }
                } catch (err) {
                    showNotification('Network error.', 'error');
                }
                setLoading(btn, false);
            });
        }

        if (tableBody) {
            tableBody.addEventListener('click', (e) => {
                const deleteBtn = e.target.closest('.delete-db-btn');
                if (!deleteBtn) return;
                const name = deleteBtn.dataset.name;
                confirmAction(`Delete database <strong>${escapeHtml(name)}</strong>? All data will be lost.`, async () => {
                    try {
                        const data = await apiCall('database.php?action=delete', {
                            method: 'POST',
                            body: JSON.stringify({ name })
                        });
                        if (data && data.success) {
                            showNotification('Database deleted.', 'success');
                            await loadDatabases(tableBody, emptyState, tableContainer);
                        } else {
                            showNotification(data?.error || 'Failed to delete database.', 'error');
                        }
                    } catch (err) {
                        showNotification('Network error.', 'error');
                    }
                });
            });
        }
    }

    async function loadDatabases(tableBody, emptyState, tableContainer) {
        if (!tableBody) return;
        showTableLoading(tableBody);
        try {
            const data = await apiCall('database.php');
            if (data && Array.isArray(data) && data.length > 0) {
                if (emptyState) emptyState.classList.add('hidden');
                if (tableContainer) tableContainer.classList.remove('hidden');
                tableBody.innerHTML = data.map(db => `
                    <tr>
                        <td><i class="fas fa-database text-success"></i> <strong>${escapeHtml(db.name)}</strong></td>
                        <td>${escapeHtml(db.user || '—')}</td>
                        <td class="text-muted text-sm">${escapeHtml(db.size || '—')}</td>
                        <td class="text-muted text-sm">${escapeHtml(db.created_at || '—')}</td>
                        <td>
                            <div class="actions">
                                <a href="/phpmyadmin" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-external-link-alt"></i> phpMyAdmin</a>
                                <button class="btn btn-sm btn-danger delete-db-btn" data-name="${escapeHtml(db.name)}"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } else {
                if (emptyState) emptyState.classList.remove('hidden');
                if (tableContainer) tableContainer.classList.add('hidden');
            }
        } catch (err) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger" style="padding:30px">Failed to load databases</td></tr>`;
        }
    }

    /* ================================================
       PAGE-SPECIFIC: SSL
       ================================================ */
    async function initSSL() {
        const tableBody = document.getElementById('ssl-table-body');
        const installForm = document.getElementById('install-ssl-form');

        await loadSSLList(tableBody);

        if (installForm) {
            installForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = installForm.querySelector('button[type="submit"]');
                setLoading(btn, true);
                try {
                    const domain = installForm.querySelector('[name="domain"]').value;
                    const email = installForm.querySelector('[name="email"]').value;
                    const data = await apiCall('ssl.php?action=install', {
                        method: 'POST',
                        body: JSON.stringify({ domain, email })
                    });
                    if (data && data.success) {
                        showNotification('SSL certificate installed!', 'success');
                        closeModal('modal-install-ssl');
                        installForm.reset();
                        await loadSSLList(tableBody);
                    } else {
                        showNotification(data?.error || 'SSL install failed.', 'error');
                    }
                } catch (err) {
                    showNotification('Network error.', 'error');
                }
                setLoading(btn, false);
            });
        }
    }

    async function loadSSLList(tableBody) {
        if (!tableBody) return;
        showTableLoading(tableBody);
        try {
            /* Try to get websites first, then check SSL for each */
            const websites = await apiCall('websites.php');
            if (websites && Array.isArray(websites) && websites.length > 0) {
                const sslRows = [];
                for (const site of websites) {
                    try {
                        const sslData = await apiCall(`ssl.php?domain=${encodeURIComponent(site.domain)}`);
                        sslRows.push({
                            domain: site.domain,
                            status: sslData?.status || 'none',
                            issuer: sslData?.issuer || '—',
                            expiry: sslData?.expiry || '—',
                            auto_renew: sslData?.auto_renew || false
                        });
                    } catch (err) {
                        sslRows.push({ domain: site.domain, status: 'none', issuer: '—', expiry: '—', auto_renew: false });
                    }
                }
                tableBody.innerHTML = sslRows.map(ssl => {
                    let statusBadge = '';
                    if (ssl.status === 'active') {
                        statusBadge = '<span class="badge badge-green"><span class="badge-dot green"></span> Active</span>';
                    } else if (ssl.status === 'expired') {
                        statusBadge = '<span class="badge badge-red"><span class="badge-dot red"></span> Expired</span>';
                    } else {
                        statusBadge = '<span class="badge badge-gray"><span class="badge-dot gray"></span> None</span>';
                    }
                    return `
                        <tr>
                            <td><i class="fas fa-globe text-primary"></i> <strong>${escapeHtml(ssl.domain)}</strong></td>
                            <td>${statusBadge}</td>
                            <td class="text-sm">${escapeHtml(ssl.issuer)}</td>
                            <td class="text-sm text-muted">${escapeHtml(ssl.expiry)}</td>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox" ${ssl.auto_renew ? 'checked' : ''} data-domain="${escapeHtml(ssl.domain)}" class="auto-renew-toggle">
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td>
                                <div class="actions">
                                    ${ssl.status !== 'active' ? `<button class="btn btn-sm btn-success install-ssl-inline" data-domain="${escapeHtml(ssl.domain)}"><i class="fas fa-lock"></i> Install</button>` : ''}
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');

                /* Wire auto-renew toggles */
                tableBody.querySelectorAll('.auto-renew-toggle').forEach(toggle => {
                    toggle.addEventListener('change', async () => {
                        showNotification('Auto-renew setting updated.', 'info');
                    });
                });

                /* Wire inline install buttons */
                tableBody.querySelectorAll('.install-ssl-inline').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        setLoading(btn, true);
                        try {
                            const user = JSON.parse(localStorage.getItem('panel_user') || '{}');
                            const data = await apiCall('ssl.php?action=install', {
                                method: 'POST',
                                body: JSON.stringify({ domain: btn.dataset.domain, email: user.email || 'admin@example.com' })
                            });
                            if (data && data.success) {
                                showNotification('SSL installed!', 'success');
                                await loadSSLList(tableBody);
                            } else {
                                showNotification(data?.error || 'Install failed.', 'error');
                            }
                        } catch (err) {
                            showNotification('Network error.', 'error');
                        }
                        setLoading(btn, false);
                    });
                });

            } else {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted" style="padding:30px">No websites found. Create a website first.</td></tr>`;
            }
        } catch (err) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger" style="padding:30px">Failed to load SSL data</td></tr>`;
        }
    }

    /* ================================================
       PAGE-SPECIFIC: PHP Settings
       ================================================ */
    async function initPHPSettings() {
        const domainSelect = document.getElementById('php-domain-select');
        const settingsForm = document.getElementById('php-settings-form');
        const phpInfoBtn = document.getElementById('php-info-btn');

        /* Load domains into select */
        if (domainSelect) {
            try {
                const data = await apiCall('websites.php');
                if (data && Array.isArray(data)) {
                    domainSelect.innerHTML = '<option value="">Select a domain...</option>' +
                        data.map(site => `<option value="${escapeHtml(site.domain)}">${escapeHtml(site.domain)}</option>`).join('');
                }
            } catch (err) {
                console.error('Failed to load domains:', err);
            }

            domainSelect.addEventListener('change', async () => {
                const domain = domainSelect.value;
                if (!domain) return;
                await loadPHPSettings(domain);
            });
        }

        /* Save Settings */
        if (settingsForm) {
            settingsForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const domain = domainSelect?.value;
                if (!domain) {
                    showNotification('Please select a domain.', 'warning');
                    return;
                }
                const btn = settingsForm.querySelector('button[type="submit"]');
                setLoading(btn, true);
                try {
                    const formData = new FormData(settingsForm);
                    const payload = {
                        domain,
                        php_version: formData.get('php_version'),
                        memory_limit: formData.get('memory_limit'),
                        upload_max: formData.get('upload_max'),
                        max_execution_time: formData.get('max_execution_time'),
                        display_errors: formData.has('display_errors') ? '1' : '0',
                        post_max_size: formData.get('post_max_size')
                    };
                    const data = await apiCall('php-settings.php?action=update', {
                        method: 'POST',
                        body: JSON.stringify(payload)
                    });
                    if (data && data.success) {
                        showNotification('PHP settings saved!', 'success');
                    } else {
                        showNotification(data?.error || 'Failed to save settings.', 'error');
                    }
                } catch (err) {
                    showNotification('Network error.', 'error');
                }
                setLoading(btn, false);
            });
        }

        /* PHP Info */
        if (phpInfoBtn) {
            phpInfoBtn.addEventListener('click', () => {
                window.open('/panel/phpinfo.php', '_blank');
            });
        }
    }

    async function loadPHPSettings(domain) {
        const form = document.getElementById('php-settings-form');
        if (!form) return;

        try {
            const data = await apiCall(`php-settings.php?domain=${encodeURIComponent(domain)}`);
            if (data) {
                /* Set radio buttons */
                const phpVersion = data.php_version || '8.2';
                const radio = form.querySelector(`input[name="php_version"][value="${phpVersion}"]`);
                if (radio) radio.checked = true;
                document.querySelectorAll('.radio-group .radio-item').forEach(item => {
                    item.classList.toggle('active', item.querySelector('input').checked);
                });

                /* Set selects */
                setSelectValue(form, 'memory_limit', data.memory_limit);
                setSelectValue(form, 'upload_max', data.upload_max);
                setSelectValue(form, 'post_max_size', data.post_max_size);

                /* Set inputs */
                const execTime = form.querySelector('[name="max_execution_time"]');
                if (execTime && data.max_execution_time) execTime.value = data.max_execution_time;

                /* Toggle */
                const displayErrors = form.querySelector('[name="display_errors"]');
                if (displayErrors) displayErrors.checked = data.display_errors === '1' || data.display_errors === true;
            }
        } catch (err) {
            showNotification('Failed to load PHP settings.', 'error');
        }
    }

    function setSelectValue(form, name, value) {
        const select = form.querySelector(`[name="${name}"]`);
        if (select && value) select.value = value;
    }

    /* ================================================
       PAGE-SPECIFIC: Cron Jobs
       ================================================ */
    async function initCronJobs() {
        const tableBody = document.getElementById('cron-table-body');
        const addForm = document.getElementById('add-cron-form');
        const scheduleSelect = document.getElementById('cron-schedule-type');
        const customExpr = document.getElementById('cron-custom-expression');
        const schedulePreview = document.getElementById('cron-schedule-preview');

        await loadCronJobs(tableBody);

        /* Schedule type toggle */
        if (scheduleSelect) {
            scheduleSelect.addEventListener('change', () => {
                const custom = document.getElementById('cron-custom-group');
                if (custom) {
                    custom.style.display = scheduleSelect.value === 'custom' ? 'block' : 'none';
                }
                updateCronPreview();
            });
        }

        if (scheduleSelect) {
            document.querySelectorAll('#add-cron-form input, #add-cron-form textarea, #add-cron-form select').forEach(el => {
                el.addEventListener('input', updateCronPreview);
                el.addEventListener('change', updateCronPreview);
            });
        }

        function updateCronPreview() {
            if (!schedulePreview) return;
            const type = scheduleSelect?.value || '';
            const expressions = {
                'every-minute': '* * * * *',
                'hourly': '0 * * * *',
                'daily': '0 0 * * *',
                'weekly': '0 0 * * 0',
                'monthly': '0 0 1 * *',
                'custom': customExpr?.value || '* * * * *'
            };
            schedulePreview.textContent = expressions[type] || '* * * * *';
        }

        /* Add Cron Job */
        if (addForm) {
            addForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = addForm.querySelector('button[type="submit"]');
                setLoading(btn, true);
                try {
                    const command = addForm.querySelector('[name="command"]').value;
                    const schedule = addForm.querySelector('[name="schedule_type"]').value;
                    const expression = schedule === 'custom' ? addForm.querySelector('[name="custom_expression"]').value : null;
                    const data = await apiCall('cron-jobs.php?action=create', {
                        method: 'POST',
                        body: JSON.stringify({ command, schedule, expression })
                    });
                    if (data && data.success) {
                        showNotification('Cron job created!', 'success');
                        closeModal('modal-add-cron');
                        addForm.reset();
                        await loadCronJobs(tableBody);
                    } else {
                        showNotification(data?.error || 'Failed to create cron job.', 'error');
                    }
                } catch (err) {
                    showNotification('Network error.', 'error');
                }
                setLoading(btn, false);
            });
        }

        /* Delete / Toggle */
        if (tableBody) {
            tableBody.addEventListener('click', async (e) => {
                const deleteBtn = e.target.closest('.delete-cron-btn');
                const toggleBtn = e.target.closest('.toggle-cron-btn');

                if (deleteBtn) {
                    const id = deleteBtn.dataset.id;
                    confirmAction('Delete this cron job?', async () => {
                        try {
                            const data = await apiCall('cron-jobs.php?action=delete', {
                                method: 'POST',
                                body: JSON.stringify({ id })
                            });
                            if (data && data.success) {
                                showNotification('Cron job deleted.', 'success');
                                await loadCronJobs(tableBody);
                            } else {
                                showNotification(data?.error || 'Failed to delete.', 'error');
                            }
                        } catch (err) {
                            showNotification('Network error.', 'error');
                        }
                    });
                }

                if (toggleBtn) {
                    const id = toggleBtn.dataset.id;
                    const status = toggleBtn.dataset.status === 'active' ? 'paused' : 'active';
                    try {
                        const data = await apiCall('cron-jobs.php?action=toggle', {
                            method: 'POST',
                            body: JSON.stringify({ id, status })
                        });
                        if (data && data.success) {
                            showNotification(`Cron job ${status}.`, 'success');
                            await loadCronJobs(tableBody);
                        } else {
                            showNotification(data?.error || 'Failed to toggle.', 'error');
                        }
                    } catch (err) {
                        showNotification('Network error.', 'error');
                    }
                }
            });
        }
    }

    async function loadCronJobs(tableBody) {
        if (!tableBody) return;
        showTableLoading(tableBody);
        try {
            const data = await apiCall('cron-jobs.php');
            if (data && Array.isArray(data) && data.length > 0) {
                tableBody.innerHTML = data.map(job => `
                    <tr>
                        <td><code class="text-sm">${escapeHtml(job.command)}</code></td>
                        <td><span class="badge badge-blue">${escapeHtml(job.schedule_label || job.schedule)}</span></td>
                        <td class="text-sm text-muted">${escapeHtml(job.next_run || '—')}</td>
                        <td>
                            <span class="badge ${job.status === 'active' ? 'badge-green' : 'badge-yellow'}">
                                <span class="badge-dot ${job.status === 'active' ? 'green' : 'yellow'}"></span>
                                ${job.status === 'active' ? 'Active' : 'Paused'}
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-sm btn-outline toggle-cron-btn" data-id="${escapeHtml(job.id)}" data-status="${job.status}">
                                    <i class="fas ${job.status === 'active' ? 'fa-pause' : 'fa-play'}"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-cron-btn" data-id="${escapeHtml(job.id)}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } else {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted" style="padding:30px">No cron jobs configured</td></tr>`;
            }
        } catch (err) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger" style="padding:30px">Failed to load cron jobs</td></tr>`;
        }
    }

    /* ================================================
       PAGE-SPECIFIC: Users
       ================================================ */
    async function initUsers() {
        const tableBody = document.getElementById('users-table-body');
        const addForm = document.getElementById('add-user-form');

        await loadUsers(tableBody);

        if (addForm) {
            addForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = addForm.querySelector('button[type="submit"]');
                setLoading(btn, true);
                try {
                    const name = addForm.querySelector('[name="name"]').value;
                    const email = addForm.querySelector('[name="email"]').value;
                    const password = addForm.querySelector('[name="password"]').value;
                    const role = addForm.querySelector('[name="role"]').value;
                    const data = await apiCall('auth.php?action=register', {
                        method: 'POST',
                        body: JSON.stringify({ name, email, password, role })
                    });
                    if (data && data.success) {
                        showNotification('User created!', 'success');
                        closeModal('modal-add-user');
                        addForm.reset();
                        await loadUsers(tableBody);
                    } else {
                        showNotification(data?.error || 'Failed to create user.', 'error');
                    }
                } catch (err) {
                    showNotification('Network error.', 'error');
                }
                setLoading(btn, false);
            });
        }

        if (tableBody) {
            tableBody.addEventListener('click', (e) => {
                const deleteBtn = e.target.closest('.delete-user-btn');
                if (!deleteBtn) return;
                const userId = deleteBtn.dataset.id;
                const userName = deleteBtn.dataset.name;
                confirmAction(`Delete user <strong>${escapeHtml(userName)}</strong>?`, async () => {
                    try {
                        const data = await apiCall('auth.php?action=delete-user', {
                            method: 'POST',
                            body: JSON.stringify({ user_id: userId })
                        });
                        if (data && data.success) {
                            showNotification('User deleted.', 'success');
                            await loadUsers(tableBody);
                        } else {
                            showNotification(data?.error || 'Failed to delete user.', 'error');
                        }
                    } catch (err) {
                        showNotification('Network error.', 'error');
                    }
                });
            });
        }
    }

    async function loadUsers(tableBody) {
        if (!tableBody) return;
        showTableLoading(tableBody);
        try {
            const data = await apiCall('auth.php?action=users');
            if (data && Array.isArray(data) && data.length > 0) {
                tableBody.innerHTML = data.map(user => {
                    const roleBadge = user.role === 'admin' ? 'badge-red' : user.role === 'reseller' ? 'badge-blue' : 'badge-green';
                    return `
                        <tr>
                            <td><i class="fas fa-user text-muted"></i> <strong>${escapeHtml(user.name)}</strong></td>
                            <td class="text-sm">${escapeHtml(user.email)}</td>
                            <td><span class="badge ${roleBadge}">${escapeHtml(user.role)}</span></td>
                            <td><span class="badge badge-green"><span class="badge-dot green"></span> Active</span></td>
                            <td class="text-sm text-muted">${escapeHtml(user.created_at || '—')}</td>
                            <td>
                                <div class="actions">
                                    <button class="btn btn-sm btn-outline"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger delete-user-btn" data-id="${escapeHtml(user.id)}" data-name="${escapeHtml(user.name)}"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');
            } else {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted" style="padding:30px">No users found</td></tr>`;
            }
        } catch (err) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger" style="padding:30px">Failed to load users</td></tr>`;
        }
    }

    /* ================================================
       UTILITIES
       ================================================ */
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
    }

    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const icons = {
            'php': 'fa-file-code', 'html': 'fa-file-code', 'htm': 'fa-file-code', 'css': 'fa-file-code',
            'js': 'fa-file-code', 'json': 'fa-file-code', 'xml': 'fa-file-code', 'py': 'fa-file-code',
            'jpg': 'fa-file-image', 'jpeg': 'fa-file-image', 'png': 'fa-file-image', 'gif': 'fa-file-image',
            'svg': 'fa-file-image', 'webp': 'fa-file-image',
            'zip': 'fa-file-archive', 'tar': 'fa-file-archive', 'gz': 'fa-file-archive', 'rar': 'fa-file-archive',
            'pdf': 'fa-file-pdf',
            'txt': 'fa-file-alt', 'md': 'fa-file-alt', 'log': 'fa-file-alt',
            'sql': 'fa-database',
            'conf': 'fa-cog', 'ini': 'fa-cog', 'yml': 'fa-cog', 'yaml': 'fa-cog'
        };
        return icons[ext] || 'fa-file';
    }

    function getFileIconClass(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const codeExts = ['php', 'html', 'htm', 'css', 'js', 'json', 'xml', 'py'];
        const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        if (codeExts.includes(ext)) return 'code';
        if (imageExts.includes(ext)) return 'image';
        return 'file';
    }

    /* ================================================
       INITIALIZATION
       ================================================ */
    document.addEventListener('DOMContentLoaded', () => {
        /* Global features */
        initNotifications();
        initSidebar();
        initDropdowns();
        initTableSearch();

        /* Close modals on overlay click */
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                closeAllModals();
            }
        });

        /* Close modals on Escape key */
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeAllModals();
        });

        /* Radio button group styling */
        document.querySelectorAll('.radio-group .radio-item input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', () => {
                radio.closest('.radio-group').querySelectorAll('.radio-item').forEach(item => {
                    item.classList.toggle('active', item.querySelector('input').checked);
                });
            });
        });

        /* Detect current page and initialize */
        const page = document.body.dataset.page;
        switch (page) {
            case 'login':
                initLoginPage();
                break;
            case 'dashboard':
                initDashboard();
                break;
            case 'websites':
                initWebsites();
                break;
            case 'file-manager':
                initFileManager();
                break;
            case 'database':
                initDatabase();
                break;
            case 'ssl':
                initSSL();
                break;
            case 'php-settings':
                initPHPSettings();
                break;
            case 'cron-jobs':
                initCronJobs();
                break;
            case 'users':
                initUsers();
                break;
        }
    });

})();

/* --- Global Utility Functions (accessible from inline onclick handlers) --- */

/**
 * Toggle password field visibility
 * @param {string} inputId - The password input field ID
 * @param {HTMLButtonElement} btn - The toggle button
 */
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}
