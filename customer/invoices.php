<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('customer');
$page_title = 'My Invoices';
$current_page = 'invoices';

$stmt = $db->prepare("SELECT id FROM customers WHERE email = (SELECT email FROM users WHERE id = ?) LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();
$customer_id = $customer['id'] ?? null;

if (!$customer_id) die('Customer account not configured');

$stmt = $db->prepare("
    SELECT i.*, v.registration_number, v.make, v.model,
           COALESCE(SUM(p.amount), 0) as paid_amount
    FROM invoices i
    JOIN vehicles v ON i.vehicle_id = v.id
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE i.customer_id = ?
    GROUP BY i.id
    ORDER BY i.created_at DESC
");
$stmt->execute([$customer_id]);
$invoices = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-file-invoice me-2"></i>My Invoices</h1>
            </div>
            
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="invoicesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): ?>
                                <?php $balance = $inv['total_amount'] - $inv['paid_amount']; ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($inv['registration_number']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($inv['make'] . ' ' . $inv['model']); ?></small>
                                    </td>
                                    <td><?php echo $functions->formatCurrency($inv['total_amount']); ?></td>
                                    <td><?php echo $functions->formatCurrency($inv['paid_amount']); ?></td>
                                    <td><strong><?php echo $functions->formatCurrency($balance); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $inv['status'] === 'paid' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($inv['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewInvoice(<?php echo $inv['id']; ?>)"><i class="fas fa-eye"></i> View</button>
                                    </td>
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
    $('#invoicesTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25
    });
});

function viewInvoice(id) {
    window.open('../admin/invoice_pdf.php?id=' + id, '_blank');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>