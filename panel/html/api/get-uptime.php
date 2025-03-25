<?php
/**
 * Get Server Uptime API
 * WordPress Hosting Panel with LiteSpeed
 */

// Load configuration
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Get server uptime
function get_server_uptime() {
    $uptime = '';
    
    // Try to get uptime from /proc/uptime
    if (file_exists('/proc/uptime')) {
        $uptime_file = file_get_contents('/proc/uptime');
        $uptime_array = explode(' ', $uptime_file);
        $uptime_seconds = (float)$uptime_array[0];
        
        // Format uptime
        $uptime = format_uptime($uptime_seconds);
    } else {
        // Try using uptime command
        exec('uptime', $output);
        if (!empty($output)) {
            $uptime = $output[0];
            
            // Extract uptime from command output
            if (preg_match('/up\s+(.*?),\s+\d+\s+user/', $uptime, $matches)) {
                $uptime = $matches[1];
            } elseif (preg_match('/up\s+(.*?),\s+load/', $uptime, $matches)) {
                $uptime = $matches[1];
            }
        }
    }
    
    return $uptime ?: 'Unknown';
}

// Format uptime in seconds to human-readable format
function format_uptime($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $uptime = '';
    
    if ($days > 0) {
        $uptime .= $days . ' day' . ($days > 1 ? 's' : '') . ', ';
    }
    
    if ($hours > 0) {
        $uptime .= $hours . ' hour' . ($hours > 1 ? 's' : '') . ', ';
    }
    
    if ($minutes > 0) {
        $uptime .= $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    }
    
    return $uptime ?: 'Less than a minute';
}

// Get uptime
$uptime = get_server_uptime();

// Return response
echo json_encode([
    'status' => 'success',
    'uptime' => $uptime
]);