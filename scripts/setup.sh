#!/bin/bash
# ============================================================
# WP Hosting Panel - Full Server Setup (One Command)
# ============================================================
# Tested on: Ubuntu 22.04 LTS / 20.04 LTS
# Run as ROOT: curl -sL .../setup.sh | sudo bash
# Or: bash setup.sh
# ============================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}   WP Hosting Panel - Server Setup${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""

# Check root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root: sudo bash setup.sh${NC}"
    exit 1
fi

# Update system
echo -e "${YELLOW}[1/10] Updating system packages...${NC}"
apt update -y && apt upgrade -y

# Install basic tools
echo -e "${YELLOW}[2/10] Installing basic tools...${NC}"
apt install -y curl wget git unzip software-properties-common \
    apt-transport-https ca-certificates gnupg lsb-release \
    ufw fail2ban htop vim

# Install Nginx
echo -e "${YELLOW}[3/10] Installing Nginx...${NC}"
apt install -y nginx
systemctl enable nginx
systemctl start nginx

# Install MariaDB
echo -e "${YELLOW}[4/10] Installing MariaDB...${NC}"
apt install -y mariadb-server
systemctl enable mariadb
systemctl start mariadb

# Secure MariaDB (non-interactive)
echo -e "${YELLOW}[5/10] Securing MariaDB...${NC}"
mysql -e "DELETE FROM mysql.user WHERE User='';"
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
mysql -e "DROP DATABASE IF EXISTS test;"
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
mysql -e "FLUSH PRIVILEGES;"

# Add PHP PPA (required for Ubuntu 20.04 & 18.04)
echo -e "${YELLOW}[6/10] Adding PHP repository...${NC}"
add-apt-repository -y ppa:ondrej/php
apt update -y

# Install PHP 8.0 + FPM + common extensions
echo -e "${YELLOW}[6.1/10] Installing PHP 8.0 FPM...${NC}"
apt install -y php8.0-fpm php8.0-mysql php8.0-mbstring php8.0-xml \
    php8.0-curl php8.0-zip php8.0-gd php8.0-intl php8.0-imagick \
    php8.0-opcache php8.0-readline php8.0-soap php8.0-ssh2 2>/dev/null || true

# Install PHP 8.1, 8.2, 8.3 (optional versions)
echo -e "${YELLOW}[6.5/10] Installing additional PHP versions...${NC}"
apt install -y php8.1-fpm php8.1-mysql php8.1-mbstring php8.1-xml php8.1-curl php8.1-zip 2>/dev/null || true
apt install -y php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip 2>/dev/null || true
apt install -y php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip 2>/dev/null || true

# PHP tuning for speed
echo -e "${YELLOW}[7/10] Tuning PHP for performance...${NC}"
for ver in 8.0 8.1 8.2 8.3; do
    PHPINI="/etc/php/$ver/fpm/php.ini"
    if [ -f "$PHPINI" ]; then
        sed -i 's/memory_limit = .*/memory_limit = 256M/' "$PHPINI"
        sed -i 's/upload_max_filesize = .*/upload_max_filesize = 64M/' "$PHPINI"
        sed -i 's/post_max_size = .*/post_max_size = 128M/' "$PHPINI"
        sed -i 's/max_execution_time = .*/max_execution_time = 300/' "$PHPINI"
        sed -i 's/;max_input_vars = .*/max_input_vars = 3000/' "$PHPINI"
        sed -i 's/display_errors = .*/display_errors = Off/' "$PHPINI"
        # OPcache
        sed -i 's/opcache.enable=.*/opcache.enable=1/' "$PHPINI"
        sed -i 's/opcache.memory_consumption=.*/opcache.memory_consumption=128/' "$PHPINI"
        sed -i 's/opcache.max_accelerated_files=.*/opcache.max_accelerated_files=10000/' "$PHPINI"
        # Realpath cache
        echo "realpath_cache_size = 4096K" >> "$PHPINI"
        echo "realpath_cache_ttl = 600" >> "$PHPINI"
    fi
done

# Restart all PHP-FPM
for ver in 8.0 8.1 8.2 8.3; do
    systemctl restart "php$ver-fpm" 2>/dev/null || true
done

# Install WP-CLI
echo -e "${YELLOW}[8/10] Installing WP-CLI...${NC}"
curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
mv wp-cli.phar /usr/local/bin/wp
wp cli info --allow-root 2>/dev/null && echo -e "${GREEN}WP-CLI installed successfully${NC}"

# Install Certbot (Let's Encrypt)
echo -e "${YELLOW}[9/10] Installing Certbot (SSL)...${NC}"
apt install -y certbot python3-certbot-nginx
systemctl enable certbot.timer
systemctl start certbot.timer

# Setup Firewall (UFW)
echo -e "${YELLOW}[10/10] Setting up Firewall...${NC}"
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 8080/tcp
echo "y" | ufw enable

# Setup Fail2Ban
echo -e "${YELLOW}Setting up Fail2Ban...${NC}"
cat > /etc/fail2ban/jail.local <<EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
destemail = root@localhost
sender = root@localhost

[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log

[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log

[sshd]
enabled = true
port = ssh
logpath = /var/log/auth.log
maxretry = 3
bantime = 7200

[recidive]
enabled = true
logpath = /var/log/fail2ban.log
bantime = 86400
findtime = 86400
maxretry = 3
EOF
systemctl enable fail2ban
systemctl restart fail2ban

# Create sites home directory
mkdir -p /home
chmod 755 /home

# Create panel database
echo -e "${YELLOW}Creating panel database...${NC}"
DB_PASS=$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)
mysql -e "CREATE DATABASE IF NOT EXISTS wp_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'wp_panel'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON wp_panel.* TO 'wp_panel'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Panel directory
PANEL_DIR="/var/www/wp-panel"
mkdir -p "$PANEL_DIR/logs"
mkdir -p "$PANEL_DIR/public"
chmod -R 755 "$PANEL_DIR"
chown -R www-data:www-data "$PANEL_DIR"

# Nginx config for panel
cat > /etc/nginx/sites-available/wp-panel <<'PANEL_NGINX'
server {
    listen 8080;
    server_name _;

    root /var/www/wp-panel/public;
    index index.php index.html;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
PANEL_NGINX

ln -sf /etc/nginx/sites-available/wp-panel /etc/nginx/sites-enabled/wp-panel
nginx -t && systemctl reload nginx

# Done!
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}   SETUP COMPLETE!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo "Database: wp_panel"
echo "DB User: wp_panel"
echo "DB Password: ${DB_PASS}"
echo "Panel URL: http://YOUR_SERVER_IP:8080"
echo "Panel Dir: ${PANEL_DIR}"
echo ""
echo -e "${YELLOW}NEXT STEPS:${NC}"
echo "1. Copy panel PHP files to: ${PANEL_DIR}/"
echo "2. Update config.php with DB credentials"
echo "3. Access panel at: http://YOUR_SERVER_IP:8080"
echo "4. Default admin: admin@wphosting.com / admin123"
echo ""
echo -e "${RED}IMPORTANT: Change default password after first login!${NC}"
echo ""
