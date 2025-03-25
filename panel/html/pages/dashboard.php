<?php
/**
 * Dashboard Page
 * WordPress Hosting Panel with LiteSpeed
 */

// Get user's domains count
$db = Database::getInstance();
$domains_count = 0;

if (is_admin()) {
    $domains_result = $db->fetchOne("SELECT COUNT(*) as count FROM domains");
    if ($domains_result) {
        $domains_count = $domains_result['count'];
    }
} else {
    $domains_result = $db->fetchOne(
        "SELECT COUNT(*) as count FROM domains WHERE user_id = ?",
        [$current_user['id']]
    );
    if ($domains_result) {
        $domains_count = $domains_result['count'];
    }
}

// Get WordPress sites count
$wordpress_count = 0;

if (is_admin()) {
    $wp_result = $db->fetchOne("SELECT COUNT(*) as count FROM wordpress_sites");
    if ($wp_result) {
        $wordpress_count = $wp_result['count'];
    }
} else {
    $wp_result = $db->fetchOne(
        "SELECT COUNT(*) as count FROM wordpress_sites ws
         JOIN domains d ON ws.domain_id = d.id
         WHERE d.user_id = ?",
        [$current_user['id']]
    );
    if ($wp_result) {
        $wordpress_count = $wp_result['count'];
    }
}

// Get users count (admin only)
$users_count = 0;
if (is_admin()) {
    $users_result = $db->fetchOne("SELECT COUNT(*) as count FROM users");
    if ($users_result) {
        $users_count = $users_result['count'];
    }
}

// Get system status
$litespeed_status = false;
$mysql_status = false;

exec(CMD_CHECK_LITESPEED, $output, $return_var);
if ($return_var === 0) {
    $litespeed_status = true;
}

exec('systemctl status mysql', $mysql_output, $mysql_return);
if ($mysql_return === 0) {
    $mysql_status = true;
}

// Get recent domains
$recent_domains = [];
if (is_admin()) {
    $recent_domains = $db->fetchAll(
        "SELECT d.*, u.username as owner
         FROM domains d
         JOIN users u ON d.user_id = u.id
         ORDER BY d.created_at DESC
         LIMIT 5"
    );
} else {
    $recent_domains = $db->fetchAll(
        "SELECT d.*, u.username as owner
         FROM domains d
         JOIN users u ON d.user_id = u.id
         WHERE d.user_id = ?
         ORDER BY d.created_at DESC
         LIMIT 5",
        [$current_user['id']]
    );
}

// Get recent WordPress installations
$recent_wordpress = [];
if (is_admin()) {
    $recent_wordpress = $db->fetchAll(
        "SELECT ws.*, d.domain_name
         FROM wordpress_sites ws
         JOIN domains d ON ws.domain_id = d.id
         ORDER BY ws.installed_at DESC
         LIMIT 5"
    );
} else {
    $recent_wordpress = $db->fetchAll(
        "SELECT ws.*, d.domain_name
         FROM wordpress_sites ws
         JOIN domains d ON ws.domain_id = d.id
         WHERE d.user_id = ?
         ORDER BY ws.installed_at DESC
         LIMIT 5",
        [$current_user['id']]
    );
}
?>

<div class="row">
    <!-- Stats Cards -->
    <div class="col-md-3 mb-4">
        <div class="card border-primary h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Domains</h5>
                <p class="display-4"><?php echo $domains_count; ?></p>
                <a href="index.php?page=domains" class="btn btn-sm btn-primary">Manage Domains</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-success h-100">
            <div class="card-body text-center">
                <h5 class="card-title">WordPress Sites</h5>
                <p class="display-4"><?php echo $wordpress_count; ?></p>
                <a href="index.php?page=wordpress" class="btn btn-sm btn-success">Manage WordPress</a>
            </div>
        </div>
    </div>
    
    <?php if (is_admin()): ?>
    <div class="col-md-3 mb-4">
        <div class="card border-info h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Users</h5>
                <p class="display-4"><?php echo $users_count; ?></p>
                <a href="index.php?page=users" class="btn btn-sm btn-info">Manage Users</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="col-md-3 mb-4">
        <div class="card border-warning h-100">
            <div class="card-body text-center">
                <h5 class="card-title">System Status</h5>
                <div class="mt-3">
                    <p class="mb-2">
                        <span class="badge bg-<?php echo $litespeed_status ? 'success' : 'danger'; ?>">
                            <i class="fas fa-<?php echo $litespeed_status ? 'check' : 'times'; ?>"></i> LiteSpeed
                        </span>
                    </p>
                    <p class="mb-2">
                        <span class="badge bg-<?php echo $mysql_status ? 'success' : 'danger'; ?>">
                            <i class="fas fa-<?php echo $mysql_status ? 'check' : 'times'; ?>"></i> MySQL
                        </span>
                    </p>
                </div>
                <?php if (is_admin()): ?>
                <a href="index.php?page=system" class="btn btn-sm btn-warning">System Details</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Domains -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Recent Domains</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_domains)): ?>
                <p class="text-muted">No domains found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_domains as $domain): ?>
                            <tr>
                                <td>
                                    <a href="index.php?page=domains&action=view&id=<?php echo $domain['id']; ?>">
                                        <?php echo htmlspecialchars($domain['domain_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $domain['status'] === 'active' ? 'success' : 
                                            ($domain['status'] === 'suspended' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($domain['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($domain['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <a href="index.php?page=domains" class="btn btn-sm btn-primary">View All Domains</a>
            </div>
        </div>
    </div>
    
    <!-- Recent WordPress Installations -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Recent WordPress Sites</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_wordpress)): ?>
                <p class="text-muted">No WordPress sites found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Version</th>
                                <th>Installed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_wordpress as $wp): ?>
                            <tr>
                                <td>
                                    <a href="index.php?page=wordpress&action=view&id=<?php echo $wp['id']; ?>">
                                        <?php echo htmlspecialchars($wp['domain_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo $wp['wp_version'] ? htmlspecialchars($wp['wp_version']) : 'Unknown'; ?></td>
                                <td><?php echo date('M j, Y', strtotime($wp['installed_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <a href="index.php?page=wordpress" class="btn btn-sm btn-success">View All WordPress Sites</a>
            </div>
        </div>
    </div>
</div>

<?php if (is_admin()): ?>
<!-- Quick Actions -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="index.php?page=domains&action=add" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle me-2"></i> Add Domain
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="index.php?page=wordpress&action=install" class="btn btn-success w-100">
                            <i class="fab fa-wordpress me-2"></i> Install WordPress
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="index.php?page=users&action=add" class="btn btn-info w-100">
                            <i class="fas fa-user-plus me-2"></i> Add User
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="index.php?page=system&action=restart-litespeed" class="btn btn-warning w-100">
                            <i class="fas fa-sync-alt me-2"></i> Restart LiteSpeed
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>