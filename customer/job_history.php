<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('customer');
$page_title = 'Service History';
$current_page = 'job_history';

$stmt = $db->prepare("SELECT id FROM customers WHERE email = (SELECT email FROM users WHERE id = ?) LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();
$customer_id = $customer['id'] ?? null;

if (!$customer_id) die('Customer account not configured');

$stmt = $db->prepare("
    SELECT jc.*, v.registration_number, v.make, v.model,
           u.first_name as tech_first, u.last_name as tech_last
    FROM job_cards jc
    JOIN vehicles v ON jc.vehicle_id = v.id
    LEFT JOIN users u ON jc.assigned_to = u.id
    WHERE jc.customer_id = ?
    ORDER BY jc.created_at DESC
");
$stmt->execute([$customer_id]);
$job_history = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-history me-2"></i>Service History</h1>
            </div>
            
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="historyTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Job #</th>
                                    <th>Vehicle</th>
                                    <th>Problem</th>
                                    <th>Technician</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($job_history as $job): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($job['job_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($job['registration_number']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($job['make'] . ' ' . $job['model']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($job['problem_description'], 0, 50)) . (strlen($job['problem_description']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo $job['tech_first'] ? htmlspecialchars($job['tech_first'] . ' ' . $job['tech_last']) : 'Unassigned'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $job['status'] === 'completed' ? 'success' : 
                                                ($job['status'] === 'in_progress' ? 'primary' : 'secondary'); 
                                        ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $job['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                    <td><?php echo $job['estimated_cost'] ? $functions->formatCurrency($job['estimated_cost']) : '-'; ?></td>
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

<script>
$(document).ready(function() {
    $('#historyTable').DataTable({
        order: [[5, 'desc']],
        pageLength: 25
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>