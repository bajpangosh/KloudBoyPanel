<?php
/**
 * Settings Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Check if user is admin
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

// Get action from URL
$action = isset($_GET['action']) ? $_GET['action'] : 'general';

// Handle different actions
switch ($action) {
    case 'litespeed':
        include 'settings/litespeed.php';
        break;
    
    case 'mysql':
        include 'settings/mysql.php';
        break;
    
    case 'wordpress':
        include 'settings/wordpress.php';
        break;
    
    case 'backup':
        include 'settings/backup.php';
        break;
    
    case 'email':
        include 'settings/email.php';
        break;
    
    default:
        // Get settings from database
        $db = Database::getInstance();
        $settings = [];
        
        $result = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
        foreach ($result as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Set default values if not set
        $default_settings = [
            'panel_name' => 'WordPress Hosting Panel with LiteSpeed',
            'panel_version' => '1.0.0',
            'default_php_version' => '8.1',
            'default_mysql_version' => '8.0',
            'max_domains_per_user' => '10',
            'max_disk_space_per_domain' => '5120', // 5GB in MB
            'max_bandwidth_per_domain' => '102400', // 100GB in MB
            'enable_ssl_by_default' => '1',
            'enable_wp_auto_update' => '1',
            'enable_backup' => '1',
            'backup_retention_days' => '7'
        ];
        
        foreach ($default_settings as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }
        
        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate CSRF token
            if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
                $error = 'Invalid form submission, please try again.';
            } else {
                try {
                    // Begin transaction
                    $db->beginTransaction();
                    
                    // Update settings
                    foreach ($default_settings as $key => $default_value) {
                        if (isset($_POST[$key])) {
                            $value = sanitize_input($_POST[$key]);
                            
                            // Validate numeric values
                            if (in_array($key, ['max_domains_per_user', 'max_disk_space_per_domain', 'max_bandwidth_per_domain', 'backup_retention_days'])) {
                                if (!is_numeric($value) || $value < 0) {
                                    throw new Exception("Invalid value for $key: must be a positive number");
                                }
                            }
                            
                            // Update or insert setting
                            $existing = $db->fetchOne(
                                "SELECT id FROM settings WHERE setting_key = ?",
                                [$key]
                            );
                            
                            if ($existing) {
                                $db->update(
                                    "UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
                                    [$value, $key]
                                );
                            } else {
                                $db->insert(
                                    "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())",
                                    [$key, $value]
                                );
                            }
                            
                            // Update local settings array
                            $settings[$key] = $value;
                        }
                    }
                    
                    // Log the action
                    log_action($current_user['id'], 'update_settings', 'Updated general settings');
                    
                    // Commit transaction
                    $db->commit();
                    
                    // Set success message
                    $success = 'Settings updated successfully';
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $db->rollback();
                    $error = 'Failed to update settings: ' . $e->getMessage();
                }
            }
        }
        
        // Generate CSRF token
        $csrf_token = generate_csrf_token();
?>
<div class="row mb-4">
    <div class="col-md-6">
        <h2>Settings</h2>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<?php if (isset($success)): ?>
<div class="alert alert-success" role="alert">
    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="list-group">
            <a href="index.php?page=settings" class="list-group-item list-group-item-action active">
                <i class="fas fa-cog me-2"></i> General Settings
            </a>
            <a href="index.php?page=settings&action=litespeed" class="list-group-item list-group-item-action">
                <i class="fas fa-server me-2"></i> LiteSpeed Settings
            </a>
            <a href="index.php?page=settings&action=mysql" class="list-group-item list-group-item-action">
                <i class="fas fa-database me-2"></i> MySQL Settings
            </a>
            <a href="index.php?page=settings&action=wordpress" class="list-group-item list-group-item-action">
                <i class="fab fa-wordpress me-2"></i> WordPress Settings
            </a>
            <a href="index.php?page=settings&action=backup" class="list-group-item list-group-item-action">
                <i class="fas fa-download me-2"></i> Backup Settings
            </a>
            <a href="index.php?page=settings&action=email" class="list-group-item list-group-item-action">
                <i class="fas fa-envelope me-2"></i> Email Settings
            </a>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">General Settings</h5>
            </div>
            <div class="card-body">
                <form method="post" action="index.php?page=settings">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="panel_name" class="form-label">Panel Name</label>
                        <input type="text" class="form-control" id="panel_name" name="panel_name" value="<?php echo htmlspecialchars($settings['panel_name']); ?>">
                        <div class="form-text">The name of your hosting panel</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="panel_version" class="form-label">Panel Version</label>
                        <input type="text" class="form-control" id="panel_version" name="panel_version" value="<?php echo htmlspecialchars($settings['panel_version']); ?>" readonly>
                        <div class="form-text">Current version of the panel (read-only)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="default_php_version" class="form-label">Default PHP Version</label>
                        <select class="form-select" id="default_php_version" name="default_php_version">
                            <option value="8.1" <?php echo $settings['default_php_version'] == '8.1' ? 'selected' : ''; ?>>PHP 8.1</option>
                            <option value="8.0" <?php echo $settings['default_php_version'] == '8.0' ? 'selected' : ''; ?>>PHP 8.0</option>
                            <option value="7.4" <?php echo $settings['default_php_version'] == '7.4' ? 'selected' : ''; ?>>PHP 7.4</option>
                        </select>
                        <div class="form-text">Default PHP version for new domains</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="default_mysql_version" class="form-label">Default MySQL Version</label>
                        <select class="form-select" id="default_mysql_version" name="default_mysql_version">
                            <option value="8.0" <?php echo $settings['default_mysql_version'] == '8.0' ? 'selected' : ''; ?>>MySQL 8.0</option>
                            <option value="5.7" <?php echo $settings['default_mysql_version'] == '5.7' ? 'selected' : ''; ?>>MySQL 5.7</option>
                        </select>
                        <div class="form-text">Default MySQL version for new databases</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_domains_per_user" class="form-label">Max Domains Per User</label>
                        <input type="number" class="form-control" id="max_domains_per_user" name="max_domains_per_user" value="<?php echo htmlspecialchars($settings['max_domains_per_user']); ?>" min="1">
                        <div class="form-text">Maximum number of domains a regular user can create</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_disk_space_per_domain" class="form-label">Max Disk Space Per Domain (MB)</label>
                        <input type="number" class="form-control" id="max_disk_space_per_domain" name="max_disk_space_per_domain" value="<?php echo htmlspecialchars($settings['max_disk_space_per_domain']); ?>" min="100">
                        <div class="form-text">Maximum disk space allowed per domain in MB (1024 MB = 1 GB)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_bandwidth_per_domain" class="form-label">Max Bandwidth Per Domain (MB)</label>
                        <input type="number" class="form-control" id="max_bandwidth_per_domain" name="max_bandwidth_per_domain" value="<?php echo htmlspecialchars($settings['max_bandwidth_per_domain']); ?>" min="1024">
                        <div class="form-text">Maximum monthly bandwidth allowed per domain in MB (1024 MB = 1 GB)</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="enable_ssl_by_default" name="enable_ssl_by_default" value="1" <?php echo $settings['enable_ssl_by_default'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enable_ssl_by_default">Enable SSL by Default</label>
                        <div class="form-text">Automatically generate SSL certificates for new domains</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="enable_wp_auto_update" name="enable_wp_auto_update" value="1" <?php echo $settings['enable_wp_auto_update'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enable_wp_auto_update">Enable WordPress Auto-Updates</label>
                        <div class="form-text">Automatically update WordPress core for minor versions</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="enable_backup" name="enable_backup" value="1" <?php echo $settings['enable_backup'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enable_backup">Enable Automatic Backups</label>
                        <div class="form-text">Automatically create backups of domains and databases</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="backup_retention_days" class="form-label">Backup Retention Days</label>
                        <input type="number" class="form-control" id="backup_retention_days" name="backup_retention_days" value="<?php echo htmlspecialchars($settings['backup_retention_days']); ?>" min="1">
                        <div class="form-text">Number of days to keep backups before automatic deletion</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
        break;
}
?>