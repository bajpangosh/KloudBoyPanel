<?php
/**
 * Profile Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Get user information
$db = Database::getInstance();
$user = $db->fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$current_user['id']]
);

if (!$user) {
    header('Location: index.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid form submission, please try again.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if ($action === 'update_profile') {
            // Validate email
            $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) : '';
            
            if (!$email) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if email already exists for another user
                $existing_email = $db->fetchOne(
                    "SELECT id FROM users WHERE email = ? AND id != ?",
                    [$email, $user['id']]
                );
                
                if ($existing_email) {
                    $error = 'Email address already exists.';
                } else {
                    try {
                        // Update user
                        $db->update(
                            "UPDATE users SET email = ? WHERE id = ?",
                            [$email, $user['id']]
                        );
                        
                        // Log the action
                        log_action($user['id'], 'update_profile', 'Updated profile information');
                        
                        // Update session
                        $_SESSION['email'] = $email;
                        
                        // Update local user variable
                        $user['email'] = $email;
                        
                        // Set success message
                        $success = 'Profile updated successfully';
                    } catch (Exception $e) {
                        $error = 'Failed to update profile: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'change_password') {
            // Validate current password
            $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
            
            if (empty($current_password)) {
                $error = 'Current password is required.';
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                // Validate new password
                $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
                $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
                
                if (empty($new_password)) {
                    $error = 'New password is required.';
                } elseif (strlen($new_password) < 8) {
                    $error = 'New password must be at least 8 characters long.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New password and confirmation do not match.';
                } else {
                    try {
                        // Hash new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        // Update user
                        $db->update(
                            "UPDATE users SET password = ? WHERE id = ?",
                            [$hashed_password, $user['id']]
                        );
                        
                        // Log the action
                        log_action($user['id'], 'change_password', 'Changed password');
                        
                        // Set success message
                        $success = 'Password changed successfully';
                    } catch (Exception $e) {
                        $error = 'Failed to change password: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Get user statistics
$domains_count = 0;
$wordpress_count = 0;

$domains_result = $db->fetchOne(
    "SELECT COUNT(*) as count FROM domains WHERE user_id = ?",
    [$user['id']]
);
if ($domains_result) {
    $domains_count = $domains_result['count'];
}

$wp_result = $db->fetchOne(
    "SELECT COUNT(*) as count FROM wordpress_sites ws
     JOIN domains d ON ws.domain_id = d.id
     WHERE d.user_id = ?",
    [$user['id']]
);
if ($wp_result) {
    $wordpress_count = $wp_result['count'];
}

// Get recent activity
$recent_activity = $db->fetchAll(
    "SELECT * FROM logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
    [$user['id']]
);

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>My Profile</h2>
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
    <div class="col-md-4 mb-4">
        <!-- User Information -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">User Information</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar-circle mx-auto mb-3">
                        <span class="avatar-initials"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                    </div>
                    <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'success'; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </div>
                
                <div class="system-info-item">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="system-info-item">
                    <strong>Account Created:</strong>
                    <span><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                </div>
                <div class="system-info-item">
                    <strong>Last Login:</strong>
                    <span><?php echo $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></span>
                </div>
            </div>
        </div>
        
        <!-- User Statistics -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">Account Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h2 class="mb-0"><?php echo $domains_count; ?></h2>
                        <small class="text-muted">Domains</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h2 class="mb-0"><?php echo $wordpress_count; ?></h2>
                        <small class="text-muted">WordPress Sites</small>
                    </div>
                </div>
                
                <div class="d-grid gap-2 mt-3">
                    <a href="index.php?page=domains" class="btn btn-outline-primary">
                        <i class="fas fa-globe me-2"></i> Manage Domains
                    </a>
                    <a href="index.php?page=wordpress" class="btn btn-outline-success">
                        <i class="fab fa-wordpress me-2"></i> Manage WordPress
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Update Profile -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Update Profile</h5>
            </div>
            <div class="card-body">
                <form method="post" action="index.php?page=profile">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                        <div class="form-text">Username cannot be changed</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label required">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
                        <div class="form-text">Enter a valid email address</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <form method="post" action="index.php?page=profile">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label required">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                        <div class="form-text">Enter your current password</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label required">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">Enter a strong password (minimum 8 characters)</div>
                        <div class="progress mt-2" style="height: 5px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 0%;" id="password_strength"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label required">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div class="form-text">Confirm your new password</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="card-title mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activity)): ?>
                <p class="text-muted">No recent activity found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>IP Address</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activity as $activity): ?>
                            <tr>
                                <td><?php echo date('M j, Y H:i:s', strtotime($activity['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                <td><?php echo htmlspecialchars($activity['description']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #007bff;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.avatar-initials {
    color: white;
    font-size: 48px;
    font-weight: bold;
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password strength meter
        const passwordInput = document.getElementById('new_password');
        const passwordStrength = document.getElementById('password_strength');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (passwordInput && passwordStrength) {
            passwordInput.addEventListener('input', function() {
                updatePasswordStrength(this.value);
                
                // Check password match
                if (confirmPassword.value) {
                    checkPasswordMatch();
                }
            });
        }
        
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                checkPasswordMatch();
            });
        }
        
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
        
        function checkPasswordMatch() {
            if (passwordInput.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
    });
</script>