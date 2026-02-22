<?php
// garage_management_system/admin/roles.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('super_admin');
$page_title = 'Role Management';
$current_page = 'users';

$error = '';
$success = '';

// Get all roles with permission counts
$stmt = $db->query("
    SELECT r.*, COUNT(p.id) as permission_count 
    FROM roles r 
    LEFT JOIN permissions p ON r.id = p.role_id 
    WHERE r.id != 1 
    GROUP BY r.id 
    ORDER BY r.id
");
$roles = $stmt->fetchAll();

// Get all permissions grouped by module
$stmt = $db->query("
    SELECT module, 
           GROUP_CONCAT(DISTINCT role_id ORDER BY role_id) as roles_with_access
    FROM permissions 
    WHERE role_id != 1 
    GROUP BY module 
    ORDER BY module
");
$permissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Role Management</h1>
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
                
                <!-- Roles Overview -->
                <div class="row mb-4">
                    <?php foreach ($roles as $role): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?php echo ucfirst(str_replace('_', ' ', $role['name'])); ?>
                                </h5>
                                <p class="card-text">
                                    <small class="text-muted"><?php echo $role['description']; ?></small>
                                </p>
                                <div class="mb-2">
                                    <strong>Permissions:</strong> <?php echo $role['permission_count']; ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Default Access:</strong><br>
                                    <small class="text-muted">
                                        <?php 
                                        $stmt = $db->prepare("SELECT module FROM permissions WHERE role_id = ? AND can_view = 1");
                                        $stmt->execute([$role['id']]);
                                        $modules = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                        echo implode(', ', array_map(function($m) {
                                            return ucfirst(str_replace('_', ' ', $m));
                                        }, $modules));
                                        ?>
                                    </small>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                        data-bs-target="#editRoleModal<?php echo $role['id']; ?>">
                                    <i class="fas fa-edit me-1"></i>Edit Permissions
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edit Role Modal -->
                    <div class="modal fade" id="editRoleModal<?php echo $role['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Permissions: <?php echo ucfirst(str_replace('_', ' ', $role['name'])); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST" action="ajax/update_permissions.php">
                                    <div class="modal-body">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                        
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Module</th>
                                                        <th>View</th>
                                                        <th>Create</th>
                                                        <th>Edit</th>
                                                        <th>Delete</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $stmt = $db->query("SELECT DISTINCT module FROM permissions WHERE role_id != 1");
                                                    $modules = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                                    
                                                    foreach ($modules as $module): 
                                                        $stmt = $db->prepare("
                                                            SELECT can_view, can_create, can_edit, can_delete 
                                                            FROM permissions 
                                                            WHERE role_id = ? AND module = ?
                                                        ");
                                                        $stmt->execute([$role['id'], $module]);
                                                        $perm = $stmt->fetch();
                                                    ?>
                                                    <tr>
                                                        <td><?php echo ucfirst(str_replace('_', ' ', $module)); ?></td>
                                                        <td>
                                                            <input type="checkbox" name="permissions[<?php echo $module; ?>][view]" 
                                                                   value="1" <?php echo $perm && $perm['can_view'] ? 'checked' : ''; ?>>
                                                        </td>
                                                        <td>
                                                            <input type="checkbox" name="permissions[<?php echo $module; ?>][create]" 
                                                                   value="1" <?php echo $perm && $perm['can_create'] ? 'checked' : ''; ?>>
                                                        </td>
                                                        <td>
                                                            <input type="checkbox" name="permissions[<?php echo $module; ?>][edit]" 
                                                                   value="1" <?php echo $perm && $perm['can_edit'] ? 'checked' : ''; ?>>
                                                        </td>
                                                        <td>
                                                            <input type="checkbox" name="permissions[<?php echo $module; ?>][delete]" 
                                                                   value="1" <?php echo $perm && $perm['can_delete'] ? 'checked' : ''; ?>>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Permissions Matrix -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Permissions Matrix</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <?php foreach ($roles as $role): ?>
                                        <th><?php echo ucfirst(str_replace('_', ' ', $role['name'])); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $all_modules = [];
                                    foreach ($permissions as $perm) {
                                        $all_modules[] = $perm['module'];
                                    }
                                    $all_modules = array_unique($all_modules);
                                    sort($all_modules);
                                    
                                    foreach ($all_modules as $module): 
                                    ?>
                                    <tr>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $module)); ?></td>
                                        <?php foreach ($roles as $role): 
                                            $stmt = $db->prepare("SELECT can_view FROM permissions WHERE role_id = ? AND module = ?");
                                            $stmt->execute([$role['id'], $module]);
                                            $has_access = $stmt->fetch();
                                        ?>
                                        <td>
                                            <?php if ($has_access && $has_access['can_view']): ?>
                                            <span class="badge bg-success">Yes</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>