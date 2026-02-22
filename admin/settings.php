<?php
// garage_management_system/admin/settings.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'System Settings';
$current_page = 'settings';

$error = '';
$success = '';

// Get current settings
$settings = $functions->getGarageSettings($_SESSION['garage_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Update general settings
        if (isset($_POST['update_general'])) {
            $business_name = $functions->sanitize($_POST['business_name'] ?? '');
            $business_email = $functions->sanitize($_POST['business_email'] ?? '');
            $business_phone = $functions->sanitize($_POST['business_phone'] ?? '');
            $business_address = $functions->sanitize($_POST['business_address'] ?? '');
            $tax_id = $functions->sanitize($_POST['tax_id'] ?? '');
            
            // Update garage information
            $stmt = $db->prepare("
                UPDATE garages 
                SET name = ?, email = ?, phone = ?, address = ?, tax_id = ? 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$business_name, $business_email, $business_phone, $business_address, $tax_id, $_SESSION['garage_id']])) {
                // Update session garage name
                $_SESSION['garage_name'] = $business_name;
                
                // Update settings
                $business_hours = json_encode([
                    'open' => $_POST['business_hours_open'] ?? '08:00',
                    'close' => $_POST['business_hours_close'] ?? '18:00'
                ]);
                
                $this->updateSetting('business_hours', $business_hours, 'json', $_SESSION['garage_id']);
                $this->updateSetting('timezone', $_POST['timezone'] ?? 'UTC', 'string', $_SESSION['garage_id']);
                $this->updateSetting('date_format', $_POST['date_format'] ?? 'Y-m-d', 'string', $_SESSION['garage_id']);
                
                $success = 'General settings updated successfully!';
            } else {
                $error = 'Failed to update general settings.';
            }
        }
        
        // Update financial settings
        elseif (isset($_POST['update_financial'])) {
            $this->updateSetting('currency', $_POST['currency'] ?? 'USD', 'string', $_SESSION['garage_id']);
            $this->updateSetting('currency_symbol', $_POST['currency_symbol'] ?? '$', 'string', $_SESSION['garage_id']);
            $this->updateSetting('default_tax_rate', $_POST['default_tax_rate'] ?? 10, 'number', $_SESSION['garage_id']);
            $this->updateSetting('invoice_prefix', $_POST['invoice_prefix'] ?? 'INV', 'string', $_SESSION['garage_id']);
            $this->updateSetting('quotation_prefix', $_POST['quotation_prefix'] ?? 'QUO', 'string', $_SESSION['garage_id']);
            $this->updateSetting('payment_terms', $_POST['payment_terms'] ?? '30', 'number', $_SESSION['garage_id']);
            
            $success = 'Financial settings updated successfully!';
        }
        
        // Update notification settings
        elseif (isset($_POST['update_notifications'])) {
            $this->updateSetting('service_reminder_days', $_POST['service_reminder_days'] ?? 7, 'number', $_SESSION['garage_id']);
            $this->updateSetting('send_service_reminders', isset($_POST['send_service_reminders']) ? '1' : '0', 'boolean', $_SESSION['garage_id']);
            $this->updateSetting('send_invoice_reminders', isset($_POST['send_invoice_reminders']) ? '1' : '0', 'boolean', $_SESSION['garage_id']);
            $this->updateSetting('low_stock_notifications', isset($_POST['low_stock_notifications']) ? '1' : '0', 'boolean', $_SESSION['garage_id']);
            $this->updateSetting('notification_email', $_POST['notification_email'] ?? '', 'string', $_SESSION['garage_id']);
            
            $success = 'Notification settings updated successfully!';
        }
        
        // Update email settings
        elseif (isset($_POST['update_email'])) {
            $this->updateSetting('smtp_host', $_POST['smtp_host'] ?? '', 'string', $_SESSION['garage_id']);
            $this->updateSetting('smtp_port', $_POST['smtp_port'] ?? '587', 'number', $_SESSION['garage_id']);
            $this->updateSetting('smtp_username', $_POST['smtp_username'] ?? '', 'string', $_SESSION['garage_id']);
            $this->updateSetting('smtp_password', $_POST['smtp_password'] ?? '', 'string', $_SESSION['garage_id']);
            $this->updateSetting('smtp_encryption', $_POST['smtp_encryption'] ?? 'tls', 'string', $_SESSION['garage_id']);
            $this->updateSetting('from_email', $_POST['from_email'] ?? '', 'string', $_SESSION['garage_id']);
            $this->updateSetting('from_name', $_POST['from_name'] ?? '', 'string', $_SESSION['garage_id']);
            
            $success = 'Email settings updated successfully!';
        }
    }
}

