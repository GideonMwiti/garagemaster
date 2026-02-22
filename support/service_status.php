<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('support_staff');
$page_title = 'Service Status';
$current_page = 'service_status';

// Fetch all active job cards
$stmt = $db->prepare("
    SELECT jc.*, v.registration_number, v.make, v.model,
           c.first_name, c.last_name, c.phone, c.email,
           u.first_name as tech_first, u.last_name as tech_last
    FROM job_cards jc
    JOIN vehicles v ON jc.vehicle_id = v.id
    JOIN customers c ON jc.customer_id = c.id
    LEFT JOIN users u ON jc.assigned_to = u.id
    WHERE jc.garage_id = ? AND jc.status NOT IN ('delivered', 'cancelled')
    ORDER BY 
        CASE jc.status
            WHEN 'in_progress' THEN 1
            WHEN 'waiting_parts' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'pending' THEN 4
            ELSE 5
        END,
        jc.created_at ASC
");
$stmt->execute([$_SESSION['garage_id']]);
$job_cards = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-tasks me-2"></i>Service Status Board</h1>
                <button class="btn btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
            
            <!-- Status Columns -->
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card border-secondary">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Pending</h6>
                        </div>
                        <div class="card-body p-2" style="min-height: 400px;">
                            <?php foreach ($job_cards as $job): ?>
                                <?php if ($job['status'] === 'pending'): ?>
                                <div class="card mb-2 shadow-sm">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($job['job_number']); ?></h6>
                                        <p class="card-text small mb-1">
                                            <strong><?php echo htmlspecialchars($job['registration_number']); ?></strong><br>
                                            <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?><br>
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($job['phone']); ?>
                                        </p>
                                        <small class="text-muted">Created: <?php echo date('M d, H:i', strtotime($job['created_at'])); ?></small>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-cog me-2"></i>In Progress</h6>
                        </div>
                        <div class="card-body p-2" style="min-height: 400px;">
                            <?php foreach ($job_cards as $job): ?>
                                <?php if ($job['status'] === 'in_progress'): ?>
                                <div class="card mb-2 shadow-sm border-primary">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($job['job_number']); ?></h6>
                                        <p class="card-text small mb-1">
                                            <strong><?php echo htmlspecialchars($job['registration_number']); ?></strong><br>
                                            <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?><br>
                                            <i class="fas fa-user-cog"></i> <?php echo $job['tech_first'] ? htmlspecialchars($job['tech_first'] . ' ' . $job['tech_last']) : 'Unassigned'; ?>
                                        </p>
                                        <small class="text-muted">Est: <?php echo $job['estimated_hours'] ? number_format($job['estimated_hours'], 1) . 'h' : 'N/A'; ?></small>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-pause me-2"></i>Waiting Parts</h6>
                        </div>
                        <div class="card-body p-2" style="min-height: 400px;">
                            <?php foreach ($job_cards as $job): ?>
                                <?php if ($job['status'] === 'waiting_parts'): ?>
                                <div class="card mb-2 shadow-sm border-warning">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($job['job_number']); ?></h6>
                                        <p class="card-text small mb-1">
                                            <strong><?php echo htmlspecialchars($job['registration_number']); ?></strong><br>
                                            <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?><br>
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($job['phone']); ?>
                                        </p>
                                        <?php if ($job['diagnosis']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($job['diagnosis'], 0, 50)); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-check me-2"></i>Completed</h6>
                        </div>
                        <div class="card-body p-2" style="min-height: 400px;">
                            <?php foreach ($job_cards as $job): ?>
                                <?php if ($job['status'] === 'completed'): ?>
                                <div class="card mb-2 shadow-sm border-success">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($job['job_number']); ?></h6>
                                        <p class="card-text small mb-1">
                                            <strong><?php echo htmlspecialchars($job['registration_number']); ?></strong><br>
                                            <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?><br>
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($job['phone']); ?>
                                        </p>
                                        <span class="badge bg-success">Ready for Pickup</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Auto-refresh every 2 minutes
setTimeout(function() {
    location.reload();
}, 120000);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>