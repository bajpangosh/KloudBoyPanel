<?php
/**
 * Main Entry Point
 * WordPress Hosting Panel with LiteSpeed
 */

// Load configuration
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// Initialize session
init_session();

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Redirect to login if not logged in
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// Get current user
function get_current_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = Database::getInstance();
    return $db->fetchOne(
        "SELECT * FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
}

// Check if user is admin
function is_admin() {
    $user = get_current_user();
    return $user && $user['role'] === 'admin';
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Require login for all pages except login and register
$public_pages = ['login.php', 'register.php', 'forgot-password.php', 'reset-password.php'];
$current_page = basename($_SERVER['SCRIPT_NAME']);

if (!in_array($current_page, $public_pages)) {
    require_login();
}

// Get current user
$current_user = get_current_user();

// Define page title and active menu
$page_title = 'Dashboard';
$active_menu = 'dashboard';

// Get page from URL
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Validate page access
$admin_only_pages = ['users', 'settings', 'system'];
if (in_array($page, $admin_only_pages) && !is_admin()) {
    $page = 'dashboard';
}

// Set page title and active menu based on page
switch ($page) {
    case 'dashboard':
        $page_title = 'Dashboard';
        $active_menu = 'dashboard';
        break;
    case 'domains':
        $page_title = 'Domains';
        $active_menu = 'domains';
        break;
    case 'wordpress':
        $page_title = 'WordPress Sites';
        $active_menu = 'wordpress';
        break;
    case 'users':
        $page_title = 'Users';
        $active_menu = 'users';
        break;
    case 'settings':
        $page_title = 'Settings';
        $active_menu = 'settings';
        break;
    case 'system':
        $page_title = 'System Status';
        $active_menu = 'system';
        break;
    case 'profile':
        $page_title = 'My Profile';
        $active_menu = 'profile';
        break;
    default:
        $page = 'dashboard';
        $page_title = 'Dashboard';
        $active_menu = 'dashboard';
}

// Include header
include 'templates/header.php';

// Include page content
include 'pages/' . $page . '.php';

// Include footer
include 'templates/footer.php';