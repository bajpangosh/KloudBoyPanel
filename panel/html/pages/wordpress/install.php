<?php
/**
 * Install WordPress Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Get domain ID from URL if available
$domain_id = isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0;

// Get domains without WordPress for installation
$db = Database::getInstance();

if (is_admin()) {
    $available_domains = $db->fetchAll(
        "SELECT d.id, d.domain_name, u.username as owner
         FROM domains d
         JOIN users u ON d.user_id = u.id
         WHERE d.status = 'active'
         AND NOT EXISTS (
             SELECT 1 FROM wordpress_sites ws WHERE ws.domain_id = d.id
         )
         ORDER BY d.domain_name"
    );
} else {
    $available_domains = $db->fetchAll(
        "SELECT d.id, d.domain_name, u.username as owner
         FROM domains d
         JOIN users u ON d.user_id = u.id
         WHERE d.user_id = ?
         AND d.status = 'active'
         AND NOT EXISTS (
             SELECT 1 FROM wordpress_sites ws WHERE ws.domain_id = d.id
         )
         ORDER BY d.domain_name",
        [$current_user['id']]
    );
}

// If no domains available, redirect to WordPress page
if (empty($available_domains)) {
    header('Location: index.php?page=wordpress&error=' . urlencode('No domains available for WordPress installation'));
    exit;
}

// If domain_id is provided, check if it's valid
$selected_domain = null;
if ($domain_id > 0) {
    foreach ($available_domains as $domain) {
        if ($domain['id'] == $domain_id) {
            $selected_domain = $domain;
            break;
        }
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid form submission, please try again.';
    } else {
        // Validate domain ID
        $domain_id = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : 0;
        
        if ($domain_id <= 0) {
            $error = 'Please select a domain.';
        } else {
            // Validate domain
            $domain = null;
            foreach ($available_domains as $d) {
                if ($d['id'] == $domain_id) {
                    $domain = $d;
                    break;
                }
            }
            
            if (!$domain) {
                $error = 'Invalid domain selected.';
            } else {
                // Validate admin email
                $admin_email = isset($_POST['admin_email']) ? filter_var($_POST['admin_email'], FILTER_VALIDATE_EMAIL) : '';
                
                if (!$admin_email) {
                    $error = 'Please enter a valid admin email address.';
                } else {
                    // Validate site title
                    $site_title = isset($_POST['site_title']) ? sanitize_input($_POST['site_title']) : '';
                    
                    if (empty($site_title)) {
                        $error = 'Please enter a site title.';
                    } else {
                        // Validate admin username
                        $admin_username = isset($_POST['admin_username']) ? sanitize_input($_POST['admin_username']) : '';
                        
                        if (empty($admin_username)) {
                            $error = 'Please enter an admin username.';
                        } else {
                            // Validate admin password
                            $admin_password = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
                            
                            if (empty($admin_password)) {
                                $error = 'Please enter an admin password.';
                            } elseif (strlen($admin_password) < 8) {
                                $error = 'Admin password must be at least 8 characters long.';
                            } else {
                                // Generate database credentials
                                $db_name = 'wp_' . preg_replace('/[^a-z0-9]/', '_', strtolower($domain['domain_name']));
                                $db_user = 'wp_' . substr(md5($domain['domain_name'] . time()), 0, 8);
                                $db_password = bin2hex(random_bytes(8));
                                
                                try {
                                    // Begin transaction
                                    $db->beginTransaction();
                                    
                                    // Insert WordPress site record
                                    $wp_id = $db->insert(
                                        "INSERT INTO wordpress_sites (domain_id, db_name, db_user, db_password, admin_email, status, installed_at) 
                                         VALUES (?, ?, ?, ?, ?, 'active', NOW())",
                                        [$domain_id, $db_name, $db_user, $db_password, $admin_email]
                                    );
                                    
                                    // Update domain record
                                    $db->update(
                                        "UPDATE domains SET updated_at = NOW() WHERE id = ?",
                                        [$domain_id]
                                    );
                                    
                                    // Create database and user
                                    $db->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                                    $db->query("CREATE USER '$db_user'@'localhost' IDENTIFIED BY '$db_password'");
                                    $db->query("GRANT ALL PRIVILEGES ON `$db_name`.* TO '$db_user'@'localhost'");
                                    $db->query("FLUSH PRIVILEGES");
                                    
                                    // Get domain document root
                                    $domain_info = $db->fetchOne(
                                        "SELECT document_root FROM domains WHERE id = ?",
                                        [$domain_id]
                                    );
                                    
                                    $document_root = $domain_info['document_root'] . '/html';
                                    
                                    // Create installation script
                                    $install_script = "#!/bin/bash

# WordPress Installation Script
# Generated by WordPress Hosting Panel with LiteSpeed

# Set variables
DOMAIN=\"{$domain['domain_name']}\"
DOCUMENT_ROOT=\"$document_root\"
DB_NAME=\"$db_name\"
DB_USER=\"$db_user\"
DB_PASSWORD=\"$db_password\"
WP_ADMIN_USER=\"$admin_username\"
WP_ADMIN_PASSWORD=\"$admin_password\"
WP_ADMIN_EMAIL=\"$admin_email\"
WP_TITLE=\"$site_title\"

# Create document root if it doesn't exist
mkdir -p \"\$DOCUMENT_ROOT\"

# Download WordPress
cd \"\$DOCUMENT_ROOT\"
wget -q -O wordpress.tar.gz https://wordpress.org/latest.tar.gz
tar -xzf wordpress.tar.gz --strip-components=1
rm wordpress.tar.gz

# Create wp-config.php
WP_SALTS=\$(curl -s https://api.wordpress.org/secret-key/1.1/salt/)
cat > wp-config.php << EOF
<?php
/**
 * WordPress Configuration File
 * Generated by WordPress Hosting Panel with LiteSpeed
 */

