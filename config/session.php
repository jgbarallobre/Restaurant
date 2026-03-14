<?php
/**
 * Configuración de Sesiones Seguras
 * Sistema POS Restaurante
 */

// Configuración de cookies de sesión seguras (debe estar antes de session_start)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();

define('SESSION_TIMEOUT', 1800); // 30 minutos
define('SESSION_REMEMBER', 28800); // 8 horas (si selecciona "recordarme")

/**
 * Verifica si la sesión está activa y no ha expirado
 */
function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }
    
    if (isset($_SESSION['ultima_actividad'])) {
        $tiempo_inactivo = time() - $_SESSION['ultima_actividad'];
        if ($tiempo_inactivo > SESSION_TIMEOUT) {
            cerrarSesion();
            return false;
        }
    }
    
    $_SESSION['ultima_actividad'] = time();
    return true;
}

/**
 * Inicializa la sesión del usuario después del login
 */
function iniciarSesion($usuario, $recordar = false) {
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_usuario'] = $usuario['usuario'];
    $_SESSION['rol_id'] = $usuario['rol_id'];
    $_SESSION['rol_nombre'] = $usuario['rol_nombre'];
    $_SESSION['permisos'] = json_decode($usuario['permisos_json'], true);
    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['ultima_actividad'] = time();
    $_SESSION['inicio_sesion'] = time();
    
    if ($recordar) {
        $_SESSION['recordar'] = true;
    }
}

/**
 * Cierra la sesión del usuario
 */
function cerrarSesion() {
    $usuario = $_SESSION['usuario_nombre'] ?? 'Desconocido';
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    return $usuario;
}

/**
 * Regenera el ID de sesión para prevenir hijacking
 */
function regenerarSesion() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Verifica si el usuario tiene acceso desde la misma IP
 */
function verificarIP() {
    if (!isset($_SESSION['ip'])) {
        return true;
    }
    return $_SESSION['ip'] === $_SERVER['REMOTE_ADDR'];
}

/**
 * Obtiene el tiempo restante de sesión en segundos
 */
function tiempoRestanteSesion() {
    if (!isset($_SESSION['ultima_actividad'])) {
        return 0;
    }
    return SESSION_TIMEOUT - (time() - $_SESSION['ultima_actividad']);
}
