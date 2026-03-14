<?php
require_once __DIR__ . '/../../public/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitizar($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usuario) || empty($password)) {
        $_SESSION['error'] = 'Usuario y contraseña son requeridos';
        redireccionar('/restaurant-pos/public/index.php');
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, r.nombre as rol_nombre, r.permisos_json 
        FROM usuarios u 
        JOIN roles r ON u.rol_id = r.id 
        WHERE u.usuario = ? AND u.estado = 'activo'
    ");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario'] = $user['nombre'];
        $_SESSION['usuario_usuario'] = $user['usuario'];
        $_SESSION['rol_id'] = $user['rol_id'];
        $_SESSION['rol_nombre'] = $user['rol_nombre'];
        $_SESSION['permisos'] = json_decode($user['permisos_json'], true);
        
        $update = $db->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $update->execute([$user['id']]);
        
        logger("Usuario {$user['nombre']} inició sesión");
        redireccionar('/restaurant-pos/public/dashboard/index.php');
    } else {
        $_SESSION['error'] = 'Usuario o contraseña incorrectos';
        redireccionar('/restaurant-pos/public/index.php');
    }
} else {
    redireccionar('/restaurant-pos/public/index.php');
}
