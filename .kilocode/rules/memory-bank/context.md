# Active Context: Sistema POS Restaurante (PHP)

## Current State

**Proyecto**: Sistema POS para Restaurante
**Stack**: PHP 8+ (OOP) + MySQL + Bootstrap 5
**Estado**: ✅ Módulo de autenticación y usuarios completado

## Recently Completed

- [x] Sesiones seguras con timeout de 30 minutos
- [x] Login con diseño moderno y validación
- [x] Logout con destrucción completa de sesión
- [x] Sistema de permisos por rol
- [x] Dashboard adaptativo según rol
- [x] CRUD completo de usuarios
- [x] Validación frontend y backend
- [x] Log de actividades

## Archivos del Módulo de Autenticación

| Archivo | Descripción |
|---------|-------------|
| `config/session.php` | Configuración de sesiones seguras |
| `includes/auth.php` | Funciones de autenticación |
| `includes/permissions.php` | Sistema de permisos |
| `login.php` | Formulario de login |
| `logout.php` | Cerrar sesión |
| `dashboard.php` | Panel principal según rol |
| `usuarios/index.php` | Listado de usuarios |
| `usuarios/acciones.php` | CRUD de usuarios |
| `usuarios/validar_usuario.php` | Validación AJAX |
| `assets/css/style.css` | Estilos personalizados |
| `assets/js/login.js` | Validaciones login |
| `assets/js/main.js` | Funciones globales |
| `assets/js/usuarios.js` | Validaciones usuarios |

## Roles y Permisos

| Rol | Permisos |
|-----|----------|
| Administrador | Acceso total |
| Mesonero | Pedidos, mesas |
| Cajero | Cobrar, caja, reportes |
| Cocina | Ver pedidos, preparar |

## Usuarios de Prueba

| Usuario | Password | Rol |
|---------|----------|-----|
| admin | admin123 | Administrador |
| mesa1 | admin123 | Mesonero |
| caja1 | admin123 | Cajero |
| cocina1 | admin123 | Cocinero |

## Pendiente

- [ ] Integrar módulos existentes con nuevo sistema de permisos

## Session History

| Date | Changes |
|------|---------|
| 2026-03-14 | Módulo de autenticación y usuarios completo |

