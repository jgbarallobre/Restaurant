/*
 * Main JavaScript
 * Sistema POS Restaurante
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            sidebar.classList.toggle('show');
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
    
    // Confirm dialogs
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            const message = el.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Time remaining session
    const tiempoRestante = document.getElementById('tiempoRestante');
    if (tiempoRestante) {
        setInterval(function() {
            let segundos = parseInt(tiempoRestante.dataset.segundos || '0');
            if (segundos > 0) {
                segundos--;
                tiempoRestante.dataset.segundos = segundos;
                const minutos = Math.floor(segundos / 60);
                const seg = segundos % 60;
                tiempoRestante.textContent = `${minutos}:${seg.toString().padStart(2, '0')}`;
            } else {
                // Session expired
                window.location.href = 'logout.php?expired=1';
            }
        }, 1000);
    }
});

// Toggle password function (global)
function togglePassword(inputId, iconId) {
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
