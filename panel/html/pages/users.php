<?php
/**
 * Users Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Check if user is admin
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

// Get action from URL
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Get user ID from URL if available
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle different actions
switch ($action) {
    case 'add':
        include 'users/add.php';
        break;
    
    case 'edit':
        include 'users/edit.php';
        break;
    
    case 'view':
        include 'users/view.php';
        break;
    
    case 'delete':
        include 'users/delete.php';
        break;
    
    default:
        // Get users from database
        $db = Database::getInstance();
        $users = $db->fetchAll(
            "SELECT u.*, 
                    (SELECT COUNT(*) FROM domains d WHERE d.user_id = u.id) as domains_count,
                    (SELECT COUNT(*) FROM wordpress_sites ws JOIN domains d ON ws.domain_id = d.id WHERE d.user_id = u.id) as wordpress_count
             FROM users u
             ORDER BY u.username"
        );
?>
<div class="row mb-4">
    <div class="col-md-6">
        <h2>Users</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php?page=users&action=add" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i> Add User
        </a>
    </div>
</div>

<?php if (empty($users)): ?>
<div class="alert alert-info" role="alert">
    <i class="fas fa-info-circle me-2"></i> No users found. Click the "Add User" button to create your first user.
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Domains</th>
                        <th>WordPress Sites</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr id="user-<?php echo $user['id']; ?>">
                        <td>
                            <a href="index.php?page=users&action=view&id=<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'success'; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo $user['domains_count']; ?></td>
                        <td><?php echo $user['wordpress_count']; ?></td>
                        <td><?php echo $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                        <td class="user-actions">
                            <a href="index.php?page=users&action=view&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View User">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="index.php?page=users&action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit User">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($user['id'] != $current_user['id']): ?>
                            <a href="index.php?page=users&action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger delete-btn" data-bs-toggle="tooltip" title="Delete User" data-id="<?php echo $user['id']; ?>" data-type="user" data-name="<?php echo htmlspecialchars($user['username']); ?>">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
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