// Database settings
define('DB_NAME', '$db_name');
define('DB_USER', '$db_user');
define('DB_PASSWORD', '$db_password');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// Authentication keys and salts
\$WP_SALTS

// Table prefix
\\\$table_prefix = 'wp_';

// Debug settings
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// Performance settings
define('WP_CACHE', true);
define('DISABLE_WP_CRON', false);
define('EMPTY_TRASH_DAYS', 7);
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Security settings
define('DISALLOW_FILE_EDIT', true);
define('AUTOMATIC_UPDATER_DISABLED', false);
define('WP_AUTO_UPDATE_CORE', 'minor');
define('FS_METHOD', 'direct');

// Absolute path to the WordPress directory
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Sets up WordPress vars and included files
require_once(ABSPATH . 'wp-settings.php');
EOF

# Set proper permissions
chown -R www-data:www-data \"\$DOCUMENT_ROOT\"
chmod -R 755 \"\$DOCUMENT_ROOT\"
find \"\$DOCUMENT_ROOT\" -type f -exec chmod 644 {} \\;

# Install WordPress using WP-CLI if available
if command -v wp &> /dev/null; then
    cd \"\$DOCUMENT_ROOT\"
    wp core install --url=\"http://\$DOMAIN\" --title=\"\$WP_TITLE\" --admin_user=\"\$WP_ADMIN_USER\" --admin_password=\"\$WP_ADMIN_PASSWORD\" --admin_email=\"\$WP_ADMIN_EMAIL\" --skip-email
    
    # Install default plugins
    wp plugin install litespeed-cache --activate
    wp plugin install wordfence --activate
    wp plugin install wp-mail-smtp --activate
else
    # Create a flag file for the web installer
    cat > \"\$DOCUMENT_ROOT/.wp-install-data\" << EOF
{
    \"title\": \"\$WP_TITLE\",
    \"admin_user\": \"\$WP_ADMIN_USER\",
    \"admin_password\": \"\$WP_ADMIN_PASSWORD\",
    \"admin_email\": \"\$WP_ADMIN_EMAIL\"
}
EOF
    
    # Create an installer script
    cat > \"\$DOCUMENT_ROOT/wp-install.php\" << 'EOF'
