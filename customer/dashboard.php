<?php
// garage_management_system/customer/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('customer');
$page_title = 'Customer Dashboard';
$current_page = 'dashboard';

// Get customer ID from user
$stmt = $db->prepare("SELECT id FROM customers WHERE id = (SELECT id FROM users WHERE id = ? LIMIT 1) OR email = (SELECT email FROM users WHERE id = ? LIMIT 1)");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$customer = $stmt->fetch();
$customer_id = $customer['id'] ?? null;

if (!$customer_id) {
    die('Customer account not properly configured');
}

// Get customer vehicles
$stmt = $db->prepare("SELECT * FROM vehicles WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$customer_id]);
$vehicles = $stmt->fetchAll();

// Get active job cards
$stmt = $db->prepare("
    SELECT jc.*, v.registration_number, v.make, v.model
    FROM job_cards jc
    JOIN vehicles v ON jc.vehicle_id = v.id
    WHERE jc.customer_id = ? AND jc.status IN ('pending', 'in_progress', 'waiting_parts')
    ORDER BY jc.created_at DESC
");
$stmt->execute([$customer_id]);
$active_jobs = $stmt->fetchAll();

// Get unpaid invoices
$stmt = $db->prepare("
    SELECT i.*, v.registration_number, COALESCE(SUM(p.amount), 0) as paid_amount
    FROM invoices i
    JOIN vehicles v ON i.vehicle_id = v.id
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE i.customer_id = ? AND i.status != 'paid'
    GROUP BY i.id
");
$stmt->execute([$customer_id]);
$unpaid_invoices = $stmt->fetchAll();

// Get upcoming services
$stmt = $db->prepare("
    SELECT * FROM vehicles 
    WHERE customer_id = ? AND next_service_date IS NOT NULL 
          AND next_service_date >= CURDATE()
    ORDER BY next_service_date ASC
    LIMIT 5
");
$stmt->execute([$customer_id]);
$upcoming_services = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-tachometer-alt me-2"></i>My Dashboard</h1>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card border-left-primary shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">My Vehicles</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo count($vehicles); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-warning shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active Jobs</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo count($active_jobs); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-danger shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Unpaid Invoices</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo count($unpaid_invoices); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-info shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Upcoming Services</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo count($upcoming_services); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Active Jobs -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-wrench me-2"></i>Active Service Jobs</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($active_jobs) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($active_jobs as $job): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($job['job_number']); ?></h6>
                                        <small><?php echo date('M d', strtotime($job['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($job['registration_number'] . ' - ' . $job['make'] . ' ' . $job['model']); ?></p>
                                    <span class="badge bg-<?php echo $job['status'] === 'in_progress' ? 'primary' : 'secondary'; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $job['status'])); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-muted text-center">No active service jobs</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming Services -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Upcoming Services</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($upcoming_services) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($upcoming_services as $service): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($service['registration_number']); ?></h6>
                                        <small><?php echo date('M d, Y', strtotime($service['next_service_date'])); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($service['make'] . ' ' . $service['model']); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-muted text-center">No upcoming services scheduled</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Unpaid Invoices -->
            <?php if (count($unpaid_invoices) > 0): ?>
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Pending Payments</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Due Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unpaid_invoices as $inv): ?>
                                <?php $balance = $inv['total_amount'] - $inv['paid_amount']; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($inv['registration_number']); ?></td>
                                    <td><?php echo $functions->formatCurrency($inv['total_amount']); ?></td>
                                    <td><?php echo $functions->formatCurrency($inv['paid_amount']); ?></td>
                                    <td><strong><?php echo $functions->formatCurrency($balance); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></td>
                                    <td>
                                        <a href="invoices.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
