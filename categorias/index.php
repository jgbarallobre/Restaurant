<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

if (!verificarSesion()) {
    header('Location: ../login.php');
    exit;
}

requirePermiso('ver_productos', '../dashboard.php');

$db = getDB();
$rolId = getRolId();
$rolNombre = getRolNombre();

// Procesar acciones
$accion = $_GET['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    
    try {
        if ($accion === 'crear') {
            requirePermiso('crear_productos', '../dashboard.php');
            
            $nombre = htmlspecialchars($_POST['nombre']);
            $tipo = $_POST['tipo'];
            $descripcion = htmlspecialchars($_POST['descripcion'] ?? '');
            
            $stmt = $db->prepare("INSERT INTO categorias (nombre, tipo, descripcion, estado) VALUES (?, ?, ?, 'activo')");
            $stmt->execute([$nombre, $tipo, $descripcion]);
            
            header('Location: index.php?msg=' . urlencode('Categoría creada') . '&tipo=success');
            exit;
            
        } elseif ($accion === 'editar') {
            requirePermiso('editar_productos', '../dashboard.php');
            
            $id = intval($_POST['id']);
            $nombre = htmlspecialchars($_POST['nombre']);
            $tipo = $_POST['tipo'];
            $descripcion = htmlspecialchars($_POST['descripcion'] ?? '');
            
            $stmt = $db->prepare("UPDATE categorias SET nombre = ?, tipo = ?, descripcion = ? WHERE id = ?");
            $stmt->execute([$nombre, $tipo, $descripcion, $id]);
            
            header('Location: index.php?msg=' . urlencode('Categoría actualizada') . '&tipo=success');
            exit;
            
        } elseif ($accion === 'cambiar_estado') {
            requirePermiso('editar_productos', '../dashboard.php');
            
            $id = intval($_GET['id']);
            $estado = $_GET['estado'];
            
            $stmt = $db->prepare("UPDATE categorias SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $id]);
            
            header('Location: index.php?msg=' . urlencode('Estado actualizado') . '&tipo=success');
            exit;
        }
    } catch (Exception $e) {
        header('Location: index.php?msg=' . urlencode($e->getMessage()) . '&tipo=danger');
        exit;
    }
}

// Obtener categorías
$categorias = $db->query("SELECT c.*, (SELECT COUNT(*) FROM productos WHERE categoria_id = c.id) as productos_count FROM categorias c ORDER BY c.nombre")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías - Sistema POS</title>
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
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-box-seam"></i><span>Productos</span></a></li>
                <li class="nav-item"><a class="nav-link active" href="categorias/"><i class="bi bi-tags"></i><span>Categorías</span></a></li>
                <li class="nav-item mt-auto"><a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-left"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </nav>

        <div class="main-content">
            <header class="header">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
                    <h4 class="mb-0">Categorías</h4>
                </div>
            </header>

            <main class="content">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <p class="mb-0">Administre las categorías de sus productos</p>
                        <?php if (tienePermiso('crear_productos')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                            <i class="bi bi-plus-lg me-2"></i>Nueva Categoría
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="card-body p-0">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Productos</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categorias as $c): ?>
                                    <tr>
                                        <td><strong><?php echo $c['nombre']; ?></strong></td>
                                        <td><span class="badge bg-info"><?php echo $c['tipo']; ?></span></td>
                                        <td><?php echo $c['productos_count']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $c['estado'] === 'activo' ? 'success' : 'secondary'; ?>">
                                                <?php echo $c['estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (tienePermiso('editar_productos')): ?>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditar<?php echo $c['id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Nueva Categoría -->
    <?php if (tienePermiso('crear_productos')): ?>
    <div class="modal fade" id="modalCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Nueva Categoría</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="crear">
                        <div class="mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo *</label>
                            <select name="tipo" class="form-select" required>
                                <option value="comida">Comida</option>
                                <option value="bebida">Bebida</option>
                                <option value="postre">Postre</option>
                                <option value="ingrediente">Ingrediente</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
