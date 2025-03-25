<?php
/**
 * Add User Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Check if user is admin
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid form submission, please try again.';
    } else {
        // Validate username
        $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
        
        if (empty($username)) {
            $error = 'Username is required.';
        } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters long.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Username can only contain letters, numbers, and underscores.';
        } else {
            // Validate email
            $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) : '';
            
            if (!$email) {
                $error = 'Please enter a valid email address.';
            } else {
                // Validate password
                $password = isset($_POST['password']) ? $_POST['password'] : '';
                
                if (empty($password)) {
                    $error = 'Password is required.';
                } elseif (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters long.';
                } else {
                    // Validate role
                    $role = isset($_POST['role']) ? $_POST['role'] : '';
                    
                    if (!in_array($role, ['admin', 'user'])) {
                        $error = 'Please select a valid role.';
                    } else {
                        $db = Database::getInstance();
                        
                        // Check if username already exists
                        $existing_username = $db->fetchOne(
                            "SELECT id FROM users WHERE username = ?",
                            [$username]
                        );
                        
                        if ($existing_username) {
                            $error = 'Username already exists.';
                        } else {
                            // Check if email already exists
                            $existing_email = $db->fetchOne(
                                "SELECT id FROM users WHERE email = ?",
                                [$email]
                            );
                            
                            if ($existing_email) {
                                $error = 'Email address already exists.';
                            } else {
                                try {
                                    // Hash password
                                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                    
                                    // Insert user
                                    $user_id = $db->insert(
                                        "INSERT INTO users (username, password, email, role, created_at) 
                                         VALUES (?, ?, ?, ?, NOW())",
                                        [$username, $hashed_password, $email, $role]
                                    );
                                    
                                    // Log the action
                                    log_action($current_user['id'], 'create_user', 'Created user: ' . $username);
                                    
                                    // Redirect to users list with success message
                                    header('Location: index.php?page=users&success=' . urlencode('User added successfully'));
                                    exit;
                                } catch (Exception $e) {
                                    $error = 'Failed to add user: ' . $e->getMessage();
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
        <h2>Add User</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=users" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Users
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
        <h5 class="card-title mb-0">User Information</h5>
    </div>
    <div class="card-body">
        <form method="post" action="index.php?page=users&action=add">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="username" class="form-label required">Username</label>
                <input type="text" class="form-control" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                <div class="form-text">Enter a unique username (letters, numbers, and underscores only)</div>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label required">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <div class="form-text">Enter a valid email address</div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label required">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required value="<?php echo isset($_POST['password']) ? $_POST['password'] : $random_password; ?>">
                    <button class="btn btn-outline-secondary" type="button" id="show_password">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-outline-secondary" type="button" id="generate_password">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="form-text">Enter a strong password (minimum 8 characters)</div>
                <div class="progress mt-2" style="height: 5px;">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: 0%;" id="password_strength"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="role" class="form-label required">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="user" <?php echo (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                </select>
                <div class="form-text">
                    <strong>User:</strong> Can manage their own domains and WordPress sites<br>
                    <strong>Administrator:</strong> Has full access to all features and settings
                </div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i> Add User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password visibility toggle
        const passwordInput = document.getElementById('password');
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