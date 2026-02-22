<?php
// garage_management_system/admin/payments.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'Payments Management';
$current_page = 'payments';

if (!$auth->hasPermission('payments', 'view')) {
    header('Location: ' . BASE_URL . 'unauthorized.php');
    exit();
}

$message = '';
$message_type = '';

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid security token';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create' && $auth->hasPermission('payments', 'create')) {
            try {
                $db->getConnection()->beginTransaction();
                
                $payment_number = 'PAY-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO payments (garage_id, invoice_id, payment_number, amount, payment_method,
                                        reference, notes, received_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_SESSION['garage_id'],
                    $_POST['invoice_id'],
                    $payment_number,
                    $_POST['amount'],
                    $_POST['payment_method'],
                    $_POST['reference'] ?? null,
                    $_POST['notes'] ?? null,
                    $_SESSION['user_id']
                ]);
                
                // Update invoice status if fully paid
                $stmt = $db->prepare("
                    SELECT i.total_amount, COALESCE(SUM(p.amount), 0) as paid_amount
                    FROM invoices i
                    LEFT JOIN payments p ON i.id = p.invoice_id
                    WHERE i.id = ?
                    GROUP BY i.id
                ");
                $stmt->execute([$_POST['invoice_id']]);
                $invoice = $stmt->fetch();
                
                if ($invoice['paid_amount'] + floatval($_POST['amount']) >= $invoice['total_amount']) {
                    $stmt = $db->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?");
                    $stmt->execute([$_POST['invoice_id']]);
                }
                
                $db->getConnection()->commit();
                $message = 'Payment recorded successfully: ' . $payment_number;
                $message_type = 'success';
                
            } catch (Exception $e) {
                $db->getConnection()->rollBack();
                $message = 'Error recording payment: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
        
        if ($action === 'delete' && $auth->hasPermission('payments', 'delete')) {
            try {
                $db->getConnection()->beginTransaction();
                
                // Get payment details before deleting
                $stmt = $db->prepare("SELECT invoice_id, amount FROM payments WHERE id = ? AND garage_id = ?");
                $stmt->execute([$_POST['payment_id'], $_SESSION['garage_id']]);
                $payment = $stmt->fetch();
                
                if ($payment) {
                    // Delete payment
                    $stmt = $db->prepare("DELETE FROM payments WHERE id = ? AND garage_id = ?");
                    $stmt->execute([$_POST['payment_id'], $_SESSION['garage_id']]);
                    
                    // Update invoice status
                    $stmt = $db->prepare("UPDATE invoices SET status = 'sent' WHERE id = ? AND status = 'paid'");
                    $stmt->execute([$payment['invoice_id']]);
                }
                
                $db->getConnection()->commit();
                $message = 'Payment deleted successfully';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $db->getConnection()->rollBack();
                $message = 'Error deleting payment: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Fetch payments with multi-tenant isolation
$stmt = $db->prepare("
    SELECT p.*, i.invoice_number, i.total_amount as invoice_total,
           c.first_name as customer_first, c.last_name as customer_last,
           v.registration_number,
           u.first_name as received_first, u.last_name as received_last
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    JOIN customers c ON i.customer_id = c.id
    JOIN vehicles v ON i.vehicle_id = v.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.garage_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['garage_id']]);
$payments = $stmt->fetchAll();

// Fetch unpaid/partially paid invoices
$stmt = $db->prepare("
    SELECT i.id, i.invoice_number, i.total_amount, 
           COALESCE(SUM(p.amount), 0) as paid_amount,
           c.first_name, c.last_name, v.registration_number
    FROM invoices i
    LEFT JOIN payments p ON i.id = p.invoice_id
    JOIN customers c ON i.customer_id = c.id
    JOIN vehicles v ON i.vehicle_id = v.id
    WHERE i.garage_id = ? AND i.status != 'paid' AND i.status != 'cancelled'
    GROUP BY i.id
    HAVING paid_amount < total_amount
    ORDER BY i.due_date ASC
");
$stmt->execute([$_SESSION['garage_id']]);
$unpaid_invoices = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-money-bill-wave me-2"></i>Payments Management</h1>
                <?php if ($auth->hasPermission('payments', 'create')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                    <i class="fas fa-plus me-2"></i>Record Payment
                </button>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="row mb-4">
                <?php
                $today_payments = array_sum(array_map(function($p) {
                    return date('Y-m-d', strtotime($p['created_at'])) === date('Y-m-d') ? $p['amount'] : 0;
                }, $payments));
                
                $month_payments = array_sum(array_map(function($p) {
                    return date('Y-m', strtotime($p['created_at'])) === date('Y-m') ? $p['amount'] : 0;
                }, $payments));
                
                $total_payments = array_sum(array_column($payments, 'amount'));
                ?>
                <div class="col-md-4">
                    <div class="card stat-card border-left-success shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today's Payments</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($today_payments); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card border-left-primary shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">This Month</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($month_payments); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card border-left-info shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Collected</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($total_payments); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payments Table -->
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
                                    <th>Reference</th>
                                    <th>Received By</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($pay['payment_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pay['invoice_number']); ?></td>
                                    <td><?php echo htmlspecialchars($pay['customer_first'] . ' ' . $pay['customer_last']); ?></td>
                                    <td><?php echo htmlspecialchars($pay['registration_number']); ?></td>
                                    <td><strong class="text-success"><?php echo $functions->formatCurrency($pay['amount']); ?></strong></td>
                                    <td>
                                        <?php
                                        $method_icons = [
                                            'cash' => 'fa-money-bill',
                                            'credit_card' => 'fa-credit-card',
                                            'debit_card' => 'fa-credit-card',
                                            'bank_transfer' => 'fa-university',
                                            'check' => 'fa-money-check',
                                            'online' => 'fa-laptop'
                                        ];
                                        $icon = $method_icons[$pay['payment_method']] ?? 'fa-money-bill';
                                        ?>
                                        <i class="fas <?php echo $icon; ?> me-1"></i>
                                        <?php echo ucwords(str_replace('_', ' ', $pay['payment_method'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($pay['reference'] ?? '-'); ?></td>
                                    <td><?php echo $pay['received_first'] ? htmlspecialchars($pay['received_first'] . ' ' . $pay['received_last']) : '-'; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($pay['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-info" onclick="viewReceipt(<?php echo $pay['id']; ?>)" title="View Receipt">
                                                <i class="fas fa-receipt"></i>
                                            </button>
                                            <button class="btn btn-sm btn-secondary" onclick="printReceipt(<?php echo $pay['id']; ?>)" title="Print">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <?php if ($auth->hasPermission('payments', 'delete')): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deletePayment(<?php echo $pay['id']; ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
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
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-money-bill me-2"></i>Record Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Invoice <span class="text-danger">*</span></label>
                            <select class="form-select" name="invoice_id" id="invoiceSelect" required>
                                <option value="">Select Invoice</option>
                                <?php foreach ($unpaid_invoices as $inv): ?>
                                <?php $balance = $inv['total_amount'] - $inv['paid_amount']; ?>
                                <option value="<?php echo $inv['id']; ?>" data-balance="<?php echo $balance; ?>">
                                    <?php echo htmlspecialchars($inv['invoice_number'] . ' - ' . $inv['first_name'] . ' ' . $inv['last_name'] . ' (' . $inv['registration_number'] . ') - Balance: ' . $functions->formatCurrency($balance)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">KSH</span>
                                <input type="number" step="0.01" class="form-control" name="amount" id="paymentAmount" required>
                            </div>
                            <small class="text-muted">Balance: <span id="invoiceBalance">KSH 0.00</span></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="check">Check</option>
                                <option value="online">Online Payment</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Reference/Transaction ID</label>
                            <input type="text" class="form-control" name="reference" placeholder="Check number, transaction ID, etc.">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#paymentsTable').DataTable({
        order: [[8, 'desc']],
        pageLength: 25
    });
    
    $('#invoiceSelect').on('change', function() {
        const balance = $(this).find(':selected').data('balance');
        $('#invoiceBalance').text('$' + parseFloat(balance).toFixed(2));
        $('#paymentAmount').val(balance);
        $('#paymentAmount').attr('max', balance);
    });
});

function viewReceipt(id) {
    window.open('payment_receipt.php?id=' + id, '_blank');
}

function printReceipt(id) {
    window.open('payment_receipt.php?id=' + id + '&action=print', '_blank');
}

function deletePayment(id) {
    if (confirm('Are you sure you want to delete this payment? This will affect the invoice balance.')) {
        const form = $('<form method="POST">');
        form.append('<input type="hidden" name="action" value="delete">');
        form.append('<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">');
        form.append('<input type="hidden" name="payment_id" value="' + id + '">');
        $('body').append(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>