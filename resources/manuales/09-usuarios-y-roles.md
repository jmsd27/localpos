# 09 · Usuarios y roles

Da de alta al personal. Cada usuario tiene **un rol** que define qué puede
hacer. Los roles ya vienen sembrados; no hace falta crearlos.

## Requisitos previos

- Manual 01 completo. Sesión como super admin o administrador
  (permiso *usuarios.crear*).

## Roles disponibles

| Rol | Para quién | Puede, a grandes rasgos |
|---|---|---|
| **Super administrador** | Dueño / implementador | Todo, incluidos estos manuales. **No** se puede asignar desde la pantalla de usuarios. |
| **Administrador** | Gerente | Todo lo operativo: ventas, caja, inventario, catálogo, compras, reportes, usuarios y configuración. |
| **Encargado** | Supervisor de turno | Ver/editar/anular ventas, descuentos, ajustar inventario, ver compras y reportes. No abre/cierra caja ni toca configuración. |
| **Cajero** | Cobro en mostrador | Crear ventas, abrir/cerrar caja, registrar movimientos, alta rápida de clientes. |
| **Mesero** | Servicio a mesa | Crear y ver comandas, alta rápida de clientes. |
| **Cocina** / **Barra** | Producción | Ver y avanzar comandas en el KDS. |
| **Inventarios** | Almacén | Ajustar inventario, ver kardex, crear y aprobar compras. |
| **Reportes** | Contabilidad / dueño remoto | Solo ver y exportar reportes. |

## Alta de un usuario

**Administración → Usuarios**

1. **Nuevo usuario**:
   - *Nombre*, *correo* (único), *contraseña* (mínimo 8, con confirmación).
   - *Rol*: uno de la tabla de arriba.
2. Guarda. El usuario ya puede iniciar sesión con su correo y contraseña.

## Bajas y cambios

- **Desactivar** un usuario (botón en la lista): **no puede iniciar sesión**
  aunque la contraseña sea correcta. Se usa para bajas sin perder su
  historial de ventas.
- Editar sin escribir contraseña **conserva** la anterior.
- No puedes **desactivar tu propia cuenta**.
- Cambiar el rol aplica en el siguiente inicio de sesión del usuario.

## Verificación

- Un cajero recién creado inicia sesión y llega al POS, pero no ve
  *Configuración* ni *Usuarios*.
- Al desactivarlo, su login queda bloqueado.
- La pantalla no ofrece el rol *Super administrador*.

Continúa con el **manual 10**.
