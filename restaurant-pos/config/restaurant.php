<?php
/**
 * Funciones de Configuración del Restaurante
 * Sistema POS Restaurante
 */

require_once __DIR__ . '/../app/config/database.php';

/**
 * Obtiene una configuración por su clave
 */
function obtenerConfig($clave, $default = null) {
    static $configs = [];
    
    if (empty($configs)) {
        $db = getDB();
        $stmt = $db->query("SELECT clave, valor FROM configuracion");
        while ($row = $stmt->fetch()) {
            $configs[$row['clave']] = $row['valor'];
        }
    }
    
    return isset($configs[$clave]) ? $configs[$clave] : $default;
}

/**
 * Obtiene todos los datos del restaurante
 */
function obtenerDatosRestaurante() {
    return [
        'nombre' => obtenerConfig('nombre_restaurante', 'Restaurante POS'),
        'rif' => obtenerConfig('rif_restaurante', ''),
        'direccion' => obtenerConfig('direccion_restaurante', ''),
        'telefono' => obtenerConfig('telefono_restaurante', ''),
        'email' => obtenerConfig('email_restaurante', ''),
        'logo' => obtenerConfig('logo_restaurante', ''),
        'mensaje_pie' => obtenerConfig('mensaje_pie', 'Gracias por su visita'),
        'resolucion_fiscal' => obtenerConfig('resolucion_fiscal', '')
    ];
}

/**
 * Obtiene la configuración del sistema
 */
function obtenerConfiguracionSistema() {
    return [
        'nombre_sistema' => obtenerConfig('nombre_sistema', 'Sistema POS'),
        'version' => obtenerConfig('version_sistema', '1.0.0'),
        'timeout_sesion' => obtenerConfig('timeout_sesion', 30),
        'productos_por_pagina' => obtenerConfig('productos_por_pagina', 10),
        'impuesto_porcentaje' => obtenerConfig('impuesto_porcentaje', 16),
        'iva_incluido' => obtenerConfig('iva_incluido', 1)
    ];
}

/**
 * Actualiza una configuración
 */
function actualizarConfig($clave, $valor) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO configuracion (clave, valor) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()
    ");
    return $stmt->execute([$clave, $valor]);
}

/**
 * Actualiza los datos del restaurante
 */
function actualizarDatosRestaurante($datos, $usuarioId) {
    $campos = [
        'nombre_restaurante' => $datos['nombre'],
        'rif_restaurante' => $datos['rif'],
        'direccion_restaurante' => $datos['direccion'],
        'telefono_restaurante' => $datos['telefono'],
        'email_restaurante' => $datos['email'],
        'mensaje_pie' => $datos['mensaje_pie'],
        'resolucion_fiscal' => $datos['resolucion_fiscal'] ?? ''
    ];
    
    try {
        foreach ($campos as $clave => $valor) {
            actualizarConfig($clave, $valor);
        }
        
        // Actualizar logo si se proporciona
        if (!empty($datos['logo'])) {
            actualizarConfig('logo_restaurante', $datos['logo']);
        }
        
        registrarLog("CONFIG_RESTAURANTE", "Datos del restaurante actualizados por usuario ID: {$usuarioId}");
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Actualiza la configuración del sistema
 */
function actualizarConfiguracionSistema($datos, $usuarioId) {
    $campos = [
        'nombre_sistema' => $datos['nombre_sistema'],
        'timeout_sesion' => intval($datos['timeout_sesion']),
        'productos_por_pagina' => intval($datos['productos_por_pagina']),
        'impuesto_porcentaje' => floatval($datos['impuesto_porcentaje']),
        'iva_incluido' => isset($datos['iva_incluido']) ? 1 : 0
    ];
    
    try {
        foreach ($campos as $clave => $valor) {
            actualizarConfig($clave, $valor);
        }
        
        registrarLog("CONFIG_SISTEMA", "Configuración del sistema actualizada por usuario ID: {$usuarioId}");
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Sube el logo del restaurante
 */
function subirLogo($archivo) {
    $directorio = __DIR__ . '/../assets/uploads/logo/';
    
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }
    
    // Validar tipo de archivo
    $permitidos = ['image/jpeg', 'image/png', 'image/jpg'];
    $tipoArchivo = mime_content_type($archivo['tmp_name']);
    
    if (!in_array($tipoArchivo, $permitidos)) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido. Solo JPEG y PNG'];
    }
    
    // Validar tamaño (2MB máximo)
    if ($archivo['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'error' => 'El archivo no puede superar 2MB'];
    }
    
    // Generar nombre único
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombreArchivo = 'logo_' . time() . '.' . $extension;
    $rutaDestino = $directorio . $nombreArchivo;
    
    if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        // Eliminar logo anterior si existe
        $logoActual = obtenerConfig('logo_restaurante');
        if ($logoActual && file_exists($directorio . $logoActual)) {
            unlink($directorio . $logoActual);
        }
        
        return ['success' => true, 'archivo' => $nombreArchivo];
    }
    
    return ['success' => false, 'error' => 'Error al subir el archivo'];
}

/**
 * Valida el RIF venezolano
 */
function validarRIF($rif) {
    $rif = strtoupper(trim($rif));
    $patron = '/^[JVEPGDC]-[0-9]{6,8}-[0-9]$/';
    
    if (!preg_match($patron, $rif)) {
        return ['valid' => false, 'error' => 'Formato de RIF inválido. Ejemplo: J-12345678-9'];
    }
    
    return ['valid' => true];
}

/**
 * Valida teléfono venezolano
 */
function validarTelefono($telefono) {
    $telefono = preg_replace('/[^0-9]/', '', $telefono);
    
    if (strlen($telefono) != 11 && strlen($telefono) != 10) {
        return ['valid' => false, 'error' => 'Teléfono inválido'];
    }
    
    // Formato: 0412-1234567 o 4121234567
    return ['valid' => true, 'telefono' => $telefono];
}

/**
 * Valida email
 */
function validarEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'error' => 'Email inválido'];
    }
    
    return ['valid' => true];
}
