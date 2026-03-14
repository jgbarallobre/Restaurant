<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/tasa.php';

if (!verificarSesion()) {
    header('Location: ../login.php');
    exit;
}

requirePermiso('ver_productos', '../dashboard.php');

$db = getDB();
$tasa = obtenerTasaActual();
$rolId = getRolId();
$rolNombre = getRolNombre();
$menu = generarMenu();

// Get product if ID provided
$producto_id = intval($_GET['id'] ?? 0);
$producto = null;
$movimientos = [];

if ($producto_id > 0) {
    $producto = obtenerProducto($producto_id);
    if (!$producto) {
        header('Location: index.php?msg=Producto no encontrado&tipo=danger');
        exit;
    }
    $movimientos = obtenerMovimientos($producto_id, null, null, 50, 0);
}

// Get filters for listing all movements
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$tipo_movimiento = $_GET['tipo_movimiento'] ?? '';

if (empty($producto_id)) {
    // Show all movements with filters
    $movimientos = obtenerMovimientos(null, $fecha_inicio, $fecha_fin, 50, 0);
    // We'll need to adjust obtenerMovimientos to accept filters, but for now ignore
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Inventario - Sistema POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <i class="bi bi-shop"></i>
                <span>Restaurante POS</span>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-box-seam"></i><span>Productos</span></a></li>
                <li class="nav-item"><a class="nav-link active" href="inventario.php"><i class="bi bi-archive"></i><span>Inventario</span></a></li>
                <li class="nav-item mt-auto"><a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-left"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </nav>

        <div class="main-content">
            <header class="header">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
                    <h4 class="mb-0">Movimientos de Inventario</h4>
                </div>
            </header>

            <main class="content">
                <div class="container-fluid">
                    <?php if ($producto_id > 0): ?>
                    <div class="mb-4">
                        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a productos</a>
                        <h4 class="ms-3"><?php echo htmlspecialchars($producto['nombre']); ?> - Movimientos</h4>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($producto_id)): ?>
                    <!-- Filters for all movements -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filtros</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3">
                                    <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $fecha_inicio; ?>">
                                </div>
                                <div class="col-md-3">
                                    <input type="date" name="fecha_fin" class="form-control" value="<?php echo $fecha_fin; ?>">
                                </div>
                                <div class="col-md-3">
                                    <select name="tipo_movimiento" class="form-select">
                                        <option value="">Todos los tipos</option>
                                        <option value="entrada" <?php echo $tipo_movimiento === 'entrada' ? 'selected' : ''; ?>>Entrada</option>
                                        <option value="salida_venta" <?php echo $tipo_movimiento === 'salida_venta' ? 'selected' : ''; ?>>Salida por Venta</option>
                                        <option value="salida_receta" <?php echo $tipo_movimiento === 'salida_receta' ? 'selected' : ''; ?>>Salida por Receta</option>
                                        <option value="merma" <?php echo $tipo_movimiento === 'merma' ? 'selected' : ''; ?>>Merma</option>
                                        <option value="ajuste" <?php echo $tipo_movimiento === 'ajuste' ? 'selected' : ''; ?>>Ajuste</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                                    <a href="inventario.php" class="btn btn-outline-secondary w-100 mt-2">Limpiar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Movimientos Table -->
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Producto</th>
                                            <th>Tipo</th>
                                            <th class="text-end">Cantidad</th>
                                            <th class="text-end">Stock Antes</th>
                                            <th class="text-end">Stock Después</th>
                                            <th>Usuario</th>
                                            <th>Referencia</th>
                                            <th>Motivo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($movimientos)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="bi bi-archive"></i>
                                                No hay movimientos de inventario
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($movimientos as $mov): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha'])); ?></td>
                                            <td><?php echo htmlspecialchars($mov['producto_nombre']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                echo in_array($mov['tipo_movimiento'], ['entrada']) ? 'success' : 
                                                    (in_array($mov['tipo_movimiento'], ['salida_venta', 'salida_receta']) ? 'danger' : 
                                                    (in_array($mov['tipo_movimiento'], ['merma', 'ajuste']) ? 'warning' : 'secondary')); ?>">
                                                    <?php echo ucfirst($mov['tipo_movimiento']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end"><?php echo number_format($mov['cantidad'], 3); ?> <?php echo htmlspecialchars($mov['unidad_medida']); ?></td>
                                            <td class="text-end"><?php echo number_format($mov['saldo_anterior'], 2); ?> <?php echo htmlspecialchars($mov['unidad_medida']); ?></td>
                                            <td class="text-end"><?php echo number_format($mov['saldo_nuevo'], 2); ?> <?php echo htmlspecialchars($mov['unidad_medida']); ?></td>
                                            <td><?php echo htmlspecialchars($mov['usuario_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($mov['referencia'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($mov['observaciones'] ?? '-'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
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
