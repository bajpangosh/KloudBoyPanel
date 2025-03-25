<?php
/**
 * Application Configuration
 * WordPress Hosting Panel with LiteSpeed
 */

// Application settings
define('APP_NAME', 'WordPress Hosting Panel');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/panel');
define('APP_ROOT', dirname(__DIR__));
define('APP_DEBUG', false);

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'wp_panel_session');

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_COST', 12);

// File paths
define('TEMPLATES_PATH', APP_ROOT . '/html/templates');
define('UPLOADS_PATH', APP_ROOT . '/html/uploads');
define('LOGS_PATH', APP_ROOT . '/logs');
define('BACKUPS_PATH', '/var/backups/wp-panel');

// WordPress settings
define('WP_DOWNLOAD_URL', 'https://wordpress.org/latest.tar.gz');
define('WP_SITES_PATH', '/var/www');
define('WP_DEFAULT_PLUGINS', [
    'litespeed-cache' => 'https://downloads.wordpress.org/plugin/litespeed-cache.latest-stable.zip',
    'wordfence' => 'https://downloads.wordpress.org/plugin/wordfence.latest-stable.zip',
    'wp-mail-smtp' => 'https://downloads.wordpress.org/plugin/wp-mail-smtp.latest-stable.zip'
]);

// LiteSpeed settings
define('LITESPEED_BIN_PATH', '/usr/local/lsws/bin');
define('LITESPEED_CONF_PATH', '/usr/local/lsws/conf');
define('LITESPEED_VHOSTS_PATH', '/usr/local/lsws/conf/vhosts');

// MySQL settings
define('MYSQL_BIN_PATH', '/usr/bin');

// System commands
define('CMD_RESTART_LITESPEED', LITESPEED_BIN_PATH . '/lswsctrl restart');
define('CMD_RELOAD_LITESPEED', LITESPEED_BIN_PATH . '/lswsctrl reload');
define('CMD_CHECK_LITESPEED', LITESPEED_BIN_PATH . '/lswsctrl status');

// API settings
define('API_ENABLED', true);
define('API_KEY_HEADER', 'X-API-Key');
define('API_RATE_LIMIT', 100); // requests per minute

// Load environment-specific configuration
$env_config = __DIR__ . '/env.php';
if (file_exists($env_config)) {
    require_once $env_config;
}

// Initialize error handling
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Initialize session
function init_session() {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
    
    // Regenerate session ID periodically to prevent session fixation
    if (!isset($_SESSION['last_regeneration']) || 
        (time() - $_SESSION['last_regeneration']) > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Initialize CSRF protection
function generate_csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Verify CSRF token
function verify_csrf_token($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || $token !== $_SESSION[CSRF_TOKEN_NAME]) {
        return false;
    }
    return true;
}

// Helper function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Helper function to validate domain name
function validate_domain($domain) {
    return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain) // valid chars check
            && preg_match("/^.{1,253}$/", $domain) // overall length check
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain)); // length of each label
}

// Helper function to get client IP address
function get_client_ip() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// Helper function to log actions
function log_action($user_id, $action, $description = '') {
    $db = Database::getInstance();
    $db->insert(
        "INSERT INTO logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())",
        [$user_id, $action, $description, get_client_ip()]
    );
}