<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/tasa.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inventario.php';

if (!verificarSesion()) {
    header('Location: ../login.php');
    exit;
}

requirePermiso('ver_productos', '../dashboard.php');

$db = getDB();
$tasa = obtenerTasaActual();
$rolId = getRolId();
$rolNombre = getRolNombre();
$menu = generarMenu();

// Filtros
$tipo = $_GET['tipo'] ?? '';
$categoria_id = $_GET['categoria'] ?? '';
$busqueda = $_GET['buscar'] ?? '';
$estado = $_GET['estado'] ?? '';

// Obtener datos
$productos = obtenerProductos($tipo, $categoria_id, $busqueda, $estado);
$categorias = obtenerCategorias();
$productos_bajo_stock = obtenerProductosStockBajo();
$valor_inventario = obtenerValorInventario();

// Estadísticas
$total_productos = count($productos);
$sin_stock = 0;
$stock_bajo = 0;

foreach ($productos as $p) {
    if ($p['tipo_producto'] !== 'compuesto') {
        if ($p['stock_actual'] <= 0) {
            $sin_stock++;
        } elseif ($p['stock_actual'] <= $p['stock_minimo']) {
            $stock_bajo++;
        }
    }
}

$mensaje = $_GET['msg'] ?? '';
$tipo_msg = $_GET['tipo'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Sistema POS Restaurante</title>
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
                <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-box-seam"></i><span>Productos</span></a></li>
                <?php if (tienePermiso('ver_mesas')): ?>
                <li class="nav-item"><a class="nav-link" href="../mesas/"><i class="bi bi-grid-3x3-gap"></i><span>Mesas</span></a></li>
                <?php endif; ?>
                <?php if (tienePermiso('ver_inventario')): ?>
                <li class="nav-item"><a class="nav-link" href="../inventario/"><i class="bi bi-archive"></i><span>Inventario</span></a></li>
                <?php endif; ?>
                <?php if (tienePermiso('ver_reportes')): ?>
                <li class="nav-item"><a class="nav-link" href="../reportes/"><i class="bi bi-graph-up"></i><span>Reportes</span></a></li>
                <?php endif; ?>
                <?php if (tienePermiso('ver_configuracion')): ?>
                <li class="nav-item"><a class="nav-link" href="../configuracion/"><i class="bi bi-gear"></i><span>Configuración</span></a></li>
                <?php endif; ?>
                <li class="nav-item mt-auto"><a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-left"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </nav>

        <div class="main-content">
            <header class="header">
                <div class="d-flex align-items-center">
                    <button class="btn btn-toggle-sidebar me-3" id="sidebarToggle"><i class="bi bi-list"></i></button>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                            <li class="breadcrumb-item active">Productos</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="tasa-badge bg-<?php echo $stock_bajo > 0 ? 'warning' : 'success'; ?>">
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
                            <li><a class="dropdown-item text-danger" href="../logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="content">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>Gestión de Productos</h4>
                        <?php if (tienePermiso('crear_productos')): ?>
                        <a href="crear.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i>Nuevo Producto
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Estadísticas -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="stat-card stat-primary">
                                <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                                <div class="stat-content">
                                    <h6>Total Productos</h6>
                                    <h3><?php echo $total_productos; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card stat-danger">
                                <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                                <div class="stat-content">
                                    <h6>Sin Stock</h6>
                                    <h3><?php echo $sin_stock; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card stat-warning">
                                <div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div>
                                <div class="stat-content">
                                    <h6>Stock Bajo</h6>
                                    <h3><?php echo $stock_bajo; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card stat-success">
                                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                                <div class="stat-content">
                                    <h6>Valor Inventario</h6>
                                    <h3><?php echo formatoBs($valor_inventario['bs']); ?></h3>
                                    <small><?php echo formatoUsd($valor_inventario['usd']); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3">
                                    <input type="text" name="buscar" class="form-control" placeholder="Buscar producto..." value="<?php echo htmlspecialchars($busqueda); ?>">
                                </div>
                                <div class="col-md-2">
                                    <select name="tipo" class="form-select">
                                        <option value="">Todos los tipos</option>
                                        <option value="materia_prima" <?php echo $tipo === 'materia_prima' ? 'selected' : ''; ?>>Materia Prima</option>
                                        <option value="terminado" <?php echo $tipo === 'terminado' ? 'selected' : ''; ?>>Producto Terminado</option>
                                        <option value="compuesto" <?php echo $tipo === 'compuesto' ? 'selected' : ''; ?>>Producto Compuesto</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="categoria" class="form-select">
                                        <option value="">Todas las categorías</option>
                                        <?php foreach ($categorias as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo $categoria_id == $c['id'] ? 'selected' : ''; ?>><?php echo $c['nombre']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="estado" class="form-select">
                                        <option value="">Todos los estados</option>
                                        <option value="activo" <?php echo $estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                        <option value="inactivo" <?php echo $estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-secondary me-2"><i class="bi bi-funnel"></i> Filtrar</button>
                                    <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabla de Productos -->
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Producto</th>
                                            <th>Tipo</th>
                                            <th>Categoría</th>
                                            <th class="text-end">Precio USD</th>
                                            <th class="text-end">Precio Bs</th>
                                            <th class="text-center">Stock</th>
                                            <th>Estado</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($productos as $p): 
                                            $precioBs = usdToBs($p['precio_base_usd']);
                                            $stock_class = '';
                                            if ($p['tipo_producto'] !== 'compuesto') {
                                                if ($p['stock_actual'] <= 0) {
                                                    $stock_class = 'text-danger fw-bold';
                                                } elseif ($p['stock_actual'] <= $p['stock_minimo']) {
                                                    $stock_class = 'text-warning fw-bold';
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo $p['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                                <?php if ($p['tipo_producto'] === 'compuesto'): 
                                                    $disponible = stockDisponibleCompuesto($p['id']);
                                                ?>
                                                <br><small class="text-info">Puede hacer: <?php echo $disponible; ?> unidades</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $tipos = [
                                                    'materia_prima' => ['label' => 'Materia Prima', 'color' => 'secondary'],
                                                    'terminado' => ['label' => 'Terminado', 'color' => 'success'],
                                                    'compuesto' => ['label' => 'Compuesto', 'color' => 'info']
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $tipos[$p['tipo_producto']]['color']; ?>">
                                                    <?php echo $tipos[$p['tipo_producto']]['label']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $p['categoria_nombre'] ?? '-'; ?></td>
                                            <td class="text-end">$<?php echo number_format($p['precio_base_usd'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($precioBs, 2, ',', '.'); ?> Bs</td>
                                            <td class="text-center <?php echo $stock_class; ?>">
                                                <?php if ($p['tipo_producto'] !== 'compuesto'): ?>
                                                <?php echo number_format($p['stock_actual'], 2); ?> <?php echo $p['unidad_medida']; ?>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $p['estado'] === 'activo' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $p['estado']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="ver.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-primary" title="Ver">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (tienePermiso('editar_productos')): ?>
                                                    <a href="editar.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-secondary" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($productos) === 0): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                                <p class="mb-0">No se encontraron productos</p>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
