<?php
// garage_management_system/admin/quotations.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'Quotations Management';
$current_page = 'quotations';

// Permission check
if (!$auth->hasPermission('quotations', 'view')) {
    header('Location: ' . BASE_URL . 'unauthorized.php');
    exit();
}

$message = '';
$message_type = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid security token';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create' && $auth->hasPermission('quotations', 'create')) {
            try {
                $db->getConnection()->beginTransaction();
                
                $quotation_number = 'QUO-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO quotations (garage_id, quotation_number, customer_id, vehicle_id,
                                          total_amount, valid_until, notes, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                ");
                
                $stmt->execute([
                    $_SESSION['garage_id'],
                    $quotation_number,
                    $_POST['customer_id'],
                    $_POST['vehicle_id'],
                    $_POST['total_amount'],
                    $_POST['valid_until'],
                    $_POST['notes'] ?? null,
                    $_SESSION['user_id']
                ]);
                
                $quotation_id = $db->lastInsertId();
                
                // Add services if provided
                if (!empty($_POST['services'])) {
                    foreach (json_decode($_POST['services'], true) as $service) {
                        $stmt = $db->prepare("
                            INSERT INTO quotation_items (quotation_id, item_type, item_id, description, quantity, price)
                            VALUES (?, 'service', ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $quotation_id,
                            $service['service_id'],
                            $service['description'],
                            $service['quantity'],
                            $service['price']
                        ]);
                    }
                }
                
                $db->getConnection()->commit();
                $message = 'Quotation created successfully: ' . $quotation_number;
                $message_type = 'success';
                
            } catch (Exception $e) {
                $db->getConnection()->rollBack();
                $message = 'Error creating quotation: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
        
        if ($action === 'update' && $auth->hasPermission('quotations', 'edit')) {
            try {
                $stmt = $db->prepare("
                    UPDATE quotations 
                    SET customer_id = ?, vehicle_id = ?, total_amount = ?, 
                        valid_until = ?, notes = ?, status = ?
                    WHERE id = ? AND garage_id = ?
                ");
                
                $stmt->execute([
                    $_POST['customer_id'],
                    $_POST['vehicle_id'],
                    $_POST['total_amount'],
                    $_POST['valid_until'],
                    $_POST['notes'] ?? null,
                    $_POST['status'],
                    $_POST['quotation_id'],
                    $_SESSION['garage_id']
                ]);
                
                $message = 'Quotation updated successfully';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error updating quotation: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
        
        if ($action === 'delete' && $auth->hasPermission('quotations', 'delete')) {
            try {
                $stmt = $db->prepare("DELETE FROM quotations WHERE id = ? AND garage_id = ?");
                $stmt->execute([$_POST['quotation_id'], $_SESSION['garage_id']]);
                
                $message = 'Quotation deleted successfully';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error deleting quotation: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Fetch quotations with multi-tenant isolation
$stmt = $db->prepare("
    SELECT q.*, v.registration_number, v.make, v.model,
           c.first_name as customer_first, c.last_name as customer_last,
           c.email, c.phone
    FROM quotations q
    JOIN vehicles v ON q.vehicle_id = v.id
    JOIN customers c ON q.customer_id = c.id
    WHERE q.garage_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$_SESSION['garage_id']]);
$quotations = $stmt->fetchAll();

// Fetch vehicles for dropdown
$stmt = $db->prepare("
    SELECT v.*, c.first_name, c.last_name, c.id as customer_id
    FROM vehicles v
    JOIN customers c ON v.customer_id = c.id
    WHERE v.garage_id = ?
    ORDER BY v.registration_number
");
$stmt->execute([$_SESSION['garage_id']]);
$vehicles = $stmt->fetchAll();

// Fetch services for quotation items
$stmt = $db->prepare("SELECT id, name, price, category FROM services WHERE garage_id = ? ORDER BY name");
$stmt->execute([$_SESSION['garage_id']]);
$services = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-file-alt me-2"></i>Quotations Management</h1>
                <?php if ($auth->hasPermission('quotations', 'create')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createQuotationModal">
                    <i class="fas fa-plus me-2"></i>Create New Quotation
                </button>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Quotations Table -->
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="quotationsTable">
                            <thead class="table-primary">
                                <tr>
                                    <th>Quotation #</th>
                                    <th>Customer</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Valid Until</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quotations as $quot): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($quot['quotation_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($quot['customer_first'] . ' ' . $quot['customer_last']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($quot['phone']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($quot['registration_number']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($quot['make'] . ' ' . $quot['model']); ?></small>
                                    </td>
                                    <td><strong><?php echo $functions->formatCurrency($quot['total_amount']); ?></strong></td>
                                    <td>
                                        <?php 
                                        $valid_date = new DateTime($quot['valid_until']);
                                        $now = new DateTime();
                                        $diff = $now->diff($valid_date);
                                        $days_left = $diff->invert ? -$diff->days : $diff->days;
                                        
                                        echo date('M d, Y', strtotime($quot['valid_until']));
                                        if ($days_left < 0 && $quot['status'] === 'pending') {
                                            echo '<br><span class="badge bg-danger">Expired</span>';
                                        } elseif ($days_left <= 3 && $quot['status'] === 'pending') {
                                            echo '<br><span class="badge bg-warning">' . $days_left . ' days left</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'pending' => 'warning',
                                            'accepted' => 'success',
                                            'rejected' => 'danger',
                                            'expired' => 'secondary'
                                        ];
                                        $badge = $status_badges[$quot['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>">
                                            <?php echo ucfirst($quot['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($quot['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-info" onclick="viewQuotation(<?php echo $quot['id']; ?>)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-secondary" onclick="printQuotation(<?php echo $quot['id']; ?>)" title="Print">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <?php if ($auth->hasPermission('quotations', 'edit') && $quot['status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-warning" onclick="editQuotation(<?php echo $quot['id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($auth->hasPermission('quotations', 'delete')): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteQuotation(<?php echo $quot['id']; ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
        </main>
    </div>
</div>

<!-- Create Quotation Modal -->
<div class="modal fade" id="createQuotationModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="quotationForm">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="services" id="servicesData">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Create New Quotation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Vehicle <span class="text-danger">*</span></label>
                            <select class="form-select" name="vehicle_id" id="vehicleSelect" required>
                                <option value="">Select Vehicle</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo $vehicle['id']; ?>" data-customer="<?php echo $vehicle['customer_id']; ?>">
                                    <?php echo htmlspecialchars($vehicle['registration_number'] . ' - ' . $vehicle['make'] . ' ' . $vehicle['model']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Customer</label>
                            <input type="hidden" name="customer_id" id="customerId">
                            <input type="text" class="form-control" id="customerName" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valid Until <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="valid_until" required 
                                   min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Amount</label>
                            <input type="number" step="0.01" class="form-control" name="total_amount" id="totalAmount" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        
                        <!-- Services Section -->
                        <div class="col-12">
                            <h6 class="border-bottom pb-2">Services & Items</h6>
                            <div class="table-responsive">
                                <table class="table table-sm" id="servicesTable">
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th width="80">Qty</th>
                                            <th width="120">Price</th>
                                            <th width="120">Total</th>
                                            <th width="50"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="servicesBody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addServiceRow()">
                                <i class="fas fa-plus me-1"></i>Add Service
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Quotation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#quotationsTable').DataTable({
        order: [[6, 'desc']],
        pageLength: 25
    });
    
    // Auto-populate customer
    $('#vehicleSelect').on('change', function() {
        const customerId = $(this).find(':selected').data('customer');
        $('#customerId').val(customerId);
        
        if (customerId) {
            const vehicleText = $(this).find(':selected').text();
            const customer = vehicleText.split(' - ')[0];
            $('#customerName').val(customer);
        }
    });
    
    // Submit handler
    $('#quotationForm').on('submit', function(e) {
        const services = [];
        $('#servicesBody tr').each(function() {
            const row = $(this);
            services.push({
                service_id: row.find('.service-select').val(),
                description: row.find('.service-select option:selected').text(),
                quantity: row.find('.qty-input').val(),
                price: row.find('.price-input').val()
            });
        });
        $('#servicesData').val(JSON.stringify(services));
    });
});

const servicesData = <?php echo json_encode($services); ?>;

function addServiceRow() {
    const row = $('<tr>');
    const serviceSelect = $('<select class="form-select form-select-sm service-select">');
    serviceSelect.append('<option value="">Select Service</option>');
    
    servicesData.forEach(service => {
        serviceSelect.append(`<option value="${service.id}" data-price="${service.price}">${service.name} - $${service.price}</option>`);
    });
    
    serviceSelect.on('change', function() {
        const price = $(this).find(':selected').data('price');
        row.find('.price-input').val(price);
        calculateRowTotal(row);
    });
    
    row.append($('<td>').append(serviceSelect));
    row.append($('<td>').html('<input type="number" class="form-control form-control-sm qty-input" value="1" min="1" onchange="calculateRowTotal(this.closest(\'tr\'))">'));
    row.append($('<td>').html('<input type="number" step="0.01" class="form-control form-control-sm price-input" readonly>'));
    row.append($('<td>').html('<input type="number" step="0.01" class="form-control form-control-sm total-input" readonly>'));
    row.append($('<td>').html('<button type="button" class="btn btn-sm btn-danger" onclick="removeServiceRow(this)"><i class="fas fa-times"></i></button>'));
    
    $('#servicesBody').append(row);
}

function calculateRowTotal(row) {
    const qty = parseFloat($(row).find('.qty-input').val()) || 0;
    const price = parseFloat($(row).find('.price-input').val()) || 0;
    const total = qty * price;
    $(row).find('.total-input').val(total.toFixed(2));
    calculateGrandTotal();
}

function calculateGrandTotal() {
    let grandTotal = 0;
    $('#servicesBody .total-input').each(function() {
        grandTotal += parseFloat($(this).val()) || 0;
    });
    $('#totalAmount').val(grandTotal.toFixed(2));
}

function removeServiceRow(btn) {
    $(btn).closest('tr').remove();
    calculateGrandTotal();
}

function viewQuotation(id) {
    window.open('quotation_pdf.php?id=' + id, '_blank');
}

function printQuotation(id) {
    window.open('quotation_pdf.php?id=' + id + '&action=print', '_blank');
}

function editQuotation(id) {
    window.location.href = '?action=edit&id=' + id;
}

function deleteQuotation(id) {
    if (confirm('Are you sure you want to delete this quotation?')) {
        const form = $('<form method="POST">');
        form.append('<input type="hidden" name="action" value="delete">');
        form.append('<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">');
        form.append('<input type="hidden" name="quotation_id" value="' + id + '">');
        $('body').append(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>