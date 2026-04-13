#!/bin/bash
# ============================================================
# WP Hosting Panel - Install WordPress (Non-Interactive)
# ============================================================
# Usage: bash install-wp.sh example.com [title] [admin-user] [admin-pass] [admin-email]
# ============================================================

set -e

DOMAIN=$1
SITE_TITLE=${2:-"My WordPress Site"}
ADMIN_USER=${3:-"admin"}
ADMIN_PASS=${4:-$(openssl rand -base64 12 | tr -d '/+=' | head -c 12)}
ADMIN_EMAIL=${5:-"admin@${DOMAIN}"}

if [ -z "$DOMAIN" ]; then
    echo "Usage: bash install-wp.sh <domain> [title] [admin-user] [admin-pass] [admin-email]"
    echo "Example: bash install-wp.sh example.com"
    exit 1
fi

DOMAIN=$(echo "$DOMAIN" | sed 's|https://||' | sed 's|http://||' | sed 's|www.||' | tr '[:upper:]' '[:lower:]')
CLEAN=$(echo "$DOMAIN" | sed 's/\.[^.]*$//')
DOC_ROOT="/home/${CLEAN}/public_html"
DB_NAME="wp_${CLEAN//[^a-z0-9]/_}"

# Get DB password from websites table
DB_PASS=$(mysql -N -e "SELECT db_password FROM wp_panel.websites WHERE domain='${DOMAIN}';" 2>/dev/null || echo "")
DB_USER="${CLEAN//[^a-z0-9]/_}_user"

if [ -z "$DB_PASS" ]; then
    echo "Error: DB password not found for $DOMAIN. Make sure site was created from panel."
    exit 1
fi

if [ ! -d "$DOC_ROOT" ]; then
    echo "Error: Directory $DOC_ROOT not found. Run create-site.sh first."
    exit 1
fi

echo "============================================"
echo "Installing WordPress: $DOMAIN"
echo "============================================"

# 1. Download WordPress
echo "[1/5] Downloading WordPress..."
wp core download --path="$DOC_ROOT" --version=latest --allow-root --quiet

# 2. Create wp-config.php
echo "[2/5] Creating wp-config.php..."
wp config create \
    --path="$DOC_ROOT" \
    --dbname="$DB_NAME" \
    --dbuser="$DB_USER" \
    --dbpass="$DB_PASS" \
    --dbhost="localhost" \
    --allow-root

# 3. Install WordPress
echo "[3/5] Installing WordPress..."
wp core install \
    --path="$DOC_ROOT" \
    --url="https://${DOMAIN}" \
    --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" \
    --allow-root

# 4. Theme + Plugins
echo "[4/5] Installing theme & plugins..."
wp theme install twentytwentyfour --activate --path="$DOC_ROOT" --allow-root 2>/dev/null || true

for plugin in wp-super-cache wordfence updraftplus; do
    wp plugin install "$plugin" --activate --path="$DOC_ROOT" --allow-root 2>/dev/null && echo "  $plugin ✓" || true
done

# 5. Permalinks
echo "[5/5] Setting permalinks..."
wp rewrite structure '/%postname%/' --path="$DOC_ROOT" --allow-root
wp rewrite flush --path="$DOC_ROOT" --allow-root

# Permissions
chown -R lsws:lsws "$DOC_ROOT"
find "$DOC_ROOT" -type d -exec chmod 755 {} \;
find "$DOC_ROOT" -type f -exec chmod 644 {} \;

# WP version
WP_VER=$(wp core version --path="$DOC_ROOT" --allow-root 2>/dev/null || echo "latest")

echo ""
echo "============================================"
echo "  WordPress Installed!"
echo "============================================"
echo "  URL:       https://${DOMAIN}"
echo "  Admin:     https://${DOMAIN}/wp-admin"
echo "  User:      $ADMIN_USER"
echo "  Password:  $ADMIN_PASS"
echo "  Version:   $WP_VER"
echo "============================================"
