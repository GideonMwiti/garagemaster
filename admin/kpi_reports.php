<?php
// garage_management_system/admin/kpi_reports.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'KPI & Reports';
$current_page = 'reports';

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'overview';

// Get dashboard stats
$stats = $functions->getDashboardStats($_SESSION['garage_id'], $start_date, $end_date);

// Get revenue by month for chart
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(total_amount) as revenue,
        COUNT(*) as invoice_count
    FROM invoices 
    WHERE garage_id = ? AND status = 'paid' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
$stmt->execute([$_SESSION['garage_id']]);
$monthly_revenue = $stmt->fetchAll();

// Get top services
$stmt = $db->prepare("
    SELECT 
        s.name,
        COUNT(js.id) as service_count,
        SUM(js.price) as total_revenue
    FROM job_services js
    JOIN services s ON js.service_id = s.id
    JOIN job_cards jc ON js.job_card_id = jc.id
    WHERE jc.garage_id = ? 
    AND jc.created_at BETWEEN ? AND ?
    GROUP BY s.id
    ORDER BY total_revenue DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$top_services = $stmt->fetchAll();

// Get top customers
$stmt = $db->prepare("
    SELECT 
        c.first_name,
        c.last_name,
        c.company,
        COUNT(i.id) as invoice_count,
        SUM(i.total_amount) as total_spent
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    WHERE i.garage_id = ? 
    AND i.status = 'paid'
    AND i.created_at BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$top_customers = $stmt->fetchAll();

// Get technician performance
$stmt = $db->prepare("
    SELECT 
        u.first_name,
        u.last_name,
        COUNT(jc.id) as job_count,
        SUM(jc.actual_hours) as total_hours,
        AVG(jc.actual_hours) as avg_hours_per_job
    FROM job_cards jc
    JOIN users u ON jc.assigned_to = u.id
    WHERE jc.garage_id = ? 
    AND jc.status = 'completed'
    AND jc.created_at BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY job_count DESC
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$technician_performance = $stmt->fetchAll();

// Get service types distribution
$stmt = $db->prepare("
    SELECT 
        s.category,
        COUNT(*) as service_count,
        SUM(js.price) as total_revenue
    FROM job_services js
    JOIN services s ON js.service_id = s.id
    JOIN job_cards jc ON js.job_card_id = jc.id
    WHERE jc.garage_id = ? 
    AND jc.created_at BETWEEN ? AND ?
    GROUP BY s.category
    ORDER BY total_revenue DESC
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$service_types = $stmt->fetchAll();

// Calculate KPIs
$stmt = $db->prepare("
    SELECT 
        AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_job_duration
    FROM job_cards 
    WHERE garage_id = ? 
    AND status = 'completed'
    AND created_at BETWEEN ? AND ?
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$avg_job_duration = $stmt->fetch()['avg_job_duration'] ?? 0;

$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT customer_id) as repeat_customers
    FROM invoices 
    WHERE garage_id = ? 
    AND status = 'paid'
    AND customer_id IN (
        SELECT customer_id 
        FROM invoices 
        WHERE garage_id = ? 
        GROUP BY customer_id 
        HAVING COUNT(*) > 1
    )
    AND created_at BETWEEN ? AND ?
");
$stmt->execute([$_SESSION['garage_id'], $_SESSION['garage_id'], $start_date, $end_date]);
$repeat_customers = $stmt->fetch()['repeat_customers'] ?? 0;

$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_customers
    FROM customers 
    WHERE garage_id = ? 
    AND created_at BETWEEN ? AND ?
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$total_customers = $stmt->fetch()['total_customers'] ?? 0;

$customer_retention_rate = $total_customers > 0 ? ($repeat_customers / $total_customers) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">KPI & Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" onclick="printReport()">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                    </div>
                </div>
                
                <!-- Date Range Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?php echo $start_date; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo $end_date; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-control" id="report_type" name="report_type">
                                    <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                                    <option value="financial" <?php echo $report_type === 'financial' ? 'selected' : ''; ?>>Financial</option>
                                    <option value="performance" <?php echo $report_type === 'performance' ? 'selected' : ''; ?>>Performance</option>
                                    <option value="inventory" <?php echo $report_type === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Generate Report</button>
                                <a href="kpi_reports.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- KPI Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Revenue
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $functions->formatCurrency($stats['total_revenue']); ?>
                                        </div>
                                        <div class="mt-2 text-xs">
                                            <span class="text-success">
                                                <i class="fas fa-arrow-up"></i> 
                                                <?php 
                                                // Calculate growth (simplified)
                                                $previous_month = date('Y-m-01', strtotime('-1 month'));
                                                $previous_month_end = date('Y-m-t', strtotime('-1 month'));
                                                
                                                $stmt = $db->prepare("
                                                    SELECT COALESCE(SUM(total_amount), 0) as revenue
                                                    FROM invoices 
                                                    WHERE garage_id = ? AND status = 'paid' 
                                                    AND created_at BETWEEN ? AND ?
                                                ");
                                                $stmt->execute([$_SESSION['garage_id'], $previous_month, $previous_month_end]);
                                                $previous_revenue = $stmt->fetch()['revenue'];
                                                
                                                if ($previous_revenue > 0) {
                                                    $growth = (($stats['total_revenue'] - $previous_revenue) / $previous_revenue) * 100;
                                                    echo number_format($growth, 1) . '%';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </span>
                                            <span class="text-muted">vs last month</span>
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
                                            Completed Jobs
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $stmt = $db->prepare("
                                                SELECT COUNT(*) as count
                                                FROM job_cards 
                                                WHERE garage_id = ? AND status = 'completed'
                                                AND created_at BETWEEN ? AND ?
                                            ");
                                            $stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
                                            echo $stmt->fetch()['count'];
                                            ?>
                                        </div>
                                        <div class="mt-2 text-xs">
                                            <span class="text-success">
                                                <i class="fas fa-check-circle"></i> 
                                                <?php echo number_format($avg_job_duration, 1); ?> hrs avg
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-check fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Customer Retention
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($customer_retention_rate, 1); ?>%
                                        </div>
                                        <div class="mt-2 text-xs">
                                            <span class="text-info">
                                                <i class="fas fa-users"></i> 
                                                <?php echo $repeat_customers; ?> repeat customers
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-friends fa-2x text-info"></i>
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
                                            Avg. Revenue per Job
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $stmt = $db->prepare("
                                                SELECT AVG(total_amount) as avg_revenue
                                                FROM invoices 
                                                WHERE garage_id = ? AND status = 'paid'
                                                AND created_at BETWEEN ? AND ?
                                            ");
                                            $stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
                                            $avg_revenue = $stmt->fetch()['avg_revenue'] ?? 0;
                                            echo $functions->formatCurrency($avg_revenue);
                                            ?>
                                        </div>
                                        <div class="mt-2 text-xs">
                                            <span class="text-warning">
                                                <i class="fas fa-chart-line"></i> 
                                                Per job average
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-bar fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Revenue Trend (Last 6 Months)</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="revenueChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Service Types Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="serviceTypeChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Reports -->
                <div class="row">
                    <!-- Top Services -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Top Services by Revenue</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Service</th>
                                                <th>Count</th>
                                                <th>Revenue</th>
                                                <th>% of Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_service_revenue = 0;
                                            foreach ($top_services as $service) {
                                                $total_service_revenue += $service['total_revenue'];
                                            }
                                            
                                            foreach ($top_services as $service): 
                                                $percentage = $total_service_revenue > 0 ? ($service['total_revenue'] / $total_service_revenue) * 100 : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($service['name']); ?></td>
                                                <td><?php echo $service['service_count']; ?></td>
                                                <td><?php echo $functions->formatCurrency($service['total_revenue']); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%">
                                                            <?php echo number_format($percentage, 1); ?>%
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
                    </div>
                    
                    <!-- Top Customers -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Top Customers</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Invoices</th>
                                                <th>Total Spent</th>
                                                <th>Loyalty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_customers as $customer): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                                    <?php if ($customer['company']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($customer['company']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $customer['invoice_count']; ?></td>
                                                <td><?php echo $functions->formatCurrency($customer['total_spent']); ?></td>
                                                <td>
                                                    <?php if ($customer['invoice_count'] > 5): ?>
                                                    <span class="badge bg-success">Gold</span>
                                                    <?php elseif ($customer['invoice_count'] > 2): ?>
                                                    <span class="badge bg-warning">Silver</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-info">Bronze</span>
                                                    <?php endif; ?>
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
                
                <!-- Technician Performance -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Technician Performance</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Technician</th>
                                                <th>Jobs Completed</th>
                                                <th>Total Hours</th>
                                                <th>Avg. Hours/Job</th>
                                                <th>Efficiency Rating</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($technician_performance as $tech): 
                                                // Calculate efficiency (lower hours per job = better)
                                                $efficiency = $tech['avg_hours_per_job'] > 0 ? (8 / $tech['avg_hours_per_job']) * 100 : 100;
                                                $efficiency = min($efficiency, 100);
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?></td>
                                                <td><?php echo $tech['job_count']; ?></td>
                                                <td><?php echo number_format($tech['total_hours'], 1); ?> hrs</td>
                                                <td><?php echo number_format($tech['avg_hours_per_job'], 1); ?> hrs</td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar 
                                                            <?php 
                                                            if ($efficiency >= 80) echo 'bg-success';
                                                            elseif ($efficiency >= 60) echo 'bg-warning';
                                                            else echo 'bg-danger';
                                                            ?>" 
                                                            style="width: <?php echo $efficiency; ?>%">
                                                            <?php echo number_format($efficiency, 0); ?>%
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
                    </div>
                </div>
                
                <!-- Export Options -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="mb-3">Export Report</h5>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-primary" onclick="exportReport('pdf')">
                                        <i class="fas fa-file-pdf me-2"></i>Export as PDF
                                    </button>
                                    <button type="button" class="btn btn-outline-success" onclick="exportReport('excel')">
                                        <i class="fas fa-file-excel me-2"></i>Export as Excel
                                    </button>
                                    <button type="button" class="btn btn-outline-info" onclick="exportReport('csv')">
                                        <i class="fas fa-file-csv me-2"></i>Export as CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            const months = <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>;
            const revenue = <?php echo json_encode(array_column($monthly_revenue, 'revenue')); ?>;
            
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: months.map(m => {
                        const date = new Date(m + '-01');
                        return date.toLocaleString('default', { month: 'short', year: '2-digit' });
                    }),
                    datasets: [{
                        label: 'Revenue',
                        data: revenue,
                        borderColor: 'var(--brand-primary)',
                        backgroundColor: 'rgba(0, 168, 206, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'KSH ' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Service Type Chart
        const serviceTypeCtx = document.getElementById('serviceTypeChart');
        if (serviceTypeCtx) {
            const categories = <?php echo json_encode(array_column($service_types, 'category')); ?>;
            const revenue = <?php echo json_encode(array_column($service_types, 'total_revenue')); ?>;
            
            new Chart(serviceTypeCtx, {
                type: 'doughnut',
                data: {
                    labels: categories.map(c => c.charAt(0).toUpperCase() + c.slice(1)),
                    datasets: [{
                        data: revenue,
                        backgroundColor: [
                            'var(--brand-primary)',
                            'var(--brand-secondary)',
                            'var(--brand-accent)',
                            'var(--brand-success)',
                            'var(--brand-warning)',
                            'var(--brand-info)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        function printReport() {
            window.print();
        }
        
        function exportReport(format) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const reportType = document.getElementById('report_type').value;
            
            window.location.href = `ajax/export_report.php?format=${format}&start_date=${startDate}&end_date=${endDate}&report_type=${reportType}`;
        }
    </script>
</body>
</html>