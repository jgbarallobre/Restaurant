/*
 * Configuración JavaScript
 * Sistema POS Restaurante
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Validar formulario de tasa
    const formTasa = document.getElementById('formTasa');
    if (formTasa) {
        formTasa.addEventListener('submit', function(e) {
            const tasaInput = formTasa.querySelector('input[name="tasa"]');
            const tasa = parseFloat(tasaInput.value.replace(',', '.'));
            
            if (isNaN(tasa) || tasa <= 0) {
                e.preventDefault();
                alert('Ingrese una tasa válida mayor a 0');
                return false;
            }
            
            if (tasa < 0.01 || tasa > 9999.99) {
                e.preventDefault();
                alert('La tasa debe estar entre 0.01 y 9999.99');
                return false;
            }
            
            // Redondear a 2 decimales
            tasaInput.value = Math.round(tasa * 100) / 100;
            
            if (!confirm('¿Está seguro de cambiar la tasa de cambio?')) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Validar formulario de datos del restaurante
    const formRestaurante = document.getElementById('formRestaurante');
    if (formRestaurante) {
        formRestaurante.addEventListener('submit', function(e) {
            const nombre = formRestaurante.querySelector('input[name="nombre"]');
            
            if (nombre.value.trim().length < 3) {
                e.preventDefault();
                alert('El nombre debe tener al menos 3 caracteres');
                nombre.focus();
                return false;
            }
            
            const rif = formRestaurante.querySelector('input[name="rif"]');
            if (rif.value.trim()) {
                const rifPattern = /^[JVEPGDC]-[0-9]{6,8}-[0-9]$/;
                if (!rifPattern.test(rif.value.trim().toUpperCase())) {
                    e.preventDefault();
                    alert('Formato de RIF inválido. Ejemplo: J-12345678-9');
                    rif.focus();
                    return false;
                }
            }
            
            const email = formRestaurante.querySelector('input[name="email"]');
            if (email.value.trim()) {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(email.value.trim())) {
                    e.preventDefault();
                    alert('Ingrese un email válido');
                    email.focus();
                    return false;
                }
            }
            
            const logo = formRestaurante.querySelector('input[name="logo"]');
            if (logo.files.length > 0) {
                const file = logo.files[0];
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('El archivo no puede superar 2MB');
                    return false;
                }
                
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    alert('Solo se permiten archivos PNG o JPG');
                    return false;
                }
            }
        });
    }
    
    // Validar formulario de configuración del sistema
    const formSistema = document.getElementById('formSistema');
    if (formSistema) {
        formSistema.addEventListener('submit', function(e) {
            const timeout = formSistema.querySelector('input[name="timeout_sesion"]');
            const timeoutVal = parseInt(timeout.value);
            
            if (timeoutVal < 5 || timeoutVal > 120) {
                e.preventDefault();
                alert('El timeout debe estar entre 5 y 120 minutos');
                timeout.focus();
                return false;
            }
        });
    }
    
    // Preview del logo
    const logoInput = document.querySelector('input[name="logo"]');
    if (logoInput) {
        logoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Aquí podrías actualizar un preview de imagen
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

// Función global para actualizar tasa desde cualquier página
function mostrarModalTasa() {
    const modal = new bootstrap.Modal(document.getElementById('modalTasa'));
    modal.show();
}
