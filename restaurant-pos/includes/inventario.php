<?php
/**
 * Funciones de Inventario
 * Sistema POS Restaurante
 */

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/config/tasa.php';

/**
 * Registra un movimiento de inventario
 */
function registrarMovimiento($producto_id, $tipo, $cantidad, $usuario_id, $referencia = null, $motivo = null) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Obtener stock actual
        $stmt = $db->prepare("SELECT stock_actual, nombre FROM productos WHERE id = ?");
        $stmt->execute([$producto_id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            throw new Exception("Producto no encontrado");
        }
        
        $saldo_anterior = floatval($producto['stock_actual']);
        
        // Calcular nuevo stock
        switch ($tipo) {
            case 'entrada':
            case 'ajuste_positivo':
                $saldo_nuevo = $saldo_anterior + $cantidad;
                break;
            case 'salida_venta':
            case 'salida_receta':
            case 'merma':
            case 'ajuste_negativo':
                $saldo_nuevo = max(0, $saldo_anterior - $cantidad);
                break;
            default:
                throw new Exception("Tipo de movimiento no válido");
        }
        
        // Actualizar stock
        $update = $db->prepare("UPDATE productos SET stock_actual = ? WHERE id = ?");
        $update->execute([$saldo_nuevo, $producto_id]);
        
        // Registrar movimiento
        $mov = $db->prepare("
            INSERT INTO movimientos_inventario 
            (producto_id, tipo_movimiento, cantidad, saldo_anterior, saldo_nuevo, usuario_id, referencia, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $mov->execute([$producto_id, $tipo, $cantidad, $saldo_anterior, $saldo_nuevo, $usuario_id, $referencia, $motivo]);
        
        $db->commit();
        
        return ['success' => true, 'saldo_anterior' => $saldo_anterior, 'saldo_nuevo' => $saldo_nuevo];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtiene movimientos de inventario por producto
 */
function obtenerMovimientos($producto_id = null, $fecha_inicio = null, $fecha_fin = null, $limit = 50, $offset = 0) {
    $db = getDB();
    
    $where = "1=1";
    $params = [];
    
    if ($producto_id) {
        $where .= " AND m.producto_id = ?";
        $params[] = $producto_id;
    }
    
    if ($fecha_inicio) {
        $where .= " AND DATE(m.fecha) >= ?";
        $params[] = $fecha_inicio;
    }
    
    if ($fecha_fin) {
        $where .= " AND DATE(m.fecha) <= ?";
        $params[] = $fecha_fin;
    }
    
    $sql = "
        SELECT m.*, p.nombre as producto_nombre, p.unidad_medida, u.nombre as usuario_nombre
        FROM movimientos_inventario m
        JOIN productos p ON m.producto_id = p.id
        JOIN usuarios u ON m.usuario_id = u.id
        WHERE {$where}
        ORDER BY m.fecha DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene stock disponible de un producto compuesto
 */
function stockDisponibleCompuesto($producto_id) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT r.materia_prima_id, r.cantidad_requerida, p.stock_actual, p.nombre, p.unidad_medida
        FROM recetas r
        JOIN productos p ON r.materia_prima_id = p.id
        WHERE r.producto_compuesto_id = ?
    ");
    $stmt->execute([$producto_id]);
    $ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($ingredientes)) {
        return 0;
    }
    
    $minimo = PHP_INT_MAX;
    foreach ($ingredientes as $ing) {
        $disponible = floatval($ing['stock_actual']);
        $requerido = floatval($ing['cantidad_requerida']);
        
        if ($requerido > 0) {
            $posibles = floor($disponible / $requerido);
            $minimo = min($minimo, $posibles);
        }
    }
    
    return $minimo === PHP_INT_MAX ? 0 : $minimo;
}

/**
 * Calcula el costo total de una receta
 */
function calcularCostoReceta($producto_id) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT r.cantidad_requerida, p.precio_base_usd as costo_unitario
        FROM recetas r
        JOIN productos p ON r.materia_prima_id = p.id
        WHERE r.producto_compuesto_id = ?
    ");
    $stmt->execute([$producto_id]);
    $ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $costo_total = 0;
    foreach ($ingredientes as $ing) {
        $costo_total += floatval($ing['cantidad_requerida']) * floatval($ing['costo_unitario']);
    }
    
    return round($costo_total, 2);
}

/**
 * Verifica si hay stock suficiente para un pedido
 */
function verificarStockPedido($items) {
    $db = getDB();
    $errores = [];
    
    foreach ($items as $item) {
        $producto_id = $item['producto_id'];
        $cantidad = $item['cantidad'];
        
        $stmt = $db->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt->execute([$producto_id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            $errores[] = "Producto no encontrado: {$producto_id}";
            continue;
        }
        
        if ($producto['tipo_producto'] === 'compuesto') {
            $disponible = stockDisponibleCompuesto($producto_id);
            if ($disponible < $cantidad) {
                $errores[] = "Stock insuficiente para {$producto['nombre']}. Disponible: {$disponible}";
            }
        } elseif ($producto['tipo_producto'] === 'terminado') {
            if ($producto['stock_actual'] < $cantidad) {
                $errores[] = "Stock insuficiente para {$producto['nombre']}. Disponible: {$producto['stock_actual']}";
            }
        }
    }
    
    return ['valido' => empty($errores), 'errores' => $errores];
}

/**
 * Descuenta inventario al vender
 */
function descontarInventarioVenta($pedido_id, $usuario_id) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Obtener detalles del pedido
        $stmt = $db->prepare("
            SELECT dp.producto_id, dp.cantidad, p.nombre, p.tipo_producto, p.stock_actual
            FROM detalle_pedido dp
            JOIN productos p ON dp.producto_id = p.id
            WHERE dp.pedido_id = ?
        ");
        $stmt->execute([$pedido_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $producto_id = $item['producto_id'];
            $cantidad = $item['cantidad'];
            $tipo = $item['tipo_producto'];
            
            if ($tipo === 'compuesto') {
                // Descontar cada ingrediente según receta
                $recetas = $db->prepare("
                    SELECT r.materia_prima_id, r.cantidad_requerida
                    FROM recetas r
                    WHERE r.producto_compuesto_id = ?
                ");
                $recetas->execute([$producto_id]);
                $ingredientes = $recetas->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($ingredientes as $ing) {
                    $cantidad_descontar = floatval($ing['cantidad_requerida']) * $cantidad;
                    
                    // Obtener stock actual
                    $stock = $db->prepare("SELECT stock_actual, nombre FROM productos WHERE id = ?");
                    $stock->execute([$ing['materia_prima_id']]);
                    $producto = $stock->fetch(PDO::FETCH_ASSOC);
                    
                    $nuevo_stock = floatval($producto['stock_actual']) - $cantidad_descontar;
                    
                    // Actualizar stock
                    $update = $db->prepare("UPDATE productos SET stock_actual = ? WHERE id = ?");
                    $update->execute([$nuevo_stock, $ing['materia_prima_id']]);
                    
                    // Registrar movimiento
                    $mov = $db->prepare("
                        INSERT INTO movimientos_inventario 
                        (producto_id, tipo_movimiento, cantidad, saldo_anterior, saldo_nuevo, usuario_id, referencia)
                        VALUES (?, 'salida_receta', ?, ?, ?, ?, ?)
                    ");
                    $mov->execute([
                        $ing['materia_prima_id'],
                        $cantidad_descontar,
                        $producto['stock_actual'],
                        $nuevo_stock,
                        $usuario_id,
                        "Pedido #{$pedido_id}"
                    ]);
                }
                
            } elseif ($tipo === 'terminado') {
                // Descontar directamente
                $nuevo_stock = floatval($item['stock_actual']) - $cantidad;
                
                $update = $db->prepare("UPDATE productos SET stock_actual = ? WHERE id = ?");
                $update->execute([$nuevo_stock, $producto_id]);
                
                // Registrar movimiento
                $mov = $db->prepare("
                    INSERT INTO movimientos_inventario 
                    (producto_id, tipo_movimiento, cantidad, saldo_anterior, saldo_nuevo, usuario_id, referencia)
                    VALUES (?, 'salida_venta', ?, ?, ?, ?, ?)
                ");
                $mov->execute([
                    $producto_id,
                    $cantidad,
                    $item['stock_actual'],
                    $nuevo_stock,
                    $usuario_id,
                    "Pedido #{$pedido_id}"
                ]);
            }
        }
        
        $db->commit();
        return ['success' => true];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtiene productos con stock bajo
 */
function obtenerProductosStockBajo() {
    $db = getDB();
    
    $stmt = $db->query("
        SELECT p.*, c.nombre as categoria_nombre
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.tipo_producto IN ('terminado', 'materia_prima')
        AND p.estado = 'activo'
        AND p.stock_actual <= p.stock_minimo
        ORDER BY (p.stock_actual / NULLIF(p.stock_minimo, 0)) ASC
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene valor del inventario
 */
function obtenerValorInventario() {
    $db = getDB();
    
    $stmt = $db->query("
        SELECT 
            SUM(p.stock_actual * p.precio_base_usd) as valor_total_usd
        FROM productos p
        WHERE p.tipo_producto IN ('terminado', 'materia_prima')
        AND p.estado = 'activo'
    ");
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $tasa = obtenerTasaActual();
    
    return [
        'usd' => floatval($result['valor_total_usd'] ?? 0),
        'bs' => floatval($result['valor_total_usd'] ?? 0) * $tasa
    ];
}

/**
 * Ajusta stock manualmente
 */
function ajustarStock($producto_id, $cantidad, $tipo, $motivo, $usuario_id) {
    // tipo: 'entrada' o 'salida'
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Obtener stock actual
        $stmt = $db->prepare("SELECT stock_actual, nombre FROM productos WHERE id = ?");
        $stmt->execute([$producto_id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            throw new Exception("Producto no encontrado");
        }
        
        $saldo_anterior = floatval($producto['stock_actual']);
        
        if ($tipo === 'entrada') {
            $saldo_nuevo = $saldo_anterior + abs($cantidad);
            $tipo_mov = 'entrada';
        } else {
            $saldo_nuevo = max(0, $saldo_anterior - abs($cantidad));
            $tipo_mov = 'ajuste';
        }
        
        // Actualizar stock
        $update = $db->prepare("UPDATE productos SET stock_actual = ? WHERE id = ?");
        $update->execute([$saldo_nuevo, $producto_id]);
        
        // Registrar movimiento
        $mov = $db->prepare("
            INSERT INTO movimientos_inventario 
            (producto_id, tipo_movimiento, cantidad, saldo_anterior, saldo_nuevo, usuario_id, observaciones)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $mov->execute([$producto_id, $tipo_mov, abs($cantidad), $saldo_anterior, $saldo_nuevo, $usuario_id, $motivo]);
        
        $db->commit();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtiene todos los productos
 */
function obtenerProductos($tipo = null, $categoria_id = null, $busqueda = null, $estado = null) {
    $db = getDB();
    
    $where = "1=1";
    $params = [];
    
    if ($tipo) {
        $where .= " AND p.tipo_producto = ?";
        $params[] = $tipo;
    }
    
    if ($categoria_id) {
        $where .= " AND p.categoria_id = ?";
        $params[] = $categoria_id;
    }
    
    if ($busqueda) {
        $where .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    
    if ($estado) {
        $where .= " AND p.estado = ?";
        $params[] = $estado;
    }
    
    $sql = "
        SELECT p.*, c.nombre as categoria_nombre
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE {$where}
        ORDER BY p.tipo_producto, p.nombre
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene un producto por ID
 */
function obtenerProducto($id) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, c.nombre as categoria_nombre
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtiene recetas de un producto compuesto
 */
function obtenerReceta($producto_id) {
    $db = $db ?? getDB();
    static $db_static = null;
    
    if ($db_static === null) {
        require_once __DIR__ . '/../app/config/database.php';
        $db_static = getDB();
    }
    
    $stmt = $db_static->prepare("
        SELECT r.*, p.nombre as materia_prima_nombre, p.stock_actual, p.unidad_medida, p.precio_base_usd
        FROM recetas r
        JOIN productos p ON r.materia_prima_id = p.id
        WHERE r.producto_compuesto_id = ?
    ");
    $stmt->execute([$producto_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Guarda o actualiza una receta
 */
function guardarReceta($producto_compuesto_id, $ingredientes, $usuario_id) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Eliminar receta actual
        $delete = $db->prepare("DELETE FROM recetas WHERE producto_compuesto_id = ?");
        $delete->execute([$producto_compuesto_id]);
        
        // Insertar nuevos ingredientes
        foreach ($ingredientes as $ing) {
            $insert = $db->prepare("
                INSERT INTO recetas (producto_compuesto_id, materia_prima_id, cantidad_requerida, unidad_medida_receta)
                VALUES (?, ?, ?, ?)
            ");
            $insert->execute([
                $producto_compuesto_id,
                $ing['materia_prima_id'],
                $ing['cantidad'],
                $ing['unidad_medida']
            ]);
        }
        
        $db->commit();
        return ['success' => true];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtiene materias primas para selector
 */
function obtenerMateriasPrimas() {
    $db = getDB();
    $stmt = $db->query("
        SELECT id, nombre, stock_actual, unidad_medida, precio_base_usd
        FROM productos
        WHERE tipo_producto = 'materia_prima' AND estado = 'activo'
        ORDER BY nombre
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene categorías
 */
function obtenerCategorias($tipo = null) {
    $db = getDB();
    
    $where = "1=1";
    $params = [];
    
    if ($tipo) {
        $where .= " AND tipo = ?";
        $params[] = $tipo;
    }
    
    $stmt = $db->prepare("SELECT * FROM categorias WHERE {$where} ORDER BY nombre");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
