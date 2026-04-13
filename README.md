# WP Hosting Panel

A lightweight, open-source **WordPress-only hosting control panel** powered by **OpenLiteSpeed**, **MariaDB**, and **PHP**.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)
![LiteSpeed](https://img.shields.io/badge/Web%20Server-OpenLiteSpeed-green.svg)
![Ubuntu](https://img.shields.io/badge/OS-Ubuntu%2020.04-orange.svg)

---

## Features

- One-click WordPress installation with progress tracking
- Free SSL certificates via Let's Encrypt (auto-renewal)
- MariaDB database management (create, delete, phpMyAdmin link)
- File manager for site files
- Cron job management
- Multi-user support (Admin, Customer, Reseller)
- PHP version switching (8.0, 8.1, 8.2, 8.3)
- Light / Dark theme UI
- OpenLiteSpeed web server (blazing fast for WordPress)
- Fail2Ban brute-force protection
- Secure JWT-based authentication

---

## Tech Stack

| Component       | Technology              |
|----------------|-------------------------|
| Web Server     | OpenLiteSpeed           |
| PHP Handler    | LSPHP 8.0               |
| Database       | MariaDB 10.5            |
| SSL            | Let's Encrypt / Certbot |
| Backend        | PHP 8.0 (REST API)      |
| Frontend       | PHP + Vanilla JS        |
| Firewall       | Fail2Ban + UFW           |

---

## Requirements

- **Ubuntu 20.04 LTS** (clean VPS)
- **Minimum**: 1 vCPU, 1GB RAM, 20GB SSD
- **Recommended**: 2 vCPU, 2GB RAM, 40GB SSD
- Root / sudo access

---

## Quick Install

Run the setup script on your fresh Ubuntu 20.04 VPS:

```bash
# 1. Download the project
git clone https://github.com/YOUR_USERNAME/wp-panel-php.git /opt/wp-panel
cd /opt/wp-panel

# 2. Copy config
cp config.example.php config.php

# 3. Edit config (set your passwords & secrets)
nano config.php

# 4. Run setup (installs OpenLiteSpeed, MariaDB, PHP, WP-CLI, etc.)
chmod +x scripts/setup.sh
sudo bash scripts/setup.sh

# 5. Access panel
# http://YOUR_SERVER_IP:8080
# Default login: admin@wphosting.com / admin123
```

> **Important**: Change the default admin password immediately after first login!

---

## Setup Script Details

`scripts/setup.sh` installs and configures:

1. **OpenLiteSpeed** - High-performance web server
2. **LSPHP 8.0** - LiteSpeed PHP handler with extensions
3. **MariaDB 10.5** - MySQL-compatible database
4. **WP-CLI** - WordPress command-line tool
5. **Certbot** - Let's Encrypt SSL certificates
6. **Fail2Ban** - Brute-force attack protection
7. **UFW Firewall** - Basic firewall rules
8. **Panel setup** - Creates database, sets permissions

---

## Project Structure

```
wp-panel-php/
├── config.example.php      # Configuration template
├── config.php              # Your actual config (gitignored)
├── index.php               # Entry point
├── .htaccess               # URL rewriting
├── DEPLOY.md               # Detailed deployment guide
│
├── api/                    # Backend REST API
│   ├── auth.php            # Login, register, JWT
│   ├── websites.php        # Create/delete sites
│   ├── wordpress.php       # WP install/management
│   ├── ssl.php             # SSL certificates
│   ├── database.php        # DB management
│   └── php-settings.php    # PHP version switch
│
├── panel/                  # Frontend pages
│   ├── index.php           # Login page
│   ├── dashboard.php       # Dashboard
│   ├── websites.php        # Website list
│   ├── ssl.php             # SSL management
│   ├── database.php        # Database page
│   ├── file-manager.php    # File browser
│   ├── cron-jobs.php       # Cron management
│   ├── php-settings.php    # PHP settings
│   ├── users.php           # User management
│   ├── includes/           # Header, footer, sidebar, auth
│   └── assets/             # CSS & JS
│
├── scripts/                # Server shell scripts
│   ├── setup.sh            # Full server setup
│   ├── create-site.sh      # Create new site
│   ├── remove-site.sh      # Remove site
│   ├── install-wp.sh       # Install WordPress
│   └── install-ssl.sh      # Install SSL cert
│
├── templates/              # Configuration templates
│   └── litespeed-vhost.conf
│
└── prototype.html          # UI prototype / demo
```

---

## Usage

### Add a Website
1. Login to panel
2. Go to **Websites** > **Add Website**
3. Enter domain name
4. Panel creates vhost, database, and directory automatically

### Install WordPress
1. Click **Install WordPress** on any site
2. One-click install with progress animation
3. WordPress installed with secure defaults

### Install SSL
1. Point your domain DNS to server IP
2. Click **Install SSL** on the site
3. Let's Encrypt certificate auto-installed & auto-renews

---

## Security Notes

- Change default admin password immediately
- Use strong `JWT_SECRET` in config.php
- Use strong `DB_PASS` in config.php
- Panel runs behind JWT authentication
- Fail2Ban blocks brute-force login attempts
- File permissions are locked down via setup script

---

## API Endpoints

| Method | Endpoint              | Description        |
|--------|-----------------------|--------------------|
| POST   | `/api/auth.php/login`  | Login              |
| POST   | `/api/auth.php/register` | Register        |
| GET    | `/api/websites.php`    | List websites      |
| POST   | `/api/websites.php`    | Create website     |
| DELETE | `/api/websites.php`    | Delete website     |
| POST   | `/api/wordpress.php/install` | Install WP    |
| POST   | `/api/ssl.php/install` | Install SSL        |
| GET    | `/api/database.php`    | List databases     |
| POST   | `/api/database.php`    | Create database    |

---

## License

MIT License - Free to use, modify, and distribute.

---

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -am 'Add new feature'`)
4. Push to branch (`git push origin feature/my-feature`)
5. Create a Pull Request

---

## Screenshots

See `prototype.html` for a live UI demo of the panel interface.
