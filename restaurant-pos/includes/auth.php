<?php
/**
 * Funciones de Autenticación
 * Sistema POS Restaurante
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

/**
 * Autentica un usuario con usuario y contraseña
 * @param string $usuario Nombre de usuario
 * @param string $password Contraseña
 * @return array|false Usuario encontrado o false si falla
 */
function autenticarUsuario($usuario, $password) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT u.*, r.nombre as rol_nombre, r.permisos_json 
        FROM usuarios u 
        INNER JOIN roles r ON u.rol_id = r.id 
        WHERE u.usuario = ? AND u.estado = 'activo'
    ");
    $stmt->execute([sanitizar($usuario)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['error' => 'Usuario no encontrado o inactivo'];
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return ['error' => 'Contraseña incorrecta'];
    }
    
    if ($user['estado'] !== 'activo') {
        return ['error' => 'Usuario inactivo. Contacte al administrador'];
    }
    
    return $user;
}

/**
 * Procesa el login del usuario
 * @return array Resultado del login
 */
function procesarLogin() {
    $resultado = ['success' => false, 'redirect' => '', 'error' => ''];
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $resultado['error'] = 'Método no válido';
        return $resultado;
    }
    
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $recordar = isset($_POST['recordar']);
    
    if (empty($usuario) || empty($password)) {
        $resultado['error'] = 'Debe ingresar usuario y contraseña';
        return $resultado;
    }
    
    $auth = autenticarUsuario($usuario, $password);
    
    if (isset($auth['error'])) {
        $resultado['error'] = $auth['error'];
        return $resultado;
    }
    
    try {
        $db = getDB();
        $db->beginTransaction();
        
        iniciarSesion($auth, $recordar);
        regenerarSesion();
        
        $update = $db->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $update->execute([$auth['id']]);
        
        $db->commit();
        
        registrarLog("LOGIN", "Usuario {$auth['nombre']} inició sesión");
        
        $resultado['success'] = true;
        $resultado['redirect'] = obtenerRedirectPorRol($auth['rol_id']);
        
    } catch (Exception $e) {
        $db->rollBack();
        $resultado['error'] = 'Error al iniciar sesión';
        registrarLog("LOGIN_ERROR", $e->getMessage());
    }
    
    return $resultado;
}

/**
 * Procesa el logout del usuario
 */
function procesarLogout() {
    $usuario = cerrarSesion();
    registrarLog("LOGOUT", "Usuario {$usuario} cerró sesión");
    header('Location: login.php');
    exit;
}

/**
 * Obtiene la URL de redirección según el rol del usuario
 */
function obtenerRedirectPorRol($rolId) {
    $redirecciones = [
        1 => 'dashboard.php',        // Administrador
        2 => 'dashboard.php',        // Admin
        3 => 'dashboard.php',        // Cajero
        4 => 'dashboard.php',        // Cocinero
        5 => 'dashboard.php'         // Mesonero
    ];
    
    return $redirecciones[$rolId] ?? 'login.php';
}

/**
 * Encripta una contraseña usando bcrypt
 */
function encriptarPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifica si una contraseña coincide con su hash
 */
function verificarPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Valida que el nombre de usuario sea único
 */
function usuarioUnico($usuario, $excluirId = null) {
    $db = getDB();
    
    if ($excluirId) {
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
        $stmt->execute([sanitizar($usuario), $excluirId]);
    } else {
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmt->execute([sanitizar($usuario)]);
    }
    
    return $stmt->fetch() === false;
}

/**
 * Registra una acción en el log
 */
function registrarLog($accion, $detalle) {
    $logFile = __DIR__ . '/../logs/actividad.log';
    $fecha = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $usuario = $_SESSION['usuario_nombre'] ?? 'Sistema';
    
    $log = "[{$fecha}] [{$ip}] [{$usuario}] [{$accion}] {$detalle}\n";
    file_put_contents($logFile, $log, FILE_APPEND);
}

/**
 * Sanitiza datos para prevenir XSS
 */
function sanitizar($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}
