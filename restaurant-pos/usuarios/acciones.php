<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (!verificarSesion()) {
    header('Location: ../login.php');
    exit;
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$resultado = ['success' => false, 'mensaje' => ''];

try {
    $db = getDB();
    
    switch ($accion) {
        case 'crear':
            requirePermiso('crear_usuarios');
            
            $nombre = sanitizar($_POST['nombre'] ?? '');
            $usuario = sanitizar($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmar_password = $_POST['confirmar_password'] ?? '';
            $rol_id = intval($_POST['rol_id'] ?? 0);
            
            // Validaciones
            if (empty($nombre) || strlen($nombre) < 3) {
                throw new Exception('El nombre debe tener al menos 3 caracteres');
            }
            
            if (empty($usuario) || strlen($usuario) < 4) {
                throw new Exception('El usuario debe tener al menos 4 caracteres');
            }
            
            if (!usuarioUnico($usuario)) {
                throw new Exception('El nombre de usuario ya existe');
            }
            
            if (empty($password) || strlen($password) < 6) {
                throw new Exception('La contraseña debe tener al menos 6 caracteres');
            }
            
            if ($password !== $confirmar_password) {
                throw new Exception('Las contraseñas no coinciden');
            }
            
            if ($rol_id <= 0) {
                throw new Exception('Debe seleccionar un rol');
            }
            
            $password_hash = encriptarPassword($password);
            
            $stmt = $db->prepare("
                INSERT INTO usuarios (nombre, usuario, password_hash, rol_id, estado)
                VALUES (?, ?, ?, ?, 'activo')
            ");
            $stmt->execute([$nombre, $usuario, $password_hash, $rol_id]);
            
            $nuevo_id = $db->lastInsertId();
            registrarLog("CREAR_USUARIO", "Usuario {$nombre} ({$usuario}) creado con ID {$nuevo_id}");
            
            $resultado['success'] = true;
            $resultado['mensaje'] = 'Usuario creado exitosamente';
            break;
            
        case 'editar':
            requirePermiso('editar_usuarios');
            
            $id = intval($_POST['id'] ?? 0);
            $nombre = sanitizar($_POST['nombre'] ?? '');
            $usuario = sanitizar($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';
            $rol_id = intval($_POST['rol_id'] ?? 0);
            $estado = sanitizar($_POST['estado'] ?? 'activo');
            
            // Validaciones
            if ($id <= 0) {
                throw new Exception('ID de usuario inválido');
            }
            
            if (empty($nombre) || strlen($nombre) < 3) {
                throw new Exception('El nombre debe tener al menos 3 caracteres');
            }
            
            if (empty($usuario) || strlen($usuario) < 4) {
                throw new Exception('El usuario debe tener al menos 4 caracteres');
            }
            
            if (!usuarioUnico($usuario, $id)) {
                throw new Exception('El nombre de usuario ya existe');
            }
            
            if ($rol_id <= 0) {
                throw new Exception('Debe seleccionar un rol');
            }
            
            // Verificar si es el admin principal
            if ($id == 1) {
                $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, usuario = ? WHERE id = ?");
                $stmt->execute([$nombre, $usuario, $id]);
                registrarLog("EDITAR_USUARIO", "Usuario admin principal actualizado");
            } else {
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        throw new Exception('La contraseña debe tener al menos 6 caracteres');
                    }
                    $password_hash = encriptarPassword($password);
                    $stmt = $db->prepare("
                        UPDATE usuarios 
                        SET nombre = ?, usuario = ?, password_hash = ?, rol_id = ?, estado = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre, $usuario, $password_hash, $rol_id, $estado, $id]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE usuarios 
                        SET nombre = ?, usuario = ?, rol_id = ?, estado = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre, $usuario, $rol_id, $estado, $id]);
                }
                registrarLog("EDITAR_USUARIO", "Usuario ID {$id} actualizado");
            }
            
            $resultado['success'] = true;
            $resultado['mensaje'] = 'Usuario actualizado exitosamente';
            break;
            
        case 'activar':
            requirePermiso('editar_usuarios');
            
            $id = intval($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('ID de usuario inválido');
            }
            
            if ($id == 1) {
                throw new Exception('No se puede modificar el usuario administrador principal');
            }
            
            $stmt = $db->prepare("UPDATE usuarios SET estado = 'activo' WHERE id = ?");
            $stmt->execute([$id]);
            
            registrarLog("ACTIVAR_USUARIO", "Usuario ID {$id} activado");
            
            $resultado['success'] = true;
            $resultado['mensaje'] = 'Usuario activado exitosamente';
            break;
            
        case 'desactivar':
            requirePermiso('editar_usuarios');
            
            $id = intval($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('ID de usuario inválido');
            }
            
            if ($id == 1) {
                throw new Exception('No se puede desactivar el usuario administrador principal');
            }
            
            if ($id == $_SESSION['usuario_id']) {
                throw new Exception('No puede desactivarse a sí mismo');
            }
            
            $stmt = $db->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = ?");
            $stmt->execute([$id]);
            
            registrarLog("DESACTIVAR_USUARIO", "Usuario ID {$id} desactivado");
            
            $resultado['success'] = true;
            $resultado['mensaje'] = 'Usuario desactivado exitosamente';
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    $resultado['success'] = false;
    $resultado['mensaje'] = $e->getMessage();
    registrarLog("ERROR_USUARIO", $e->getMessage());
}

// Redireccionar con mensaje
$tipo = $resultado['success'] ? 'success' : 'danger';
$redirect = $resultado['success'] ? 'index.php' : 'index.php';

header("Location: {$redirect}?msg=" . urlencode($resultado['mensaje']) . "&tipo={$tipo}");
exit;
