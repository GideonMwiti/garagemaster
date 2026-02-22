<?php
// garage_management_system/employee/service_schedule.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('employee');
$page_title = 'Service Schedule';
$current_page = 'service_schedule';

// Get upcoming services (including overdue for visibility)
// Shows services due within next 60 days or overdue
$stmt = $db->prepare("
    SELECT v.*, c.first_name, c.last_name, c.phone, c.email,
           DATEDIFF(v.next_service_date, CURDATE()) as days_until
    FROM vehicles v
    JOIN customers c ON v.customer_id = c.id
    WHERE v.garage_id = ? 
          AND v.next_service_date IS NOT NULL
          AND v.next_service_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    ORDER BY v.next_service_date ASC
    LIMIT 50
");
$stmt->execute([$_SESSION['garage_id']]);
$upcoming_services = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-calendar-alt me-2"></i>Service Schedule</h1>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="scheduleTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Vehicle</th>
                                    <th>Customer</th>
                                    <th>Contact</th>
                                    <th>Last Service</th>
                                    <th>Next Service</th>
                                    <th>Days Until</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_services as $service): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($service['registration_number']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($service['make'] . ' ' . $service['model']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($service['phone']); ?><br>
                                        <small><?php echo htmlspecialchars($service['email']); ?></small>
                                    </td>
                                    <td><?php echo $service['last_service_date'] ? date('M d, Y', strtotime($service['last_service_date'])) : '-'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($service['next_service_date'])); ?></td>
                                    <td>
                                        <?php
                                        $days = $service['days_until'];
                                        if ($days < 0) {
                                            echo '<span class="badge bg-danger">Overdue by ' . abs($days) . ' days</span>';
                                        } elseif ($days <= 7) {
                                            echo '<span class="badge bg-warning">' . $days . ' days</span>';
                                        } else {
                                            echo '<span class="badge bg-success">' . $days . ' days</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($days <= 0): ?>
                                        <span class="badge bg-danger">Action Required</span>
                                        <?php elseif ($days <= 7): ?>
                                        <span class="badge bg-warning">Due Soon</span>
                                        <?php else: ?>
                                        <span class="badge bg-success">Scheduled</span>
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

<script>
$(document).ready(function() {
    $('#scheduleTable').DataTable({
        order: [[5, 'asc']],
        pageLength: 25
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>