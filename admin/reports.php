<?php
// garage_management_system/admin/reports.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'Reports & Analytics';
$current_page = 'reports';

// Date range defaults
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Revenue Report
$stmt = $db->prepare("
    SELECT DATE(i.created_at) as date, SUM(i.total_amount) as revenue, COUNT(*) as invoice_count
    FROM invoices i
    WHERE i.garage_id = ? AND i.created_at BETWEEN ? AND ?
    GROUP BY DATE(i.created_at)
    ORDER BY date DESC
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$revenue_data = $stmt->fetchAll();

// Job Cards Statistics
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count, SUM(estimated_cost) as total_value
    FROM job_cards
    WHERE garage_id = ? AND created_at BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$job_stats = $stmt->fetchAll();

// Top Services
$stmt = $db->prepare("
    SELECT s.name, COUNT(js.id) as count, SUM(js.price) as revenue
    FROM job_services js
    JOIN services s ON js.service_id = s.id
    JOIN job_cards jc ON js.job_card_id = jc.id
    WHERE jc.garage_id = ? AND jc.created_at BETWEEN ? AND ?
    GROUP BY s.id
    ORDER BY revenue DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$top_services = $stmt->fetchAll();

// Technician Performance
$stmt = $db->prepare("
    SELECT u.first_name, u.last_name, 
           COUNT(jc.id) as jobs_completed,
           AVG(jc.actual_hours) as avg_hours,
           SUM(jc.estimated_cost) as total_value
    FROM job_cards jc
    JOIN users u ON jc.assigned_to = u.id
    WHERE jc.garage_id = ? AND jc.status = 'completed' 
          AND jc.created_at BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY jobs_completed DESC
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$technician_performance = $stmt->fetchAll();

// Payment Methods Distribution
$stmt = $db->prepare("
    SELECT payment_method, COUNT(*) as count, SUM(amount) as total
    FROM payments
    WHERE garage_id = ? AND created_at BETWEEN ? AND ?
    GROUP BY payment_method
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$payment_methods = $stmt->fetchAll();

// Customer Statistics
$stmt = $db->prepare("
    SELECT c.first_name, c.last_name, c.email,
           COUNT(DISTINCT jc.id) as total_jobs,
           COUNT(DISTINCT i.id) as total_invoices,
           SUM(i.total_amount) as total_spent
    FROM customers c
    LEFT JOIN job_cards jc ON c.id = jc.customer_id AND jc.created_at BETWEEN ? AND ?
    LEFT JOIN invoices i ON c.id = i.customer_id AND i.created_at BETWEEN ? AND ?
    WHERE c.garage_id = ?
    GROUP BY c.id
    HAVING total_spent > 0
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date, $start_date, $end_date, $_SESSION['garage_id']]);
$top_customers = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-chart-line me-2"></i>Reports & Analytics</h1>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-1"></i>Export Excel
                    </button>
                </div>
            </div>
            
            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Revenue Overview -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Revenue Overview</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="80"></canvas>
                </div>
            </div>
            
            <div class="row mb-4">
                <!-- Job Cards Status -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Job Cards Status</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="jobStatusChart"></canvas>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Count</th>
                                            <th>Total Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($job_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo ucwords(str_replace('_', ' ', $stat['status'])); ?></td>
                                            <td><?php echo $stat['count']; ?></td>
                                            <td><?php echo $functions->formatCurrency($stat['total_value'] ?? 0); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Methods -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payment Methods</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="paymentMethodsChart"></canvas>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Method</th>
                                            <th>Count</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payment_methods as $method): ?>
                                        <tr>
                                            <td><?php echo ucwords(str_replace('_', ' ', $method['payment_method'])); ?></td>
                                            <td><?php echo $method['count']; ?></td>
                                            <td><?php echo $functions->formatCurrency($method['total']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Services -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Top Services</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Service Name</th>
                                    <th>Times Performed</th>
                                    <th>Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($top_services as $service): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($service['name']); ?></td>
                                    <td><?php echo $service['count']; ?></td>
                                    <td><?php echo $functions->formatCurrency($service['revenue']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Technician Performance -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>Technician Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Technician</th>
                                    <th>Jobs Completed</th>
                                    <th>Avg. Hours/Job</th>
                                    <th>Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($technician_performance as $tech): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?></td>
                                    <td><?php echo $tech['jobs_completed']; ?></td>
                                    <td><?php echo number_format($tech['avg_hours'] ?? 0, 2); ?> hrs</td>
                                    <td><?php echo $functions->formatCurrency($tech['total_value'] ?? 0); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Top Customers -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Top Customers</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Customer Name</th>
                                    <th>Email</th>
                                    <th>Total Jobs</th>
                                    <th>Total Invoices</th>
                                    <th>Total Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($top_customers as $customer): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td><?php echo $customer['total_jobs']; ?></td>
                                    <td><?php echo $customer['total_invoices']; ?></td>
                                    <td><strong><?php echo $functions->formatCurrency($customer['total_spent'] ?? 0); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($revenue_data, 'date')); ?>,
        datasets: [{
            label: 'Daily Revenue',
            data: <?php echo json_encode(array_column($revenue_data, 'revenue')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Job Status Chart
const jobStatusCtx = document.getElementById('jobStatusChart').getContext('2d');
const jobStatusChart = new Chart(jobStatusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($s) { return ucwords(str_replace('_', ' ', $s['status'])); }, $job_stats)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($job_stats, 'count')); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 159, 64, 0.8)'
            ]
        }]
    }
});

// Payment Methods Chart
const paymentMethodsCtx = document.getElementById('paymentMethodsChart').getContext('2d');
const paymentMethodsChart = new Chart(paymentMethodsCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_map(function($m) { return ucwords(str_replace('_', ' ', $m['payment_method'])); }, $payment_methods)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($payment_methods, 'total')); ?>,
            backgroundColor: [
                'rgba(76, 175, 80, 0.8)',
                'rgba(33, 150, 243, 0.8)',
                'rgba(255, 152, 0, 0.8)',
                'rgba(156, 39, 176, 0.8)',
                'rgba(233, 30, 99, 0.8)',
                'rgba(0, 188, 212, 0.8)'
            ]
        }]
    }
});

function exportToExcel() {
    alert('Excel export functionality would be implemented here');
    // Implementation would use a library like PHPSpreadsheet or export to CSV
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>