#!/bin/bash
# ============================================================
# WP Hosting Panel - Remove Website (OpenLiteSpeed)
# ============================================================
# Usage: bash remove-site.sh example.com
# ============================================================

set -e

DOMAIN=$1

if [ -z "$DOMAIN" ]; then
    echo "Usage: bash remove-site.sh <domain>"
    exit 1
fi

DOMAIN=$(echo "$DOMAIN" | sed 's|https://||' | sed 's|http://||' | sed 's|www.||' | tr '[:upper:]' '[:lower:]')
CLEAN=$(echo "$DOMAIN" | sed 's/\.[^.]*$//')
HOME_DIR="/home/${CLEAN}"
VHOST_DIR="/usr/local/lsws/conf/vhosts/${DOMAIN}"
DB_NAME="wp_${CLEAN//[^a-z0-9]/_}"
DB_USER="${CLEAN//[^a-z0-9]/_}_user"

echo "============================================"
echo "WARNING: Deleting $DOMAIN"
echo "============================================"
read -p "Type 'DELETE' to confirm: " CONFIRM

if [ "$CONFIRM" != "DELETE" ]; then
    echo "Cancelled."
    exit 0
fi

# 1. Remove SSL
echo "[1/4] Removing SSL..."
certbot delete --cert-name "$DOMAIN" --non-interactive 2>/dev/null || true

# 2. Remove vhost config
echo "[2/4] Removing OpenLiteSpeed config..."
rm -rf "$VHOST_DIR"

# Remove from main config
python3 << PYEOF
conf_file = "/usr/local/lsws/conf/httpd_config.xml"
with open(conf_file, 'r') as f:
    content = f.read()

import re
# Remove virtualHost block for this domain
pattern = r'\s*<virtualHost>\s*<name>' + re.escape('${DOMAIN}') + r'</name>.*?</virtualHost>'
content = re.sub(pattern, '', content, flags=re.DOTALL)

with open(conf_file, 'w') as f:
    f.write(content)
print("Vhost removed from main config")
PYEOF

# 3. Remove files
echo "[3/4] Removing files..."
if [ -d "$HOME_DIR" ] && [[ "$HOME_DIR" == /home/* ]]; then
    rm -rf "$HOME_DIR"
fi

# 4. Remove database
echo "[4/4] Removing database..."
mysql -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;" 2>/dev/null
mysql -e "DROP USER IF EXISTS \`${DB_USER}\`@'localhost';" 2>/dev/null
mysql -e "FLUSH PRIVILEGES;" 2>/dev/null

# Restart
/usr/local/lsws/bin/lswsctrl restart > /dev/null 2>&1 || true

echo ""
echo "============================================"
echo "  $DOMAIN deleted!"
echo "============================================"
