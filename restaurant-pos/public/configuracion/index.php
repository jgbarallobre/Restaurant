<?php
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/config/config.php';

if (!isLoggedIn()) {
    redireccionar('/restaurant-pos/public/index.php');
}

if (!verificarPermiso('configuracion')) {
    $_SESSION['error'] = 'No tienes permiso para acceder a esta sección';
    redireccionar('/restaurant-pos/public/dashboard/index.php');
}

$db = getDB();
$tasaActual = getTasaCambio();

$tasasHistorico = $db->query("
    SELECT t.*, u.nombre as usuario_nombre
    FROM tasa_cambio_historico t
    JOIN usuarios u ON t.usuario_id = u.id
    ORDER BY t.fecha DESC
    LIMIT 10
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'actualizar_tasa') {
        $nuevaTasa = floatval(str_replace(',', '.', $_POST['tasa']));
        
        if ($nuevaTasa <= 0) {
            $_SESSION['error'] = 'La tasa debe ser mayor a 0';
        } else {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_cambio_dia'");
                $stmt->execute([$nuevaTasa]);
                
                $historial = $db->prepare("INSERT INTO tasa_cambio_historico (tasa, fecha, usuario_id) VALUES (?, CURDATE(), ?)");
                $historial->execute([$nuevaTasa, $_SESSION['usuario_id']]);
                
                $db->commit();
                logger("Tasa de cambio actualizada a {$nuevaTasa} por {$_SESSION['usuario']}");
                redireccionar('/restaurant-pos/public/dashboard/index.php');
                
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error'] = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --primary: #1e3a5f; --secondary: #2d5a87; --accent: #ff6b35; }
        .sidebar { min-height: 100vh; background: var(--primary); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 2px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: var(--secondary); color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse show">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <i class="bi bi-shop" style="font-size: 2rem; color: white;"></i>
                        <h5 class="text-white mt-2"><?php echo APP_NAME; ?></h5>
                    </div>
                    <hr class="border-light">
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/dashboard/index.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/pedidos/index.php"><i class="bi bi-receipt me-2"></i>Pedidos</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/productos/index.php"><i class="bi bi-box-seam me-2"></i>Productos</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/mesas/index.php"><i class="bi bi-grid-3x3-gap me-2"></i>Mesas</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/inventario/index.php"><i class="bi bi-archive me-2"></i>Inventario</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/reportes/index.php"><i class="bi bi-graph-up me-2"></i>Reportes</a></li>
                        <hr class="border-light">
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/app/controllers/auth/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div><h4 class="mb-0"><i class="bi bi-gear me-2"></i>Configuración</h4></div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0"><i class="bi bi-currency-exchange me-2"></i>Actualizar Tasa de Cambio</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <strong>Tasa actual:</strong> <?php echo number_format($tasaActual, 2, ',', '.'); ?> Bs/$
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="accion" value="actualizar_tasa">
                                    <div class="mb-3">
                                        <label class="form-label">Nueva Tasa (Bs por USD)</label>
                                        <input type="text" name="tasa" class="form-control" value="<?php echo $tasaActual; ?>" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-2"></i>Actualizar Tasa
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-white"><h5 class="mb-0">Historial de Tasas</h5></div>
                            <div class="card-body p-0">
                                <table class="table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Tasa</th>
                                            <th>Usuario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tasasHistorico as $t): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($t['fecha'])); ?></td>
                                            <td><strong><?php echo number_format($t['tasa'], 2, ',', '.'); ?></strong></td>
                                            <td><?php echo $t['usuario_nombre']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
