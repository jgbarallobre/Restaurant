<?php
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/config/config.php';

if (!isLoggedIn()) {
    redireccionar('/restaurant-pos/public/index.php');
}

$db = getDB();
$tasa = getTasaCambio();

$productos = $db->query("
    SELECT p.*, c.nombre as categoria_nombre
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    ORDER BY c.nombre, p.nombre
")->fetchAll();

$categorias = $db->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - <?php echo APP_NAME; ?></title>
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
                        <li class="nav-item"><a class="nav-link active" href="/restaurant-pos/public/productos/index.php"><i class="bi bi-box-seam me-2"></i>Productos</a></li>
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
                    <div><h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>Gestión de Productos</h4></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="badge bg-warning text-dark fs-6">Tasa: <?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$</div>
                        <?php if (verificarPermiso('productos')): ?>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalProducto"><i class="bi bi-plus-lg me-1"></i>Nuevo Producto</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Precio USD</th>
                                        <th>Precio Bs</th>
                                        <th>Stock</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos as $p): 
                                        $precioBs = $p['precio_base_usd'] * $tasa;
                                        $stockBajo = ($p['tipo_producto'] === 'materia_prima' || $p['tipo_producto'] === 'terminado') && $p['stock_actual'] <= $p['stock_minimo'];
                                    ?>
                                    <tr class="<?php echo $stockBajo ? 'table-warning' : ''; ?>">
                                        <td><?php echo $p['id']; ?></td>
                                        <td>
                                            <strong><?php echo $p['nombre']; ?></strong>
                                            <br><small class="text-muted"><?php echo $p['categoria_nombre'] ?? 'Sin categoría'; ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $tipos = [
                                                'materia_prima' => ['label' => 'Materia Prima', 'color' => 'secondary'],
                                                'terminado' => ['label' => 'Terminado', 'color' => 'success'],
                                                'compuesto' => ['label' => 'Compuesto', 'color' => 'info']
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $tipos[$p['tipo_producto']]['color']; ?>"><?php echo $tipos[$p['tipo_producto']]['label']; ?></span>
                                        </td>
                                        <td>$<?php echo number_format($p['precio_base_usd'], 2); ?></td>
                                        <td><?php echo number_format($precioBs, 2, ',', '.'); ?> Bs</td>
                                        <td>
                                            <?php if ($p['tipo_producto'] !== 'compuesto'): ?>
                                            <span class="<?php echo $stockBajo ? 'text-danger fw-bold' : ''; ?>">
                                                <?php echo number_format($p['stock_actual'], 2); ?> <?php echo $p['unidad_medida']; ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-<?php echo $p['estado'] === 'activo' ? 'success' : 'secondary'; ?>"><?php echo $p['estado']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Nuevo Producto -->
    <div class="modal fade" id="modalProducto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="/restaurant-pos/app/controllers/productos/guardar.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Precio USD</label>
                                <input type="number" name="precio_base_usd" class="form-control" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo</label>
                                <select name="tipo_producto" class="form-select" required>
                                    <option value="terminado">Terminado</option>
                                    <option value="compuesto">Compuesto</option>
                                    <option value="materia_prima">Materia Prima</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unidad de Medida</label>
                                <input type="text" name="unidad_medida" class="form-control" value="unidad">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Inicial</label>
                                <input type="number" name="stock_actual" class="form-control" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock Mínimo</label>
                            <input type="number" name="stock_minimo" class="form-control" step="0.01" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoría</label>
                            <select name="categoria_id" class="form-select">
                                <option value="">Sin categoría</option>
                                <?php foreach ($categorias as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
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
