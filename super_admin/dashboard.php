<?php
// garage_management_system/super_admin/dashboard.php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('super_admin');
$page_title = 'Super Admin Dashboard';
$current_page = 'dashboard';

// Get system-wide statistics
$stmt = $db->query("SELECT COUNT(*) as total_garages FROM garages WHERE status = 'active'");
$active_garages = $stmt->fetch()['total_garages'];

$stmt = $db->query("SELECT COUNT(*) as total_users FROM users WHERE role_id != 1 AND status = 'active'");
$total_users = $stmt->fetch()['total_users'];

$stmt = $db->query("SELECT COUNT(*) as total_vehicles FROM vehicles");
$total_vehicles = $stmt->fetch()['total_vehicles'];

$stmt = $db->query("SELECT COUNT(*) as total_jobs FROM job_cards WHERE status IN ('pending', 'in_progress')");
$active_jobs = $stmt->fetch()['total_jobs'];

// Revenue statistics (this month)
$stmt = $db->query("
    SELECT 
        COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) THEN total_amount ELSE 0 END), 0) as month_revenue,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN status IN ('sent', 'draft') THEN total_amount ELSE 0 END), 0) as pending_revenue
    FROM invoices
");
$revenue_stats = $stmt->fetch();

// Recent garages
$stmt = $db->query("
    SELECT g.*, 
           (SELECT COUNT(*) FROM users WHERE garage_id = g.id AND role_id = 2) as admin_count,
           (SELECT COUNT(*) FROM users WHERE garage_id = g.id AND role_id != 1) as total_users
    FROM garages g 
    ORDER BY g.created_at DESC 
    LIMIT 5
");
$recent_garages = $stmt->fetchAll();

// System activity (recent users)
$stmt = $db->query("
    SELECT u.*, r.name as role_name, g.name as garage_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN garages g ON u.garage_id = g.id
    WHERE u.role_id != 1
    ORDER BY u.created_at DESC
    LIMIT 10
");
$recent_users = $stmt->fetchAll();

// Garage performance
$stmt = $db->query("
    SELECT g.name, g.id,
           COUNT(DISTINCT jc.id) as job_count,
           COALESCE(SUM(i.total_amount), 0) as revenue
    FROM garages g
    LEFT JOIN job_cards jc ON g.id = jc.garage_id AND MONTH(jc.created_at) = MONTH(CURDATE())
    LEFT JOIN invoices i ON g.id = i.garage_id AND i.status = 'paid' AND MONTH(i.created_at) = MONTH(CURDATE())
    WHERE g.status = 'active'
    GROUP BY g.id, g.name
    ORDER BY revenue DESC
    LIMIT 10
");
$top_garages = $stmt->fetchAll();

include '../includes/header.php';
?>
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-crown me-2 text-warning"></i>Super Admin Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-danger me-2">SUPER ADMIN</span>
                        <span class="text-muted"><?php echo date('F d, Y'); ?></span>
                    </div>
                </div>
                
                <!-- System Statistics -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Active Garages
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $active_garages; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-building fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Revenue (This Month)
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $functions->formatCurrency($revenue_stats['month_revenue']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Active Users
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $total_users; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Active Jobs
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $active_jobs; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-wrench fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Top Performing Garages -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Top Performing Garages (This Month)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Garage</th>
                                                <th>Jobs</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_garages as $garage): ?>
                                            <tr>
                                                <td>
                                                    <a href="garages.php?action=view&id=<?php echo $garage['id']; ?>">
                                                        <?php echo htmlspecialchars($garage['name']); ?>
                                                    </a>
                                                </td>
                                                <td><span class="badge bg-info"><?php echo $garage['job_count']; ?></span></td>
                                                <td class="text-success"><strong><?php echo $functions->formatCurrency($garage['revenue']); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Garages -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-building me-2"></i>Recent Garages</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Users</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_garages as $garage): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($garage['name']); ?></td>
                                                <td><?php echo $garage['total_users']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $garage['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($garage['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="garages.php?action=view&id=<?php echo $garage['id']; ?>" 
                                                       class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent System Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent System Activity</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Garage</th>
                                                <th>Status</th>
                                                <th>Last Login</th>
                                                <th>Registered</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo ucfirst(str_replace('_', ' ', $user['role_name'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['garage_name'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never'; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

<?php include '../includes/footer.php'; ?>
