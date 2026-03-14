<?php
require_once __DIR__ . '/../../public/bootstrap.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'No autorizado'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$db = getDB();
$tasa = floatval($_POST['tasa'] ?? 0);

if ($tasa <= 0) {
    $tasa = getTasaCambio();
}

$mesa_id = !empty($_POST['mesa_id']) ? intval($_POST['mesa_id']) : null;
$tipo_pago = sanitizar($_POST['tipo_pago'] ?? 'efectivo');
$items = json_decode($_POST['items'] ?? '[]', true);

if (empty($items)) {
    $_SESSION['error'] = 'Debe agregar al menos un producto';
    redireccionar('/restaurant-pos/public/pedidos/nuevo.php');
}

try {
    $db->beginTransaction();

    $subtotalUsd = 0;
    $productosValidados = [];

    foreach ($items as $item) {
        $producto = $db->prepare("SELECT * FROM productos WHERE id = ? AND estado = 'activo'")->execute([$item['id']])->fetch();
        
        if (!$producto) {
            throw new Exception("Producto no encontrado: " . $item['id']);
        }

        $cantidad = intval($item['cantidad']);

        if ($producto['tipo_producto'] === 'compuesto') {
            $recetas = $db->prepare("
                SELECT r.*, p.nombre as nombre_mp, p.stock_actual, p.unidad_medida
                FROM recetas r
                JOIN productos p ON r.materia_prima_id = p.id
                WHERE r.producto_compuesto_id = ?
            ")->execute([$producto['id']])->fetchAll();

            foreach ($recetas as $receta) {
                $cantidadRequerida = $receta['cantidad_requerida'] * $cantidad;
                if ($receta['stock_actual'] < $cantidadRequerida) {
                    throw new Exception("Stock insuficiente de {$receta['nombre_mp']}. Necesario: {$cantidadRequerida} {$receta['unidad_medida_receta']}, Disponible: {$receta['stock_actual']}");
                }
            }
        } elseif ($producto['tipo_producto'] === 'terminado') {
            if ($producto['stock_actual'] < $cantidad) {
                throw new Exception("Stock insuficiente de {$producto['nombre']}. Disponible: {$producto['stock_actual']}");
            }
        }

        $precioUsd = floatval($producto['precio_base_usd']);
        $precioBs = $precioUsd * $tasa;
        
        $productosValidados[] = [
            'producto' => $producto,
            'cantidad' => $cantidad,
            'precio_usd' => $precioUsd,
            'precio_bs' => $precioBs
        ];

        $subtotalUsd += $precioUsd * $cantidad;
    }

    $totalBs = $subtotalUsd * $tasa;

    $stmt = $db->prepare("
        INSERT INTO pedidos (mesa_id, usuario_id, total_usd, total_bs, tasa_cambio_usada, tipo_pago, estado)
        VALUES (?, ?, ?, ?, ?, ?, 'pendiente')
    ");
    $stmt->execute([$mesa_id, $_SESSION['usuario_id'], $subtotalUsd, $totalBs, $tasa, $tipo_pago]);
    $pedidoId = $db->lastInsertId();

    foreach ($productosValidados as $item) {
        $p = $item['producto'];
        $cantidad = $item['cantidad'];
        
        $subtotalItemUsd = $item['precio_usd'] * $cantidad;
        $subtotalItemBs = $item['precio_bs'] * $cantidad;

        $detalleStmt = $db->prepare("
            INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio_unitario_usd, precio_unitario_bs, subtotal_usd, subtotal_bs)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $detalleStmt->execute([$pedidoId, $p['id'], $cantidad, $item['precio_usd'], $item['precio_bs'], $subtotalItemUsd, $subtotalItemBs]);

        if ($p['tipo_producto'] === 'compuesto') {
            $recetas = $db->prepare("
                SELECT * FROM recetas WHERE producto_compuesto_id = ?
            ")->execute([$p['id']])->fetchAll();

            foreach ($recetas as $receta) {
                $cantidadDescontar = $receta['cantidad_requerida'] * $cantidad;
                $stockAnterior = floatval($receta['stock_actual']);
                $stockNuevo = $stockAnterior - $cantidadDescontar;

                $updateStock = $db->prepare("UPDATE productos SET stock_actual = ? WHERE id = ?");
                $updateStock->execute([$stockNuevo, $receta['materia_prima_id']]);

                $movimiento = $db->prepare("
                    INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, saldo_anterior, saldo_nuevo, usuario_id, referencia)
                    VALUES (?, 'salida_receta', ?, ?, ?, ?, ?)
                ");
                $movimiento->execute([$receta['materia_prima_id'], $cantidadDescontar, $stockAnterior, $stockNuevo, $_SESSION['usuario_id'], 'Pedido #' . $pedidoId]);
            }
        } elseif ($p['tipo_producto'] === 'terminado') {
            $stockAnterior = floatval($p['stock_actual']);
            $stockNuevo = $stockAnterior - $cantidad;

            $updateStock = $db->prepare("UPDATE productos SET stock_actual = ? WHERE id = ?");
            $updateStock->execute([$stockNuevo, $p['id']]);

            $movimiento = $db->prepare("
                INSERT INTO movimientos_inventario (producto_id, tipo_movimiento, cantidad, saldo_anterior, saldo_nuevo, usuario_id, referencia)
                VALUES (?, 'salida_venta', ?, ?, ?, ?, ?)
            ");
            $movimiento->execute([$p['id'], $cantidad, $stockAnterior, $stockNuevo, $_SESSION['usuario_id'], 'Pedido #' . $pedidoId]);
        }
    }

    if ($mesa_id) {
        $updateMesa = $db->prepare("UPDATE mesas SET estado = 'ocupada' WHERE id = ?");
        $updateMesa->execute([$mesa_id]);
    }

    $db->commit();
    
    logger("Pedido #{$pedidoId} creado por {$_SESSION['usuario']}");
    redireccionar('/restaurant-pos/public/pedidos/ver.php?id=' . $pedidoId);

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = $e->getMessage();
    logger("Error al crear pedido: " . $e->getMessage(), 'ERROR');
    redireccionar('/restaurant-pos/public/pedidos/nuevo.php');
}
