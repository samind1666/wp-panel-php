#!/bin/bash
# ============================================================
# WP Hosting Panel - Create Website (OpenLiteSpeed)
# ============================================================
# Usage: bash create-site.sh example.com [php-version]
# PHP version: 80, 81, 82, 83 (default: 80)
# ============================================================

set -e

DOMAIN=$1
PHP_VER=${2:-"80"}

if [ -z "$DOMAIN" ]; then
    echo "Usage: bash create-site.sh <domain> [php-version]"
    echo "Example: bash create-site.sh example.com 80"
    exit 1
fi

# Clean domain
DOMAIN=$(echo "$DOMAIN" | sed 's|https://||' | sed 's|http://||' | sed 's|www.||' | tr '[:upper:]' '[:lower:]')
CLEAN=$(echo "$DOMAIN" | sed 's/\.[^.]*$//')
DOC_ROOT="/home/${CLEAN}/public_html"
HOME_DIR="/home/${CLEAN}"
VHOST_DIR="/usr/local/lsws/conf/vhosts/${DOMAIN}"
VHOST_FILE="${VHOST_DIR}/vhconf.conf"
LISTENER_FILE="/usr/local/lsws/conf/vhosts/${DOMAIN}/listener.xml"
DB_NAME="wp_${CLEAN//[^a-z0-9]/_}"
DB_USER="${CLEAN//[^a-z0-9]/_}_user"
DB_PASS=$(openssl rand -base64 16 | tr -d '/+=' | head -c 16)

echo "============================================"
echo "Creating: $DOMAIN (PHP $PHP_VER)"
echo "============================================"

# 1. Create directories
echo "[1/5] Creating directories..."
mkdir -p "$DOC_ROOT"
mkdir -p "$HOME_DIR/logs"
mkdir -p "$HOME_DIR/backups"
mkdir -p "$VHOST_DIR"
chown -R lsws:lsws "$HOME_DIR"
chmod -R 755 "$HOME_DIR"

# 2. Create LiteSpeed vhost config
echo "[2/5] Configuring OpenLiteSpeed..."
cat > "$VHOST_FILE" << VHEOF
<?xml version="1.0" encoding="UTF-8"?>
<virtualHostConfig>
  <docRoot>${DOC_ROOT}</docRoot>
  <vhRoot>${HOME_DIR}</vhRoot>
  <enableGzip>1</enableGzip>

  <log>
    <fileName>${HOME_DIR}/logs/error.log</fileName>
    <logLevel>ERROR</logLevel>
  </log>

  <accessLog>
    <fileName>${HOME_DIR}/logs/access.log</fileName>
    <logFormat>%h %l %u %t "%r" %>s %b "%{Referer}i" "%{User-Agent}i" %T</logFormat>
  </accessLog>

  <index>
    <useServer>0</useServer>
    <files>index.php index.html</files>
  </index>

  <rewrite>
    <enable>1</enable>
    <autoLoadHtaccess>1</autoLoadHtaccess>
  </rewrite>

  <context>
    <type>fcgi</type>
    <uri>/</uri>
    <handler>
      <type>lsapi:lsphp${PHP_VER}</type>
    </handler>
  </context>

  <context>
    <type>NULL</type>
    <uri>/wp-config.php</uri>
    <accessControl>
      <deny>ALL</deny>
    </accessControl>
  </context>

  <context>
    <type>NULL</type>
    <uri>/xmlrpc.php</uri>
    <accessControl>
      <deny>ALL</deny>
    </accessControl>
  </context>

  <context>
    <type>NULL</type>
    <uri>~ /\.(.*)</uri>
    <accessControl>
      <deny>ALL</deny>
    </accessControl>
  </context>

  <scriptHandler>
    <add>
      <extension>php</extension>
      <handler>lsapi:lsphp${PHP_VER}</handler>
    </add>
  </scriptHandler>

  <phpIniOverride>
    <memory_limit>256M</memory_limit>
    <upload_max_filesize>64M</upload_max_filesize>
    <post_max_size>128M</post_max_size>
    <max_execution_time>300</max_execution_time>
    <max_input_vars>3000</max_input_vars>
    <display_errors>Off</display_errors>
  </phpIniOverride>

  <maxConns>200</maxConns>
  <maxReqBodySize>67108864</maxReqBodySize>
  <instantiateOnStartup>1</instantiateOnStartup>
</virtualHostConfig>
VHEOF

# 3. Add vhost to main config (if not already added)
MAIN_CONF="/usr/local/lsws/conf/httpd_config.xml"
if ! grep -q "${DOMAIN}" "$MAIN_CONF" 2>/dev/null; then
    # Add listener for domain on port 80
    python3 << PYEOF
import xml.etree.ElementTree as ET
import re

conf_file = "${MAIN_CONF}"
with open(conf_file, 'r') as f:
    content = f.read()

# Add virtualHost entry if not exists
vhost_entry = f'''    <virtualHost>
      <name>${DOMAIN}</name>
      <vhMap>${VHOST_DIR}</vhMap>
    </virtualHost>'''

# Add before </virtualHostList>
if '</virtualHostList>' in content and '${DOMAIN}' not in content:
    content = content.replace('</virtualHostList>', vhost_entry + '\n  </virtualHostList>')

with open(conf_file, 'w') as f:
    f.write(content)
print("Vhost added to main config")
PYEOF
fi

# 4. Create database
echo "[3/5] Creating database..."
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
mysql -e "CREATE USER IF NOT EXISTS \`${DB_USER}\`@'localhost' IDENTIFIED BY '${DB_PASS}';" 2>/dev/null
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO \`${DB_USER}\`@'localhost';" 2>/dev/null
mysql -e "FLUSH PRIVILEGES;" 2>/dev/null

# 5. Create placeholder & restart
echo "[4/5] Creating placeholder page..."
cat > "${DOC_ROOT}/index.html" << HTMLEOF
<!DOCTYPE html>
<html>
<head>
    <title>${DOMAIN} - Ready</title>
    <style>
        body { font-family: -apple-system, sans-serif; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; }
        .box { text-align: center; padding: 40px; }
        h1 { color: #38bdf8; margin-bottom: 10px; font-size: 2em; }
        p { color: #94a3b8; font-size: 1.1em; }
        .badge { display: inline-block; background: #22c55e; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 0.85em; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>${DOMAIN}</h1>
        <p>Site is ready! Install WordPress from WP Hosting Panel.</p>
        <div class="badge">OpenLiteSpeed + LSPHP</div>
    </div>
</body>
</html>
HTMLEOF

chown -R lsws:lsws "$DOC_ROOT"

# Restart LiteSpeed
echo "[5/5] Restarting LiteSpeed..."
/usr/local/lsws/bin/lswsctrl restart > /dev/null 2>&1 || true

echo ""
echo "============================================"
echo "  Website Created!"
echo "============================================"
echo "  Domain:   $DOMAIN"
echo "  DocRoot:  $DOC_ROOT"
echo "  Database: $DB_NAME"
echo "  DB User:  $DB_USER"
echo "  DB Pass:  $DB_PASS"
echo "  PHP:      lsphp${PHP_VER}"
echo "============================================"
