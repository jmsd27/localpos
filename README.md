# LOCALPOS

Punto de venta para restaurantes/bares que opera **100 % local** en cada
sucursal (Laravel 12 + Livewire 4 + Tailwind v4, sobre Laragon: Apache +
PHP 8.2+ + MySQL 8). No necesita Internet para vender.

Incluye un **espejo de solo lectura en la nube** (desplegable en Vercel) que
recibe una copia de los datos de todas las sucursales para consulta remota y
una PWA instalable.

## Módulos

Catálogo · POS y ventas · Caja (apertura/movimientos/cierre) · Mesas y mapa ·
Cocina/barra (KDS) · Inventario, recetas y kardex · Compras y proveedores ·
Impresión ESC/POS y cajón de dinero · Reportes, dashboard y auditoría ·
Respaldos · Menú QR público · Sincronización con la nube.

## Puesta en marcha (local)

```bash
composer install
npm ci && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Abre la app y completa el asistente en `/instalacion`. Guía detallada:
[`docs/02-implementacion-local.md`](docs/02-implementacion-local.md).

## Documentación

Todos los manuales de implementación y operación están en **[`docs/`](docs/README.md)**:

| Documento | Tema |
|-----------|------|
| [01 · Arquitectura](docs/01-arquitectura.md) | Cómo encajan el local y el espejo; qué se sincroniza. |
| [02 · Implementación local](docs/02-implementacion-local.md) | Montar LOCALPOS en una sucursal. |
| [03 · Despliegue en Vercel](docs/03-despliegue-vercel.md) | Crear el espejo en la nube. |
| [04 · Alta de sucursales](docs/04-alta-de-sucursales.md) | Conectar cada sucursal al espejo. |
| [05 · Operación y diagnóstico](docs/05-operacion-y-diagnostico.md) | Día a día, salud del sync, respaldos. |
| [06 · Checklist de lanzamiento](docs/06-checklist-lanzamiento.md) | Antes de dar por vivo. |

## Pruebas

```bash
php artisan test
```

## Licencia

MIT (framework Laravel). Código de la aplicación: propiedad del negocio.
