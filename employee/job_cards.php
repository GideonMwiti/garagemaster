<?php
// garage_management_system/employee/job_cards.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('employee');
$page_title = 'My Job Cards';
$current_page = 'job_cards';

$message = '';
$message_type = '';

// Handle job updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid security token';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_status') {
            try {
                // Verify job is assigned to this technician
                $stmt = $db->prepare("SELECT id FROM job_cards WHERE id = ? AND assigned_to = ?");
                $stmt->execute([$_POST['job_id'], $_SESSION['user_id']]);
                
                if ($stmt->fetch()) {
                    $update_fields = ["status = ?"];
                    $params = [$_POST['status']];
                    
                    if (!empty($_POST['diagnosis'])) {
                        $update_fields[] = "diagnosis = ?";
                        $params[] = $_POST['diagnosis'];
                    }
                    
                    if (!empty($_POST['actual_hours'])) {
                        $update_fields[] = "actual_hours = ?";
                        $params[] = $_POST['actual_hours'];
                    }
                    
                    $params[] = $_POST['job_id'];
                    
                    $stmt = $db->prepare("
                        UPDATE job_cards 
                        SET " . implode(', ', $update_fields) . "
                        WHERE id = ?
                    ");
                    $stmt->execute($params);
                    
                    $message = 'Job card updated successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Unauthorized access';
                    $message_type = 'danger';
                }
            } catch (Exception $e) {
                $message = 'Error updating job: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Fetch jobs assigned to this technician
$stmt = $db->prepare("
    SELECT jc.*, v.registration_number, v.make, v.model, v.vin,
           c.first_name, c.last_name, c.phone, c.email
    FROM job_cards jc
    JOIN vehicles v ON jc.vehicle_id = v.id
    JOIN customers c ON jc.customer_id = c.id
    WHERE jc.assigned_to = ?
    ORDER BY 
        CASE jc.status
            WHEN 'in_progress' THEN 1
            WHEN 'waiting_parts' THEN 2
            WHEN 'pending' THEN 3
            ELSE 4
        END,
        jc.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$job_cards = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-clipboard-list me-2"></i>My Job Cards</h1>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Job Cards List -->
            <?php if (count($job_cards) > 0): ?>
            <div class="row">
                <?php foreach ($job_cards as $job): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-<?php 
                            echo $job['status'] === 'in_progress' ? 'primary' : 
                                ($job['status'] === 'waiting_parts' ? 'warning' : 
                                ($job['status'] === 'completed' ? 'success' : 'secondary')); 
                        ?> text-white">
                            <h6 class="mb-0"><?php echo htmlspecialchars($job['job_number']); ?></h6>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($job['registration_number']); ?></h5>
                            <p class="card-text">
                                <strong><?php echo htmlspecialchars($job['make'] . ' ' . $job['model']); ?></strong><br>
                                <small class="text-muted">VIN: <?php echo htmlspecialchars($job['vin'] ?? 'N/A'); ?></small>
                            </p>
                            <hr>
                            <p class="mb-2">
                                <i class="fas fa-user me-2"></i>
                                <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-phone me-2"></i>
                                <?php echo htmlspecialchars($job['phone']); ?>
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-tools me-2"></i>
                                <?php echo htmlspecialchars($job['problem_description']); ?>
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-clock me-2"></i>
                                Est: <?php echo $job['estimated_hours'] ? number_format($job['estimated_hours'], 1) . ' hrs' : 'N/A'; ?>
                            </p>
                            <span class="badge bg-<?php 
                                echo $job['status'] === 'in_progress' ? 'primary' : 
                                    ($job['status'] === 'waiting_parts' ? 'warning' : 
                                    ($job['status'] === 'completed' ? 'success' : 'secondary')); 
                            ?>">
                                <?php echo ucwords(str_replace('_', ' ', $job['status'])); ?>
                            </span>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $job['id']; ?>">
                                <i class="fas fa-edit me-1"></i>Update Status
                            </button>
                        </div>
                    </div>
                    
                    <!-- Update Modal -->
                    <div class="modal fade" id="updateModal<?php echo $job['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Job: <?php echo htmlspecialchars($job['job_number']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" name="status" required>
                                                <option value="pending" <?php echo $job['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="in_progress" <?php echo $job['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="waiting_parts" <?php echo $job['status'] === 'waiting_parts' ? 'selected' : ''; ?>>Waiting for Parts</option>
                                                <option value="completed" <?php echo $job['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Diagnosis/Work Done</label>
                                            <textarea class="form-control" name="diagnosis" rows="3"><?php echo htmlspecialchars($job['diagnosis'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Actual Hours</label>
                                            <input type="number" step="0.1" class="form-control" name="actual_hours" value="<?php echo $job['actual_hours'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Update Job</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h4>No Jobs Assigned</h4>
                    <p class="text-muted">You don't have any job cards assigned yet.</p>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
