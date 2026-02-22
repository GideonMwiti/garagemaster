<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('accountant');
$page_title = 'Payments';
$current_page = 'payments';

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // Payment creation logic similar to admin/payments.php
    // ...code from admin payments for creating payment...
}

$stmt = $db->prepare("
    SELECT p.*, i.invoice_number, i.total_amount as invoice_total,
           c.first_name, c.last_name, v.registration_number
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    JOIN customers c ON i.customer_id = c.id
    JOIN vehicles v ON i.vehicle_id = v.id
    WHERE p.garage_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['garage_id']]);
$payments = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-money-bill-wave me-2"></i>Payments</h1>
            </div>
            
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="paymentsTable">
                            <thead class="table-primary">
                                <tr>
                                    <th>Payment #</th>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pay['payment_number']); ?></td>
                                    <td><?php echo htmlspecialchars($pay['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pay['registration_number']); ?></td>
                                    <td class="text-success"><strong><?php echo $functions->formatCurrency($pay['amount']); ?></strong></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $pay['payment_method'])); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($pay['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewReceipt(<?php echo $pay['id']; ?>)"><i class="fas fa-receipt"></i></button>
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
        order: [[6, 'desc']],
        pageLength: 25
    });
});

function viewReceipt(id) {
    window.open('../admin/payment_receipt.php?id=' + id, '_blank');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>