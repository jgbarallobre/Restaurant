<?php
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/tasa.php';

if (!verificarSesion()) {
    header('Location: ../login.php');
    exit;
}

requirePermiso('ver_productos', '../dashboard.php');

$db = getDB();
$menu = generarMenu();

// Get all recipes with product names
$stmt = $db->query("
    SELECT r.*, pc.nombre as producto_compuesto, mp.nombre as materia_prima
    FROM recetas r
    JOIN productos pc ON r.producto_compuesto_id = pc.id
    JOIN productos mp ON r.materia_prima_id = mp.id
    ORDER BY pc.nombre
");
$recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Recetas - Sistema POS</title>
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
                <li class="nav-item"><a class="nav-link active" href="receta.php"><i class="bi bi-book"></i><span>Recetas</span></a></li>
                <li class="nav-item mt-auto"><a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-left"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </nav>

        <div class="main-content">
            <header class="header">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
                    <h4 class="mb-0">Gestión de Recetas</h4>
                </div>
            </header>

            <main class="content">
                <div class="container-fluid">
                    <?php if (empty($recetas)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        No hay recetas registradas. Las recetas se crean al guardar productos compuestos.
                    </div>
                    <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-book me-2"></i>Recetas Registradas</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto Compuesto</th>
                                            <th>Ingrediente</th>
                                            <th>Cantidad</th>
                                            <th>Unidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recetas as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['producto_compuesto']); ?></td>
                                            <td><?php echo htmlspecialchars($r['materia_prima']); ?></td>
                                            <td><?php echo number_format($r['cantidad_requerida'], 3); ?></td>
                                            <td><?php echo htmlspecialchars($r['unidad_medida_receta']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
