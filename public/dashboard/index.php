<?php
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/config/config.php';

if (!isLoggedIn()) {
    redireccionar('/restaurant-pos/public/index.php');
}

$db = getDB();
$tasa = getTasaCambio();

$ventasHoy = $db->prepare("
    SELECT COALESCE(SUM(total_bs), 0) as total_bs, COALESCE(SUM(total_usd), 0) as total_usd, COUNT(*) as cantidad
    FROM pedidos 
    WHERE DATE(fecha) = CURDATE() AND estado = 'pagado'
");
$ventasHoy->execute();
$ventas = $ventasHoy->fetch();

$productosStock = $db->query("
    SELECT COUNT(*) as total 
    FROM productos 
    WHERE tipo_producto IN ('terminado', 'materia_prima') 
    AND stock_actual <= stock_minimo 
    AND estado = 'activo'
")->fetch();

$pedidosPendientes = $db->query("
    SELECT COUNT(*) as total 
    FROM pedidos 
    WHERE estado IN ('pendiente', 'preparando')
")->fetch();

$mesasOcupadas = $db->query("
    SELECT COUNT(*) as total 
    FROM mesas 
    WHERE estado = 'ocupada'
")->fetch();

$mesasDisponibles = $db->query("
    SELECT COUNT(*) as total 
    FROM mesas 
    WHERE estado = 'disponible'
")->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1e3a5f;
            --secondary: #2d5a87;
            --accent: #ff6b35;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: var(--primary);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: var(--secondary);
            color: white;
        }
        .stat-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .bg-primary-custom { background: var(--primary); }
        .text-accent { color: var(--accent); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse show" id="sidebarMenu">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <i class="bi bi-shop" style="font-size: 2rem; color: white;"></i>
                        <h5 class="text-white mt-2"><?php echo APP_NAME; ?></h5>
                    </div>
                    <hr class="border-light">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="/restaurant-pos/public/dashboard/index.php">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/restaurant-pos/public/pedidos/index.php">
                                <i class="bi bi-receipt me-2"></i>Pedidos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/restaurant-pos/public/productos/index.php">
                                <i class="bi bi-box-seam me-2"></i>Productos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/restaurant-pos/public/mesas/index.php">
                                <i class="bi bi-grid-3x3-gap me-2"></i>Mesas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/restaurant-pos/public/inventario/index.php">
                                <i class="bi bi-archive me-2"></i>Inventario
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/restaurant-pos/public/reportes/index.php">
                                <i class="bi bi-graph-up me-2"></i>Reportes
                            </a>
                        </li>
                        <?php if (verificarPermiso('configuracion')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/restaurant-pos/public/configuracion/index.php">
                                <i class="bi bi-gear me-2"></i>Configuración
                            </a>
                        </li>
                        <?php endif; ?>
                        <hr class="border-light">
                        <li class="nav-item">
                            <a class="nav-link" href="/restaurant-pos/app/controllers/auth/logout.php">
                                <i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h4 class="mb-0">Dashboard</h4>
                        <small class="text-muted">Bienvenido, <?php echo $_SESSION['usuario']; ?></small>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="badge bg-warning text-dark fs-6">
                            <i class="bi bi-currency-exchange me-1"></i>
                            Tasa: <?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$
                        </div>
                        <button class="btn btn-sm btn-outline-secondary d-md-none" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                            <i class="bi bi-list"></i>
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white-50">Ventas de Hoy</h6>
                                        <h3 class="mb-0"><?php echo number_format($ventas['total_bs'], 2, ',', '.'); ?></h3>
                                        <small class="text-white-50">Bs (<?php echo $ventas['total_usd']; ?>$)</small>
                                    </div>
                                    <i class="bi bi-cash-stack" style="font-size: 2.5rem; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white-50">Pedidos Activos</h6>
                                        <h3 class="mb-0"><?php echo $pedidosPendientes['total']; ?></h3>
                                        <small class="text-white-50">pendientes</small>
                                    </div>
                                    <i class="bi bi-receipt-cutoff" style="font-size: 2.5rem; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="card stat-card bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-dark">Stock Bajo</h6>
                                        <h3 class="mb-0"><?php echo $productosStock['total']; ?></h3>
                                        <small class="text-dark">productos</small>
                                    </div>
                                    <i class="bi bi-exclamation-triangle" style="font-size: 2.5rem; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white-50">Mesas</h6>
                                        <h3 class="mb-0"><?php echo $mesasOcupadas['total']; ?>/<?php echo $mesasOcupadas['total'] + $mesasDisponibles['total']; ?></h3>
                                        <small class="text-white-50">ocupadas</small>
                                    </div>
                                    <i class="bi bi-grid-3x3-gap" style="font-size: 2.5rem; opacity: 0.5;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Acciones Rápidas</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="/restaurant-pos/public/pedidos/nuevo.php" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-2"></i>Nuevo Pedido
                                    </a>
                                    <a href="/restaurant-pos/public/inventario/entrada.php" class="btn btn-success">
                                        <i class="bi bi-arrow-down-circle me-2"></i>Entrada de Inventario
                                    </a>
                                    <a href="/restaurant-pos/public/configuracion/tasa.php" class="btn btn-warning">
                                        <i class="bi bi-currency-exchange me-2"></i>Actualizar Tasa
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-exclamation-circle me-2"></i>Productos con Stock Bajo</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $stockBajo = $db->query("
                                    SELECT nombre, stock_actual, stock_minimo, unidad_medida 
                                    FROM productos 
                                    WHERE tipo_producto IN ('terminado', 'materia_prima') 
                                    AND stock_actual <= stock_minimo 
                                    AND estado = 'activo'
                                    LIMIT 5
                                ")->fetchAll();
                                
                                if (count($stockBajo) > 0): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($stockBajo as $p): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo $p['nombre']; ?>
                                            <span class="badge bg-danger"><?php echo $p['stock_actual']; ?> <?php echo $p['unidad_medida']; ?></span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No hay productos con stock bajo</p>
                                <?php endif; ?>
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
