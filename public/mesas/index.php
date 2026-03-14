<?php
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/config/config.php';

if (!isLoggedIn()) {
    redireccionar('/restaurant-pos/public/index.php');
}

$db = getDB();

$mesas = $db->query("SELECT * FROM mesas ORDER BY numero")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $mesaId = intval($_POST['mesa_id']);
    $accion = $_POST['accion'];
    
    try {
        if ($accion === 'cambiar_estado') {
            $nuevoEstado = $_POST['estado'];
            $stmt = $db->prepare("UPDATE mesas SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevoEstado, $mesaId]);
            logger("Mesa {$mesaId} cambió a estado: {$nuevoEstado}");
            redireccionar('/restaurant-pos/public/mesas/index.php');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesas - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --primary: #1e3a5f; --secondary: #2d5a87; --accent: #ff6b35; }
        .sidebar { min-height: 100vh; background: var(--primary); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; border-radius: 8px; margin: 2px 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: var(--secondary); color: white; }
        .mesa-card { transition: transform 0.2s; }
        .mesa-card:hover { transform: scale(1.05); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse show">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <i class="bi bi-shop" style="font-size: 2rem; color: white;"></i>
                        <h5 class="text-white mt-2"><?php echo APP_NAME; ?></h5>
                    </div>
                    <hr class="border-light">
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/dashboard/index.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/pedidos/index.php"><i class="bi bi-receipt me-2"></i>Pedidos</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/productos/index.php"><i class="bi bi-box-seam me-2"></i>Productos</a></li>
                        <li class="nav-item"><a class="nav-link active" href="/restaurant-pos/public/mesas/index.php"><i class="bi bi-grid-3x3-gap me-2"></i>Mesas</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/inventario/index.php"><i class="bi bi-archive me-2"></i>Inventario</a></li>
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/public/reportes/index.php"><i class="bi bi-graph-up me-2"></i>Reportes</a></li>
                        <hr class="border-light">
                        <li class="nav-item"><a class="nav-link" href="/restaurant-pos/app/controllers/auth/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div><h4 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Gestión de Mesas</h4></div>
                </div>

                <div class="row g-4">
                    <?php 
                    $estadosMesa = [
                        'disponible' => ['bg' => 'bg-success', 'icon' => 'bi-check-circle', 'label' => 'Disponible'],
                        'ocupada' => ['bg' => 'bg-warning', 'icon' => 'bi-person-fill', 'label' => 'Ocupada'],
                        'reservada' => ['bg' => 'bg-info', 'icon' => 'bi-calendar-check', 'label' => 'Reservada'],
                        'inactiva' => ['bg' => 'bg-secondary', 'icon' => 'bi-x-circle', 'label' => 'Inactiva']
                    ];
                    
                    $contador = ['disponible' => 0, 'ocupada' => 0, 'reservada' => 0, 'inactiva' => 0];
                    foreach ($mesas as $m) {
                        $contador[$m['estado']]++;
                    }
                    ?>
                    
                    <div class="col-12">
                        <div class="row g-3 mb-4">
                            <div class="col-auto"><span class="badge bg-success"><?php echo $contador['disponible']; ?> Disponibles</span></div>
                            <div class="col-auto"><span class="badge bg-warning text-dark"><?php echo $contador['ocupada']; ?> Ocupadas</span></div>
                            <div class="col-auto"><span class="badge bg-info"><?php echo $contador['reservada']; ?> Reservadas</span></div>
                        </div>
                    </div>

                    <?php foreach ($mesas as $mesa): ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="card mesa-card text-center h-100">
                            <div class="card-body <?php echo $estadosMesa[$mesa['estado']]['bg']; ?> text-white rounded">
                                <i class="bi <?php echo $estadosMesa[$mesa['estado']]['icon']; ?>" style="font-size: 2rem;"></i>
                                <h4 class="mt-2 mb-1">Mesa <?php echo $mesa['numero']; ?></h4>
                                <p class="mb-2"><small><?php echo $mesa['ubicacion']; ?> • <?php echo $mesa['capacidad']; ?> personas</small></p>
                                <span class="badge bg-light text-dark"><?php echo $estadosMesa[$mesa['estado']]['label']; ?></span>
                            </div>
                            <div class="card-footer bg-white">
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="mesa_id" value="<?php echo $mesa['id']; ?>">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <select name="estado" class="form-select form-select-sm">
                                        <?php foreach (['disponible', 'ocupada', 'reservada', 'inactiva'] as $e): ?>
                                        <option value="<?php echo $e; ?>" <?php echo $e === $mesa['estado'] ? 'selected' : ''; ?>><?php echo ucfirst($e); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
