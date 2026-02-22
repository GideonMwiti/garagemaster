<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('support_staff');
$page_title = 'Gate Pass Management';
$current_page = 'gate_pass';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        try {
            $pass_number = 'GP-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("SELECT customer_id FROM vehicles WHERE id = ? AND garage_id = ?");
            $stmt->execute([$_POST['vehicle_id'], $_SESSION['garage_id']]);
            $vehicle = $stmt->fetch();
            
            if ($vehicle) {
                $stmt = $db->prepare("
                    INSERT INTO gate_pass (garage_id, pass_number, vehicle_id, customer_id, 
                                          purpose, entry_time, security_notes, created_by)
                    VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['garage_id'],
                    $pass_number,
                    $_POST['vehicle_id'],
                    $vehicle['customer_id'],
                    $_POST['purpose'],
                    $_POST['security_notes'] ?? null,
                    $_SESSION['user_id']
                ]);
                
                $message = 'Gate pass created: ' . $pass_number;
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
    
    if ($action === 'exit') {
        try {
            $stmt = $db->prepare("UPDATE gate_pass SET exit_time = NOW() WHERE id = ? AND garage_id = ?");
            $stmt->execute([$_POST['pass_id'], $_SESSION['garage_id']]);
            
            $message = 'Vehicle exit recorded';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Fetch gate passes
$stmt = $db->prepare("
    SELECT gp.*, v.registration_number, v.make, v.model,
           c.first_name, c.last_name, c.phone
    FROM gate_pass gp
    JOIN vehicles v ON gp.vehicle_id = v.id
    JOIN customers c ON gp.customer_id = c.id
    WHERE gp.garage_id = ?
    ORDER BY gp.entry_time DESC
    LIMIT 100
");
$stmt->execute([$_SESSION['garage_id']]);
$gate_passes = $stmt->fetchAll();

// Fetch vehicles
$stmt = $db->prepare("
    SELECT v.*, c.first_name, c.last_name
    FROM vehicles v
    JOIN customers c ON v.customer_id = c.id
    WHERE v.garage_id = ?
    ORDER BY v.registration_number
");
$stmt->execute([$_SESSION['garage_id']]);
$vehicles = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-car-side me-2"></i>Gate Pass Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPassModal">
                    <i class="fas fa-plus me-2"></i>Create Gate Pass
                </button>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="gatePassTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Pass #</th>
                                    <th>Vehicle</th>
                                    <th>Customer</th>
                                    <th>Purpose</th>
                                    <th>Entry Time</th>
                                    <th>Exit Time</th>
                                    <th>Duration</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gate_passes as $pass): ?>
                                <?php 
                                $duration = '';
                                if ($pass['exit_time']) {
                                    $entry = new DateTime($pass['entry_time']);
                                    $exit = new DateTime($pass['exit_time']);
                                    $diff = $entry->diff($exit);
                                    $duration = $diff->format('%h hrs %i mins');
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pass['pass_number']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($pass['registration_number']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($pass['make'] . ' ' . $pass['model']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($pass['first_name'] . ' ' . $pass['last_name']); ?><br>
                                        <small><?php echo htmlspecialchars($pass['phone']); ?></small>
                                    </td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($pass['purpose']); ?></span></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($pass['entry_time'])); ?></td>
                                    <td><?php echo $pass['exit_time'] ? date('M d, Y H:i', strtotime($pass['exit_time'])) : '-'; ?></td>
                                    <td><?php echo $duration ?: '<span class="badge bg-warning">Inside</span>'; ?></td>
                                    <td>
                                        <?php if (!$pass['exit_time']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="exit">
                                            <input type="hidden" name="pass_id" value="<?php echo $pass['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success">Record Exit</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
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

<!-- Create Gate Pass Modal -->
<div class="modal fade" id="createPassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Create Gate Pass</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Vehicle <span class="text-danger">*</span></label>
                        <select class="form-select" name="vehicle_id" required>
                            <option value="">Select Vehicle</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['id']; ?>">
                                <?php echo htmlspecialchars($vehicle['registration_number'] . ' - ' . $vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['first_name'] . ' ' . $vehicle['last_name'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purpose <span class="text-danger">*</span></label>
                        <select class="form-select" name="purpose" required>
                            <option value="service">Service</option>
                            <option value="delivery">Delivery</option>
                            <option value="pickup">Pickup</option>
                            <option value="inspection">Inspection</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Security Notes</label>
                        <textarea class="form-control" name="security_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Pass</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#gatePassTable').DataTable({
        order: [[4, 'desc']],
        pageLength: 25
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>