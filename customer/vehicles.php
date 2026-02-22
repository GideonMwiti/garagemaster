<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('customer');
$page_title = 'My Vehicles';
$current_page = 'vehicles';

$stmt = $db->prepare("SELECT id FROM customers WHERE email = (SELECT email FROM users WHERE id = ?) LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();
$customer_id = $customer['id'] ?? null;

if (!$customer_id) die('Customer account not configured');

$stmt = $db->prepare("SELECT * FROM vehicles WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$customer_id]);
$vehicles = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-car me-2"></i>My Vehicles</h1>
            </div>
            
            <div class="row">
                <?php foreach ($vehicles as $vehicle): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?php echo htmlspecialchars($vehicle['registration_number']); ?></h5>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h6>
                            <ul class="list-unstyled">
                                <li><strong>Year:</strong> <?php echo $vehicle['year']; ?></li>
                                <li><strong>Color:</strong> <?php echo htmlspecialchars($vehicle['color']); ?></li>
                                <li><strong>Fuel:</strong> <?php echo ucfirst($vehicle['fuel_type']); ?></li>
                                <?php if ($vehicle['vin']): ?>
                                <li><strong>VIN:</strong> <small><?php echo htmlspecialchars($vehicle['vin']); ?></small></li>
                                <?php endif; ?>
                            </ul>
                            <hr>
                            <p class="mb-1"><i class="fas fa-calendar me-2"></i><strong>Last Service:</strong><br>
                            <?php echo $vehicle['last_service_date'] ? date('M d, Y', strtotime($vehicle['last_service_date'])) : 'No record'; ?></p>
                            <p class="mb-0"><i class="fas fa-calendar-check me-2"></i><strong>Next Service:</strong><br>
                            <?php echo $vehicle['next_service_date'] ? date('M d, Y', strtotime($vehicle['next_service_date'])) : 'Not scheduled'; ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>