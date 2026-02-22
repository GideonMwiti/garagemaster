<?php
// garage_management_system/admin/inventory.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'Inventory Management';
$current_page = 'inventory';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        switch ($_POST['action']) {
            case 'create':
                $part_code = $functions->sanitize($_POST['part_code']);
                $name = $functions->sanitize($_POST['name']);
                $description = $functions->sanitize($_POST['description'] ?? '');
                $category = $functions->sanitize($_POST['category']);
                $quantity = (int)$_POST['quantity'];
                $reorder_level = (int)$_POST['reorder_level'];
                $unit_price = (float)$_POST['unit_price'];
                $selling_price = (float)$_POST['selling_price'];
                $supplier = $functions->sanitize($_POST['supplier'] ?? '');
                
                // Check if part code already exists
                $stmt = $db->prepare("SELECT id FROM inventory WHERE part_code = ? AND garage_id = ?");
                $stmt->execute([$part_code, $_SESSION['garage_id']]);
                
                if ($stmt->fetch()) {
                    $error = 'Part code already exists. Please use a unique code.';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO inventory (garage_id, part_code, name, description, category, 
                                              quantity, reorder_level, unit_price, selling_price, supplier) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([
                        $_SESSION['garage_id'], $part_code, $name, $description, $category,
                        $quantity, $reorder_level, $unit_price, $selling_price, $supplier
                    ])) {
                        $success = 'Inventory item added successfully!';
                        header('Location: inventory.php?success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to add inventory item.';
                    }
                }
                break;
                
            case 'update':
                $item_id = (int)$_POST['id'];
                $name = $functions->sanitize($_POST['name']);
                $description = $functions->sanitize($_POST['description'] ?? '');
                $category = $functions->sanitize($_POST['category']);
                $reorder_level = (int)$_POST['reorder_level'];
                $unit_price = (float)$_POST['unit_price'];
                $selling_price = (float)$_POST['selling_price'];
                $supplier = $functions->sanitize($_POST['supplier'] ?? '');
                
                $stmt = $db->prepare("
                    UPDATE inventory 
                    SET name = ?, description = ?, category = ?, reorder_level = ?, 
                        unit_price = ?, selling_price = ?, supplier = ? 
                    WHERE id = ? AND garage_id = ?
                ");
                
                if ($stmt->execute([
                    $name, $description, $category, $reorder_level,
                    $unit_price, $selling_price, $supplier, $item_id, $_SESSION['garage_id']
                ])) {
                    $success = 'Inventory item updated successfully!';
                    header('Location: inventory.php?success=' . urlencode($success));
                    exit();
                } else {
                    $error = 'Failed to update inventory item.';
                }
                break;
                
            case 'adjust_stock':
                $item_id = (int)$_POST['id'];
                $adjustment_type = $functions->sanitize($_POST['adjustment_type']);
                $quantity = (int)$_POST['quantity'];
                $reason = $functions->sanitize($_POST['reason'] ?? '');
                
                if ($adjustment_type === 'add') {
                    $stmt = $db->prepare("
                        UPDATE inventory 
                        SET quantity = quantity + ? 
                        WHERE id = ? AND garage_id = ?
                    ");
                } else {
                    $stmt = $db->prepare("
                        UPDATE inventory 
                        SET quantity = quantity - ? 
                        WHERE id = ? AND garage_id = ?
                    ");
                }
                
                if ($stmt->execute([$quantity, $item_id, $_SESSION['garage_id']])) {
                    // Log the adjustment
                    $stmt = $db->prepare("
                        INSERT INTO inventory_adjustments (inventory_id, adjustment_type, quantity, reason, adjusted_by) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$item_id, $adjustment_type, $quantity, $reason, $_SESSION['user_id']]);
                    
                    $success = 'Stock adjusted successfully!';
                    header('Location: inventory.php?success=' . urlencode($success));
                    exit();
                } else {
                    $error = 'Failed to adjust stock.';
                }
                break;
                
            case 'delete':
                $item_id = (int)$_POST['id'];
                
                // Check if item is used in any job cards
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM job_parts 
                    WHERE inventory_id = ?
                ");
                $stmt->execute([$item_id]);
                $usage = $stmt->fetch();
                
                if ($usage['count'] > 0) {
                    $error = 'Cannot delete item. It has been used in job cards.';
                } else {
                    $stmt = $db->prepare("DELETE FROM inventory WHERE id = ? AND garage_id = ?");
                    if ($stmt->execute([$item_id, $_SESSION['garage_id']])) {
                        $success = 'Inventory item deleted successfully!';
                        header('Location: inventory.php?success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to delete inventory item.';
                    }
                }
                break;
        }
    }
}

// Get categories
$stmt = $db->prepare("SELECT DISTINCT category FROM inventory WHERE garage_id = ? AND category IS NOT NULL ORDER BY category");
$stmt->execute([$_SESSION['garage_id']]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($action === 'create' || $action === 'edit') {
    if ($action === 'edit' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM inventory WHERE id = ? AND garage_id = ?");
        $stmt->execute([$id, $_SESSION['garage_id']]);
        $item = $stmt->fetch();
        
        if (!$item) {
            header('Location: inventory.php');
            exit();
        }
    }
} else {
    // List items with filters
    $category_filter = $_GET['category'] ?? '';
    $stock_filter = $_GET['stock'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $query = "SELECT * FROM inventory WHERE garage_id = ?";
    $params = [$_SESSION['garage_id']];
    
    if ($category_filter) {
        $query .= " AND category = ?";
        $params[] = $category_filter;
    }
    
    if ($stock_filter === 'low') {
        $query .= " AND quantity <= reorder_level";
    } elseif ($stock_filter === 'out') {
        $query .= " AND quantity = 0";
    }
    
    if ($search) {
        $query .= " AND (part_code LIKE ? OR name LIKE ? OR description LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    
    // Calculate inventory value
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_items,
            SUM(quantity) as total_quantity,
            SUM(quantity * unit_price) as total_cost_value,
            SUM(quantity * selling_price) as total_selling_value
        FROM inventory 
        WHERE garage_id = ?
    ");
    $stmt->execute([$_SESSION['garage_id']]);
    $inventory_stats = $stmt->fetch();
    
    // Get low stock items
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM inventory WHERE garage_id = ? AND quantity <= reorder_level");
    $stmt->execute([$_SESSION['garage_id']]);
    $low_stock_count = $stmt->fetch()['count'];
    
    // Get out of stock items
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM inventory WHERE garage_id = ? AND quantity = 0");
    $stmt->execute([$_SESSION['garage_id']]);
    $out_of_stock_count = $stmt->fetch()['count'];
}

include '../includes/header.php';
?>
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if ($action === 'list'): ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Inventory Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Item
                        </a>
                    </div>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Inventory Stats -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Items
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $inventory_stats['total_items'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-boxes fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Low Stock Items
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $low_stock_count; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Out of Stock
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $out_of_stock_count; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-times-circle fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Inventory Value
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $functions->formatCurrency($inventory_stats['total_cost_value'] ?? 0); ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            Potential: <?php echo $functions->formatCurrency($inventory_stats['total_selling_value'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" placeholder="Search by code, name..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <select class="form-control" name="category" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" 
                                            <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <select class="form-control" name="stock" onchange="this.form.submit()">
                                    <option value="">All Stock Levels</option>
                                    <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <a href="inventory.php" class="btn btn-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Inventory Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Part Code</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th>Reorder Level</th>
                                        <th>Cost Price</th>
                                        <th>Selling Price</th>
                                        <th>Stock Value</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): 
                                        $stock_value = $item['quantity'] * $item['unit_price'];
                                        $selling_value = $item['quantity'] * $item['selling_price'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['part_code']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($item['name']); ?>
                                            <?php if ($item['description']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                                        <td>
                                            <?php echo $item['quantity']; ?>
                                            <?php if ($item['quantity'] <= $item['reorder_level']): ?>
                                            <br><small class="text-danger">
                                                <i class="fas fa-exclamation-circle"></i> Reorder needed
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $item['reorder_level']; ?></td>
                                        <td><?php echo $functions->formatCurrency($item['unit_price']); ?></td>
                                        <td><?php echo $functions->formatCurrency($item['selling_price']); ?></td>
                                        <td><?php echo $functions->formatCurrency($stock_value); ?></td>
                                        <td>
                                            <?php if ($item['quantity'] == 0): ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                            <?php elseif ($item['quantity'] <= $item['reorder_level']): ?>
                                            <span class="badge bg-warning">Low Stock</span>
                                            <?php else: ?>
                                            <span class="badge bg-success">In Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-info" data-bs-toggle="modal" 
                                                        data-bs-target="#viewModal<?php echo $item['id']; ?>" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <a href="?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" 
                                                        data-bs-target="#stockModal<?php echo $item['id']; ?>" title="Adjust Stock">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal<?php echo $item['id']; ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $item['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Item Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <strong>Part Code:</strong><br>
                                                        <?php echo htmlspecialchars($item['part_code']); ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Name:</strong><br>
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Description:</strong><br>
                                                        <?php echo nl2br(htmlspecialchars($item['description'] ?: 'No description')); ?>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <strong>Category:</strong><br>
                                                            <?php echo htmlspecialchars($item['category']); ?>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <strong>Supplier:</strong><br>
                                                            <?php echo htmlspecialchars($item['supplier'] ?: 'Not specified'); ?>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <strong>Quantity:</strong><br>
                                                            <?php echo $item['quantity']; ?>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <strong>Reorder Level:</strong><br>
                                                            <?php echo $item['reorder_level']; ?>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <strong>Cost Price:</strong><br>
                                                            <?php echo $functions->formatCurrency($item['unit_price']); ?>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <strong>Selling Price:</strong><br>
                                                            <?php echo $functions->formatCurrency($item['selling_price']); ?>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <strong>Cost Value:</strong><br>
                                                            <?php echo $functions->formatCurrency($stock_value); ?>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <strong>Selling Value:</strong><br>
                                                            <?php echo $functions->formatCurrency($selling_value); ?>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Status:</strong><br>
                                                        <?php if ($item['quantity'] == 0): ?>
                                                        <span class="badge bg-danger">Out of Stock</span>
                                                        <?php elseif ($item['quantity'] <= $item['reorder_level']): ?>
                                                        <span class="badge bg-warning">Low Stock</span>
                                                        <?php else: ?>
                                                        <span class="badge bg-success">In Stock</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Stock Adjustment Modal -->
                                    <div class="modal fade" id="stockModal<?php echo $item['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Adjust Stock: <?php echo htmlspecialchars($item['name']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="adjust_stock">
                                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Current Stock: <?php echo $item['quantity']; ?></label>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="adjustment_type" class="form-label">Adjustment Type</label>
                                                            <select class="form-control" id="adjustment_type" name="adjustment_type" required>
                                                                <option value="add">Add Stock</option>
                                                                <option value="remove">Remove Stock</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="quantity" class="form-label">Quantity</label>
                                                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                                                   min="1" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="reason" class="form-label">Reason (Optional)</label>
                                                            <select class="form-control" id="reason" name="reason">
                                                                <option value="">Select Reason</option>
                                                                <option value="purchase">New Purchase</option>
                                                                <option value="return">Customer Return</option>
                                                                <option value="damaged">Damaged/Lost</option>
                                                                <option value="adjustment">Stock Adjustment</option>
                                                                <option value="other">Other</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            This adjustment will be logged for audit purposes.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Adjust Stock</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $item['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Delete Item</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                        
                                                        <div class="alert alert-danger">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            Are you sure you want to delete this item?
                                                        </div>
                                                        
                                                        <p>
                                                            <strong>Item:</strong> <?php echo htmlspecialchars($item['name']); ?><br>
                                                            <strong>Code:</strong> <?php echo htmlspecialchars($item['part_code']); ?><br>
                                                            <strong>Current Stock:</strong> <?php echo $item['quantity']; ?>
                                                        </p>
                                                        
                                                        <p class="text-muted">
                                                            <small>
                                                                Note: Items that have been used in job cards cannot be deleted.
                                                            </small>
                                                        </p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Delete Item</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Low Stock Alert -->
                <?php 
                $stmt = $db->prepare("SELECT * FROM inventory WHERE garage_id = ? AND quantity <= reorder_level ORDER BY quantity ASC");
                $stmt->execute([$_SESSION['garage_id']]);
                $low_stock_items = $stmt->fetchAll();
                
                if ($low_stock_items): ?>
                <div class="card border-left-warning mt-4">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Part Code</th>
                                        <th>Name</th>
                                        <th>Current Stock</th>
                                        <th>Reorder Level</th>
                                        <th>Supplier</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($low_stock_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['part_code']); ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td class="text-danger">
                                            <strong><?php echo $item['quantity']; ?></strong>
                                        </td>
                                        <td><?php echo $item['reorder_level']; ?></td>
                                        <td><?php echo htmlspecialchars($item['supplier'] ?: 'Not specified'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#stockModal<?php echo $item['id']; ?>" 
                                                    title="Reorder Stock">
                                                <i class="fas fa-plus-circle me-1"></i>Reorder
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php elseif ($action === 'create' || $action === 'edit'): ?>
                <!-- Create/Edit Form -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $action === 'create' ? 'Add New' : 'Edit'; ?> Inventory Item</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="inventory.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                    </div>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                            
                            <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="part_code" class="form-label">Part Code *</label>
                                    <input type="text" class="form-control" id="part_code" name="part_code" 
                                           value="<?php echo $item['part_code'] ?? ''; ?>" required 
                                           <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                                    <small class="text-muted">Unique identifier for this part</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Item Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo $item['name'] ?? ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3"><?php echo $item['description'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-control" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="Lubricants" <?php echo ($item['category'] ?? '') === 'Lubricants' ? 'selected' : ''; ?>>Lubricants</option>
                                        <option value="Filters" <?php echo ($item['category'] ?? '') === 'Filters' ? 'selected' : ''; ?>>Filters</option>
                                        <option value="Brakes" <?php echo ($item['category'] ?? '') === 'Brakes' ? 'selected' : ''; ?>>Brakes</option>
                                        <option value="Tires" <?php echo ($item['category'] ?? '') === 'Tires' ? 'selected' : ''; ?>>Tires</option>
                                        <option value="Battery" <?php echo ($item['category'] ?? '') === 'Battery' ? 'selected' : ''; ?>>Battery</option>
                                        <option value="Electrical" <?php echo ($item['category'] ?? '') === 'Electrical' ? 'selected' : ''; ?>>Electrical</option>
                                        <option value="Body Parts" <?php echo ($item['category'] ?? '') === 'Body Parts' ? 'selected' : ''; ?>>Body Parts</option>
                                        <option value="Engine Parts" <?php echo ($item['category'] ?? '') === 'Engine Parts' ? 'selected' : ''; ?>>Engine Parts</option>
                                        <option value="Tools" <?php echo ($item['category'] ?? '') === 'Tools' ? 'selected' : ''; ?>>Tools</option>
                                        <option value="Consumables" <?php echo ($item['category'] ?? '') === 'Consumables' ? 'selected' : ''; ?>>Consumables</option>
                                        <option value="Other" <?php echo ($item['category'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <?php if ($action === 'create'): ?>
                                <div class="col-md-4 mb-3">
                                    <label for="quantity" class="form-label">Initial Quantity *</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           value="<?php echo $item['quantity'] ?? 0; ?>" min="0" required>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="reorder_level" class="form-label">Reorder Level *</label>
                                    <input type="number" class="form-control" id="reorder_level" name="reorder_level" 
                                           value="<?php echo $item['reorder_level'] ?? 10; ?>" min="0" required>
                                    <small class="text-muted">System will alert when stock reaches this level</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="unit_price" class="form-label">Cost Price *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">KSH</span>
                                        <input type="number" class="form-control" id="unit_price" name="unit_price" 
                                               step="0.01" min="0" value="<?php echo $item['unit_price'] ?? 0; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="selling_price" class="form-label">Selling Price *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">KSH</span>
                                        <input type="number" class="form-control" id="selling_price" name="selling_price" 
                                               step="0.01" min="0" value="<?php echo $item['selling_price'] ?? 0; ?>" required>
                                    </div>
                                    <small class="text-muted">Price charged to customers</small>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="supplier" class="form-label">Supplier</label>
                                    <input type="text" class="form-control" id="supplier" name="supplier" 
                                           value="<?php echo $item['supplier'] ?? ''; ?>">
                                    <small class="text-muted">Primary supplier for this item</small>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $action === 'create' ? 'Add Item' : 'Update Item'; ?>
                                </button>
                                <a href="inventory.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>

<?php include '../includes/footer.php'; ?>