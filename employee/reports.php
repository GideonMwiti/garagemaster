<?php
// garage_management_system/employee/reports.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('employee');
$page_title = 'My Performance Reports';
$current_page = 'reports';

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get performance statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_jobs,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_jobs,
        SUM(actual_hours) as total_hours,
        AVG(actual_hours) as avg_hours,
        SUM(estimated_cost) as total_value
    FROM job_cards
    WHERE assigned_to = ? AND created_at BETWEEN ? AND ?
");
$stmt->execute([$_SESSION['user_id'], $start_date, $end_date]);
$stats = $stmt->fetch();

// Daily completion stats
$stmt = $db->prepare("
    SELECT DATE(updated_at) as date, COUNT(*) as completed
    FROM job_cards
    WHERE assigned_to = ? AND status = 'completed' AND updated_at BETWEEN ? AND ?
    GROUP BY DATE(updated_at)
    ORDER BY date DESC
");
$stmt->execute([$_SESSION['user_id'], $start_date, $end_date]);
$daily_completions = $stmt->fetchAll();

// Service types performed
$stmt = $db->prepare("
    SELECT s.name, s.category, COUNT(js.id) as count
    FROM job_services js
    JOIN services s ON js.service_id = s.id
    JOIN job_cards jc ON js.job_card_id = jc.id
    WHERE jc.assigned_to = ? AND jc.created_at BETWEEN ? AND ?
    GROUP BY s.id
    ORDER BY count DESC
");
$stmt->execute([$_SESSION['user_id'], $start_date, $end_date]);
$services_performed = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-chart-bar me-2"></i>My Performance Reports</h1>
            </div>
            
            <!-- Date Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card border-left-primary shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Jobs</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $stats['total_jobs'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-success shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $stats['completed_jobs'] ?? 0; ?></div>
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
                    <div class="card stat-card border-left-warning shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Avg Hours/Job</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['avg_hours'] ?? 0, 1); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Services Performed -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Services Performed</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Category</th>
                                    <th>Times Performed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services_performed as $service): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($service['name']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($service['category']); ?></span></td>
                                    <td><strong><?php echo $service['count']; ?></strong></td>
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