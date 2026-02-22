<?php
// garage_management_system/employee/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('employee');
$page_title = 'Technician Dashboard';
$current_page = 'dashboard';

// Get assigned job cards
$stmt = $db->prepare("
    SELECT jc.*, v.registration_number, v.make, v.model,
           c.first_name, c.last_name, c.phone
    FROM job_cards jc
    JOIN vehicles v ON jc.vehicle_id = v.id
    JOIN customers c ON jc.customer_id = c.id
    WHERE jc.assigned_to = ? AND jc.status IN ('pending', 'in_progress', 'waiting_parts')
    ORDER BY jc.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$assigned_jobs = $stmt->fetchAll();

// Get completed jobs count
$stmt = $db->prepare("
    SELECT COUNT(*) as completed, 
           SUM(actual_hours) as total_hours,
           AVG(actual_hours) as avg_hours
    FROM job_cards
    WHERE assigned_to = ? AND status = 'completed' 
          AND MONTH(updated_at) = MONTH(CURRENT_DATE())
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Get today's schedule
$stmt = $db->prepare("
    SELECT jc.*, v.registration_number, v.make, v.model, c.first_name, c.last_name
    FROM job_cards jc
    JOIN vehicles v ON jc.vehicle_id = v.id
    JOIN customers c ON jc.customer_id = c.id
    WHERE jc.assigned_to = ? AND DATE(jc.created_at) = CURDATE()
    ORDER BY jc.created_at ASC
");
$stmt->execute([$_SESSION['user_id']]);
$today_jobs = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-tachometer-alt me-2"></i>Technician Dashboard</h1>
                <div class="text-muted">
                    <i class="fas fa-calendar me-1"></i><?php echo date('l, F j, Y'); ?>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card border-left-warning shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active Jobs</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo count($assigned_jobs); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-success shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed (Month)</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $stats['completed'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-info shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Hours</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_hours'] ?? 0, 1); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-primary shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Avg Hours/Job</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['avg_hours'] ?? 0, 1); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Today's Schedule -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Today's Schedule</h5>
                </div>
                <div class="card-body">
                    <?php if (count($today_jobs) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($today_jobs as $job): ?>
                        <a href="job_cards.php?action=view&id=<?php echo $job['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($job['job_number']); ?></h6>
                                <small><?php echo date('H:i', strtotime($job['created_at'])); ?></small>
                            </div>
                            <p class="mb-1">
                                <strong><?php echo htmlspecialchars($job['registration_number']); ?></strong> - 
                                <?php echo htmlspecialchars($job['make'] . ' ' . $job['model']); ?>
                            </p>
                            <small><?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-check-circle fa-3x mb-2"></i>
                        <p>No jobs scheduled for today</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Assigned Jobs -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>My Assigned Jobs</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Job #</th>
                                    <th>Vehicle</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Estimated Hours</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_jobs as $job): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($job['job_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($job['registration_number']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($job['make'] . ' ' . $job['model']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($job['phone']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'pending' => 'secondary',
                                            'in_progress' => 'primary',
                                            'waiting_parts' => 'warning'
                                        ];
                                        $badge = $badges[$job['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $job['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $job['estimated_hours'] ? number_format($job['estimated_hours'], 1) . ' hrs' : '-'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                    <td>
                                        <a href="job_cards.php?action=view&id=<?php echo $job['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
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
