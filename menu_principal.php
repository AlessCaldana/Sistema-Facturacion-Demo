<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Menú Principal</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #ffffff;
            color: #333;
        }
        header {
            background: #1f6691;
            color: #fff;
            padding: 15px 30px;
            text-align: center;
            box-shadow: 0 3px 6px rgba(0,0,0,0.2);
        }
        header h2 {
            margin: 0;
            font-size: 1.8rem;
        }
        .container {
            max-width: 700px;
            margin: 50px auto;
            background: linear-gradient(135deg, #1f6691, #2980b9);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 18px rgba(0,0,0,0.2);
            color: #fff;
        }
        h3 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.4rem;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        ul li {
            margin: 15px 0;
        }
        ul li a {
            display: block;
            padding: 14px;
            text-decoration: none;
            font-size: 1.1rem;
            border-radius: 8px;
            background: #ffffff;
            color: #1f6691;
            font-weight: bold;
            transition: 0.3s;
        }
        ul li a:hover {
            background: #6dd5fa;
            color: #000;
            transform: translateX(5px);
        }
        .logout {
            text-align: center;
            margin-top: 25px;
        }
        .logout a {
            color: #ff4d4d;
            font-weight: bold;
            text-decoration: none;
            background: #fff;
            padding: 10px 15px;
            border-radius: 6px;
            display: inline-block;
            transition: 0.3s;
        }
        .logout a:hover {
            background: #ffe5e5;
            text-decoration: none;
        }
    </style>
</head>
<body>
<header>
    <h2>Bienvenido</h2>
</header>

<div class="container">
    <h3>Menú Principal</h3>
    <ul>
        <li><a href="mantenimiento_tablas_D.php">1. Mantenimiento de Tablas</a></li>
        <li><a href="generar_factura.php">2. Generar Factura</a></li>

        <!-- Control de Pagos ahora es el #3 -->
        <li><a href="control_pagos.php">3. Control de Pagos</a></li>

        <!-- Control de Entregas ahora es el #4 -->
        <li><a href="control_entregas.php">4. Control de Entregas</a></li>

        <!-- Consulta de Facturas ahora es el #5 -->
        <li><a href="consultar_factura.php">5. Consulta de Facturas</a></li>

        <li><a href="usuarios_mantenimiento.php">6. Mantenimiento de Usuarios</a></li>
        <li><a href="reportes.php">7. Reportes</a></li>
</ul>

    <div class="logout">
        <a href="logout.php">Cerrar sesión</a>
    </div>
</div>
</body>
</html>
