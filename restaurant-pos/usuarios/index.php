<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (!verificarSesion()) {
    header('Location: ../login.php');
    exit;
}

requirePermiso('ver_usuarios', 'dashboard.php');

$db = getDB();
$rolId = getRolId();
$rolNombre = getRolNombre();

// Obtener tasa de cambio
$stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = 'tasa_cambio_dia'");
$stmt->execute();
$tasa = floatval($stmt->fetch()['valor'] ?? 0);

// Obtener roles para el filtro
$roles = $db->query("SELECT * FROM roles ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Búsqueda y filtros
$busqueda = $_GET['buscar'] ?? '';
$filtro_rol = $_GET['rol'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';

$where = "1=1";
$params = [];

if ($busqueda) {
    $where .= " AND (u.nombre LIKE ? OR u.usuario LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if ($filtro_rol) {
    $where .= " AND u.rol_id = ?";
    $params[] = $filtro_rol;
}

if ($filtro_estado) {
    $where .= " AND u.estado = ?";
    $params[] = $filtro_estado;
}

// Paginación
$por_pagina = 10;
$pagina = intval($_GET['pagina'] ?? 1);
$inicio = ($pagina - 1) * $por_pagina;

$countSql = "SELECT COUNT(*) as total FROM usuarios u WHERE $where";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total / $por_pagina);

$sql = "SELECT u.*, r.nombre as rol_nombre 
        FROM usuarios u 
        LEFT JOIN roles r ON u.rol_id = r.id 
        WHERE $where 
        ORDER BY u.id ASC 
        LIMIT $inicio, $por_pagina";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$menu = generarMenu();

// Mensajes
$mensaje = $_GET['msg'] ?? '';
$tipo_msg = $_GET['tipo'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Sistema POS Restaurante</title>
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
                <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-people"></i><span>Usuarios</span></a></li>
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
                            <li class="breadcrumb-item active">Usuarios</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="tasa-badge">
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="bi bi-people me-2"></i>Gestión de Usuarios</h4>
                        <?php if (tienePermiso('crear_usuarios')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
                            <i class="bi bi-plus-lg me-2"></i>Nuevo Usuario
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Filtros -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre o usuario..." value="<?php echo htmlspecialchars($busqueda); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select name="rol" class="form-select">
                                        <option value="">Todos los roles</option>
                                        <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo $r['id']; ?>" <?php echo $filtro_rol == $r['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="estado" class="form-select">
                                        <option value="">Todos los estados</option>
                                        <option value="activo" <?php echo $filtro_estado == 'activo' ? 'selected' : ''; ?>>Activo</option>
                                        <option value="inactivo" <?php echo $filtro_estado == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-funnel"></i> Filtrar</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabla de Usuarios -->
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Usuario</th>
                                            <th>Rol</th>
                                            <th>Estado</th>
                                            <th>Último Login</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios as $u): ?>
                                        <tr>
                                            <td><?php echo $u['id']; ?></td>
                                            <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($u['usuario']); ?></td>
                                            <td>
                                                <?php 
                                                $colores_rol = [
                                                    'Administrador' => 'primary',
                                                    'Admin' => 'info',
                                                    'Cajero' => 'warning',
                                                    'Cocinero' => 'secondary',
                                                    'Mesonero' => 'success'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $colores_rol[$u['rol_nombre']] ?? 'secondary'; ?>">
                                                    <?php echo htmlspecialchars($u['rol_nombre']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['estado'] === 'activo' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($u['estado']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : 'Nunca'; ?></td>
                                            <td>
                                                <?php if (tienePermiso('editar_usuarios')): ?>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditar<?php echo $u['id']; ?>" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($u['id'] != 1 && tienePermiso('editar_usuarios')): ?>
                                                <?php if ($u['estado'] === 'activo'): ?>
                                                <a href="acciones.php?accion=desactivar&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-danger" title="Desactivar" onclick="return confirm('¿Desactivar este usuario?');">
                                                    <i class="bi bi-x-circle"></i>
                                                </a>
                                                <?php else: ?>
                                                <a href="acciones.php?accion=activar&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-success" title="Activar" onclick="return confirm('¿Activar este usuario?');">
                                                    <i class="bi bi-check-circle"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($usuarios) === 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                                <p class="mb-0">No se encontraron usuarios</p>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($total_paginas > 1): ?>
                        <div class="card-footer">
                            <nav>
                                <ul class="pagination justify-content-center mb-0">
                                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $i; ?>&buscar=<?php echo urlencode($busqueda); ?>&rol=<?php echo $filtro_rol; ?>&estado=<?php echo $filtro_estado; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Crear Usuario -->
    <?php if (tienePermiso('crear_usuarios')): ?>
    <div class="modal fade" id="modalCrear" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="acciones.php" id="formCrear">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="crear">
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre" class="form-control" required minlength="3" maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Usuario *</label>
                            <input type="text" name="usuario" class="form-control" required minlength="4" maxlength="50">
                            <div class="invalid-feedback" id="usuarioFeedback"></div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contraseña *</label>
                                <div class="input-group">
                                    <input type="password" name="password" class="form-control" required minlength="6" id="passwordCrear">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('passwordCrear', 'eyeCrear')">
                                        <i class="bi bi-eye-slash" id="eyeCrear"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirmar Contraseña *</label>
                                <input type="password" name="confirmar_password" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rol *</label>
                            <select name="rol_id" class="form-select" required>
                                <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modales de Editar -->
    <?php foreach ($usuarios as $u): ?>
    <div class="modal fade" id="modalEditar<?php echo $u['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="acciones.php">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($u['nombre']); ?>" required minlength="3" maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Usuario *</label>
                            <input type="text" name="usuario" class="form-control" value="<?php echo htmlspecialchars($u['usuario']); ?>" required minlength="4" maxlength="50">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña (dejar en blanco para mantener)</label>
                            <input type="password" name="password" class="form-control" minlength="6" placeholder="Mínimo 6 caracteres">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rol *</label>
                            <select name="rol_id" class="form-select" required <?php echo $u['id'] == 1 ? 'disabled' : ''; ?>>
                                <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo $r['id'] == $u['rol_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($u['id'] != 1): ?>
                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="activo" <?php echo $u['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo $u['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/usuarios.js"></script>
</body>
</html>
