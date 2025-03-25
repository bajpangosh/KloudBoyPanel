<?php
/**
 * Restart MariaDB Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Check if user is admin
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

// Process restart request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid form submission, please try again.';
    } else {
        // Execute restart command
        exec('systemctl restart mariadb 2>&1', $output, $return_var);
        
        if ($return_var === 0) {
            // Log the action
            log_action($current_user['id'], 'restart_mariadb', 'Restarted MariaDB Database Server');
            
            // Redirect to system page with success message
            header('Location: index.php?page=system&success=' . urlencode('MariaDB Database Server restarted successfully'));
            exit;
        } else {
            $error = 'Failed to restart MariaDB Database Server: ' . implode("\n", $output);
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Restart MariaDB Database Server</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=system" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to System Status
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-warning text-dark">
        <h5 class="card-title mb-0">Confirm Restart</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> <strong>Warning:</strong> Restarting the MariaDB Database Server will temporarily interrupt all database services. This operation may take a few seconds to complete.
        </div>
        
        <p>Are you sure you want to restart the MariaDB Database Server?</p>
        
        <form method="post" action="index.php?page=system&action=restart-mariadb">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="d-flex justify-content-between">
                <a href="index.php?page=system" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-sync-alt me-2"></i> Restart MariaDB
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Current Status -->
<div class="card mt-4">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">Current MariaDB Status</h5>
    </div>
    <div class="card-body">
        <?php
        // Get MariaDB status
        exec('systemctl status mariadb', $output, $return_var);
        $is_running = ($return_var === 0);
        
        // Get MariaDB version
        $version = '';
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne("SELECT VERSION() as version");
            if ($result) {
                $version = $result['version'];
            }
        } catch (Exception $e) {
            // Database error, leave default value
        }
        ?>
        
        <div class="d-flex align-items-center mb-3">
            <div class="me-3">
                <span class="badge bg-<?php echo $is_running ? 'success' : 'danger'; ?> p-2">
                    <i class="fas fa-<?php echo $is_running ? 'check' : 'times'; ?> fa-lg"></i>
                </span>
            </div>
            <div>
                <h5 class="mb-0"><?php echo $is_running ? 'Running' : 'Stopped'; ?></h5>
                <small class="text-muted"><?php echo $version; ?></small>
            </div>
        </div>
        
        <?php if ($is_running): ?>
        <div class="system-info-item">
            <strong>Status Output:</strong>
        </div>
        <pre class="bg-light p-3 mt-2" style="max-height: 300px; overflow-y: auto;"><?php echo implode("\n", $output); ?></pre>
        <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i> MariaDB Database Server is not running.
        </div>
        <?php endif; ?>
    </div>
</div>