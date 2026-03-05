# Backend

Esta carpeta contiene la capa tecnica central:
- `conexion.php` (acceso a base de datos)
- `guard.php` (sesion y permisos de acceso)
- `permisos.php` (matriz de permisos)
- `demo_config.php` (modo demo publico)
- `config_recaudo.php` (configuracion de recaudo)
- `recaudo_helpers.php` (helpers de recaudo)

Compatibilidad:
- En la raiz siguen existiendo los mismos archivos como wrappers.
- El codigo viejo que hace `require 'conexion.php'` o `require 'guard.php'` sigue funcionando.

Recomendacion:
- Nuevos helpers de negocio/seguridad deben vivir en `backend/`.
- Las paginas/pantallas deben vivir en `frontend/`.
