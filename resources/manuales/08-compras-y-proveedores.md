# 08 · Compras y proveedores

Registra la reposición de insumos. Al **recibir** una compra, el stock sube y
el costo del insumo se actualiza. Opcional, pero recomendado si usas
inventario (manual 07).

## Requisitos previos

- Manual 07 (insumos creados).

## 1 · Proveedores

**Compras → Proveedores** — requiere permiso *compras.crear*.

1. **Nuevo proveedor**: nombre, contacto, teléfono, notas.

## 2 · Compras

**Compras → Compras** — requiere permiso *compras.ver* para consultar,
*compras.crear* para registrar.

1. **Nueva compra**: elige proveedor y agrega renglones
   (insumo, cantidad, costo unitario). El sistema calcula folio y total.
2. Estado **borrador**: aún **no** afecta inventario. Puedes editarla o
   cancelarla sin consecuencias.
3. **Recibir** la compra:
   - sube el stock de cada insumo,
   - actualiza el **costo** del insumo con el de esta compra,
   - deja el movimiento en el kardex.
4. Una compra recibida **no se puede recibir dos veces**. Si te equivocaste,
   **cancélala**: revierte exactamente el stock que había ingresado.

## Verificación

- Crear una compra en borrador no cambia el inventario.
- Recibirla sube el stock y cambia el costo del insumo.
- Cancelar una compra recibida devuelve el stock al estado previo.
- Un usuario con solo *compras.ver* no puede crear ni recibir.

Continúa con el **manual 09**.
