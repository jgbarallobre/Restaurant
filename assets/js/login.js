/*
 * Login JavaScript
 * Sistema POS Restaurante
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            }
        });
    }
    
    // Form validation
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const usuario = document.getElementById('usuario');
            const password = document.getElementById('password');
            let valid = true;
            
            // Reset errors
            usuario.classList.remove('is-invalid');
            password.classList.remove('is-invalid');
            
            if (usuario.value.length < 4) {
                showError(usuario, 'El usuario debe tener al menos 4 caracteres');
                valid = false;
            }
            
            if (password.value.length < 6) {
                showError(password, 'La contraseña debe tener al menos 6 caracteres');
                valid = false;
            }
            
            if (!valid) {
                e.preventDefault();
            } else {
                // Show loading
                const btn = document.getElementById('btnLogin');
                const spinner = document.getElementById('spinner');
                const btnText = btn.querySelector('span:last-child');
                
                btn.disabled = true;
                spinner.classList.remove('d-none');
                btnText.textContent = 'Ingresando...';
            }
        });
    }
    
    function showError(input, message) {
        input.classList.add('is-invalid');
        const feedback = input.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = message;
        }
    }
});
