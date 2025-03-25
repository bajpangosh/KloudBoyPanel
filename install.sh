#!/bin/bash
#
# WordPress Hosting Panel with LiteSpeed for Ubuntu 22.04
# Created by Roo
#

# Set terminal colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
RESET='\033[0m'

# Function to print colored messages
print_message() {
    case $1 in
        "info") echo -e "${BLUE}[INFO]${RESET} $2" ;;
        "success") echo -e "${GREEN}[SUCCESS]${RESET} $2" ;;
        "warning") echo -e "${YELLOW}[WARNING]${RESET} $2" ;;
        "error") echo -e "${RED}[ERROR]${RESET} $2" ;;
        *) echo -e "$2" ;;
    esac
}

# Function to check if script is run as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_message "error" "This script must be run as root"
        exit 1
    fi
}

# Function to check system requirements
check_system() {
    print_message "info" "Checking system requirements..."
    
    # Check if Ubuntu 22.04
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        if [[ "$ID" != "ubuntu" || "$VERSION_ID" != "22.04" ]]; then
            print_message "error" "This script requires Ubuntu 22.04"
            exit 1
        fi
    else
        print_message "error" "Cannot determine OS version"
        exit 1
    fi
    
    # Check minimum system requirements
    CPU_CORES=$(nproc)
    TOTAL_RAM=$(free -m | awk '/^Mem:/{print $2}')
    DISK_SPACE=$(df -m / | awk 'NR==2 {print $4}')
    
    if [[ $CPU_CORES -lt 2 ]]; then
        print_message "warning" "Recommended minimum: 2 CPU cores. Found: $CPU_CORES"
    else
        print_message "success" "CPU cores: $CPU_CORES"
    fi
    
    if [[ $TOTAL_RAM -lt 2048 ]]; then
        print_message "warning" "Recommended minimum: 2GB RAM. Found: $TOTAL_RAM MB"
    else
        print_message "success" "RAM: $TOTAL_RAM MB"
    fi
    
    if [[ $DISK_SPACE -lt 10240 ]]; then
        print_message "warning" "Recommended minimum: 10GB free disk space. Found: $DISK_SPACE MB"
    else
        print_message "success" "Disk space: $DISK_SPACE MB"
    fi
}

# Function to update system
update_system() {
    print_message "info" "Updating system packages..."
    apt update && apt upgrade -y
    
    print_message "info" "Installing required packages..."
    apt install -y curl wget unzip tar software-properties-common apt-transport-https ca-certificates gnupg lsb-release
}

# Function to install and configure MariaDB
install_mariadb() {
    print_message "info" "Installing MariaDB..."
    
    # Install MariaDB
    apt install -y mariadb-server
    
    # Secure MariaDB installation
    print_message "info" "Securing MariaDB installation..."
    
    # Generate a random root password
    MARIADB_ROOT_PASSWORD=$(openssl rand -base64 12)
    
    # Save MariaDB root password
    echo "MariaDB Root Password: $MARIADB_ROOT_PASSWORD" > /root/mysql_credentials.txt
    chmod 600 /root/mysql_credentials.txt
    
    # Secure MariaDB
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$MARIADB_ROOT_PASSWORD';"
    mysql -e "DELETE FROM mysql.user WHERE User='';"
    mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    mysql -e "DROP DATABASE IF EXISTS test;"
    mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
    mysql -e "FLUSH PRIVILEGES;"
    
    print_message "success" "MariaDB installed and secured"
    print_message "info" "MariaDB root password saved to /root/mysql_credentials.txt"
}

