/**
 * Custom Scripts
 * WordPress Hosting Panel with LiteSpeed
 */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Handle form submissions with AJAX
    const ajaxForms = document.querySelectorAll('.ajax-form');
    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading spinner
            showSpinner();
            
            // Get form data
            const formData = new FormData(form);
            
            // Convert FormData to JSON
            const jsonData = {};
            formData.forEach((value, key) => {
                jsonData[key] = value;
            });
            
            // Get form action URL
            const url = form.getAttribute('action') || window.location.href;
            
            // Send AJAX request
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(jsonData)
            })
            .then(response => response.json())
            .then(data => {
                // Hide loading spinner
                hideSpinner();
                
                // Handle response
                if (data.status === 'success') {
                    // Show success message
                    showToast('Success', data.message || 'Action completed successfully', 'success');
                    
                    // Redirect if specified
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    }
                    
                    // Reset form if specified
                    if (data.reset_form) {
                        form.reset();
                    }
                    
                    // Reload page if specified
                    if (data.reload) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                    
                    // Call custom callback if specified
                    if (form.dataset.successCallback) {
                        window[form.dataset.successCallback](data);
                    }
                } else {
                    // Show error message
                    showToast('Error', data.message || 'An error occurred', 'error');
                }
            })
            .catch(error => {
                // Hide loading spinner
                hideSpinner();
                
                // Show error message
                showToast('Error', 'An error occurred: ' + error.message, 'error');
            });
        });
    });
    
    // Handle delete confirmations
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemId = this.dataset.id;
            const itemType = this.dataset.type;
            const itemName = this.dataset.name || 'this item';
            
            confirmAction(
                'Delete ' + itemType,
                'Are you sure you want to delete ' + itemName + '? This action cannot be undone.',
                () => {
                    // Show loading spinner
                    showSpinner();
                    
                    // Send delete request
                    fetch(this.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            id: itemId,
                            csrf_token: document.querySelector('meta[name="csrf-token"]').content
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Hide loading spinner
                        hideSpinner();
                        
                        // Handle response
                        if (data.status === 'success') {
                            // Show success message
                            showToast('Success', data.message || itemType + ' deleted successfully', 'success');
                            
                            // Remove item from DOM if it exists
                            const itemElement = document.getElementById(itemType + '-' + itemId);
                            if (itemElement) {
                                itemElement.remove();
                            } else {
                                // Reload page
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            }
                        } else {
                            // Show error message
                            showToast('Error', data.message || 'Failed to delete ' + itemType, 'error');
                        }
                    })
                    .catch(error => {
                        // Hide loading spinner
                        hideSpinner();
                        
                        // Show error message
                        showToast('Error', 'An error occurred: ' + error.message, 'error');
                    });
                }
            );
        });
    });
    
    // Handle status toggle buttons
    const statusToggles = document.querySelectorAll('.status-toggle');
    statusToggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const itemId = this.dataset.id;
            const itemType = this.dataset.type;
            const newStatus = this.checked ? 'active' : 'suspended';
            
            // Show loading spinner
            showSpinner();
            
            // Send status update request
            fetch('api/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    id: itemId,
                    type: itemType,
                    status: newStatus,
                    csrf_token: document.querySelector('meta[name="csrf-token"]').content
                })
            })
            .then(response => response.json())
            .then(data => {
                // Hide loading spinner
                hideSpinner();
                
                // Handle response
                if (data.status === 'success') {
                    // Show success message
                    showToast('Success', data.message || 'Status updated successfully', 'success');
                    
                    // Update status badge if it exists
                    const statusBadge = document.querySelector('#' + itemType + '-' + itemId + ' .status-badge');
                    if (statusBadge) {
                        statusBadge.className = 'badge ' + (newStatus === 'active' ? 'bg-success' : 'bg-danger') + ' status-badge';
                        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    }
                } else {
                    // Show error message
                    showToast('Error', data.message || 'Failed to update status', 'error');
                    
                    // Revert toggle state
                    this.checked = !this.checked;
                }
            })
            .catch(error => {
                // Hide loading spinner
                hideSpinner();
                
                // Show error message
                showToast('Error', 'An error occurred: ' + error.message, 'error');
                
                // Revert toggle state
                this.checked = !this.checked;
            });
        });
    });
});

// Show loading spinner
function showSpinner() {
    // Check if spinner already exists
    if (document.querySelector('.spinner-overlay')) {
        return;
    }
    
    // Create spinner element
    const spinner = document.createElement('div');
    spinner.className = 'spinner-overlay';
    spinner.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
    
    // Add spinner to body
    document.body.appendChild(spinner);
}

// Hide loading spinner
function hideSpinner() {
    const spinner = document.querySelector('.spinner-overlay');
    if (spinner) {
        spinner.remove();
    }
}

// Format bytes to human-readable format
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Copy text to clipboard
function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        const successful = document.execCommand('copy');
        const message = successful ? 'Text copied to clipboard' : 'Failed to copy text';
        showToast('Clipboard', message, successful ? 'success' : 'error');
    } catch (err) {
        showToast('Error', 'Failed to copy text: ' + err, 'error');
    }
    
    document.body.removeChild(textarea);
}

// Password strength meter
function checkPasswordStrength(password) {
    let strength = 0;
    
    // Length check
    if (password.length >= 8) {
        strength += 1;
    }
    
    // Contains lowercase letters
    if (password.match(/[a-z]+/)) {
        strength += 1;
    }
    
    // Contains uppercase letters
    if (password.match(/[A-Z]+/)) {
        strength += 1;
    }
    
    // Contains numbers
    if (password.match(/[0-9]+/)) {
        strength += 1;
    }
    
    // Contains special characters
    if (password.match(/[$@#&!]+/)) {
        strength += 1;
    }
    
    return strength;
}

// Update password strength meter
function updatePasswordStrength(password, meterElement) {
    const strength = checkPasswordStrength(password);
    
    // Update meter width
    meterElement.style.width = (strength * 20) + '%';
    
    // Update meter color
    if (strength < 2) {
        meterElement.className = 'progress-bar bg-danger';
        meterElement.textContent = 'Weak';
    } else if (strength < 4) {
        meterElement.className = 'progress-bar bg-warning';
        meterElement.textContent = 'Medium';
    } else {
        meterElement.className = 'progress-bar bg-success';
        meterElement.textContent = 'Strong';
    }
}