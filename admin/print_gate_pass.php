<?php
// garage_management_system/admin/print_gate_pass.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$id = $_GET['id'] ?? 0;

if (!$id) {
    die('Invalid Gate Pass ID');
}

$stmt = $db->prepare("
    SELECT g.*, v.registration_number, v.make, v.model, v.color,
           c.first_name, c.last_name, c.phone, c.company
    FROM gate_pass g
    JOIN vehicles v ON g.vehicle_id = v.id
    JOIN customers c ON g.customer_id = c.id
    WHERE g.id = ? AND g.garage_id = ?
");
$stmt->execute([$id, $_SESSION['garage_id']]);
$pass = $stmt->fetch();

if (!$pass) {
    die('Gate Pass not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Pass #<?php echo $pass['pass_number']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; margin: 0; padding: 20px; }
        .pass-container { border: 2px solid #000; padding: 20px; max-width: 800px; margin: 0 auto; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px; }
        .logo { max-height: 80px; margin-bottom: 10px; }
        .title { font-size: 24px; font-weight: bold; text-transform: uppercase; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .section { margin-bottom: 20px; }
        .section-title { font-weight: bold; border-bottom: 1px solid #ccc; margin-bottom: 10px; }
        .row { display: flex; margin-bottom: 5px; }
        .label { width: 150px; font-weight: bold; }
        .value { flex: 1; }
        .footer { margin-top: 50px; text-align: center; font-size: 12px; }
        .signatures { margin-top: 50px; display: flex; justify-content: space-between; }
        .signature-box { text-align: center; border-top: 1px solid #000; width: 200px; padding-top: 5px; }
        
        @media print {
            body { padding: 0; }
            .pass-container { border: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="pass-container">
        <div class="header">
            <?php if (defined('BRAND_LOGO')): ?>
            <img src="<?php echo BASE_URL . BRAND_LOGO; ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <div class="title">Vehicle Gate Pass</div>
            <div><?php echo SITE_NAME; ?></div>
        </div>
        
        <div class="meta">
            <div><strong>Pass Number:</strong> <?php echo $pass['pass_number']; ?></div>
            <div><strong>Date:</strong> <?php echo date('d-M-Y H:i', strtotime($pass['created_at'])); ?></div>
        </div>
        
        <div class="section">
            <div class="section-title">Vehicle Details</div>
            <div class="row">
                <div class="label">Registration No:</div>
                <div class="value"><?php echo $pass['registration_number']; ?></div>
            </div>
            <div class="row">
                <div class="label">Make / Model:</div>
                <div class="value"><?php echo $pass['make'] . ' ' . $pass['model']; ?></div>
            </div>
            <div class="row">
                <div class="label">Color:</div>
                <div class="value"><?php echo $pass['color'] ?? 'N/A'; ?></div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Customer Details</div>
            <div class="row">
                <div class="label">Name:</div>
                <div class="value"><?php echo $pass['first_name'] . ' ' . $pass['last_name']; ?></div>
            </div>
            <div class="row">
                <div class="label">Phone:</div>
                <div class="value"><?php echo $pass['phone']; ?></div>
            </div>
            <?php if ($pass['company']): ?>
            <div class="row">
                <div class="label">Company:</div>
                <div class="value"><?php echo $pass['company']; ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <div class="section-title">Pass Details</div>
            <div class="row">
                <div class="label">Purpose:</div>
                <div class="value"><?php echo ucfirst($pass['purpose']); ?></div>
            </div>
            <?php if ($pass['security_notes']): ?>
            <div class="row">
                <div class="label">Notes:</div>
                <div class="value"><?php echo nl2br($pass['security_notes']); ?></div>
            </div>
            <?php endif; ?>
            <div class="row">
                <div class="label">Issued By:</div>
                <div class="value">Authorized Staff</div>
            </div>
        </div>
        
        <div class="signatures">
            <div class="signature-box">
                Authorized Signature
            </div>
            <div class="signature-box">
                Security Check
            </div>
            <div class="signature-box">
                Customer Signature
            </div>
        </div>
        
        <div class="footer">
            This is a computer-generated document.
        </div>
    </div>
</body>
</html>
