<?php
/**
 * Admin Header & Sidebar Layout
 */

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin'; ?> - GDPR Wizard Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Yanone+Kaffeesatz:wght@300&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="../assets/css/solid.css">
    <link rel="stylesheet" href="../assets/css/regular.css">
    <link rel="stylesheet" href="../assets/css/light.css">
    <link rel="stylesheet" href="../assets/css/thin.css">
    <link rel="stylesheet" href="../assets/css/duotone.css">
    <link rel="stylesheet" href="../assets/css/brands.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h3><img src="../assets/img/gdpr-admin.webp" /> Wizard</h3>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-section">
            <div class="menu-section-title">Main Menu</div>
            <a href="index.php" class="menu-item <?php echo $current_page === 'index' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="documents.php" class="menu-item <?php echo $current_page === 'documents' ? 'active' : ''; ?>">
                <i class="bi bi-file-text"></i> Documents
            </a>
            <a href="users.php" class="menu-item <?php echo $current_page === 'users' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Users
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">Content Management</div>
            <a href="services.php" class="menu-item <?php echo $current_page === 'services' ? 'active' : ''; ?>">
                <i class="bi bi-puzzle"></i> Services
            </a>
            <a href="service-categories.php" class="menu-item <?php echo $current_page === 'service-categories' ? 'active' : ''; ?>">
                <i class="bi bi-ui-checks-grid"></i> Service Categories
            </a>
            <a href="data-types.php" class="menu-item <?php echo $current_page === 'data-types' ? 'active' : ''; ?>">
                <i class="bi bi-database"></i> Data Types
            </a>
            <a href="hosting.php" class="menu-item <?php echo $current_page === 'hosting' ? 'active' : ''; ?>">
                <i class="bi bi-server"></i> Hosting & CDN
            </a>
            <a href="gdpr-blocks.php" class="menu-item <?php echo $current_page === 'gdpr-blocks' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i> GDPR Blocks
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">System</div>
            <a href="languages.php" class="menu-item <?php echo $current_page === 'languages' ? 'active' : ''; ?>">
                <i class="bi bi-translate"></i> Languages
            </a>
            <a href="watermark.php" class="menu-item <?php echo $current_page === 'watermark' ? 'active' : ''; ?>">
                <i class="bi bi-shield-check"></i> Watermark
            </a>
            <a href="security.php" class="menu-item <?php echo $current_page === 'security' ? 'active' : ''; ?>">
                <i class="bi bi-shield-exclamation"></i> Security
            </a>
            <a href="settings.php" class="menu-item <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i> Settings
            </a>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Header -->
    <div class="top-header">
        <div class="header-title">
            <h1><?php echo $page_title ?? 'Admin Panel'; ?></h1>
        </div>
        
        <div class="header-actions">
            <a href="../index.php" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-up-right"></i> View Frontend
            </a>
            
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['admin_name'] ?? $_SESSION['admin_username']; ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_username'], 0, 1)); ?>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Content Area -->
    <div class="content-area">