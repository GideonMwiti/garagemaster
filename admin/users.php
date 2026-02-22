<?php
// garage_management_system/admin/users.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'User Management';
$current_page = 'users';

$is_super_admin = $functions->isSuperAdmin();
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
            case 'update_status':
                $user_id = (int)$_POST['user_id'];
                $status = $functions->sanitize($_POST['status']);
                
                // Super admin can update any user, regular admin only their garage
                if ($is_super_admin) {
                    $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND role_id != 1");
                    $result = $stmt->execute([$status, $user_id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND garage_id = ?");
                    $result = $stmt->execute([$status, $user_id, $_SESSION['garage_id']]);
                }
                
                if ($result) {
                    $success = 'User status updated successfully!';
                } else {
                    $error = 'Failed to update user status.';
                }
                break;
                
            case 'reset_password':
                $user_id = (int)$_POST['user_id'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($new_password !== $confirm_password) {
                    $error = 'Passwords do not match.';
                } elseif (strlen($new_password) < 8) {
                    $error = 'Password must be at least 8 characters long.';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Super admin can reset any user password, regular admin only their garage
                    if ($is_super_admin) {
                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ? AND role_id != 1");
                        $result = $stmt->execute([$hashed_password, $user_id]);
                    } else {
                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ? AND garage_id = ?");
                        $result = $stmt->execute([$hashed_password, $user_id, $_SESSION['garage_id']]);
                    }
                    
                    if ($result) {
                        $success = 'Password reset successfully!';
                    } else {
                        $error = 'Failed to reset password.';
                    }
                }
                break;
        }
    }
}

// Get users based on role
$users = [];

// Super admin can see all users except super admins
if ($is_super_admin) {
    if ($_GET['garage_id'] ?? 0) {
        $garage_id = (int)$_GET['garage_id'];
        $stmt = $db->prepare("
            SELECT u.*, r.name as role_name, g.name as garage_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            LEFT JOIN garages g ON u.garage_id = g.id 
            WHERE u.role_id != 1 AND u.garage_id = ?
            ORDER BY u.first_name
        ");
        $stmt->execute([$garage_id]);
    } else {
        $query = "SELECT u.*, r.name as role_name, g.name as garage_name 
                  FROM users u 
                  JOIN roles r ON u.role_id = r.id 
                  LEFT JOIN garages g ON u.garage_id = g.id 
                  WHERE u.role_id != 1 
                  ORDER BY g.name, u.first_name";
        $stmt = $db->query($query);
    }
} else {
    // Admin can only see users in their garage
    $query = "
        SELECT u.*, r.name as role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.garage_id = ? AND u.role_id != 1
        ORDER BY u.first_name
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['garage_id']]);
}

$users = $stmt->fetchAll();

// Get available roles for filter
$stmt = $db->query("SELECT id, name FROM roles WHERE id != 1 ORDER BY id");
$roles = $stmt->fetchAll();

// Get garages for super admin
$garages = [];
if ($is_super_admin) {
    $stmt = $db->query("SELECT id, name FROM garages WHERE status = 'active' ORDER BY name");
    $garages = $stmt->fetchAll();
}

include '../includes/header.php';
?>
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">User Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Create User
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
                            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                            <div class="col-md-4">
                                <select class="form-control" name="garage_id" onchange="this.form.submit()">
                                    <option value="">All Garages</option>
                                    <?php foreach ($garages as $garage): ?>
                                    <option value="<?php echo $garage['id']; ?>" 
                                            <?php echo ($_GET['garage_id'] ?? '') == $garage['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($garage['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <select class="form-control" name="role_id" onchange="this.form.submit()">
                                    <option value="">All Roles</option>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" 
                                            <?php echo ($_GET['role_id'] ?? '') == $role['id'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $role['name'])); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <select class="form-control" name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo ($_GET['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($_GET['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo ($_GET['status'] ?? '') == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <a href="users.php" class="btn btn-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                        <th>Garage</th>
                                        <?php endif; ?>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo ucfirst(str_replace('_', ' ', $user['role_name'])); ?>
                                            </span>
                                        </td>
                                        <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                        <td><?php echo $user['garage_name'] ?? 'System'; ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch($user['status']) {
                                                    case 'active': echo 'success'; break;
                                                    case 'inactive': echo 'secondary'; break;
                                                    case 'suspended': echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-info" data-bs-toggle="modal" 
                                                        data-bs-target="#viewModal<?php echo $user['id']; ?>" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($user['role_name'] !== 'admin' || $_SESSION['role'] === 'super_admin'): ?>
                                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" 
                                                        data-bs-target="#statusModal<?php echo $user['id']; ?>" title="Change Status">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" 
                                                        data-bs-target="#passwordModal<?php echo $user['id']; ?>" title="Reset Password">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">User Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <strong>Full Name:</strong><br>
                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Username:</strong><br>
                                                        <?php echo htmlspecialchars($user['username']); ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Email:</strong><br>
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Phone:</strong><br>
                                                        <?php echo $user['phone'] ?: 'Not set'; ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Role:</strong><br>
                                                        <?php echo ucfirst(str_replace('_', ' ', $user['role_name'])); ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Status:</strong><br>
                                                        <span class="badge bg-<?php 
                                                            switch($user['status']) {
                                                                case 'active': echo 'success'; break;
                                                                case 'inactive': echo 'secondary'; break;
                                                                case 'suspended': echo 'danger'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                            <?php echo ucfirst($user['status']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Last Login:</strong><br>
                                                        <?php echo $user['last_login'] ? date('F j, Y, g:i a', strtotime($user['last_login'])) : 'Never'; ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Created:</strong><br>
                                                        <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Status Modal -->
                                    <div class="modal fade" id="statusModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Change User Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label for="status" class="form-label">Select Status</label>
                                                            <select class="form-control" id="status" name="status" required>
                                                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                                <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="alert alert-warning">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            Changing status will affect user's access to the system.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Update Status</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Password Reset Modal -->
                                    <div class="modal fade" id="passwordModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reset Password</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="reset_password">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label for="new_password" class="form-label">New Password</label>
                                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="confirm_password" class="form-label">Confirm Password</label>
                                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            Password must be at least 8 characters long.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Reset Password</button>
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
            </main>

<?php include '../includes/footer.php'; ?>