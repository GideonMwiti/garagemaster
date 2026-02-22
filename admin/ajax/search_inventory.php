<?php
// garage_management_system/admin/ajax/search_inventory.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check auth
if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search = $_GET['term'] ?? '';
$garage_id = $_SESSION['garage_id'];

if (strlen($search) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT id, part_code, name, selling_price, quantity 
        FROM inventory 
        WHERE garage_id = ? 
        AND (name LIKE ? OR part_code LIKE ?) 
        AND quantity > 0
        LIMIT 20
    ");
    $term = "%$search%";
    $stmt->execute([$garage_id, $term, $term]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for jQuery UI Autocomplete
    $formatted = [];
    foreach ($results as $item) {
        $formatted[] = [
            'id' => $item['id'],
            'label' => $item['part_code'] . ' - ' . $item['name'] . ' (Stock: ' . $item['quantity'] . ')',
            'value' => $item['name'],
            'price' => $item['selling_price'],
            'stock' => $item['quantity']
        ];
    }
    
    echo json_encode($formatted);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
