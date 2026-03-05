# Frontend

Esta carpeta contiene las pantallas/modulos del sistema (UI + flujo web en PHP).

Archivos movidos aqui:
- `login.php`
- `menu_principal.php`
- `dashboard.php`
- `reportes.php`
- `generar_factura.php`
- `control_pagos.php`
- `control_entregas.php`
- `consultar_factura.php`
- `usuarios_mantenimiento.php`
- `mantenimiento_tablas_D.php`
- `clientes_D.php`
- `productos_D.php`
- `vehiculos_D.php`
- `auditoria.php`
- `acceso_denegado.php`
- `diagnostico.php`
- `mapa_sistema.php`

Compatibilidad:
- En la raiz siguen existiendo archivos con los mismos nombres como wrappers.
- Eso permite que las URLs antiguas sigan funcionando sin cambios.

Puentes locales:
- `frontend/conexion.php`
- `frontend/guard.php`
- `frontend/permisos.php`
- `frontend/demo_config.php`

Estos puentes redirigen al backend para mantener includes existentes.

