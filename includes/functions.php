<?php
// garage_management_system/includes/functions.php
require_once __DIR__ . '/../config/config.php';

class Functions {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Check if current user is super admin
     */
    public function isSuperAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
    }
    
    /**
     * Get the garage ID to filter by (null for super admin = all garages)
     */
    public function getFilterGarageId() {
        return $this->isSuperAdmin() ? null : $_SESSION['garage_id'];
    }
    
    public function sanitize($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitize($value);
            }
            return $data;
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public function validatePhone($phone) {
        return preg_match('/^[\+]?[1-9][0-9\-\(\)\.\s]{8,20}$/', $phone);
    }
    
    public function generateRandomString($length = 10) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    public function generateJobNumber($garageId) {
        $year = date('Y');
        $month = date('m');
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM job_cards 
            WHERE garage_id = ? AND YEAR(created_at) = ? AND MONTH(created_at) = ?
        ");
        $stmt->execute([$garageId, $year, $month]);
        $result = $stmt->fetch();
        
        $sequence = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
        return "JOB-$year$month-$sequence";
    }
    
    public function generateInvoiceNumber($garageId) {
        $year = date('Y');
        $month = date('m');
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM invoices 
            WHERE garage_id = ? AND YEAR(created_at) = ? AND MONTH(created_at) = ?
        ");
        $stmt->execute([$garageId, $year, $month]);
        $result = $stmt->fetch();
        
        $sequence = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
        return "INV-$year$month-$sequence";
    }
    
    public function formatCurrency($amount) {
        return 'KSH ' . number_format($amount, 2);
    }
    
    public function getGarageSettings($garageId) {
        $stmt = $this->db->prepare("
            SELECT `key`, value, type 
            FROM settings 
            WHERE garage_id = ? OR garage_id IS NULL
            ORDER BY garage_id DESC
        ");
        $stmt->execute([$garageId]);
        $settings = $stmt->fetchAll();
        
        $result = [];
        foreach ($settings as $setting) {
            switch ($setting['type']) {
                case 'json':
                    $result[$setting['key']] = json_decode($setting['value'], true);
                    break;
                case 'number':
                    $result[$setting['key']] = (float)$setting['value'];
                    break;
                case 'boolean':
                    $result[$setting['key']] = (bool)$setting['value'];
                    break;
                default:
                    $result[$setting['key']] = $setting['value'];
            }
        }
        
        return $result;
    }
    
    public function sendServiceReminder($vehicleId) {
        $stmt = $this->db->prepare("
            SELECT v.*, c.email, c.first_name, c.last_name, g.name as garage_name, g.email as garage_email, g.phone as garage_phone, g.address as garage_address
            FROM vehicles v
            JOIN customers c ON v.customer_id = c.id
            JOIN garages g ON v.garage_id = g.id
            WHERE v.id = ?
        ");
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch();
        
        if (!$vehicle || !$vehicle['next_service_date']) {
            return false;
        }
        
        $reminderDays = $this->getGarageSettings($vehicle['garage_id'])['service_reminder_days'] ?? 7;
        $reminderDate = date('Y-m-d', strtotime("-$reminderDays days", strtotime($vehicle['next_service_date'])));
        
        if (date('Y-m-d') >= $reminderDate) {
            // Get email template
            $stmt = $this->db->prepare("SELECT * FROM email_templates WHERE name = 'service_reminder'");
            $stmt->execute();
            $template = $stmt->fetch();
            
            if ($template) {
                $content = $template['content'];
                
                // Replace variables
                $replacements = [
                    '[LOGO_URL]' => BASE_URL . BRAND_LOGO,
                    '[CUSTOMER_NAME]' => $vehicle['first_name'] . ' ' . $vehicle['last_name'],
                    '[VEHICLE_DETAILS]' => $vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ')',
                    '[SERVICE_DATE]' => date('F j, Y', strtotime($vehicle['next_service_date'])),
                    '[VEHICLE_MAKE]' => $vehicle['make'],
                    '[VEHICLE_MODEL]' => $vehicle['model'],
                    '[REGISTRATION]' => $vehicle['registration_number'],
                    '[SERVICE_TYPE]' => 'Regular Maintenance',
                    '[GARAGE_PHONE]' => $vehicle['garage_phone'],
                    '[GARAGE_EMAIL]' => $vehicle['garage_email'],
                    '[GARAGE_NAME]' => $vehicle['garage_name'],
                    '[GARAGE_ADDRESS]' => $vehicle['garage_address']
                ];
                
                $content = str_replace(array_keys($replacements), array_values($replacements), $content);
                
                // Send email
                $mailer = new Mailer($vehicle['email'], $template['subject'], $content, true);
                return $mailer->send();
            }
        }
        
        return false;
    }
    
    public function checkLowStock($garageId) {
        if ($garageId === null) {
            // Super admin - check all garages
            $stmt = $this->db->prepare("
                SELECT i.*, g.name as garage_name 
                FROM inventory i
                LEFT JOIN garages g ON i.garage_id = g.id
                WHERE i.quantity <= i.reorder_level
            ");
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("
                SELECT * FROM inventory 
                WHERE garage_id = ? AND quantity <= reorder_level
            ");
            $stmt->execute([$garageId]);
        }
        return $stmt->fetchAll();
    }
    
    public function getDashboardStats($garageId, $startDate = null, $endDate = null) {
        $stats = [];
        
        // Default to current month
        if (!$startDate) $startDate = date('Y-m-01');
        if (!$endDate) $endDate = date('Y-m-t');
        
        // Build WHERE clause based on garage_id
        $whereGarage = ($garageId === null) ? "1=1" : "garage_id = ?";
        $params = ($garageId === null) ? [] : [$garageId];
        
        // Total revenue
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total_revenue
            FROM invoices 
            WHERE $whereGarage AND status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge($params, [$startDate, $endDate]));
        $stats['total_revenue'] = $stmt->fetch()['total_revenue'];
        
        // Pending invoices
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as amount
            FROM invoices 
            WHERE $whereGarage AND status IN ('sent', 'draft') AND (due_date IS NULL OR due_date >= CURDATE())
        ");
        $stmt->execute($params);
        $pending = $stmt->fetch();
        $stats['pending_invoices'] = $pending['count'];
        $stats['pending_amount'] = $pending['amount'];
        
        // Overdue invoices
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as amount
            FROM invoices 
            WHERE $whereGarage AND status = 'sent' AND due_date < CURDATE()
        ");
        $stmt->execute($params);
        $overdue = $stmt->fetch();
        $stats['overdue_invoices'] = $overdue['count'];
        $stats['overdue_amount'] = $overdue['amount'];
        
        // Active job cards
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM job_cards 
            WHERE $whereGarage AND status IN ('pending', 'in_progress', 'waiting_parts')
        ");
        $stmt->execute($params);
        $stats['active_jobs'] = $stmt->fetch()['count'];
        
        // Low stock items
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM inventory 
            WHERE $whereGarage AND quantity <= reorder_level
        ");
        $stmt->execute($params);
        $stats['low_stock'] = $stmt->fetch()['count'];
        
        // Recent customers
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM customers 
            WHERE $whereGarage AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute(array_merge($params, [$startDate, $endDate]));
        $stats['new_customers'] = $stmt->fetch()['count'];
        
        return $stats;
    }
    
    public function checkPermissions($module, $action = 'view') {
        global $auth;
        return $auth->hasPermission($module, $action);
    }

    public function getRevenueData($garageId) {
        $data = [];
        $labels = [];
        $revenues = [];
        
        // Last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-$i months"));
            $monthEnd = date('Y-m-t', strtotime("-$i months"));
            $monthName = date('M', strtotime("-$i months"));
            
            $whereGarage = ($garageId === null) ? "1=1" : "garage_id = ?";
            $params = ($garageId === null) ? [] : [$garageId];
            
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(total_amount), 0) as total
                FROM invoices 
                WHERE $whereGarage AND status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute(array_merge($params, [$monthStart, $monthEnd]));
            
            $labels[] = $monthName;
            $revenues[] = $stmt->fetch()['total'];
        }
        
        return ['labels' => $labels, 'data' => $revenues];
    }

    public function getServiceTypeData($garageId) {
        $whereGarage = ($garageId === null) ? "1=1" : "s.garage_id = ?";
        $params = ($garageId === null) ? [] : [$garageId];
        
        $stmt = $this->db->prepare("
            SELECT s.category, COUNT(*) as count
            FROM job_services js
            JOIN services s ON js.service_id = s.id
            WHERE $whereGarage
            GROUP BY s.category
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        $labels = [];
        $data = [];
        
        foreach ($results as $row) {
            $labels[] = ucfirst($row['category']);
            $data[] = $row['count'];
        }
        
        return ['labels' => $labels, 'data' => $data];
    }
}

$functions = new Functions();
?>