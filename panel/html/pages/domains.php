<?php
/**
 * Domains Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Get action from URL
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Get domain ID from URL if available
$domain_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle different actions
switch ($action) {
    case 'add':
        include 'domains/add.php';
        break;
    
    case 'edit':
        include 'domains/edit.php';
        break;
    
    case 'view':
        include 'domains/view.php';
        break;
    
    case 'delete':
        include 'domains/delete.php';
        break;
    
    default:
        // Get domains from database
        $db = Database::getInstance();
        
        if (is_admin()) {
            $domains = $db->fetchAll(
                "SELECT d.*, u.username as owner, 
                        (SELECT COUNT(*) FROM wordpress_sites ws WHERE ws.domain_id = d.id) as has_wordpress
                 FROM domains d
                 LEFT JOIN users u ON d.user_id = u.id
                 ORDER BY d.created_at DESC"
            );
        } else {
            $domains = $db->fetchAll(
                "SELECT d.*, u.username as owner, 
                        (SELECT COUNT(*) FROM wordpress_sites ws WHERE ws.domain_id = d.id) as has_wordpress
                 FROM domains d
                 LEFT JOIN users u ON d.user_id = u.id
                 WHERE d.user_id = ?
                 ORDER BY d.created_at DESC",
                [$current_user['id']]
            );
        }
        
        // Format domains data
        foreach ($domains as &$domain) {
            $domain['has_wordpress'] = (bool)$domain['has_wordpress'];
            $domain['created_at'] = date('Y-m-d H:i:s', strtotime($domain['created_at']));
            if ($domain['updated_at']) {
                $domain['updated_at'] = date('Y-m-d H:i:s', strtotime($domain['updated_at']));
            }
        }
?>
<div class="row mb-4">
    <div class="col-md-6">
        <h2>Domains</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=domains&action=add" class="btn btn-primary">
            <i class="fas fa-plus-circle me-2"></i> Add Domain
        </a>
    </div>
</div>

<?php if (empty($domains)): ?>
<div class="alert alert-info" role="alert">
    <i class="fas fa-info-circle me-2"></i> No domains found. Click the "Add Domain" button to create your first domain.
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Domain Name</th>
                        <?php if (is_admin()): ?>
                        <th>Owner</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th>WordPress</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domains as $domain): ?>
                    <tr id="domain-<?php echo $domain['id']; ?>">
                        <td>
                            <a href="index.php?page=domains&action=view&id=<?php echo $domain['id']; ?>">
                                <?php echo htmlspecialchars($domain['domain_name']); ?>
                            </a>
                        </td>
                        <?php if (is_admin()): ?>
                        <td><?php echo htmlspecialchars($domain['owner']); ?></td>
                        <?php endif; ?>
                        <td>
                            <span class="badge bg-<?php 
                                echo $domain['status'] === 'active' ? 'success' : 
                                    ($domain['status'] === 'suspended' ? 'danger' : 'warning'); 
                            ?> status-badge">
                                <?php echo ucfirst($domain['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($domain['has_wordpress']): ?>
                            <span class="badge bg-success">Installed</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Not Installed</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($domain['created_at'])); ?></td>
                        <td class="domain-actions">
                            <a href="index.php?page=domains&action=view&id=<?php echo $domain['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Domain">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="index.php?page=domains&action=edit&id=<?php echo $domain['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit Domain">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if (!$domain['has_wordpress']): ?>
                            <a href="index.php?page=wordpress&action=install&domain_id=<?php echo $domain['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Install WordPress">
                                <i class="fab fa-wordpress"></i>
                            </a>
                            <?php endif; ?>
                            <a href="index.php?page=domains&action=delete&id=<?php echo $domain['id']; ?>" class="btn btn-sm btn-danger delete-btn" data-bs-toggle="tooltip" title="Delete Domain" data-id="<?php echo $domain['id']; ?>" data-type="domain" data-name="<?php echo htmlspecialchars($domain['domain_name']); ?>">
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