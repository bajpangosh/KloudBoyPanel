<?php
/**
 * LiteSpeed Settings Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Check if user is admin
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

// Get LiteSpeed settings from database
$db = Database::getInstance();
$settings = [];

$result = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'litespeed_%'");
foreach ($result as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Set default values if not set
$default_settings = [
    'litespeed_admin_port' => '7080',
    'litespeed_http_port' => '8088',
    'litespeed_https_port' => '8443',
    'litespeed_max_connections' => '2000',
    'litespeed_max_keep_alive' => '500',
    'litespeed_keep_alive_timeout' => '5',
    'litespeed_gzip_compression' => '1',
    'litespeed_cache_enabled' => '1',
    'litespeed_cache_timeout' => '3600',
    'litespeed_enable_http2' => '1',
    'litespeed_enable_quic' => '0',
    'litespeed_log_level' => 'ERROR',
    'litespeed_access_log_enabled' => '1',
    'litespeed_error_log_enabled' => '1'
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
                    if (in_array($key, ['litespeed_admin_port', 'litespeed_http_port', 'litespeed_https_port', 'litespeed_max_connections', 'litespeed_max_keep_alive', 'litespeed_keep_alive_timeout', 'litespeed_cache_timeout'])) {
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
            log_action($current_user['id'], 'update_settings', 'Updated LiteSpeed settings');
            
            // Commit transaction
            $db->commit();
            
            // Set success message
            $success = 'LiteSpeed settings updated successfully';
            
            // Apply changes to LiteSpeed configuration
            $apply_changes = isset($_POST['apply_changes']) && $_POST['apply_changes'] == '1';
            
            if ($apply_changes) {
                // Create backup of current configuration
                $config_file = LITESPEED_CONF_PATH . '/httpd_config.conf';
                $backup_file = LITESPEED_CONF_PATH . '/httpd_config.conf.bak.' . date('YmdHis');
                
                if (file_exists($config_file)) {
                    copy($config_file, $backup_file);
                    
                    // Read current configuration
                    $config_content = file_get_contents($config_file);
                    
                    // Update configuration values
                    $config_content = preg_replace('/adminPort\s+\d+/', 'adminPort ' . $settings['litespeed_admin_port'], $config_content);
                    $config_content = preg_replace('/port\s+\d+/', 'port ' . $settings['litespeed_http_port'], $config_content);
                    $config_content = preg_replace('/maxConnections\s+\d+/', 'maxConnections ' . $settings['litespeed_max_connections'], $config_content);
                    $config_content = preg_replace('/maxKeepAliveReq\s+\d+/', 'maxKeepAliveReq ' . $settings['litespeed_max_keep_alive'], $config_content);
                    $config_content = preg_replace('/keepAliveTimeout\s+\d+/', 'keepAliveTimeout ' . $settings['litespeed_keep_alive_timeout'], $config_content);
                    $config_content = preg_replace('/enableGzip\s+\d+/', 'enableGzip ' . $settings['litespeed_gzip_compression'], $config_content);
                    $config_content = preg_replace('/enableCache\s+\d+/', 'enableCache ' . $settings['litespeed_cache_enabled'], $config_content);
                    $config_content = preg_replace('/enableIpGeo\s+\d+/', 'enableIpGeo 1', $config_content);
                    
                    // Write updated configuration
                    file_put_contents($config_file, $config_content);
                    
                    // Restart LiteSpeed to apply changes
                    exec(CMD_RESTART_LITESPEED . ' 2>&1', $output, $return_var);
                    
                    if ($return_var === 0) {
                        $success .= ' and applied to LiteSpeed configuration';
                    } else {
                        $error = 'Settings updated but failed to restart LiteSpeed: ' . implode("\n", $output);
                    }
                } else {
                    $error = 'Settings updated but LiteSpeed configuration file not found';
                }
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollback();
            $error = 'Failed to update settings: ' . $e->getMessage();
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Get LiteSpeed status
$litespeed_status = [
    'running' => false,
    'version' => '',
    'uptime' => '',
    'connections' => 0
];

exec(CMD_CHECK_LITESPEED, $output, $return_var);
if ($return_var === 0) {
    $litespeed_status['running'] = true;
    
    // Get LiteSpeed version
    exec('/usr/local/lsws/bin/lshttpd -v 2>&1', $version_output);
    if (!empty($version_output)) {
        $litespeed_status['version'] = trim($version_output[0]);
    }
    
    // Get LiteSpeed uptime and connections
    $status_output = implode("\n", $output);
    if (preg_match('/Uptime:\s+(.+)/', $status_output, $matches)) {
        $litespeed_status['uptime'] = trim($matches[1]);
    }
    if (preg_match('/Total Requests:\s+(\d+)/', $status_output, $matches)) {
        $litespeed_status['connections'] = (int)$matches[1];
    }
}
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>LiteSpeed Settings</h2>
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
            <a href="index.php?page=settings" class="list-group-item list-group-item-action">
                <i class="fas fa-cog me-2"></i> General Settings
            </a>
            <a href="index.php?page=settings&action=litespeed" class="list-group-item list-group-item-action active">
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
        
        <!-- LiteSpeed Status -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">LiteSpeed Status</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <span class="badge bg-<?php echo $litespeed_status['running'] ? 'success' : 'danger'; ?> p-2">
                            <i class="fas fa-<?php echo $litespeed_status['running'] ? 'check' : 'times'; ?> fa-lg"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="mb-0"><?php echo $litespeed_status['running'] ? 'Running' : 'Stopped'; ?></h5>
                        <small class="text-muted"><?php echo $litespeed_status['version']; ?></small>
                    </div>
                </div>
                
                <?php if ($litespeed_status['running']): ?>
                <div class="system-info-item">
                    <strong>Uptime:</strong>
                    <span><?php echo $litespeed_status['uptime']; ?></span>
                </div>
                <div class="system-info-item">
                    <strong>Total Requests:</strong>
                    <span><?php echo number_format($litespeed_status['connections']); ?></span>
                </div>
                <div class="mt-3">
                    <a href="https://<?php echo $_SERVER['SERVER_ADDR']; ?>:7080" target="_blank" class="btn btn-sm btn-primary">
                        <i class="fas fa-external-link-alt me-2"></i> Open LiteSpeed Admin
                    </a>
                </div>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> LiteSpeed Web Server is not running.
                </div>
                <div class="mt-3">
                    <a href="index.php?page=system&action=restart-litespeed" class="btn btn-warning">
                        <i class="fas fa-sync-alt me-2"></i> Restart LiteSpeed
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">LiteSpeed Web Server Settings</h5>
            </div>
            <div class="card-body">
                <form method="post" action="index.php?page=settings&action=litespeed">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <h5 class="border-bottom pb-2 mb-3">Server Ports</h5>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="litespeed_admin_port" class="form-label">Admin Port</label>
                            <input type="number" class="form-control" id="litespeed_admin_port" name="litespeed_admin_port" value="<?php echo htmlspecialchars($settings['litespeed_admin_port']); ?>" min="1" max="65535">
                            <div class="form-text">LiteSpeed admin console port</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="litespeed_http_port" class="form-label">HTTP Port</label>
                            <input type="number" class="form-control" id="litespeed_http_port" name="litespeed_http_port" value="<?php echo htmlspecialchars($settings['litespeed_http_port']); ?>" min="1" max="65535">
                            <div class="form-text">LiteSpeed HTTP port</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="litespeed_https_port" class="form-label">HTTPS Port</label>
                            <input type="number" class="form-control" id="litespeed_https_port" name="litespeed_https_port" value="<?php echo htmlspecialchars($settings['litespeed_https_port']); ?>" min="1" max="65535">
                            <div class="form-text">LiteSpeed HTTPS port</div>
                        </div>
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-3 mt-4">Performance Settings</h5>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="litespeed_max_connections" class="form-label">Max Connections</label>
                            <input type="number" class="form-control" id="litespeed_max_connections" name="litespeed_max_connections" value="<?php echo htmlspecialchars($settings['litespeed_max_connections']); ?>" min="100">
                            <div class="form-text">Maximum concurrent connections</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="litespeed_max_keep_alive" class="form-label">Max Keep-Alive Requests</label>
                            <input type="number" class="form-control" id="litespeed_max_keep_alive" name="litespeed_max_keep_alive" value="<?php echo htmlspecialchars($settings['litespeed_max_keep_alive']); ?>" min="1">
                            <div class="form-text">Maximum requests per connection</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="litespeed_keep_alive_timeout" class="form-label">Keep-Alive Timeout</label>
                            <input type="number" class="form-control" id="litespeed_keep_alive_timeout" name="litespeed_keep_alive_timeout" value="<?php echo htmlspecialchars($settings['litespeed_keep_alive_timeout']); ?>" min="1">
                            <div class="form-text">Seconds to keep idle connections</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="litespeed_gzip_compression" name="litespeed_gzip_compression" value="1" <?php echo $settings['litespeed_gzip_compression'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="litespeed_gzip_compression">Enable GZIP Compression</label>
                                <div class="form-text">Compress content to reduce bandwidth</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="litespeed_enable_http2" name="litespeed_enable_http2" value="1" <?php echo $settings['litespeed_enable_http2'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="litespeed_enable_http2">Enable HTTP/2</label>
                                <div class="form-text">Use HTTP/2 protocol for better performance</div>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-3 mt-4">Cache Settings</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="litespeed_cache_enabled" name="litespeed_cache_enabled" value="1" <?php echo $settings['litespeed_cache_enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="litespeed_cache_enabled">Enable Server Cache</label>
                                <div class="form-text">Cache static content for better performance</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="litespeed_cache_timeout" class="form-label">Cache Timeout (seconds)</label>
                            <input type="number" class="form-control" id="litespeed_cache_timeout" name="litespeed_cache_timeout" value="<?php echo htmlspecialchars($settings['litespeed_cache_timeout']); ?>" min="60">
                            <div class="form-text">How long to cache content (in seconds)</div>
                        </div>
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-3 mt-4">Logging Settings</h5>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="litespeed_log_level" class="form-label">Log Level</label>
                            <select class="form-select" id="litespeed_log_level" name="litespeed_log_level">
                                <option value="DEBUG" <?php echo $settings['litespeed_log_level'] == 'DEBUG' ? 'selected' : ''; ?>>Debug</option>
                                <option value="INFO" <?php echo $settings['litespeed_log_level'] == 'INFO' ? 'selected' : ''; ?>>Info</option>
                                <option value="NOTICE" <?php echo $settings['litespeed_log_level'] == 'NOTICE' ? 'selected' : ''; ?>>Notice</option>
                                <option value="WARN" <?php echo $settings['litespeed_log_level'] == 'WARN' ? 'selected' : ''; ?>>Warning</option>
                                <option value="ERROR" <?php echo $settings['litespeed_log_level'] == 'ERROR' ? 'selected' : ''; ?>>Error</option>
                            </select>
                            <div class="form-text">Level of detail in error logs</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="litespeed_access_log_enabled" name="litespeed_access_log_enabled" value="1" <?php echo $settings['litespeed_access_log_enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="litespeed_access_log_enabled">Enable Access Log</label>
                                <div class="form-text">Log all HTTP requests</div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="litespeed_error_log_enabled" name="litespeed_error_log_enabled" value="1" <?php echo $settings['litespeed_error_log_enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="litespeed_error_log_enabled">Enable Error Log</label>
                                <div class="form-text">Log errors and warnings</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="apply_changes" name="apply_changes" value="1">
                        <label class="form-check-label" for="apply_changes">Apply changes to LiteSpeed configuration</label>
                        <div class="form-text">This will restart LiteSpeed to apply the changes</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save LiteSpeed Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>