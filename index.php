<?php
// garage_management_system/index.php
require_once 'config/config.php';
require_once 'includes/auth.php';

if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    header('Location: ' . BASE_URL . $user['role_name'] . '/dashboard.php');
    exit();
} else {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}
?>