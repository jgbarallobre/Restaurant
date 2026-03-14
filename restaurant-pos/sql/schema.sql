-- =====================================================
-- SISTEMA POS RESTAURANTE - ESTRUCTURA DE BASE DE DATOS
-- =====================================================
-- Base de datos: restaurant_pos
-- Compatible con MySQL 5.7+
-- =====================================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS restaurant_pos 
    DEFAULT CHARACTER SET utf8mb4 
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE restaurant_pos;

-- =====================================================
-- TABLA: roles
-- =====================================================
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    permisos_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: usuarios
-- =====================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol_id INT UNSIGNED NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_login TIMESTAMP NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: categorias
-- =====================================================
CREATE TABLE IF NOT EXISTS categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('comida', 'bebida', 'postre', 'otro') NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: productos
-- =====================================================
CREATE TABLE IF NOT EXISTS productos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio_base_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tipo_producto ENUM('terminado', 'compuesto', 'materia_prima') NOT NULL,
    unidad_medida VARCHAR(20) DEFAULT 'unidad',
    stock_actual DECIMAL(10,3) DEFAULT 0,
    stock_minimo DECIMAL(10,3) DEFAULT 0,
    categoria_id INT UNSIGNED,
    imagen VARCHAR(255) DEFAULT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: recetas
