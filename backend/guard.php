<?php
// guard.php â€” PHP 5.3 compatible

// Iniciar sesiÃ³n en 5.3 (no existe session_status)
if (session_id() === '') {
    session_start();
}
require_once __DIR__ . '/demo_config.php';

/* ========= Helpers de sesiÃ³n ========= */
function current_user() {
    return (string)(isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '');
}
function current_role() {
    $rol = (isset($_SESSION['rol']) ? $_SESSION['rol'] : '');
    return strtolower(trim((string)$rol));
}

/* ========= Forzar login ========= */
if (current_user() === '' || current_role() === '') {
    if (defined('DEMO_PUBLICO') && DEMO_PUBLICO && defined('DEMO_AUTOLOGIN') && DEMO_AUTOLOGIN) {
        $_SESSION['usuario'] = (defined('DEMO_USUARIO_DOC') ? DEMO_USUARIO_DOC : 'demo');
        $_SESSION['nombre']  = (defined('DEMO_USUARIO_NOMBRE') ? DEMO_USUARIO_NOMBRE : 'Usuario Demo');
        $_SESSION['rol']     = strtolower((defined('DEMO_USUARIO_ROL') ? DEMO_USUARIO_ROL : 'admin'));
    } else {
        header('Location: login.php');
        exit;
    }
}

/* ========= MATRIZ DE PERMISOS =========
   Claves: usa estas mismas en require_perm('<clave>')
*/
$__PERMISOS = array(
    'tablas'        => array('admin'),
    'generar'       => array('admin', 'vendedor'),
    'entregas'      => array('admin', 'despachador'),
    'consultas'     => array('admin', 'despachador', 'vendedor', 'consulta'),
    'usuarios'      => array('admin'),
    'rep_ventas'    => array('admin', 'vendedor', 'consulta'),   // consulta = solo lectura
    'rep_entregas'  => array('admin', 'despachador', 'consulta'),// consulta = solo lectura
);

/* ========= Acciones (read/write) ========= */
$__ACCIONES = array(
    'rep_ventas' => array(
        'read'  => array('admin', 'vendedor', 'consulta'),
        'write' => array('admin', 'vendedor'), // consulta NO escribe
    ),
    'rep_entregas' => array(
        'read'  => array('admin', 'despachador', 'consulta'),
        'write' => array('admin', 'despachador'), // consulta NO escribe
    ),
);

/* ========= Alias / normalizaciÃ³n de claves ========= */
$__ALIAS_PERM = array(
    'consulta'          => 'consultas',     // singular â†’ plural
    'reporte_ventas'    => 'rep_ventas',
    'reporte_entregas'  => 'rep_entregas',
);

function normalize_perm($perm) {
    global $__ALIAS_PERM;
    $k = strtolower(trim($perm));
    return (isset($__ALIAS_PERM[$k]) ? $__ALIAS_PERM[$k] : $k);
}

/* ========= API de permiso ========= */
function has_perm($perm, $action = 'read') {
    global $__PERMISOS, $__ACCIONES;
    $perm = normalize_perm($perm);
    $rol  = current_role();

    // Admin es superusuario
    if ($rol === 'admin') return true;

    // 1) ValidaciÃ³n de entrada al mÃ³dulo
    $rolesModulo = (isset($__PERMISOS[$perm]) ? $__PERMISOS[$perm] : null);
    if (!$rolesModulo || !in_array($rol, $rolesModulo, true)) {
        return false;
    }

    // 2) ValidaciÃ³n por acciÃ³n si aplica
    if (isset($__ACCIONES[$perm])) {
        $accion = strtolower($action);
        $permitidosAccion = (isset($__ACCIONES[$perm][$accion]) ? $__ACCIONES[$perm][$accion] : array());
        return in_array($rol, $permitidosAccion, true);
    }

    // Por defecto, entrar = permitido
    return true;
}

function require_perm($perm, $action = 'read') {
    $perm = normalize_perm($perm);
    if (!has_perm($perm, $action)) {
        // PHP 5.3 no tiene http_response_code
        header('HTTP/1.1 403 Forbidden');
        $qs = http_build_query(array(
            'm' => $perm,
            'a' => $action,
            'r' => current_role(),
            'u' => current_user(),
        ));
        header('Location: acceso_denegado.php?' . $qs);
        exit;
    }
}

/* ========= Atajos ========= */
function can($perm, $action = 'read') {
    return has_perm($perm, $action);
}

function require_any($perms, $action = 'read') {
    foreach ($perms as $p) {
        if (has_perm($p, $action)) return;
    }
    header('HTTP/1.1 403 Forbidden');
    $norm = array();
    foreach ($perms as $p) { $norm[] = normalize_perm($p); }
    $qs = http_build_query(array(
        'm' => implode('|', $norm),
        'a' => $action,
        'r' => current_role(),
        'u' => current_user(),
    ));
    header('Location: acceso_denegado.php?' . $qs);
    exit;
}

function require_all($perms, $action = 'read') {
    foreach ($perms as $p) {
        if (!has_perm($p, $action)) {
            header('HTTP/1.1 403 Forbidden');
            $qs = http_build_query(array(
                'm' => normalize_perm($p),
                'a' => $action,
                'r' => current_role(),
                'u' => current_user(),
            ));
            header('Location: acceso_denegado.php?' . $qs);
            exit;
        }
    }
}
