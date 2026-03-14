/*
 * Usuarios JavaScript
 * Sistema POS Restaurante
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Validación del formulario de crear usuario
    const formCrear = document.getElementById('formCrear');
    if (formCrear) {
        formCrear.addEventListener('submit', function(e) {
            const password = document.getElementById('passwordCrear');
            const confirmar = formCrear.querySelector('input[name="confirmar_password"]');
            const usuario = formCrear.querySelector('input[name="usuario"]');
            
            let valid = true;
            
            // Validar contraseña
            if (password.value.length < 6) {
                showError(password, 'La contraseña debe tener al menos 6 caracteres');
                valid = false;
            } else {
                clearError(password);
            }
            
            // Validar coincidencia de contraseñas
            if (password.value !== confirmar.value) {
                showError(confirmar, 'Las contraseñas no coinciden');
                valid = false;
            } else {
                clearError(confirmar);
            }
            
            // Validar usuario
            if (usuario.value.length < 4) {
                showError(usuario, 'El usuario debe tener al menos 4 caracteres');
                valid = false;
            } else {
                clearError(usuario);
            }
            
            if (!valid) {
                e.preventDefault();
            }
        });
        
        // Validar usuario en tiempo real (AJAX)
        const usuarioInput = formCrear.querySelector('input[name="usuario"]');
        if (usuarioInput) {
            usuarioInput.addEventListener('blur', function() {
                if (this.value.length >= 4) {
                    validarUsuario(this.value, this);
                }
            });
        }
    }
    
    // Validación de formularios de edición
    document.querySelectorAll('form[action="acciones.php"]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const password = form.querySelector('input[name="password"]');
            const nombre = form.querySelector('input[name="nombre"]');
            
            let valid = true;
            
            if (password && password.value.length > 0 && password.value.length < 6) {
                showError(password, 'La contraseña debe tener al menos 6 caracteres');
                valid = false;
            } else if (password) {
                clearError(password);
            }
            
            if (nombre && nombre.value.length < 3) {
                showError(nombre, 'El nombre debe tener al menos 3 caracteres');
                valid = false;
            } else if (nombre) {
                clearError(nombre);
            }
            
            if (!valid) {
                e.preventDefault();
            }
        });
    });
    
    // Función para validar usuario único via AJAX
    function validarUsuario(usuario, input) {
        fetch('validar_usuario.php?usuario=' + encodeURIComponent(usuario))
            .then(response => response.json())
            .then(data => {
                if (data.existe) {
                    showError(input, 'Este nombre de usuario ya está en uso');
                    input.dataset.valido = 'false';
                } else {
                    clearError(input);
                    input.dataset.valido = 'true';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }
    
    function showError(input, message) {
        input.classList.add('is-invalid');
        const feedback = input.parentElement.querySelector('.invalid-feedback') || 
                        input.parentElement.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = message;
            feedback.style.display = 'block';
        }
    }
    
    function clearError(input) {
        input.classList.remove('is-invalid');
        const feedback = input.parentElement.querySelector('.invalid-feedback') || 
                        input.parentElement.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = '';
            feedback.style.display = 'none';
        }
    }
});

// Toggle password global
function togglePasswordField(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input && icon) {
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
    }
}
