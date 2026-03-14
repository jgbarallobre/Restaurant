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

requirePermiso('editar_productos', '../dashboard.php');

$db = getDB();
$tasa = obtenerTasaActual();
$rolId = getRolId();
$rolNombre = getRolNombre();
$menu = generarMenu();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php?msg=ID inválido&tipo=danger');
    exit;
}

$producto = obtenerProducto($id);
if (!$producto) {
    header('Location: index.php?msg=Producto no encontrado&tipo=danger');
    exit;
}

$receta = ($producto['tipo_producto'] === 'compuesto') ? obtenerReceta($id) : [];

$categorias = obtenerCategorias();
$materias_primas = obtenerMateriasPrimas();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Sistema POS</title>
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
                <li class="nav-item mt-auto"><a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-left"></i><span>Cerrar Sesión</span></a></li>
            </ul>
        </nav>

        <div class="main-content">
            <header class="header">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Productos</a></li>
                            <li class="breadcrumb-item active">Editar Producto</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="tasa-badge bg-success">
                        <i class="bi bi-currency-exchange"></i>
                        <span><?php echo number_format($tasa, 2, ',', '.'); ?> Bs/$</span>
                    </div>
                </div>
            </header>

            <main class="content">
                <div class="container-fluid">
                    <h4 class="mb-4"><i class="bi bi-pencil me-2"></i>Editar Producto</h4>

                    <form method="POST" action="acciones.php" enctype="multipart/form-data" id="formProducto">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        
                        <!-- Paso 1: Datos Básicos -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Datos del Producto</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre del Producto *</label>
                                            <input type="text" name="nombre" class="form-control" required 
                                                   value="<?php echo htmlspecialchars($producto['nombre']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de Producto *</label>
                                            <select name="tipo_producto" class="form-select" id="tipoProducto" required disabled>
                                                <option value="">Seleccionar...</option>
                                                <option value="materia_prima" <?php echo $producto['tipo_producto'] === 'materia_prima' ? 'selected' : ''; ?>>Materia Prima</option>
                                                <option value="terminado" <?php echo $producto['tipo_producto'] === 'terminado' ? 'selected' : ''; ?>>Producto Terminado</option>
                                                <option value="compuesto" <?php echo $producto['tipo_producto'] === 'compuesto' ? 'selected' : ''; ?>>Producto Compuesto</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Descripción</label>
                                    <textarea name="descripcion" class="form-control" rows="2"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Categoría</label>
                                            <select name="categoria_id" class="form-select">
                                                <option value="">Sin categoría</option>
                                                <?php foreach ($categorias as $c): ?>
                                                <option value="<?php echo $c['id']; ?>" <?php echo ($producto['categoria_id'] == $c['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $c['nombre']; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Estado</label>
                                            <select name="estado" class="form-select">
                                                <option value="activo" <?php echo $producto['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                                <option value="inactivo" <?php echo $producto['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Imagen del Producto</label>
                                    <input type="file" name="imagen" class="form-control" accept="image/png,image/jpeg">
                                    <small class="text-muted">Máximo 2MB, PNG o JPG (dejar vacío para mantener actual)</small>
                                    <?php if ($producto['imagen']): ?>
                                    <div class="mt-2">
                                        <img src="../assets/uploads/productos/<?php echo $producto['imagen']; ?>" alt="Imagen actual" class="img-thumbnail" style="max-width: 200px;">
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Paso 2: Precio y Stock -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-tag me-2"></i>Precio y Stock</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Precio de Venta (USD) *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" name="precio_usd" class="form-control" required step="0.01" min="0.01" 
                                                       value="<?php echo $producto['precio_base_usd']; ?>">
                                            </div>
                                            <small class="text-muted">equivale a: <span id="precioBs"><?php echo number_format(usdToBs($producto['precio_base_usd']), 2, ',', '.'); ?></span> Bs</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Unidad de Medida</label>
                                            <select name="unidad_medida" class="form-select" id="unidadMedida">
                                                <option value="unidad" <?php echo $producto['unidad_medida'] === 'unidad' ? 'selected' : ''; ?>>Unidad</option>
                                                <option value="kg" <?php echo $producto['unidad_medida'] === 'kg' ? 'selected' : ''; ?>>Kilogramo (kg)</option>
                                                <option value="gramos" <?php echo $producto['unidad_medida'] === 'gramos' ? 'selected' : ''; ?>>Gramos (g)</option>
                                                <option value="litros" <?php echo $producto['unidad_medida'] === 'litros' ? 'selected' : ''; ?>>Litros (L)</option>
                                                <option value="ml" <?php echo $producto['unidad_medida'] === 'ml' ? 'selected' : ''; ?>>Mililitros (ml)</option>
                                                <option value="paquete" <?php echo $producto['unidad_medida'] === 'paquete' ? 'selected' : ''; ?>>Paquete</option>
                                                <option value="porcion" <?php echo $producto['unidad_medida'] === 'porcion' ? 'selected' : ''; ?>>Porción</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Stock Mínimo (alerta)</label>
                                            <input type="number" name="stock_minimo" class="form-control" step="0.01" min="0" 
                                                   value="<?php echo $producto['stock_minimo']; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Campos específicos para materia prima -->
                                <div id="camposMP" <?php echo $producto['tipo_producto'] === 'materia_prima' ? '' : 'style="display: none;"'; ?>>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Stock Actual</label>
                                                <input type="number" name="stock_actual" class="form-control" step="0.01" min="0" 
                                                       value="<?php echo $producto['stock_actual']; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Costo (USD)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="costo" class="form-control" step="0.01" min="0" 
                                                           value="<?php echo $producto['precio_base_usd']; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Campos específicos para terminado -->
                                <div id="camposTerminado" <?php echo $producto['tipo_producto'] === 'terminado' ? '' : 'style="display: none;"'; ?>>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Stock Actual</label>
                                                <input type="number" name="stock_actual" class="form-control" step="1" min="0" 
                                                       value="<?php echo $producto['stock_actual']; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Código de Barras</label>
                                                <input type="text" name="codigo_barras" class="form-control" 
                                                       value="<?php echo htmlspecialchars($producto['descripcion'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Campos específicos para compuesto -->
                                <div id="camposCompuesto" <?php echo $producto['tipo_producto'] === 'compuesto' ? '' : 'style="display: none;"'; ?>>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Tiempo de Preparación (min)</label>
                                                <input type="number" name="tiempo_preparacion" class="form-control" min="1" 
                                                       value="<?php echo $producto['tiempo_preparacion'] ?? 15; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Paso 3: Receta (solo para compuestos) -->
                        <div class="card mb-4" id="seccionReceta" <?php echo $producto['tipo_producto'] === 'compuesto' ? '' : 'style="display: none;"'; ?>>
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-book me-2"></i>Receta del Producto</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Agregue los ingredientes necesarios para preparar este plato.</p>
                                
                                <table class="table" id="tablaReceta">
                                    <thead>
                                        <tr>
                                            <th>Ingrediente</th>
                                            <th style="width: 150px;">Cantidad</th>
                                            <th>Unidad</th>
                                            <th>Stock Actual</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="ingredientesBody">
                                        <?php foreach ($receta as $ing): ?>
                                        <tr>
                                            <td>
                                                <select name="ingredientes[]" class="form-select select-ingrediente">
                                                    <?php foreach ($materias_primas as $mp): ?>
                                                    <option value="<?php echo $mp['id']; ?>" <?php echo $mp['id'] == $ing['materia_prima_id'] ? 'selected' : ''; ?>>
                                                        <?php echo $mp['nombre']; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="cantidades[]" class="form-control" step="0.001" min="0.001" value="<?php echo $ing['cantidad_requerida']; ?>">
                                            </td>
                                            <td>
                                                <input type="text" name="unidades[]" class="form-control" value="<?php echo $ing['unidad_medida_receta']; ?>">
                                            </td>
                                            <td class="text-muted"><?php echo $ing['stock_actual']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFila(this)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <button type="button" class="btn btn-success" onclick="agregarIngrediente()">
                                    <i class="bi bi-plus-lg me-2"></i>Agregar Ingrediente
                                </button>
                                
                                <div class="alert alert-info mt-3">
                                    <strong>Costo estimado de la receta:</strong> <span id="costoReceta">$0.00</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/productos.js"></script>
    <script>
        const tasa = <?php echo $tasa; ?>;
        const materiasPrimas = <?php echo json_encode($materias_primas); ?>;
        
        // Calcular precio en Bs
        document.querySelector('input[name="precio_usd"]').addEventListener('input', function() {
            const bs = (this.value * tasa).toFixed(2).replace(/\./g, ',');
            document.getElementById('precioBs').textContent = bs;
        });
        
        // Mostrar campos según tipo
        document.getElementById('tipoProducto').addEventListener('change', function() {
            document.getElementById('camposMP').style.display = 'none';
            document.getElementById('camposTerminado').style.display = 'none';
            document.getElementById('camposCompuesto').style.display = 'none';
            document.getElementById('seccionReceta').style.display = 'none';
            
            if (this.value === 'materia_prima') {
                document.getElementById('camposMP').style.display = 'block';
            } else if (this.value === 'terminado') {
                document.getElementById('camposTerminado').style.display = 'block';
            } else if (this.value === 'compuesto') {
                document.getElementById('camposCompuesto').style.display = 'block';
                document.getElementById('seccionReceta').style.display = 'block';
            }
        });
        
        // Inicializar
        document.getElementById('tipoProducto').dispatchEvent(new Event('change'));
        
        function agregarIngrediente() {
            const tbody = document.getElementById('ingredientesBody');
            const tr = document.createElement('tr');
            
            let options = '<option value="">Seleccionar...</option>';
            materiasPrimas.forEach(mp => {
                options += `<option value="${mp.id}" data-stock="${mp.stock_actual}" data-unidad="${mp.unidad_medida}">${mp.nombre}</option>`;
            });
            
            tr.innerHTML = `
                <td><select name="ingredientes[]" class="form-select select-ingrediente" onchange="actualizarStock(this)">${options}</select></td>
                <td><input type="number" name="cantidades[]" class="form-control" step="0.001" min="0.001" value="0.1"></td>
                <td><input type="text" name="unidades[]" class="form-control" placeholder="kg/unidad"></td>
                <td class="text-muted">-</td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="eliminarFila(this)"><i class="bi bi-trash"></i></button></td>
            `;
            
            tbody.appendChild(tr);
        }
        
        function actualizarStock(select) {
            const option = select.options[select.selectedIndex];
            const stock = option.dataset.stock || 0;
            const row = select.closest('tr');
            row.querySelector('td:nth-child(4)').textContent = stock;
        }
        
        function eliminarFila(btn) {
            btn.closest('tr').remove();
        }
    </script>
</body>
</html>
