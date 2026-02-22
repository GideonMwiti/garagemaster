<?php
// garage_management_system/admin/payment_receipt.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireLogin();

if (!isset($_GET['id'])) {
    die('Payment ID required');
}

// Fetch payment details
$stmt = $db->prepare("
    SELECT p.*, i.invoice_number, i.total_amount as invoice_total,
           c.first_name, c.last_name, c.email, c.phone, c.address,
           v.registration_number, v.make, v.model,
           g.name as garage_name, g.address as garage_address, 
           g.phone as garage_phone, g.email as garage_email,
           u.first_name as received_first, u.last_name as received_last
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    JOIN customers c ON i.customer_id = c.id
    JOIN vehicles v ON i.vehicle_id = v.id
    JOIN garages g ON p.garage_id = g.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.id = ?
");

$stmt->execute([$_GET['id']]);
$payment = $stmt->fetch();

if (!$payment) {
    die('Payment not found');
}

// Check access
if ($_SESSION['garage_id'] && $payment['garage_id'] != $_SESSION['garage_id']) {
    die('Unauthorized access');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt <?php echo htmlspecialchars($payment['payment_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
        }
        .receipt-box {
            max-width: 600px;
            margin: auto;
            padding: 30px;
            border: 2px solid #0E2033;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
        }
        .receipt-header {
            text-align: center;
            border-bottom: 3px solid #00A8CE;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .amount-box {
            background: #f8f9fa;
            padding: 20px;
            border: 2px dashed #00A8CE;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <div class="no-print mb-3">
            <button onclick="window.print()" class="btn btn-primary">Print Receipt</button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>
        
        <div class="receipt-box">
            <div class="receipt-header">
                <h2 style="color: #0E2033;"><?php echo htmlspecialchars($payment['garage_name']); ?></h2>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($payment['garage_address'])); ?></p>
                <p class="mb-0">Phone: <?php echo htmlspecialchars($payment['garage_phone']); ?></p>
                <h3 class="mt-3" style="color: #00A8CE;">PAYMENT RECEIPT</h3>
            </div>
            
            <div class="row mb-3">
                <div class="col-6">
                    <strong>Receipt #:</strong><br>
                    <?php echo htmlspecialchars($payment['payment_number']); ?>
                </div>
                <div class="col-6 text-end">
                    <strong>Date:</strong><br>
                    <?php echo date('F d, Y', strtotime($payment['created_at'])); ?><br>
                    <?php echo date('h:i A', strtotime($payment['created_at'])); ?>
                </div>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <strong>Received From:</strong><br>
                <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?><br>
                <?php if ($payment['address']): ?>
                <?php echo nl2br(htmlspecialchars($payment['address'])); ?><br>
                <?php endif; ?>
                Phone: <?php echo htmlspecialchars($payment['phone']); ?>
            </div>
            
            <div class="mb-3">
                <strong>Vehicle:</strong><br>
                <?php echo htmlspecialchars($payment['registration_number'] . ' - ' . $payment['make'] . ' ' . $payment['model']); ?>
            </div>
            
            <div class="amount-box">
                AMOUNT PAID: $<?php echo number_format($payment['amount'], 2); ?>
            </div>
            
            <table class="table">
                <tr>
                    <td><strong>Invoice Number:</strong></td>
                    <td class="text-end"><?php echo htmlspecialchars($payment['invoice_number']); ?></td>
                </tr>
                <tr>
                    <td><strong>Payment Method:</strong></td>
                    <td class="text-end"><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                </tr>
                <?php if ($payment['reference']): ?>
                <tr>
                    <td><strong>Reference/Transaction ID:</strong></td>
                    <td class="text-end"><?php echo htmlspecialchars($payment['reference']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Received By:</strong></td>
                    <td class="text-end">
                        <?php echo $payment['received_first'] ? htmlspecialchars($payment['received_first'] . ' ' . $payment['received_last']) : 'System'; ?>
                    </td>
                </tr>
            </table>
            
            <?php if ($payment['notes']): ?>
            <div class="mb-3">
                <strong>Notes:</strong><br>
                <?php echo nl2br(htmlspecialchars($payment['notes'])); ?>
            </div>
            <?php endif; ?>
            
            <div class="text-center mt-4" style="border-top: 2px solid #0E2033; padding-top: 20px;">
                <p class="mb-0"><strong>Thank you for your payment!</strong></p>
                <p class="text-muted small">This is a computer-generated receipt.</p>
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