<?php
// WordPress Auto-Installer
// This file will be deleted after installation

// Check if WordPress is already installed
if (file_exists('./wp-config.php') && !file_exists('./.wp-install-data')) {
    // Redirect to the WordPress site
    header('Location: /');
    exit;
}

// Load installation data
\$install_data = json_decode(file_get_contents('./.wp-install-data'), true);

if (!\$install_data) {
    die('Installation data not found');
}

// Set up the WordPress database
require_once('./wp-admin/includes/upgrade.php');
require_once('./wp-includes/wp-db.php');
require_once('./wp-includes/functions.php');
require_once('./wp-includes/load.php');
require_once('./wp-includes/plugin.php');
require_once('./wp-includes/kses.php');
require_once('./wp-includes/l10n.php');
require_once('./wp-includes/class-wp-locale.php');
require_once('./wp-includes/class-wp-locale-switcher.php');
require_once('./wp-admin/includes/schema.php');

// Create database tables
wp_install(
    \$install_data['title'],
    \$install_data['admin_user'],
    \$install_data['admin_email'],
    1, // public
    '', // deprecated
    \$install_data['admin_password']
);

// Delete the installation data and this file
@unlink('./.wp-install-data');
@unlink(__FILE__);

// Redirect to the WordPress admin
header('Location: /wp-admin/');
exit;
EOF
    
    # Create an index.php that redirects to the installer
    cat > \"\$DOCUMENT_ROOT/index.php.redirect\" << 'EOF'
<?php
// Redirect to the WordPress installer if it exists
if (file_exists('./wp-install.php')) {
    header('Location: /wp-install.php');
    exit;
}

// Otherwise, load WordPress
require('./index.php');
EOF
    
    # Rename files
    mv \"\$DOCUMENT_ROOT/index.php\" \"\$DOCUMENT_ROOT/index.php.original\"
    mv \"\$DOCUMENT_ROOT/index.php.redirect\" \"\$DOCUMENT_ROOT/index.php\"
fi

