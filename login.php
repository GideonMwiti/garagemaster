<?php
// garage_management_system/login.php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$page_title = 'Login';
$functions = new Functions();

if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    $redirect_role = $user['role_name'];
    header('Location: ' . BASE_URL . $redirect_role . '/dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $functions->sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            $user = $auth->getCurrentUser();
            $redirect_role = $user['role_name'];
            header('Location: ' . BASE_URL . $redirect_role . '/dashboard.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        body, input, button, select, textarea, h1, h2, h3, h4, h5, h6, p, div, span, label {
            font-family: 'Montserrat', 'Poppins', Arial, sans-serif;
        }
        :root {
            --brand-primary: #00A8CE;
            --brand-secondary: #FFA629;
            --brand-accent: #7A2048;
            --brand-dark: #0E2033;
            --brand-muted: #6B7280;
        }
        body {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand-primary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            background-color: var(--brand-primary);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header img {
            max-height: 60px;
            margin-bottom: 15px;
        }
        .login-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 168, 206, 0.25);
        }
        .btn-login {
            background-color: var(--brand-primary);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-login:hover {
            background-color: #0097b9;
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 8px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="<?php echo BASE_URL . BRAND_LOGO; ?>" alt="<?php echo BRAND_NAME; ?>">
            <h4 class="mb-0"><?php echo BRAND_NAME; ?></h4>
            <p class="mb-0 opacity-75"><?php echo BRAND_SLOGAN; ?></p>
        </div>
        
        <div class="login-body">
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
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo $_POST['username'] ?? ''; ?>" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
                
                <div class="text-center">
                    <small class="text-muted">© <?php echo date('Y'); ?> <?php echo BRAND_NAME; ?>. All rights reserved.</small>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>