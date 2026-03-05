<?php
/* ========= Panel de Mantenimiento ========= */
session_start();
require_once __DIR__.'/guard.php';
require_once __DIR__.'/permisos.php';
require_perm(PERM_MANT_TABLAS);

if (!ini_get('date.timezone')) { date_default_timezone_set('America/Bogota'); }
$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Mantenimiento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --azul:#1f6691;
      --azul-2:#2f83b5;
      --azul-3:#154c6d;
      --txt:#153549;
      --bg:#eef3f8;
      --line:#d9e5ef;
      --danger:#d9534f;
      --danger-2:#b63733;
    }
    html,body{margin:0 !important;padding:0 !important;}

    body{
      margin:0;
      min-height:100vh;
      background:linear-gradient(180deg,#f3f7fb 0%,var(--bg) 100%);
      font-family:"Segoe UI",Tahoma,Arial,sans-serif;
      color:var(--txt);
    }

    .topbar{
      margin:0 !important;
      position:sticky;
      top:0;
      z-index:1100;
      background:linear-gradient(135deg,var(--azul),var(--azul-2));
      box-shadow:0 8px 18px rgba(13,53,79,.18);
    }
    .topbar-inner{
      max-width:1320px;
      margin:0 auto;
      padding:10px 14px;
      display:grid;
      grid-template-columns:auto 1fr auto;
      align-items:center;
      gap:10px;
    }
    .btn-volver{
      display:inline-flex;
      align-items:center;
      gap:8px;
      text-decoration:none;
      color:#fff;
      border:1px solid rgba(255,255,255,.40);
      border-radius:10px;
      padding:8px 12px;
      font-weight:800;
      background:rgba(255,255,255,.08);
      transition:.2s ease;
    }
    .btn-volver:hover{
      color:#fff;
      background:rgba(255,255,255,.16);
      border-color:rgba(255,255,255,.55);
    }
    .topbar-title{
      margin:0;
      color:#fff;
      text-align:center;
      font-size:1.55rem;
      font-weight:900;
      letter-spacing:.2px;
    }

    .user-btn{
      justify-self:end;
      display:inline-flex;
      align-items:center;
      gap:8px;
      cursor:pointer;
      color:#fff;
      padding:7px 10px;
      border-radius:12px;
      position:relative;
      user-select:none;
    }
    .user-btn:hover{ background:rgba(255,255,255,.10); }

    .user-menu{
      position:absolute;
      right:0;
      top:calc(100% + 8px);
      min-width:190px;
      display:none;
      background:#fff;
      border:1px solid var(--line);
      border-radius:12px;
      padding:6px;
      box-shadow:0 14px 26px rgba(0,0,0,.16);
      z-index:1200;
    }
    .user-item{
      display:flex;
      align-items:center;
      gap:8px;
      padding:10px 12px;
      border-radius:10px;
      text-decoration:none;
      font-weight:700;
    }
    .user-item.logout{ color:var(--danger); }
    .user-item.logout:hover{ background:#ffe6e5; color:var(--danger-2); }

    .main-wrap{ max-width:1220px; margin:28px auto; padding:0 14px; }

    .panel{
      background:#fff;
      border:1px solid #e3ecf4;
      border-radius:16px;
      box-shadow:0 12px 28px rgba(17,58,84,.10);
      overflow:hidden;
    }
    .panel-head{
      background:linear-gradient(135deg,var(--azul),var(--azul-2));
      color:#fff;
      padding:16px 20px;
    }
    .panel-head h3{ margin:0; font-size:1.35rem; font-weight:900; }
    .panel-head p{ margin:5px 0 0; opacity:.95; }
    .panel-body{ padding:18px; }

    .menu-card{
      background:#fff;
      border:1px solid var(--line);
      border-radius:16px;
      padding:22px 16px;
      text-align:center;
      transition:all .22s ease;
      box-shadow:0 4px 10px rgba(16,54,78,.04);
      height:100%;
    }
    .menu-card:hover{
      transform:translateY(-3px);
      border-color:#bfd7e8;
      box-shadow:0 12px 22px rgba(22,73,106,.13);
      background:#f9fcff;
    }
    .menu-icon{
      width:62px;
      height:62px;
      margin:0 auto 12px;
      border-radius:16px;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:1.7rem;
      color:var(--azul-3);
      background:linear-gradient(135deg,rgba(31,102,145,.12),rgba(47,131,181,.18));
      border:1px solid #c8ddeb;
    }
    .menu-card h4{ margin:0 0 6px; font-weight:900; color:#1c4963; }
    .menu-card p{ margin:0 0 14px; color:#567286; font-weight:600; min-height:42px; }
    .btn-main{
      background:linear-gradient(135deg,var(--azul),var(--azul-2));
      border:none;
      color:#fff;
      border-radius:10px;
      font-weight:800;
      padding:9px 16px;
    }
    .btn-main:hover{ color:#fff; filter:brightness(1.06); }

    .modal-mask{
      position:fixed;
      inset:0;
      background:rgba(10,14,23,.45);
      display:none;
      align-items:center;
      justify-content:center;
      z-index:1300;
      backdrop-filter:saturate(120%) blur(2px);
    }
    .modal-card{
      width:min(92vw,460px);
      background:#fff;
      border-radius:16px;
      box-shadow:0 18px 50px rgba(0,0,0,.25);
      overflow:hidden;
      transform:translateY(10px) scale(.98);
      opacity:0;
      transition:transform .18s ease,opacity .18s ease;
    }
    .modal-head{
      display:flex;
      align-items:center;
      gap:10px;
      padding:14px 18px;
      border-bottom:1px solid #eef1f5;
    }
    .modal-head .badge-icon{
      width:36px;
      height:36px;
      border-radius:10px;
      display:flex;
      align-items:center;
      justify-content:center;
      background:#ffe5e5;
      color:var(--danger);
    }
    .modal-title{ margin:0; font-size:18px; font-weight:800; color:#182230; }
    .modal-body{ padding:16px 18px; color:#334155; }
    .modal-foot{
      padding:14px 18px;
      display:flex;
      gap:10px;
      justify-content:flex-end;
      background:#fafbfd;
      border-top:1px solid #eef1f5;
    }
    .btn-danger-soft{
      background:var(--danger);
      color:#fff;
      border:none;
      border-radius:10px;
      padding:8px 14px;
      font-weight:700;
    }
    .btn-danger-soft:hover{ background:var(--danger-2); }
    .btn-ghost{
      background:#fff;
      border:1px solid #e5e7eb;
      color:#111827;
      border-radius:10px;
      padding:8px 14px;
      font-weight:600;
    }
    .btn-ghost:hover{ background:#f3f4f6; }

    .modal-mask.show{ display:flex; }
    .modal-mask.show .modal-card{ transform:translateY(0) scale(1); opacity:1; }

    footer{
      text-align:center;
      color:#3f647c;
      padding:16px 10px 20px;
      font-weight:600;
    }

    @media (max-width: 900px){ .topbar-title{ font-size:1.25rem; } }
    @media (max-width: 768px){
      .topbar-inner{ grid-template-columns:1fr; gap:8px; }
      .btn-volver,.user-btn{ justify-self:center; }
      .topbar-title{ font-size:1.1rem; }
    }
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a href="menu_principal.php" class="btn-volver"><i class="bi bi-arrow-left-circle"></i> Volver</a>
    <h1 class="topbar-title">Panel de Mantenimiento</h1>

    <div class="user-btn" id="userBtn">
      <i class="bi bi-person-circle"></i>
      <span><?php echo htmlspecialchars($usuario); ?></span>
      <div class="user-menu" id="userMenu">
        <a href="logout.php" data-href="logout.php" class="user-item logout" id="btnOpenLogout">
          <i class="bi bi-box-arrow-right"></i> Cerrar sesion
        </a>
      </div>
    </div>
  </div>
</header>

<div class="modal-mask" id="logoutModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="logoutTitle">
  <div class="modal-card" role="document">
    <div class="modal-head">
      <div class="badge-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <h2 id="logoutTitle" class="modal-title">Cerrar sesion</h2>
    </div>
    <div class="modal-body">
      <p>Vas a salir del sistema. Si continuas, se cerrara tu sesion actual.</p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn-ghost" id="cancelLogout">Cancelar</button>
      <button type="button" class="btn-danger-soft" id="confirmLogout">Si, cerrar sesion</button>
    </div>
  </div>
</div>

<div class="main-wrap">
  <section class="panel">
    <div class="panel-head">
      <h3>Mantenimiento de Tablas</h3>
      <p>Selecciona el modulo que deseas administrar.</p>
    </div>

    <div class="panel-body">
      <div class="row g-4 justify-content-center">
        <div class="col-md-4">
          <div class="menu-card">
            <div class="menu-icon"><i class="bi bi-people-fill"></i></div>
            <h4>Clientes</h4>
            <p>Gestion de clientes y datos de contacto.</p>
            <a href="clientes_D.php" class="btn btn-main">Ingresar</a>
          </div>
        </div>

        <div class="col-md-4">
          <div class="menu-card">
            <div class="menu-icon"><i class="bi bi-box-seam"></i></div>
            <h4>Productos</h4>
            <p>Administrar productos, precios y stock.</p>
            <a href="productos_D.php" class="btn btn-main">Ingresar</a>
          </div>
        </div>

        <div class="col-md-4">
          <div class="menu-card">
            <div class="menu-icon"><i class="bi bi-truck"></i></div>
            <h4>Vehiculos</h4>
            <p>Registro de vehiculos y conductores.</p>
            <a href="vehiculos_D.php" class="btn btn-main">Ingresar</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<footer><small>&copy; <?php echo date('Y'); ?> - Sistema de Facturacion</small></footer>

<script>
(function(){
  var btn = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if (btn && menu) {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    });
    document.addEventListener('click', function(e){
      if (!btn.contains(e.target)) menu.style.display = 'none';
    });
  }

  var modal = document.getElementById('logoutModal');
  var openBtn = document.getElementById('btnOpenLogout');
  var cancelBtn = document.getElementById('cancelLogout');
  var confirmBtn = document.getElementById('confirmLogout');
  var targetHref = 'logout.php';

  function openModal(e){
    if (e) e.preventDefault();
    targetHref = (openBtn && openBtn.getAttribute('data-href')) || 'logout.php';
    modal.classList.add('show');
    modal.setAttribute('aria-hidden','false');
  }
  function closeModal(){
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden','true');
  }

  if (openBtn) openBtn.addEventListener('click', openModal, false);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal, false);
  if (confirmBtn) confirmBtn.addEventListener('click', function(){ window.location.href = targetHref; }, false);
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
  if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
})();
</script>

</body>
</html>

