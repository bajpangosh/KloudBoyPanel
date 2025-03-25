<?php
/**
 * System Status Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Check if user is admin
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

// Get action from URL
$action = isset($_GET['action']) ? $_GET['action'] : 'status';

// Handle different actions
switch ($action) {
    case 'restart-litespeed':
        include 'system/restart-litespeed.php';
        break;
    
    case 'restart-mariadb':
        include 'system/restart-mariadb.php';
        break;
    
    case 'update-system':
        include 'system/update-system.php';
        break;
    
    case 'logs':
        include 'system/logs.php';
        break;
    
    default:
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
        
        // Get MariaDB status
        $mariadb_status = [
            'running' => false,
            'version' => '',
            'connections' => 0,
            'uptime' => ''
        ];
        
        exec('systemctl status mariadb', $mariadb_output, $mariadb_return);
        if ($mariadb_return === 0) {
            $mariadb_status['running'] = true;
            
            // Get MariaDB version and status from database
            try {
                $db = Database::getInstance();
                $result = $db->fetchOne("SELECT VERSION() as version,
                                              (SELECT COUNT(*) FROM information_schema.processlist) as connections,
                                              (SELECT VARIABLE_VALUE FROM information_schema.global_status WHERE VARIABLE_NAME='Uptime') as uptime");
                
                if ($result) {
                    $mariadb_status['version'] = $result['version'];
                    $mariadb_status['connections'] = (int)$result['connections'];
                    $mariadb_status['uptime'] = gmdate("H:i:s", (int)$result['uptime']);
                }
            } catch (Exception $e) {
                // Database error, leave default values
            }
        }
        
        // Get system resources
        $system_resources = [
            'cpu_usage' => 0,
            'memory_total' => 0,
            'memory_used' => 0,
            'memory_free' => 0,
            'disk_total' => 0,
            'disk_used' => 0,
            'disk_free' => 0,
            'load_average' => ''
        ];
        
        // Get CPU usage
        exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'", $cpu_output);
        if (!empty($cpu_output)) {
            $system_resources['cpu_usage'] = (float)$cpu_output[0];
        }
        
        // Get memory usage
        exec("free -m | awk 'NR==2{printf \"%s %s %s\", $2, $3, $4}'", $memory_output);
        if (!empty($memory_output)) {
            list($total, $used, $free) = explode(' ', $memory_output[0]);
            $system_resources['memory_total'] = (int)$total;
            $system_resources['memory_used'] = (int)$used;
            $system_resources['memory_free'] = (int)$free;
        }
        
        // Get disk usage
        exec("df -h / | awk 'NR==2{printf \"%s %s %s\", $2, $3, $4}'", $disk_output);
        if (!empty($disk_output)) {
            list($total, $used, $free) = explode(' ', $disk_output[0]);
            $system_resources['disk_total'] = $total;
            $system_resources['disk_used'] = $used;
            $system_resources['disk_free'] = $free;
        }
        
        // Get load average
        exec("cat /proc/loadavg | awk '{print $1, $2, $3}'", $load_output);
        if (!empty($load_output)) {
            $system_resources['load_average'] = $load_output[0];
        }
        
        // Get WordPress sites count
        $wp_sites_count = 0;
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne("SELECT COUNT(*) as count FROM wordpress_sites");
            if ($result) {
                $wp_sites_count = (int)$result['count'];
            }
        } catch (Exception $e) {
            // Database error, leave default value
        }
        
        // Get domains count
        $domains_count = 0;
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne("SELECT COUNT(*) as count FROM domains");
            if ($result) {
                $domains_count = (int)$result['count'];
            }
        } catch (Exception $e) {
            // Database error, leave default value
        }
        
        // Get users count
        $users_count = 0;
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne("SELECT COUNT(*) as count FROM users");
            if ($result) {
                $users_count = (int)$result['count'];
            }
        } catch (Exception $e) {
            // Database error, leave default value
        }
        
        // Get panel version from settings
        $panel_version = APP_VERSION;
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'panel_version'");
            if ($result) {
                $panel_version = $result['setting_value'];
            }
        } catch (Exception $e) {
            // Database error, use default value
        }
        
        // Get recent system logs
        $system_logs = [];
        try {
            $db = Database::getInstance();
            $system_logs = $db->fetchAll(
                "SELECT l.*, u.username 
                 FROM logs l
                 LEFT JOIN users u ON l.user_id = u.id
                 ORDER BY l.created_at DESC
                 LIMIT 10"
            );
        } catch (Exception $e) {
            // Database error, leave empty array
        }
?>
<div class="row mb-4">
    <div class="col-md-6">
        <h2>System Status</h2>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group">
            <a href="index.php?page=system&action=restart-litespeed" class="btn btn-warning">
                <i class="fas fa-sync-alt me-2"></i> Restart LiteSpeed
            </a>
            <a href="index.php?page=system&action=restart-mariadb" class="btn btn-warning">
                <i class="fas fa-sync-alt me-2"></i> Restart MariaDB
            </a>
            <a href="index.php?page=system&action=update-system" class="btn btn-primary">
                <i class="fas fa-download me-2"></i> Update System
            </a>
            <a href="index.php?page=system&action=logs" class="btn btn-info">
                <i class="fas fa-list me-2"></i> View Logs
            </a>
        </div>
    </div>
</div>

<!-- Server Information -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Server Information</h5>
            </div>
            <div class="card-body">
                <div class="system-info-item">
                    <strong>Hostname:</strong>
                    <span><?php echo gethostname(); ?></span>
                </div>
                <div class="system-info-item">
                    <strong>Operating System:</strong>
                    <span><?php echo php_uname('s') . ' ' . php_uname('r'); ?></span>
                </div>
                <div class="system-info-item">
                    <strong>Kernel Version:</strong>
                    <span><?php echo php_uname('v'); ?></span>
                </div>
                <div class="system-info-item">
                    <strong>Architecture:</strong>
                    <span><?php echo php_uname('m'); ?></span>
                </div>
                <div class="system-info-item">
                    <strong>PHP Version:</strong>
                    <span><?php echo phpversion(); ?></span>
                </div>
                <div class="system-info-item">
                    <strong>Panel Version:</strong>
                    <span><?php echo htmlspecialchars($panel_version); ?></span>
                </div>
                <div class="system-info-item">
                    <strong>Server Time:</strong>
                    <span><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
                <div class="system-info-item">
                    <strong>Server Uptime:</strong>
                    <span id="server-uptime">Loading...</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">Resource Usage</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <strong>CPU Usage:</strong>
                        <span><?php echo number_format($system_resources['cpu_usage'], 1); ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar <?php echo $system_resources['cpu_usage'] > 80 ? 'bg-danger' : ($system_resources['cpu_usage'] > 60 ? 'bg-warning' : 'bg-success'); ?>" role="progressbar" style="width: <?php echo $system_resources['cpu_usage']; ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <strong>Memory Usage:</strong>
                        <span><?php echo $system_resources['memory_used']; ?> MB / <?php echo $system_resources['memory_total']; ?> MB</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <?php $memory_percentage = ($system_resources['memory_used'] / $system_resources['memory_total']) * 100; ?>
                        <div class="progress-bar <?php echo $memory_percentage > 80 ? 'bg-danger' : ($memory_percentage > 60 ? 'bg-warning' : 'bg-success'); ?>" role="progressbar" style="width: <?php echo $memory_percentage; ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <strong>Disk Usage:</strong>
                        <span><?php echo $system_resources['disk_used']; ?> / <?php echo $system_resources['disk_total']; ?> (<?php echo $system_resources['disk_free']; ?> free)</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <?php 
                        $disk_used = str_replace('G', '', $system_resources['disk_used']);
                        $disk_total = str_replace('G', '', $system_resources['disk_total']);
                        $disk_percentage = ($disk_used / $disk_total) * 100; 
                        ?>
                        <div class="progress-bar <?php echo $disk_percentage > 80 ? 'bg-danger' : ($disk_percentage > 60 ? 'bg-warning' : 'bg-success'); ?>" role="progressbar" style="width: <?php echo $disk_percentage; ?>%"></div>
                    </div>
                </div>
                
                <div class="system-info-item">
                    <strong>Load Average:</strong>
                    <span><?php echo $system_resources['load_average']; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Services Status -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">LiteSpeed Web Server</h5>
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
                    <a href="index.php?page=system&action=restart-litespeed" class="btn btn-sm btn-warning">
                        <i class="fas fa-sync-alt me-2"></i> Restart LiteSpeed
                    </a>
                </div>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> LiteSpeed Web Server is not running. Please restart it.
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
    
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">MariaDB Database Server</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <span class="badge bg-<?php echo $mariadb_status['running'] ? 'success' : 'danger'; ?> p-2">
                            <i class="fas fa-<?php echo $mariadb_status['running'] ? 'check' : 'times'; ?> fa-lg"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="mb-0"><?php echo $mariadb_status['running'] ? 'Running' : 'Stopped'; ?></h5>
                        <small class="text-muted"><?php echo $mariadb_status['version']; ?></small>
                    </div>
                </div>
                
                <?php if ($mariadb_status['running']): ?>
                <div class="system-info-item">
                    <strong>Uptime:</strong>
                    <span><?php echo $mariadb_status['uptime']; ?></span>
                </div>
                <div class="system-info-item">
                    <strong>Active Connections:</strong>
                    <span><?php echo number_format($mariadb_status['connections']); ?></span>
                </div>
                <div class="mt-3">
                    <a href="index.php?page=system&action=restart-mariadb" class="btn btn-sm btn-warning">
                        <i class="fas fa-sync-alt me-2"></i> Restart MariaDB
                    </a>
                </div>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> MariaDB Database Server is not running. Please restart it.
                </div>
                <div class="mt-3">
                    <a href="index.php?page=system&action=restart-mariadb" class="btn btn-warning">
                        <i class="fas fa-sync-alt me-2"></i> Restart MariaDB
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="card-title mb-0">System Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Domains</h6>
                                <h2 class="mb-0"><?php echo number_format($domains_count); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">WordPress Sites</h6>
                                <h2 class="mb-0"><?php echo number_format($wp_sites_count); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Users</h6>
                                <h2 class="mb-0"><?php echo number_format($users_count); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">PHP Version</h6>
                                <h2 class="mb-0"><?php echo phpversion(); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent System Logs -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="card-title mb-0">Recent System Logs</h5>
            </div>
            <div class="card-body">
                <?php if (empty($system_logs)): ?>
                <p class="text-muted">No logs found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>IP Address</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($system_logs as $log): ?>
                            <tr>
                                <td><?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><?php echo $log['username'] ? htmlspecialchars($log['username']) : 'System'; ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end">
                    <a href="index.php?page=system&action=logs" class="btn btn-sm btn-info">
                        <i class="fas fa-list me-2"></i> View All Logs
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Get server uptime
    function getServerUptime() {
        fetch('api/get-uptime.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('server-uptime').textContent = data.uptime;
                } else {
                    document.getElementById('server-uptime').textContent = 'Unknown';
                }
            })
            .catch(error => {
                document.getElementById('server-uptime').textContent = 'Unknown';
            });
    }
    
    // Update uptime every 60 seconds
    document.addEventListener('DOMContentLoaded', function() {
        getServerUptime();
        setInterval(getServerUptime, 60000);
    });
</script>
<?php
        break;
}
?>