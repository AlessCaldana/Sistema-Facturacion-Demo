<?php
session_start();
// Limpia variables de sesión
$_SESSION = array();
// Elimina cookie de sesión (si existe)
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}
// Destruye sesión
session_destroy();
// Redirige al login
header("Location: login.php");
exit;
