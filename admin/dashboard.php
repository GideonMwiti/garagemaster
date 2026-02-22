<?php
// garage_management_system/admin/dashboard.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'Dashboard';
$current_page = 'dashboard';

// Check if user is super_admin
$is_super_admin = $functions->isSuperAdmin();
$filter_garage_id = $functions->getFilterGarageId();

// Get dashboard statistics
$stats = $functions->getDashboardStats($filter_garage_id);

// Get recent job cards
if ($is_super_admin) {
    // Show all job cards from all garages
    $stmt = $db->prepare("
        SELECT jc.*, v.registration_number, v.make, v.model, 
               c.first_name, c.last_name, u.first_name as tech_first, u.last_name as tech_last,
               g.name as garage_name
        FROM job_cards jc
        JOIN vehicles v ON jc.vehicle_id = v.id
        JOIN customers c ON jc.customer_id = c.id
        LEFT JOIN users u ON jc.assigned_to = u.id
        LEFT JOIN garages g ON jc.garage_id = g.id
        ORDER BY jc.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
} else {
    $stmt = $db->prepare("
        SELECT jc.*, v.registration_number, v.make, v.model, 
               c.first_name, c.last_name, u.first_name as tech_first, u.last_name as tech_last
        FROM job_cards jc
        JOIN vehicles v ON jc.vehicle_id = v.id
        JOIN customers c ON jc.customer_id = c.id
        LEFT JOIN users u ON jc.assigned_to = u.id
        WHERE jc.garage_id = ?
        ORDER BY jc.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['garage_id']]);
}
$recent_jobs = $stmt->fetchAll();

// Get low stock items
$low_stock = $functions->checkLowStock($filter_garage_id);

// Get upcoming service reminders
if ($is_super_admin) {
    $stmt = $db->prepare("
        SELECT v.*, c.first_name, c.last_name, c.email, c.phone, g.name as garage_name
        FROM vehicles v
        JOIN customers c ON v.customer_id = c.id
        LEFT JOIN garages g ON v.garage_id = g.id
        WHERE v.next_service_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY v.next_service_date ASC
        LIMIT 5
    ");
    $stmt->execute();
} else {
    $stmt = $db->prepare("
        SELECT v.*, c.first_name, c.last_name, c.email, c.phone
        FROM vehicles v
        JOIN customers c ON v.customer_id = c.id
        WHERE v.garage_id = ? AND v.next_service_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY v.next_service_date ASC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['garage_id']]);
}
$upcoming_services = $stmt->fetchAll();

// Get chart data
$revenue_data = $functions->getRevenueData($filter_garage_id);
$service_type_data = $functions->getServiceTypeData($filter_garage_id);

include '../includes/header.php';
?>
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="me-3">
                            <i class="fas fa-building me-1"></i>
                            <?php echo $_SESSION['garage_name']; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Revenue (This Month)
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $functions->formatCurrency($stats['total_revenue']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Active Jobs
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['active_jobs']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-list fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Invoices
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['pending_invoices']; ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            Amount: <?php echo $functions->formatCurrency($stats['pending_amount']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-file-invoice-dollar fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Low Stock Items
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['low_stock']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Recent Activity -->
                <div class="row">
                    <!-- Revenue Chart -->
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Revenue Overview</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Service Types -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Service Types</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4">
                                    <canvas id="serviceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Jobs & Upcoming Services -->
                <div class="row">
                    <!-- Recent Job Cards -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Job Cards</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Job #</th>
                                                <th>Vehicle</th>
                                                <th>Customer</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_jobs as $job): ?>
                                            <tr>
                                                <td>
                                                    <a href="job_cards.php?action=view&id=<?php echo $job['id']; ?>">
                                                        <?php echo $job['job_number']; ?>
                                                    </a>
                                                </td>
                                                <td><?php echo $job['make'] . ' ' . $job['model']; ?></td>
                                                <td><?php echo $job['first_name'] . ' ' . $job['last_name']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch($job['status']) {
                                                            case 'pending': echo 'warning'; break;
                                                            case 'in_progress': echo 'primary'; break;
                                                            case 'completed': echo 'success'; break;
                                                            case 'cancelled': echo 'danger'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="job_cards.php" class="btn btn-sm btn-primary">View All Jobs</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upcoming Services -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Upcoming Services</h6>
                                <button class="btn btn-sm btn-primary" onclick="sendReminders()">
                                    <i class="fas fa-bell me-1"></i>Send Reminders
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Vehicle</th>
                                                <th>Customer</th>
                                                <th>Service Date</th>
                                                <th>Contact</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcoming_services as $vehicle): ?>
                                            <tr>
                                                <td><?php echo $vehicle['make'] . ' ' . $vehicle['model']; ?></td>
                                                <td><?php echo $vehicle['first_name'] . ' ' . $vehicle['last_name']; ?></td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($vehicle['next_service_date'])); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php 
                                                            $days = floor((strtotime($vehicle['next_service_date']) - time()) / (60 * 60 * 24));
                                                            echo $days > 0 ? "in $days days" : "Today";
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <a href="tel:<?php echo $vehicle['phone']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-phone"></i>
                                                    </a>
                                                    <a href="mailto:<?php echo $vehicle['email']; ?>" class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Low Stock Alert -->
                <?php if ($low_stock): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow border-left-danger">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($low_stock as $item): ?>
                                    <div class="col-md-3 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo $item['name']; ?></h6>
                                                <p class="card-text">
                                                    <strong>Current Stock:</strong> <?php echo $item['quantity']; ?><br>
                                                    <strong>Reorder Level:</strong> <?php echo $item['reorder_level']; ?><br>
                                                    <strong>Part Code:</strong> <?php echo $item['part_code']; ?>
                                                </p>
                                                <a href="inventory.php?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit me-1"></i>Reorder
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
    
    <script>
        function sendReminders() {
            if (confirm('Send service reminders to all customers with upcoming services?')) {
                $.ajax({
                    url: 'ajax/send_reminders.php',
                    type: 'POST',
                    data: { garage_id: <?php echo intval($_SESSION['garage_id']); ?> },
                    success: function(response) {
                        alert('Reminders sent successfully!');
                        location.reload();
                    },
                    error: function() {
                        alert('Error sending reminders. Please try again.');
                    }
                });
            }
        }
        
        // Initialize charts
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($revenue_data['labels']); ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?php echo json_encode($revenue_data['data']); ?>,
                        borderColor: 'var(--brand-primary)',
                        backgroundColor: 'rgba(0, 168, 206, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'KSH ' + value;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        const serviceCtx = document.getElementById('serviceChart');
        if (serviceCtx) {
            new Chart(serviceCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($service_type_data['labels']); ?>,
                    datasets: [{
                        data: <?php echo json_encode($service_type_data['data']); ?>,
                        backgroundColor: [
                            'var(--brand-primary)',
                            'var(--brand-secondary)',
                            'var(--brand-accent)',
                            'var(--brand-muted)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    </script>

<?php include '../includes/footer.php'; ?>