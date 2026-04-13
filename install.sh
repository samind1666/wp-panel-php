#!/bin/bash
# ============================================================
# WP Hosting Panel - FAST Install (~3 Minutes!)
# ============================================================
# Panel only. Server stack installs from panel UI later.
# Usage: bash install.sh
# ============================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

clear
echo -e "${CYAN}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║     WP Hosting Panel - Quick Install     ║"
echo "  ║           Panel Only (~3 min)            ║"
echo "  ╚══════════════════════════════════════════╝"
echo -e "${NC}"

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED} Root required! Run: sudo bash install.sh${NC}"
    exit 1
fi

PANEL_DIR="/opt/wp-panel"

# ── STEP 1: Update system ──
echo -e "${YELLOW}[1/4] Updating system...${NC}"
apt update -y > /dev/null 2>&1

# ── STEP 2: Install PHP 8.0 + SQLite (PANEL ONLY!) ──
echo -e "${YELLOW}[2/4] Installing PHP 8.0 + SQLite...${NC}"

# Add PHP repo for Ubuntu 20.04
add-apt-repository -y ppa:ondrej/php > /dev/null 2>&1
apt update -y > /dev/null 2>&1

# Install ONLY what panel needs (minimal, fast!)
apt install -y php8.0-cli php8.0-sqlite3 php8.0-mbstring \
    php8.0-xml php8.0-curl php8.0-zip php8.0-gd > /dev/null 2>&1

# Basic tools
apt install -y curl wget unzip > /dev/null 2>&1

echo -e "${GREEN}  PHP 8.0 + SQLite installed!${NC}"

# ── STEP 3: Setup panel files + config ──
echo -e "${YELLOW}[3/4] Setting up panel...${NC}"

JWT_SECRET=$(openssl rand -base64 64 | tr -d '/+=' | head -c 64)
SERVER_IP=$(hostname -I | awk '{print $1}')

# Create directories
mkdir -p "$PANEL_DIR/logs"
mkdir -p "$PANEL_DIR/data"
chmod 755 "$PANEL_DIR/logs"
chmod 755 "$PANEL_DIR/data"

# Auto-generate config.php if not exists
if [ ! -f "$PANEL_DIR/config.php" ]; then
cat > "$PANEL_DIR/config.php" << CFGEOF
<?php
define('PANEL_URL', 'http://${SERVER_IP}:8080');
define('PANEL_PORT', 8080);
define('PANEL_NAME', 'WP Hosting Panel');
define('JWT_SECRET', '${JWT_SECRET}');
define('DB_PATH', __DIR__ . '/data/panel.sqlite');
define('SITES_HOME', '/home');
define('LSWS_VHOSTS_DIR', '/usr/local/lsws/conf/vhosts');
define('LSWS_CONF_DIR', '/usr/local/lsws/conf');
define('LSWS_VHOST_TEMPLATE', __DIR__ . '/templates/litespeed-vhost.conf');
define('LSWS_PHP_DIR', '/usr/local/lsws/lsphp8');
define('SSL_CERTS_DIR', '/etc/letsencrypt/live');
define('LSWS_BIN', '/usr/local/lsws/bin/lswsctrl');
define('LSWS_ADMIN_PORT', 7080);
define('CERTBOT_WEBROOT', '/var/www/html');
define('DEFAULT_PHP', '8.0');
define('PHP_VERSIONS', '8.0,8.1,8.2,8.3');
define('WP_CLI_PATH', '/usr/local/bin/wp');
define('WP_DEFAULT_THEME', 'twentytwentyfour');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);
define('SESSION_EXPIRE_HOURS', 24);
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');
if (!is_dir(__DIR__ . '/logs')) mkdir(__DIR__ . '/logs', 0755, true);
if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);

function getDB(): PDO {
    static \$pdo = null;
    if (\$pdo === null) {
        try {
            \$pdo = new PDO('sqlite:' . DB_PATH);
            \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            \$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            \$pdo->exec('PRAGMA foreign_keys = ON');
            \$pdo->exec('PRAGMA journal_mode = WAL');
        } catch (PDOException \$e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection failed']);
            exit;
        }
    }
    return \$pdo;
}

