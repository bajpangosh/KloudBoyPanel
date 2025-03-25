<?php
/**
 * WordPress Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Get action from URL
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Get WordPress site ID from URL if available
$wp_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get domain ID from URL if available (for installation)
$domain_id = isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0;

// Handle different actions
switch ($action) {
    case 'install':
        include 'wordpress/install.php';
        break;
    
    case 'view':
        include 'wordpress/view.php';
        break;
    
    case 'update':
        include 'wordpress/update.php';
        break;
    
    case 'backup':
        include 'wordpress/backup.php';
        break;
    
    case 'restore':
        include 'wordpress/restore.php';
        break;
    
    case 'delete':
        include 'wordpress/delete.php';
        break;
    
    default:
        // Get WordPress sites from database
        $db = Database::getInstance();
        
        if (is_admin()) {
            $wordpress_sites = $db->fetchAll(
                "SELECT ws.*, d.domain_name, d.status as domain_status, u.username as owner
                 FROM wordpress_sites ws
                 JOIN domains d ON ws.domain_id = d.id
                 JOIN users u ON d.user_id = u.id
                 ORDER BY ws.installed_at DESC"
            );
        } else {
            $wordpress_sites = $db->fetchAll(
                "SELECT ws.*, d.domain_name, d.status as domain_status, u.username as owner
                 FROM wordpress_sites ws
                 JOIN domains d ON ws.domain_id = d.id
                 JOIN users u ON d.user_id = u.id
                 WHERE d.user_id = ?
                 ORDER BY ws.installed_at DESC",
                [$current_user['id']]
            );
        }
        
        // Format WordPress sites data
        foreach ($wordpress_sites as &$site) {
            $site['installed_at'] = date('Y-m-d H:i:s', strtotime($site['installed_at']));
            if ($site['updated_at']) {
                $site['updated_at'] = date('Y-m-d H:i:s', strtotime($site['updated_at']));
            }
        }
        
        // Get domains without WordPress for installation
        if (is_admin()) {
            $available_domains = $db->fetchAll(
                "SELECT d.id, d.domain_name, u.username as owner
                 FROM domains d
                 JOIN users u ON d.user_id = u.id
                 WHERE d.status = 'active'
                 AND NOT EXISTS (
                     SELECT 1 FROM wordpress_sites ws WHERE ws.domain_id = d.id
                 )
                 ORDER BY d.domain_name"
            );
        } else {
            $available_domains = $db->fetchAll(
                "SELECT d.id, d.domain_name, u.username as owner
                 FROM domains d
                 JOIN users u ON d.user_id = u.id
                 WHERE d.user_id = ?
                 AND d.status = 'active'
                 AND NOT EXISTS (
                     SELECT 1 FROM wordpress_sites ws WHERE ws.domain_id = d.id
                 )
                 ORDER BY d.domain_name",
                [$current_user['id']]
            );
        }
?>
<div class="row mb-4">
    <div class="col-md-6">
        <h2>WordPress Sites</h2>
    </div>
    <div class="col-md-6 text-end">
        <?php if (!empty($available_domains)): ?>
        <a href="index.php?page=wordpress&action=install" class="btn btn-primary">
            <i class="fab fa-wordpress me-2"></i> Install WordPress
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($wordpress_sites)): ?>
<div class="alert alert-info" role="alert">
    <i class="fas fa-info-circle me-2"></i> No WordPress sites found.
    <?php if (!empty($available_domains)): ?>
    Click the "Install WordPress" button to install WordPress on one of your domains.
    <?php else: ?>
    You need to <a href="index.php?page=domains&action=add">add a domain</a> before you can install WordPress.
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <?php if (is_admin()): ?>
                        <th>Owner</th>
                        <?php endif; ?>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Installed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wordpress_sites as $site): ?>
                    <tr id="wordpress-<?php echo $site['id']; ?>">
                        <td>
                            <a href="index.php?page=wordpress&action=view&id=<?php echo $site['id']; ?>">
                                <?php echo htmlspecialchars($site['domain_name']); ?>
                            </a>
                        </td>
                        <?php if (is_admin()): ?>
                        <td><?php echo htmlspecialchars($site['owner']); ?></td>
                        <?php endif; ?>
                        <td><?php echo $site['wp_version'] ? htmlspecialchars($site['wp_version']) : 'Unknown'; ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $site['status'] === 'active' ? 'success' : 'danger'; 
                            ?> status-badge">
                                <?php echo ucfirst($site['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($site['installed_at'])); ?></td>
                        <td class="wordpress-actions">
                            <a href="index.php?page=wordpress&action=view&id=<?php echo $site['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Site">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="http://<?php echo htmlspecialchars($site['domain_name']); ?>/wp-admin/" target="_blank" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="WordPress Admin">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <a href="index.php?page=wordpress&action=update&id=<?php echo $site['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Update WordPress">
                                <i class="fas fa-sync-alt"></i>
                            </a>
                            <a href="index.php?page=wordpress&action=backup&id=<?php echo $site['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Backup Site">
                                <i class="fas fa-download"></i>
                            </a>
                            <a href="index.php?page=wordpress&action=delete&id=<?php echo $site['id']; ?>" class="btn btn-sm btn-danger delete-btn" data-bs-toggle="tooltip" title="Delete WordPress" data-id="<?php echo $site['id']; ?>" data-type="wordpress" data-name="WordPress on <?php echo htmlspecialchars($site['domain_name']); ?>">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($available_domains)): ?>
<div class="card mt-4">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">Available Domains for WordPress Installation</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <?php if (is_admin()): ?>
                        <th>Owner</th>
                        <?php endif; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($available_domains as $domain): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($domain['domain_name']); ?></td>
                        <?php if (is_admin()): ?>
                        <td><?php echo htmlspecialchars($domain['owner']); ?></td>
                        <?php endif; ?>
                        <td>
                            <a href="index.php?page=wordpress&action=install&domain_id=<?php echo $domain['id']; ?>" class="btn btn-sm btn-success">
                                <i class="fab fa-wordpress me-2"></i> Install WordPress
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
<?php
        break;
}
?>