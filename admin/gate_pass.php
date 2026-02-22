<?php
// garage_management_system/admin/gate_pass.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permissions
if (!in_array($_SESSION['role'], ['admin', 'super_admin', 'support_staff']) && !$functions->checkPermissions('gate_pass', 'view')) {
    header('Location: ../unauthorized.php');
    exit;
}

$page_title = 'Gate Passes';
$current_page = 'gate_pass';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        if ($_POST['action'] === 'create') {
            $vehicle_id = (int)$_POST['vehicle_id'];
            $purpose = $functions->sanitize($_POST['purpose']);
            $security_notes = $functions->sanitize($_POST['security_notes']);
            
            // Generate Pass Number
            $stmt = $db->prepare("SELECT COUNT(*) FROM gate_pass WHERE garage_id = ?");
            $stmt->execute([$_SESSION['garage_id']]);
            $count = $stmt->fetchColumn();
            $pass_number = 'GP-' . date('Ymd') . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            
            // Get customer from vehicle
            $stmt = $db->prepare("SELECT customer_id FROM vehicles WHERE id = ?");
            $stmt->execute([$vehicle_id]);
            $customer_id = $stmt->fetchColumn();
            
            if ($customer_id) {
                $stmt = $db->prepare("
                    INSERT INTO gate_pass (garage_id, pass_number, vehicle_id, customer_id, purpose, entry_time, security_notes, created_by)
                    VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
                ");
                if ($stmt->execute([$_SESSION['garage_id'], $pass_number, $vehicle_id, $customer_id, $purpose, $security_notes, $_SESSION['user_id']])) {
                    $success = 'Gate Pass created successfully!';
                    $action = 'list'; // Go back to list
                } else {
                    $error = 'Failed to create gate pass.';
                }
            } else {
                $error = 'Invalid vehicle or customer not found.';
            }
        } elseif ($_POST['action'] === 'checkout') {
            $pass_id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE gate_pass SET exit_time = NOW() WHERE id = ? AND garage_id = ?");
            if ($stmt->execute([$pass_id, $_SESSION['garage_id']])) {
                $success = 'Vehicle checked out successfully!';
            } else {
                $error = 'Failed to check out vehicle.';
            }
        }
    }
}

// Get data for list
$whereGarage = "g.garage_id = ?";
$params = [$_SESSION['garage_id']];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $whereGarage .= " AND (g.pass_number LIKE ? OR v.registration_number LIKE ?)";
    $params[] = $search;
    $params[] = $search;
}

$stmt = $db->prepare("
    SELECT g.*, v.registration_number, v.make, v.model,
           c.first_name, c.last_name
    FROM gate_pass g
    JOIN vehicles v ON g.vehicle_id = v.id
    JOIN customers c ON g.customer_id = c.id
    WHERE $whereGarage
    ORDER BY g.created_at DESC
");
$stmt->execute($params);
$gate_passes = $stmt->fetchAll();

// Get vehicles for create form
$stmt = $db->prepare("SELECT id, registration_number, make, model FROM vehicles WHERE garage_id = ? ORDER BY registration_number");
$stmt->execute([$_SESSION['garage_id']]);
$vehicles = $stmt->fetchAll();

?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Gate Pass Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <?php if ($action !== 'create'): ?>
            <a href="?action=create" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Gate Pass
            </a>
            <?php else: ?>
            <a href="gate_pass.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
            <?php endif; ?>
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

    <?php if ($action === 'create'): ?>
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="vehicle_id" class="form-label">Vehicle</label>
                        <select class="form-control" id="vehicle_id" name="vehicle_id" required>
                            <option value="">Select Vehicle</option>
                            <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo $v['id']; ?>"><?php echo $v['registration_number'] . ' - ' . $v['make'] . ' ' . $v['model']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="purpose" class="form-label">Purpose</label>
                        <select class="form-control" id="purpose" name="purpose" required>
                            <option value="service">Service</option>
                            <option value="delivery">Delivery</option>
                            <option value="pickup">Pickup</option>
                            <option value="inspection">Inspection</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="security_notes" class="form-label">Security Notes</label>
                    <textarea class="form-control" id="security_notes" name="security_notes" rows="3" placeholder="Any specific instructions or notes..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-2"></i>Generate Pass
                </button>
            </form>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Filter/Search -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="text" class="form-control" name="search" placeholder="Search pass number or registration..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Pass #</th>
                            <th>Vehicle</th>
                            <th>Customer</th>
                            <th>Purpose</th>
                            <th>Entry Time</th>
                            <th>Exit Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gate_passes as $pass): ?>
                        <tr>
                            <td><strong><?php echo $pass['pass_number']; ?></strong></td>
                            <td>
                                <?php echo $pass['registration_number']; ?><br>
                                <small class="text-muted"><?php echo $pass['make'] . ' ' . $pass['model']; ?></small>
                            </td>
                            <td><?php echo $pass['first_name'] . ' ' . $pass['last_name']; ?></td>
                            <td><?php echo ucfirst($pass['purpose']); ?></td>
                            <td><?php echo date('M d, H:i', strtotime($pass['entry_time'])); ?></td>
                            <td>
                                <?php echo $pass['exit_time'] ? date('M d, H:i', strtotime($pass['exit_time'])) : '-'; ?>
                            </td>
                            <td>
                                <?php if ($pass['exit_time']): ?>
                                    <span class="badge bg-success">Checked Out</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button onclick="printGatePass(<?php echo $pass['id']; ?>)" class="btn btn-info" title="Print">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <?php if (!$pass['exit_time']): ?>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Confirm vehicle checkout?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="checkout">
                                        <input type="hidden" name="id" value="<?php echo $pass['id']; ?>">
                                        <button type="submit" class="btn btn-success" title="Check Out">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<script>
function printGatePass(id) {
    // Determine the base URL
    var baseUrl = "<?php echo BASE_URL; ?>admin/print_gate_pass.php?id=" + id;
    
    // Open a new window for printing
    var printWindow = window.open(baseUrl, '_blank', 'width=800,height=600');
    if (printWindow) {
        printWindow.focus();
        // The print_gate_pass.php file should handle window.print() on load
    } else {
        alert('Please allow popups to print.');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
