<?php
/**
 * Footer Template
 * WordPress Hosting Panel with LiteSpeed
 */
?>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?></span>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted">Powered by <a href="https://litespeedtech.com/" target="_blank" class="text-decoration-none">LiteSpeed</a></span>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Modal for confirmations -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to perform this action?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmButton">Confirm</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast notifications -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle">Notification</strong>
                <small id="toastTime">Just now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage">
                Action completed successfully.
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/scripts.js"></script>
    
    <?php if (isset($page_scripts) && is_array($page_scripts)): ?>
        <?php foreach ($page_scripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <script>
        // Show toast notification
        function showToast(title, message, type = 'success') {
            const toast = document.getElementById('liveToast');
            const toastTitle = document.getElementById('toastTitle');
            const toastMessage = document.getElementById('toastMessage');
            const toastTime = document.getElementById('toastTime');
            
            // Set content
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            toastTime.textContent = 'Just now';
            
            // Set type
            toast.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'text-white');
            if (type === 'success') {
                toast.classList.add('bg-success', 'text-white');
            } else if (type === 'error') {
                toast.classList.add('bg-danger', 'text-white');
            } else if (type === 'warning') {
                toast.classList.add('bg-warning');
            } else if (type === 'info') {
                toast.classList.add('bg-info');
            }
            
            // Show toast
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        }
        
        // Confirmation modal
        function confirmAction(title, message, callback) {
            const modal = document.getElementById('confirmModal');
            const modalTitle = document.getElementById('confirmModalLabel');
            const modalBody = modal.querySelector('.modal-body');
            const confirmButton = document.getElementById('confirmButton');
            
            // Set content
            modalTitle.textContent = title;
            modalBody.textContent = message;
            
            // Set callback
            confirmButton.onclick = function() {
                callback();
                bootstrap.Modal.getInstance(modal).hide();
            };
            
            // Show modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
        
        // Show success message if present in URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if (success) {
                showToast('Success', decodeURIComponent(success), 'success');
            } else if (error) {
                showToast('Error', decodeURIComponent(error), 'error');
            }
        });
    </script>
</body>
</html>