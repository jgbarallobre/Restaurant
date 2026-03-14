<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/inventario.php';

if (!verificarSesion()) {
    header('Location: ../login.php');
    exit;
}

// Check permission: only administrators and maybe managers can adjust stock
// We'll allow users with permission to edit products to adjust stock
requirePermiso('editar_productos', '../dashboard.php');

$db = getDB();
$tasa = obtenerTasaActual();
$menu = generarMenu();

$mensaje = '';
$tipo_msg = '';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ../productos/index.php?msg=ID inválido&tipo=danger');
    exit;
}

$producto = obtenerProducto($id);
if (!$producto) {
    header('Location: ../productos/index.php?msg=Producto no encontrado&tipo=danger');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $cantidad = floatval($_POST['cantidad'] ?? 0);
    $motivo = sanitizar($_POST['motivo'] ?? '');
    
    if ($cantidad <= 0) {
        $mensaje = 'La cantidad debe ser mayor a cero';
        $tipo_msg = 'danger';
    } elseif (empty($motivo)) {
        $mensaje = 'El motivo es obligatorio';
        $tipo_msg = 'danger';
    } else {
        $tipo = $_POST['tipo'] ?? ''; // entrada or salida
        if (!in_array($tipo, ['entrada', 'salida'])) {
            $mensaje = 'Tipo de movimiento inválido';
            $tipo_msg = 'danger';
        } else {
            $resultado = ajustarStock($id, $cantidad, $tipo, $motivo, $_SESSION['usuario_id']);
            if ($resultado['success']) {
                registrarLog("AJUSTE_STOCK", "Ajuste de stock: {$tipo} {$cantidad} unidades para producto {$producto['nombre']} (ID: {$id}). Motivo: {$motivo}");
                header('Location: ../productos/ver.php?id=' . $id . '&msg=' . urlencode('Stock ajustado exitosamente') . '&tipo=success');
                exit;
            } else {
                $mensaje = $resultado['error'];
                $tipo_msg = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustar Stock - Sistema POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <i class="bi bi-shop"></i>
                <span>Restaurante POS</span>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
                <li class="nav-item"><a class="nav-link" href="../productos/index.php"><i class="bi bi-box-seam"></i><span>Productos</span></a></li>
                <li class="nav-item"><a class="nav-link" href="../productos/ver.php?id=<?php echo $id; ?>"><i class="bi bi-eye"></i><span>Ver Producto</span></a></li>
                <li class="nav-item mt-auto"><a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-left"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </nav>

        <div class="main-content">
            <header class="header">
                <div class="d-flex align-items-center">
                    <a href="../productos/ver.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
                    <h4 class="mb-0">Ajustar Stock</h4>
                </div>
            </header>

            <main class="content">
                <div class="container-fluid">
                    <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_msg; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-arrow-clockwise me-2"></i>Ajuste de Stock para: <?php echo htmlspecialchars($producto['nombre']); ?></h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                Stock actual: <strong><?php echo number_format($producto['stock_actual'], 2); ?> <?php echo htmlspecialchars($producto['unidad_medida']); ?></strong><br>
                                Stock mínimo: <strong><?php echo number_format($producto['stock_minimo'], 2); ?> <?php echo htmlspecialchars($producto['unidad_medida']); ?></strong>
                            </p>
                            
                            <form method="POST">
                                <input type="hidden" name="accion" value="ajustar">
                                
                                <div class="mb-3">
                                    <label class="form-label">Tipo de Ajuste</label>
                                    <select name="tipo" class="form-select" required>
                                        <option value="entrada">Entrada (Agregar stock)</option>
                                        <option value="salida">Salida (Reducir stock)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Cantidad</label>
                                    <input type="number" name="cantidad" class="form-control" step="0.01" min="0.01" required>
                                    <small class="text-muted">Unidad: <?php echo htmlspecialchars($producto['unidad_medida']); ?></small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Motivo *</label>
                                    <textarea name="motivo" class="form-control" rows="3" placeholder="Ej: Merma por daño, entrada de compra, correte de inventario, etc." required></textarea>
                                </div>
                                
                                <div class="text-end">
                                    <a href="../productos/ver.php?id=<?php echo $id; ?>" class="btn btn-secondary me-2">Cancelar</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-2"></i>Aplicar Ajuste
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
