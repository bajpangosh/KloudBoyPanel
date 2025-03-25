# WordPress Hosting Panel with LiteSpeed

A comprehensive web hosting control panel specifically designed for WordPress sites using LiteSpeed Web Server on Ubuntu 22.04.

## Features

- **Easy Domain Management**: Add, remove, and manage domains with ease.
- **One-Click WordPress Installation**: Install WordPress with a single click using WP-CLI.
- **LiteSpeed Web Server**: Leverage the power and performance of LiteSpeed.
- **MariaDB Database**: High-performance MariaDB database server.
- **WP-CLI Integration**: Full WP-CLI support for advanced WordPress management.
- **User Management**: Create and manage hosting accounts for your clients.
- **System Monitoring**: Monitor server resources and performance.
- **SSL Certificate Management**: Easily install and manage SSL certificates.
- **Database Management**: Create and manage MariaDB databases.
- **Backup & Restore**: Automated backup and restore functionality.
- **Email Notifications**: Get notified about important system events.
- **API Access**: RESTful API for integration with other systems.

## Requirements

- Ubuntu 22.04 LTS
- Minimum 2GB RAM (4GB recommended)
- Minimum 20GB disk space
- Root access to the server

## Installation

1. Clone this repository:
   ```
   git clone https://github.com/yourusername/wp-litespeed-panel.git
   ```

2. Navigate to the project directory:
   ```
   cd wp-litespeed-panel
   ```

3. Make the installation script executable:
   ```
   chmod +x install.sh
   ```

4. Run the installation script as root:
   ```
   sudo ./install.sh
   ```

5. Follow the on-screen instructions to complete the installation.

## Post-Installation

After installation, you can access the panel at:

- Panel URL: `http://YOUR_SERVER_IP/panel/`
- Default admin username: `admin`
- Default admin password: (generated during installation and displayed at the end)

LiteSpeed Admin Console:
- URL: `https://YOUR_SERVER_IP:7080/`
- Username: `admin`
- Password: (generated during installation and displayed at the end)

All credentials are also saved in `/root/mysql_credentials.txt` on your server (contains MariaDB credentials despite the filename).

## Usage

### Adding a Domain

1. Log in to the panel.
2. Navigate to "Domains" and click "Add Domain".
3. Enter the domain name and other required information.
4. Click "Add Domain" to create the domain.

### Installing WordPress

1. Log in to the panel.
2. Navigate to "WordPress" and click "Install WordPress".
3. Select the domain where you want to install WordPress.
4. Enter the required information (admin email, username, etc.).
5. Click "Install WordPress" to begin the installation.

### Managing Users

1. Log in to the panel as an admin.
2. Navigate to "Users" to view, add, edit, or delete users.
3. Click "Add User" to create a new user account.

## Security Recommendations

1. Change the default admin password immediately after installation.
2. Keep your server and all software up to date.
3. Use strong passwords for all accounts.
4. Enable two-factor authentication if available.
5. Regularly backup your data.
6. Configure a firewall to restrict access to your server.

## Troubleshooting

### LiteSpeed Web Server Issues

If you encounter issues with LiteSpeed Web Server, check the following:

1. Verify that LiteSpeed is running:
   ```
   sudo /usr/local/lsws/bin/lswsctrl status
   ```

2. Check LiteSpeed error logs:
   ```
   sudo cat /usr/local/lsws/logs/error.log
   ```

3. Restart LiteSpeed if needed:
   ```
   sudo /usr/local/lsws/bin/lswsctrl restart
   ```

### Database Issues

If you encounter database issues, check the following:

1. Verify that MariaDB is running:
   ```
   sudo systemctl status mariadb
   ```

2. Check MariaDB error logs:
   ```
   sudo cat /var/log/mysql/error.log
   ```

3. Restart MariaDB if needed:
   ```
   sudo systemctl restart mariadb
   ```

### Panel Access Issues

If you cannot access the panel, check the following:

1. Verify that your server is running and accessible.
2. Check that the panel virtual host is properly configured in LiteSpeed.
3. Check the panel error logs in `/var/www/panel/logs/`.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- LiteSpeed Technologies for their excellent web server
- WordPress for their amazing CMS
- All the open-source projects that made this possible