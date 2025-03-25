<?php
/**
 * System Status API Handler
 * WordPress Hosting Panel with LiteSpeed
 */

/**
 * Get system status information
 * 
 * @param array $body Request body (not used for GET requests)
 * @return array System status information
 */
function get_system_status($body = null) {
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
    
    // Get MySQL status
    $mysql_status = [
        'running' => false,
        'version' => '',
        'connections' => 0,
        'uptime' => ''
    ];
    
    exec('systemctl status mysql', $mysql_output, $mysql_return);
    if ($mysql_return === 0) {
        $mysql_status['running'] = true;
        
        // Get MySQL version and status from database
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne("SELECT VERSION() as version, 
                                          (SELECT COUNT(*) FROM information_schema.processlist) as connections,
                                          (SELECT VARIABLE_VALUE FROM information_schema.global_status WHERE VARIABLE_NAME='Uptime') as uptime");
            
            if ($result) {
                $mysql_status['version'] = $result['version'];
                $mysql_status['connections'] = (int)$result['connections'];
                $mysql_status['uptime'] = gmdate("H:i:s", (int)$result['uptime']);
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
    
    // Return combined status
    return [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'panel_version' => $panel_version,
        'litespeed' => $litespeed_status,
        'mysql' => $mysql_status,
        'system' => $system_resources,
        'stats' => [
            'wordpress_sites' => $wp_sites_count,
            'domains' => $domains_count,
            'users' => $users_count
        ]
    ];
}