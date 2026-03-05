<?php
/* acceso_denegado.php — limpio, sin módulo/acción ni fecha/hora */
session_start();

$usuario = htmlspecialchars(isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado');
$rol     = htmlspecialchars(isset($_SESSION['rol']) ? $_SESSION['rol'] : 'sin rol');

/* Si tu plantilla anterior traía $mod y $acc, simplemente NO los usamos */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Acceso denegado</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{ --rojo:#c0392b; --borde:#e5e7eb; --azul:#2980b9; --bg:#f6f8fa; }
  *{box-sizing:border-box}
  body{font-family:Segoe UI,Arial,sans-serif;background:var(--bg);margin:0;padding:24px}
  .box{max-width:720px;margin:0 auto;background:#fff;border:1px solid var(--borde);
       border-radius:14px;padding:24px 22px;box-shadow:0 8px 20px rgba(0,0,0,.06)}
  h1{margin:0 0 14px;color:var(--rojo);display:flex;gap:10px;align-items:center}
  h1:before{content:"🚫"}
  .meta{color:#334155;margin:4px 0 14px;font-weight:600}
  .msg{margin:10px 0 18px;color:#0b3850}
  .btn{display:inline-block;padding:10px 16px;border-radius:10px;background:var(--azul);
       color:#fff;text-decoration:none;font-weight:700}
  .btn:hover{filter:brightness(1.05)}
</style>
</head>
<body>
  <div class="box">
    <h1>Acceso denegado</h1>

    <div class="meta">Usuario: <?php echo $usuario; ?> &nbsp;&nbsp; Rol: <?php echo $rol; ?></div>

    <!-- Mensaje principal -->
    <p class="msg">No cuentas con permisos para acceder a este recurso.</p>

    <!-- Botón volver -->
    <a class="btn" href="menu_principal.php">Volver al menú</a>
  </div>
</body>
</html>
