<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/inventario.php';

if (!verificarSesion()) {
    header('Location: ../login.php');
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$resultado = ['success' => false, 'mensaje' => ''];

function sanitizar($dato) {
    return htmlspecialchars(trim($dato ?? ''), ENT_QUOTES, 'UTF-8');
}

try {
    $db = getDB();
    
    switch ($accion) {
        case 'crear':
            requirePermiso('crear_productos', '../dashboard.php');
            
            $nombre = sanitizar($_POST['nombre'] ?? '');
            $tipo = $_POST['tipo_producto'] ?? '';
            $descripcion = sanitizar($_POST['descripcion'] ?? '');
            $precio_usd = floatval($_POST['precio_usd'] ?? 0);
            $unidad_medida = $_POST['unidad_medida'] ?? 'unidad';
            $stock_actual = floatval($_POST['stock_actual'] ?? 0);
            $stock_minimo = floatval($_POST['stock_minimo'] ?? 0);
            $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
            $estado = $_POST['estado'] ?? 'activo';
            
            // Validaciones
            if (empty($nombre)) {
                throw new Exception('El nombre es requerido');
            }
            
            if ($tipo === 'compuesto' && empty($_POST['ingredientes'])) {
                throw new Exception('Un producto compuesto debe tener al menos un ingrediente');
            }
            
            // Manejar imagen
            $imagen_nombre = null;
            if (!empty($_FILES['imagen']['name'])) {
                $directorio = __DIR__ . '/../assets/uploads/productos/';
                if (!is_dir($directorio)) {
                    mkdir($directorio, 0755, true);
                }
                
                $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                $imagen_nombre = 'prod_' . time() . '.' . $extension;
                
                move_uploaded_file($_FILES['imagen']['tmp_name'], $directorio . $imagen_nombre);
            }
            
            // Para materia prima, usar costo como precio_base_usd
            if ($tipo === 'materia_prima' && isset($_POST['costo'])) {
                $precio_usd = floatval($_POST['costo']);
            }
            
            // Insertar producto
            $stmt = $db->prepare("
                INSERT INTO productos (nombre, descripcion, precio_base_usd, tipo_producto, unidad_medida, stock_actual, stock_minimo, categoria_id, imagen, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nombre, $descripcion, $precio_usd, $tipo, $unidad_medida, $stock_actual, $stock_minimo, $categoria_id, $imagen_nombre, $estado
            ]);
            
            $producto_id = $db->lastInsertId();
            
            // Guardar receta si es compuesto
            if ($tipo === 'compuesto' && !empty($_POST['ingredientes'])) {
                $ingredientes = $_POST['ingredientes'];
                $cantidades = $_POST['cantidades'] ?? [];
                $unidades = $_POST['unidades'] ?? [];
                
                foreach ($ingredientes as $i => $mp_id) {
                    if ($mp_id && isset($cantidades[$i])) {
                        $receta = $db->prepare("
                            INSERT INTO recetas (producto_compuesto_id, materia_prima_id, cantidad_requerida, unidad_medida_receta)
                            VALUES (?, ?, ?, ?)
                        ");
                        $receta->execute([$producto_id, $mp_id, floatval($cantidades[$i]), $unidades[$i] ?? 'unidad']);
                    }
                }
            }
            
            // Registrar movimiento de inventario si hay stock inicial
            if ($stock_actual > 0 && ($tipo === 'materia_prima' || $tipo === 'terminado')) {
                registrarMovimiento($producto_id, 'entrada', $stock_actual, $_SESSION['usuario_id'], null, 'Stock inicial');
            }
            
            registrarLog("CREAR_PRODUCTO", "Producto {$nombre} (ID: {$producto_id}) creado");
            
            $resultado['success'] = true;
            $resultado['mensaje'] = 'Producto creado exitosamente';
            break;
            
        case 'editar':
            requirePermiso('editar_productos', '../dashboard.php');
            
            $id = intval($_POST['id'] ?? 0);
            $nombre = sanitizar($_POST['nombre'] ?? '');
            $descripcion = sanitizar($_POST['descripcion'] ?? '');
            $precio_usd = floatval($_POST['precio_usd'] ?? 0);
            $unidad_medida = $_POST['unidad_medida'] ?? 'unidad';
            $stock_minimo = floatval($_POST['stock_minimo'] ?? 0);
            $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
            $estado = $_POST['estado'] ?? 'activo';
            
            if ($id <= 0) {
                throw new Exception('ID de producto inválido');
            }
            
            if (empty($nombre)) {
                throw new Exception('El nombre es requerido');
            }
            
            // Obtener producto actual
            $producto = obtenerProducto($id);
            
            // Manejar imagen
            $imagen_nombre = $producto['imagen'];
            if (!empty($_FILES['imagen']['name'])) {
                $directorio = __DIR__ . '/../assets/uploads/productos/';
                
                // Eliminar imagen anterior
                if ($imagen_nombre && file_exists($directorio . $imagen_nombre)) {
                    unlink($directorio . $imagen_nombre);
                }
                
                $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                $imagen_nombre = 'prod_' . time() . '.' . $extension;
                move_uploaded_file($_FILES['imagen']['tmp_name'], $directorio . $imagen_nombre);
            }
            
            // Actualizar stock solo si es materia prima o terminado
            $stock_actual = $producto['stock_actual'];
            if ($producto['tipo_producto'] !== 'compuesto' && isset($_POST['stock_actual'])) {
                $nuevo_stock = floatval($_POST['stock_actual']);
                if ($nuevo_stock != $stock_actual) {
                    $diferencia = $nuevo_stock - $stock_actual;
                    if ($diferencia > 0) {
                        registrarMovimiento($id, 'entrada', abs($diferencia), $_SESSION['usuario_id'], null, 'Ajuste manual');
                    } else {
                        registrarMovimiento($id, 'ajuste', abs($diferencia), $_SESSION['usuario_id'], null, 'Ajuste manual');
                    }
                    $stock_actual = $nuevo_stock;
                }
            }
            
            // Actualizar producto
            $stmt = $db->prepare("
                UPDATE productos SET 
                    nombre = ?, descripcion = ?, precio_base_usd = ?, unidad_medida = ?,
                    stock_actual = ?, stock_minimo = ?, categoria_id = ?, imagen = ?, estado = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $nombre, $descripcion, $precio_usd, $unidad_medida,
                $stock_actual, $stock_minimo, $categoria_id, $imagen_nombre, $estado, $id
            ]);
            
            // Actualizar receta si es compuesto
            if ($producto['tipo_producto'] === 'compuesto' && !empty($_POST['ingredientes'])) {
                $db->prepare("DELETE FROM recetas WHERE producto_compuesto_id = ?")->execute([$id]);
                
                $ingredientes = $_POST['ingredientes'];
                $cantidades = $_POST['cantidades'] ?? [];
                $unidades = $_POST['unidades'] ?? [];
                
                foreach ($ingredientes as $i => $mp_id) {
                    if ($mp_id && isset($cantidades[$i])) {
                        $receta = $db->prepare("
                            INSERT INTO recetas (producto_compuesto_id, materia_prima_id, cantidad_requerida, unidad_medida_receta)
                            VALUES (?, ?, ?, ?)
                        ");
                        $receta->execute([$id, $mp_id, floatval($cantidades[$i]), $unidades[$i] ?? 'unidad']);
                    }
                }
            }
            
            registrarLog("EDITAR_PRODUCTO", "Producto ID {$id} actualizado");
            
            $resultado['success'] = true;
            $resultado['mensaje'] = 'Producto actualizado exitosamente';
            break;
            
        case 'eliminar':
            requirePermiso('eliminar_productos', '../dashboard.php');
            
            $id = intval($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('ID inválido');
            }
            
            // Verificar si tiene pedidos asociados
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM detalle_pedido WHERE producto_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch()['total'] > 0) {
                // En lugar de eliminar, inactivar
                $stmt = $db->prepare("UPDATE productos SET estado = 'inactivo' WHERE id = ?");
                $stmt->execute([$id]);
                registrarLog("INACTIVAR_PRODUCTO", "Producto ID {$id} inactivado (tiene pedidos)");
            } else {
                $db->prepare("DELETE FROM recetas WHERE producto_compuesto_id = ? OR materia_prima_id = ?")->execute([$id, $id]);
                $db->prepare("DELETE FROM productos WHERE id = ?")->execute([$id]);
                registrarLog("ELIMINAR_PRODUCTO", "Producto ID {$id} eliminado");
            }
            
            $resultado['success'] = true;
            $resultado['mensaje'] = 'Producto eliminado';
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    $resultado['success'] = false;
    $resultado['mensaje'] = $e->getMessage();
    registrarLog("ERROR_PRODUCTO", $e->getMessage());
}

$tipo = $resultado['success'] ? 'success' : 'danger';
header("Location: index.php?msg=" . urlencode($resultado['mensaje']) . "&tipo={$tipo}");
exit;
