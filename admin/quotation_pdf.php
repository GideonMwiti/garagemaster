<?php
// garage_management_system/admin/quotation_pdf.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireLogin();

if (!isset($_GET['id'])) {
    die('Quotation ID required');
}

// Fetch quotation details
$stmt = $db->prepare("
    SELECT q.*, v.registration_number, v.make, v.model, v.year,
           c.first_name, c.last_name, c.email, c.phone, c.address, c.company,
           g.name as garage_name, g.address as garage_address, 
           g.phone as garage_phone, g.email as garage_email
    FROM quotations q
    JOIN vehicles v ON q.vehicle_id = v.id
    JOIN customers c ON q.customer_id = c.id
    JOIN garages g ON q.garage_id = g.id
    WHERE q.id = ?
");

$stmt->execute([$_GET['id']]);
$quotation = $stmt->fetch();

if (!$quotation) {
    die('Quotation not found');
}

// Check access
if ($_SESSION['garage_id'] && $quotation['garage_id'] != $_SESSION['garage_id']) {
    die('Unauthorized access');
}

// Get quotation items
$stmt = $db->prepare("
    SELECT qi.*, s.name as service_name
    FROM quotation_items qi
    LEFT JOIN services s ON qi.item_id = s.id AND qi.item_type = 'service'
    WHERE qi.quotation_id = ?
");
$stmt->execute([$_GET['id']]);
$quotation_items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation <?php echo htmlspecialchars($quotation['quotation_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
        }
        .quotation-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
        }
        .quotation-header {
            border-bottom: 3px solid #FFA629;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .validity-notice {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <div class="no-print mb-3">
            <button onclick="window.print()" class="btn btn-primary">Print Quotation</button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>
        
        <div class="quotation-box">
            <div class="quotation-header">
                <div class="row">
                    <div class="col-6">
                        <h2 style="color: #0E2033;"><?php echo htmlspecialchars($quotation['garage_name']); ?></h2>
                        <p>
                            <?php echo nl2br(htmlspecialchars($quotation['garage_address'])); ?><br>
                            Phone: <?php echo htmlspecialchars($quotation['garage_phone']); ?><br>
                            Email: <?php echo htmlspecialchars($quotation['garage_email']); ?>
                        </p>
                    </div>
                    <div class="col-6 text-end">
                        <h1 style="color: #FFA629;">QUOTATION</h1>
                        <p>
                            <strong>Quotation #:</strong> <?php echo htmlspecialchars($quotation['quotation_number']); ?><br>
                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($quotation['created_at'])); ?><br>
                            <strong>Valid Until:</strong> <?php echo date('M d, Y', strtotime($quotation['valid_until'])); ?><br>
                            <span class="badge bg-<?php 
                                echo $quotation['status'] === 'accepted' ? 'success' : 
                                    ($quotation['status'] === 'rejected' ? 'danger' : 'warning'); 
                            ?> fs-6">
                                <?php echo strtoupper($quotation['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-6">
                    <h5>Prepared For:</h5>
                    <p>
                        <strong><?php echo htmlspecialchars($quotation['first_name'] . ' ' . $quotation['last_name']); ?></strong><br>
                        <?php if ($quotation['company']): ?>
                        <?php echo htmlspecialchars($quotation['company']); ?><br>
                        <?php endif; ?>
                        <?php if ($quotation['address']): ?>
                        <?php echo nl2br(htmlspecialchars($quotation['address'])); ?><br>
                        <?php endif; ?>
                        Phone: <?php echo htmlspecialchars($quotation['phone']); ?><br>
                        Email: <?php echo htmlspecialchars($quotation['email']); ?>
                    </p>
                </div>
                <div class="col-6">
                    <h5>Vehicle:</h5>
                    <p>
                        <strong><?php echo htmlspecialchars($quotation['registration_number']); ?></strong><br>
                        <?php echo htmlspecialchars($quotation['year'] . ' ' . $quotation['make'] . ' ' . $quotation['model']); ?>
                    </p>
                </div>
            </div>
            
            <?php
            $valid_date = new DateTime($quotation['valid_until']);
            $now = new DateTime();
            $days_left = $now->diff($valid_date)->days;
            $is_expired = $valid_date < $now;
            ?>
            
            <?php if (!$is_expired && $days_left <= 7): ?>
            <div class="validity-notice">
                <strong><i class="fas fa-exclamation-triangle"></i> Notice:</strong>
                This quotation is valid for only <strong><?php echo $days_left; ?> more day(s)</strong>.
                Please respond before <?php echo date('M d, Y', strtotime($quotation['valid_until'])); ?>.
            </div>
            <?php elseif ($is_expired): ?>
            <div class="alert alert-danger">
                <strong><i class="fas fa-times-circle"></i> Expired:</strong>
                This quotation expired on <?php echo date('M d, Y', strtotime($quotation['valid_until'])); ?>.
                Please contact us for an updated quote.
            </div>
            <?php endif; ?>
            
            <table class="table">
                <thead style="background: #0E2033; color: white;">
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotation_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-end">$<?php echo number_format($item['price'], 2); ?></td>
                        <td class="text-end">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr style="background: #f8f9fa; font-weight: bold;">
                        <td colspan="3" class="text-end">TOTAL AMOUNT:</td>
                        <td class="text-end">$<?php echo number_format($quotation['total_amount'], 2); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <?php if ($quotation['notes']): ?>
            <div class="mt-4">
                <h5>Notes:</h5>
                <p><?php echo nl2br(htmlspecialchars($quotation['notes'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 p-3" style="background: #f8f9fa; border-left: 4px solid #00A8CE;">
                <h6>Terms & Conditions:</h6>
                <ul class="small mb-0">
                    <li>This quotation is valid until <?php echo date('M d, Y', strtotime($quotation['valid_until'])); ?></li>
                    <li>Prices are subject to change if work is not commenced within the validity period</li>
                    <li>Additional charges may apply for parts and services not included in this quotation</li>
                    <li>Payment terms: As per agreement</li>
                </ul>
            </div>
            
            <div class="text-center mt-4" style="border-top: 2px solid #0E2033; padding-top: 20px;">
                <p class="mb-1"><strong>We look forward to serving you!</strong></p>
                <p class="text-muted small">Please contact us if you have any questions about this quotation.</p>
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
