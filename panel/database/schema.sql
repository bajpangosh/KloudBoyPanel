-- WordPress Hosting Panel with LiteSpeed Database Schema

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` datetime NOT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Domains table
CREATE TABLE IF NOT EXISTS `domains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `domain_name` varchar(255) NOT NULL,
  `document_root` varchar(255) NOT NULL,
  `status` enum('active','suspended','pending') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain_name` (`domain_name`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `domains_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WordPress installations table
CREATE TABLE IF NOT EXISTS `wordpress_sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `db_user` varchar(64) NOT NULL,
  `db_password` varchar(255) NOT NULL,
  `wp_version` varchar(10) DEFAULT NULL,
  `admin_email` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `installed_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain_id` (`domain_id`),
  UNIQUE KEY `db_name` (`db_name`),
  CONSTRAINT `wordpress_sites_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SSL certificates table
CREATE TABLE IF NOT EXISTS `ssl_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11) NOT NULL,
  `certificate` text NOT NULL,
  `private_key` text NOT NULL,
  `issuer` varchar(255) DEFAULT NULL,
  `valid_from` datetime NOT NULL,
  `valid_to` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `domain_id` (`domain_id`),
  CONSTRAINT `ssl_certificates_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Statistics table
CREATE TABLE IF NOT EXISTS `statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `visits` int(11) NOT NULL DEFAULT 0,
  `bandwidth` bigint(20) NOT NULL DEFAULT 0,
  `disk_usage` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain_id_date` (`domain_id`,`date`),
  CONSTRAINT `statistics_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings table
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('panel_name', 'WordPress Hosting Panel with LiteSpeed', NOW()),
('panel_version', '1.0.0', NOW()),
('default_php_version', '8.1', NOW()),
('default_mysql_version', '8.0', NOW()),
('max_domains_per_user', '10', NOW()),
('max_disk_space_per_domain', '5120', NOW()),  -- 5GB in MB
('max_bandwidth_per_domain', '102400', NOW()), -- 100GB in MB
('enable_ssl_by_default', '1', NOW()),
('enable_wp_auto_update', '1', NOW()),
('enable_backup', '1', NOW()),
('backup_retention_days', '7', NOW());

-- Logs table
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backups table
CREATE TABLE IF NOT EXISTS `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11) NOT NULL,
  `backup_type` enum('full','database','files') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `domain_id` (`domain_id`),
  CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;