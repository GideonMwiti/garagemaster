<?php
// garage_management_system/includes/sidebar.php
if (!isset($current_page)) {
    $current_page = '';
}

$role = $_SESSION['role'] ?? '';
?>
<div class="col-md-3 col-lg-2 sidebar d-md-block d-none">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/dashboard.php'; ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            
            <!-- Super Admin Specific Menu -->
            <?php if ($role == 'super_admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'garages' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/garages.php'; ?>">
                    <i class="fas fa-building me-2"></i>Garages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'users' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/users.php'; ?>">
                    <i class="fas fa-users-cog me-2"></i>System Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reports' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/reports.php'; ?>">
                    <i class="fas fa-chart-bar me-2"></i>System Reports
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Admin & Super Admin Menu -->
            <?php if ($role == 'admin'): ?>
            <?php if ($functions->checkPermissions('users')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'users' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/users.php'; ?>">
                    <i class="fas fa-users me-2"></i>Users
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($functions->checkPermissions('vehicles')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'vehicles' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/vehicles.php'; ?>">
                    <i class="fas fa-car me-2"></i>Vehicles
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($functions->checkPermissions('services')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'services' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/services.php'; ?>">
                    <i class="fas fa-tools me-2"></i>Services
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($functions->checkPermissions('inventory')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'inventory' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/inventory.php'; ?>">
                    <i class="fas fa-boxes me-2"></i>Inventory
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($functions->checkPermissions('job_cards')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'job_cards' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/job_cards.php'; ?>">
                    <i class="fas fa-clipboard-list me-2"></i>Job Cards
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($functions->checkPermissions('quotations')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'quotations' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/quotations.php'; ?>">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Quotations
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'gate_pass' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . 'admin/gate_pass.php'; ?>">
                    <i class="fas fa-id-card me-2"></i>Gate Pass
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Accountant Menu -->
            <?php if ($role == 'accountant'): ?>
            <?php if ($functions->checkPermissions('invoices')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'invoices' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/invoices.php'; ?>">
                    <i class="fas fa-file-invoice me-2"></i>Invoices
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($functions->checkPermissions('payments')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'payments' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/payments.php'; ?>">
                    <i class="fas fa-credit-card me-2"></i>Payments
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Employee Menu -->
            <?php if ($role == 'employee'): ?>
            <?php if ($functions->checkPermissions('job_cards')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'job_cards' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/job_cards.php'; ?>">
                    <i class="fas fa-clipboard-list me-2"></i>My Jobs
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'schedule' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/service_schedule.php'; ?>">
                    <i class="fas fa-calendar-alt me-2"></i>Schedule
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Customer Menu -->
            <?php if ($role == 'customer'): ?>
            <?php if ($functions->checkPermissions('vehicles')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'vehicles' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/vehicles.php'; ?>">
                    <i class="fas fa-car me-2"></i>My Vehicles
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($functions->checkPermissions('job_history')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'history' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/job_history.php'; ?>">
                    <i class="fas fa-history me-2"></i>Service History
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($functions->checkPermissions('invoices')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'invoices' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/invoices.php'; ?>">
                    <i class="fas fa-file-invoice me-2"></i>My Invoices
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Support Staff Menu -->
            <?php if ($role == 'support_staff'): ?>
            <?php if ($functions->checkPermissions('gate_pass')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'gate_pass' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/gate_pass.php'; ?>">
                    <i class="fas fa-id-card me-2"></i>Gate Pass
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($functions->checkPermissions('service_status')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'service_status' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/service_status.php'; ?>">
                    <i class="fas fa-sync-alt me-2"></i>Update Status
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Reports (Admin, Super Admin, Accountant) -->
            <?php if (in_array($role, ['super_admin', 'admin', 'accountant']) && $functions->checkPermissions('reports')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reports' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL . $role . '/reports.php'; ?>">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Settings (Admin only) -->
            <?php if ($role === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'settings' ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL . 'admin/settings.php'; ?>">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</div>