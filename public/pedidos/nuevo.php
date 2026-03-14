<?php
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/config/config.php';

if (!isLoggedIn()) {
    redireccionar('/restaurant-pos/public/index.php');
}

$db = getDB();
$tasa = getTasaCambio();

$mesas = $db->query("SELECT * FROM mesas WHERE estado = 'disponible' ORDER BY numero")->fetchAll();

$productos = $db->query("
    SELECT p.*, c.nombre as categoria_nombre
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.estado = 'activo'
    ORDER BY c.nombre, p.nombre
")->fetchAll();

$productosPorCategoria = [];
foreach ($productos as $p) {
    $cat = $p['categoria_nombre'] ?? 'Sin categoría';
    if (!isset($productosPorCategoria[$cat])) {
        $productosPorCategoria[$cat] = [];
    }
    $productosPorCategoria[$cat][] = $p;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Pedido - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --primary: #1e3a5f; --secondary: #2d5a87; --accent: #ff6b35; }
        .sidebar { min-height: 100vh; background: var(--primary); }
        .product-card { cursor: pointer; transition: all 0.2s; border: 2px solid transparent; }
        .product-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .product-card .price { color: var(--primary); font-weight: bold; }
        .order-item { transition: all 0.3s; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-md-block sidebar collapse show" id="sidebarMenu">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <i class="bi bi-shop" style="font-size: 2rem; color: white;"></i>
                        <h6 class="text-white mt-2"><?php echo APP_NAME; ?></h6>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/dashboard/index.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link active" href="/restaurant-pos/public/pedidos/index.php"><i class="bi bi-receipt me-2"></i>Pedidos</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/app/controllers/auth/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Salir</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div><h4 class="mb-0"><i class="bi bi-cart-plus me-2"></i>Nuevo Pedido</h4></div>
                    <div class="badge bg-warning text-dark fs-6">Tasa: <?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$</div>
                </div>

                <div class="row g-4">
                    <!-- Productos -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-white">
                                <ul class="nav nav-pills" id="categoryTabs" role="tablist">
                                    <?php $first = true; foreach ($productosPorCategoria as $cat => $prods): ?>
                                    <li class="nav-item">
                                        <button class="nav-link <?php echo $first ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#cat-<?php echo md5($cat); ?>"><?php echo $cat; ?></button>
                                    </li>
                                    <?php $first = false; endforeach; ?>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content" id="categoryTabsContent">
                                    <?php $first = true; foreach ($productosPorCategoria as $cat => $prods): ?>
                                    <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="cat-<?php echo md5($cat); ?>">
                                        <div class="row g-3">
                                            <?php foreach ($prods as $p): 
                                                $precioBs = $p['precio_base_usd'] * $tasa;
                                            ?>
                                            <div class="col-md-4 col-sm-6">
                                                <div class="card product-card h-100" onclick="agregarProducto(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                                                    <div class="card-body text-center">
                                                        <h6 class="mb-1"><?php echo $p['nombre']; ?></h6>
                                                        <p class="text-muted small mb-2"><?php echo $p['descripcion'] ?? ''; ?></p>
                                                        <div class="price"><?php echo formatearPrecio($precioBs, $p['precio_base_usd']); ?></div>
                                                        <?php if ($p['tipo_producto'] == 'compuesto'): ?>
                                                        <small class="badge bg-info mt-1">Compuesto</small>
                                                        <?php elseif ($p['tipo_producto'] == 'materia_prima'): ?>
                                                        <small class="badge bg-secondary mt-1">MP</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php $first = false; endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Orden Actual -->
                    <div class="col-lg-4">
                        <div class="card sticky-top" style="top: 20px;">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Orden Actual</h5>
                            </div>
                            <div class="card-body p-0">
                                <form id="formPedido" method="POST" action="/restaurant-pos/app/controllers/pedidos/guardar.php">
                                    <div class="mb-3 p-3">
                                        <label class="form-label">Mesa</label>
                                        <select name="mesa_id" class="form-select" required>
                                            <option value="">Seleccionar mesa...</option>
                                            <?php foreach ($mesas as $m): ?>
                                            <option value="<?php echo $m['id']; ?>">Mesa <?php echo $m['numero']; ?> (<?php echo $m['capacidad']; ?> pers.)</option>
                                            <?php endforeach; ?>
                                            <option value="">ParaLlevar</option>
                                        </select>
                                    </div>
                                    
                                    <div id="itemsOrden" class="border-top border-bottom" style="max-height: 300px; overflow-y: auto;">
                                        <div class="text-center text-muted py-4" id="emptyOrder">
                                            <i class="bi bi-cart-x" style="font-size: 2rem;"></i>
                                            <p class="mb-0">Sin productos agregados</p>
                                        </div>
                                    </div>

                                    <div class="p-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal USD:</span>
                                            <span id="subtotalUsd">$0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal Bs:</span>
                                            <span id="subtotalBs">0.00 Bs</span>
                                        </div>
                                        <div class="d-flex justify-content-between fw-bold fs-5">
                                            <span>Total:</span>
                                            <span id="totalOrden">0.00 Bs</span>
                                        </div>
                                    </div>

                                    <div class="p-3 border-top">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de Pago</label>
                                            <select name="tipo_pago" class="form-select">
                                                <option value="efectivo">Efectivo</option>
                                                <option value="tarjeta">Tarjeta</option>
                                                <option value="transferencia">Transferencia</option>
                                            </select>
                                        </div>
                                        <input type="hidden" name="items" id="itemsInput">
                                        <input type="hidden" name="tasa" value="<?php echo $tasa; ?>">
                                        <button type="submit" class="btn btn-success w-100" id="btnPagar" disabled>
                                            <i class="bi bi-check-circle me-2"></i>Confirmar Pedido
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let orden = [];
        const tasa = <?php echo $tasa; ?>;

        function agregarProducto(producto) {
            const existente = orden.find(item => item.id === producto.id);
            if (existente) {
                existente.cantidad++;
            } else {
                orden.push({
                    id: producto.id,
                    nombre: producto.nombre,
                    precio_usd: parseFloat(producto.precio_base_usd),
                    tipo: producto.tipo_producto,
                    cantidad: 1
                });
            }
            renderOrden();
        }

        function actualizarCantidad(index, cambio) {
            orden[index].cantidad += cambio;
            if (orden[index].cantidad <= 0) {
                orden.splice(index, 1);
            }
            renderOrden();
        }

        function renderOrden() {
            const container = document.getElementById('itemsOrden');
            const emptyMsg = document.getElementById('emptyOrder');
            
            if (orden.length === 0) {
                container.innerHTML = '';
                container.appendChild(emptyMsg);
                emptyMsg.style.display = 'block';
                document.getElementById('btnPagar').disabled = true;
                document.getElementById('subtotalUsd').textContent = '$0.00';
                document.getElementById('subtotalBs').textContent = '0.00 Bs';
                document.getElementById('totalOrden').textContent = '0.00 Bs';
                document.getElementById('itemsInput').value = '';
                return;
            }

            emptyMsg.style.display = 'none';
            let html = '';
            let subtotalUsd = 0;
            let subtotalBs = 0;

            orden.forEach((item, index) => {
                const itemUsd = item.precio_usd * item.cantidad;
                const itemBs = itemUsd * tasa;
                subtotalUsd += itemUsd;
                subtotalBs += itemBs;

                html += `
                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                        <div>
                            <strong>${item.nombre}</strong>
                            <br><small class="text-muted">${item.tipo}</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="actualizarCantidad(${index}, -1)">-</button>
                            <span class="badge bg-primary">${item.cantidad}</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="actualizarCantidad(${index}, 1)">+</button>
                            <span class="text-end" style="min-width: 80px;">${itemBs.toFixed(2)} Bs</span>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            document.getElementById('subtotalUsd').textContent = '$' + subtotalUsd.toFixed(2);
            document.getElementById('subtotalBs').textContent = subtotalBs.toFixed(2) + ' Bs';
            document.getElementById('totalOrden').textContent = subtotalBs.toFixed(2) + ' Bs';
            document.getElementById('btnPagar').disabled = false;
            document.getElementById('itemsInput').value = JSON.stringify(orden);
        }
    </script>
</body>
</html>
