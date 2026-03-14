<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';

$mensaje = '';
$tipo_alerta = '';

if (verificarSesion()) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = procesarLogin();
    
    if ($resultado['success']) {
        header('Location: ' . $resultado['redirect']);
        exit;
    } else {
        $mensaje = $resultado['error'];
        $tipo_alerta = 'danger';
    }
}

$db = getDB();
$stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_cambio_dia'");
$stmt->execute();
$tasa = $stmt->fetch()['valor'] ?? '0';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema POS Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-container">
                    <i class="bi bi-shop"></i>
                </div>
                <h4>Restaurante POS</h4>
                <p class="text-muted">Sistema de Gestión</p>
            </div>
            
            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm" class="login-form">
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="usuario" name="usuario" 
                               placeholder="Ingrese su usuario" required autocomplete="username"
                               minlength="4" maxlength="50">
                    </div>
                    <div class="invalid-feedback" id="usuarioError"></div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Ingrese su contraseña" required autocomplete="current-password"
                               minlength="6">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye-slash" id="eyeIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="passwordError"></div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="recordar" name="recordar">
                    <label class="form-check-label" for="recordar">Recordar sesión (8 horas)</label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 btn-login" id="btnLogin">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="spinner"></span>
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    <span>Iniciar Sesión</span>
                </button>
            </form>
            
            <div class="login-footer">
                <div class="tasa-cambio">
                    <i class="bi bi-currency-exchange"></i>
                    <span>Tasa del día: <strong><?php echo number_format(floatval($tasa), 2, ',', '.'); ?> Bs/$</strong></span>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/login.js"></script>
</body>
</html>