-- =====================================================
CREATE TABLE IF NOT EXISTS recetas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_compuesto_id INT UNSIGNED NOT NULL,
    materia_prima_id INT UNSIGNED NOT NULL,
    cantidad_requerida DECIMAL(10,3) NOT NULL,
    unidad_medida_receta VARCHAR(20) DEFAULT 'unidad',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_compuesto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_prima_id) REFERENCES productos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_receta (producto_compuesto_id, materia_prima_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: mesas
-- =====================================================
CREATE TABLE IF NOT EXISTS mesas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero INT UNSIGNED NOT NULL UNIQUE,
    capacidad INT UNSIGNED DEFAULT 4,
    estado ENUM('disponible', 'ocupada', 'reservada', 'inactiva') DEFAULT 'disponible',
    ubicacion VARCHAR(50) DEFAULT 'principal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: pedidos
-- =====================================================
CREATE TABLE IF NOT EXISTS pedidos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mesa_id INT UNSIGNED,
    usuario_id INT UNSIGNED NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente', 'preparando', 'listo', 'entregado', 'cancelado', 'pagado') DEFAULT 'pendiente',
    total_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_bs DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    tasa_cambio_usada DECIMAL(10,2) NOT NULL,
    tipo_pago ENUM('efectivo', 'tarjeta', 'transferencia', 'mixto') DEFAULT 'efectivo',
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mesa_id) REFERENCES mesas(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: detalle_pedido
-- =====================================================
CREATE TABLE IF NOT EXISTS detalle_pedido (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 1,
    precio_unitario_usd DECIMAL(10,2) NOT NULL,
    precio_unitario_bs DECIMAL(20,2) NOT NULL,
    subtotal_usd DECIMAL(10,2) NOT NULL,
    subtotal_bs DECIMAL(20,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: movimientos_inventario
-- =====================================================
CREATE TABLE IF NOT EXISTS movimientos_inventario (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id INT UNSIGNED NOT NULL,
    tipo_movimiento ENUM('entrada', 'salida_venta', 'ajuste', 'merma', 'salida_receta') NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL,
    saldo_anterior DECIMAL(10,3) NOT NULL,
    saldo_nuevo DECIMAL(10,3) NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    referencia VARCHAR(100) DEFAULT NULL,
    observaciones TEXT,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: configuracion
-- =====================================================
CREATE TABLE IF NOT EXISTS configuracion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT,
    descripcion VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: tasa_cambio_historico
-- =====================================================
CREATE TABLE IF NOT EXISTS tasa_cambio_historico (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tasa DECIMAL(10,2) NOT NULL,
    fecha DATE NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ÍNDICES PARA MEJORAR RENDIMIENTO
-- =====================================================
CREATE INDEX idx_productos_tipo ON productos(tipo_producto);
CREATE INDEX idx_productos_estado ON productos(estado);
CREATE INDEX idx_pedidos_fecha ON pedidos(fecha);
CREATE INDEX idx_pedidos_estado ON pedidos(estado);
CREATE INDEX idx_movimientos_fecha ON movimientos_inventario(fecha);
CREATE INDEX idx_movimientos_producto ON movimientos_inventario(producto_id);

-- =====================================================
-- DATOS INICIALES - SEEDERS
-- =====================================================

-- =====================================================
-- ROLES Y PERMISOS
-- =====================================================

-- Eliminar roles existentes para recrear
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE roles;
SET FOREIGN_KEY_CHECKS = 1;

-- Insertar Roles con permisos JSON
INSERT INTO roles (id, nombre, permisos_json) VALUES 
(1, 'Administrador', '{"todos": true}'),
(2, 'Admin', '{"ver_usuarios": true, "crear_usuarios": true, "editar_usuarios": true, "ver_productos": true, "crear_productos": true, "editar_productos": true, "eliminar_productos": true, "ver_pedidos": true, "crear_pedidos": true, "editar_pedidos": true, "cobrar_pedidos": true, "ver_mesas": true, "gestionar_mesas": true, "ver_inventario": true, "gestionar_inventario": true, "ver_reportes": true, "ver_caja": true, "ver_configuracion": true, "modificar_tasa": true}'),
(3, 'Cajero', '{"ver_pedidos": true, "crear_pedidos": true, "cobrar_pedidos": true, "ver_caja": true, "ver_reportes": true, "ver_productos": true, "ver_mesas": true}'),
(4, 'Cocinero', '{"ver_pedidos": true, "editar_pedidos": true, "ver_inventario": true, "ver_productos": true}'),
(5, 'Mesonero', '{"ver_pedidos": true, "crear_pedidos": true, "editar_pedidos": true, "ver_mesas": true, "gestionar_mesas": true, "ver_productos": true}');

-- =====================================================
-- USUARIOS
-- =====================================================

-- Eliminar usuarios existentes para recrear
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- Insertar Usuarios
-- Password: admin123 (hash generado con password_hash)
-- password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
INSERT INTO usuarios (id, nombre, usuario, password_hash, rol_id, estado) VALUES 
(1, 'Administrador', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'activo'),
(2, 'Juan Mesonero', 'mesa1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'activo'),
(3, 'Maria Cajera', 'caja1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'activo'),
(4, 'Pedro Cocinero', 'cocina1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'activo');

-- NOTA: Todos los usuarios tienen la misma contraseña: admin123
-- Para cambiar, genera un nuevo hash con: password_hash('nueva_contraseña', PASSWORD_DEFAULT)

-- Insertar Categorías
INSERT INTO categorias (nombre, tipo, estado) VALUES 
('Hamburguesas', 'comida', 'activo'),
('Bebidas', 'bebida', 'activo'),
('Acompanamientos', 'comida', 'activo'),
('Postres', 'postre', 'activo');

-- Insertar Materias Primas
INSERT INTO productos (nombre, descripcion, precio_base_usd, tipo_producto, unidad_medida, stock_actual, stock_minimo, categoria_id, estado) VALUES 
('Carne Molida', 'Carne de res molida fresca', 8.50, 'materia_prima', 'kg', 10.000, 2.000, 1, 'activo'),
('Pan Hamburguesa', 'Pan para hamburguesa', 0.35, 'materia_prima', 'unidad', 50.000, 10.000, 1, 'activo'),
('Queso Amarillo', 'Queso amarillo procesado', 6.00, 'materia_prima', 'kg', 5.000, 1.000, 1, 'activo'),
('Papas', 'Papas frescas para freir', 1.50, 'materia_prima', 'kg', 15.000, 3.000, 3, 'activo'),
('Cebolla', 'Cebolla blanca', 0.80, 'materia_prima', 'kg', 8.000, 2.000, 1, 'activo'),
('Tomate', 'Tomates frescos', 1.20, 'materia_prima', 'kg', 6.000, 2.000, 1, 'activo'),
('Lechuga', 'Lechuga fresca', 1.00, 'materia_prima', 'kg', 4.000, 1.000, 1, 'activo');

-- Insertar Productos Terminados
INSERT INTO productos (nombre, descripcion, precio_base_usd, tipo_producto, unidad_medida, stock_actual, stock_minimo, categoria_id, estado) VALUES 
('Coca Cola 355ml', 'Refresco de cola', 1.50, 'terminado', 'unidad', 24.000, 6.000, 2, 'activo'),
('Cerveza Polar', 'Cerveza nacional 350ml', 2.00, 'terminado', 'unidad', 12.000, 3.000, 2, 'activo'),
('Agua Mineral', 'Agua sin gas 500ml', 1.00, 'terminado', 'unidad', 18.000, 5.000, 2, 'activo');

-- Insertar Productos Compuestos (Platos del menú)
INSERT INTO productos (nombre, descripcion, precio_base_usd, tipo_producto, unidad_medida, stock_actual, stock_minimo, categoria_id, estado) VALUES 
('Hamburguesa Clasica', 'Hamburguesa con Carne, Queso, Lechuga, Tomate y Cebolla', 5.00, 'compuesto', 'unidad', 0, 0, 1, 'activo'),
('Hamburguesa Doble', 'Hamburguesa con doble carne y doble queso', 7.00, 'compuesto', 'unidad', 0, 0, 1, 'activo'),
('Papas Fritas', 'Papas fritas crujientes', 2.50, 'compuesto', 'porcion', 0, 0, 3, 'activo');

-- Insertar Recetas
-- Hamburguesa Clásica: 1 Pan + 150g Carne + 100g Queso
INSERT INTO recetas (producto_compuesto_id, materia_prima_id, cantidad_requerida, unidad_medida_receta) VALUES 
((SELECT id FROM productos WHERE nombre = 'Hamburguesa Clasica'), (SELECT id FROM productos WHERE nombre = 'Pan Hamburguesa'), 1.000, 'unidad'),
((SELECT id FROM productos WHERE nombre = 'Hamburguesa Clasica'), (SELECT id FROM productos WHERE nombre = 'Carne Molida'), 0.150, 'kg'),
((SELECT id FROM productos WHERE nombre = 'Hamburguesa Clasica'), (SELECT id FROM productos WHERE nombre = 'Queso Amarillo'), 0.100, 'kg');

-- Hamburguesa Doble: 1 Pan + 300g Carne + 150g Queso
INSERT INTO recetas (producto_compuesto_id, materia_prima_id, cantidad_requerida, unidad_medida_receta) VALUES 
((SELECT id FROM productos WHERE nombre = 'Hamburguesa Doble'), (SELECT id FROM productos WHERE nombre = 'Pan Hamburguesa'), 1.000, 'unidad'),
((SELECT id FROM productos WHERE nombre = 'Hamburguesa Doble'), (SELECT id FROM productos WHERE nombre = 'Carne Molida'), 0.300, 'kg'),
((SELECT id FROM productos WHERE nombre = 'Hamburguesa Doble'), (SELECT id FROM productos WHERE nombre = 'Queso Amarillo'), 0.150, 'kg');

-- Papas Fritas: 0.200 kg de papas por porción
INSERT INTO recetas (producto_compuesto_id, materia_prima_id, cantidad_requerida, unidad_medida_receta) VALUES 
((SELECT id FROM productos WHERE nombre = 'Papas Fritas'), (SELECT id FROM productos WHERE nombre = 'Papas'), 0.200, 'kg');

-- Insertar Mesas
INSERT INTO mesas (numero, capacidad, estado, ubicacion) VALUES 
(1, 4, 'disponible', 'Terraza'),
(2, 4, 'disponible', 'Terraza'),
(3, 6, 'disponible', 'Interior'),
(4, 2, 'disponible', 'Bar'),
(5, 8, 'disponible', 'Privado');

-- Insertar Configuración Inicial
INSERT INTO configuracion (clave, valor, descripcion) VALUES 
('tasa_cambio_dia', '446.80', 'Tasa de cambio USD a Bolivares'),
('nombre_restaurante', 'Restaurante Demo', 'Nombre del restaurante'),
('rif_restaurante', 'J-12345678-9', 'RIF del restaurante'),
('direccion_restaurante', 'Av. Principal, Caracas, Venezuela', 'Dirección del restaurante'),
('telefono_restaurante', '0412-1234567', 'Teléfono de contacto'),
('email_restaurante', 'contacto@restaurante.com', 'Correo electrónico'),
('mensaje_pie', 'Gracias por su visita', 'Mensaje en pie de factura'),
('nombre_sistema', 'Sistema POS', 'Nombre del sistema'),
('version_sistema', '1.0.0', 'Versión del sistema'),
('timeout_sesion', '30', 'Tiempo de timeout en minutos'),
('productos_por_pagina', '10', 'Productos por página en listados'),
('impuesto_porcentaje', '16', 'Porcentaje de impuesto (IVA)'),
('iva_incluido', '1', 'Si el precio incluye IVA (1=sí, 0=no)'),
('logo_restaurante', '', 'Nombre del archivo del logo');

-- Insertar Tasa de Cambio Inicial + Historial de ejemplo
INSERT INTO tasa_cambio_historico (tasa, fecha, usuario_id) VALUES 
(430.50, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 1),
(435.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 1),
(440.25, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 1),
(443.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 1),
(445.50, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1),
(446.80, CURDATE(), 1);
