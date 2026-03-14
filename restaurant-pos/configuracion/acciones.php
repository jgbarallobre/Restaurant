<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/tasa.php';
require_once __DIR__ . '/../config/restaurant.php';

if (!verificarSesion()) {
    header('Location: ../login.php');
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$resultado = ['success' => false, 'mensaje' => ''];

try {
    switch ($accion) {
        case 'actualizar_tasa':
            requirePermiso('modificar_tasa', '../dashboard.php');
            
            $tasa = $_POST['tasa'] ?? '';
            $validacion = validarTasa($tasa);
            
            if (!$validacion['valid']) {
                throw new Exception($validacion['error']);
            }
            
            $result = actualizarTasa($validacion['tasa'], $_SESSION['usuario_id']);
            
            if ($result['success']) {
                $resultado['success'] = true;
                $resultado['mensaje'] = 'Tasa actualizada exitosamente';
            } else {
                throw new Exception('Error al actualizar la tasa');
            }
            break;
            
        case 'datos_restaurante':
            requirePermiso('gestionar_roles', '../dashboard.php');
            
            $datos = [
                'nombre' => sanitizar($_POST['nombre'] ?? ''),
                'rif' => sanitizar($_POST['rif'] ?? ''),
                'direccion' => sanitizar($_POST['direccion'] ?? ''),
                'telefono' => sanitizar($_POST['telefono'] ?? ''),
                'email' => sanitizar($_POST['email'] ?? ''),
                'mensaje_pie' => sanitizar($_POST['mensaje_pie'] ?? ''),
                'resolucion_fiscal' => sanitizar($_POST['resolucion_fiscal'] ?? '')
            ];
            
            if (empty($datos['nombre']) || strlen($datos['nombre']) < 3) {
                throw new Exception('El nombre debe tener al menos 3 caracteres');
            }
            
            if (!empty($datos['email']) && !validarEmail($datos['email'])['valid']) {
                throw new Exception('Email inválido');
            }
            
            if (!empty($datos['rif'])) {
                $validacionRif = validarRIF($datos['rif']);
                if (!$validacionRif['valid']) {
                    throw new Exception($validacionRif['error']);
                }
            }
            
            // Subir logo si existe
            if (!empty($_FILES['logo']['name'])) {
                $resultadoLogo = subirLogo($_FILES['logo']);
                if (!$resultadoLogo['success']) {
                    throw new Exception($resultadoLogo['error']);
                }
                $datos['logo'] = $resultadoLogo['archivo'];
            }
            
            $result = actualizarDatosRestaurante($datos, $_SESSION['usuario_id']);
            
            if ($result['success']) {
                $resultado['success'] = true;
                $resultado['mensaje'] = 'Datos del restaurante actualizados';
            } else {
                throw new Exception($result['error']);
            }
            break;
            
        case 'config_sistema':
            requirePermiso('gestionar_roles', '../dashboard.php');
            
            $datos = [
                'nombre_sistema' => sanitizar($_POST['nombre_sistema'] ?? 'Sistema POS'),
                'timeout_sesion' => intval($_POST['timeout_sesion'] ?? 30),
                'productos_por_pagina' => intval($_POST['productos_por_pagina'] ?? 10),
                'impuesto_porcentaje' => floatval($_POST['impuesto_porcentaje'] ?? 16),
                'iva_incluido' => isset($_POST['iva_incluido']) ? 1 : 0
            ];
            
            if ($datos['timeout_sesion'] < 5 || $datos['timeout_sesion'] > 120) {
                throw new Exception('El timeout debe estar entre 5 y 120 minutos');
            }
            
            $result = actualizarConfiguracionSistema($datos, $_SESSION['usuario_id']);
            
            if ($result['success']) {
                $resultado['success'] = true;
                $resultado['mensaje'] = 'Configuración del sistema guardada';
            } else {
                throw new Exception($result['error']);
            }
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    $resultado['success'] = false;
    $resultado['mensaje'] = $e->getMessage();
}

$tipo = $resultado['success'] ? 'success' : 'danger';
header("Location: index.php?msg=" . urlencode($resultado['mensaje']) . "&tipo={$tipo}");
exit;
