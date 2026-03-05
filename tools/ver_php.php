<?php
header("Content-Type: text/html; charset=UTF-8");

$version = phpversion();

// Funciones que existen solo en versiones nuevas
$hasPasswordHash  = function_exists('password_hash');
$hasPasswordVerify = function_exists('password_verify');
$hasMySQLi = extension_loaded('mysqli');
$hasPDO    = extension_loaded('pdo_mysql');
$hasGD     = extension_loaded('gd');
$hasMB     = extension_loaded('mbstring');

echo "<h2>📌 Información de PHP</h2>";
echo "<b>Versión actual:</b> $version <br><br>";

echo "<h3>🔍 Compatibilidad del servidor:</h3>";

// Ver si el servidor está por debajo, igual o por encima
if ($version < '5.3') {
    echo "❌ <b>Tu servidor está por debajo de PHP 5.3</b> (muy antiguo, requiere actualización).<br>";
} elseif ($version >= '5.3' && $version < '5.5') {
    echo "✅ <b>Tu servidor soporta PHP 5.3</b><br>";
    echo "⚠ No soporta password_hash() — Se debe usar sha1() o md5().<br>";
} elseif ($version >= '5.5' && $version < '7.0') {
    echo "✅ <b>Tu servidor es moderno (PHP 5.5+)</b><br>";
    echo "✅ Soporta password_hash().<br>";
} else {
    echo "✅ Tu servidor es muy moderno (PHP $version)<br>";
    echo "⚠ Si tu código fue creado para PHP 5.3, hay que ajustar sintaxis.<br>";
}

echo "<br><h3>🔧 Extensiones importantes:</h3>";

echo "PDO (pdo_mysql): " . ($hasPDO ? "✅ Cargada" : "❌ No disponible") . "<br>";
echo "MySQLi: " . ($hasMySQLi ? "✅ Cargada" : "❌ No disponible") . "<br>";
echo "GD (imágenes): " . ($hasGD ? "✅ Disponible" : "❌ No disponible") . "<br>";
echo "MBString (acentos y UTF-8): " . ($hasMB ? "✅ Disponible" : "❌ No disponible") . "<br>";

echo "<br><h3>🔐 Soporte de funciones de contraseña:</h3>";
echo "password_hash(): " . ($hasPasswordHash ? "✅ Disponible" : "❌ No disponible") . "<br>";
echo "password_verify(): " . ($hasPasswordVerify ? "✅ Disponible" : "❌ No disponible") . "<br>";

echo "<br><hr><a href='?phpinfo=1'>Ver phpinfo() completo</a>";

if (isset($_GET['phpinfo'])) {
    phpinfo();
}
?>
