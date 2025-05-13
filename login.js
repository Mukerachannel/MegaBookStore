document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    
    togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
            const passwordInput = this.previousElementSibling;
            
            // Toggle password visibility
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
    });
    
    // Password strength meter
    const passwordInput = document.getElementById('password');
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text');
    
    if (passwordInput && strengthBar && strengthText) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Check password length
            if (password.length >= 8) {
                strength += 25;
            }
            
            // Check for uppercase letters
            if (/[A-Z]/.test(password)) {
                strength += 25;
            }
            
            // Check for numbers
            if (/[0-9]/.test(password)) {
                strength += 25;
            }
            
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) {
                strength += 25;
            }
            
            // Update strength bar
            strengthBar.style.width = strength + '%';
            
            // Update strength text
            if (strength <= 25) {
                strengthBar.style.backgroundColor = '#e74c3c';
                strengthText.textContent = 'Weak';
            } else if (strength <= 50) {
                strengthBar.style.backgroundColor = '#f39c12';
                strengthText.textContent = 'Fair';
            } else if (strength <= 75) {
                strengthBar.style.backgroundColor = '#f1c40f';
                strengthText.textContent = 'Good';
            } else {
                strengthBar.style.backgroundColor = '#2ecc71';
                strengthText.textContent = 'Strong';
            }
        });
    }
    
    // Form validation
    const signupForm = document.getElementById('signup-form');
    
    if (signupForm) {
        signupForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    }
});