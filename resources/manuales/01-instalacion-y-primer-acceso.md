# 01 · Instalación y primer acceso

Crea el negocio, la sucursal principal y el primer usuario. **Esto se hace
una sola vez**: en cuanto exista un negocio, la pantalla de instalación
desaparece.

## Requisitos previos

- Manual 00 completo (máquina lista, base `localpos` vacía).
- El servidor corriendo. En desarrollo: `composer dev`. En un equipo de
  producción, Laragon sirviendo el proyecto.

## Pasos

1. Abre el navegador en la dirección del sistema (ej. `http://localpos.test`
   o `http://127.0.0.1:8000`). Al no existir ningún negocio, te redirige a
   **`/instalacion`**.
2. **Negocio**
   - *Nombre del negocio*: el nombre comercial (se puede cambiar después).
   - *Moneda*: código de 3 letras — `MXN`.
   - *Zona horaria*: `America/Mexico_City` (o la que corresponda). Es
     importante: define la fecha de cortes de caja y reportes.
3. **Cuenta de administrador**
   - *Nombre*, *correo* y *contraseña* (mínimo 8 caracteres). Confírmala.
   - Este usuario queda con el rol **Super administrador**: acceso total,
     incluidos estos manuales.
4. Pulsa **Instalar**. El sistema:
   - crea el negocio,
   - crea la **Sucursal Principal** (código `principal`),
   - siembra todos los roles y permisos,
   - crea tu usuario y **te deja la sesión iniciada** en el Dashboard.

## Verificación

- Estás en el **Dashboard** con tu nombre arriba a la derecha.
- El menú lateral muestra todas las secciones (eres super admin).
- Si cierras sesión y vuelves a `/instalacion`, responde **404** — correcto,
  ya no se puede reinstalar.

## Errores comunes

| Síntoma | Causa / solución |
|---|---|
| `/instalacion` da 404 desde el inicio | Ya existe un negocio en la base. Usa `/login`. Para empezar de cero: `php artisan migrate:fresh` (¡borra todo!). |
| "La contraseña no coincide" | El campo de confirmación no es igual. |
| Error de conexión a base de datos | Revisa `DB_*` en `.env` y que MySQL esté arriba. |

## Después de instalar

Cambia la contraseña por una fuerte si usaste una temporal
(**Administración → Usuarios**), y continúa con el **manual 02**.
