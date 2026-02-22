<?php
// garage_management_system/api/get_customer.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Customer ID required']);
    exit();
}

try {
    $stmt = $db->prepare("
        SELECT c.*, COUNT(v.id) as vehicle_count
        FROM customers c
        LEFT JOIN vehicles v ON c.id = v.customer_id
        WHERE c.id = ? AND c.garage_id = ?
        GROUP BY c.id
    ");
    
    $stmt->execute([$_GET['id'], $_SESSION['garage_id']]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        echo json_encode([
            'success' => true,
            'data' => $customer
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
