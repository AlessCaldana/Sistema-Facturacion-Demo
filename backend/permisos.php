<?php
// permisos.php (PHP 5.3 compatible)

// === CLAVES DE PERMISOS ===
const PERM_MANT_TABLAS       = 'mant_tablas';
const PERM_GENERAR_FACTURA   = 'generar_factura';
const PERM_CONTROL_ENTREGAS  = 'control_entregas';
const PERM_CONSULTA_FACTURAS = 'consulta_facturas';
const PERM_MANT_USUARIOS     = 'mant_usuarios';
const PERM_REPORTES_VENTAS   = 'reportes_ventas';
const PERM_REPORTES_ENTREGAS = 'reportes_entregas';

// === QUIÉN PUEDE ENTRAR A CADA MÓDULO ===
$__MATRIZ_PERMISOS = array(
    PERM_MANT_TABLAS       => array('admin'),
    PERM_GENERAR_FACTURA   => array('admin', 'vendedor'),
    PERM_CONTROL_ENTREGAS  => array('admin', 'despachador'),
    PERM_CONSULTA_FACTURAS => array('admin', 'despachador', 'vendedor', 'consulta'),
    PERM_MANT_USUARIOS     => array('admin'),

    // Nueva lógica:
    PERM_REPORTES_VENTAS   => array('admin', 'vendedor', 'consulta'),
    PERM_REPORTES_ENTREGAS => array('admin', 'vendedor', 'consulta'),
);

// === CONTROL DE ACCIONES (EXPORTAR / EDITAR / LEER) ===
$__ACCIONES = array(
    PERM_REPORTES_VENTAS => array(
        'read'  => array('admin', 'vendedor', 'consulta'), // pueden ver en pantalla
        'write' => array('admin', 'vendedor'),              // solo estos exportan
    ),
    PERM_REPORTES_ENTREGAS => array(
        'read'  => array('admin', 'vendedor', 'consulta'),
        'write' => array('admin', 'vendedor'),
    ),
);

function rolActual() {
    $rol = (isset($_SESSION['rol']) ? $_SESSION['rol'] : '');
    return strtolower($rol);
}

function tienePermiso($modulo) {
    global $__MATRIZ_PERMISOS;
    $rol = rolActual();
    if (!$rol) return false;

    $lista = (isset($__MATRIZ_PERMISOS[$modulo]) ? $__MATRIZ_PERMISOS[$modulo] : array());
    return in_array($rol, $lista, true);
}

function puede($modulo, $accion = 'read') {
    global $__ACCIONES;
    $rol = rolActual();
    if (!$rol) return false;

    if (!isset($__ACCIONES[$modulo])) {
        // Si no hay definición por acción, usar la matriz general
        return tienePermiso($modulo);
    }
    $lista = (isset($__ACCIONES[$modulo][$accion]) ? $__ACCIONES[$modulo][$accion] : array());
    return in_array($rol, $lista, true);
}
