<?php
// garage_management_system/admin/services.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'Service Management';
$current_page = 'services';

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
                $service_code = $functions->sanitize($_POST['service_code']);
                $name = $functions->sanitize($_POST['name']);
                $description = $functions->sanitize($_POST['description'] ?? '');
                $category = $functions->sanitize($_POST['category']);
                $duration_hours = (float)$_POST['duration_hours'];
                $price = (float)$_POST['price'];
                
                // Check if service code already exists
                $stmt = $db->prepare("SELECT id FROM services WHERE service_code = ? AND garage_id = ?");
                $stmt->execute([$service_code, $_SESSION['garage_id']]);
                
                if ($stmt->fetch()) {
                    $error = 'Service code already exists. Please use a unique code.';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO services (garage_id, service_code, name, description, category, duration_hours, price) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([
                        $_SESSION['garage_id'], $service_code, $name, $description, 
                        $category, $duration_hours, $price
                    ])) {
                        $success = 'Service added successfully!';
                        header('Location: services.php?success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to add service.';
                    }
                }
                break;
                
            case 'update':
                $service_id = (int)$_POST['id'];
                $name = $functions->sanitize($_POST['name']);
                $description = $functions->sanitize($_POST['description'] ?? '');
                $category = $functions->sanitize($_POST['category']);
                $duration_hours = (float)$_POST['duration_hours'];
                $price = (float)$_POST['price'];
                
                $stmt = $db->prepare("
                    UPDATE services 
                    SET name = ?, description = ?, category = ?, duration_hours = ?, price = ? 
                    WHERE id = ? AND garage_id = ?
                ");
                
                if ($stmt->execute([
                    $name, $description, $category, $duration_hours, $price, 
                    $service_id, $_SESSION['garage_id']
                ])) {
                    $success = 'Service updated successfully!';
                    header('Location: services.php?success=' . urlencode($success));
                    exit();
                } else {
                    $error = 'Failed to update service.';
                }
                break;
                
            case 'delete':
                $service_id = (int)$_POST['id'];
                
                // Check if service is used in any job cards
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM job_services 
                    WHERE service_id = ?
                ");
                $stmt->execute([$service_id]);
                $usage = $stmt->fetch();
                
                if ($usage['count'] > 0) {
                    $error = 'Cannot delete service. It has been used in job cards.';
                } else {
                    $stmt = $db->prepare("DELETE FROM services WHERE id = ? AND garage_id = ?");
                    if ($stmt->execute([$service_id, $_SESSION['garage_id']])) {
                        $success = 'Service deleted successfully!';
                        header('Location: services.php?success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to delete service.';
                    }
                }
                break;
        }
    }
}

