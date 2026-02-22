<?php
// garage_management_system/logout.php
require_once 'config/config.php';
require_once 'includes/auth.php';

$auth->logout();
header('Location: ' . BASE_URL . 'login.php');
exit();
?>