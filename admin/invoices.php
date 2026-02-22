<?php
// garage_management_system/admin/invoices.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth->requireRole('admin');
$page_title = 'Invoices Management';
$current_page = 'invoices';

if (!$auth->hasPermission('invoices', 'view')) {
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
        
        if ($action === 'create' && $auth->hasPermission('invoices', 'create')) {
            try {
                $db->getConnection()->beginTransaction();
                
                $invoice_number = $functions->generateInvoiceNumber($_SESSION['garage_id']);
                $subtotal = floatval($_POST['subtotal']);
                $tax_rate = floatval($_POST['tax_rate'] ?? 0);
                $discount = floatval($_POST['discount'] ?? 0);
                $tax_amount = ($subtotal - $discount) * ($tax_rate / 100);
                $total_amount = $subtotal - $discount + $tax_amount;
                
                $stmt = $db->prepare("
                    INSERT INTO invoices (garage_id, invoice_number, job_card_id, customer_id, vehicle_id,
                                        subtotal, tax_rate, tax_amount, discount, total_amount, 
                                        status, due_date, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_SESSION['garage_id'],
                    $invoice_number,
                    $_POST['job_card_id'] ?? null,
                    $_POST['customer_id'],
                    $_POST['vehicle_id'],
                    $subtotal,
                    $tax_rate,
                    $tax_amount,
                    $discount,
                    $total_amount,
                    $_POST['due_date'],
                    $_POST['notes'] ?? null,
                    $_SESSION['user_id']
                ]);
                
                $invoice_id = $db->lastInsertId();
                
                // Add invoice items
                if (!empty($_POST['items'])) {
                    foreach (json_decode($_POST['items'], true) as $item) {
                        $inventory_id = !empty($item['inventory_id']) ? $item['inventory_id'] : null;
                        
                        $stmt = $db->prepare("
                            INSERT INTO invoice_items (invoice_id, inventory_id, description, quantity, unit_price, total_price)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $invoice_id,
                            $inventory_id,
                            $item['description'],
                            $item['quantity'],
                            $item['unit_price'],
                            $item['total_price']
                        ]);
                        
                        // Deduct stock if inventory item linked
                        if ($inventory_id) {
                            $stmt = $db->prepare("
                                UPDATE inventory 
                                SET quantity = GREATEST(0, quantity - ?) 
                                WHERE id = ? AND garage_id = ?
                            ");
                            $stmt->execute([$item['quantity'], $inventory_id, $_SESSION['garage_id']]);
                        }
                    }
                }
                
                $db->getConnection()->commit();
                $message = 'Invoice created successfully: ' . $invoice_number;
                $message_type = 'success';
                
            } catch (Exception $e) {
                $db->getConnection()->rollBack();
                $message = 'Error creating invoice: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
        
        if ($action === 'update_status' && $auth->hasPermission('invoices', 'edit')) {
            try {
                $stmt = $db->prepare("
                    UPDATE invoices SET status = ? WHERE id = ? AND garage_id = ?
                ");
                $stmt->execute([$_POST['status'], $_POST['invoice_id'], $_SESSION['garage_id']]);
                
                $message = 'Invoice status updated successfully';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error updating invoice: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
        
        if ($action === 'delete' && $auth->hasPermission('invoices', 'delete')) {
            try {
                // Check if invoice has payments
                $stmt = $db->prepare("SELECT COUNT(*) as payment_count FROM payments WHERE invoice_id = ?");
                $stmt->execute([$_POST['invoice_id']]);
                $result = $stmt->fetch();
                
                if ($result['payment_count'] > 0) {
                    $message = 'Cannot delete invoice with existing payments';
                    $message_type = 'warning';
                } else {
                    $stmt = $db->prepare("DELETE FROM invoices WHERE id = ? AND garage_id = ?");
                    $stmt->execute([$_POST['invoice_id'], $_SESSION['garage_id']]);
                    
                    $message = 'Invoice deleted successfully';
                    $message_type = 'success';
                }
                
            } catch (Exception $e) {
                $message = 'Error deleting invoice: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Fetch invoices with multi-tenant isolation
$stmt = $db->prepare("
    SELECT i.*, v.registration_number, v.make, v.model,
           c.first_name as customer_first, c.last_name as customer_last,
           c.email, c.phone,
           COALESCE(SUM(p.amount), 0) as paid_amount
    FROM invoices i
    JOIN vehicles v ON i.vehicle_id = v.id
    JOIN customers c ON i.customer_id = c.id
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE i.garage_id = ?
    GROUP BY i.id
    ORDER BY i.created_at DESC
");
$stmt->execute([$_SESSION['garage_id']]);
$invoices = $stmt->fetchAll();

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

// Fetch job cards for linking
$stmt = $db->prepare("
    SELECT jc.id, jc.job_number, v.registration_number
    FROM job_cards jc
    JOIN vehicles v ON jc.vehicle_id = v.id
    WHERE jc.garage_id = ? AND jc.status = 'completed'
    AND jc.id NOT IN (SELECT job_card_id FROM invoices WHERE job_card_id IS NOT NULL)
    ORDER BY jc.created_at DESC
");
$stmt->execute([$_SESSION['garage_id']]);
$available_jobs = $stmt->fetchAll();

// Get tax rate from settings
$stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'default_tax_rate' AND (garage_id = ? OR garage_id IS NULL) ORDER BY garage_id DESC LIMIT 1");
$stmt->execute([$_SESSION['garage_id']]);
$default_tax_rate = $stmt->fetchColumn() ?: 0;

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-file-invoice-dollar me-2"></i>Invoices Management</h1>
                <?php if ($auth->hasPermission('invoices', 'create')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                    <i class="fas fa-plus me-2"></i>Create New Invoice
                </button>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <?php
                $total_revenue = array_sum(array_column($invoices, 'total_amount'));
                $total_paid = array_sum(array_column($invoices, 'paid_amount'));
                $total_pending = $total_revenue - $total_paid;
                $paid_count = count(array_filter($invoices, fn($inv) => $inv['status'] === 'paid'));
                $overdue_count = count(array_filter($invoices, fn($inv) => $inv['status'] === 'overdue'));
                ?>
                <div class="col-md-3">
                    <div class="card stat-card border-left-primary shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Revenue</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($total_revenue); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-success shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Paid</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($total_paid); ?></div>
                            <small class="text-muted"><?php echo $paid_count; ?> invoices</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-warning shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $functions->formatCurrency($total_pending); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-left-danger shadow">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo $overdue_count; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Invoices Table -->
            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="invoicesTable">
                            <thead class="table-primary">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): ?>
                                <?php
                                $balance = $inv['total_amount'] - $inv['paid_amount'];
                                $is_overdue = strtotime($inv['due_date']) < time() && $balance > 0 && $inv['status'] !== 'paid';
                                ?>
                                <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                                    <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($inv['customer_first'] . ' ' . $inv['customer_last']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($inv['phone']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($inv['registration_number']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($inv['make'] . ' ' . $inv['model']); ?></small>
                                    </td>
                                    <td><strong><?php echo $functions->formatCurrency($inv['total_amount']); ?></strong></td>
                                    <td><?php echo $functions->formatCurrency($inv['paid_amount']); ?></td>
                                    <td><strong><?php echo $functions->formatCurrency($balance); ?></strong></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($inv['due_date'])); ?>
                                        <?php if ($is_overdue): ?>
                                        <br><span class="badge bg-danger">Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'draft' => 'secondary',
                                            'sent' => 'info',
                                            'paid' => 'success',
                                            'overdue' => 'danger',
                                            'cancelled' => 'dark'
                                        ];
                                        $badge = $status_badges[$inv['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge; ?>">
                                            <?php echo ucfirst($inv['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-info" onclick="viewInvoice(<?php echo $inv['id']; ?>)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-secondary" onclick="printInvoice(<?php echo $inv['id']; ?>)" title="Print">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <?php if ($auth->hasPermission('invoices', 'edit') && $balance > 0): ?>
                                            <button class="btn btn-sm btn-success" onclick="recordPayment(<?php echo $inv['id']; ?>)" title="Record Payment">
                                                <i class="fas fa-dollar-sign"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($auth->hasPermission('invoices', 'delete') && $inv['paid_amount'] == 0): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteInvoice(<?php echo $inv['id']; ?>)" title="Delete">
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

<!-- Create Invoice Modal -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="invoiceForm">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="items" id="itemsData">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Create New Invoice</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Link to Job Card (Optional)</label>
                            <select class="form-select" name="job_card_id" id="jobCardSelect">
                                <option value="">None - Manual Invoice</option>
                                <?php foreach ($available_jobs as $job): ?>
                                <option value="<?php echo $job['id']; ?>">
                                    <?php echo htmlspecialchars($job['job_number'] . ' - ' . $job['registration_number']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                            <label class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="due_date" required 
                                   min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        
                        <!-- Invoice Items -->
                        <div class="col-12">
                            <h6 class="border-bottom pb-2">Invoice Items</h6>
                            <div class="table-responsive">
                                <table class="table table-sm" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th width="80">Qty</th>
                                            <th width="120">Unit Price</th>
                                            <th width="120">Total</th>
                                            <th width="50"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsBody"></tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="subtotal" id="subtotal" readonly></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="text-end">
                                                <strong>Discount:</strong>
                                            </td>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="discount" id="discount" value="0" onchange="calculateTotals()"></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="text-end">
                                                <strong>Tax (%):</strong>
                                            </td>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" name="tax_rate" id="tax_rate" value="<?php echo $default_tax_rate; ?>" onchange="calculateTotals()"></td>
                                            <td></td>
                                        </tr>
                                        <tr class="table-primary">
                                            <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
                                            <td><strong id="grandTotal">$0.00</strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItemRow()">
                                <i class="fas fa-plus me-1"></i>Add Item
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#invoicesTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25
    });
    
    $('#vehicleSelect').on('change', function() {
        const customerId = $(this).find(':selected').data('customer');
        $('#customerId').val(customerId);
    });
    
    $('#invoiceForm').on('submit', function(e) {
        const items = [];
        $('#itemsBody tr').each(function() {
            const row = $(this);
            items.push({
                inventory_id: row.find('.inventory-id-input').val(),
                description: row.find('.desc-input').val(),
                quantity: row.find('.qty-input').val(),
                unit_price: row.find('.price-input').val(),
                total_price: row.find('.total-input').val()
            });
        });
        $('#itemsData').val(JSON.stringify(items));
    });
    
// Link to Inventory search
    $(document).on('focus', '.desc-input', function() {
        if (!$(this).data('autocomplete')) {
            $(this).autocomplete({
                source: function(request, response) {
                    $.getJSON('ajax/search_inventory.php', { term: request.term }, response);
                },
                minLength: 2,
                select: function(event, ui) {
                    const row = $(this).closest('tr');
                    row.find('.qty-input').val(1);
                    row.find('.qty-input').attr('max', ui.item.stock); // Optional: limit to stock
                    row.find('.price-input').val(ui.item.price);
                    row.find('.inventory-id-input').val(ui.item.id);
                    calculateItemTotal(row);
                }
            });
            $(this).data('autocomplete', true);
        }
    });

    // Add initial row
    addItemRow();
});

function addItemRow() {
    const row = $('<tr>');
    row.append($('<td>').html(`
        <input type="text" class="form-control form-control-sm desc-input" placeholder="Item description or search part..." required>
        <input type="hidden" class="inventory-id-input">
    `));
    row.append($('<td>').html('<input type="number" class="form-control form-control-sm qty-input" value="1" min="1" onchange="calculateItemTotal(this.closest(\'tr\'))" required>'));
    row.append($('<td>').html('<input type="number" step="0.01" class="form-control form-control-sm price-input" onchange="calculateItemTotal(this.closest(\'tr\'))" required>'));
    row.append($('<td>').html('<input type="number" step="0.01" class="form-control form-control-sm total-input" readonly>'));
    row.append($('<td>').html('<button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow(this)"><i class="fas fa-times"></i></button>'));
    $('#itemsBody').append(row);
}

function calculateItemTotal(row) {
    const qty = parseFloat($(row).find('.qty-input').val()) || 0;
    const price = parseFloat($(row).find('.price-input').val()) || 0;
    const total = qty * price;
    $(row).find('.total-input').val(total.toFixed(2));
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    $('#itemsBody .total-input').each(function() {
        subtotal += parseFloat($(this).val()) || 0;
    });
    
    const discount = parseFloat($('#discount').val()) || 0;
    const taxRate = parseFloat($('#tax_rate').val()) || 0;
    const taxableAmount = subtotal - discount;
    const taxAmount = taxableAmount * (taxRate / 100);
    const grandTotal = taxableAmount + taxAmount;
    
    $('#subtotal').val(subtotal.toFixed(2));
    $('#grandTotal').text('$' + grandTotal.toFixed(2));
}

function removeItemRow(btn) {
    if ($('#itemsBody tr').length > 1) {
        $(btn).closest('tr').remove();
        calculateTotals();
    } else {
        alert('At least one item is required');
    }
}

function viewInvoice(id) {
    window.open('invoice_pdf.php?id=' + id, '_blank');
}

function printInvoice(id) {
    window.open('invoice_pdf.php?id=' + id + '&action=print', '_blank');
}

function recordPayment(id) {
    window.location.href = 'payments.php?invoice_id=' + id + '&action=add';
}

function deleteInvoice(id) {
    if (confirm('Are you sure you want to delete this invoice?')) {
        const form = $('<form method="POST">');
        form.append('<input type="hidden" name="action" value="delete">');
        form.append('<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">');
        form.append('<input type="hidden" name="invoice_id" value="' + id + '">');
        $('body').append(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
