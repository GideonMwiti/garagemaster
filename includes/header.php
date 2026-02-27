<?php
// garage_management_system/includes/header.php
if (!isset($page_title)) {
    $page_title = SITE_NAME;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' | ' . SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom Brand CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/brand.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?><?php echo BRAND_FAVICON; ?>">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- CSRF Token for AJAX -->
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
    
    <style>
        .navbar-brand img {
            max-height: 40px;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: var(--brand-dark);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-left: 4px solid transparent;
        }
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(0, 168, 206, 0.2);
            border-left-color: var(--brand-primary);
        }
        .stat-card {
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .table th {
            background-color: var(--brand-primary);
            color: white;
            border-color: var(--brand-primary);
        }
        .footer {
            background-color: #f8f9fa;
            margin-top: 50px;
            border-top: 1px solid #dee2e6;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .container-fluid {
            flex: 1;
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--brand-dark);">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL . $_SESSION['role'] . '/dashboard.php'; ?>">
                <img src="<?php echo BASE_URL . BRAND_LOGO; ?>" alt="<?php echo BRAND_NAME; ?>" class="me-2">
                <?php echo BRAND_NAME; ?>
            </a>
            
            <!-- Mobile Sidebar Toggle -->
            <button class="navbar-toggler me-auto ms-2 border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Mobile Profile Menu Toggle -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-ellipsis-v text-white"></i>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo $_SESSION['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text">
                                <small class="text-muted"><?php echo ucfirst($_SESSION['role']); ?></small>
                            </span></li>
                            <?php if ($_SESSION['garage_name']): ?>
                            <li><span class="dropdown-item-text">
                                <small class="text-muted"><?php echo $_SESSION['garage_name']; ?></small>
                            </span></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <div class="container-fluid">
        <div class="row">