<?php
// garage_management_system/admin/invoice_pdf.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireLogin();

if (!isset($_GET['id'])) {
    die('Invoice ID required');
}

// Fetch invoice details
$stmt = $db->prepare("
    SELECT i.*, v.registration_number, v.make, v.model, v.year,
           c.first_name, c.last_name, c.email, c.phone, c.address, c.company,
           g.name as garage_name, g.address as garage_address, g.phone as garage_phone, 
           g.email as garage_email, g.tax_id as garage_tax_id
    FROM invoices i
    JOIN vehicles v ON i.vehicle_id = v.id
    JOIN customers c ON i.customer_id = c.id
    JOIN garages g ON i.garage_id = g.id
    WHERE i.id = ?
");

$stmt->execute([$_GET['id']]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Invoice not found');
}

// Check access permissions
if ($_SESSION['role'] === 'customer') {
    $stmt = $db->prepare("SELECT id FROM customers WHERE id = ? AND email = (SELECT email FROM users WHERE id = ?)");
    $stmt->execute([$invoice['customer_id'], $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        die('Unauthorized access');
    }
} elseif ($_SESSION['garage_id'] && $invoice['garage_id'] != $_SESSION['garage_id']) {
    die('Unauthorized access');
}

// Get invoice items
$stmt = $db->prepare("
    SELECT * FROM invoice_items WHERE invoice_id = ?
");
$stmt->execute([$_GET['id']]);
$invoice_items = $stmt->fetchAll();

// Get payments
$stmt = $db->prepare("
    SELECT * FROM payments WHERE invoice_id = ? ORDER BY created_at DESC
");
$stmt->execute([$_GET['id']]);
$payments = $stmt->fetchAll();

$total_paid = array_sum(array_column($payments, 'amount'));
$balance = $invoice['total_amount'] - $total_paid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
            font-size: 16px;
            line-height: 24px;
            color: #555;
        }
        .invoice-header {
            border-bottom: 3px solid #0E2033;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .invoice-table { width: 100%; margin-top: 20px; }
        .invoice-table th { background: #0E2033; color: white; padding: 10px; }
        .invoice-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .total-row { font-weight: bold; font-size: 18px; }
    </style>
</head>
<body>
    <div class="container my-4">
        <div class="no-print mb-3">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Invoice
            </button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>
        
        <div class="invoice-box">
            <!-- Header -->
            <div class="invoice-header">
                <div class="row">
                    <div class="col-6">
                        <h2 style="color: #0E2033;"><?php echo htmlspecialchars($invoice['garage_name']); ?></h2>
                        <p>
                            <?php echo nl2br(htmlspecialchars($invoice['garage_address'])); ?><br>
                            Phone: <?php echo htmlspecialchars($invoice['garage_phone']); ?><br>
                            Email: <?php echo htmlspecialchars($invoice['garage_email']); ?><br>
                            <?php if ($invoice['garage_tax_id']): ?>
                            Tax ID: <?php echo htmlspecialchars($invoice['garage_tax_id']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-6 text-end">
                        <h1 style="color: #0E2033;">INVOICE</h1>
                        <p>
                            <strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?><br>
                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($invoice['created_at'])); ?><br>
                            <strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?><br>
                            <span class="badge bg-<?php echo $invoice['status'] === 'paid' ? 'success' : 'warning'; ?> fs-6">
                                <?php echo strtoupper($invoice['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Customer & Vehicle Info -->
            <div class="row mb-4">
                <div class="col-6">
                    <h5>Bill To:</h5>
                    <p>
                        <strong><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></strong><br>
                        <?php if ($invoice['company']): ?>
                        <?php echo htmlspecialchars($invoice['company']); ?><br>
                        <?php endif; ?>
                        <?php if ($invoice['address']): ?>
                        <?php echo nl2br(htmlspecialchars($invoice['address'])); ?><br>
                        <?php endif; ?>
                        Phone: <?php echo htmlspecialchars($invoice['phone']); ?><br>
                        Email: <?php echo htmlspecialchars($invoice['email']); ?>
                    </p>
                </div>
                <div class="col-6">
                    <h5>Vehicle:</h5>
                    <p>
                        <strong><?php echo htmlspecialchars($invoice['registration_number']); ?></strong><br>
                        <?php echo htmlspecialchars($invoice['year'] . ' ' . $invoice['make'] . ' ' . $invoice['model']); ?>
                    </p>
                </div>
            </div>
            
            <!-- Invoice Items -->
            <table class="invoice-table table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-end">$<?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totals -->
                    <tr>
                        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end">$<?php echo number_format($invoice['subtotal'], 2); ?></td>
                    </tr>
                    <?php if ($invoice['discount'] > 0): ?>
                    <tr>
                        <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                        <td class="text-end">-$<?php echo number_format($invoice['discount'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="3" class="text-end"><strong>Tax (<?php echo $invoice['tax_rate']; ?>%):</strong></td>
                        <td class="text-end">$<?php echo number_format($invoice['tax_amount'], 2); ?></td>
                    </tr>
                    <tr class="total-row" style="background: #f8f9fa;">
                        <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
                        <td class="text-end"><strong>$<?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                    </tr>
                    <?php if ($total_paid > 0): ?>
                    <tr>
                        <td colspan="3" class="text-end" style="color: green;"><strong>Paid:</strong></td>
                        <td class="text-end" style="color: green;"><strong>-$<?php echo number_format($total_paid, 2); ?></strong></td>
                    </tr>
                    <tr class="total-row" style="background: #fff3cd;">
                        <td colspan="3" class="text-end"><strong>BALANCE DUE:</strong></td>
                        <td class="text-end"><strong>$<?php echo number_format($balance, 2); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Notes -->
            <?php if ($invoice['notes']): ?>
            <div class="mt-4">
                <h5>Notes:</h5>
                <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Payment History -->
            <?php if (count($payments) > 0): ?>
            <div class="mt-4">
                <h5>Payment History:</h5>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Payment #</th>
                            <th>Method</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($payment['payment_number']); ?></td>
                            <td><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                            <td class="text-end">$<?php echo number_format($payment['amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="text-center mt-4" style="border-top: 2px solid #0E2033; padding-top: 20px;">
                <p class="text-muted">Thank you for your business!</p>
            </div>
        </div>
    </div>
    
    <script>
        <?php if (isset($_GET['action']) && $_GET['action'] === 'print'): ?>
        window.onload = function() {
            window.print();
        };
        <?php endif; ?>
    </script>
</body>
</html>