// Get categories
$stmt = $db->prepare("SELECT DISTINCT category FROM services WHERE garage_id = ? ORDER BY category");
$stmt->execute([$_SESSION['garage_id']]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($action === 'create' || $action === 'edit') {
    if ($action === 'edit' && $id > 0) {
        $stmt = $db->prepare("SELECT * FROM services WHERE id = ? AND garage_id = ?");
        $stmt->execute([$id, $_SESSION['garage_id']]);
        $service = $stmt->fetch();
        
        if (!$service) {
            header('Location: services.php');
            exit();
        }
    }
} else {
    // List services with filters
    $category_filter = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $query = "SELECT * FROM services WHERE garage_id = ?";
    $params = [$_SESSION['garage_id']];
    
    if ($category_filter) {
        $query .= " AND category = ?";
        $params[] = $category_filter;
    }
    
    if ($search) {
        $query .= " AND (service_code LIKE ? OR name LIKE ? OR description LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $services = $stmt->fetchAll();
    
    // Calculate service statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_services,
            AVG(price) as avg_price,
            MIN(price) as min_price,
            MAX(price) as max_price
        FROM services 
        WHERE garage_id = ?
    ");
    $stmt->execute([$_SESSION['garage_id']]);
    $service_stats = $stmt->fetch();
}
?>
<?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if ($action === 'list'): ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Service Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Service
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
                
                <!-- Service Stats -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Services
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $service_stats['total_services'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-tools fa-2x text-primary"></i>
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
                                            Average Price
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $functions->formatCurrency($service_stats['avg_price'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-success"></i>
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
                                            Price Range
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $functions->formatCurrency($service_stats['min_price'] ?? 0); ?> - 
                                            <?php echo $functions->formatCurrency($service_stats['max_price'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-info"></i>
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
                                            Categories
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo count($categories); ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            Different service types
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-tags fa-2x text-warning"></i>
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
                            <div class="col-md-5">
                                <input type="text" class="form-control" name="search" placeholder="Search by code, name..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="col-md-4">
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
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    <a href="services.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Services Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Service Code</th>
                                        <th>Service Name</th>
                                        <th>Category</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($service['service_code']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($service['name']); ?>
                                            <?php if ($service['description']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($service['description'], 0, 50)); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($service['category']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $service['duration_hours']; ?> hours</td>
                                        <td>
                                            <strong class="text-primary"><?php echo $functions->formatCurrency($service['price']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-info" data-bs-toggle="modal" 
                                                        data-bs-target="#viewModal<?php echo $service['id']; ?>" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <a href="?action=edit&id=<?php echo $service['id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal<?php echo $service['id']; ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $service['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Service Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <strong>Service Code:</strong><br>
                                                        <?php echo htmlspecialchars($service['service_code']); ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Name:</strong><br>
                                                        <?php echo htmlspecialchars($service['name']); ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Description:</strong><br>
                                                        <?php echo nl2br(htmlspecialchars($service['description'] ?: 'No description')); ?>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <strong>Category:</strong><br>
                                                            <span class="badge bg-primary">
                                                                <?php echo htmlspecialchars($service['category']); ?>
                                                            </span>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <strong>Duration:</strong><br>
                                                            <?php echo $service['duration_hours']; ?> hours
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Price:</strong><br>
                                                        <h4 class="text-primary"><?php echo $functions->formatCurrency($service['price']); ?></h4>
                                                    </div>
                                                    
                                                    <?php
                                                    // Get service usage statistics
                                                    $stmt = $db->prepare("
                                                        SELECT 
                                                            COUNT(*) as usage_count,
                                                            SUM(price) as total_revenue
                                                        FROM job_services js
                                                        JOIN job_cards jc ON js.job_card_id = jc.id
                                                        WHERE js.service_id = ? AND jc.garage_id = ?
                                                    ");
                                                    $stmt->execute([$service['id'], $_SESSION['garage_id']]);
                                                    $usage = $stmt->fetch();
                                                    ?>
                                                    
                                                    <?php if ($usage['usage_count'] > 0): ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-chart-bar me-2"></i>
                                                        <strong>Service Statistics:</strong><br>
                                                        Used in <?php echo $usage['usage_count']; ?> job cards<br>
                                                        Generated <?php echo $functions->formatCurrency($usage['total_revenue']); ?> in revenue
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $service['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Delete Service</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                                        
                                                        <div class="alert alert-danger">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            Are you sure you want to delete this service?
                                                        </div>
                                                        
                                                        <p>
                                                            <strong>Service:</strong> <?php echo htmlspecialchars($service['name']); ?><br>
                                                            <strong>Code:</strong> <?php echo htmlspecialchars($service['service_code']); ?><br>
                                                            <strong>Price:</strong> <?php echo $functions->formatCurrency($service['price']); ?>
                                                        </p>
                                                        
                                                        <?php if ($usage['usage_count'] > 0): ?>
                                                        <div class="alert alert-warning">
                                                            <i class="fas fa-exclamation-circle me-2"></i>
                                                            This service has been used in <?php echo $usage['usage_count']; ?> job cards and cannot be deleted.
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <?php if ($usage['usage_count'] == 0): ?>
                                                        <button type="submit" class="btn btn-danger">Delete Service</button>
                                                        <?php endif; ?>
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
                
                <?php elseif ($action === 'create' || $action === 'edit'): ?>
                <!-- Create/Edit Form -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $action === 'create' ? 'Add New' : 'Edit'; ?> Service</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="services.php" class="btn btn-secondary">
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
                            <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="service_code" class="form-label">Service Code *</label>
                                    <input type="text" class="form-control" id="service_code" name="service_code" 
                                           value="<?php echo $service['service_code'] ?? ''; ?>" required 
                                           <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                                    <small class="text-muted">Unique identifier for this service</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Service Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo $service['name'] ?? ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3"><?php echo $service['description'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-control" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="routine" <?php echo ($service['category'] ?? '') === 'routine' ? 'selected' : ''; ?>>Routine Maintenance</option>
                                        <option value="repair" <?php echo ($service['category'] ?? '') === 'repair' ? 'selected' : ''; ?>>Repair</option>
                                        <option value="diagnostic" <?php echo ($service['category'] ?? '') === 'diagnostic' ? 'selected' : ''; ?>>Diagnostic</option>
                                        <option value="bodywork" <?php echo ($service['category'] ?? '') === 'bodywork' ? 'selected' : ''; ?>>Body Work</option>
                                        <option value="electrical" <?php echo ($service['category'] ?? '') === 'electrical' ? 'selected' : ''; ?>>Electrical</option>
                                        <option value="tire" <?php echo ($service['category'] ?? '') === 'tire' ? 'selected' : ''; ?>>Tire Service</option>
                                        <option value="battery" <?php echo ($service['category'] ?? '') === 'battery' ? 'selected' : ''; ?>>Battery Service</option>
                                        <option value="ac" <?php echo ($service['category'] ?? '') === 'ac' ? 'selected' : ''; ?>>AC Service</option>
                                        <option value="other" <?php echo ($service['category'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="duration_hours" class="form-label">Duration (Hours) *</label>
                                    <input type="number" class="form-control" id="duration_hours" name="duration_hours" 
                                           step="0.5" min="0.5" value="<?php echo $service['duration_hours'] ?? 1.0; ?>" required>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="price" class="form-label">Price *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">KSH</span>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               step="0.01" min="0" value="<?php echo $service['price'] ?? 0; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Package Services (Optional) -->
                            <div class="mb-4">
                                <label class="form-label">Package Services (Optional)</label>
                                <small class="text-muted d-block mb-2">Select services that are included in this package (for package services only)</small>
                                
                                <div class="row" id="package-services-container">
                                    <!-- Dynamic package services will be added here -->
                                </div>
                                
                                <?php if ($action === 'edit' && $service['is_package'] ?? false): ?>
                                <?php
                                $stmt = $db->prepare("
                                    SELECT s.id, s.service_code, s.name, s.price 
                                    FROM package_services ps
                                    JOIN services s ON ps.included_service_id = s.id
                                    WHERE ps.package_service_id = ?
                                ");
                                $stmt->execute([$service['id']]);
                                $included_services = $stmt->fetchAll();
                                
                                foreach ($included_services as $inc_service): ?>
                                <div class="row mb-2">
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($inc_service['name'] . ' (' . $inc_service['service_code'] . ')'); ?>" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removePackageService(this)">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPackageService()">
                                    <i class="fas fa-plus me-1"></i>Add Service to Package
                                </button>
                            </div>
                            
                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" id="is_package" name="is_package" 
                                       value="1" <?php echo ($service['is_package'] ?? false) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_package">
                                    This is a service package (multiple services combined)
                                </label>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $action === 'create' ? 'Add Service' : 'Update Service'; ?>
                                </button>
                                <a href="services.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script>
                    let serviceCounter = 0;
                    
                    function addPackageService() {
                        serviceCounter++;
                        const container = document.getElementById('package-services-container');
                        
                        const row = document.createElement('div');
                        row.className = 'row mb-2';
                        row.innerHTML = `
                            <div class="col-md-8">
                                <select class="form-control package-service-select" name="package_services[]" required>
                                    <option value="">Select Service</option>
                                    <?php 
                                    $stmt = $db->prepare("SELECT id, service_code, name FROM services WHERE garage_id = ? AND id != ? ORDER BY name");
                                    $stmt->execute([$_SESSION['garage_id'], $service['id'] ?? 0]);
                                    $available_services = $stmt->fetchAll();
                                    
                                    foreach ($available_services as $av_service): ?>
                                    <option value="<?php echo $av_service['id']; ?>">
                                        <?php echo htmlspecialchars($av_service['name'] . ' (' . $av_service['service_code'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removePackageService(this)">
                                    <i class="fas fa-times"></i> Remove
                                </button>
                            </div>
                        `;
                        
                        container.appendChild(row);
                    }
                    
                    function removePackageService(button) {
                        const row = button.closest('.row');
                        row.remove();
                    }
                    
                    // Toggle package services visibility
                    document.getElementById('is_package').addEventListener('change', function() {
                        const packageContainer = document.getElementById('package-services-container').closest('.mb-4');
                        if (this.checked) {
                            packageContainer.style.display = 'block';
                        } else {
                            packageContainer.style.display = 'none';
                        }
                    });
                    
                    // Initialize visibility
                    document.getElementById('is_package').dispatchEvent(new Event('change'));
                </script>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>