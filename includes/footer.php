<?php
// garage_management_system/includes/footer.php
?>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light border-top">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 text-muted">
                    <small>&copy; <?php echo date('Y'); ?> <?php echo BRAND_NAME ?? SITE_NAME; ?>. All rights reserved.</small>
                </div>
                <div class="col-md-6 text-end text-muted">
                    <small>Version 1.0.0 | Powered by <a href="https://www.sericsoft.com" target="_blank" class="text-decoration-none text-primary fw-bold">Sericsoft Innovations Ltd</a></small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('.datatable').DataTable({
                "pageLength": 25,
                "responsive": true,
                "order": [[0, 'desc']]
            });
            
            // CSRF Token for AJAX
            $.ajaxSetup({
                headers: {
                    'X-CSRF-Token': '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
                }
            });
            
            // Auto-hide alerts
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
        
        // Confirm before delete
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }
        
        // Format currency
        function formatCurrency(amount) {
            return 'KSH ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    </script>
</body>
</html>