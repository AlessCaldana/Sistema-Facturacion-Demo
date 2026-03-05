<?php
session_start();
require_once __DIR__ . '/demo_config.php';

if (defined('DEMO_PUBLICO') && DEMO_PUBLICO && defined('DEMO_RESET_ON_LOGOUT') && DEMO_RESET_ON_LOGOUT) {
    require_once __DIR__ . '/conexion.php';
    if (isset($pdo)) {
        demo_reset_data($pdo);
    }
}

// Limpia variables de sesion
$_SESSION = array();

// Elimina cookie de sesion (si existe)
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destruye sesion
session_destroy();

// Redirige al login
header("Location: login.php");
exit;
?>