# Function to install LiteSpeed Web Server
install_litespeed() {
    print_message "info" "Installing LiteSpeed Web Server..."
    
    # Add LiteSpeed repository
    wget -O - https://rpms.litespeedtech.com/debian/enable_lst_debian_repo.sh | bash
    
    # Install OpenLiteSpeed
    apt install -y openlitespeed
    
    # Install PHP for OpenLiteSpeed
    apt install -y lsphp81 lsphp81-common lsphp81-mysql lsphp81-opcache lsphp81-curl lsphp81-json lsphp81-imagick lsphp81-redis lsphp81-memcached
    
    # Create symbolic links for PHP
    ln -sf /usr/local/lsws/lsphp81/bin/lsphp /usr/local/lsws/fcgi-bin/lsphp81
    
    # Update LiteSpeed configuration
    sed -i 's/user nobody/user www-data/g' /usr/local/lsws/conf/httpd_config.conf
    sed -i 's/group nobody/group www-data/g' /usr/local/lsws/conf/httpd_config.conf
    
    # Set LiteSpeed admin password
    LSWS_ADMIN_PASSWORD=$(openssl rand -base64 12)
    echo "LiteSpeed Admin Password: $LSWS_ADMIN_PASSWORD" >> /root/mysql_credentials.txt
    
    # Update LiteSpeed admin password
    echo "admin:$LSWS_ADMIN_PASSWORD" | /usr/local/lsws/admin/fcgi-bin/admin_php -r
    
    # Restart LiteSpeed
    /usr/local/lsws/bin/lswsctrl restart
    
    print_message "success" "LiteSpeed Web Server installed"
    print_message "info" "LiteSpeed admin password saved to /root/mysql_credentials.txt"
    print_message "info" "LiteSpeed admin URL: https://YOUR_SERVER_IP:7080"
}

# Function to install and configure PHP
install_php() {
    print_message "info" "Configuring PHP for optimal performance..."
    
    # Create PHP configuration directory if it doesn't exist
    mkdir -p /usr/local/lsws/lsphp81/etc/php/8.1/mods-available/
    
    # Create optimized PHP configuration
    cat > /usr/local/lsws/lsphp81/etc/php/8.1/mods-available/custom.ini << EOF
; Optimized PHP settings for WordPress
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
max_input_vars = 3000
display_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
date.timezone = UTC

; OpCache settings
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
opcache.enable_cli = 1
EOF

    # Link the custom configuration
    ln -sf /usr/local/lsws/lsphp81/etc/php/8.1/mods-available/custom.ini /usr/local/lsws/lsphp81/etc/php/8.1/litespeed/conf.d/99-custom.ini
    
    print_message "success" "PHP configured for optimal performance"
}

