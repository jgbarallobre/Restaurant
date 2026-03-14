<?php
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/config/config.php';

if (!isLoggedIn()) {
    redireccionar('/restaurant-pos/public/index.php');
}

$db = getDB();
$tasa = getTasaCambio();

$pedidos = $db->query("
    SELECT p.*, m.numero as mesa_numero, u.nombre as usuario_nombre
    FROM pedidos p
    LEFT JOIN mesas m ON p.mesa_id = m.id
    LEFT JOIN usuarios u ON p.usuario_id = u.id
    ORDER BY p.fecha DESC
    LIMIT 50
")->fetchAll();

$mesas = $db->query("SELECT * FROM mesas WHERE estado != 'inactiva' ORDER BY numero")->fetchAll();
$productos = $db->query("SELECT * FROM productos WHERE estado = 'activo' ORDER BY nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --primary: #1e3a5f; --secondary: #2d5a87; --accent: #ff6b35; }
        .sidebar { min-height: 100vh; background: var(--primary); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 2px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: var(--secondary); color: white; }
        .product-card { cursor: pointer; transition: all 0.2s; }
        .product-card:hover { transform: scale(1.02); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .product-card.selected { border: 2px solid var(--accent); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse show" id="sidebarMenu">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <i class="bi bi-shop" style="font-size: 2rem; color: white;"></i>
                        <h5 class="text-white mt-2"><?php echo APP_NAME; ?></h5>
                    </div>
                    <hr class="border-light">
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/dashboard/index.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link active" href="/restaurant-pos/public/pedidos/index.php"><i class="bi bi-receipt me-2"></i>Pedidos</a></li>
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
                    <div><h4 class="mb-0"><i class="bi bi-receipt me-2"></i>Gestión de Pedidos</h4></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="badge bg-warning text-dark fs-6">Tasa: <?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$</div>
                        <button class="btn btn-sm btn-outline-secondary d-md-none" data-bs-toggle="collapse" data-bs-target="#sidebarMenu"><i class="bi bi-list"></i></button>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Lista de Pedidos Activos -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Pedidos Recientes</h5>
                                <a href="/restaurant-pos/public/pedidos/nuevo.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo Pedido</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Mesa</th>
                                                <th>Fecha</th>
                                                <th>Total</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pedidos as $p): ?>
                                            <tr>
                                                <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo $p['mesa_numero'] ? 'Mesa ' . $p['mesa_numero'] : 'Llevar'; ?></td>
                                                <td><?php echo date('d/m H:i', strtotime($p['fecha'])); ?></td>
                                                <td><?php echo formatearPrecio($p['total_bs'], $p['total_usd']); ?></td>
                                                <td>
                                                    <?php
                                                    $estados = [
                                                        'pendiente' => 'warning',
                                                        'preparando' => 'info',
                                                        'listo' => 'primary',
                                                        'entregado' => 'success',
                                                        'pagado' => 'success',
                                                        'cancelado' => 'danger'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?php echo $estados[$p['estado']] ?? 'secondary'; ?>"><?php echo ucfirst($p['estado']); ?></span>
                                                </td>
                                                <td>
                                                    <a href="/restaurant-pos/public/pedidos/ver.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mesas -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-white"><h5 class="mb-0">Mesas</h5></div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <?php foreach ($mesas as $m): ?>
                                    <div class="col-4">
                                        <div class="p-3 text-center rounded <?php echo $m['estado'] == 'disponible' ? 'bg-success' : ($m['estado'] == 'ocupada' ? 'bg-warning' : 'bg-secondary'); ?>">
                                            <strong class="text-white"><?php echo $m['numero']; ?></strong>
                                        </div>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
