<?php
// garage_management_system/admin/job_cards.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'Job Cards';
$current_page = 'job_cards';

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
                // Create new job card
                $job_number = $functions->generateJobNumber($_SESSION['garage_id']);
                $vehicle_id = (int)$_POST['vehicle_id'];
                $assigned_to = (int)$_POST['assigned_to'];
                $problem_description = $functions->sanitize($_POST['problem_description']);
                $estimated_hours = (float)$_POST['estimated_hours'];
                $estimated_cost = (float)$_POST['estimated_cost'];
                
                // Get customer_id from vehicle
                $stmt = $db->prepare("SELECT customer_id FROM vehicles WHERE id = ? AND garage_id = ?");
                $stmt->execute([$vehicle_id, $_SESSION['garage_id']]);
                $vehicle = $stmt->fetch();
                
                if ($vehicle) {
                    $stmt = $db->prepare("
                        INSERT INTO job_cards (garage_id, job_number, vehicle_id, customer_id, assigned_to, 
                                              problem_description, estimated_hours, estimated_cost, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([
                        $_SESSION['garage_id'], $job_number, $vehicle_id, $vehicle['customer_id'], 
                        $assigned_to, $problem_description, $estimated_hours, $estimated_cost, $_SESSION['user_id']
                    ])) {
                        $job_card_id = $db->lastInsertId();
                        $success = 'Job card created successfully!';
                        
                        // Redirect to edit page to add services/parts
                        header('Location: job_cards.php?action=edit&id=' . $job_card_id . '&success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to create job card.';
                    }
                } else {
                    $error = 'Invalid vehicle selected.';
                }
                break;
                
            case 'update':
                // Update job card
                $job_card_id = (int)$_POST['id'];
                $status = $functions->sanitize($_POST['status']);
                $diagnosis = $functions->sanitize($_POST['diagnosis'] ?? '');
                $actual_hours = (float)$_POST['actual_hours'] ?? null;
                
                $stmt = $db->prepare("
                    UPDATE job_cards 
                    SET status = ?, diagnosis = ?, actual_hours = ?, updated_at = NOW() 
                    WHERE id = ? AND garage_id = ?
                ");
                
                if ($stmt->execute([$status, $diagnosis, $actual_hours, $job_card_id, $_SESSION['garage_id']])) {
                    $success = 'Job card updated successfully!';
                } else {
                    $error = 'Failed to update job card.';
                }
                break;
                
            case 'add_service':
                // Add service to job card
                $job_card_id = (int)$_POST['job_card_id'];
                $service_id = (int)$_POST['service_id'];
                $quantity = (int)$_POST['quantity'];
                $notes = $functions->sanitize($_POST['notes'] ?? '');
                
                // Get service price
                $stmt = $db->prepare("SELECT price FROM services WHERE id = ? AND garage_id = ?");
                $stmt->execute([$service_id, $_SESSION['garage_id']]);
                $service = $stmt->fetch();
                
                if ($service) {
                    $total_price = $service['price'] * $quantity;
                    
                    $stmt = $db->prepare("
                        INSERT INTO job_services (job_card_id, service_id, quantity, price, notes) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([$job_card_id, $service_id, $quantity, $total_price, $notes])) {
                        $success = 'Service added to job card!';
                    } else {
                        $error = 'Failed to add service.';
                    }
                }
                break;
                
            case 'add_part':
                // Add part to job card
                $job_card_id = (int)$_POST['job_card_id'];
                $inventory_id = (int)$_POST['inventory_id'];
                $quantity = (int)$_POST['quantity'];
                $notes = $functions->sanitize($_POST['notes'] ?? '');
                
                // Check inventory stock
                $stmt = $db->prepare("SELECT quantity, selling_price FROM inventory WHERE id = ? AND garage_id = ?");
                $stmt->execute([$inventory_id, $_SESSION['garage_id']]);
                $part = $stmt->fetch();
                
                if ($part && $part['quantity'] >= $quantity) {
                    $total_price = $part['selling_price'] * $quantity;
                    
                    // Add to job parts
                    $stmt = $db->prepare("
                        INSERT INTO job_parts (job_card_id, inventory_id, quantity, price, notes) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([$job_card_id, $inventory_id, $quantity, $total_price, $notes])) {
                        // Update inventory stock
                        $stmt = $db->prepare("
                            UPDATE inventory 
                            SET quantity = quantity - ? 
                            WHERE id = ? AND garage_id = ?
                        ");
                        $stmt->execute([$quantity, $inventory_id, $_SESSION['garage_id']]);
                        
                        $success = 'Part added to job card!';
                    } else {
                        $error = 'Failed to add part.';
                    }
                } else {
                    $error = 'Insufficient stock or invalid part.';
                }
                break;
                
            case 'complete_job':
                // Complete job and create invoice
                $job_card_id = (int)$_POST['id'];
                
                // Get job card total
                $stmt = $db->prepare("
                    SELECT 
                        jc.*,
                        (SELECT COALESCE(SUM(price), 0) FROM job_services WHERE job_card_id = jc.id) as services_total,
                        (SELECT COALESCE(SUM(price * quantity), 0) FROM job_parts WHERE job_card_id = jc.id) as parts_total
                    FROM job_cards jc
                    WHERE jc.id = ? AND jc.garage_id = ?
                ");
                $stmt->execute([$job_card_id, $_SESSION['garage_id']]);
                $job = $stmt->fetch();
                
                if ($job) {
                    $subtotal = $job['services_total'] + $job['parts_total'];
                    $tax_rate = $functions->getGarageSettings($_SESSION['garage_id'])['default_tax_rate'] ?? 10;
                    $tax_amount = $subtotal * ($tax_rate / 100);
                    $total_amount = $subtotal + $tax_amount;
                    
                    // Generate invoice
                    $invoice_number = $functions->generateInvoiceNumber($_SESSION['garage_id']);
                    
                    $stmt = $db->prepare("
                        INSERT INTO invoices (garage_id, invoice_number, job_card_id, customer_id, vehicle_id, 
                                            subtotal, tax_rate, tax_amount, total_amount, status, created_by)
                        SELECT 
                            ?, ?, ?, customer_id, vehicle_id, ?, ?, ?, ?, 'sent', ?
                        FROM job_cards 
                        WHERE id = ? AND garage_id = ?
                    ");
                    
                    if ($stmt->execute([
                        $_SESSION['garage_id'], $invoice_number, $job_card_id, 
                        $subtotal, $tax_rate, $tax_amount, $total_amount, 
                        $_SESSION['user_id'], $job_card_id, $_SESSION['garage_id']
                    ])) {
                        // Update job card status
                        $stmt = $db->prepare("
                            UPDATE job_cards 
                            SET status = 'completed', updated_at = NOW() 
                            WHERE id = ? AND garage_id = ?
                        ");
                        $stmt->execute([$job_card_id, $_SESSION['garage_id']]);
                        
                        $success = 'Job completed and invoice created!';
                    } else {
                        $error = 'Failed to create invoice.';
                    }
                }
                break;
        }
    }
}

// Get data for forms
$vehicles = [];
$employees = [];
$services = [];
$inventory = [];

$stmt = $db->prepare("
    SELECT v.id, v.registration_number, v.make, v.model, v.year, 
           c.first_name, c.last_name, c.phone
    FROM vehicles v
    JOIN customers c ON v.customer_id = c.id
    WHERE v.garage_id = ?
    ORDER BY v.registration_number
");
$stmt->execute([$_SESSION['garage_id']]);
$vehicles = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT u.id, u.first_name, u.last_name, u.username
    FROM users u
    WHERE u.garage_id = ? AND u.role_id = 3 AND u.status = 'active'
    ORDER BY u.first_name
");
$stmt->execute([$_SESSION['garage_id']]);
$employees = $stmt->fetchAll();

$stmt = $db->prepare("SELECT id, service_code, name, price FROM services WHERE garage_id = ? ORDER BY name");
$stmt->execute([$_SESSION['garage_id']]);
$services = $stmt->fetchAll();

$stmt = $db->prepare("SELECT id, part_code, name, quantity, selling_price FROM inventory WHERE garage_id = ? AND quantity > 0 ORDER BY name");
$stmt->execute([$_SESSION['garage_id']]);
$inventory = $stmt->fetchAll();

// Handle different actions
switch ($action) {
    case 'create':
        // Show create form
        break;
        
    case 'edit':
    case 'view':
        // Get job card details
        $stmt = $db->prepare("
            SELECT jc.*, v.registration_number, v.make, v.model, v.year, v.vin,
                   c.first_name as customer_first, c.last_name as customer_last, c.email, c.phone,
                   u.first_name as assigned_first, u.last_name as assigned_last
            FROM job_cards jc
            JOIN vehicles v ON jc.vehicle_id = v.id
            JOIN customers c ON jc.customer_id = c.id
            LEFT JOIN users u ON jc.assigned_to = u.id
            WHERE jc.id = ? AND jc.garage_id = ?
        ");
        $stmt->execute([$id, $_SESSION['garage_id']]);
        $job_card = $stmt->fetch();
        
        if (!$job_card) {
            header('Location: job_cards.php');
            exit();
        }
        
        // Get job services
        $stmt = $db->prepare("
            SELECT js.*, s.name as service_name, s.service_code
            FROM job_services js
            JOIN services s ON js.service_id = s.id
            WHERE js.job_card_id = ?
        ");
        $stmt->execute([$id]);
        $job_services = $stmt->fetchAll();
        
        // Get job parts
        $stmt = $db->prepare("
            SELECT jp.*, i.name as part_name, i.part_code
            FROM job_parts jp
            JOIN inventory i ON jp.inventory_id = i.id
            WHERE jp.job_card_id = ?
        ");
        $stmt->execute([$id]);
        $job_parts = $stmt->fetchAll();
        break;
        
    case 'delete':
        // Delete job card
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM job_cards WHERE id = ? AND garage_id = ? AND status = 'pending'");
            if ($stmt->execute([$id, $_SESSION['garage_id']])) {
                $success = 'Job card deleted successfully!';
            } else {
                $error = 'Failed to delete job card. Only pending jobs can be deleted.';
            }
        }
        header('Location: job_cards.php?success=' . urlencode($success) . '&error=' . urlencode($error));
        exit();
        
    default:
        // List all job cards
        $status_filter = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        
        $query = "
            SELECT jc.*, v.registration_number, v.make, v.model, 
                   c.first_name, c.last_name, c.phone,
                   u.first_name as tech_first, u.last_name as tech_last
            FROM job_cards jc
            JOIN vehicles v ON jc.vehicle_id = v.id
            JOIN customers c ON jc.customer_id = c.id
            LEFT JOIN users u ON jc.assigned_to = u.id
            WHERE jc.garage_id = ?
        ";
        
        $params = [$_SESSION['garage_id']];
        
        if ($status_filter) {
            $query .= " AND jc.status = ?";
            $params[] = $status_filter;
        }
        
        if ($search) {
            $query .= " AND (jc.job_number LIKE ? OR v.registration_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $query .= " ORDER BY jc.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $job_cards = $stmt->fetchAll();
        break;
}
?>
<?php include '../includes/header.php'; ?>
    <style>
        .job-status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-in_progress { background-color: #0d6efd; color: #fff; }
        .status-completed { background-color: #198754; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
    </style>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if ($action === 'list'): ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Job Cards</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Job Card
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
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" placeholder="Search by job #, vehicle, or customer..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                            <div class="col-md-2">
                                <a href="job_cards.php" class="btn btn-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Job Cards Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Job #</th>
                                        <th>Vehicle</th>
                                        <th>Customer</th>
                                        <th>Assigned To</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($job_cards as $job): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $job['job_number']; ?></strong>
                                        </td>
                                        <td>
                                            <?php echo $job['make'] . ' ' . $job['model']; ?><br>
                                            <small class="text-muted"><?php echo $job['registration_number']; ?></small>
                                        </td>
                                        <td>
                                            <?php echo $job['first_name'] . ' ' . $job['last_name']; ?><br>
                                            <small class="text-muted"><?php echo $job['phone']; ?></small>
                                        </td>
                                        <td>
                                            <?php if ($job['tech_first']): ?>
                                            <?php echo $job['tech_first'] . ' ' . $job['tech_last']; ?>
                                            <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="job-status-badge status-<?php echo $job['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($job['created_at'])); ?><br>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($job['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?action=view&id=<?php echo $job['id']; ?>" class="btn btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?action=edit&id=<?php echo $job['id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($job['status'] === 'pending'): ?>
                                                <a href="?action=delete&id=<?php echo $job['id']; ?>" 
                                                   class="btn btn-danger" title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this job card?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
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
                
                <?php elseif ($action === 'create'): ?>
                <!-- Create Job Card Form -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Create New Job Card</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="job_cards.php" class="btn btn-secondary">
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
                            <input type="hidden" name="action" value="create">
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="vehicle_id" class="form-label">Select Vehicle *</label>
                                        <select class="form-control" id="vehicle_id" name="vehicle_id" required>
                                            <option value="">Choose a vehicle...</option>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo $vehicle['id']; ?>">
                                                <?php echo $vehicle['registration_number'] . ' - ' . $vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ') - ' . $vehicle['first_name'] . ' ' . $vehicle['last_name']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="assigned_to" class="form-label">Assign To</label>
                                        <select class="form-control" id="assigned_to" name="assigned_to">
                                            <option value="">Select Technician</option>
                                            <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['id']; ?>">
                                                <?php echo $employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['username'] . ')'; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="estimated_hours" class="form-label">Estimated Hours</label>
                                        <input type="number" class="form-control" id="estimated_hours" name="estimated_hours" 
                                               step="0.5" min="0.5" value="1.0">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="estimated_cost" class="form-label">Estimated Cost ($)</label>
                                        <input type="number" class="form-control" id="estimated_cost" name="estimated_cost" 
                                               step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="problem_description" class="form-label">Problem Description *</label>
                                <textarea class="form-control" id="problem_description" name="problem_description" 
                                          rows="4" required></textarea>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-save me-2"></i>Create Job Card
                                </button>
                                <a href="job_cards.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php elseif (in_array($action, ['view', 'edit'])): ?>
                <!-- View/Edit Job Card -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <?php echo $action === 'edit' ? 'Edit' : 'View'; ?> Job Card: <?php echo $job_card['job_number']; ?>
                        <span class="job-status-badge status-<?php echo $job_card['status']; ?> ms-2">
                            <?php echo ucfirst(str_replace('_', ' ', $job_card['status'])); ?>
                        </span>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="job_cards.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to List
                            </a>
                            <button onclick="window.print()" class="btn btn-outline-primary">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                        </div>
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
                
                <div class="row">
                    <!-- Job Card Details -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Job Details</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($action === 'edit' && $job_card['status'] !== 'completed' && $job_card['status'] !== 'cancelled'): ?>
                                <form method="POST" action="" class="mb-4">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo $job_card['id']; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="pending" <?php echo $job_card['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="in_progress" <?php echo $job_card['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="waiting_parts" <?php echo $job_card['status'] === 'waiting_parts' ? 'selected' : ''; ?>>Waiting for Parts</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="actual_hours" class="form-label">Actual Hours</label>
                                            <input type="number" class="form-control" id="actual_hours" name="actual_hours" 
                                                   step="0.25" min="0" value="<?php echo $job_card['actual_hours'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="diagnosis" class="form-label">Diagnosis / Technician Notes</label>
                                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"><?php echo $job_card['diagnosis'] ?? ''; ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Job Card
                                    </button>
                                </form>
                                <hr>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Vehicle Information</h6>
                                        <p>
                                            <strong>Registration:</strong> <?php echo $job_card['registration_number']; ?><br>
                                            <strong>Make/Model:</strong> <?php echo $job_card['make'] . ' ' . $job_card['model'] . ' (' . $job_card['year'] . ')'; ?><br>
                                            <strong>VIN:</strong> <?php echo $job_card['vin'] ?? 'N/A'; ?>
                                        </p>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6>Customer Information</h6>
                                        <p>
                                            <strong>Name:</strong> <?php echo $job_card['customer_first'] . ' ' . $job_card['customer_last']; ?><br>
                                            <strong>Phone:</strong> <?php echo $job_card['phone']; ?><br>
                                            <strong>Email:</strong> <?php echo $job_card['email']; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h6>Problem Description</h6>
                                        <p><?php echo nl2br($job_card['problem_description']); ?></p>
                                        
                                        <?php if ($job_card['diagnosis']): ?>
                                        <h6 class="mt-3">Diagnosis</h6>
                                        <p><?php echo nl2br($job_card['diagnosis']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <strong>Assigned To:</strong><br>
                                        <?php echo $job_card['assigned_first'] . ' ' . $job_card['assigned_last'] ?? 'Not assigned'; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Estimated Hours:</strong><br>
                                        <?php echo $job_card['estimated_hours'] ?? 'N/A'; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Actual Hours:</strong><br>
                                        <?php echo $job_card['actual_hours'] ?? 'N/A'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Services Section -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Services</h5>
                                <?php if ($action === 'edit' && $job_card['status'] !== 'completed' && $job_card['status'] !== 'cancelled'): ?>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                                    <i class="fas fa-plus me-1"></i>Add Service
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Service Code</th>
                                                <th>Service Name</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Total</th>
                                                <?php if ($action === 'edit' && $job_card['status'] !== 'completed' && $job_card['status'] !== 'cancelled'): ?>
                                                <th>Actions</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $services_total = 0;
                                            foreach ($job_services as $service): 
                                                $services_total += $service['price'];
                                            ?>
                                            <tr>
                                                <td><?php echo $service['service_code']; ?></td>
                                                <td>
                                                    <?php echo $service['service_name']; ?>
                                                    <?php if ($service['notes']): ?>
                                                    <br><small class="text-muted"><?php echo $service['notes']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $service['quantity']; ?></td>
                                                <td><?php echo $functions->formatCurrency($service['price'] / $service['quantity']); ?></td>
                                                <td><?php echo $functions->formatCurrency($service['price']); ?></td>
                                                <?php if ($action === 'edit' && $job_card['status'] !== 'completed' && $job_card['status'] !== 'cancelled'): ?>
                                                <td>
                                                    <a href="?action=delete_service&id=<?php echo $service['id']; ?>&job_id=<?php echo $job_card['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Remove this service?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($job_services)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No services added yet</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="4" class="text-end">Services Total:</th>
                                                <th><?php echo $functions->formatCurrency($services_total); ?></th>
                                                <?php if ($action === 'edit' && $job_card['status'] !== 'completed' && $job_card['status'] !== 'cancelled'): ?>
                                                <th></th>
                                                <?php endif; ?>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Parts Section -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Parts Used</h5>
                                <?php if ($action === 'edit' && $job_card['status'] !== 'completed' && $job_card['status'] !== 'cancelled'): ?>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal">
                                    <i class="fas fa-plus me-1"></i>Add Part
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Part Code</th>
                                                <th>Part Name</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Total</th>
                                                <?php if ($action === 'edit' && $job_card['status'] !== 'completed' && $job_card['status'] !== 'cancelled'): ?>
                                                <th>Actions</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $parts_total = 0;
                                            foreach ($job_parts as $part): 
                                                $parts_total += $part['price'] * $part['quantity'];
                                            ?>
                                            <tr>
                                                <td><?php echo $part['part_code']; ?></td>
                                                <td>
                                                    <?php echo $part['part_name']; ?>
                                                    <?php if ($part['notes']): ?>
                                                    <br><small class="text-muted"><?php echo $part['notes']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $part['quantity']; ?></td>
                                                <td><?php echo $functions->formatCurrency($part['price'] / $part['quantity']); ?></td>
                                                <td><?php echo $functions->formatCurrency($part['price']); ?></td>
                                                <?php if ($action === 'edit' && $job_card['status'] !== 'completed' && $job_card['status'] !== 'cancelled'): ?>
                                                <td>
                                                    <a href="?action=delete_part&id=<?php echo $part['id']; ?>&job_id=<?php echo $job_card['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Remove this part?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($job_parts)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No parts used yet</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="4" class="text-end">Parts Total:</th>
                                                <th><?php echo $functions->formatCurrency($parts_total); ?></th>
                                                <?php if ($action === 'edit' && $job_card['status'] !== 'completed' && $job_card['status'] !== 'cancelled'): ?>
                                                <th></th>
                                                <?php endif; ?>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar: Actions and Summary -->
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Job Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Job Number:</strong><br>
                                    <?php echo $job_card['job_number']; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Created:</strong><br>
                                    <?php echo date('F j, Y', strtotime($job_card['created_at'])); ?><br>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($job_card['created_at'])); ?></small>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Last Updated:</strong><br>
                                    <?php echo $job_card['updated_at'] ? date('F j, Y', strtotime($job_card['updated_at'])) : 'N/A'; ?><br>
                                    <?php if ($job_card['updated_at']): ?>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($job_card['updated_at'])); ?></small>
                                    <?php endif; ?>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-2">
                                    <strong>Services Total:</strong>
                                    <span class="float-end"><?php echo $functions->formatCurrency($services_total); ?></span>
                                </div>
                                
                                <div class="mb-2">
                                    <strong>Parts Total:</strong>
                                    <span class="float-end"><?php echo $functions->formatCurrency($parts_total); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Subtotal:</strong>
                                    <span class="float-end"><?php echo $functions->formatCurrency($services_total + $parts_total); ?></span>
                                </div>
                                
                                <?php
                                $tax_rate = $functions->getGarageSettings($_SESSION['garage_id'])['default_tax_rate'] ?? 10;
                                $tax_amount = ($services_total + $parts_total) * ($tax_rate / 100);
                                $total_amount = $services_total + $parts_total + $tax_amount;
                                ?>
                                
                                <div class="mb-2">
                                    <strong>Tax (<?php echo $tax_rate; ?>%):</strong>
                                    <span class="float-end"><?php echo $functions->formatCurrency($tax_amount); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Estimated Total:</strong>
                                    <span class="float-end h5 text-primary"><?php echo $functions->formatCurrency($total_amount); ?></span>
                                </div>
                                
                                <hr>
                                
                                <?php if ($action === 'edit' && $job_card['status'] !== 'completed' && $job_card['status'] !== 'cancelled'): ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="complete_job">
                                    <input type="hidden" name="id" value="<?php echo $job_card['id']; ?>">
                                    
                                    <button type="submit" class="btn btn-success w-100 mb-2" 
                                            onclick="return confirm('Complete this job and create invoice?')">
                                        <i class="fas fa-check-circle me-2"></i>Complete Job & Create Invoice
                                    </button>
                                </form>
                                
                                <a href="?action=cancel&id=<?php echo $job_card['id']; ?>" class="btn btn-danger w-100"
                                   onclick="return confirm('Cancel this job? This action cannot be undone.')">
                                    <i class="fas fa-times-circle me-2"></i>Cancel Job
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($job_card['status'] === 'completed'): ?>
                                <?php
                                // Check if invoice exists
                                $stmt = $db->prepare("SELECT id, invoice_number FROM invoices WHERE job_card_id = ?");
                                $stmt->execute([$job_card['id']]);
                                $invoice = $stmt->fetch();
                                ?>
                                
                                <?php if ($invoice): ?>
                                <a href="invoices.php?action=view&id=<?php echo $invoice['id']; ?>" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-file-invoice me-2"></i>View Invoice <?php echo $invoice['invoice_number']; ?>
                                </a>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="tel:<?php echo $job_card['phone']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-phone me-2"></i>Call Customer
                                    </a>
                                    <a href="mailto:<?php echo $job_card['email']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-envelope me-2"></i>Email Customer
                                    </a>
                                    <a href="gate_pass.php?vehicle_id=<?php echo $job_card['vehicle_id']; ?>" class="btn btn-outline-info">
                                        <i class="fas fa-id-card me-2"></i>Create Gate Pass
                                    </a>
                                    <a href="quotations.php?job_id=<?php echo $job_card['id']; ?>" class="btn btn-outline-warning">
                                        <i class="fas fa-file-invoice-dollar me-2"></i>Create Quotation
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Add Service Modal -->
                <div class="modal fade" id="addServiceModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Service</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="add_service">
                                    <input type="hidden" name="job_card_id" value="<?php echo $job_card['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="service_id" class="form-label">Service</label>
                                        <select class="form-control" id="service_id" name="service_id" required>
                                            <option value="">Select Service</option>
                                            <?php foreach ($services as $service): ?>
                                            <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>">
                                                <?php echo $service['service_code'] . ' - ' . $service['name'] . ' ($' . $service['price'] . ')'; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="quantity" class="form-label">Quantity</label>
                                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Unit Price</label>
                                            <input type="text" class="form-control" id="unit_price" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes (Optional)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Total Price</label>
                                        <input type="text" class="form-control" id="total_price" readonly>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Service</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Add Part Modal -->
                <div class="modal fade" id="addPartModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Part</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="add_part">
                                    <input type="hidden" name="job_card_id" value="<?php echo $job_card['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="inventory_id" class="form-label">Part</label>
                                        <select class="form-control" id="inventory_id" name="inventory_id" required>
                                            <option value="">Select Part</option>
                                            <?php foreach ($inventory as $item): ?>
                                            <option value="<?php echo $item['id']; ?>" 
                                                    data-price="<?php echo $item['selling_price']; ?>"
                                                    data-stock="<?php echo $item['quantity']; ?>">
                                                <?php echo $item['part_code'] . ' - ' . $item['name'] . ' (Stock: ' . $item['quantity'] . ')'; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted" id="stock-info"></small>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="part_quantity" class="form-label">Quantity</label>
                                            <input type="number" class="form-control" id="part_quantity" name="quantity" value="1" min="1" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Unit Price</label>
                                            <input type="text" class="form-control" id="part_unit_price" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="part_notes" class="form-label">Notes (Optional)</label>
                                        <textarea class="form-control" id="part_notes" name="notes" rows="2"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Total Price</label>
                                        <input type="text" class="form-control" id="part_total_price" readonly>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Part</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <script>
                    // Service modal calculations
                    $('#service_id').change(function() {
                        const price = $(this).find(':selected').data('price') || 0;
                        $('#unit_price').val('$' + price.toFixed(2));
                        calculateServiceTotal();
                    });
                    
                    $('#quantity').on('input', function() {
                        calculateServiceTotal();
                    });
                    
                    function calculateServiceTotal() {
                        const price = $('#service_id').find(':selected').data('price') || 0;
                        const quantity = $('#quantity').val() || 0;
                        const total = price * quantity;
                        $('#total_price').val('$' + total.toFixed(2));
                    }
                    
                    // Part modal calculations
                    $('#inventory_id').change(function() {
                        const price = $(this).find(':selected').data('price') || 0;
                        const stock = $(this).find(':selected').data('stock') || 0;
                        $('#part_unit_price').val('$' + price.toFixed(2));
                        $('#stock-info').text('Available stock: ' + stock);
                        calculatePartTotal();
                    });
                    
                    $('#part_quantity').on('input', function() {
                        calculatePartTotal();
                    });
                    
                    function calculatePartTotal() {
                        const price = $('#inventory_id').find(':selected').data('price') || 0;
                        const quantity = $('#part_quantity').val() || 0;
                        const total = price * quantity;
                        $('#part_total_price').val('$' + total.toFixed(2));
                    }
                </script>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>