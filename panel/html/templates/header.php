<?php
/**
 * Header Template
 * WordPress Hosting Panel with LiteSpeed
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-server me-2"></i><?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_menu === 'dashboard' ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_menu === 'domains' ? 'active' : ''; ?>" href="index.php?page=domains">
                            <i class="fas fa-globe me-1"></i> Domains
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_menu === 'wordpress' ? 'active' : ''; ?>" href="index.php?page=wordpress">
                            <i class="fab fa-wordpress me-1"></i> WordPress
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_menu === 'users' ? 'active' : ''; ?>" href="index.php?page=users">
                            <i class="fas fa-users me-1"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_menu === 'settings' ? 'active' : ''; ?>" href="index.php?page=settings">
                            <i class="fas fa-cogs me-1"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_menu === 'system' ? 'active' : ''; ?>" href="index.php?page=system">
                            <i class="fas fa-heartbeat me-1"></i> System
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($current_user['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="index.php?page=profile">
                                    <i class="fas fa-id-card me-1"></i> My Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="index.php?logout=1">
                                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><?php echo $page_title; ?></h2>
            </div>
        </div>