echo \"WordPress installation completed for \$DOMAIN\"
exit 0
";
                                    
                                    // Save installation script
                                    $script_path = '/tmp/wp-install-' . $domain['domain_name'] . '.sh';
                                    file_put_contents($script_path, $install_script);
                                    chmod($script_path, 0755);
                                    
                                    // Execute installation script
                                    exec($script_path . ' 2>&1', $output, $return_var);
                                    
                                    if ($return_var !== 0) {
                                        throw new Exception("Installation script failed: " . implode("\n", $output));
                                    }
                                    
                                    // Update WordPress version in database
                                    $wp_version = '';
                                    if (file_exists($document_root . '/wp-includes/version.php')) {
                                        include($document_root . '/wp-includes/version.php');
                                        if (isset($wp_version)) {
                                            $db->update(
                                                "UPDATE wordpress_sites SET wp_version = ? WHERE id = ?",
                                                [$wp_version, $wp_id]
                                            );
                                        }
                                    }
                                    
                                    // Log the action
                                    log_action($current_user['id'], 'install_wordpress', 'Installed WordPress on domain: ' . $domain['domain_name']);
                                    
                                    // Commit transaction
                                    $db->commit();
                                    
                                    // Redirect to WordPress page with success message
                                    header('Location: index.php?page=wordpress&success=' . urlencode('WordPress installed successfully on ' . $domain['domain_name']));
                                    exit;
                                } catch (Exception $e) {
                                    // Rollback transaction on error
                                    $db->rollback();
                                    $error = 'Failed to install WordPress: ' . $e->getMessage();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Generate random password if not set
$random_password = bin2hex(random_bytes(8));
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Install WordPress</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=wordpress" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to WordPress Sites
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">WordPress Installation</h5>
    </div>
    <div class="card-body">
        <form method="post" action="index.php?page=wordpress&action=install">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="domain_id" class="form-label required">Domain</label>
                <select class="form-select" id="domain_id" name="domain_id" required>
                    <option value="">Select a domain</option>
                    <?php foreach ($available_domains as $domain): ?>
                    <option value="<?php echo $domain['id']; ?>" <?php echo ($selected_domain && $selected_domain['id'] == $domain['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($domain['domain_name']); ?>
                        <?php if (is_admin()): ?>
                        (Owner: <?php echo htmlspecialchars($domain['owner']); ?>)
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Select the domain where you want to install WordPress</div>
            </div>
            
            <div class="mb-3">
                <label for="site_title" class="form-label required">Site Title</label>
                <input type="text" class="form-control" id="site_title" name="site_title" required value="<?php echo isset($_POST['site_title']) ? htmlspecialchars($_POST['site_title']) : ''; ?>">
                <div class="form-text">Enter the title for your WordPress site</div>
            </div>
            
            <div class="mb-3">
                <label for="admin_username" class="form-label required">Admin Username</label>
                <input type="text" class="form-control" id="admin_username" name="admin_username" required value="<?php echo isset($_POST['admin_username']) ? htmlspecialchars($_POST['admin_username']) : 'admin'; ?>">
                <div class="form-text">Enter the username for the WordPress admin account</div>
            </div>
            
            <div class="mb-3">
                <label for="admin_password" class="form-label required">Admin Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="admin_password" name="admin_password" required value="<?php echo isset($_POST['admin_password']) ? $_POST['admin_password'] : $random_password; ?>">
                    <button class="btn btn-outline-secondary" type="button" id="show_password">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-outline-secondary" type="button" id="generate_password">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="form-text">Enter a strong password for the WordPress admin account</div>
                <div class="progress mt-2" style="height: 5px;">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: 0%;" id="password_strength"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="admin_email" class="form-label required">Admin Email</label>
                <input type="email" class="form-control" id="admin_email" name="admin_email" required value="<?php echo isset($_POST['admin_email']) ? htmlspecialchars($_POST['admin_email']) : $current_user['email']; ?>">
                <div class="form-text">Enter the email address for the WordPress admin account</div>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="install_plugins" name="install_plugins" checked>
                <label class="form-check-label" for="install_plugins">Install recommended plugins</label>
                <div class="form-text">Install and activate recommended plugins (LiteSpeed Cache, Wordfence, WP Mail SMTP)</div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fab fa-wordpress me-2"></i> Install WordPress
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password visibility toggle
        const passwordInput = document.getElementById('admin_password');
        const showPasswordButton = document.getElementById('show_password');
        const generatePasswordButton = document.getElementById('generate_password');
        const passwordStrength = document.getElementById('password_strength');
        
        showPasswordButton.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                showPasswordButton.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                showPasswordButton.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
        
        // Generate random password
        generatePasswordButton.addEventListener('click', function() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            passwordInput.value = password;
            passwordInput.type = 'text';
            showPasswordButton.innerHTML = '<i class="fas fa-eye-slash"></i>';
            updatePasswordStrength(password);
        });
        
        // Update password strength meter
        passwordInput.addEventListener('input', function() {
            updatePasswordStrength(this.value);
        });
        
        // Initial password strength check
        updatePasswordStrength(passwordInput.value);
        
        function updatePasswordStrength(password) {
            let strength = 0;
            
            // Length check
            if (password.length >= 8) {
                strength += 1;
            }
            
            // Contains lowercase letters
            if (password.match(/[a-z]+/)) {
                strength += 1;
            }
            
            // Contains uppercase letters
            if (password.match(/[A-Z]+/)) {
                strength += 1;
            }
            
            // Contains numbers
            if (password.match(/[0-9]+/)) {
                strength += 1;
            }
            
            // Contains special characters
            if (password.match(/[$@#&!]+/)) {
                strength += 1;
            }
            
            // Update meter width
            passwordStrength.style.width = (strength * 20) + '%';
            
            // Update meter color
            if (strength < 2) {
                passwordStrength.className = 'progress-bar bg-danger';
            } else if (strength < 4) {
                passwordStrength.className = 'progress-bar bg-warning';
            } else {
                passwordStrength.className = 'progress-bar bg-success';
            }
        }
    });
</script>