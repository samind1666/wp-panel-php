#!/bin/bash
# ============================================================
# Component Installer (runs in background from panel)
# Usage: bash install-component.sh <component> <logfile>
# ============================================================

COMPONENT=$1
LOGFILE=$2
PANEL_DIR="/opt/wp-panel"

log() {
    echo "$1" >> "$LOGFILE"
    # Flush to disk
    sync
}

log "[START] Installing $COMPONENT at $(date)"

case $COMPONENT in

    webserver)
        log "[1/5] Adding LiteSpeed repository..."
        wget -qO - https://repo.litespeed.sh | bash >> "$LOGFILE" 2>&1
        if [ $? -eq 0 ]; then
            log "[OK] LiteSpeed repo added"
        else
            log "[FAIL] Failed to add LiteSpeed repo"
        fi

        log "[2/5] Installing OpenLiteSpeed..."
        apt install -y openlitespeed >> "$LOGFILE" 2>&1
        if [ $? -eq 0 ]; then
            log "[OK] OpenLiteSpeed installed"
        else
            log "[FAIL] OpenLiteSpeed install failed"
        fi

        log "[3/5] Installing LSPHP 8.0-8.3..."
        apt install -y lsphp80 lsphp80-mysql lsphp80-mbstring lsphp80-xml lsphp80-curl lsphp80-zip lsphp80-gd lsphp80-imagick >> "$LOGFILE" 2>&1
        apt install -y lsphp81 lsphp82 lsphp83 >> "$LOGFILE" 2>&1
        log "[DONE] LSPHP installed"

        log "[4/5] Configuring OpenLiteSpeed..."
        # Set admin password
        /usr/local/lsws/admin/misc/admpass.sh -a -u admin -p admin123 >> "$LOGFILE" 2>&1
        # Change port from 8088 to 80
        sed -i 's/listener.*8088/listener 0 80/' /usr/local/lsws/conf/httpd_config.conf 2>/dev/null
        /usr/local/lsws/bin/lswsctrl restart >> "$LOGFILE" 2>&1
        log "[DONE] OpenLiteSpeed configured and started"

        log "[5/5] Opening firewall ports..."
        ufw allow 80/tcp >> "$LOGFILE" 2>&1
        ufw allow 443/tcp >> "$LOGFILE" 2>&1
        ufw allow 7080/tcp >> "$LOGFILE" 2>&1
        ufw allow 8088/tcp >> "$LOGFILE" 2>&1
        log "[DONE] Firewall configured"

        # Update panel DB
        php8.0 -r "
            \$db = new PDO('sqlite:$PANEL_DIR/data/panel.sqlite');
            \$ver = '';
            exec('/usr/local/lsws/bin/lswsctrl -v 2>&1', \$out);
            \$ver = trim(implode(' ', \$out));
            \$db->prepare(\"UPDATE server_components SET status='installed', version=?, installed_at=datetime('now') WHERE component='webserver'\")->execute([\$ver]);
        "
        log "[COMPLETE] Web server installed successfully!"
        ;;

    php)
        log "[1/4] Adding PHP repository..."
        add-apt-repository -y ppa:ondrej/php >> "$LOGFILE" 2>&1
        apt update -y >> "$LOGFILE" 2>&1
        log "[OK] PHP repo ready"

        log "[2/4] Installing PHP 8.0 with extensions..."
        apt install -y php8.0-fpm php8.0-mysql php8.0-mbstring php8.0-xml php8.0-curl php8.0-zip php8.0-gd php8.0-imagick php8.0-intl php8.0-soap >> "$LOGFILE" 2>&1
        log "[DONE] PHP 8.0 installed"

        log "[3/4] Installing PHP 8.1-8.3..."
        apt install -y php8.1-fpm php8.1-mysql php8.2-fpm php8.2-mysql php8.3-fpm php8.3-mysql >> "$LOGFILE" 2>&1 || true
        log "[DONE] Additional PHP versions installed"

        log "[4/4] Restarting services..."
        systemctl enable php8.0-fpm >> "$LOGFILE" 2>&1
        systemctl restart php8.0-fpm >> "$LOGFILE" 2>&1 || true
        log "[DONE] PHP services started"

        php8.0 -r "
            \$db = new PDO('sqlite:$PANEL_DIR/data/panel.sqlite');
            \$ver = '';
            exec('/usr/bin/php8.0 -v 2>&1 | head -1', \$out);
            \$ver = trim(implode(' ', \$out));
            \$db->prepare(\"UPDATE server_components SET status='installed', version=?, installed_at=datetime('now') WHERE component='php'\")->execute([\$ver]);
        "
        log "[COMPLETE] PHP installed successfully!"
        ;;

    mariadb)
        log "[1/4] Installing MariaDB..."
        apt install -y mariadb-server >> "$LOGFILE" 2>&1
        systemctl enable mariadb >> "$LOGFILE" 2>&1
        systemctl start mariadb >> "$LOGFILE" 2>&1
        log "[OK] MariaDB installed and started"

        log "[2/4] Securing MariaDB..."
        mysql -e "DELETE FROM mysql.user WHERE User='';" >> "$LOGFILE" 2>&1
        mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1');" >> "$LOGFILE" 2>&1
        mysql -e "DROP DATABASE IF EXISTS test;" >> "$LOGFILE" 2>&1
        mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';" >> "$LOGFILE" 2>&1
        mysql -e "FLUSH PRIVILEGES;" >> "$LOGFILE" 2>&1
        log "[DONE] MariaDB secured"

        log "[3/4] Creating databases directory..."
        mkdir -p /home
        log "[DONE] Ready"

        log "[4/4] Verifying..."
        mysql -e "SELECT VERSION();" >> "$LOGFILE" 2>&1
        log "[DONE] MariaDB verified"

        php8.0 -r "
            \$db = new PDO('sqlite:$PANEL_DIR/data/panel.sqlite');
            \$ver = '';
            exec('mysql --version 2>&1', \$out);
            \$ver = trim(implode(' ', \$out));
            \$db->prepare(\"UPDATE server_components SET status='installed', version=?, installed_at=datetime('now') WHERE component='mariadb'\")->execute([\$ver]);
        "
        log "[COMPLETE] MariaDB installed successfully!"
        ;;

    phpmyadmin)
        log "[1/3] Downloading phpMyAdmin..."
        cd /opt
        wget -q https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.tar.gz >> "$LOGFILE" 2>&1
        if [ $? -eq 0 ]; then
            log "[OK] phpMyAdmin downloaded"
        else
            log "[FAIL] Download failed, trying alternative..."
            wget -q --no-check-certificate https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.tar.gz -O phpMyAdmin-latest-all-languages.tar.gz >> "$LOGFILE" 2>&1
        fi

        log "[2/3] Extracting..."
        rm -rf /opt/phpmyadmin
        tar xzf phpMyAdmin-latest-all-languages.tar.gz >> "$LOGFILE" 2>&1
        mv phpMyAdmin-*-all-languages phpmyadmin 2>/dev/null
        rm -f phpMyAdmin-latest-all-languages.tar.gz
        log "[DONE] Extracted to /opt/phpmyadmin"

        log "[3/3] Configuring..."
        cp /opt/phpmyadmin/config.sample.inc.php /opt/phpmyadmin/config.inc.php 2>/dev/null
        # Generate blowfish secret
        BLOWFISH=$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)
        sed -i "s/\$cfg\['blowfish_secret'\] = ''/\$cfg['blowfish_secret'] = '$BLOWFISH'/" /opt/phpmyadmin/config.inc.php 2>/dev/null
        log "[DONE] phpMyAdmin configured"

        php8.0 -r "
            \$db = new PDO('sqlite:$PANEL_DIR/data/panel.sqlite');
            \$db->prepare(\"UPDATE server_components SET status='installed', version='latest', installed_at=datetime('now') WHERE component='phpmyadmin'\")->execute([]);
        "
        log "[COMPLETE] phpMyAdmin installed! Access at your-server:8088/phpmyadmin (after LiteSpeed setup)"
        ;;

    postfix)
        log "[1/2] Installing Postfix..."
        DEBIAN_FRONTEND=noninteractive apt install -y postfix >> "$LOGFILE" 2>&1
        systemctl enable postfix >> "$LOGFILE" 2>&1
        systemctl restart postfix >> "$LOGFILE" 2>&1
        log "[DONE] Postfix installed"

        log "[2/2] Configuring..."
        postconf -e "inet_interfaces = all" >> "$LOGFILE" 2>&1
        log "[DONE] Postfix configured"

        php8.0 -r "
            \$db = new PDO('sqlite:$PANEL_DIR/data/panel.sqlite');
            \$db->prepare(\"UPDATE server_components SET status='installed', version='installed', installed_at=datetime('now') WHERE component='postfix'\")->execute([]);
        "
        log "[COMPLETE] Postfix installed!"
        ;;

    security)
        log "[1/4] Installing Certbot..."
        apt install -y certbot >> "$LOGFILE" 2>&1
        log "[DONE] Certbot installed"

        log "[2/4] Installing Fail2Ban..."
        apt install -y fail2ban >> "$LOGFILE" 2>&1
        systemctl enable fail2ban >> "$LOGFILE" 2>&1
        systemctl restart fail2ban >> "$LOGFILE" 2>&1
        log "[DONE] Fail2Ban installed and running"

        log "[3/4] Configuring UFW firewall..."
        ufw allow 22/tcp >> "$LOGFILE" 2>&1
        ufw allow 80/tcp >> "$LOGFILE" 2>&1
        ufw allow 443/tcp >> "$LOGFILE" 2>&1
        echo "y" | ufw enable >> "$LOGFILE" 2>&1
        log "[DONE] Firewall configured"

        log "[4/4] Setting up basic Fail2Ban config..."
        cat > /etc/fail2ban/jail.local << 'F2BEOF'
