<?php
/**
 * Configuración General del Sistema
 * Sistema POS Restaurante
 */

define('APP_NAME', 'Restaurante POS');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development');
define('APP_URL', 'http://localhost/restaurant-pos');

session_start();

date_default_timezone_set('America/Caracas');

define('MONEDA_SIMBOLO', 'Bs');
define('MONEDA_SIMBOLO_USD', '$');

function getTasaCambio() {
    $db = getDB();
    $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_cambio_dia'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ? floatval($result['valor']) : 0;
}

function getConfig($clave, $default = null) {
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

function formatearPrecio($precioBs, $precioUsd = null, $incluirUsd = true) {
    $tasa = getTasaCambio();
    $bs = number_format($precioBs, 2, ',', '.');
    
    if ($precioUsd !== null && $incluirUsd) {
        return "{$bs} Bs (\${$precioUsd})";
    }
    
    return "{$bs} Bs";
}

function convertirABs($montoUsd) {
    $tasa = getTasaCambio();
    return $montoUsd * $tasa;
}

function getUsuarioActual() {
    return isset($_SESSION['usuario_id']) ? $_SESSION : null;
}

function isLoggedIn() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario']);
}

function verificarPermiso($permiso) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $rolPermisos = $_SESSION['permisos'] ?? [];
    
    if (isset($rolPermisos['todos']) && $rolPermisos['todos'] === true) {
        return true;
    }
    
    return isset($rolPermisos[$permiso]);
}

function redireccionar($ruta) {
    header("Location: " . APP_URL . $ruta);
    exit;
}

function sanitizar($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

function logger($mensaje, $nivel = 'INFO') {
    $fecha = date('Y-m-d H:i:s');
    $log = "[{$fecha}] [{$nivel}] {$mensaje}\n";
    file_put_contents(__DIR__ . '/../../logs/app.log', $log, FILE_APPEND);
}
