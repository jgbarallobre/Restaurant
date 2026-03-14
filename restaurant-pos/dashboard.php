<?php
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

if (!verificarSesion()) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$rolId = getRolId();
$rolNombre = getRolNombre();

// Obtener tasa de cambio
$stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_cambio_dia'");
$stmt->execute();
$tasa = floatval($stmt->fetch()['valor'] ?? 0);

$menu = generarMenu();

// Obtener estadísticas según el rol
$estadisticas = [];

if (tienePermiso('ver_reportes')) {
    // Ventas del día
    $stmt = $db->query("
        SELECT COALESCE(SUM(total_bs), 0) as total_bs, COALESCE(SUM(total_usd), 0) as total_usd, COUNT(*) as cantidad
        FROM pedidos WHERE DATE(fecha) = CURDATE() AND estado = 'pagado'
    ");
    $estadisticas['ventas_hoy'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ventas del mes
    $stmt = $db->query("
        SELECT COALESCE(SUM(total_bs), 0) as total_bs, COALESCE(SUM(total_usd), 0) as total_usd, COUNT(*) as cantidad
        FROM pedidos WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado = 'pagado'
    ");
    $estadisticas['ventas_mes'] = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (tienePermiso('ver_inventario')) {
    // Productos con stock bajo
    $stmt = $db->query("
        SELECT COUNT(*) as total FROM productos 
        WHERE tipo_producto IN ('terminado', 'materia_prima') 
        AND stock_actual <= stock_minimo AND estado = 'activo'
    ");
    $estadisticas['stock_bajo'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

if (tienePermiso('ver_pedidos')) {
    // Pedidos activos
    $stmt = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado IN ('pendiente', 'preparando')");
    $estadisticas['pedidos_activos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pedidos pendientes para cocina
    $stmt = $db->query("
        SELECT p.id, p.fecha, m.numero as mesa_numero, pr.nombre as producto, dp.cantidad, p.estado
        FROM pedidos p
        LEFT JOIN mesas m ON p.mesa_id = m.id
        JOIN detalle_pedido dp ON p.id = dp.pedido_id
        JOIN productos pr ON dp.producto_id = pr.id
        WHERE p.estado IN ('pendiente', 'preparando')
        ORDER BY p.fecha ASC LIMIT 10
    ");
    $estadisticas['pedidos_cocina'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (tienePermiso('ver_mesas')) {
    // Estado de mesas
    $stmt = $db->query("
        SELECT 
            SUM(CASE WHEN estado = 'disponible' THEN 1 ELSE 0 END) as disponibles,
            SUM(CASE WHEN estado = 'ocupada' THEN 1 ELSE 0 END) as ocupadas,
            COUNT(*) as total
        FROM mesas WHERE estado != 'inactiva'
    ");
    $estadisticas['mesas'] = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (tienePermiso('cobrar_pedidos')) {
    // Pedidos listos para cobrar
    $stmt = $db->query("
        SELECT p.id, p.total_bs, p.total_usd, p.tipo_pago, m.numero as mesa_numero, p.fecha
        FROM pedidos p
        LEFT JOIN mesas m ON p.mesa_id = m.id
        WHERE p.estado = 'entregado'
        ORDER BY p.fecha DESC LIMIT 10
    ");
    $estadisticas['pedidos_cobrar'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRolNombre() {
    $roles = [
        1 => 'Administrador',
        2 => 'Admin',
        3 => 'Cajero',
        4 => 'Cocinero',
        5 => 'Mesonero'
    ];
    return $roles[$_SESSION['rol_id']] ?? 'Usuario';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema POS Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <i class="bi bi-shop"></i>
                <span>Restaurante POS</span>
            </div>
            
            <ul class="nav flex-column">
                <?php foreach ($menu as $key => $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($key === 'dashboard' || (empty($_GET['seccion']) && $key === 'pedidos')) ? 'active' : ''; ?>" 
                       href="<?php echo $item['url']; ?>">
                        <i class="bi <?php echo $item['icono']; ?>"></i>
                        <span><?php echo $item['titulo']; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
                
                <li class="nav-item mt-auto">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-left"></i>
                        <span>Cerrar Sesión</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="d-flex align-items-center">
                    <button class="btn btn-toggle-sidebar me-3" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Inicio</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                        </ol>
                    </nav>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <div class="tasa-badge">
                        <i class="bi bi-currency-exchange"></i>
                        <span><?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$</span>
                    </div>
                    
                    <div class="user-info dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo $_SESSION['usuario_nombre']; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text">
                                <small class="text-muted">Rol: <?php echo getRolNombre(); ?></small>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión
                            </a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="content">
                <div class="container-fluid">
                    <!-- Título -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">
                            <?php 
                            $titulos = [
                                1 => 'Panel de Administración',
                                2 => 'Panel de Administración',
                                3 => 'Caja',
                                4 => 'Cocina',
                                5 => 'Mesas'
                            ];
                            echo $titulos[$rolId] ?? 'Dashboard';
                            ?>
                        </h4>
                    </div>

                    <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- DASHBOARD ADMINISTRADOR -->
                    <?php if ($rolId == 1 || $rolId == 2): ?>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card stat-primary">
                                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                                <div class="stat-content">
                                    <h6>Ventas de Hoy</h6>
                                    <h3><?php echo number_format($estadisticas['ventas_hoy']['total_bs'] ?? 0, 2, ',', '.'); ?></h3>
                                    <small><?php echo $estadisticas['ventas_hoy']['total_usd'] ?? 0; ?> $ • <?php echo $estadisticas['ventas_hoy']['cantidad'] ?? 0; ?> pedidos</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card stat-success">
                                <div class="stat-icon"><i class="bi bi-calendar-month"></i></div>
                                <div class="stat-content">
                                    <h6>Ventas del Mes</h6>
                                    <h3><?php echo number_format($estadisticas['ventas_mes']['total_bs'] ?? 0, 0, ',', '.'); ?></h3>
                                    <small><?php echo $estadisticas['ventas_mes']['cantidad'] ?? 0; ?> pedidos</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card stat-warning">
                                <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                                <div class="stat-content">
                                    <h6>Stock Bajo</h6>
                                    <h3><?php echo $estadisticas['stock_bajo'] ?? 0; ?></h3>
                                    <small>productos</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card stat-info">
                                <div class="stat-icon"><i class="bi bi-grid-3x3-gap"></i></div>
                                <div class="stat-content">
                                    <h6>Mesas</h6>
                                    <h3><?php echo ($estadisticas['mesas']['ocupadas'] ?? 0) . '/' . ($estadisticas['mesas']['total'] ?? 0); ?></h3>
                                    <small><?php echo $estadisticas['mesas']['disponibles'] ?? 0; ?> disponibles</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Acciones Rápidas</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <?php if (tienePermiso('crear_pedidos')): ?>
                                        <a href="pedidos/nuevo.php" class="btn btn-primary btn-action">
                                            <i class="bi bi-plus-circle me-2"></i>Nuevo Pedido
                                        </a>
                                        <?php endif; ?>
                                        <?php if (tienePermiso('gestionar_inventario')): ?>
                                        <a href="inventario/" class="btn btn-success btn-action">
                                            <i class="bi bi-arrow-down-circle me-2"></i>Entrada de Inventario
                                        </a>
                                        <?php endif; ?>
                                        <?php if (tienePermiso('modificar_tasa')): ?>
                                        <a href="configuracion/" class="btn btn-warning btn-action">
                                            <i class="bi bi-currency-exchange me-2"></i>Actualizar Tasa
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-exclamation-circle me-2"></i>Productos con Stock Bajo</h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php
                                    $stockBajo = $db->query("
                                        SELECT nombre, stock_actual, stock_minimo, unidad_medida 
                                        FROM productos 
                                        WHERE tipo_producto IN ('terminado', 'materia_prima') 
                                        AND stock_actual <= stock_minimo AND estado = 'activo'
                                        LIMIT 5
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php if (count($stockBajo) > 0): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($stockBajo as $p): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($p['nombre']); ?>
                                            <span class="badge bg-danger"><?php echo $p['stock_actual']; ?> <?php echo $p['unidad_medida']; ?></span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                                        <p class="mb-0">No hay productos con stock bajo</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- DASHBOARD COCINA -->
                    <?php if ($rolId == 4): ?>
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-warning">
                                    <h5 class="mb-0"><i class="bi bi-fire me-2"></i>Pedidos Pendientes</h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (count($estadisticas['pedidos_cocina']) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Pedido</th>
                                                    <th>Mesa</th>
                                                    <th>Producto</th>
                                                    <th>Cantidad</th>
                                                    <th>Estado</th>
                                                    <th>Hora</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($estadisticas['pedidos_cocina'] as $p): ?>
                                                <tr>
                                                    <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                                    <td><?php echo $p['mesa_numero'] ? 'Mesa ' . $p['mesa_numero'] : 'Llevar'; ?></td>
                                                    <td><?php echo htmlspecialchars($p['producto']); ?></td>
                                                    <td><?php echo $p['cantidad']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $p['estado'] == 'pendiente' ? 'warning' : 'info'; ?>">
                                                            <?php echo ucfirst($p['estado']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('H:i', strtotime($p['fecha'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">No hay pedidos pendientes</h5>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- DASHBOARD CAJERO -->
                    <?php if ($rolId == 3): ?>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="stat-card stat-success">
                                <div class="stat-icon"><i class="bi bi-cash"></i></div>
                                <div class="stat-content">
                                    <h6>Ventas del Día</h6>
                                    <h3><?php echo number_format($estadisticas['ventas_hoy']['total_bs'] ?? 0, 2, ',', '.'); ?> Bs</h3>
                                    <small><?php echo $estadisticas['ventas_hoy']['total_usd'] ?? 0; ?> $</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card stat-primary">
                                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
                                <div class="stat-content">
                                    <h6>Pedidos por Cobrar</h6>
                                    <h3><?php echo count($estadisticas['pedidos_cobrar'] ?? []); ?></h3>
                                    <small>entregados</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="bi bi-cash me-2"></i>Pedidos Listos para Cobrar</h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (count($estadisticas['pedidos_cobrar']) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Pedido</th>
                                                    <th>Mesa</th>
                                                    <th>Total</th>
                                                    <th>Tipo Pago</th>
                                                    <th>Hora</th>
                                                    <th>Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($estadisticas['pedidos_cobrar'] as $p): ?>
                                                <tr>
                                                    <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                                    <td><?php echo $p['mesa_numero'] ? 'Mesa ' . $p['mesa_numero'] : 'Llevar'; ?></td>
                                                    <td><strong><?php echo number_format($p['total_bs'], 2, ',', '.'); ?> Bs</strong></td>
                                                    <td><?php echo ucfirst($p['tipo_pago']); ?></td>
                                                    <td><?php echo date('H:i', strtotime($p['fecha'])); ?></td>
                                                    <td>
                                                        <a href="pedidos/ver.php?id=<?php echo $p['id']; ?>" class="btn btn-success btn-sm">
                                                            <i class="bi bi-check-lg"></i> Cobrar
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                                        <h5 class="mt-3">No hay pedidos por cobrar</h5>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- DASHBOARD MESONERO -->
                    <?php if ($rolId == 5): ?>
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="stat-card stat-success">
                                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                                <div class="stat-content">
                                    <h6>Mesas Disponibles</h6>
                                    <h3><?php echo $estadisticas['mesas']['disponibles'] ?? 0; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card stat-warning">
                                <div class="stat-icon"><i class="bi bi-people"></i></div>
                                <div class="stat-content">
                                    <h6>Mesas Ocupadas</h6>
                                    <h3><?php echo $estadisticas['mesas']['ocupadas'] ?? 0; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card stat-primary">
                                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
                                <div class="stat-content">
                                    <h6>Pedidos Activos</h6>
                                    <h3><?php echo $estadisticas['pedidos_activos'] ?? 0; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12">
                            <div class="d-grid gap-2">
                                <a href="pedidos/nuevo.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-plus-circle me-2"></i>Nuevo Pedido
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
