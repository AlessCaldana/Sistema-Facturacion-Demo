<?php
session_start();
require_once __DIR__ . '/demo_config.php';

if (
    (!isset($_SESSION['usuario']) || $_SESSION['usuario'] === '') &&
    defined('DEMO_PUBLICO') && DEMO_PUBLICO &&
    defined('DEMO_AUTOLOGIN') && DEMO_AUTOLOGIN
) {
    $_SESSION['usuario'] = (defined('DEMO_USUARIO_DOC') ? DEMO_USUARIO_DOC : 'demo');
    $_SESSION['nombre']  = (defined('DEMO_USUARIO_NOMBRE') ? DEMO_USUARIO_NOMBRE : 'Usuario Demo');
    $_SESSION['rol']     = strtolower((defined('DEMO_USUARIO_ROL') ? DEMO_USUARIO_ROL : 'admin'));
}

if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] === '') {
    header('Location: login.php');
    exit;
}

$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Menu Principal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
  html,body{margin:0 !important;padding:0 !important;} 
  header,.topbar{margin-top:0 !important;}
    :root{
      --azul:#1f6691;
      --azul-2:#2f83b5;
      --azul-3:#154c6d;
      --bg:#eef3f8;
      --panel:#ffffff;
      --txt:#153549;
      --muted:#5d7688;
      --line:#d9e5ef;
      --danger:#d9534f;
      --danger-2:#b63733;
      --warning-bg:#fff4cf;
      --warning-line:#f0d88d;
      --warning-txt:#6e5200;
    }

    * { box-sizing: border-box; }

    body{
      margin:0;
      min-height:100vh;
      font-family: "Segoe UI", Tahoma, Arial, sans-serif;
      color:var(--txt);
      background:
        radial-gradient(1200px 500px at 50% -200px, #dcecf8 0%, rgba(220,236,248,0) 70%),
        linear-gradient(180deg, #f3f7fb 0%, var(--bg) 100%);
    }

    .topbar{
      position: sticky;
      top: 0;
      z-index: 1000;
      background: linear-gradient(135deg, var(--azul), var(--azul-2));
      box-shadow: 0 8px 18px rgba(13, 53, 79, .20);
      border-bottom: 1px solid rgba(255,255,255,.12);
    }

    .topbar-inner{
      max-width: 1320px;
      margin: 0 auto;
      padding: 10px 14px;
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      gap: 10px;
    }

        .topbar-title{
      margin:0;
      text-align:center;
      color:#fff;
      font-size: 2rem;
      font-weight: 900;
      letter-spacing: .3px;
      text-shadow: 0 2px 4px rgba(0,0,0,.15);
    }

    .left-slot{justify-self:start;min-width:220px;}
    .user-btn{
      justify-self:end;
      display:inline-flex;
      align-items:center;
      gap:9px;
      color:#fff;
      padding:7px 10px;
      border-radius: 12px;
      cursor:pointer;
      position:relative;
      transition: background .2s ease;
      user-select: none;
    }
    .user-btn:hover{ background:rgba(255,255,255,.10); }

    .user-avatar{
      width:29px;
      height:29px;
      border-radius: 50%;
      display:flex;
      align-items:center;
      justify-content:center;
      background: rgba(255,255,255,.2);
      border:1px solid rgba(255,255,255,.35);
    }

    .user-menu{
      position:absolute;
      right:0;
      top:calc(100% + 8px);
      min-width:190px;
      display:none;
      background:#fff;
      border:1px solid var(--line);
      border-radius: 12px;
      padding:6px;
      box-shadow: 0 14px 26px rgba(0,0,0,.16);
      z-index:2000;
    }

    .user-item{
      display:flex;
      align-items:center;
      gap:8px;
      padding:10px 12px;
      border-radius:9px;
      text-decoration:none;
      color:var(--danger);
      font-weight:800;
    }
    .user-item:hover{ background:#ffe6e5; color:var(--danger-2); }

    .main-wrap{
      max-width: 980px;
      margin: 30px auto;
      padding: 0 14px 24px;
    }

    .panel{
      background: var(--panel);
      border:1px solid #e3ecf4;
      border-radius: 18px;
      box-shadow: 0 12px 30px rgba(17, 58, 84, .10);
      overflow: hidden;
      animation: rise .35s ease;
    }

    .panel-head{
      background: linear-gradient(135deg, var(--azul), var(--azul-2));
      color:#fff;
      padding:18px 20px;
      border-bottom: 1px solid rgba(255,255,255,.14);
    }
    .panel-head h3{
      margin:0;
      font-size:1.6rem;
      font-weight:900;
      letter-spacing:.2px;
    }
    .panel-head p{
      margin:6px 0 0;
      opacity:.95;
      font-size:.95rem;
    }

    .panel-body{ padding:18px; }

    .demo-alert{
      background:var(--warning-bg);
      border:1px solid var(--warning-line);
      color:var(--warning-txt);
      border-radius:12px;
      padding:12px 14px;
      margin-bottom:15px;
      font-weight:700;
    }

    .menu-grid{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:12px;
    }

    .menu-link{
      position:relative;
      display:flex;
      align-items:center;
      gap:12px;
      padding:13px 14px;
      border-radius:13px;
      text-decoration:none;
      color:var(--txt);
      background:#fff;
      border:1px solid var(--line);
      font-weight:800;
      transition: all .22s ease;
      overflow:hidden;
      box-shadow: 0 3px 8px rgba(16,54,78,.04);
    }

    .menu-link::after{
      content:"";
      position:absolute;
      right:-40px;
      top:-20px;
      width:90px;
      height:90px;
      border-radius:50%;
      background: rgba(47,131,181,.10);
      transition: all .25s ease;
    }

    .menu-link:hover{
      transform: translateY(-2px);
      border-color:#bfd7e8;
      background:#f7fcff;
      box-shadow: 0 10px 18px rgba(22,73,106,.13);
      color:#0f3a54;
    }

    .menu-link.disabled{
      opacity: 0.5;
      cursor: not-allowed;
      background: #f5f5f5;
      border-color: #ddd;
      color: #999;
    }

    .menu-link.disabled:hover{
      transform: none;
      border-color: #ddd;
      background: #f5f5f5;
      box-shadow: 0 3px 8px rgba(16,54,78,.04);
      color: #999;
    }

    .menu-link.disabled .icon-chip{
      background: linear-gradient(135deg, rgba(153,153,153,.14), rgba(153,153,153,.22));
      color: #999;
      border-color: #ddd;
    }

    .icon-chip{
      width:34px;
      height:34px;
      border-radius:10px;
      display:flex;
      align-items:center;
      justify-content:center;
      background:linear-gradient(135deg, rgba(31,102,145,.14), rgba(47,131,181,.22));
      color:var(--azul-3);
      font-size:1rem;
      border:1px solid #c8ddeb;
      flex-shrink:0;
      position:relative;
      z-index:2;
    }

    .menu-text{
      position:relative;
      z-index:2;
      font-size:1.08rem;
      letter-spacing:.1px;
    }

    .logout-row{ text-align:center; margin-top:18px; }
    .logout-btn{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:11px 16px;
      border-radius:11px;
      text-decoration:none;
      color:#fff;
      font-weight:800;
      background: linear-gradient(135deg, var(--danger), #e06561);
      box-shadow: 0 8px 18px rgba(217,83,79,.28);
      transition: all .2s ease;
    }
    .logout-btn:hover{
      color:#fff;
      transform: translateY(-1px);
      background: linear-gradient(135deg, var(--danger-2), #d24d49);
      box-shadow: 0 10px 20px rgba(182,55,51,.30);
    }

    @keyframes rise{
      from{ opacity:0; transform: translateY(8px); }
      to{ opacity:1; transform: translateY(0); }
    }

    @media (max-width: 980px){
      .topbar-title{ font-size:1.5rem; }
    }

    @media (max-width: 768px){
      .topbar-inner{ grid-template-columns:1fr; padding:10px 12px; gap:8px; }
      .left-slot{justify-self:start;min-width:220px;}
    .user-btn{ justify-self:center; }
      .topbar-title{ font-size:1.2rem; }
      .menu-grid{ grid-template-columns:1fr; }
      .main-wrap{ margin-top:18px; }
      .panel-head h3{ font-size:1.25rem; }
      .menu-text{ font-size:1rem; }
    }
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <div class="left-slot" aria-hidden="true"></div>
    <h1 class="topbar-title">Menu Principal</h1>

    <div class="user-btn" id="userBtn">
      <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
      <span><?php echo htmlspecialchars($usuario); ?></span>
      <div class="user-menu" id="userMenu">
        <a href="logout.php" class="user-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesion</a>
      </div>
    </div>
  </div>
</header>

<div class="main-wrap">
  <section class="panel">
    <div class="panel-head">
      <h3>Accesos del Sistema</h3>
      <p>Selecciona el modulo que deseas administrar.</p>
    </div>

    <div class="panel-body">
      <?php if (function_exists('demo_activo') && demo_activo()): ?>
        <div class="demo-alert">ENTORNO DEMO: datos temporales de prueba.</div>
      <?php endif; ?>

      <div class="menu-grid">
        <a class="menu-link" href="mantenimiento_tablas_D.php">
          <span class="icon-chip"><i class="bi bi-table"></i></span>
          <span class="menu-text">1. Mantenimiento de Tablas</span>
        </a>

        <a class="menu-link" href="generar_factura.php">
          <span class="icon-chip"><i class="bi bi-file-earmark-text"></i></span>
          <span class="menu-text">2. Generar Factura</span>
        </a>

        <a class="menu-link" href="control_pagos.php">
          <span class="icon-chip"><i class="bi bi-cash-stack"></i></span>
          <span class="menu-text">3. Control de Pagos</span>
        </a>

        <a class="menu-link disabled" href="#" onclick="alert('Módulo próximamente disponible'); return false;">
          <span class="icon-chip"><i class="bi bi-truck"></i></span>
          <span class="menu-text">4. Control de Entregas</span>
        </a>

        <a class="menu-link disabled" href="#" onclick="alert('Módulo próximamente disponible'); return false;">
          <span class="icon-chip"><i class="bi bi-search"></i></span>
          <span class="menu-text">5. Consulta de Facturas</span>
        </a>

        <a class="menu-link disabled" href="#" onclick="alert('Módulo próximamente disponible'); return false;">
          <span class="icon-chip"><i class="bi bi-people"></i></span>
          <span class="menu-text">6. Mantenimiento de Usuarios</span>
        </a>

        <a class="menu-link disabled" href="#" onclick="alert('Módulo próximamente disponible'); return false;">
          <span class="icon-chip"><i class="bi bi-bar-chart"></i></span>
          <span class="menu-text">7. Reportes</span>
        </a>
      </div>

      <div class="logout-row">
        <a class="logout-btn" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesion</a>
      </div>
    </div>
  </section>
</div>

<script>
(function(){
  var btn = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if (!btn || !menu) return;

  btn.addEventListener('click', function(e){
    e.preventDefault();
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
  });

  document.addEventListener('click', function(e){
    if (!btn.contains(e.target)) {
      menu.style.display = 'none';
    }
  });
})();
</script>

</body>
</html>