# Function to install the web hosting panel
install_panel() {
    print_message "info" "Installing web hosting panel..."
    
    # Create panel directory
    mkdir -p /var/www/panel
    
    # Copy panel files
    cp -r $(dirname "$0")/panel/* /var/www/panel/
    
    # Set permissions
    chown -R www-data:www-data /var/www/panel
    chmod -R 755 /var/www/panel
    
    # Create LiteSpeed virtual host configuration
    cat > /usr/local/lsws/conf/vhosts/panel.conf << EOF
docRoot                   \$VH_ROOT/html/
vhDomain                  panel.\$VH_NAME
adminEmails               admin@\$VH_NAME
enableGzip                1
enableIpGeo               1

index  {
  useServer               0
  indexFiles              index.php index.html
}

context / {
  location                \$VH_ROOT/html/
  allowBrowse             1
  
  rewrite  {
    enable                1
    rules                 <<<END_rules
    rewriteCond %{REQUEST_FILENAME} !-f
    rewriteCond %{REQUEST_FILENAME} !-d
    rewriteRule ^(.*)$ index.php [QSA,L]
    END_rules
  }
}

context /api {
  location                \$VH_ROOT/api/
  allowBrowse             1
  
  rewrite  {
    enable                1
    rules                 <<<END_rules
    rewriteCond %{REQUEST_FILENAME} !-f
    rewriteCond %{REQUEST_FILENAME} !-d
    rewriteRule ^(.*)$ index.php [QSA,L]
    END_rules
  }
}

phpIniOverride  {
  php_admin_value upload_max_filesize 64M
  php_admin_value post_max_size 64M
  php_admin_value memory_limit 256M
  php_admin_value max_execution_time 300
}
EOF

    # Update main configuration to include panel virtual host
    if ! grep -q "panel" /usr/local/lsws/conf/httpd_config.conf; then
        sed -i '/virtualHost Example/a virtualHost panel {\n  vhRoot                  /var/www/panel/\n  configFile               \$SERVER_ROOT/conf/vhosts/panel.conf\n  allowSymbolLink         1\n  enableScript            1\n  restrained              1\n}' /usr/local/lsws/conf/httpd_config.conf
    fi
    
    # Create panel admin user
    PANEL_ADMIN_USER="admin"
    PANEL_ADMIN_PASSWORD=$(openssl rand -base64 12)
    
    # Save panel admin credentials
    echo "Panel Admin Username: $PANEL_ADMIN_USER" >> /root/mysql_credentials.txt
    echo "Panel Admin Password: $PANEL_ADMIN_PASSWORD" >> /root/mysql_credentials.txt
    
    # Create panel database
    mysql -u root -p"$MARIADB_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS panel_db;"
    mysql -u root -p"$MARIADB_ROOT_PASSWORD" -e "CREATE USER 'panel_user'@'localhost' IDENTIFIED BY '$PANEL_ADMIN_PASSWORD';"
    mysql -u root -p"$MARIADB_ROOT_PASSWORD" -e "GRANT ALL PRIVILEGES ON panel_db.* TO 'panel_user'@'localhost';"
    mysql -u root -p"$MARIADB_ROOT_PASSWORD" -e "FLUSH PRIVILEGES;"
    
    # Import panel database schema
    mysql -u root -p"$MARIADB_ROOT_PASSWORD" panel_db < $(dirname "$0")/panel/database/schema.sql
    
    # Insert admin user into database
    HASHED_PASSWORD=$(echo -n "$PANEL_ADMIN_PASSWORD" | sha256sum | awk '{print $1}')
    mysql -u root -p"$MARIADB_ROOT_PASSWORD" -e "INSERT INTO panel_db.users (username, password, email, role, created_at) VALUES ('$PANEL_ADMIN_USER', '$HASHED_PASSWORD', 'admin@localhost', 'admin', NOW());"
    
    # Update panel configuration
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$PANEL_ADMIN_PASSWORD/" /var/www/panel/config/database.php
    
    # Restart LiteSpeed
    /usr/local/lsws/bin/lswsctrl restart
    
    print_message "success" "Web hosting panel installed"
    print_message "info" "Panel admin credentials saved to /root/mysql_credentials.txt"
    print_message "info" "Panel URL: http://YOUR_SERVER_IP/panel/"
}

# Function to configure firewall
configure_firewall() {
    print_message "info" "Configuring firewall..."
    
    # Install UFW if not already installed
    apt install -y ufw
    
    # Configure UFW
    ufw default deny incoming
    ufw default allow outgoing
    ufw allow ssh
    ufw allow http
    ufw allow https
    ufw allow 7080/tcp  # LiteSpeed admin
    ufw allow 8088/tcp  # LiteSpeed HTTP
    
    # Enable UFW non-interactively
    echo "y" | ufw enable
    
    print_message "success" "Firewall configured"
}

# Function to create WordPress installation script
create_wp_installer() {
    print_message "info" "Creating WordPress installation script..."
    
    # Create WordPress installer directory
    mkdir -p /var/www/panel/scripts
    
    # Create WordPress installation script
    cat > /var/www/panel/scripts/install_wordpress.sh << 'EOF'
#!/bin/bash

# WordPress Installation Script
# This script is called by the panel to install WordPress for a new site

# Check if all required parameters are provided
if [ $# -lt 5 ]; then
    echo "Usage: $0 <domain> <db_name> <db_user> <db_password> <wp_admin_email>"
    exit 1
fi

DOMAIN=$1
DB_NAME=$2
DB_USER=$3
DB_PASSWORD=$4
WP_ADMIN_EMAIL=$5

# Create website directory
SITE_DIR="/var/www/$DOMAIN"
mkdir -p "$SITE_DIR/html"

# Use WP-CLI to download and configure WordPress
cd "$SITE_DIR/html"

# Download WordPress core files
wp core download --quiet

# Create wp-config.php
wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" --dbhost="localhost" --quiet

# Add extra configuration to wp-config.php
wp config set WP_DEBUG false --raw --quiet
wp config set FS_METHOD direct --quiet
wp config set WP_MEMORY_LIMIT 256M --quiet
wp config set WP_MAX_MEMORY_LIMIT 512M --quiet
wp config set DISALLOW_FILE_EDIT true --raw --quiet
wp config set AUTOMATIC_UPDATER_DISABLED false --raw --quiet
wp config set WP_AUTO_UPDATE_CORE minor --quiet

# Create LiteSpeed configuration for the site
cat > /usr/local/lsws/conf/vhosts/$DOMAIN.conf << LSCONFIG
docRoot                   \$VH_ROOT/html/
vhDomain                  $DOMAIN
adminEmails               $WP_ADMIN_EMAIL
enableGzip                1
enableIpGeo               1

index  {
  useServer               0
  indexFiles              index.php index.html
}

context / {
  location                \$VH_ROOT/html/
  allowBrowse             1
  
  rewrite  {
    enable                1
    rules                 <<<END_rules
    rewriteCond %{REQUEST_FILENAME} !-f
    rewriteCond %{REQUEST_FILENAME} !-d
    rewriteRule ^(.*)$ index.php [QSA,L]
    END_rules
  }
}

phpIniOverride  {
  php_admin_value upload_max_filesize 64M
  php_admin_value post_max_size 64M
  php_admin_value memory_limit 256M
  php_admin_value max_execution_time 300
}
LSCONFIG

# Update main configuration to include the new virtual host
if ! grep -q "$DOMAIN" /usr/local/lsws/conf/httpd_config.conf; then
    sed -i "/virtualHost panel/a virtualHost $DOMAIN {\n  vhRoot                  /var/www/$DOMAIN/\n  configFile               \$SERVER_ROOT/conf/vhosts/$DOMAIN.conf\n  allowSymbolLink         1\n  enableScript            1\n  restrained              1\n}" /usr/local/lsws/conf/httpd_config.conf
fi

# Create database and user
mysql -u root -p$(cat /root/mysql_credentials.txt | grep "MariaDB Root Password" | cut -d ":" -f2 | tr -d ' ') -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"
mysql -u root -p$(cat /root/mysql_credentials.txt | grep "MariaDB Root Password" | cut -d ":" -f2 | tr -d ' ') -e "CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
mysql -u root -p$(cat /root/mysql_credentials.txt | grep "MariaDB Root Password" | cut -d ":" -f2 | tr -d ' ') -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
mysql -u root -p$(cat /root/mysql_credentials.txt | grep "MariaDB Root Password" | cut -d ":" -f2 | tr -d ' ') -e "FLUSH PRIVILEGES;"

# Install WordPress using WP-CLI
wp core install --url="http://$DOMAIN" --title="WordPress on $DOMAIN" --admin_user="admin" --admin_password="$(openssl rand -base64 12)" --admin_email="$WP_ADMIN_EMAIL" --quiet

# Install and activate essential plugins
wp plugin install litespeed-cache --activate --quiet
wp plugin install wordfence --activate --quiet
wp plugin install wp-mail-smtp --activate --quiet

# Set proper permissions
chown -R www-data:www-data "$SITE_DIR"
chmod -R 755 "$SITE_DIR"
find "$SITE_DIR/html" -type f -exec chmod 644 {} \;

# Restart LiteSpeed
/usr/local/lsws/bin/lswsctrl restart

# Save WordPress admin credentials
WP_ADMIN_USER="admin"
WP_ADMIN_PASSWORD=$(wp user get admin --field=user_pass --quiet)
echo "WordPress installed successfully for $DOMAIN"
echo "WordPress Admin URL: http://$DOMAIN/wp-admin/"
echo "WordPress Admin Username: $WP_ADMIN_USER"
echo "WordPress Admin Password: $WP_ADMIN_PASSWORD"
exit 0
EOF

    # Make the script executable
    chmod +x /var/www/panel/scripts/install_wordpress.sh
    
    print_message "success" "WordPress installation script created"
}

# Function to install WP-CLI
install_wp_cli() {
    print_message "info" "Installing WP-CLI..."
    
    # Download WP-CLI
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    
    # Make it executable
    chmod +x wp-cli.phar
    
    # Move to path
    mv wp-cli.phar /usr/local/bin/wp
    
    # Verify installation
    wp --info
    
    print_message "success" "WP-CLI installed successfully"
}

# Main installation function
main_install() {
    print_message "info" "Starting installation of WordPress Hosting Panel with LiteSpeed..."
    
    check_root
    check_system
    update_system
    install_mariadb
    install_litespeed
    install_php
    install_wp_cli
    install_panel
    configure_firewall
    create_wp_installer
    
    print_message "success" "Installation completed successfully!"
    print_message "info" "LiteSpeed Admin URL: https://YOUR_SERVER_IP:7080"
    print_message "info" "Panel URL: http://YOUR_SERVER_IP/panel/"
    print_message "info" "All credentials are saved in /root/mysql_credentials.txt"
}

# Run the main installation
main_install