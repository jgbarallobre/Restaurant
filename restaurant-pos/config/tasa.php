<?php
/**
 * Funciones de Tasa de Cambio
 * Sistema POS Restaurante
 */

require_once __DIR__ . '/../app/config/database.php';

/**
 * Obtiene la tasa de cambio actual
 */
function obtenerTasaActual() {
    $db = getDB();
    $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_cambio_dia'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ? floatval($result['valor']) : 0;
}

/**
 * Obtiene la fecha de última actualización de la tasa
 */
function obtenerFechaTasa() {
    $db = getDB();
    $stmt = $db->query("SELECT MAX(fecha) as ultima_fecha FROM tasa_cambio_historico");
    $result = $stmt->fetch();
    return $result['ultima_fecha'] ?? null;
}

/**
 * Obtiene información de la última actualización de tasa
 */
function obtenerInfoTasa() {
    $db = getDB();
    $stmt = $db->query("
        SELECT t.*, u.nombre as usuario_nombre 
        FROM tasa_cambio_historico t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        ORDER BY t.fecha DESC, t.id DESC
        LIMIT 1
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Determina el estado de la tasa (verde/amarillo/rojo)
 */
function obtenerEstadoTasa() {
    $info = obtenerInfoTasa();
    if (!$info) return ['estado' => 'rojo', 'dias' => 0, 'color' => 'danger'];
    
    $fecha = new DateTime($info['fecha']);
    $hoy = new DateTime();
    $dias = $fecha->diff($hoy)->days;
    
    if ($dias == 0) {
        return ['estado' => 'verde', 'dias' => 0, 'color' => 'success', 'texto' => 'Actualizada hoy'];
    } elseif ($dias == 1) {
        return ['estado' => 'amarillo', 'dias' => 1, 'color' => 'warning', 'texto' => 'Hace 1 día'];
    } else {
        return ['estado' => 'rojo', 'dias' => $dias, 'color' => 'danger', 'texto' => "Hace {$dias} días"];
    }
}

/**
 * Actualiza la tasa de cambio
 */
function actualizarTasa($nuevaTasa, $usuarioId) {
    $db = getDB();
    
    // Obtener tasa anterior
    $tasaAnterior = obtenerTasaActual();
    
    try {
        $db->beginTransaction();
        
        // Actualizar configuración
        $stmt = $db->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'tasa_cambio_dia'");
        $stmt->execute([$nuevaTasa]);
        
        // Registrar en historial
        $historial = $db->prepare("
            INSERT INTO tasa_cambio_historico (tasa, fecha, usuario_id) 
            VALUES (?, NOW(), ?)
        ");
        $historial->execute([$nuevaTasa, $usuarioId]);
        
        $db->commit();
        
        registrarLog("TASA_CAMBIO", "Tasa actualizada de {$tasaAnterior} a {$nuevaTasa}");
        
        return ['success' => true, 'tasa_anterior' => $tasaAnterior, 'tasa_nueva' => $nuevaTasa];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtiene historial de tasas con filtros
 */
function obtenerHistorialTasas($fechaInicio = null, $fechaFin = null, $limit = 50, $offset = 0) {
    $db = getDB();
    
    $where = "1=1";
    $params = [];
    
    if ($fechaInicio) {
        $where .= " AND DATE(t.fecha) >= ?";
        $params[] = $fechaInicio;
    }
    
    if ($fechaFin) {
        $where .= " AND DATE(t.fecha) <= ?";
        $params[] = $fechaFin;
    }
    
    $sql = "
        SELECT t.*, u.nombre as usuario_nombre,
        LAG(t.tasa) OVER (ORDER BY t.fecha DESC) as tasa_anterior
        FROM tasa_cambio_historico t
        LEFT JOIN usuarios u ON t.usuario_id = u.id
        WHERE {$where}
        ORDER BY t.fecha DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Cuenta total de registros en historial
 */
function contarHistorialTasas($fechaInicio = null, $fechaFin = null) {
    $db = getDB();
    
    $where = "1=1";
    $params = [];
    
    if ($fechaInicio) {
        $where .= " AND DATE(fecha) >= ?";
        $params[] = $fechaInicio;
    }
    
    if ($fechaFin) {
        $where .= " AND DATE(fecha) <= ?";
        $params[] = $fechaFin;
    }
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM tasa_cambio_historico WHERE {$where}");
    $stmt->execute($params);
    return $stmt->fetch()['total'];
}

/**
 * Obtiene tasas de los últimos 7 días para gráfico
 */
function obtenerTasasUltimosDias($dias = 7) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT DATE(fecha) as fecha, tasa 
        FROM tasa_cambio_historico 
        WHERE fecha >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(fecha)
        ORDER BY fecha ASC
    ");
    $stmt->execute([$dias]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Valida que la tasa sea válida
 */
function validarTasa($tasa) {
    $tasa = floatval(str_replace(',', '.', $tasa));
    
    if ($tasa <= 0) {
        return ['valid' => false, 'error' => 'La tasa debe ser mayor a 0'];
    }
    
    if ($tasa < 0.01) {
        return ['valid' => false, 'error' => 'La tasa mínima es 0.01'];
    }
    
    if ($tasa > 9999.99) {
        return ['valid' => false, 'error' => 'La tasa máxima es 9999.99'];
    }
    
    // Verificar que tenga máximo 2 decimales
    if (round($tasa, 2) != $tasa) {
        return ['valid' => false, 'error' => 'La tasa debe tener máximo 2 decimales'];
    }
    
    // Verificar que no sea igual a la actual
    $tasaActual = obtenerTasaActual();
    if ($tasa == $tasaActual) {
        return ['valid' => false, 'error' => 'La tasa debe ser diferente a la actual'];
    }
    
    return ['valid' => true, 'tasa' => $tasa];
}
