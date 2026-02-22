<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('customer');
$page_title = 'Payment History';
$current_page = 'payments';

$stmt = $db->prepare("SELECT id FROM customers WHERE email = (SELECT email FROM users WHERE id = ?) LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();
$customer_id = $customer['id'] ?? null;

if (!$customer_id) die('Customer account not configured');

$stmt = $db->prepare("
    SELECT p.*, i.invoice_number, v.registration_number
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    JOIN vehicles v ON i.vehicle_id = v.id
    WHERE i.customer_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$customer_id]);
$payments = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-money-bill-wave me-2"></i>Payment History</h1>
            </div>
            
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="paymentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Payment #</th>
                                    <th>Invoice #</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($pay['payment_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pay['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($pay['registration_number']); ?></td>
                                    <td class="text-success"><strong><?php echo $functions->formatCurrency($pay['amount']); ?></strong></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $pay['payment_method'])); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($pay['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewReceipt(<?php echo $pay['id']; ?>)"><i class="fas fa-receipt"></i> Receipt</button>
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
    $('#paymentsTable').DataTable({
        order: [[5, 'desc']],
        pageLength: 25
    });
});

function viewReceipt(id) {
    window.open('../admin/payment_receipt.php?id=' + id, '_blank');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>