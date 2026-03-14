<?php
/**
 * Sistema de Permisos
 * Sistema POS Restaurante
 */

require_once __DIR__ . '/../config/session.php';

/**
 * Lista de permisos disponibles en el sistema
 */
function getPermisosDisponibles() {
    return [
        // Usuarios
        'ver_usuarios' => 'Ver usuarios',
        'crear_usuarios' => 'Crear usuarios',
        'editar_usuarios' => 'Editar usuarios',
        'eliminar_usuarios' => 'Eliminar usuarios',
        
        // Productos
        'ver_productos' => 'Ver productos',
        'crear_productos' => 'Crear productos',
        'editar_productos' => 'Editar productos',
        'eliminar_productos' => 'Eliminar productos',
        
        // Pedidos
        'ver_pedidos' => 'Ver pedidos',
        'crear_pedidos' => 'Crear pedidos',
        'editar_pedidos' => 'Editar pedidos',
        'cobrar_pedidos' => 'Cobrar pedidos',
        
        // Mesas
        'ver_mesas' => 'Ver mesas',
        'gestionar_mesas' => 'Gestionar mesas',
        
        // Inventario
        'ver_inventario' => 'Ver inventario',
        'gestionar_inventario' => 'Gestionar inventario',
        
        // Reportes
        'ver_reportes' => 'Ver reportes',
        'ver_caja' => 'Ver caja',
        
        // Configuración
        'ver_configuracion' => 'Ver configuración',
        'modificar_tasa' => 'Modificar tasa de cambio',
        'gestionar_roles' => 'Gestionar roles'
    ];
}

/**
 * Verifica si el usuario tiene un permiso específico
 */
function tienePermiso($permiso) {
    if (!verificarSesion()) {
        return false;
    }
    
    $permisos = $_SESSION['permisos'] ?? [];
    
    if (isset($permisos['todos']) && $permisos['todos'] === true) {
        return true;
    }
    
    return isset($permisos[$permiso]) && $permisos[$permiso] === true;
}

/**
 * Verifica si el usuario tiene alguno de los permisos dados
 */
function tieneAlgunPermiso($permisos) {
    foreach ($permisos as $permiso) {
        if (tienePermiso($permiso)) {
            return true;
        }
    }
    return false;
}

/**
 * Verifica si el usuario tiene todos los permisos dados
 */
function tieneTodosLosPermisos($permisos) {
    foreach ($permisos as $permiso) {
        if (!tienePermiso($permiso)) {
            return false;
        }
    }
    return true;
}

/**
 * Obtiene los permisos del usuario actual
 */
function getPermisosUsuario() {
    return $_SESSION['permisos'] ?? [];
}

/**
 * Obtiene el nombre del rol del usuario actual
 */
function getRolUsuario() {
    return $_SESSION['rol_nombre'] ?? 'Invitado';
}

/**
 * Obtiene el ID del rol del usuario actual
 */
function getRolId() {
    return $_SESSION['rol_id'] ?? 0;
}

/**
 * Verifica acceso y redirige si no tiene permiso
 */
function requirePermiso($permiso, $redirect = 'dashboard.php') {
    if (!tienePermiso($permiso)) {
        $_SESSION['error'] = 'No tienes permiso para realizar esta acción';
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Verifica acceso por rol y redirige si no coincide
 */
function requireRol($roles, $redirect = 'dashboard.php') {
    if (!verificarSesion()) {
        header('Location: login.php');
        exit;
    }
    
    $rolActual = getRolId();
    
    if (!in_array($rolActual, (array)$roles)) {
        $_SESSION['error'] = 'No tienes acceso a esta sección';
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Genera el menú según los permisos del usuario
 */
function generarMenu() {
    $menu = [];
    
    if (tienePermiso('ver_pedidos') || tienePermiso('crear_pedidos')) {
        $menu['pedidos'] = [
            'icono' => 'bi-receipt',
            'titulo' => 'Pedidos',
            'url' => 'pedidos/',
            'permiso' => 'ver_pedidos'
        ];
    }
    
    if (tienePermiso('ver_productos')) {
        $menu['productos'] = [
            'icono' => 'bi-box-seam',
            'titulo' => 'Productos',
            'url' => 'productos/',
            'permiso' => 'ver_productos'
        ];
    }
    
    if (tienePermiso('ver_mesas')) {
        $menu['mesas'] = [
            'icono' => 'bi-grid-3x3-gap',
            'titulo' => 'Mesas',
            'url' => 'mesas/',
            'permiso' => 'ver_mesas'
        ];
    }
    
    if (tienePermiso('ver_inventario')) {
        $menu['inventario'] = [
            'icono' => 'bi-archive',
            'titulo' => 'Inventario',
            'url' => 'inventario/',
            'permiso' => 'ver_inventario'
        ];
    }
    
    if (tienePermiso('ver_reportes')) {
        $menu['reportes'] = [
            'icono' => 'bi-graph-up',
            'titulo' => 'Reportes',
            'url' => 'reportes/',
            'permiso' => 'ver_reportes'
        ];
    }
    
    if (tienePermiso('ver_usuarios')) {
        $menu['usuarios'] = [
            'icono' => 'bi-people',
            'titulo' => 'Usuarios',
            'url' => 'usuarios/',
            'permiso' => 'ver_usuarios'
        ];
    }
    
    if (tienePermiso('ver_configuracion')) {
        $menu['configuracion'] = [
            'icono' => 'bi-gear',
            'titulo' => 'Configuración',
            'url' => 'configuracion/',
            'permiso' => 'ver_configuracion'
        ];
    }
    
    return $menu;
}

/**
 * Obtiene la configuración de permisos para un rol
 */
function getPermisosRol($rolId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT permisos_json FROM roles WHERE id = ?");
    $stmt->execute([$rolId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['permisos_json']) {
        return json_decode($result['permisos_json'], true);
    }
    
    return [];
}

/**
 * Actualiza los permisos de un rol
 */
function actualizarPermisosRol($rolId, $permisos) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE roles SET permisos_json = ? WHERE id = ?");
    return $stmt->execute([json_encode($permisos), $rolId]);
}

/**
 * Define los permisos por defecto para cada rol
 */
function getPermisosPorDefecto() {
    return [
        'administrador' => ['todos' => true],
        
        'mesonero' => [
            'ver_pedidos' => true,
            'crear_pedidos' => true,
            'editar_pedidos' => true,
            'ver_mesas' => true,
            'gestionar_mesas' => true,
            'ver_productos' => true
        ],
        
        'cajero' => [
            'ver_pedidos' => true,
            'crear_pedidos' => true,
            'cobrar_pedidos' => true,
            'ver_caja' => true,
            'ver_reportes' => true,
            'ver_productos' => true,
            'ver_mesas' => true
        ],
        
        'cocina' => [
            'ver_pedidos' => true,
            'editar_pedidos' => true,
            'ver_inventario' => true,
            'ver_productos' => true
        ]
    ];
}
