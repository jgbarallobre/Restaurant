/*
 * Productos JavaScript
 * Sistema POS Restaurante
 */

document.addEventListener('DOMContentOLD', function() {
    
    // Validar formulario de crear/editar producto
    const formProducto = document.getElementById('formProducto');
    if (formProducto) {
        formProducto.addEventListener('submit', function(e) {
            const nombre = formProducto.querySelector('input[name="nombre"]');
            const precio = formProducto.querySelector('input[name="precio_usd"]');
            const stockMinimo = formProducto.querySelector('input[name="stock_minimo"]');
            const stockActual = formProducto.querySelector('input[name="stock_actual"]');
            const tipo = formProducto.querySelector('select[name="tipo_producto"]');
            
            let valid = true;
            
            // Validar nombre
            if (nombre.value.trim().length < 3) {
                showError(nombre, 'El nombre debe tener al menos 3 caracteres');
                valid = false;
            } else {
                clearError(nombre);
            }
            
            // Validar precio
            const precioVal = parseFloat(precio.value);
            if (isNaN(precioVal) || precioVal < 0.01) {
                showError(precio, 'El precio debe ser mayor a 0.00');
                valid = false;
            } else {
                clearError(precio);
            }
            
            // Validar stock mínimo
            const stockMinVal = parseFloat(stockMinimo.value);
            if (isNaN(stockMinVal) || stockMinVal < 0) {
                showError(stockMinimo, 'El stock mínimo debe ser un número positivo');
                valid = false;
            } else {
                clearError(stockMinimo);
            }
            
            // Validar stock actual si es materia prima o terminado
            const tipoValor = tipo.value;
            if (tipoValor === 'materia_prima' || tipoValor === 'terminado') {
                if (stockActual) {
                    const stockActVal = parseFloat(stockActual.value);
                    if (isNaN(stockActVal) || stockActVal < 0) {
                        showError(stockActual, 'El stock actual debe ser un número positivo');
                        valid = false;
                    } else {
                        clearError(stockActual);
                    }
                }
            }
            
            // Si es compuesto, validar que tenga al menos un ingrediente
            if (tipoValor === 'compuesto') {
                const ingredientes = formProducto.querySelectorAll('select[name="ingredientes[]"]');
                let tieneIngrediente = false;
                ingredientes.forEach(select => {
                    if (select.value) {
                        tieneIngrediente = true;
                    }
                });
                
                if (!tieneIngrediente) {
                    alert('Un producto compuesto debe tener al menos un ingrediente en su receta');
                    e.preventDefault();
                    return false;
                }
            }
            
            if (!valid) {
                e.preventDefault();
            }
        });
    }
    
    // Funciones de ayuda para validación
    function showError(input, message) {
        input.classList.add('is-invalid');
        const feedback = input.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = message;
            feedback.style.display = 'block';
        }
    }
    
    function clearError(input) {
        input.classList.remove('is-invalid');
        const feedback = input.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = '';
            feedback.style.display = 'none';
        }
    }
    
    // Preview de imagen
    const imagenInput = document.querySelector('input[name="imagen"]');
    if (imagenInput) {
        imagenInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validar tamaño
                const maxSize = 2 * 1024 * 1024; // 2MB
                if (file.size > maxSize) {
                    alert('La imagen no puede superar 2MB');
                    e.target.value = '';
                    return;
                }
                
                // Validar tipo
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Solo se permiten imágenes JPG o PNG');
                    e.target.value = '';
                    return;
                }
                
                // Mostrar preview
                const preview = document.getElementById('imagenPreview');
                if (preview) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        preview.src = event.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
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

// Función global para mostrar toast (si se implementa)
function showToast(message, type = 'info') {
    // Implementación simple con alert for now
    alert(message);
}
