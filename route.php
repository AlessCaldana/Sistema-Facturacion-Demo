<?php
/* Router de compatibilidad para URLs antiguas tipo /archivo.php */

if (!isset($_GET['file'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Solicitud invalida');
}

$file = basename((string)$_GET['file']);
if (!preg_match('/^[A-Za-z0-9_-]+\.php$/', $file)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Archivo invalido');
}

$base = __DIR__;
$candidates = array(
    $base . '/frontend/' . $file,
    $base . '/backend/' . $file,
    $base . '/tools/' . $file,
);

foreach ($candidates as $target) {
    if (file_exists($target)) {
        require $target;
        exit;
    }
}

header('HTTP/1.1 404 Not Found');
exit('No encontrado');

