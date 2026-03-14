<?php
/**
 * Script de diagnóstico de login
 */

require_once 'app/config/database.php';

echo "<h2>Diagnóstico de Login</h2>";

// 1. Verificar conexión
echo "<h3>1. Conexión a BD</h3>";
try {
    $db = getDB();
    echo "✓ Conexión exitosa<br>";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
    exit;
}

// 2. Verificar tabla roles
echo "<h3>2. Tabla roles</h3>";
$stmt = $db->query("SELECT * FROM roles");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Registros: " . count($roles) . "<br>";
foreach ($roles as $r) {
    echo "- ID: {$r['id']}, Nombre: {$r['nombre']}<br>";
}

// 3. Verificar tabla usuarios
echo "<h3>3. Tabla usuarios</h3>";
$stmt = $db->query("SELECT id, nombre, usuario, estado, rol_id FROM usuarios");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Registros: " . count($usuarios) . "<br>";
foreach ($usuarios as $u) {
    echo "- ID: {$u['id']}, Usuario: {$u['usuario']}, Estado: {$u['estado']}, Rol ID: {$u['rol_id']}<br>";
}

// 4. Probar autenticación
echo "<h3>4. Prueba de autenticación</h3>";
$stmt = $db->prepare("SELECT * FROM usuarios WHERE usuario = 'admin' AND estado = 'activo'");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✓ Usuario 'admin' encontrado<br>";
    echo "Hash en BD: " . substr($user['password_hash'], 0, 50) . "...<br>";
    
    // Probar password_verify
    $testPassword = 'admin123';
    if (password_verify($testPassword, $user['password_hash'])) {
        echo "✓ Password 'admin123' es CORRECTO<br>";
    } else {
        echo "✗ Password 'admin123' es INCORRECTO<br>";
        
        // Probar con otros passwords
        $testPasswords = ['password', 'admin', '123456', 'admin1234'];
        foreach ($testPasswords as $p) {
            if (password_verify($p, $user['password_hash'])) {
                echo "✓ Password correcto es: $p<br>";
            }
        }
    }
} else {
    echo "✗ Usuario 'admin' no encontrado o inactivo<br>";
}

echo "<h3>5. JOIN test</h3>";
$stmt = $db->query("
    SELECT u.*, r.nombre as rol_nombre, r.permisos_json 
    FROM usuarios u 
    INNER JOIN roles r ON u.rol_id = r.id 
    WHERE u.usuario = 'admin' AND u.estado = 'activo'
");
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✓ JOIN exitoso - Usuario: {$user['nombre']}, Rol: {$user['rol_nombre']}<br>";
} else {
    echo "✗ JOIN falló - possible rol_id no coincide<br>";
}
