<?php
// garage_management_system/admin/vehicles.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'Vehicle Management';
$current_page = 'vehicles';

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
                $registration_number = $functions->sanitize($_POST['registration_number']);
                $make = $functions->sanitize($_POST['make']);
                $model = $functions->sanitize($_POST['model']);
                $year = (int)$_POST['year'];
                $vin = $functions->sanitize($_POST['vin'] ?? '');
                $engine_number = $functions->sanitize($_POST['engine_number'] ?? '');
                $color = $functions->sanitize($_POST['color'] ?? '');
                $fuel_type = $functions->sanitize($_POST['fuel_type']);
                $customer_id = (int)$_POST['customer_id'];
                $last_service_date = $_POST['last_service_date'] ?? null;
                $next_service_date = $_POST['next_service_date'] ?? null;
                
                // Check if registration number already exists
                $stmt = $db->prepare("SELECT id FROM vehicles WHERE registration_number = ? AND garage_id = ?");
                $stmt->execute([$registration_number, $_SESSION['garage_id']]);
                
                if ($stmt->fetch()) {
                    $error = 'Vehicle with this registration number already exists.';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO vehicles (garage_id, customer_id, registration_number, make, model, year, 
                                             vin, engine_number, color, fuel_type, last_service_date, next_service_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([
                        $_SESSION['garage_id'], $customer_id, $registration_number, $make, $model, $year,
                        $vin, $engine_number, $color, $fuel_type, $last_service_date, $next_service_date
                    ])) {
                        $success = 'Vehicle added successfully!';
                        header('Location: vehicles.php?success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to add vehicle.';
                    }
                }
                break;
                
            case 'update':
                $vehicle_id = (int)$_POST['id'];
                $registration_number = $functions->sanitize($_POST['registration_number']);
                $make = $functions->sanitize($_POST['make']);
                $model = $functions->sanitize($_POST['model']);
                $year = (int)$_POST['year'];
                $vin = $functions->sanitize($_POST['vin'] ?? '');
                $engine_number = $functions->sanitize($_POST['engine_number'] ?? '');
                $color = $functions->sanitize($_POST['color'] ?? '');
                $fuel_type = $functions->sanitize($_POST['fuel_type']);
                $customer_id = (int)$_POST['customer_id'];
                $last_service_date = $_POST['last_service_date'] ?? null;
                $next_service_date = $_POST['next_service_date'] ?? null;
                
                $stmt = $db->prepare("
                    UPDATE vehicles 
                    SET registration_number = ?, make = ?, model = ?, year = ?, vin = ?, 
                        engine_number = ?, color = ?, fuel_type = ?, customer_id = ?,
                        last_service_date = ?, next_service_date = ? 
                    WHERE id = ? AND garage_id = ?
                ");
                
                if ($stmt->execute([
                    $registration_number, $make, $model, $year, $vin, $engine_number, 
                    $color, $fuel_type, $customer_id, $last_service_date, $next_service_date,
                    $vehicle_id, $_SESSION['garage_id']
                ])) {
                    $success = 'Vehicle updated successfully!';
                    header('Location: vehicles.php?success=' . urlencode($success));
                    exit();
                } else {
                    $error = 'Failed to update vehicle.';
                }
                break;
                
            case 'delete':
                $vehicle_id = (int)$_POST['id'];
                
                // Check if vehicle has job cards
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM job_cards WHERE vehicle_id = ?");
                $stmt->execute([$vehicle_id]);
                $usage = $stmt->fetch();
                
                if ($usage['count'] > 0) {
                    $error = 'Cannot delete vehicle. It has associated job cards.';
                } else {
                    $stmt = $db->prepare("DELETE FROM vehicles WHERE id = ? AND garage_id = ?");
                    if ($stmt->execute([$vehicle_id, $_SESSION['garage_id']])) {
                        $success = 'Vehicle deleted successfully!';
                        header('Location: vehicles.php?success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to delete vehicle.';
                    }
                }
                break;
                
            case 'update_service_dates':
                $vehicle_id = (int)$_POST['id'];
                $last_service_date = $_POST['last_service_date'] ?? null;
                $next_service_date = $_POST['next_service_date'] ?? null;
                
                $stmt = $db->prepare("
                    UPDATE vehicles 
                    SET last_service_date = ?, next_service_date = ? 
                    WHERE id = ? AND garage_id = ?
                ");
                
                if ($stmt->execute([$last_service_date, $next_service_date, $vehicle_id, $_SESSION['garage_id']])) {
                    $success = 'Service dates updated successfully!';
                    header('Location: vehicles.php?action=view&id=' . $vehicle_id . '&success=' . urlencode($success));
                    exit();
                } else {
                    $error = 'Failed to update service dates.';
                }
                break;
        }
    }
}

// Get customers for dropdown
$stmt = $db->prepare("SELECT id, first_name, last_name, company FROM customers WHERE garage_id = ? ORDER BY first_name");
$stmt->execute([$_SESSION['garage_id']]);
$customers = $stmt->fetchAll();

if ($action === 'create' || $action === 'edit' || $action === 'view') {
    if (in_array($action, ['edit', 'view']) && $id > 0) {
        $stmt = $db->prepare("
            SELECT v.*, c.first_name, c.last_name, c.email, c.phone, c.company 
            FROM vehicles v 
            JOIN customers c ON v.customer_id = c.id 
            WHERE v.id = ? AND v.garage_id = ?
        ");
        $stmt->execute([$id, $_SESSION['garage_id']]);
        $vehicle = $stmt->fetch();
        
        if (!$vehicle) {
            header('Location: vehicles.php');
            exit();
        }
        
        // Get vehicle service history
        $stmt = $db->prepare("
            SELECT jc.*, 
                   (SELECT COUNT(*) FROM job_services WHERE job_card_id = jc.id) as service_count,
                   (SELECT COUNT(*) FROM job_parts WHERE job_card_id = jc.id) as part_count
            FROM job_cards jc 
            WHERE jc.vehicle_id = ? 
            ORDER BY jc.created_at DESC
        ");
        $stmt->execute([$id]);
        $service_history = $stmt->fetchAll();
        
        // Get upcoming service reminders
        $today = date('Y-m-d');
        $next_week = date('Y-m-d', strtotime('+7 days'));
        
        $stmt = $db->prepare("
            SELECT * 
            FROM job_cards 
            WHERE vehicle_id = ? AND status NOT IN ('completed', 'cancelled')
            ORDER BY created_at DESC
        ");
        $stmt->execute([$id]);
        $active_jobs = $stmt->fetchAll();
    }
} else {
    // List vehicles with filters
    $make_filter = $_GET['make'] ?? '';
    $fuel_filter = $_GET['fuel'] ?? '';
    $service_filter = $_GET['service'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $query = "
        SELECT v.*, c.first_name, c.last_name, c.phone 
        FROM vehicles v 
        JOIN customers c ON v.customer_id = c.id 
        WHERE v.garage_id = ?
    ";
    $params = [$_SESSION['garage_id']];
    
    if ($make_filter) {
        $query .= " AND v.make = ?";
        $params[] = $make_filter;
    }
    
    if ($fuel_filter) {
        $query .= " AND v.fuel_type = ?";
        $params[] = $fuel_filter;
    }
    
    if ($service_filter === 'due') {
        $query .= " AND v.next_service_date <= CURDATE()";
    } elseif ($service_filter === 'soon') {
        $query .= " AND v.next_service_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    }
    
    if ($search) {
        $query .= " AND (v.registration_number LIKE ? OR v.make LIKE ? OR v.model LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY v.registration_number";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll();
    
    // Get vehicle statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_vehicles,
            COUNT(DISTINCT make) as unique_makes,
            COUNT(DISTINCT fuel_type) as fuel_types,
            COUNT(CASE WHEN next_service_date <= CURDATE() THEN 1 END) as service_due
        FROM vehicles 
        WHERE garage_id = ?
    ");
    $stmt->execute([$_SESSION['garage_id']]);
    $vehicle_stats = $stmt->fetch();
    
    // Get unique makes for filter
    $stmt = $db->prepare("SELECT DISTINCT make FROM vehicles WHERE garage_id = ? ORDER BY make");
    $stmt->execute([$_SESSION['garage_id']]);
    $makes = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if ($action === 'list'): ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Vehicle Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Vehicle
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
                
                <!-- Vehicle Stats -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Vehicles
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $vehicle_stats['total_vehicles'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-car fa-2x text-primary"></i>
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
                                            Service Due
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $vehicle_stats['service_due'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-wrench fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Vehicle Makes
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $vehicle_stats['unique_makes'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-tags fa-2x text-info"></i>
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
                                            Fuel Types
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $vehicle_stats['fuel_types'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-gas-pump fa-2x text-success"></i>
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
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" placeholder="Search by reg, make, model..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <select class="form-control" name="make" onchange="this.form.submit()">
                                    <option value="">All Makes</option>
                                    <?php foreach ($makes as $make): ?>
                                    <option value="<?php echo htmlspecialchars($make); ?>" 
                                            <?php echo $make_filter === $make ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($make); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <select class="form-control" name="fuel" onchange="this.form.submit()">
                                    <option value="">All Fuel Types</option>
                                    <option value="petrol" <?php echo $fuel_filter === 'petrol' ? 'selected' : ''; ?>>Petrol</option>
                                    <option value="diesel" <?php echo $fuel_filter === 'diesel' ? 'selected' : ''; ?>>Diesel</option>
                                    <option value="electric" <?php echo $fuel_filter === 'electric' ? 'selected' : ''; ?>>Electric</option>
                                    <option value="hybrid" <?php echo $fuel_filter === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                    <option value="cng" <?php echo $fuel_filter === 'cng' ? 'selected' : ''; ?>>CNG</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <select class="form-control" name="service" onchange="this.form.submit()">
                                    <option value="">All Vehicles</option>
                                    <option value="due" <?php echo $service_filter === 'due' ? 'selected' : ''; ?>>Service Due</option>
                                    <option value="soon" <?php echo $service_filter === 'soon' ? 'selected' : ''; ?>>Service Soon</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    <a href="vehicles.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Vehicles Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Registration</th>
                                        <th>Vehicle</th>
                                        <th>Owner</th>
                                        <th>Year</th>
                                        <th>Fuel</th>
                                        <th>Last Service</th>
                                        <th>Next Service</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vehicles as $vehicle): 
                                        $today = date('Y-m-d');
                                        $service_status = '';
                                        $status_class = '';
                                        
                                        if ($vehicle['next_service_date']) {
                                            $days_until = floor((strtotime($vehicle['next_service_date']) - strtotime($today)) / (60 * 60 * 24));
                                            
                                            if ($days_until < 0) {
                                                $service_status = 'Overdue';
                                                $status_class = 'danger';
                                            } elseif ($days_until <= 7) {
                                                $service_status = 'Due Soon';
                                                $status_class = 'warning';
                                            } else {
                                                $service_status = 'Scheduled';
                                                $status_class = 'success';
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($vehicle['registration_number']); ?></strong>
                                            <?php if ($vehicle['vin']): ?>
                                            <br><small class="text-muted">VIN: <?php echo substr($vehicle['vin'], 0, 8) . '...'; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($vehicle['color'] ?: 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($vehicle['first_name'] . ' ' . $vehicle['last_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($vehicle['phone']); ?></small>
                                        </td>
                                        <td><?php echo $vehicle['year']; ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($vehicle['fuel_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $vehicle['last_service_date'] ? date('M d, Y', strtotime($vehicle['last_service_date'])) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <?php if ($vehicle['next_service_date']): ?>
                                            <?php echo date('M d, Y', strtotime($vehicle['next_service_date'])); ?><br>
                                            <small class="text-<?php echo $status_class; ?>">
                                                <?php 
                                                if ($days_until < 0) {
                                                    echo abs($days_until) . ' days ago';
                                                } elseif ($days_until == 0) {
                                                    echo 'Today';
                                                } else {
                                                    echo 'in ' . $days_until . ' days';
                                                }
                                                ?>
                                            </small>
                                            <?php else: ?>
                                            Not set
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($vehicle['next_service_date']): ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $service_status; ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">No Schedule</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?action=view&id=<?php echo $vehicle['id']; ?>" class="btn btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <a href="?action=edit&id=<?php echo $vehicle['id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <button type="button" class="btn btn-success" data-bs-toggle="modal" 
                                                        data-bs-target="#serviceModal<?php echo $vehicle['id']; ?>" title="Update Service">
                                                    <i class="fas fa-wrench"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal<?php echo $vehicle['id']; ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Service Update Modal -->
                                    <div class="modal fade" id="serviceModal<?php echo $vehicle['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Service Dates</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="update_service_dates">
                                                        <input type="hidden" name="id" value="<?php echo $vehicle['id']; ?>">
                                                        
                                                        <p>
                                                            <strong>Vehicle:</strong> <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?><br>
                                                            <strong>Registration:</strong> <?php echo htmlspecialchars($vehicle['registration_number']); ?>
                                                        </p>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label for="last_service_date" class="form-label">Last Service Date</label>
                                                                <input type="date" class="form-control" id="last_service_date" name="last_service_date" 
                                                                       value="<?php echo $vehicle['last_service_date'] ?? ''; ?>">
                                                            </div>
                                                            
                                                            <div class="col-md-6 mb-3">
                                                                <label for="next_service_date" class="form-label">Next Service Date</label>
                                                                <input type="date" class="form-control" id="next_service_date" name="next_service_date" 
                                                                       value="<?php echo $vehicle['next_service_date'] ?? ''; ?>">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            Service reminders will be sent based on the next service date.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Update Dates</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $vehicle['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Delete Vehicle</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $vehicle['id']; ?>">
                                                        
                                                        <div class="alert alert-danger">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            Are you sure you want to delete this vehicle?
                                                        </div>
                                                        
                                                        <p>
                                                            <strong>Vehicle:</strong> <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?><br>
                                                            <strong>Registration:</strong> <?php echo htmlspecialchars($vehicle['registration_number']); ?><br>
                                                            <strong>Owner:</strong> <?php echo htmlspecialchars($vehicle['first_name'] . ' ' . $vehicle['last_name']); ?>
                                                        </p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Delete Vehicle</button>
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
                
                <!-- Service Due Alert -->
                <?php 
                $stmt = $db->prepare("
                    SELECT v.*, c.first_name, c.last_name, c.phone, c.email 
                    FROM vehicles v 
                    JOIN customers c ON v.customer_id = c.id 
                    WHERE v.garage_id = ? AND v.next_service_date <= CURDATE() 
                    ORDER BY v.next_service_date ASC
                ");
                $stmt->execute([$_SESSION['garage_id']]);
                $service_due_vehicles = $stmt->fetchAll();
                
                if ($service_due_vehicles): ?>
                <div class="card border-left-danger mt-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>Service Due Vehicles
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Vehicle</th>
                                        <th>Owner</th>
                                        <th>Contact</th>
                                        <th>Last Service</th>
                                        <th>Next Service</th>
                                        <th>Overdue By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($service_due_vehicles as $vehicle): 
                                        $days_overdue = floor((strtotime(date('Y-m-d')) - strtotime($vehicle['next_service_date'])) / (60 * 60 * 24));
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($vehicle['registration_number']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($vehicle['first_name'] . ' ' . $vehicle['last_name']); ?></td>
                                        <td>
                                            <a href="tel:<?php echo htmlspecialchars($vehicle['phone']); ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-phone"></i>
                                            </a>
                                            <a href="mailto:<?php echo htmlspecialchars($vehicle['email']); ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        </td>
                                        <td><?php echo $vehicle['last_service_date'] ? date('M d, Y', strtotime($vehicle['last_service_date'])) : 'Never'; ?></td>
                                        <td class="text-danger">
                                            <strong><?php echo date('M d, Y', strtotime($vehicle['next_service_date'])); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger"><?php echo $days_overdue; ?> days</span>
                                        </td>
                                        <td>
                                            <a href="job_cards.php?action=create&vehicle_id=<?php echo $vehicle['id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-plus me-1"></i>Schedule Service
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php elseif ($action === 'view'): ?>
                <!-- View Vehicle Details -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Vehicle Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="vehicles.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to List
                            </a>
                            <a href="?action=edit&id=<?php echo $vehicle['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Edit
                            </a>
                            <a href="job_cards.php?action=create&vehicle_id=<?php echo $vehicle['id']; ?>" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>New Job Card
                            </a>
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
                    <!-- Vehicle Information -->
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Vehicle Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <i class="fas fa-car fa-4x text-primary mb-3"></i>
                                    <h4><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h4>
                                    <h5 class="text-muted"><?php echo htmlspecialchars($vehicle['registration_number']); ?></h5>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Registration Number:</strong><br>
                                    <?php echo htmlspecialchars($vehicle['registration_number']); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Make & Model:</strong><br>
                                    <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ')'); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Color:</strong><br>
                                    <?php echo htmlspecialchars($vehicle['color'] ?: 'Not specified'); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Fuel Type:</strong><br>
                                    <span class="badge bg-info"><?php echo ucfirst($vehicle['fuel_type']); ?></span>
                                </div>
                                
                                <?php if ($vehicle['vin']): ?>
                                <div class="mb-3">
                                    <strong>VIN:</strong><br>
                                    <?php echo htmlspecialchars($vehicle['vin']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($vehicle['engine_number']): ?>
                                <div class="mb-3">
                                    <strong>Engine Number:</strong><br>
                                    <?php echo htmlspecialchars($vehicle['engine_number']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Service Information -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Service Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Last Service Date:</strong><br>
                                    <?php if ($vehicle['last_service_date']): ?>
                                    <?php echo date('F j, Y', strtotime($vehicle['last_service_date'])); ?>
                                    <?php else: ?>
                                    <span class="text-muted">Never serviced</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Next Service Date:</strong><br>
                                    <?php if ($vehicle['next_service_date']): 
                                        $today = date('Y-m-d');
                                        $days_until = floor((strtotime($vehicle['next_service_date']) - strtotime($today)) / (60 * 60 * 24));
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?php echo date('F j, Y', strtotime($vehicle['next_service_date'])); ?></span>
                                        <span class="badge bg-<?php echo $days_until < 0 ? 'danger' : ($days_until <= 7 ? 'warning' : 'success'); ?>">
                                            <?php 
                                            if ($days_until < 0) {
                                                echo abs($days_until) . ' days overdue';
                                            } elseif ($days_until == 0) {
                                                echo 'Today';
                                            } else {
                                                echo 'in ' . $days_until . ' days';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">Not scheduled</span>
                                    <?php endif; ?>
                                </div>
                                
                                <hr>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="update_service_dates">
                                    <input type="hidden" name="id" value="<?php echo $vehicle['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="last_service_date" class="form-label">Update Last Service</label>
                                        <input type="date" class="form-control" id="last_service_date" name="last_service_date" 
                                               value="<?php echo $vehicle['last_service_date'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="next_service_date" class="form-label">Update Next Service</label>
                                        <input type="date" class="form-control" id="next_service_date" name="next_service_date" 
                                               value="<?php echo $vehicle['next_service_date'] ?? ''; ?>">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-2"></i>Update Service Dates
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Information & Service History -->
                    <div class="col-lg-8">
                        <!-- Customer Information -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Owner Information</h5>
                                <a href="customers.php?action=edit&id=<?php echo $vehicle['customer_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit me-1"></i>Edit Customer
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <strong>Name:</strong><br>
                                            <?php echo htmlspecialchars($vehicle['first_name'] . ' ' . $vehicle['last_name']); ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <strong>Phone:</strong><br>
                                            <?php echo htmlspecialchars($vehicle['phone']); ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <strong>Email:</strong><br>
                                            <?php echo htmlspecialchars($vehicle['email']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <?php if ($vehicle['company']): ?>
                                        <div class="mb-3">
                                            <strong>Company:</strong><br>
                                            <?php echo htmlspecialchars($vehicle['company']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <strong>Quick Actions:</strong><br>
                                            <div class="btn-group mt-2">
                                                <a href="tel:<?php echo htmlspecialchars($vehicle['phone']); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-phone me-1"></i>Call
                                                </a>
                                                <a href="mailto:<?php echo htmlspecialchars($vehicle['email']); ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-envelope me-1"></i>Email
                                                </a>
                                                <a href="invoices.php?action=create&customer_id=<?php echo $vehicle['customer_id']; ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-file-invoice me-1"></i>Invoice
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Jobs -->
                        <?php if ($active_jobs): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Active Job Cards</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Job #</th>
                                                <th>Created</th>
                                                <th>Status</th>
                                                <th>Problem</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($active_jobs as $job): ?>
                                            <tr>
                                                <td>
                                                    <a href="job_cards.php?action=view&id=<?php echo $job['id']; ?>">
                                                        <?php echo $job['job_number']; ?>
                                                    </a>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch($job['status']) {
                                                            case 'pending': echo 'warning'; break;
                                                            case 'in_progress': echo 'primary'; break;
                                                            case 'waiting_parts': echo 'info'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars(substr($job['problem_description'], 0, 50)); ?>...</small>
                                                </td>
                                                <td>
                                                    <a href="job_cards.php?action=view&id=<?php echo $job['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Service History -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Service History</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($service_history): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Job #</th>
                                                <th>Services</th>
                                                <th>Parts</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($service_history as $job): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                                <td>
                                                    <a href="job_cards.php?action=view&id=<?php echo $job['id']; ?>">
                                                        <?php echo $job['job_number']; ?>
                                                    </a>
                                                </td>
                                                <td><?php echo $job['service_count']; ?></td>
                                                <td><?php echo $job['part_count']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch($job['status']) {
                                                            case 'completed': echo 'success'; break;
                                                            case 'cancelled': echo 'danger'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="job_cards.php?action=view&id=<?php echo $job['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="invoices.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-file-invoice"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-history fa-3x mb-3"></i>
                                    <p>No service history found for this vehicle.</p>
                                    <a href="job_cards.php?action=create&vehicle_id=<?php echo $vehicle['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Create First Service
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($action === 'create' || $action === 'edit'): ?>
                <!-- Create/Edit Form -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $action === 'create' ? 'Add New' : 'Edit'; ?> Vehicle</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="vehicles.php" class="btn btn-secondary">
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
                            <input type="hidden" name="id" value="<?php echo $vehicle['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="customer_id" class="form-label">Customer *</label>
                                    <select class="form-control" id="customer_id" name="customer_id" required>
                                        <option value="">Select Customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>" 
                                                <?php echo ($vehicle['customer_id'] ?? '') == $customer['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ($customer['company'] ? ' (' . $customer['company'] . ')' : '')); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Can't find customer? <a href="customers.php?action=create">Add new customer</a></small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="registration_number" class="form-label">Registration Number *</label>
                                    <input type="text" class="form-control" id="registration_number" name="registration_number" 
                                           value="<?php echo $vehicle['registration_number'] ?? ''; ?>" required 
                                           <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                                    <small class="text-muted">Unique vehicle registration number</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="make" class="form-label">Make *</label>
                                    <input type="text" class="form-control" id="make" name="make" 
                                           value="<?php echo $vehicle['make'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="model" class="form-label">Model *</label>
                                    <input type="text" class="form-control" id="model" name="model" 
                                           value="<?php echo $vehicle['model'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="year" class="form-label">Year *</label>
                                    <select class="form-control" id="year" name="year" required>
                                        <option value="">Select Year</option>
                                        <?php for ($y = date('Y'); $y >= 1990; $y--): ?>
                                        <option value="<?php echo $y; ?>" 
                                                <?php echo ($vehicle['year'] ?? '') == $y ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="color" class="form-label">Color</label>
                                    <input type="text" class="form-control" id="color" name="color" 
                                           value="<?php echo $vehicle['color'] ?? ''; ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="fuel_type" class="form-label">Fuel Type *</label>
                                    <select class="form-control" id="fuel_type" name="fuel_type" required>
                                        <option value="">Select Fuel Type</option>
                                        <option value="petrol" <?php echo ($vehicle['fuel_type'] ?? '') === 'petrol' ? 'selected' : ''; ?>>Petrol</option>
                                        <option value="diesel" <?php echo ($vehicle['fuel_type'] ?? '') === 'diesel' ? 'selected' : ''; ?>>Diesel</option>
                                        <option value="electric" <?php echo ($vehicle['fuel_type'] ?? '') === 'electric' ? 'selected' : ''; ?>>Electric</option>
                                        <option value="hybrid" <?php echo ($vehicle['fuel_type'] ?? '') === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                        <option value="cng" <?php echo ($vehicle['fuel_type'] ?? '') === 'cng' ? 'selected' : ''; ?>>CNG</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="vin" class="form-label">VIN (Vehicle Identification Number)</label>
                                    <input type="text" class="form-control" id="vin" name="vin" 
                                           value="<?php echo $vehicle['vin'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="engine_number" class="form-label">Engine Number</label>
                                    <input type="text" class="form-control" id="engine_number" name="engine_number" 
                                           value="<?php echo $vehicle['engine_number'] ?? ''; ?>">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="last_service_date" class="form-label">Last Service Date</label>
                                    <input type="date" class="form-control" id="last_service_date" name="last_service_date" 
                                           value="<?php echo $vehicle['last_service_date'] ?? ''; ?>">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="next_service_date" class="form-label">Next Service Date</label>
                                    <input type="date" class="form-control" id="next_service_date" name="next_service_date" 
                                           value="<?php echo $vehicle['next_service_date'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $action === 'create' ? 'Add Vehicle' : 'Update Vehicle'; ?>
                                </button>
                                <a href="vehicles.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>