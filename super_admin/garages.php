<?php
// garage_management_system/super_admin/garages.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('super_admin');
$page_title = 'Garage Management';
$current_page = 'garages';

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
                $name = $functions->sanitize($_POST['name']);
                $address = $functions->sanitize($_POST['address']);
                $phone = $functions->sanitize($_POST['phone']);
                $email = $functions->sanitize($_POST['email']);
                $tax_id = $functions->sanitize($_POST['tax_id'] ?? '');
                $admin_first_name = $functions->sanitize($_POST['admin_first_name']);
                $admin_last_name = $functions->sanitize($_POST['admin_last_name']);
                $admin_username = $functions->sanitize($_POST['admin_username']);
                $admin_password = $_POST['admin_password'];
                $admin_confirm_password = $_POST['admin_confirm_password'];
                
                if (!$functions->validateEmail($email)) {
                    $error = 'Invalid email address.';
                } elseif (strlen($admin_password) < 8) {
                    $error = 'Admin password must be at least 8 characters long.';
                } elseif ($admin_password !== $admin_confirm_password) {
                    $error = 'Admin passwords do not match.';
                } else {
                    // Check if username already exists
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$admin_username]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Username already exists. Please choose a different one.';
                    } else {
                        $db->getConnection()->beginTransaction();
                        
                        try {
                            // Create garage
                            $stmt = $db->prepare("
                                INSERT INTO garages (name, address, phone, email, tax_id, status) 
                                VALUES (?, ?, ?, ?, ?, 'active')
                            ");
                            
                            if (!$stmt->execute([$name, $address, $phone, $email, $tax_id])) {
                                throw new Exception('Failed to create garage.');
                            }
                            
                            $garage_id = $db->lastInsertId();
                            
                            // Create admin user for this garage
                            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare("
                                INSERT INTO users (garage_id, role_id, username, email, password, first_name, last_name, status) 
                                VALUES (?, 2, ?, ?, ?, ?, ?, 'active')
                            ");
                            
                            if (!$stmt->execute([$garage_id, $admin_username, $email, $hashed_password, $admin_first_name, $admin_last_name])) {
                                throw new Exception('Failed to create garage admin user.');
                            }
                            
                            $db->getConnection()->commit();
                            
                            $success = "Garage '{$name}' created successfully with admin user '{$admin_username}'!";
                            header('Location: garages.php?success=' . urlencode($success));
                            exit();
                        } catch (Exception $e) {
                            $db->getConnection()->rollBack();
                            $error = $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'update':
                $garage_id = (int)$_POST['id'];
                $name = $functions->sanitize($_POST['name']);
                $address = $functions->sanitize($_POST['address']);
                $phone = $functions->sanitize($_POST['phone']);
                $email = $functions->sanitize($_POST['email']);
                $tax_id = $functions->sanitize($_POST['tax_id'] ?? '');
                $status = $functions->sanitize($_POST['status']);
                
                if (!$functions->validateEmail($email)) {
                    $error = 'Invalid email address.';
                } else {
                    $stmt = $db->prepare("
                        UPDATE garages 
                        SET name = ?, address = ?, phone = ?, email = ?, tax_id = ?, status = ?
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([$name, $address, $phone, $email, $tax_id, $status, $garage_id])) {
                        $success = 'Garage updated successfully!';
                        header('Location: garages.php?success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to update garage.';
                    }
                }
                break;
                
            case 'delete':
                $garage_id = (int)$_POST['id'];
                
                // Check if garage has users
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE garage_id = ?");
                $stmt->execute([$garage_id]);
                $user_count = $stmt->fetch()['count'];
                
                if ($user_count > 0) {
                    $error = "Cannot delete garage. It has {$user_count} user(s). Please reassign or delete users first.";
                } else {
                    $stmt = $db->prepare("DELETE FROM garages WHERE id = ?");
                    if ($stmt->execute([$garage_id])) {
                        $success = 'Garage deleted successfully!';
                        header('Location: garages.php?success=' . urlencode($success));
                        exit();
                    } else {
                        $error = 'Failed to delete garage.';
                    }
                }
                break;
        }
    }
}

if ($action === 'view' || $action === 'edit') {
    $stmt = $db->prepare("SELECT * FROM garages WHERE id = ?");
    $stmt->execute([$id]);
    $garage = $stmt->fetch();
    
    if (!$garage) {
        header('Location: garages.php');
        exit();
    }
    
    // Get garage statistics
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE garage_id = ? AND status = 'active') as active_users,
            (SELECT COUNT(*) FROM vehicles WHERE garage_id = ?) as total_vehicles,
            (SELECT COUNT(*) FROM job_cards WHERE garage_id = ?) as total_jobs,
            (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE garage_id = ? AND status = 'paid') as total_revenue
    ");
    $stmt->execute([$id, $id, $id, $id]);
    $garage_stats = $stmt->fetch();
}

// List garages
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    $query = "SELECT g.*, 
                     (SELECT COUNT(*) FROM users WHERE garage_id = g.id AND role_id = 2) as admin_count,
                     (SELECT COUNT(*) FROM users WHERE garage_id = g.id) as total_users,
                     (SELECT COUNT(*) FROM vehicles WHERE garage_id = g.id) as vehicle_count
              FROM garages g WHERE 1=1";
    $params = [];
    
    if ($search) {
        $query .= " AND (g.name LIKE ? OR g.email LIKE ? OR g.phone LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if ($status_filter) {
        $query .= " AND g.status = ?";
        $params[] = $status_filter;
    }
    
    $query .= " ORDER BY g.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $garages = $stmt->fetchAll();
}

