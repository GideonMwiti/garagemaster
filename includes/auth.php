<?php
// garage_management_system/includes/auth.php
require_once __DIR__ . '/../config/config.php';

class Auth {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    public function login($username, $password) {
        // Check login attempts
        if ($this->isLockedOut($username)) {
            return ['success' => false, 'message' => 'Account locked. Try again later.'];
        }
        
        // Record login attempt
        $this->recordLoginAttempt($username, $_SERVER['REMOTE_ADDR'], 0);
        
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, g.name as garage_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            LEFT JOIN garages g ON u.garage_id = g.id 
            WHERE u.username = ? AND u.status = 'active'
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Reset failed attempts
            $this->resetFailedAttempts($username);
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Record successful attempt
            $this->recordLoginAttempt($username, $_SERVER['REMOTE_ADDR'], 1);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['garage_id'] = $user['garage_id'];
            $_SESSION['garage_name'] = $user['garage_name'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['last_activity'] = time();
            
            return ['success' => true, 'role' => $user['role_name']];
        } else {
            // Increment failed attempts
            $this->incrementFailedAttempts($username);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
    }
    
    public function logout() {
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        // Check session timeout
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . BASE_URL . 'login.php');
            exit();
        }
    }
    
    public function requireRole($requiredRole) {
        $this->requireLogin();
        
        if ($_SESSION['role'] !== $requiredRole) {
            header('Location: ' . BASE_URL . 'unauthorized.php');
            exit();
        }
    }
    
    public function hasPermission($module, $action) {
        $stmt = $this->db->prepare("
            SELECT p.can_view, p.can_create, p.can_edit, p.can_delete 
            FROM permissions p 
            WHERE p.role_id = ? AND p.module = ?
        ");
        $stmt->execute([$_SESSION['role_id'], $module]);
        $permission = $stmt->fetch();
        
        if (!$permission) {
            return false;
        }
        
        switch ($action) {
            case 'view': return (bool)$permission['can_view'];
            case 'create': return (bool)$permission['can_create'];
            case 'edit': return (bool)$permission['can_edit'];
            case 'delete': return (bool)$permission['can_delete'];
            default: return false;
        }
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, g.name as garage_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            LEFT JOIN garages g ON u.garage_id = g.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    private function isLockedOut($username) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND success = 0
        ");
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= MAX_LOGIN_ATTEMPTS;
    }
    
    private function recordLoginAttempt($username, $ip, $success) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (username, ip_address, success) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $ip, $success]);
    }
    
    private function resetFailedAttempts($username) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_attempts = 0, locked_until = NULL 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
    }
    
    private function incrementFailedAttempts($username) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_attempts = failed_attempts + 1 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        
        // Check if account should be locked
        $stmt = $this->db->prepare("
            SELECT failed_attempts 
            FROM users 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $user['failed_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) 
                WHERE username = ?
            ");
            $stmt->execute([$username]);
        }
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET last_login = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
}

$auth = new Auth();
?>