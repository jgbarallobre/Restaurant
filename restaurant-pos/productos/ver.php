<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/tasa.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inventario.php';

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

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php?msg=ID inválido&tipo=danger');
    exit;
}

$producto = obtenerProducto($id);
if (!$producto) {
    header('Location: index.php?msg=Producto no encontrado&tipo=danger');
    exit;
}

$receta = ($producto['tipo_producto'] === 'compuesto') ? obtenerReceta($id) : [];

// Calculate recipe cost and margin for composite products
$costoReceta = 0;
$margen = 0;
$margenPorcentaje = 0;

if ($producto['tipo_producto'] === 'compuesto' && !empty($receta)) {
    foreach ($receta as $ing) {
        $costoReceta += floatval($ing['cantidad_requerida']) * floatval($ing['precio_base_usd']);
    }
    
    $precioVenta = floatval($producto['precio_base_usd']);
    $margen = $precioVenta - $costoReceta;
    $margenPorcentaje = ($precioVenta > 0) ? ($margen / $precioVenta) * 100 : 0;
}

// Stock disponible para productos compuestos
$stockDisponible = 0;
if ($producto['tipo_producto'] === 'compuesto') {
    $stockDisponible = stockDisponibleCompuesto($id);
}

// Get recent movements
$movimientos = obtenerMovimientos($id, null, null, 10, 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Producto - Sistema POS</title>
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
                <li class="nav-item mt-auto"><a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-left"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </nav>

        <div class="main-content">
            <header class="header">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
                            <li class="breadcrumb-item active">Detalle de Producto</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="tasa-badge bg-success">
                        <i class="bi bi-currency-exchange"></i>
                        <span><?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$</span>
                    </div>
                </div>
            </header>

            <main class="content">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="bi bi-info-circle me-2"></i>Detalle del Producto</h4>
                        <div class="btn-group">
                            <?php if (tienePermiso('editar_productos')): ?>
                            <a href="editar.php?id=<?php echo $id; ?>" class="btn btn-outline-primary">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                            <?php endif; ?>
                            <?php if (tienePermiso('eliminar_productos')): ?>
                            <a href="acciones.php?accion=eliminar&id=<?php echo $id; ?>" 
                               class="btn btn-outline-danger" 
                               onclick="return confirm('¿Está seguro de eliminar este producto?')">
                                <i class="bi bi-trash"></i> Eliminar
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Main Product Info -->
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-tag"></i> Información del Producto</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($producto['nombre']); ?></p>
                                            <p><strong>Descripción:</strong> <?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                                            <p><strong>Tipo:</strong> 
                                                <span class="badge bg-<?php 
                                                echo ($producto['tipo_producto'] === 'materia_prima') ? 'secondary' : 
                                                    (($producto['tipo_producto'] === 'terminado') ? 'success' : 'info'); ?>">
                                                    <?php echo ucfirst($producto['tipo_producto']); ?>
                                                </span>
                                            </p>
                                            <p><strong>Categoría:</strong> <?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></p>
                                            <p><strong>Estado:</strong> 
                                                <span class="badge bg-<?php echo $producto['estado'] === 'activo' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($producto['estado']); ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Unidad de Medida:</strong> <?php echo htmlspecialchars($producto['unidad_medida']); ?></p>
                                            <?php if ($producto['tipo_producto'] !== 'compuesto'): ?>
                                            <p><strong>Stock Actual:</strong> 
                                                <span class="badge bg-<?php 
                                                echo ($producto['stock_actual'] <= 0) ? 'danger' : 
                                                    (($producto['stock_actual'] <= $producto['stock_minimo']) ? 'warning' : 'success'); ?>">
                                                    <?php echo number_format($producto['stock_actual'], 2); ?> <?php echo $producto['unidad_medida']; ?>
                                                </span>
                                            </p>
                                            <p><strong>Stock Mínimo:</strong> <?php echo number_format($producto['stock_minimo'], 2); ?> <?php echo $producto['unidad_medida']; ?></p>
                                            <?php endif; ?>
                                            <p><strong>Precio de Venta:</strong> <?php echo formatoPrecio($producto['precio_base_usd']); ?></p>
                                            <?php if ($producto['tiempo_preparacion']): ?>
                                            <p><strong>Tiempo de Preparación:</strong> <?php echo $producto['tiempo_preparacion']; ?> minutos</p>
                                            <?php endif; ?>
                                            <?php if ($producto['codigo_barras']): ?>
                                            <p><strong>Código de Barras:</strong> <?php echo htmlspecialchars($producto['codigo_barras']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($producto['imagen'])): ?>
                                    <div class="text-center mt-4">
                                        <img src="../assets/uploads/productos/<?php echo $producto['imagen']; ?>" 
                                             alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                             class="img-fluid rounded" 
                                             style="max-height: 300px;">
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Recipe / Details -->
                        <div class="col-md-4">
                            <?php if ($producto['tipo_producto'] === 'compuesto'): ?>
                            <div class="card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="bi bi-book me-2"></i>Receta</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($receta)): ?>
                                    <p class="text-muted">Esta receta no tiene ingredientes definidos.</p>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Ingrediente</th>
                                                    <th>Cantidad</th>
                                                    <th>Unidad</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($receta as $ing): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($ing['materia_prima_nombre']); ?></td>
                                                    <td><?php echo number_format($ing['cantidad_requerida'], 3); ?></td>
                                                    <td><?php echo htmlspecialchars($ing['unidad_medida_receta']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <h6>Análisis de Costo</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <p><strong>Costo Ingredientes:</strong><br><?php echo formatoBs(usdToBs($costoReceta)); ?></p>
                                            </div>
                                            <div class="col-6">
                                                <p><strong>Margen de Ganancia:</strong><br>
                                                    <?php echo formatoBs(usdToBs($margen)); ?> 
                                                    (<span class="text-success"><?php echo number_format($margenPorcentaje, 1); ?>%</span>)
                                                </p>
                                            </div>
                                        </div>
                                        <p class="mt-2"><strong>Unidades disponibles con stock actual:</strong> 
                                            <span class="badge bg-<?php echo $stockDisponible > 0 ? 'success' : 'warning'; ?>">
                                                <?php echo $stockDisponible; ?>
                                            </span>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Inventory Movements -->
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="bi bi-arrow-clockwise me-2"></i>Movimientos Recientes</h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (empty($movimientos)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-clock-history"></i>
                                        <p class="mb-0">No hay movimientos registrados</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($movimientos as $mov): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo ucfirst($mov['tipo_movimiento']); ?></strong>
                                                    <br><small class="text-muted"><?php echo $mov['observaciones'] ?? ''; ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-<?php 
                                                    echo in_array($mov['tipo_movimiento'], ['entrada']) ? 'success' : 'warning'; ?>">
                                                        <?php echo number_format($mov['cantidad'], 2); ?> <?php echo htmlspecialchars($mov['unidad_medida']); ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m H:i', strtotime($mov['fecha'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
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
