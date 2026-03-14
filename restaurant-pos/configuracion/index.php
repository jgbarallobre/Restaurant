<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/tasa.php';
require_once __DIR__ . '/../config/restaurant.php';
require_once __DIR__ . '/../includes/functions.php';

if (!verificarSesion()) {
    header('Location: ../login.php');
    exit;
}

requirePermiso('ver_configuracion', '../dashboard.php');

$db = getDB();
$tasa = obtenerTasaActual();
$estadoTasa = obtenerEstadoTasa();
$infoTasa = obtenerInfoTasa();
$restaurante = obtenerDatosRestaurante();
$sistema = obtenerConfiguracionSistema();
$rolId = getRolId();
$rolNombre = getRolNombre();

$esAdmin = tienePermiso('modificar_tasa') || tienePermiso('gestionar_roles');

$menu = generarMenu();

$mensaje = $_GET['msg'] ?? '';
$tipo_msg = $_GET['tipo'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Sistema POS Restaurante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <i class="bi bi-shop"></i>
                <span>Restaurante POS</span>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
                <?php if (tienePermiso('ver_usuarios')): ?>
                <li class="nav-item"><a class="nav-link" href="../usuarios/"><i class="bi bi-people"></i><span>Usuarios</span></a></li>
                <?php endif; ?>
                <?php if (tienePermiso('ver_productos')): ?>
                <li class="nav-item"><a class="nav-link" href="../productos/"><i class="bi bi-box-seam"></i><span>Productos</span></a></li>
                <?php endif; ?>
                <?php if (tienePermiso('ver_mesas')): ?>
                <li class="nav-item"><a class="nav-link" href="../mesas/"><i class="bi bi-grid-3x3-gap"></i><span>Mesas</span></a></li>
                <?php endif; ?>
                <?php if (tienePermiso('ver_inventario')): ?>
                <li class="nav-item"><a class="nav-link" href="../inventario/"><i class="bi bi-archive"></i><span>Inventario</span></a></li>
                <?php endif; ?>
                <?php if (tienePermiso('ver_reportes')): ?>
                <li class="nav-item"><a class="nav-link" href="../reportes/"><i class="bi bi-graph-up"></i><span>Reportes</span></a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-gear"></i><span>Configuración</span></a></li>
                <li class="nav-item mt-auto"><a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-left"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </nav>

        <div class="main-content">
            <header class="header">
                <div class="d-flex align-items-center">
                    <button class="btn btn-toggle-sidebar me-3" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                            <li class="breadcrumb-item active">Configuración</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="tasa-badge bg-<?php echo $estadoTasa['color']; ?>">
                        <i class="bi bi-currency-exchange"></i>
                        <span><?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$</span>
                    </div>
                    <div class="user-info dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?php echo $_SESSION['usuario_nombre']; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text"><small class="text-muted">Rol: <?php echo $rolNombre; ?></small></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="content">
                <div class="container-fluid">
                    <h4 class="mb-4"><i class="bi bi-gear me-2"></i>Configuración del Sistema</h4>

                    <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Tabs -->
                    <ul class="nav nav-tabs" id="configTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="tasa-tab" data-bs-toggle="tab" data-bs-target="#tasa" type="button">
                                <i class="bi bi-currency-exchange me-2"></i>Tasa de Cambio
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="restaurante-tab" data-bs-toggle="tab" data-bs-target="#restaurante" type="button">
                                <i class="bi bi-shop me-2"></i>Datos del Restaurante
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="sistema-tab" data-bs-toggle="tab" data-bs-target="#sistema" type="button">
                                <i class="bi bi-sliders me-2"></i>Configuración del Sistema
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-4 bg-white border border-top-0 rounded-bottom">
                        
                        <!-- TAB 1: TASA DE CAMBIO -->
                        <div class="tab-pane fade show active" id="tasa" role="tabpanel">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="card mb-4">
                                        <div class="card-header bg-<?php echo $estadoTasa['color']; ?> text-white">
                                            <h5 class="mb-0"><i class="bi bi-currency-exchange me-2"></i>Tasa Actual</h5>
                                        </div>
                                        <div class="card-body text-center">
                                            <div class="display-4 fw-bold text-<?php echo $estadoTasa['color']; ?>">
                                                <?php echo number_format($tasa, 2, ',', '.'); ?>
                                                <small class="fs-6 text-muted">Bs/$</small>
                                            </div>
                                            <p class="text-muted mb-3"><?php echo $estadoTasa['texto']; ?></p>
                                            
                                            <?php if ($infoTasa): ?>
                                            <div class="text-start bg-light p-3 rounded">
                                                <p class="mb-1"><strong>Última actualización:</strong> <?php echo date('d/m/Y H:i', strtotime($infoTasa['fecha'])); ?></p>
                                                <p class="mb-0"><strong>Usuario:</strong> <?php echo $infoTasa['usuario_nombre']; ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($esAdmin): ?>
                                            <button class="btn btn-<?php echo $estadoTasa['color']; ?> btn-lg mt-3" data-bs-toggle="modal" data-bs-target="#modalTasa">
                                                <i class="bi bi-pencil me-2"></i>Actualizar Tasa
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Historial Reciente</h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <?php
                                            $historial = obtenerHistorialTasas(null, null, 5, 0);
                                            ?>
                                            <table class="table table-sm mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Fecha</th>
                                                        <th>Tasa</th>
                                                        <th>Usuario</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($historial as $h): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m H:i', strtotime($h['fecha'])); ?></td>
                                                        <td><strong><?php echo number_format($h['tasa'], 2, ',', '.'); ?></strong></td>
                                                        <td><?php echo $h['usuario_nombre']; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="card-footer">
                                            <a href="historial_tasas.php" class="btn btn-sm btn-outline-primary">
                                                Ver historial completo <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: DATOS DEL RESTAURANTE -->
                        <div class="tab-pane fade" id="restaurante" role="tabpanel">
                            <form method="POST" action="acciones.php" enctype="multipart/form-data" id="formRestaurante">
                                <input type="hidden" name="accion" value="datos_restaurante">
                                
                                <div class="row">
                                    <div class="col-md-4 text-center mb-4">
                                        <label class="form-label">Logo del Restaurante</label>
                                        <div class="bg-light rounded p-4 mb-2" style="max-width: 200px; margin: 0 auto;">
                                            <?php if (!empty($restaurante['logo'])): ?>
                                            <img src="../assets/uploads/logo/<?php echo $restaurante['logo']; ?>" alt="Logo" class="img-fluid" style="max-height: 120px;">
                                            <?php else: ?>
                                            <i class="bi bi-shop" style="font-size: 4rem; color: #ccc;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" name="logo" class="form-control form-control-sm" accept="image/png,image/jpeg">
                                        <small class="text-muted">Máximo 2MB, PNG o JPG</small>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre del Restaurante *</label>
                                            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($restaurante['nombre']); ?>" required minlength="3">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">RIF / NIT</label>
                                            <input type="text" name="rif" class="form-control" value="<?php echo htmlspecialchars($restaurante['rif']); ?>" placeholder="J-12345678-9">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Dirección</label>
                                            <textarea name="direccion" class="form-control" rows="2"><?php echo htmlspecialchars($restaurante['direccion']); ?></textarea>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Teléfono</label>
                                                <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($restaurante['telefono']); ?>" placeholder="0412-1234567">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($restaurante['email']); ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Mensaje Pie de Factura</label>
                                            <input type="text" name="mensaje_pie" class="form-control" value="<?php echo htmlspecialchars($restaurante['mensaje_pie']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($esAdmin): ?>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-2"></i>Guardar Datos
                                    </button>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- TAB 3: CONFIGURACIÓN DEL SISTEMA -->
                        <div class="tab-pane fade" id="sistema" role="tabpanel">
                            <form method="POST" action="acciones.php" id="formSistema">
                                <input type="hidden" name="accion" value="config_sistema">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre del Sistema</label>
                                            <input type="text" name="nombre_sistema" class="form-control" value="<?php echo htmlspecialchars($sistema['nombre_sistema']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Timeout de Sesión (minutos)</label>
                                            <input type="number" name="timeout_sesion" class="form-control" value="<?php echo $sistema['timeout_sesion']; ?>" min="5" max="120">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Productos por Página</label>
                                            <input type="number" name="productos_por_pagina" class="form-control" value="<?php echo $sistema['productos_por_pagina']; ?>" min="5" max="100">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Porcentaje de Impuesto (%)</label>
                                            <input type="number" name="impuesto_porcentaje" class="form-control" value="<?php echo $sistema['impuesto_porcentaje']; ?>" min="0" max="100" step="0.1">
                                        </div>
                                        <div class="mb-3 form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="iva_incluido" id="iva_incluido" <?php echo $sistema['iva_incluido'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="iva_incluido">IVA incluido en precios</label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Versión del Sistema</label>
                                            <input type="text" class="form-control" value="<?php echo $sistema['version']; ?>" disabled>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($esAdmin): ?>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-2"></i>Guardar Configuración
                                    </button>
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- MODAL ACTUALIZAR TASA -->
    <?php if ($esAdmin): ?>
    <div class="modal fade" id="modalTasa" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-currency-exchange me-2"></i>Actualizar Tasa de Cambio</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="acciones.php" id="formTasa">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="actualizar_tasa">
                        
                        <div class="mb-3">
                            <label class="form-label">Tasa Actual</label>
                            <div class="form-control-plaintext fw-bold">
                                <?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nueva Tasa (Bs por USD) *</label>
                            <input type="number" name="tasa" class="form-control form-control-lg" required 
                                   step="0.01" min="0.01" max="9999.99" placeholder="446.80">
                            <div class="form-text">Ingrese la tasa con máximo 2 decimales</div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            La tasa anterior (<strong><?php echo number_format($tasa, 2, ',', '.'); ?></strong>) se mantendrá en el historial.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Confirmar Cambio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/configuracion.js"></script>
</body>
</html>
