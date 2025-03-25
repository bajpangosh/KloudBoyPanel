<?php
/**
 * Get Domains API Handler
 * WordPress Hosting Panel with LiteSpeed
 */

/**
 * Get all domains for the authenticated user
 * 
 * @param array $body Request body (not used for GET requests)
 * @return array List of domains
 */
function get_domains($body = null) {
    $db = Database::getInstance();
    $user = $_REQUEST['api_user'];
    
    // Admin can see all domains, regular users can only see their own
    if ($user['role'] === 'admin') {
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
            [$user['id']]
        );
    }
    
    // Format the response
    foreach ($domains as &$domain) {
        $domain['has_wordpress'] = (bool)$domain['has_wordpress'];
        $domain['created_at'] = date('Y-m-d H:i:s', strtotime($domain['created_at']));
        if ($domain['updated_at']) {
            $domain['updated_at'] = date('Y-m-d H:i:s', strtotime($domain['updated_at']));
        }
    }
    
    return [
        'status' => 'success',
        'count' => count($domains),
        'domains' => $domains
    ];
}