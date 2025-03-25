<?php
/**
 * Login Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Load configuration
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// Initialize session
init_session();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Process login form
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid form submission, please try again.';
    } else {
        // Validate credentials
        $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required.';
        } else {
            // Check credentials
            $db = Database::getInstance();
            $user = $db->fetchOne(
                "SELECT * FROM users WHERE username = ?",
                [$username]
            );
            
            if ($user) {
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Update last login
                    $db->update(
                        "UPDATE users SET last_login = NOW() WHERE id = ?",
                        [$user['id']]
                    );
                    
                    // Log the action
                    log_action($user['id'], 'login', 'User logged in');
                    
                    // Redirect to dashboard
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Page title
$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white text-center">
                        <h4><?php echo APP_NAME; ?></h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title text-center mb-4"><?php echo $page_title; ?></h5>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="login.php">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <a href="forgot-password.php" class="text-decoration-none">Forgot Password?</a>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0">Powered by <a href="https://litespeedtech.com/" target="_blank" class="text-decoration-none">LiteSpeed</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scripts.js"></script>
</body>
</html>