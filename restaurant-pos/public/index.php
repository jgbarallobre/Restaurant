<?php
/**
 * Index - Punto de entrada del Sistema POS
 */
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/config/config.php';

if (isLoggedIn()) {
    redireccionar('/dashboard/index.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1e3a5f;
            --secondary: #2d5a87;
            --accent: #ff6b35;
            --light: #f8f9fa;
            --dark: #212529;
        }
        body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background: var(--secondary);
            border-color: var(--secondary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5 col-lg-4">
                <div class="login-card p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-shop" style="font-size: 3rem; color: var(--primary);"></i>
                        <h4 class="mt-2 fw-bold"><?php echo APP_NAME; ?></h4>
                        <p class="text-muted">Ingrese sus credenciales</p>
                    </div>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <form action="/restaurant-pos/app/controllers/auth/login.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="usuario" class="form-control" required autofocus>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">Tasa del día: <?php echo formatearPrecio(getTasaCambio(), 1, false); ?>/$</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
