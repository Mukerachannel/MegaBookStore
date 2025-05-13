document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
    }
    
    // Close alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    }
    
    // Handle quantity updates in cart
    const quantityForms = document.querySelectorAll('.cart-item-quantity form');
    
    if (quantityForms.length > 0) {
        quantityForms.forEach(function(form) {
            const quantityInput = form.querySelector('input[name="quantity"]');
            const updateBtn = form.querySelector('.update-btn');
            
            if (quantityInput && updateBtn) {
                quantityInput.addEventListener('change', function() {
                    updateBtn.style.display = 'inline-block';
                });
            }
        });
    }
});