#!/bin/bash
# ============================================================
# WP Hosting Panel - Install SSL (OpenLiteSpeed + Let's Encrypt)
# ============================================================
# Usage: bash install-ssl.sh example.com [email]
# ============================================================

set -e

DOMAIN=$1
EMAIL=${2:-"admin@${DOMAIN}"}

if [ -z "$DOMAIN" ]; then
    echo "Usage: bash install-ssl.sh <domain> [email]"
    exit 1
fi

DOMAIN=$(echo "$DOMAIN" | sed 's|https://||' | sed 's|http://||' | sed 's|www.||' | tr '[:upper:]' '[:lower:]')
VHOST_DIR="/usr/local/lsws/conf/vhosts/${DOMAIN}"
VHOST_FILE="${VHOST_DIR}/vhconf.conf"

if [ ! -f "$VHOST_FILE" ]; then
    echo "Error: Vhost for ${DOMAIN} not found. Create website first."
    exit 1
fi

echo "============================================"
echo "Installing SSL for: $DOMAIN"
echo "============================================"

# 1. Get certificate
echo "[1/3] Getting SSL certificate..."
certbot certonly --webroot \
    -w "/usr/local/lsws/htdocs" \
    -d "$DOMAIN" \
    -d "www.${DOMAIN}" \
    --non-interactive \
    --agree-tos \
    -m "$EMAIL" 2>&1 || {
    echo "Webroot failed, trying standalone..."
    /usr/local/lsws/bin/lswsctrl stop > /dev/null 2>&1 || true
    certbot certonly --standalone \
        -d "$DOMAIN" \
        -d "www.${DOMAIN}" \
        --non-interactive \
        --agree-tos \
        -m "$EMAIL" 2>&1
    /usr/local/lsws/bin/lswsctrl start > /dev/null 2>&1 || true
}

# 2. Add SSL to vhost config
echo "[2/3] Configuring SSL in OpenLiteSpeed..."
if [ -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]; then
    # Insert SSL block before closing tag
    python3 << PYEOF
import re

vhost_file = "${VHOST_FILE}"
with open(vhost_file, 'r') as f:
    content = f.read()

ssl_block = '''
  <vhssl>
    <keyFile>/etc/letsencrypt/live/${DOMAIN}/privkey.pem</keyFile>
    <certFile>/etc/letsencrypt/live/${DOMAIN}/fullchain.pem</certFile>
    <certChain>/etc/letsencrypt/live/${DOMAIN}/chain.pem</certChain>
    <sslProtocol>TLSv1.2 TLSv1.3</sslProtocol>
    <enableECDHE>1</enableECDHE>
    <renegProtection>1</renegProtection>
    <sslSessionCache>1</sslSessionCache>
    <enableH2>1</enableH2>
  </vhssl>

  <header>
    <add>Strict-Transport-Security "max-age=31536000; includeSubDomains" always</add>
  </header>'''

if '<vhssl>' not in content:
    content = content.replace('</virtualHostConfig>', ssl_block + '\n</virtualHostConfig>')

with open(vhost_file, 'w') as f:
    f.write(content)
print("SSL config added")
PYEOF
else
    echo "Error: Certificate files not found!"
    exit 1
fi

# 3. Restart LiteSpeed
echo "[3/3] Restarting OpenLiteSpeed..."
/usr/local/lsws/bin/lswsctrl restart > /dev/null 2>&1 || true

# Get expiry
EXPIRY=""
if [ -f "/etc/letsencrypt/live/${DOMAIN}/cert.pem" ]; then
    EXPIRY=$(openssl x509 -in "/etc/letsencrypt/live/${DOMAIN}/cert.pem" -noout -enddate 2>/dev/null | cut -d= -f2)
fi

echo ""
echo "============================================"
echo "  SSL Installed!"
echo "============================================"
echo "  Domain:  $DOMAIN"
echo "  HTTPS:   https://${DOMAIN}"
echo "  Expires: $EXPIRY"
echo "  Auto-renew: Active"
echo "============================================"
