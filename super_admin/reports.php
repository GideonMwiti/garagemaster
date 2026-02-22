<?php
// garage_management_system/super_admin/reports.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('super_admin');
$page_title = 'System Reports';
$current_page = 'reports';

// Date filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$garage_filter = $_GET['garage_id'] ?? '';

// Revenue Report
$revenue_query = "
    SELECT 
        DATE(i.created_at) as date,
        COUNT(DISTINCT i.id) as invoice_count,
        SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN i.status IN ('sent', 'draft') THEN i.total_amount ELSE 0 END) as pending_amount
    FROM invoices i
    WHERE DATE(i.created_at) BETWEEN ? AND ?
";

$params = [$start_date, $end_date];
if ($garage_filter) {
    $revenue_query .= " AND i.garage_id = ?";
    $params[] = $garage_filter;
}

$revenue_query .= " GROUP BY DATE(i.created_at) ORDER BY DATE(i.created_at) DESC";
$stmt = $db->prepare($revenue_query);
$stmt->execute($params);
$revenue_data = $stmt->fetchAll();

// Garage Performance
$garage_query = "
    SELECT 
        g.id, g.name,
        COUNT(DISTINCT jc.id) as job_count,
        COUNT(DISTINCT v.id) as vehicle_count,
        COUNT(DISTINCT u.id) as user_count,
        COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END), 0) as revenue
    FROM garages g
    LEFT JOIN job_cards jc ON g.id = jc.garage_id AND DATE(jc.created_at) BETWEEN ? AND ?
    LEFT JOIN vehicles v ON g.id = v.garage_id
    LEFT JOIN users u ON g.id = u.garage_id AND u.status = 'active'
    LEFT JOIN invoices i ON g.id = i.garage_id AND i.status = 'paid' AND DATE(i.created_at) BETWEEN ? AND ?
    WHERE g.status = 'active'
    GROUP BY g.id, g.name
    ORDER BY revenue DESC
";
$stmt = $db->prepare($garage_query);
$stmt->execute([$start_date, $end_date, $start_date, $end_date]);
$garage_performance = $stmt->fetchAll();

// User Activity
$user_query = "
    SELECT 
        r.name as role_name,
        COUNT(u.id) as user_count,
        SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN u.last_login IS NOT NULL THEN 1 ELSE 0 END) as logged_in_count
    FROM roles r
    LEFT JOIN users u ON r.id = u.role_id AND u.role_id != 1
    GROUP BY r.id, r.name
    ORDER BY user_count DESC
";
$stmt = $db->query($user_query);
$user_activity = $stmt->fetchAll();

// Get all garages for filter
$stmt = $db->query("SELECT id, name FROM garages WHERE status = 'active' ORDER BY name");
$garages = $stmt->fetchAll();

include '../includes/header.php';
?>
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-bar me-2"></i>System Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-success" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Garage</label>
                                <select class="form-control" name="garage_id">
                                    <option value="">All Garages</option>
                                    <?php foreach ($garages as $g): ?>
                                    <option value="<?php echo $g['id']; ?>" <?php echo $garage_filter == $g['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Revenue Report -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Revenue Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Invoices</th>
                                        <th>Paid Amount</th>
                                        <th>Pending Amount</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_invoices = 0;
                                    $total_paid = 0;
                                    $total_pending = 0;
                                    foreach ($revenue_data as $row): 
                                        $total_invoices += $row['invoice_count'];
                                        $total_paid += $row['paid_amount'];
                                        $total_pending += $row['pending_amount'];
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                        <td><?php echo $row['invoice_count']; ?></td>
                                        <td class="text-success"><?php echo $functions->formatCurrency($row['paid_amount']); ?></td>
                                        <td class="text-warning"><?php echo $functions->formatCurrency($row['pending_amount']); ?></td>
                                        <td><strong><?php echo $functions->formatCurrency($row['paid_amount'] + $row['pending_amount']); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-dark">
                                    <tr>
                                        <th>Total</th>
                                        <th><?php echo $total_invoices; ?></th>
                                        <th class="text-success"><?php echo $functions->formatCurrency($total_paid); ?></th>
                                        <th class="text-warning"><?php echo $functions->formatCurrency($total_pending); ?></th>
                                        <th><?php echo $functions->formatCurrency($total_paid + $total_pending); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Garage Performance -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Garage Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Garage</th>
                                        <th>Jobs</th>
                                        <th>Vehicles</th>
                                        <th>Users</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($garage_performance as $g): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($g['name']); ?></strong></td>
                                        <td><?php echo $g['job_count']; ?></td>
                                        <td><?php echo $g['vehicle_count']; ?></td>
                                        <td><?php echo $g['user_count']; ?></td>
                                        <td class="text-success"><strong><?php echo $functions->formatCurrency($g['revenue']); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- User Activity -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>User Activity by Role</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Role</th>
                                        <th>Total Users</th>
                                        <th>Active</th>
                                        <th>Ever Logged In</th>
                                        <th>Activity Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_activity as $ua): ?>
                                    <tr>
                                        <td><strong><?php echo ucfirst(str_replace('_', ' ', $ua['role_name'])); ?></strong></td>
                                        <td><?php echo $ua['user_count']; ?></td>
                                        <td><span class="badge bg-success"><?php echo $ua['active_count']; ?></span></td>
                                        <td><?php echo $ua['logged_in_count']; ?></td>
                                        <td>
                                            <?php 
                                            $rate = $ua['user_count'] > 0 ? ($ua['logged_in_count'] / $ua['user_count'] * 100) : 0;
                                            ?>
                                            <div class="progress">
                                                <div class="progress-bar bg-<?php echo $rate > 75 ? 'success' : ($rate > 50 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo $rate; ?>%">
                                                    <?php echo round($rate); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>

<?php include '../includes/footer.php'; ?>
