# WP Hosting Panel - OpenLiteSpeed Deployment Guide

## Prerequisites

- **OS**: Ubuntu 20.04 LTS or 22.04 LTS
- **Access**: Root SSH access
- **Domain**: A domain name pointing to your server's public IP address
- **RAM**: Minimum 1GB (2GB+ recommended)
- **Disk**: 10GB+ SSD

---

## Step 1: Initial Server Setup (One Command)

```bash
# SSH into your server
ssh root@YOUR_SERVER_IP

# Download and run the setup script
cd /tmp
wget https://your-domain.com/scripts/setup.sh
# OR copy the setup.sh content and save it
bash setup.sh
```

**This script automatically installs:**
- OpenLiteSpeed web server
- MariaDB database server
- LSPHP (lsphp80, lsphp81, lsphp82, lsphp83)
- WP-CLI (WordPress management)
- Certbot (Let's Encrypt SSL)
- Fail2Ban (Brute force protection)
- UFW Firewall
- PHP performance tuning for LiteSpeed

**Key changes from previous Nginx setup:**
- **OpenLiteSpeed** replaces Nginx as the web server
- **LSPHP** replaces php-fpm for PHP processing ( LiteSpeed's built-in PHP handler )
- No separate PHP-FPM service to manage — LiteSpeed handles it natively
- Built-in HTTP/3 support out of the box

**Note the DB_PASSWORD shown at the end of setup — you'll need it!**

---

## Step 2: Upload Panel Files

```bash
# Copy all panel files to the server
scp -r wp-panel-php/ root@YOUR_SERVER_IP:/var/www/wp-panel/

# Set permissions
chown -R www-data:www-data /var/www/wp-panel/
chmod -R 755 /var/www/wp-panel/
chmod 777 /var/www/wp-panel/logs/
```

---

## Step 3: Configure Panel

```bash
# Edit config.php with your database password
nano /var/www/wp-panel/config.php

# Change these values:
define('DB_PASS', 'YOUR_DB_PASSWORD_FROM_STEP1');
define('PANEL_URL', 'http://YOUR_SERVER_IP:8080');
define('JWT_SECRET', 'GENERATE_A_RANDOM_STRING_32_CHARS');
```

---

## Step 4: Access the Panel

```
Open browser: http://YOUR_SERVER_IP:8080
```

### Default Login Credentials
- **Email**: admin@wphosting.com
- **Password**: admin123

**IMPORTANT: Change the default password immediately after first login!**

---

## Port Information

| Port | Service |
|------|---------|
| 80 | HTTP (websites) |
| 443 | HTTPS (websites) |
| 7080 | LiteSpeed WebAdmin Console |
| 8080 | WP Hosting Panel |

---

## LiteSpeed WebAdmin Console

LiteSpeed includes its own admin panel for advanced server configuration:

```
Open browser: https://YOUR_SERVER_IP:7080
```

- **Default Username**: admin
- **Default Password**: Set during installation (or use `lsws/admin/conf/admin_passwd.key`)

> The WebAdmin console lets you configure virtual hosts, PHP versions, SSL certificates, caching, and performance tuning directly.

---

## Step 5: Create Websites

### Via the Panel UI

1. Log into the WP Hosting Panel at `http://YOUR_SERVER_IP:8080`
2. Go to **Websites** in the sidebar
3. Click **Create Website**
4. Enter your domain name and select the PHP version
5. The panel creates the virtual host configuration for OpenLiteSpeed automatically

### Via API

```bash
# Get auth token
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@wphosting.com","password":"admin123"}' \
  | jq -r '.token')

# Create website
curl -X POST http://localhost:8080/api/websites?action=create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "domain": "example.com",
    "php_version": "8.2"
  }'
```

### Via Shell Script

```bash
# Create website
bash /var/www/wp-panel/scripts/create-site.sh example.com

# Remove website
bash /var/www/wp-panel/scripts/remove-site.sh example.com
```

---

## Step 6: Install WordPress

### Via the Panel UI

1. Go to **Websites** in the sidebar
2. Find your website and click the **WP** button (Install WordPress)
3. Fill in the site details (title, admin username, password, email)
4. Click **Install**

### Via API

```bash
curl -X POST http://localhost:8080/api/wordpress?action=install \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "website_id": "WEBSITE_ID_FROM_CREATE",
    "site_title": "My Awesome Blog",
    "admin_user": "admin",
    "admin_email": "me@example.com",
    "admin_password": "SecurePassword123!",
    "theme": "astra",
    "plugins": ["woocommerce", "yoast-seo", "litespeed-cache"]
  }'
```

### Via Shell Script

```bash
bash /var/www/wp-panel/scripts/install-wp.sh example.com "My Site" admin MyPass admin@example.com astra
```

> **Tip**: Install the **LiteSpeed Cache** plugin for maximum performance. It is specifically designed for OpenLiteSpeed and provides server-level page caching.

---

## Step 7: Install SSL Certificate

### Via the Panel UI

1. Go to **Websites** in the sidebar
2. Find your website and click the **SSL** button
3. The panel requests a free Let's Encrypt SSL certificate automatically

### Via API

```bash
curl -X POST http://localhost:8080/api/ssl?action=install \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "website_id": "WEBSITE_ID",
    "email": "admin@example.com"
  }'
```

### Via Shell Script

```bash
bash /var/www/wp-panel/scripts/install-ssl.sh example.com admin@example.com
```

---

## Step 8: Create Databases

### Via the Panel UI

1. Go to **Database** in the sidebar
2. Click **Create Database**
3. Enter database name, user, and password
4. Click **Create**

### Via API

```bash
curl -X POST http://localhost:8080/api/database?action=create \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "website_id": "WEBSITE_ID",
    "db_name": "my_database",
    "db_user": "my_dbuser",
    "db_password": "SecurePassword123!"
  }'
```

---

## Connect Frontend (Next.js)

1. Build the Next.js frontend:
```bash
cd wp-hosting-panel-frontend
npm run build
```

2. Copy build output:
```bash
cp -r .next/standalone/* /var/www/wp-panel/public/
```

3. Update frontend to call the PHP API:
   - Replace `useAppStore()` calls with `fetch('/api/...')`
   - Add `Authorization: Bearer <token>` header
   - Handle real API responses

---

## Security Checklist

- [ ] Change default admin password
- [ ] Change JWT_SECRET in config.php
- [ ] Change DB_PASS in config.php
- [ ] Change LiteSpeed WebAdmin password (port 7080)
- [ ] Enable UFW firewall (done by setup.sh)
- [ ] Enable Fail2Ban (done by setup.sh)
- [ ] Remove phpinfo.php after testing
- [ ] Set up regular database backups
- [ ] Monitor SSL auto-renewal (certbot renew --dry-run)
- [ ] Keep system updated: `apt update && apt upgrade`

---

## Troubleshooting

### OpenLiteSpeed not starting

```bash
# Test configuration
/usr/local/lsws/bin/lswsctrl -t

# Check status
/usr/local/lsws/bin/lswsctrl status

# Restart OpenLiteSpeed
/usr/local/lsws/bin/lswsctrl restart

# View error logs
tail -f /usr/local/lsws/logs/error.log
```

### LSPHP not working

```bash
# Check installed LSPHP versions
ls -la /usr/local/lsws/fcgi-bin/

# Verify the correct LSPHP binary exists
/usr/local/lsws/fcgi-bin/lsphp82 -v

# Test PHP execution manually
echo "<?php phpinfo(); ?>" | /usr/local/lsws/fcgi-bin/lsphp82
```

### LiteSpeed Admin Panel (7080) not accessible

```bash
# Check if the admin port is listening
ss -tlnp | grep 7080

# Ensure the firewall allows port 7080
ufw allow 7080/tcp

# Restart LiteSpeed
/usr/local/lsws/bin/lswsctrl restart
```

### WordPress installation fails

```bash
# Check WP-CLI
wp cli info --allow-root

# Check file permissions (LiteSpeed requires the 'nobody' user by default)
ls -la /home/example.com/public_html/
chown -R nobody:nobody /home/example.com/public_html/

# If using the panel user, ensure correct ownership
chown -R www-data:www-data /home/example.com/
```

### SSL certificate fails

```bash
# Verify domain DNS points to server IP
dig example.com +short

# Ensure port 80 is open for ACME challenge
ufw allow 80/tcp

# Try standalone mode
certbot certonly --standalone -d example.com -d www.example.com

# If behind LiteSpeed, use webroot mode
certbot certonly --webroot -w /home/example.com/public_html -d example.com
```

### Database connection fails

```bash
# Test connection
mysql -u wp_panel -p
SHOW DATABASES;

# Check user permissions
mysql -e "SELECT user, host FROM mysql.user;"

# Restart MariaDB
systemctl restart mariadb
```

### 503 Service Unavailable errors

```bash
# Check if LiteSpeed is running
/usr/local/lsws/bin/lswsctrl status

# Check if the backend (LSPHP) is configured correctly in the virtual host
# Go to WebAdmin > Virtual Hosts > Your Domain > External App

# Check for memory issues
free -m
# Increase swap if needed
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
```

---

## API Endpoints Reference

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/auth/login | Login |
| POST | /api/auth/register | Create user (admin) |
| GET | /api/auth/me | Current user info |
| GET | /api/websites?action=list | List websites |
| POST | /api/websites?action=create | Create website |
| POST | /api/websites?action=delete | Delete website |
| GET | /api/websites?action=stats | Server stats |
| POST | /api/wordpress?action=install | Install WordPress |
| POST | /api/wordpress?action=uninstall | Remove WordPress |
| GET | /api/wordpress?action=check | Check WP status |
| POST | /api/ssl?action=install | Install SSL |
| GET | /api/ssl?action=list | List certificates |
| POST | /api/ssl?action=renew | Renew certificate |
| POST | /api/database?action=create | Create database |
| POST | /api/database?action=delete | Delete database |
| GET | /api/database?action=list | List databases |
| POST | /api/php-settings?action=get | Get PHP settings |
| POST | /api/php-settings?action=update | Update PHP version |
| GET | /api/php-settings?action=versions | List PHP versions |
