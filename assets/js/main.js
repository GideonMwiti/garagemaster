// garage_management_system/assets/js/main.js
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-bs-toggle="popover"]').popover();
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // Confirm delete actions
    $('.delete-btn').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });
    
    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        const input = $(this).parent().find('input');
        const type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });
    
    // Calculate totals in forms
    $('.calculate-total').on('input', function() {
        calculateFormTotals();
    });
    
    // Date pickers
    $('.datepicker').attr('type', 'date');
    
    // File upload preview
    $('.file-input').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').text(fileName);
    });
});

function calculateFormTotals() {
    // Calculate subtotal
    let subtotal = 0;
    $('.item-total').each(function() {
        subtotal += parseFloat($(this).val()) || 0;
    });
    
    // Get tax rate and discount
    const taxRate = parseFloat($('#tax_rate').val()) || 0;
    const discount = parseFloat($('#discount').val()) || 0;
    
    // Calculate tax amount
    const taxAmount = subtotal * (taxRate / 100);
    
    // Calculate total
    const total = subtotal + taxAmount - discount;
    
    // Update display
    $('#subtotal').text(formatCurrency(subtotal));
    $('#tax_amount').text(formatCurrency(taxAmount));
    $('#total_amount').text(formatCurrency(total));
    
    // Update hidden fields if they exist
    $('input[name="subtotal"]').val(subtotal.toFixed(2));
    $('input[name="tax_amount"]').val(taxAmount.toFixed(2));
    $('input[name="total_amount"]').val(total.toFixed(2));
}

function formatCurrency(amount) {
    const currency = $('meta[name="currency"]').attr('content') || '$';
    return currency + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function generatePDF(elementId, filename) {
    const element = document.getElementById(elementId);
    const opt = {
        margin: 1,
        filename: filename + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save();
}

function printElement(elementId) {
    const printContent = document.getElementById(elementId).innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// AJAX helper functions
function ajaxRequest(url, data, successCallback, errorCallback) {
    $.ajax({
        url: url,
        type: 'POST',
        data: data,
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
        },
        success: successCallback,
        error: errorCallback || function(xhr, status, error) {
            console.error('AJAX Error:', error);
            alert('An error occurred. Please try again.');
        }
    });
}

// Inventory search
function searchInventory(query) {
    ajaxRequest(
        'ajax/search_inventory.php',
        { query: query },
        function(response) {
            $('#inventory-results').html(response);
        }
    );
}

// Service price calculation
function calculateServicePrice(serviceId, quantity = 1) {
    ajaxRequest(
        'ajax/get_service_price.php',
        { service_id: serviceId, quantity: quantity },
        function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                $('#service-price-' + serviceId).text(formatCurrency(data.price));
            }
        }
    );
}

// Dashboard chart initialization
function initDashboardCharts() {
    // Revenue chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue',
                    data: [12000, 19000, 15000, 25000, 22000, 30000],
                    borderColor: 'var(--brand-primary)',
                    backgroundColor: 'rgba(0, 168, 206, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Service types chart
    const serviceCtx = document.getElementById('serviceChart');
    if (serviceCtx) {
        new Chart(serviceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Routine', 'Repair', 'Diagnostic', 'Bodywork'],
                datasets: [{
                    data: [40, 25, 20, 15],
                    backgroundColor: [
                        'var(--brand-primary)',
                        'var(--brand-secondary)',
                        'var(--brand-accent)',
                        'var(--brand-muted)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Call chart initialization on dashboard pages
if ($('#revenueChart').length || $('#serviceChart').length) {
    initDashboardCharts();
}