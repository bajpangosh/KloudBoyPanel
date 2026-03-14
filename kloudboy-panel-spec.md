
# KloudBoy Panel – Full Specification

High-performance hosting control panel optimized for WordPress and WooCommerce using OpenLiteSpeed.

Goal: Build a lightweight, secure, and ultra-fast alternative to CyberPanel focused on WordPress hosting.

---

# 1. Project Goals

KloudBoy Panel should:

- Manage multiple WordPress hosting sites
- Use OpenLiteSpeed as the web server
- Provide per-site isolation
- Support multiple PHP versions
- Use domain-based database naming
- Provide automated WordPress installation
- Provide a lightweight modern dashboard
- Be optimized for high-traffic WooCommerce hosting

---

# 2. Technology Stack

## Backend
- Go (Golang)
- Gin Web Framework
- SQLite database

## Frontend
- Vue 3
- TailwindCSS
- Axios

## Server Stack
- Ubuntu 24
- OpenLiteSpeed
- PHP LSAPI
- MariaDB
- Redis

---

# 3. Core System Architecture

Internet
   |
Cloudflare CDN
   |
OpenLiteSpeed
   |
PHP LSAPI Pools
   |
Redis Cache
   |
MariaDB

Panel Control Layer

KloudBoy Panel
   |
Automation Engine
   |
Linux + OpenLiteSpeed + MariaDB

---

# 4. Directory Structure

/opt/kloudboy
/home/sites
/backups
/logs

Example site:

/home/sites/example.com
/home/sites/example.com/public_html
/home/sites/example.com/logs

---

# 5. Panel Access

https://panel.example.com:8443/kb-admin-random/

Features:
- custom panel domain
- custom port
- hidden path for security

Example:

https://panel.example.com:8443/kb-admin-a83fj/

---

# 6. Dashboard Pages

Dashboard
Websites
Databases
Backups
PHP Manager
WordPress Toolkit
Performance
Server
Security
Logs
Cron Manager
Panel Configuration
Updates
API Tokens

---

# 7. Dashboard Overview

Widgets:
- CPU usage
- RAM usage
- Disk usage
- Server uptime
- Website count
- Database count
- Backup status

Quick actions:
- Create Website
- Create Database
- Backup Now
- Restart Services
- Run Malware Scan

---

# 8. Website Manager

Columns:

Domain
PHP Version
Disk Usage
CPU Usage
Status
Actions

Actions:
- Create site
- Delete site
- Change PHP version
- Enable Redis
- Enable SSL
- View logs
- Create staging
- Backup site

---

# 9. Website Creation Engine

API endpoint:

POST /api/sites/create

Workflow:

1 generate Linux user
2 create website directory
3 set file permissions
4 create database
5 create database user
6 generate OpenLiteSpeed vhost
7 assign PHP handler
8 issue SSL certificate
9 install WordPress
10 enable Redis cache

---

# 10. Database Manager

Naming format:

example_com_wp
example_com_usr

Features:

- Create database
- Reset password
- Delete database
- Backup database
- Access Adminer

Panel database:

kloudboy

Tables:

admins
sites
databases
backups
api_tokens
server_settings

---

# 11. PHP Manager

Supported versions:

PHP 8.1
PHP 8.2
PHP 8.3

Features:

- Install PHP version
- Enable extensions
- Restart PHP
- Change memory limit
- Change upload limit

Per-site PHP version switching.

---

# 12. WordPress Toolkit

Per-site tools:

- Install WordPress
- Update WordPress
- Update plugins
- Update themes
- Run WP-CLI commands
- Clear LSCache
- Flush Redis
- Enable staging

Example commands:

wp plugin update --all
wp cache flush

---

# 13. Performance Optimization Engine

Performance options:

- Enable Redis Object Cache
- Enable LSCache
- Enable Brotli compression
- Enable HTTP/3
- Tune OPcache
- Tune PHP workers
- Tune MariaDB buffers

Templates:

Standard WordPress
WooCommerce optimized
High-traffic site

---

# 14. Security Center

Features:

- Firewall management
- Fail2Ban monitoring
- Block IP addresses
- Malware scanning
- WordPress login protection
- File integrity monitoring

Alerts:

- Malware detected
- Brute force attempt
- High CPU usage
- Disk full warning

---

# 15. Resource Monitoring

Per-site metrics:

- CPU usage
- RAM usage
- Disk usage
- Bandwidth usage

Server metrics:

- Load average
- Network traffic
- Process list

---

# 16. Backup System

Backup types:

- Manual
- Scheduled
- Remote

Backup includes:

files
database

Backup path:

/backups/domain/date.tar.gz

Remote storage:

- S3
- Backblaze
- FTP
- Object Storage

---

# 17. Staging System

Create staging site:

example.com -> staging.example.com

Workflow:

1 Clone files
2 Clone database
3 Create new vhost
4 Disable search indexing

---

# 18. Cron Manager

Manage scheduled tasks.

Examples:

- WordPress cron
- Backup cron
- Custom commands

---

# 19. Server Manager

Manage services:

OpenLiteSpeed
MariaDB
Redis
KloudBoy Panel

Controls:

Start
Stop
Restart

System info:

CPU
RAM
Disk
Load

---

# 20. Logs Manager

Log sources:

OpenLiteSpeed logs
Site access logs
PHP logs
Panel logs

Paths:

/usr/local/lsws/logs
/home/sites/domain/logs
/opt/kloudboy/logs

---

# 21. Panel Configuration

Settings:

- Panel domain
- Panel port
- Hidden login path
- Server hostname
- Timezone
- Backup location

---

# 22. Update System

Command:

kloudboy update

Update workflow:

1 Check latest version
2 Backup panel
3 Download update
4 Apply update
5 Restart services

---

# 23. CLI Tool

Commands:

kloudboy site create
kloudboy site delete
kloudboy backup run
kloudboy update
kloudboy doctor

Doctor checks:

OpenLiteSpeed
MariaDB
Redis
Disk space
Ports

---

# 24. API System

Endpoints:

POST /api/site/create
POST /api/site/delete
POST /api/backup
POST /api/php/change
GET /api/server/status

Authentication:

JWT tokens
API keys

---

# 25. Installer Script

curl -s https://install.kloudboy.com | bash

Installer actions:

1 Install OpenLiteSpeed
2 Install PHP
3 Install MariaDB
4 Install Redis
5 Install KloudBoy Panel
6 Generate admin credentials
7 Generate SSL certificate
8 Configure firewall

Credentials saved to:

/root/kloudboy-login.txt

---

# 26. System Service

/etc/systemd/system/kloudboy.service

Commands:

systemctl enable kloudboy
systemctl start kloudboy

---

# 27. Security Isolation

Each site must have:

- separate Linux user
- separate PHP process
- separate database

Permissions:

folders 755
files 644
wp-config.php 600

---

# 28. Future Multi-Server Architecture

Main Panel
   |
Agent Server 1
Agent Server 2
Agent Server 3

Allows hosting:

1000+ WordPress sites

---

# 29. Development Phases

Phase 1
- Installer
- Site creation engine
- Database manager
- OpenLiteSpeed automation

Phase 2
- Dashboard UI
- PHP manager
- Backup system

Phase 3
- Security tools
- Monitoring
- Staging system

Phase 4
- Multi-server cluster
- Central management

---

# Final Goal

Within 5 minutes of installation, a user should be able to:

- open dashboard
- create WordPress site
- manage databases
- monitor server
- backup sites
- secure hosting environment
