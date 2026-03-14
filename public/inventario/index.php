<?php
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/config/config.php';

if (!isLoggedIn()) {
    redireccionar('/restaurant-pos/public/index.php');
}

$db = getDB();
$tasa = getTasaCambio();

$inventario = $db->query("
    SELECT * FROM productos 
    WHERE tipo_producto IN ('materia_prima', 'terminado')
    AND estado = 'activo'
    ORDER BY nombre
")->fetchAll();

$movimientos = $db->query("
    SELECT m.*, p.nombre as producto_nombre, u.nombre as usuario_nombre
    FROM movimientos_inventario m
    JOIN productos p ON m.producto_id = p.id
    JOIN usuarios u ON m.usuario_id = u.id
    ORDER BY m.fecha DESC
    LIMIT 20
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $productoId = intval($_POST['producto_id']);
    $cantidad = floatval($_POST['cantidad']);
    $tipo = $_POST['tipo_movimiento'];
    $observaciones = sanitizar($_POST['observaciones'] ?? '');
    
    try {
        $db->beginTransaction();
        
        $producto = $db->prepare("SELECT * FROM productos WHERE id = ?")->execute([$productoId])->fetch();
        
        if ($tipo === 'entrada') {
            $nuevoStock = $producto['stock_actual'] + $cantidad;
            $tipoMov = 'entrada';
        } elseif ($tipo === 'merma') {
            $nuevoStock = max(0, $producto['stock_actual'] - $cantidad);
            $tipoMov = 'merma';
        } else {
            throw new Exception("Tipo de movimiento no válido");
        }
        
        $stmt = $db->prepare("UPDATE productos SET stock_actual = ? WHERE id = ?");
        $stmt->execute([$nuevoStock, $productoId]);
        
        $mov = $db->prepare("
            INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, saldo_anterior, saldo_nuevo, usuario_id, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $mov->execute([$productoId, $tipoMov, $cantidad, $producto['stock_actual'], $nuevoStock, $_SESSION['usuario_id'], $observaciones]);
        
        $db->commit();
        logger("Movimiento de inventario: {$tipoMov} - Producto: {$producto['nombre']} - Cantidad: {$cantidad}");
        redireccionar('/restaurant-pos/public/inventario/index.php');
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - <?php echo APP_NAME; ?></title>
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
                        <li class="nav-item"><a class="nav-link active" href="/restaurant-pos/public/inventario/index.php"><i class="bi bi-archive me-2"></i>Inventario</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/reportes/index.php"><i class="bi bi-graph-up me-2"></i>Reportes</a></li>
                        <hr class="border-light">
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/app/controllers/auth/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div><h4 class="mb-0"><i class="bi bi-archive me-2"></i>Inventario</h4></div>
                    <div class="badge bg-warning text-dark fs-6">Tasa: <?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$</div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Stock Actual -->
                    <div class="col-lg-7">
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Stock Actual</h5>
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalMovimiento">
                                    <i class="bi bi-plus-lg me-1"></i>Nuevo Movimiento
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Producto</th>
                                                <th>Tipo</th>
                                                <th class="text-center">Stock</th>
                                                <th>Mín</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inventario as $inv): 
                                                $stockBajo = $inv['stock_actual'] <= $inv['stock_minimo'];
                                            ?>
                                            <tr class="<?php echo $stockBajo ? 'table-warning' : ''; ?>">
                                                <td><?php echo $inv['nombre']; ?></td>
                                                <td><span class="badge bg-<?php echo $inv['tipo_producto'] === 'terminado' ? 'success' : 'secondary'; ?>"><?php echo $inv['tipo_producto']; ?></span></td>
                                                <td class="text-center"><strong><?php echo number_format($inv['stock_actual'], 2); ?></strong> <?php echo $inv['unidad_medida']; ?></td>
                                                <td class="text-center"><?php echo number_format($inv['stock_minimo'], 2); ?></td>
                                                <td>
                                                    <?php if ($stockBajo): ?>
                                                    <span class="badge bg-danger">Bajo</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-success">OK</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Movimientos Recientes -->
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header bg-white"><h5 class="mb-0">Movimientos Recientes</h5></div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($movimientos as $m): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo $m['producto_nombre']; ?></strong>
                                                <br><small class="text-muted"><?php echo $m['tipo_movimiento']; ?> • <?php echo $m['cantidad']; ?></small>
                                            </div>
                                            <span class="badge bg-<?php echo in_array($m['tipo_movimiento'], ['entrada']) ? 'success' : 'warning'; ?>">
                                                <?php echo number_format($m['saldo_nuevo'], 2); ?>
                                            </span>
                                        </div>
                                        <small class="text-muted"><?php echo date('d/m H:i', strtotime($m['fecha'])); ?> • <?php echo $m['usuario_nombre']; ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Movimiento -->
    <div class="modal fade" id="modalMovimiento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Movimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="nuevo_movimiento">
                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <select name="producto_id" class="form-select" required>
                                <?php foreach ($inventario as $inv): ?>
                                <option value="<?php echo $inv['id']; ?>"><?php echo $inv['nombre']; ?> (Stock: <?php echo $inv['stock_actual']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Movimiento</label>
                            <select name="tipo_movimiento" class="form-select" required>
                                <option value="entrada">Entrada (Compra/Reposición)</option>
                                <option value="merma">Merma (Pérdida)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" name="cantidad" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