[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 5
bantime = 3600
F2BEOF
        systemctl restart fail2ban >> "$LOGFILE" 2>&1
        log "[DONE] Security configured"

        php8.0 -r "
            \$db = new PDO('sqlite:$PANEL_DIR/data/panel.sqlite');
            \$db->prepare(\"UPDATE server_components SET status='installed', version='certbot+fail2ban', installed_at=datetime('now') WHERE component='security'\")->execute([]);
        "
        log "[COMPLETE] Security suite installed!"
        ;;

    wpcli)
        log "[1/2] Downloading WP-CLI..."
        curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar >> "$LOGFILE" 2>&1
        if [ $? -eq 0 ]; then
            chmod +x wp-cli.phar
            mv wp-cli.phar /usr/local/bin/wp >> "$LOGFILE" 2>&1
            log "[DONE] WP-CLI installed"
        else
            log "[FAIL] WP-CLI download failed"
        fi

        log "[2/2] Verifying..."
        /usr/local/bin/wp --info --allow-root >> "$LOGFILE" 2>&1
        log "[DONE] WP-CLI verified"

        php8.0 -r "
            \$db = new PDO('sqlite:$PANEL_DIR/data/panel.sqlite');
            exec('/usr/local/bin/wp cli version --allow-root 2>&1', \$out);
            \$ver = 'WP-CLI ' . trim(implode('', \$out));
            \$db->prepare(\"UPDATE server_components SET status='installed', version=?, installed_at=datetime('now') WHERE component='wpcli'\")->execute([\$ver]);
        "
        log "[COMPLETE] WP-CLI installed!"
        ;;

    *)
        log "[ERROR] Unknown component: $COMPONENT"
        php8.0 -r "
            \$db = new PDO('sqlite:$PANEL_DIR/data/panel.sqlite');
            \$db->prepare(\"UPDATE server_components SET status='failed' WHERE component=?\")->execute(['$COMPONENT']);
        "
        ;;
esac