include '../includes/header.php';
?>
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if ($action === 'list'): ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-building me-2"></i>Garage Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add New Garage
                        </a>
                    </div>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success || isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success ?: $_GET['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="search" placeholder="Search by name, email, phone..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <select class="form-control" name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <a href="garages.php" class="btn btn-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Garages Table -->
                <div class="card shadow">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Admin</th>
                                        <th>Users</th>
                                        <th>Vehicles</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($garages as $g): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($g['name']); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($g['email']); ?><br>
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($g['phone']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo $g['admin_count']; ?></td>
                                        <td><?php echo $g['total_users']; ?></td>
                                        <td><?php echo $g['vehicle_count']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $g['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($g['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($g['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?action=view&id=<?php echo $g['id']; ?>" class="btn btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?action=edit&id=<?php echo $g['id']; ?>" class="btn btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal<?php echo $g['id']; ?>" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $g['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">Delete Garage</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                                                        
                                                        <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($g['name']); ?></strong>?</p>
                                                        <div class="alert alert-warning">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            This action cannot be undone. All related data will be affected.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Delete Garage</button>
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $action === 'create' ? 'Add New' : 'Edit'; ?> Garage</h1>
                    <a href="garages.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to List
                    </a>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="card shadow">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                            <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $garage['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Garage Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo $garage['name'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo $garage['email'] ?? ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo $garage['phone'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="tax_id" class="form-label">Tax ID</label>
                                    <input type="text" class="form-control" id="tax_id" name="tax_id" 
                                           value="<?php echo $garage['tax_id'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address *</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $garage['address'] ?? ''; ?></textarea>
                            </div>
                            
                            <?php if ($action === 'edit'): ?>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="active" <?php echo ($garage['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($garage['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Admin User Details (Only for Create) -->
                            <?php if ($action === 'create'): ?>
                            <div class="card mt-4 mb-4 bg-light">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Garage Admin Account</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted"><small>This admin user will be the owner/manager of the garage</small></p>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="admin_first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="admin_first_name" name="admin_first_name" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="admin_last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="admin_last_name" name="admin_last_name" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="admin_username" class="form-label">Username *</label>
                                            <input type="text" class="form-control" id="admin_username" name="admin_username" required>
                                            <small class="text-muted">Used for login</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="admin_password" class="form-label">Password *</label>
                                            <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                            <small class="text-muted">Minimum 8 characters</small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="admin_confirm_password" class="form-label">Confirm Password *</label>
                                            <input type="password" class="form-control" id="admin_confirm_password" name="admin_confirm_password" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $action === 'create' ? 'Create Garage' : 'Update Garage'; ?>
                                </button>
                                <a href="garages.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php elseif ($action === 'view'): ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo htmlspecialchars($garage['name']); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="?action=edit&id=<?php echo $garage['id']; ?>" class="btn btn-warning me-2">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                        <a href="garages.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                    </div>
                </div>
                
                <!-- Garage Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-left-primary shadow">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Users</div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $garage_stats['active_users']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-left-info shadow">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Vehicles</div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $garage_stats['total_vehicles']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-left-warning shadow">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Jobs</div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $garage_stats['total_jobs']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-left-success shadow">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Revenue</div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($garage_stats['total_revenue']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Garage Details -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Garage Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($garage['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($garage['phone']); ?></p>
                                <p><strong>Tax ID:</strong> <?php echo htmlspecialchars($garage['tax_id'] ?: 'Not provided'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($garage['address'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $garage['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($garage['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Created:</strong> <?php echo date('F d, Y', strtotime($garage['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>

<?php include '../includes/footer.php'; ?>
