<?php
/**
 * Create Domain API Handler
 * WordPress Hosting Panel with LiteSpeed
 */

/**
 * Create a new domain
 * 
 * @param array $body Request body containing domain information
 * @return array Created domain information
 */
function create_domain($body) {
    // Validate request body
    if (!$body || !isset($body['domain_name'])) {
        api_error('Domain name is required');
    }
    
    $domain_name = trim($body['domain_name']);
    
    // Validate domain name
    if (!validate_domain($domain_name)) {
        api_error('Invalid domain name format');
    }
    
    $db = Database::getInstance();
    $user = $_REQUEST['api_user'];
    
    // Check if domain already exists
    $existing = $db->fetchOne(
        "SELECT id FROM domains WHERE domain_name = ?",
        [$domain_name]
    );
    
    if ($existing) {
        api_error('Domain already exists', 409);
    }
    
    // For regular users, check if they've reached their domain limit
    if ($user['role'] !== 'admin') {
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
            [$user['id']]
        );
        
        if ($count && (int)$count['count'] >= $max_domains) {
            api_error('You have reached the maximum number of domains allowed', 403);
        }
    }
    
    // Determine user_id (admin can create domains for other users)
    $user_id = $user['id'];
    if ($user['role'] === 'admin' && isset($body['user_id'])) {
        // Verify the user exists
        $target_user = $db->fetchOne(
            "SELECT id FROM users WHERE id = ?",
            [(int)$body['user_id']]
        );
        
        if ($target_user) {
            $user_id = (int)$body['user_id'];
        } else {
            api_error('Specified user does not exist', 404);
        }
    }
    
    // Set document root
    $document_root = WP_SITES_PATH . '/' . $domain_name;
    
    // Set status (admin can set initial status)
    $status = 'pending';
    if ($user['role'] === 'admin' && isset($body['status']) && 
        in_array($body['status'], ['active', 'suspended', 'pending'])) {
        $status = $body['status'];
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
        
        // Create document root directory
        if (!file_exists($document_root)) {
            mkdir($document_root, 0755, true);
            mkdir($document_root . '/html', 0755, true);
            
            // Create a default index.html
            $default_html = '<!DOCTYPE html>
<html>
<head>
    <title>Welcome to ' . htmlspecialchars($domain_name) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #0066cc;
        }
        .container {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to ' . htmlspecialchars($domain_name) . '</h1>
        <p>This domain has been successfully set up on the WordPress Hosting Panel with LiteSpeed.</p>
        <p>To replace this page, upload your website files or install WordPress from the control panel.</p>
    </div>
</body>
</html>';
            
            file_put_contents($document_root . '/html/index.html', $default_html);
            
            // Set proper permissions
            exec('chown -R www-data:www-data ' . escapeshellarg($document_root));
        }
        
        // Create LiteSpeed virtual host configuration
        $vhost_config = 'docRoot                   $VH_ROOT/html/
vhDomain                  ' . $domain_name . '
adminEmails               admin@' . $domain_name . '
enableGzip                1
enableIpGeo               1

index  {
  useServer               0
  indexFiles              index.php index.html
}

context / {
  location                $VH_ROOT/html/
  allowBrowse             1
  
  rewrite  {
    enable                1
    rules                 <<<END_rules
    rewriteCond %{REQUEST_FILENAME} !-f
    rewriteCond %{REQUEST_FILENAME} !-d
    rewriteRule ^(.*)$ index.php [QSA,L]
    END_rules
  }
}

phpIniOverride  {
  php_admin_value upload_max_filesize 64M
  php_admin_value post_max_size 64M
  php_admin_value memory_limit 256M
  php_admin_value max_execution_time 300
}';
        
        file_put_contents(LITESPEED_VHOSTS_PATH . '/' . $domain_name . '.conf', $vhost_config);
        
        // Update main LiteSpeed configuration to include the new virtual host
        $main_config = file_get_contents(LITESPEED_CONF_PATH . '/httpd_config.conf');
        if (strpos($main_config, 'virtualHost ' . $domain_name) === false) {
            $vhost_entry = 'virtualHost ' . $domain_name . ' {
  vhRoot                  ' . $document_root . '/
  configFile              $SERVER_ROOT/conf/vhosts/' . $domain_name . '.conf
  allowSymbolLink         1
  enableScript            1
  restrained              1
}';
            
            // Find position to insert (after the last virtualHost block)
            $pos = strrpos($main_config, 'virtualHost ');
            if ($pos !== false) {
                $pos = strpos($main_config, '}', $pos);
                if ($pos !== false) {
                    $main_config = substr_replace($main_config, "}\n\n" . $vhost_entry, $pos, 1);
                    file_put_contents(LITESPEED_CONF_PATH . '/httpd_config.conf', $main_config);
                }
            }
        }
        
        // Reload LiteSpeed to apply changes
        exec(CMD_RELOAD_LITESPEED);
        
        // Log the action
        log_action($user['id'], 'create_domain', 'Created domain: ' . $domain_name);
        
        // Commit transaction
        $db->commit();
        
        // Get the created domain
        $domain = $db->fetchOne(
            "SELECT d.*, u.username as owner
             FROM domains d
             LEFT JOIN users u ON d.user_id = u.id
             WHERE d.id = ?",
            [$domain_id]
        );
        
        // Format dates
        $domain['created_at'] = date('Y-m-d H:i:s', strtotime($domain['created_at']));
        if ($domain['updated_at']) {
            $domain['updated_at'] = date('Y-m-d H:i:s', strtotime($domain['updated_at']));
        }
        
        return [
            'status' => 'success',
            'message' => 'Domain created successfully',
            'domain' => $domain
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        api_error('Failed to create domain: ' . $e->getMessage(), 500);
    }
}