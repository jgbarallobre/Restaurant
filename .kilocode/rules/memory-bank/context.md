# Active Context: Sistema POS Restaurante (PHP)

## Current State

**Proyecto**: Sistema POS para Restaurante
**Stack**: PHP 8+ (OOP) + MySQL + Bootstrap 5
**Estado**: ✅ Estructura base completada

## Recently Completed

- [x] Estructura de carpetas del proyecto PHP
- [x] Schema SQL con 11 tablas y seeders
- [x] Conexión a base de datos (PDO Singleton)
- [x] Sistema de autenticación (login/logout)
- [x] Dashboard con estadísticas
- [x] Módulo de Pedidos (crear, ver, cambiar estado)
- [x] Módulo de Productos
- [x] Módulo de Mesas
- [x] Módulo de Inventario (entradas, mermas)
- [x] Módulo de Reportes
- [x] Configuración de tasa de cambio

## Estructura del Proyecto

```
restaurant-pos/
├── app/
│   ├── config/
│   │   ├── config.php      # Configuración global
│   │   └── database.php    # Conexión PDO
│   └── controllers/
│       ├── auth/           # Login, Logout
│       └── pedidos/        # Guardar pedido
├── public/
│   ├── index.php           # Login
│   ├── bootstrap.php       # Autoload
│   ├── dashboard/          # Dashboard principal
│   ├── pedidos/           # Listado, nuevo, ver
│   ├── productos/         # Gestión productos
│   ├── mesas/              # Gestión mesas
│   ├── inventario/        # Control inventario
│   ├── reportes/          # Reportes ventas
│   └── configuracion/     # Tasa cambio
└── sql/
    └── schema.sql          # Base de datos + seeders
```

## Lógica de Negocio Implementada

1. **Tipos de Productos**: materia_prima, terminado, compuesto
2. **Venta de Compuestos**: Descuenta automáticamente materias primas según receta
3. **Stock**: Validación antes de venta, alertas de stock bajo
4. **Moneda**: USD como base, visualización en Bs con tasa configurable

## Credenciales de Acceso

- Usuario: `admin`
- Contraseña: `admin123`

## Pendiente

- [ ] Controlador para crear productos
- [ ] Ver detalles de pedido desde listado
- [ ] Impresión de tickets

## Session History

| Date | Changes |
|------|---------|
| 2026-03-14 | Estructura base del sistema POS Restaurant |

