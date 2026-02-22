<?php
// garage_management_system/register.php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$functions = new Functions();
$auth->requireLogin();

// Only Super Admin and Admin can access this page
if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header('Location: ' . BASE_URL . 'unauthorized.php');
    exit();
}

$page_title = 'Create User';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $first_name = $functions->sanitize($_POST['first_name'] ?? '');
        $last_name = $functions->sanitize($_POST['last_name'] ?? '');
        $email = $functions->sanitize($_POST['email'] ?? '');
        $username = $functions->sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone = $functions->sanitize($_POST['phone'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0);
        
        // Validate inputs
        if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
            $error = 'All required fields must be filled.';
        } elseif (!$functions->validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                // Determine garage_id based on role
                $garage_id = null;
                if ($_SESSION['role'] === 'super_admin') {
                    // Super Admin can create Admins for any garage
                    $garage_id = ($role_id == 2) ? ((int)($_POST['garage_id'] ?? 0)) : null;
                } else {
                    // Admin can only create users for their own garage (except other Admins)
                    if ($role_id == 2) {
                        $error = 'You cannot create Admin accounts.';
                    } else {
                        $garage_id = $_SESSION['garage_id'];
                    }
                }
                
                if (!$error) {
                    // Create user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("
                        INSERT INTO users (garage_id, role_id, username, email, password, first_name, last_name, phone, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    
                    if ($stmt->execute([$garage_id, $role_id, $username, $email, $hashed_password, $first_name, $last_name, $phone])) {
                        $success = 'User created successfully!';
                        $_POST = []; // Clear form
                    } else {
                        $error = 'Failed to create user. Please try again.';
                    }
                }
            }
        }
    }
}

// Get available roles for creation
$available_roles = [];
if ($_SESSION['role'] === 'super_admin') {
    $stmt = $db->query("SELECT id, name FROM roles WHERE id != 1 ORDER BY id");
} else {
    $stmt = $db->query("SELECT id, name FROM roles WHERE id NOT IN (1, 2) ORDER BY id");
}
$roles = $stmt->fetchAll();

// Get garages for Super Admin
$garages = [];
if ($_SESSION['role'] === 'super_admin') {
    $stmt = $db->query("SELECT id, name FROM garages WHERE status = 'active' ORDER BY name");
    $garages = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/header.php'; ?>
</head>
<body>
    <?php include 'includes/auth.php'; ?>
    <?php $auth->requireLogin(); ?>
    
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php 
            $current_page = 'users';
            include 'includes/sidebar.php'; 
            ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Create New User</h1>
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
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo $_POST['first_name'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo $_POST['last_name'] ?? ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo $_POST['phone'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo $_POST['username'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="role_id" class="form-label">Role *</label>
                                    <select class="form-control" id="role_id" name="role_id" required>
                                        <option value="">Select Role</option>
                                        <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" 
                                                <?php echo ($_POST['role_id'] ?? '') == $role['id'] ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $role['name'])); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                            <div class="row mb-3" id="garage-field" style="display: none;">
                                <div class="col-md-6">
                                    <label for="garage_id" class="form-label">Assign to Garage</label>
                                    <select class="form-control" id="garage_id" name="garage_id">
                                        <option value="">Select Garage</option>
                                        <?php foreach ($garages as $garage): ?>
                                        <option value="<?php echo $garage['id']; ?>">
                                            <?php echo htmlspecialchars($garage['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Only required for Admin role</small>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-user-plus me-2"></i>Create User
                                </button>
                                <a href="<?php echo BASE_URL . $_SESSION['role'] . '/users.php'; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Show garage field only when Admin role is selected
        $(document).ready(function() {
            $('#role_id').change(function() {
                if ($(this).val() == '2') { // Admin role
                    $('#garage-field').show();
                } else {
                    $('#garage-field').hide();
                }
            });
            
            // Trigger change on page load
            $('#role_id').trigger('change');
        });
    </script>
</body>
</html>