// Helper function to update settings
function updateSetting($key, $value, $type, $garage_id) {
    global $db;
    
    // Check if setting exists
    $stmt = $db->prepare("SELECT id FROM settings WHERE `key` = ? AND garage_id = ?");
    $stmt->execute([$key, $garage_id]);
    
    if ($stmt->fetch()) {
        // Update existing
        $stmt = $db->prepare("UPDATE settings SET value = ?, type = ?, updated_at = NOW() WHERE `key` = ? AND garage_id = ?");
        $stmt->execute([$value, $type, $key, $garage_id]);
    } else {
        // Insert new
        $stmt = $db->prepare("INSERT INTO settings (garage_id, `key`, value, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$garage_id, $key, $value, $type]);
    }
}

// Get garage details
$stmt = $db->prepare("SELECT * FROM garages WHERE id = ?");
$stmt->execute([$_SESSION['garage_id']]);
$garage = $stmt->fetch();
?>
<?php include '../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">System Settings</h1>
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
                
                <!-- Settings Tabs -->
                <ul class="nav nav-tabs" id="settingsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                            <i class="fas fa-building me-2"></i>General
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="financial-tab" data-bs-toggle="tab" data-bs-target="#financial" type="button">
                            <i class="fas fa-dollar-sign me-2"></i>Financial
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button">
                            <i class="fas fa-envelope me-2"></i>Email
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button">
                            <i class="fas fa-database me-2"></i>Backup
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="settingsTabContent">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="update_general" value="1">
                                    
                                    <h5 class="mb-4">Business Information</h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="business_name" class="form-label">Business Name *</label>
                                            <input type="text" class="form-control" id="business_name" name="business_name" 
                                                   value="<?php echo htmlspecialchars($garage['name'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="business_email" class="form-label">Business Email *</label>
                                            <input type="email" class="form-control" id="business_email" name="business_email" 
                                                   value="<?php echo htmlspecialchars($garage['email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="business_phone" class="form-label">Business Phone *</label>
                                            <input type="tel" class="form-control" id="business_phone" name="business_phone" 
                                                   value="<?php echo htmlspecialchars($garage['phone'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="tax_id" class="form-label">Tax ID / VAT Number</label>
                                            <input type="text" class="form-control" id="tax_id" name="tax_id" 
                                                   value="<?php echo htmlspecialchars($garage['tax_id'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="business_address" class="form-label">Business Address</label>
                                        <textarea class="form-control" id="business_address" name="business_address" 
                                                  rows="3"><?php echo htmlspecialchars($garage['address'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <h5 class="mb-4">Business Hours</h5>
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label for="business_hours_open" class="form-label">Opening Time</label>
                                            <input type="time" class="form-control" id="business_hours_open" name="business_hours_open" 
                                                   value="<?php echo isset($settings['business_hours']) ? $settings['business_hours']['open'] : '08:00'; ?>">
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="business_hours_close" class="form-label">Closing Time</label>
                                            <input type="time" class="form-control" id="business_hours_close" name="business_hours_close" 
                                                   value="<?php echo isset($settings['business_hours']) ? $settings['business_hours']['close'] : '18:00'; ?>">
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="timezone" class="form-label">Timezone</label>
                                            <select class="form-control" id="timezone" name="timezone">
                                                <option value="UTC" <?php echo ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                                <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                                <option value="America/Chicago" <?php echo ($settings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                                <option value="America/Denver" <?php echo ($settings['timezone'] ?? '') === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                                <option value="America/Los_Angeles" <?php echo ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="date_format" class="form-label">Date Format</label>
                                            <select class="form-control" id="date_format" name="date_format">
                                                <option value="Y-m-d" <?php echo ($settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                                <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                                <option value="d/m/Y" <?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                                <option value="F j, Y" <?php echo ($settings['date_format'] ?? '') === 'F j, Y' ? 'selected' : ''; ?>>Month Day, Year</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save General Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Settings -->
                    <div class="tab-pane fade" id="financial" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="update_financial" value="1">
                                    
                                    <h5 class="mb-4">Currency & Pricing</h5>
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label for="currency" class="form-label">Currency</label>
                                            <select class="form-control" id="currency" name="currency">
                                                <option value="USD" <?php echo ($settings['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                                <option value="EUR" <?php echo ($settings['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                                <option value="GBP" <?php echo ($settings['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>British Pound (GBP)</option>
                                                <option value="CAD" <?php echo ($settings['currency'] ?? '') === 'CAD' ? 'selected' : ''; ?>>Canadian Dollar (CAD)</option>
                                                <option value="AUD" <?php echo ($settings['currency'] ?? '') === 'AUD' ? 'selected' : ''; ?>>Australian Dollar (AUD)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                            <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" 
                                                   value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? '$'); ?>" maxlength="3">
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="default_tax_rate" class="form-label">Default Tax Rate (%)</label>
                                            <input type="number" class="form-control" id="default_tax_rate" name="default_tax_rate" 
                                                   step="0.01" min="0" max="100" value="<?php echo $settings['default_tax_rate'] ?? 10; ?>">
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="payment_terms" class="form-label">Payment Terms (Days)</label>
                                            <input type="number" class="form-control" id="payment_terms" name="payment_terms" 
                                                   min="0" value="<?php echo $settings['payment_terms'] ?? 30; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label for="invoice_prefix" class="form-label">Invoice Prefix</label>
                                            <input type="text" class="form-control" id="invoice_prefix" name="invoice_prefix" 
                                                   value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'INV'); ?>">
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="quotation_prefix" class="form-label">Quotation Prefix</label>
                                            <input type="text" class="form-control" id="quotation_prefix" name="quotation_prefix" 
                                                   value="<?php echo htmlspecialchars($settings['quotation_prefix'] ?? 'QUO'); ?>">
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="job_prefix" class="form-label">Job Card Prefix</label>
                                            <input type="text" class="form-control" id="job_prefix" name="job_prefix" 
                                                   value="<?php echo htmlspecialchars($settings['job_prefix'] ?? 'JOB'); ?>">
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="payment_prefix" class="form-label">Payment Prefix</label>
                                            <input type="text" class="form-control" id="payment_prefix" name="payment_prefix" 
                                                   value="<?php echo htmlspecialchars($settings['payment_prefix'] ?? 'PAY'); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Financial Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notification Settings -->
                    <div class="tab-pane fade" id="notifications" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="update_notifications" value="1">
                                    
                                    <h5 class="mb-4">Email Notifications</h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="notification_email" class="form-label">Notification Email</label>
                                            <input type="email" class="form-control" id="notification_email" name="notification_email" 
                                                   value="<?php echo htmlspecialchars($settings['notification_email'] ?? ''); ?>">
                                            <small class="text-muted">Where to send system notifications</small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="service_reminder_days" class="form-label">Service Reminder (Days Before)</label>
                                            <input type="number" class="form-control" id="service_reminder_days" name="service_reminder_days" 
                                                   min="1" max="30" value="<?php echo $settings['service_reminder_days'] ?? 7; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="send_service_reminders" 
                                                   name="send_service_reminders" value="1" 
                                                   <?php echo ($settings['send_service_reminders'] ?? '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="send_service_reminders">
                                                Send Service Reminders to Customers
                                            </label>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="send_invoice_reminders" 
                                                   name="send_invoice_reminders" value="1" 
                                                   <?php echo ($settings['send_invoice_reminders'] ?? '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="send_invoice_reminders">
                                                Send Invoice Due Date Reminders
                                            </label>
                                        </div>
                                        
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="low_stock_notifications" 
                                                   name="low_stock_notifications" value="1" 
                                                   <?php echo ($settings['low_stock_notifications'] ?? '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="low_stock_notifications">
                                                Send Low Stock Notifications
                                            </label>
                                        </div>
                                        
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="new_user_notifications" 
                                                   name="new_user_notifications" value="1" 
                                                   <?php echo ($settings['new_user_notifications'] ?? '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="new_user_notifications">
                                                Send New User Welcome Emails
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Notification Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email Settings -->
                    <div class="tab-pane fade" id="email" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="update_email" value="1">
                                    
                                    <h5 class="mb-4">SMTP Settings</h5>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Configure SMTP settings to enable email notifications from the system.
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_host" class="form-label">SMTP Host</label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_port" class="form-label">SMTP Port</label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_username" class="form-label">SMTP Username</label>
                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_password" class="form-label">SMTP Password</label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_encryption" class="form-label">Encryption</label>
                                            <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                <option value="" <?php echo empty($settings['smtp_encryption'] ?? '') ? 'selected' : ''; ?>>None</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_auth" class="form-label">Authentication Required</label>
                                            <select class="form-control" id="smtp_auth" name="smtp_auth">
                                                <option value="1" <?php echo ($settings['smtp_auth'] ?? '1') ? 'selected' : ''; ?>>Yes</option>
                                                <option value="0" <?php echo !($settings['smtp_auth'] ?? '1') ? 'selected' : ''; ?>>No</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <h5 class="mb-4">Sender Information</h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="from_email" class="form-label">From Email Address</label>
                                            <input type="email" class="form-control" id="from_email" name="from_email" 
                                                   value="<?php echo htmlspecialchars($settings['from_email'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="from_name" class="form-label">From Name</label>
                                            <input type="text" class="form-control" id="from_name" name="from_name" 
                                                   value="<?php echo htmlspecialchars($settings['from_name'] ?? $garage['name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="fas fa-save me-2"></i>Save Email Settings
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="testEmailSettings()">
                                            <i class="fas fa-paper-plane me-2"></i>Test Email Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Backup Settings -->
                    <div class="tab-pane fade" id="backup" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="mb-4">Database Backup</h5>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Regular backups are essential for data protection. We recommend daily backups.
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <i class="fas fa-download fa-3x text-primary mb-3"></i>
                                                <h5>Manual Backup</h5>
                                                <p class="text-muted">Download a complete backup of your database</p>
                                                <button type="button" class="btn btn-primary" onclick="createBackup()">
                                                    <i class="fas fa-download me-2"></i>Download Backup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <i class="fas fa-upload fa-3x text-success mb-3"></i>
                                                <h5>Restore Backup</h5>
                                                <p class="text-muted">Restore your database from a backup file</p>
                                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#restoreModal">
                                                    <i class="fas fa-upload me-2"></i>Restore Backup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Auto Backup Settings</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label for="backup_frequency" class="form-label">Backup Frequency</label>
                                                    <select class="form-control" id="backup_frequency" name="backup_frequency">
                                                        <option value="daily" <?php echo ($settings['backup_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                                        <option value="weekly" <?php echo ($settings['backup_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                        <option value="monthly" <?php echo ($settings['backup_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                                        <option value="disabled" <?php echo ($settings['backup_frequency'] ?? '') === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="backup_time" class="form-label">Backup Time (Daily)</label>
                                                    <input type="time" class="form-control" id="backup_time" name="backup_time" 
                                                           value="<?php echo $settings['backup_time'] ?? '02:00'; ?>">
                                                </div>
                                                
                                                <div class="col-md-4 mb-3">
                                                    <label for="backup_retention" class="form-label">Retention Period (Days)</label>
                                                    <input type="number" class="form-control" id="backup_retention" name="backup_retention" 
                                                           min="1" value="<?php echo $settings['backup_retention'] ?? 30; ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="backup_email" 
                                                       name="backup_email" value="1" 
                                                       <?php echo ($settings['backup_email'] ?? '0') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="backup_email">
                                                    Email backup notifications
                                                </label>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i>Save Backup Settings
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Restore Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Restore Database Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="ajax/restore_backup.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will overwrite your current database. Make sure you have a backup.
                        </div>
                        
                        <div class="mb-3">
                            <label for="backup_file" class="form-label">Select Backup File</label>
                            <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".sql,.gz,.zip" required>
                            <small class="text-muted">Accepted formats: .sql, .gz, .zip</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('This will overwrite ALL data. Are you sure?')">
                            Restore Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        function testEmailSettings() {
            $.ajax({
                url: 'ajax/test_email.php',
                type: 'POST',
                data: {
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                },
                success: function(response) {
                    alert('Test email sent successfully!');
                },
                error: function() {
                    alert('Failed to send test email. Check your settings.');
                }
            });
        }
        
        function createBackup() {
            if (confirm('Create and download database backup?')) {
                window.location.href = 'ajax/create_backup.php';
            }
        }
    </script>
</body>
</html>