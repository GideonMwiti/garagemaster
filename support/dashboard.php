<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('support_staff');
$page_title = 'Support Dashboard';
$current_page = 'dashboard';

// Today's gate passes
$stmt = $db->prepare("
    SELECT gp.*, v.registration_number, v.make, v.model, c.first_name, c.last_name
    FROM gate_pass gp
    JOIN vehicles v ON gp.vehicle_id = v.id
    JOIN customers c ON gp.customer_id = c.id
    WHERE gp.garage_id = ? AND DATE(gp.entry_time) = CURDATE()
    ORDER BY gp.entry_time DESC
");
$stmt->execute([$_SESSION['garage_id']]);
$today_passes = $stmt->fetchAll();

// Active job cards count
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count
    FROM job_cards
    WHERE garage_id = ? AND status IN ('pending', 'in_progress', 'waiting_parts')
    GROUP BY status
");
$stmt->execute([$_SESSION['garage_id']]);
$job_stats = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-headset me-2"></i>Support Dashboard</h1>
                <div class="text-muted"><?php echo date('l, F j, Y'); ?></div>
            </div>
            
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card border-left-primary shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Today's Passes</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo count($today_passes); ?></div>
                        </div>
                    </div>
                </div>
                <?php foreach ($job_stats as $stat): ?>
                <div class="col-md-3">
                    <div class="card stat-card border-left-info shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1"><?php echo ucwords(str_replace('_', ' ', $stat['status'])); ?></div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $stat['count']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Today's Gate Passes -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-car-side me-2"></i>Today's Vehicle Gate Passes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Pass #</th>
                                    <th>Vehicle</th>
                                    <th>Customer</th>
                                    <th>Purpose</th>
                                    <th>Entry Time</th>
                                    <th>Exit Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_passes as $pass): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pass['pass_number']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($pass['registration_number']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($pass['make'] . ' ' . $pass['model']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($pass['first_name'] . ' ' . $pass['last_name']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($pass['purpose']); ?></span></td>
                                    <td><?php echo date('H:i', strtotime($pass['entry_time'])); ?></td>
                                    <td><?php echo $pass['exit_time'] ? date('H:i', strtotime($pass['exit_time'])) : '-'; ?></td>
                                    <td>
                                        <?php if ($pass['exit_time']): ?>
                                        <span class="badge bg-success">Exited</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">Inside</span>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>