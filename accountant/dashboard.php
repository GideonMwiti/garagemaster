<?php
// garage_management_system/accountant/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('accountant');
$page_title = 'Accountant Dashboard';
$current_page = 'dashboard';

// Financial statistics
$stmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) THEN total_amount ELSE 0 END) as month_revenue,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END) as today_revenue,
        SUM(CASE WHEN status != 'paid' THEN total_amount ELSE 0 END) as pending_amount,
        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count
    FROM invoices WHERE garage_id = ?
");
$stmt->execute([$_SESSION['garage_id']]);
$stats = $stmt->fetch();

// Recent invoices
$stmt = $db->prepare("
    SELECT i.*, c.first_name, c.last_name, v.registration_number
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    JOIN vehicles v ON i.vehicle_id = v.id
    WHERE i.garage_id = ?
    ORDER BY i.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['garage_id']]);
$recent_invoices = $stmt->fetchAll();

// Recent payments
$stmt = $db->prepare("
    SELECT p.*, i.invoice_number, c.first_name, c.last_name
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    JOIN customers c ON i.customer_id = c.id
    WHERE p.garage_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['garage_id']]);
$recent_payments = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-tachometer-alt me-2"></i>Financial Dashboard</h1>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card border-left-success shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today's Revenue</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($stats['today_revenue'] ?? 0); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-primary shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Month Revenue</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($stats['month_revenue'] ?? 0); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-warning shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Invoices</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($stats['pending_amount'] ?? 0); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-danger shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $stats['overdue_count'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Recent Invoices</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_invoices as $inv): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                            <td><?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?></td>
                                            <td><?php echo $functions->formatCurrency($inv['total_amount']); ?></td>
                                            <td><span class="badge bg-<?php echo $inv['status'] === 'paid' ? 'success' : 'warning'; ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-money-bill me-2"></i>Recent Payments</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Payment #</th>
                                            <th>Invoice #</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_payments as $pay): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($pay['payment_number']); ?></td>
                                            <td><?php echo htmlspecialchars($pay['invoice_number']); ?></td>
                                            <td><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></td>
                                            <td class="text-success"><strong><?php echo $functions->formatCurrency($pay['amount']); ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>