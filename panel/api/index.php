<?php
/**
 * API Entry Point
 * WordPress Hosting Panel with LiteSpeed
 */

// Load configuration
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable CORS for API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ' . API_KEY_HEADER);

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize session
init_session();

// Parse request
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/panel/api';
$path = parse_url($request_uri, PHP_URL_PATH);
$path = substr($path, strlen($base_path));
$method = $_SERVER['REQUEST_METHOD'];

// Get request body for POST, PUT requests
$body = null;
if ($method === 'POST' || $method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
}

// API authentication
function authenticate_api_request() {
    // Check for API key in header
    $api_key = isset($_SERVER['HTTP_' . str_replace('-', '_', strtoupper(API_KEY_HEADER))]) 
        ? $_SERVER['HTTP_' . str_replace('-', '_', strtoupper(API_KEY_HEADER))] 
        : null;
    
    if (!$api_key) {
        return false;
    }
    
    // Verify API key
    $db = Database::getInstance();
    $user = $db->fetchOne(
        "SELECT id, username, role FROM users WHERE api_key = ? AND status = 'active'",
        [$api_key]
    );
    
    if (!$user) {
        return false;
    }
    
    // Store user info in request
    $_REQUEST['api_user'] = $user;
    return true;
}

// API response helper
function api_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// API error response helper
function api_error($message, $status_code = 400) {
    api_response(['error' => $message], $status_code);
}

// Rate limiting
function check_rate_limit() {
    $ip = get_client_ip();
    $key = 'rate_limit:' . $ip;
    
    // Simple in-memory rate limiting
    // In production, use Redis or similar for distributed rate limiting
    static $rate_limits = [];
    
    if (!isset($rate_limits[$key])) {
        $rate_limits[$key] = [
            'count' => 0,
            'reset_time' => time() + 60
        ];
    }
    
    // Reset counter if time expired
    if (time() > $rate_limits[$key]['reset_time']) {
        $rate_limits[$key] = [
            'count' => 0,
            'reset_time' => time() + 60
        ];
    }
    
    // Increment counter
    $rate_limits[$key]['count']++;
    
    // Check if limit exceeded
    if ($rate_limits[$key]['count'] > API_RATE_LIMIT) {
        api_error('Rate limit exceeded. Try again later.', 429);
    }
}

// Check if API is enabled
if (!API_ENABLED) {
    api_error('API is disabled', 503);
}

// Apply rate limiting
check_rate_limit();

// Route the request
$routes = [
    // Domain endpoints
    '/domains' => [
        'GET' => 'get_domains',
        'POST' => 'create_domain'
    ],
    '/domains/(\d+)' => [
        'GET' => 'get_domain',
        'PUT' => 'update_domain',
        'DELETE' => 'delete_domain'
    ],
    
    // WordPress endpoints
    '/wordpress' => [
        'GET' => 'get_wordpress_sites',
        'POST' => 'install_wordpress'
    ],
    '/wordpress/(\d+)' => [
        'GET' => 'get_wordpress_site',
        'PUT' => 'update_wordpress_site',
        'DELETE' => 'delete_wordpress_site'
    ],
    
    // User endpoints
    '/users' => [
        'GET' => 'get_users',
        'POST' => 'create_user'
    ],
    '/users/(\d+)' => [
        'GET' => 'get_user',
        'PUT' => 'update_user',
        'DELETE' => 'delete_user'
    ],
    
    // Statistics endpoints
    '/statistics/(\d+)' => [
        'GET' => 'get_statistics'
    ],
    
    // System endpoints
    '/system/status' => [
        'GET' => 'get_system_status'
    ],
    '/system/restart-litespeed' => [
        'POST' => 'restart_litespeed'
    ]
];

// Find matching route
$matched = false;
foreach ($routes as $route => $handlers) {
    $pattern = '#^' . $route . '$#';
    if (preg_match($pattern, $path, $matches)) {
        if (isset($handlers[$method])) {
            $handler = $handlers[$method];
            array_shift($matches); // Remove full match
            
            // Require authentication for all endpoints except system status
            if ($path !== '/system/status' && !authenticate_api_request()) {
                api_error('Unauthorized', 401);
            }
            
            // Include the appropriate handler file
            $handler_file = __DIR__ . '/handlers/' . $handler . '.php';
            if (file_exists($handler_file)) {
                require_once $handler_file;
                
                // Call the handler function
                $result = call_user_func_array($handler, array_merge([$body], $matches));
                api_response($result);
            } else {
                api_error('Handler not implemented', 501);
            }
            
            $matched = true;
            break;
        } else {
            api_error('Method not allowed', 405);
        }
    }
}

if (!$matched) {
    api_error('Endpoint not found', 404);
}