function jwtEncode(array \$payload): string {
    \$header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    \$payload['exp'] = time() + (SESSION_EXPIRE_HOURS * 3600);
    \$payload['iat'] = time();
    \$payloadEnc = base64_encode(json_encode(\$payload));
    \$signature = base64_encode(hash_hmac('sha256', "\$header.\$payloadEnc", JWT_SECRET, true));
    return "\$header.\$payloadEnc.\$signature";
}

function jwtDecode(string \$token): ?array {
    \$parts = explode('.', \$token);
    if (count(\$parts) !== 3) return null;
    [\$header, \$payload, \$signature] = \$parts;
    \$expectedSig = base64_encode(hash_hmac('sha256', "\$header.\$payload", JWT_SECRET, true));
    if (!hash_equals(\$expectedSig, \$signature)) return null;
    \$data = json_decode(base64_decode(\$payload), true);
    if (isset(\$data['exp']) && \$data['exp'] < time()) return null;
    return \$data;
}

function authMiddleware(): array {
    \$headers = getallheaders();
    \$authHeader = \$headers['Authorization'] ?? \$headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/', \$authHeader, \$matches)) {
        \$decoded = jwtDecode(\$matches[1]);
        if (\$decoded) return \$decoded;
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

function requireRole(string \$role): void {
    \$user = authMiddleware();
    if (\$user['role'] !== \$role && \$user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied.']);
        exit;
    }
}

function jsonResponse(array \$data, int \$code = 200): void {
    http_response_code(\$code);
    header('Content-Type: application/json');
    echo json_encode(\$data);
    exit;
}

function input(\$key, \$default = null) {
    \$json = json_decode(file_get_contents('php://input'), true);
    if (\$json && isset(\$json[\$key])) return \$json[\$key];
    return \$_POST[\$key] ?? \$_GET[\$key] ?? \$default;
}

function sanitizeDomain(string \$domain): string {
    \$domain = strtolower(trim(\$domain));
    \$domain = preg_replace('/^https?:\/\//', '', \$domain);
    \$domain = preg_replace('/^www\./', '', \$domain);
    return rtrim(\$domain, '/');
}

function validateDomain(string \$domain): bool {
    return preg_match('/^[a-z0-9][a-z0-9.-]+\.[a-z]{2,}$/', \$domain) === 1;
}

function randomPassword(int \$length = 16): string {
    \$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#\$%&*';
    \$pass = '';
    for (\$i = 0; \$i < \$length; \$i++) {
        \$pass .= \$chars[random_int(0, strlen(\$chars) - 1)];
    }
    return \$pass;
}

function generateId(string \$prefix = ''): string {
    return \$prefix . date('YmdHis') . random_int(1000, 9999);
}

function initDatabase(): void {
    \$db = getDB();
    \$db->exec("CREATE TABLE IF NOT EXISTS users (
        id TEXT PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL, role TEXT DEFAULT 'customer',
        status TEXT DEFAULT 'active', max_websites INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now'))
    )");
    \$db->exec("CREATE TABLE IF NOT EXISTS websites (
        id TEXT PRIMARY KEY, user_id TEXT NOT NULL, domain TEXT NOT NULL UNIQUE,
        doc_root TEXT NOT NULL, php_version TEXT DEFAULT '8.0',
        db_name TEXT, db_user TEXT, db_password TEXT,
        status TEXT DEFAULT 'active', ssl_status TEXT DEFAULT 'none',
        ssl_expiry TEXT, wp_version TEXT, disk_used_mb REAL DEFAULT 0,
        bandwidth_used_mb REAL DEFAULT 0, ip TEXT DEFAULT '0.0.0.0',
        created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    \$db->exec("CREATE TABLE IF NOT EXISTS databases (
        id TEXT PRIMARY KEY, website_id TEXT NOT NULL, db_name TEXT NOT NULL,
        db_user TEXT NOT NULL, db_host TEXT DEFAULT 'localhost',
        size_mb REAL DEFAULT 0, created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
    )");
    \$db->exec("CREATE TABLE IF NOT EXISTS ssl_certs (
        id TEXT PRIMARY KEY, website_id TEXT NOT NULL, domain TEXT NOT NULL,
        issuer TEXT DEFAULT 'Let''s Encrypt', expiry_date TEXT NOT NULL,
        auto_renew INTEGER DEFAULT 1, status TEXT DEFAULT 'active',
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
    )");
    \$db->exec("CREATE TABLE IF NOT EXISTS ftp_accounts (
        id TEXT PRIMARY KEY, website_id TEXT NOT NULL, username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL, directory TEXT NOT NULL, quota_mb INTEGER DEFAULT 0,
        status TEXT DEFAULT 'active', created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
    )");
    \$db->exec("CREATE TABLE IF NOT EXISTS cron_jobs (
        id TEXT PRIMARY KEY, website_id TEXT, user_id TEXT NOT NULL,
        command TEXT NOT NULL, schedule TEXT NOT NULL, cron_expression TEXT NOT NULL,
        description TEXT, enabled INTEGER DEFAULT 1,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    \$db->exec("CREATE TABLE IF NOT EXISTS backups (
        id TEXT PRIMARY KEY, website_id TEXT NOT NULL, file_path TEXT NOT NULL,
        file_size_mb REAL DEFAULT 0, type TEXT DEFAULT 'full',
        status TEXT DEFAULT 'completed', created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
    )");
    \$db->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id TEXT PRIMARY KEY, user_id TEXT NOT NULL, action TEXT NOT NULL,
        description TEXT, type TEXT DEFAULT 'info', ip_address TEXT,
        created_at TEXT DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    \$db->exec("CREATE TABLE IF NOT EXISTS server_components (
        component TEXT PRIMARY KEY, status TEXT DEFAULT 'not_installed',
        version TEXT, installed_at TEXT, install_log TEXT
    )");
    \$stmt = \$db->prepare("SELECT id FROM users WHERE email = ?");
    \$stmt->execute(['admin@wphosting.com']);
    if (!\$stmt->fetch()) {
        \$adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        \$db->prepare("INSERT INTO users (id,name,email,password,role,max_websites) VALUES (?,?,?,?,?,?)")
            ->execute([generateId('u'), 'Administrator', 'admin@wphosting.com', \$adminPass, 'admin', 999]);
    }
    foreach (['webserver','php','mariadb','phpmyadmin','postfix','security','wpcli'] as \$c) {
        \$db->prepare("INSERT OR IGNORE INTO server_components (component,status) VALUES (?,'not_installed')")->execute([\$c]);
    }
}
CFGEOF
fi

echo -e "${GREEN}  Panel configured!${NC}"

# ── STEP 4: Start panel ──
echo -e "${YELLOW}[4/4] Starting panel...${NC}"

# Create systemd service using PHP built-in server
cat > /etc/systemd/system/wp-panel.service << 'EOF'
[Unit]
Description=WP Hosting Panel
After=network.target

[Service]
Type=simple
ExecStart=/usr/bin/php8.0 -S 0.0.0.0:8080 -t /opt/wp-panel
Restart=always
RestartSec=3
WorkingDirectory=/opt/wp-panel

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable wp-panel > /dev/null 2>&1
systemctl restart wp-panel

# Open firewall
ufw allow 22/tcp > /dev/null 2>&1
ufw allow 8080/tcp > /dev/null 2>&1
echo "y" | ufw enable > /dev/null 2>&1

# Save credentials
cat > "$PANEL_DIR/CREDENTIALS.txt" << CREDS
============================================
  WP Hosting Panel - READY!
============================================

Panel URL:     http://${SERVER_IP}:8080
Admin Email:   admin@wphosting.com
Admin Password: admin123

NEXT STEPS:
  1. Open panel URL in browser
  2. Login with admin@wphosting.com / admin123
  3. Go to Server Setup
  4. Install components from UI (LiteSpeed, MariaDB, etc.)

============================================
  NOTE: Delete this file after saving!
============================================
CREDS
chmod 600 "$PANEL_DIR/CREDENTIALS.txt"

# Done!
echo ""
echo -e "${GREEN}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║      PANEL READY! (~3 min)               ║"
echo "  ╚══════════════════════════════════════════╝"
echo -e "${NC}"
echo ""
echo -e "  Panel URL:  ${CYAN}http://${SERVER_IP}:8080${NC}"
echo -e "  Login:      ${CYAN}admin@wphosting.com / admin123${NC}"
echo ""
echo -e "  ${YELLOW}Next: Open panel -> Server Setup -> Install components${NC}"
echo -e "  ${RED}Change default password after first login!${NC}"
echo ""
