<?php
/**
 * Add Domain Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid form submission, please try again.';
    } else {
        // Validate domain name
        $domain_name = isset($_POST['domain_name']) ? sanitize_input($_POST['domain_name']) : '';
        
        if (empty($domain_name)) {
            $error = 'Domain name is required.';
        } elseif (!validate_domain($domain_name)) {
            $error = 'Invalid domain name format.';
        } else {
            $db = Database::getInstance();
            
            // Check if domain already exists
            $existing = $db->fetchOne(
                "SELECT id FROM domains WHERE domain_name = ?",
                [$domain_name]
            );
            
            if ($existing) {
                $error = 'Domain already exists.';
            } else {
                // For regular users, check if they've reached their domain limit
                if (!is_admin()) {
                    // Get max domains per user from settings
                    $max_domains = 10; // Default
                    $setting = $db->fetchOne(
                        "SELECT setting_value FROM settings WHERE setting_key = 'max_domains_per_user'"
                    );
                    
                    if ($setting) {
                        $max_domains = (int)$setting['setting_value'];
                    }
                    
                    // Count user's domains
                    $count = $db->fetchOne(
                        "SELECT COUNT(*) as count FROM domains WHERE user_id = ?",
                        [$current_user['id']]
                    );
                    
                    if ($count && (int)$count['count'] >= $max_domains) {
                        $error = 'You have reached the maximum number of domains allowed.';
                    }
                }
                
                if (!isset($error)) {
                    // Determine user_id (admin can create domains for other users)
                    $user_id = $current_user['id'];
                    if (is_admin() && isset($_POST['user_id']) && !empty($_POST['user_id'])) {
                        // Verify the user exists
                        $target_user = $db->fetchOne(
                            "SELECT id FROM users WHERE id = ?",
                            [(int)$_POST['user_id']]
                        );
                        
                        if ($target_user) {
                            $user_id = (int)$_POST['user_id'];
                        }
                    }
                    
                    // Set document root
                    $document_root = WP_SITES_PATH . '/' . $domain_name;
                    
                    // Set status (admin can set initial status)
                    $status = 'pending';
                    if (is_admin() && isset($_POST['status']) && 
                        in_array($_POST['status'], ['active', 'suspended', 'pending'])) {
                        $status = $_POST['status'];
                    }
                    
                    try {
                        // Begin transaction
                        $db->beginTransaction();
                        
                        // Insert domain
                        $domain_id = $db->insert(
                            "INSERT INTO domains (user_id, domain_name, document_root, status, created_at) 
                             VALUES (?, ?, ?, ?, NOW())",
                            [$user_id, $domain_name, $document_root, $status]
                        );
                        
                        // Log the action
                        log_action($current_user['id'], 'create_domain', 'Created domain: ' . $domain_name);
                        
                        // Commit transaction
                        $db->commit();
                        
                        // Redirect to domain list with success message
                        header('Location: index.php?page=domains&success=' . urlencode('Domain added successfully'));
                        exit;
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $db->rollback();
                        $error = 'Failed to add domain: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Get users for admin
$users = [];
if (is_admin()) {
    $db = Database::getInstance();
    $users = $db->fetchAll("SELECT id, username, email FROM users ORDER BY username");
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Add Domain</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=domains" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Domains
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
        <h5 class="card-title mb-0">Domain Information</h5>
    </div>
    <div class="card-body">
        <form method="post" action="index.php?page=domains&action=add">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="domain_name" class="form-label required">Domain Name</label>
                <input type="text" class="form-control" id="domain_name" name="domain_name" required value="<?php echo isset($_POST['domain_name']) ? htmlspecialchars($_POST['domain_name']) : ''; ?>">
                <div class="form-text">Enter the domain name without http:// or www (e.g., example.com)</div>
            </div>
            
            <?php if (is_admin()): ?>
            <div class="mb-3">
                <label for="user_id" class="form-label">Owner</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="<?php echo $current_user['id']; ?>">Me (<?php echo htmlspecialchars($current_user['username']); ?>)</option>
                    <?php foreach ($users as $user): ?>
                        <?php if ($user['id'] != $current_user['id']): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Select the user who will own this domain</div>
            </div>
            
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="pending" <?php echo (!isset($_POST['status']) || $_POST['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo (isset($_POST['status']) && $_POST['status'] == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                </select>
                <div class="form-text">Set the initial status of the domain</div>
            </div>
            <?php endif; ?>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="install_wordpress" name="install_wordpress" <?php echo (isset($_POST['install_wordpress'])) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="install_wordpress">Install WordPress after domain creation</label>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i> Add Domain
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Show WordPress installation options when checkbox is checked
    document.addEventListener('DOMContentLoaded', function() {
        const installWordPress = document.getElementById('install_wordpress');
        
        installWordPress.addEventListener('change', function() {
            if (this.checked) {
                // Redirect to WordPress installation page after domain creation
                this.form.action = 'index.php?page=domains&action=add&install_wp=1';
            } else {
                this.form.action = 'index.php?page=domains&action=add';
            }
        });
    });
</script>