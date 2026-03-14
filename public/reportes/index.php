<?php
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/config/config.php';

if (!isLoggedIn()) {
    redireccionar('/restaurant-pos/public/index.php');
}

$db = getDB();
$tasa = getTasaCambio();

$ventasDia = $db->query("
    SELECT SUM(total_bs) as total, SUM(total_usd) as total_usd, COUNT(*) as cantidad
    FROM pedidos WHERE DATE(fecha) = CURDATE() AND estado = 'pagado'
")->fetch();

$ventasSemana = $db->query("
    SELECT SUM(total_bs) as total, SUM(total_usd) as total_usd, COUNT(*) as cantidad
    FROM pedidos WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND estado = 'pagado'
")->fetch();

$topProductos = $db->query("
    SELECT p.nombre, SUM(d.cantidad) as cantidad_vendida, SUM(d.subtotal_usd) as total_usd
    FROM detalle_pedido d
    JOIN productos p ON d.producto_id = p.id
    JOIN pedidos pe ON d.pedido_id = pe.id
    WHERE pe.estado = 'pagado' AND pe.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY p.id
    ORDER BY cantidad_vendida DESC
    LIMIT 5
")->fetchAll();

$pedidosPorEstado = $db->query("
    SELECT estado, COUNT(*) as cantidad, SUM(total_bs) as total
    FROM pedidos
    WHERE DATE(fecha) = CURDATE()
    GROUP BY estado
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - <?php echo APP_NAME; ?></title>
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
                        <li class="nav-item"><a class="nav-link active" href="/restaurant-pos/public/reportes/index.php"><i class="bi bi-graph-up me-2"></i>Reportes</a></li>
                        <hr class="border-light">
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/app/controllers/auth/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div><h4 class="mb-0"><i class="bi bi-graph-up me-2"></i>Reportes</h4></div>
                    <div class="badge bg-warning text-dark fs-6">Tasa: <?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$</div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="text-white-50">Ventas de Hoy</h6>
                                <h3 class="mb-0"><?php echo number_format($ventasDia['total'] ?: 0, 2, ',', '.'); ?> Bs</h3>
                                <small><?php echo $ventasDia['total_usd'] ?: 0; ?> $ • <?php echo $ventasDia['cantidad'] ?: 0; ?> pedidos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="text-white-50">Ventas de la Semana</h6>
                                <h3 class="mb-0"><?php echo number_format($ventasSemana['total'] ?: 0, 2, ',', '.'); ?> Bs</h3>
                                <small><?php echo $ventasSemana['total_usd'] ?: 0; ?> $ • <?php echo $ventasSemana['cantidad'] ?: 0; ?> pedidos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="text-white-50">Pedidos Hoy</h6>
                                <h3 class="mb-0"><?php echo array_sum($pedidosPorEstado); ?></h3>
                                <small>Total de pedidos del día</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-white"><h5 class="mb-0">Productos Más Vendidos (Semana)</h5></div>
                            <div class="card-body p-0">
                                <table class="table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th class="text-center">Cantidad</th>
                                            <th class="text-end">Total USD</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($topProductos) > 0): ?>
                                            <?php foreach ($topProductos as $p): ?>
                                            <tr>
                                                <td><?php echo $p['nombre']; ?></td>
                                                <td class="text-center"><?php echo $p['cantidad_vendida']; ?></td>
                                                <td class="text-end">$<?php echo number_format($p['total_usd'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center text-muted">Sin ventas en los últimos 7 días</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-white"><h5 class="mb-0">Pedidos por Estado (Hoy)</h5></div>
                            <div class="card-body">
                                <?php 
                                $estados = ['pendiente', 'preparando', 'listo', 'entregado', 'pagado', 'cancelado'];
                                $colores = ['warning', 'info', 'primary', 'success', 'success', 'danger'];
                                $totalHoy = array_sum($pedidosPorEstado);
                                ?>
                                <?php foreach ($estados as $i => $e): 
                                    $cantidad = $pedidosPorEstado[$e] ?? 0;
                                    $porcentaje = $totalHoy > 0 ? ($cantidad / $totalHoy) * 100 : 0;
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><?php echo ucfirst($e); ?></span>
                                        <span><?php echo $cantidad; ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-<?php echo $colores[$i]; ?>" style="width: <?php echo $porcentaje; ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
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
