<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('accountant');
$page_title = 'Financial Reports';
$current_page = 'financial_reports';

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Revenue by period
$stmt = $db->prepare("
    SELECT DATE(created_at) as date, 
           SUM(total_amount) as revenue,
           COUNT(*) as invoice_count
    FROM invoices
    WHERE garage_id = ? AND created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$revenue_by_date = $stmt->fetchAll();

// Payment methods summary
$stmt = $db->prepare("
    SELECT payment_method, COUNT(*) as count, SUM(amount) as total
    FROM payments
    WHERE garage_id = ? AND created_at BETWEEN ? AND ?
    GROUP BY payment_method
");
$stmt->execute([$_SESSION['garage_id'], $start_date, $end_date]);
$payment_methods = $stmt->fetchAll();

// Outstanding invoices
$stmt = $db->prepare("
    SELECT i.*, c.first_name, c.last_name, 
           COALESCE(SUM(p.amount), 0) as paid_amount
    FROM invoices i
    JOIN customers c ON i.customer_id = c.id
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE i.garage_id = ? AND i.status != 'paid'
    GROUP BY i.id
    HAVING paid_amount < total_amount
    ORDER BY i.due_date ASC
");
$stmt->execute([$_SESSION['garage_id']]);
$outstanding_invoices = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-chart-line me-2"></i>Financial Reports</h1>
                <button class="btn btn-outline-success" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
            </div>
            
            <!-- Date Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Revenue Summary -->
            <div class="row mb-4">
                <?php
                $total_revenue = array_sum(array_column($revenue_by_date, 'revenue'));
                $total_invoices = array_sum(array_column($revenue_by_date, 'invoice_count'));
                $avg_invoice = $total_invoices > 0 ? $total_revenue / $total_invoices : 0;
                ?>
                <div class="col-md-4">
                    <div class="card stat-card border-left-success shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Revenue</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($total_revenue); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card border-left-primary shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Invoices</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $total_invoices; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card border-left-info shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Invoice Value</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($avg_invoice); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Revenue Chart -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Daily Revenue</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="80"></canvas>
                </div>
            </div>
            
            <!-- Payment Methods -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Methods Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_payment_amount = array_sum(array_column($payment_methods, 'total'));
                                foreach ($payment_methods as $method): 
                                $percentage = $total_payment_amount > 0 ? ($method['total'] / $total_payment_amount * 100) : 0;
                                ?>
                                <tr>
                                    <td><?php echo ucwords(str_replace('_', ' ', $method['payment_method'])); ?></td>
                                    <td><?php echo $method['count']; ?></td>
                                    <td><?php echo $functions->formatCurrency($method['total']); ?></td>
                                    <td><?php echo number_format($percentage, 1); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Outstanding Invoices -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Outstanding Invoices</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($outstanding_invoices as $inv): ?>
                                <?php $balance = $inv['total_amount'] - $inv['paid_amount']; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']); ?></td>
                                    <td><?php echo $functions->formatCurrency($inv['total_amount']); ?></td>
                                    <td><?php echo $functions->formatCurrency($inv['paid_amount']); ?></td>
                                    <td><strong><?php echo $functions->formatCurrency($balance); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></td>
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
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($revenue_by_date, 'date')); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode(array_column($revenue_by_date, 'revenue')); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.6)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>