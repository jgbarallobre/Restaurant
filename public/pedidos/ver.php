<?php
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/config/config.php';

if (!isLoggedIn()) {
    redireccionar('/restaurant-pos/public/index.php');
}

$db = getDB();
$tasa = getTasaCambio();

$pedidoId = intval($_GET['id'] ?? 0);

$pedido = $db->prepare("
    SELECT p.*, m.numero as mesa_numero, u.nombre as usuario_nombre
    FROM pedidos p
    LEFT JOIN mesas m ON p.mesa_id = m.id
    LEFT JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.id = ?
")->execute([$pedidoId])->fetch();

if (!$pedido) {
    $_SESSION['error'] = 'Pedido no encontrado';
    redireccionar('/restaurant-pos/public/pedidos/index.php');
}

$detalles = $db->prepare("
    SELECT d.*, pr.nombre as producto_nombre, pr.tipo_producto
    FROM detalle_pedido d
    JOIN productos pr ON d.producto_id = pr.id
    WHERE d.pedido_id = ?
")->execute([$pedidoId])->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    
    try {
        $db->beginTransaction();

        if ($accion === 'cambiar_estado') {
            $nuevoEstado = $_POST['estado'];
            $stmt = $db->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevoEstado, $pedidoId]);
            
            if ($nuevoEstado === 'pagado' && $pedido['mesa_id']) {
                $updateMesa = $db->prepare("UPDATE mesas SET estado = 'disponible' WHERE id = ?");
                $updateMesa->execute([$pedido['mesa_id']]);
            }
            
            logger("Pedido #{$pedidoId} cambió a estado: {$nuevoEstado}");
            $db->commit();
            redireccionar('/restaurant-pos/public/pedidos/ver.php?id=' . $pedidoId);
        }

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
    <title>Pedido #<?php echo str_pad($pedidoId, 4, '0', STR_PAD_LEFT); ?> - <?php echo APP_NAME; ?></title>
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
                        <li class="nav-item"><a class="nav-link active" href="/restaurant-pos/public/pedidos/index.php"><i class="bi bi-receipt me-2"></i>Pedidos</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/app/controllers/auth/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <a href="/restaurant-pos/public/pedidos/index.php" class="btn btn-outline-secondary btn-sm me-2"><i class="bi bi-arrow-left"></i></a>
                        <h4 class="mb-0 d-inline">Pedido #<?php echo str_pad($pedidoId, 4, '0', STR_PAD_LEFT); ?></h4>
                    </div>
                    <div class="badge bg-warning text-dark fs-6">Tasa: <?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$</div>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Detalles del Pedido</h5>
                                <?php
                                $estados = ['pendiente', 'preparando', 'listo', 'entregado', 'pagado', 'cancelado'];
                                $estadoActual = $pedido['estado'];
                                $idxActual = array_search($estadoActual, $estados);
                                ?>
                                <form method="POST" class="d-flex gap-2">
                                    <select name="estado" class="form-select form-select-sm" style="width: auto;">
                                        <?php foreach ($estados as $e): ?>
                                        <option value="<?php echo $e; ?>" <?php echo $e === $estadoActual ? 'selected' : ''; ?>><?php echo ucfirst($e); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check"></i></button>
                                </form>
                            </div>
                            <div class="card-body p-0">
                                <table class="table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th>Tipo</th>
                                            <th class="text-center">Cantidad</th>
                                            <th class="text-end">Precio</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detalles as $d): ?>
                                        <tr>
                                            <td><?php echo $d['producto_nombre']; ?></td>
                                            <td><span class="badge bg-<?php echo $d['tipo_producto'] === 'compuesto' ? 'info' : 'secondary'; ?>"><?php echo $d['tipo_producto']; ?></span></td>
                                            <td class="text-center"><?php echo $d['cantidad']; ?></td>
                                            <td class="text-end"><?php echo formatearPrecio($d['precio_unitario_bs'], $d['precio_unitario_usd']); ?></td>
                                            <td class="text-end"><?php echo formatearPrecio($d['subtotal_bs'], $d['subtotal_usd']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="4" class="text-end fw-bold">Total:</td>
                                            <td class="text-end fw-bold"><?php echo formatearPrecio($pedido['total_bs'], $pedido['total_usd']); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header bg-white"><h5 class="mb-0">Información</h5></div>
                            <div class="card-body">
                                <p><strong>Mesa:</strong> <?php echo $pedido['mesa_numero'] ? 'Mesa ' . $pedido['mesa_numero'] : 'Para Llevar'; ?></p>
                                <p><strong>Atendido por:</strong> <?php echo $pedido['usuario_nombre']; ?></p>
                                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></p>
                                <p><strong>Tipo de Pago:</strong> <?php echo ucfirst($pedido['tipo_pago']); ?></p>
                                <p><strong>Tasa Usada:</strong> <?php echo number_format($pedido['tasa_cambio_usada'], 2, ',', '.'); ?> Bs/$</p>
                                <hr>
                                <p><strong>Estado:</strong> 
                                    <?php
                                    $estadosColor = [
                                        'pendiente' => 'warning',
                                        'preparando' => 'info',
                                        'listo' => 'primary',
                                        'entregado' => 'success',
                                        'pagado' => 'success',
                                        'cancelado' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $estadosColor[$pedido['estado']]; ?>"><?php echo ucfirst($pedido['estado']); ?></span>
                                </p>
                            </div>
                        </div>

                        <?php if ($pedido['estado'] !== 'pagado' && $pedido['estado'] !== 'cancelado'): ?>
                        <div class="card border-success">
                            <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="bi bi-cash me-2"></i>Pagar Pedido</h5></div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="estado" value="pagado">
                                    <p class="mb-2">Total a pagar:</p>
                                    <h3 class="text-success mb-3"><?php echo formatearPrecio($pedido['total_bs'], $pedido['total_usd']); ?></h3>
                                    <button type="submit" class="btn btn-success w-100" onclick="return confirm('¿Confirmar pago del pedido?');">
                                        <i class="bi bi-check-circle me-2"></i>Confirmar Pago
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
