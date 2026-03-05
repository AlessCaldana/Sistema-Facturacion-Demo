<?php
/* ========= Panel de Mantenimiento (topbar + dropdown + modal logout) ========= */
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
      --txt:#0b3850;
      --danger:#d9534f;
      --danger-2:#b52b27;
      --bg:#f8f9fa;
    }

    body{ background:var(--bg); min-height:100vh; display:flex; flex-direction:column; margin:0; font-family:Segoe UI,Arial,sans-serif; }

    /* ===== TOPBAR ===== */
    .topbar{
      background:var(--azul); color:#fff;
      padding:10px 0; position:sticky; top:0; z-index:9999;
    }
    .topbar-inner{
      width:100%; padding:0 16px;
      display:grid; grid-template-columns:auto 1fr auto; align-items:center;
    }
    .btn-volver{
      justify-self:start; display:inline-flex; align-items:center; gap:8px;
      background:#fff; color:var(--txt); border:none; border-radius:9999px; padding:8px 14px;
      font-weight:700; text-decoration:none; box-shadow:0 2px 6px rgba(0,0,0,.08);
    }
    .topbar-title{
      margin:0; color:#fff; font-size:20px; font-weight:800; text-align:center;
    }
    .user-btn{
      justify-self:end; display:inline-flex; align-items:center; gap:8px; cursor:pointer;
      color:#fff; padding:6px 10px; border-radius:10px; position:relative;
    }
    .user-avatar{
      width:28px; height:28px; border-radius:50%;
      display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.15);
    }
    .user-menu{
      position:absolute; right:0; top:100%; margin-top:8px; min-width:190px; display:none;
      background:#fff; color:var(--txt); border:1px solid #e5e7eb; border-radius:12px; padding:6px;
      box-shadow:0 12px 28px rgba(0,0,0,.20); z-index:10000;
    }
    .user-item{
      display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:10px;
      color:var(--txt); text-decoration:none; font-weight:600;
    }
    .user-item:hover{ background:#f5f7fb; }

    /* ===== CERRAR SESIÓN EN ROJO ===== */
    .user-item.logout{ color:var(--danger) !important; font-weight:700; }
    .user-item.logout:hover{ background:#ffe5e5 !important; color:var(--danger-2) !important; }

    /* ===== Tarjetas ===== */
    .menu-card{ transition:transform .2s, box-shadow .2s; }
    .menu-card:hover{ transform:translateY(-5px); box-shadow:0 6px 20px rgba(0,0,0,.15); }
    .menu-icon{ font-size:3rem; color:var(--azul); }

    footer{ background:var(--azul); color:#fff; }

    /* ===== MODAL BONITO (sin librerías) ===== */
    .modal-mask{
      position:fixed; inset:0; background:rgba(10,14,23,.45);
      display:none; align-items:center; justify-content:center; z-index:11000;
      backdrop-filter:saturate(120%) blur(2px);
    }
    .modal-card{
      width:min(92vw, 460px);
      background:#fff; border-radius:16px; box-shadow:0 18px 50px rgba(0,0,0,.25);
      overflow:hidden; transform:translateY(10px) scale(.98); opacity:0;
      transition:transform .18s ease, opacity .18s ease;
    }
    .modal-head{
      display:flex; align-items:center; gap:10px; padding:14px 18px; background:#fff;
      border-bottom:1px solid #eef1f5;
    }
    .modal-head .badge-icon{
      width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center;
      background:#ffe5e5; color:var(--danger);
    }
    .modal-title{ margin:0; font-size:18px; font-weight:800; color:#182230; }
    .modal-body{ padding:16px 18px; color:#334155; }
    .modal-foot{
      padding:14px 18px; display:flex; gap:10px; justify-content:flex-end; background:#fafbfd;
      border-top:1px solid #eef1f5;
    }
    .btn-danger-soft{
      background:var(--danger); color:#fff; border:none; border-radius:10px; padding:8px 14px; font-weight:700;
    }
    .btn-danger-soft:hover{ background:var(--danger-2); }
    .btn-ghost{
      background:#fff; border:1px solid #e5e7eb; color:#111827; border-radius:10px; padding:8px 14px; font-weight:600;
    }
    .btn-ghost:hover{ background:#f3f4f6; }

    /* estado visible */
    .modal-mask.show{ display:flex; }
    .modal-mask.show .modal-card{ transform:translateY(0) scale(1); opacity:1; }
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a href="menu_principal.php" class="btn-volver"><i class="bi bi-arrow-left-circle"></i> Volver</a>
    <h1 class="topbar-title">Panel de Mantenimiento</h1>

    <div class="user-btn" id="userBtn">
      <div class="user-avatar"><i class="bi bi-person-fill" style="color:#fff;"></i></div>
      <span><?php echo htmlspecialchars($usuario); ?></span>
      <div class="user-menu" id="userMenu">
        <!-- IMPORTANTE: data-href para interceptar y abrir el modal -->
        <a href="logout.php" data-href="logout.php" class="user-item logout" id="btnOpenLogout">
          <i class="bi bi-box-arrow-right"></i> Cerrar sesión
        </a>
      </div>
    </div>
  </div>
</header>

<!-- ===== MODAL DE CONFIRMACIÓN (bonito) ===== -->
<div class="modal-mask" id="logoutModal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="logoutTitle">
  <div class="modal-card" role="document">
    <div class="modal-head">
      <div class="badge-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <h2 id="logoutTitle" class="modal-title">¿Cerrar sesión?</h2>
    </div>
    <div class="modal-body">
      <p>Vas a salir del sistema. Si continúas, se cerrará tu sesión actual y volverás al inicio de sesión.</p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn-ghost" id="cancelLogout">Cancelar</button>
      <button type="button" class="btn-danger-soft" id="confirmLogout">Sí, cerrar sesión</button>
    </div>
  </div>
</div>

<script>
(function(){
  // ===== Dropdown usuario =====
  var btn = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if(btn && menu){
    function openM(){ menu.style.display='block'; }
    function closeM(){ menu.style.display='none'; }
    function toggleM(e){ e.preventDefault(); (menu.style.display==='block')?closeM():openM(); }
    btn.onclick = toggleM;
    document.addEventListener('click', function(e){ if(!btn.contains(e.target)) closeM(); }, false);
  }

  // ===== Modal bonito (no confirm nativo) =====
  var modal = document.getElementById('logoutModal');
  var openBtn = document.getElementById('btnOpenLogout');
  var cancelBtn = document.getElementById('cancelLogout');
  var confirmBtn = document.getElementById('confirmLogout');
  var targetHref = 'logout.php';

  function openModal(e){
    if(e) e.preventDefault();
    // leer href destino
    var h = (openBtn && openBtn.getAttribute('data-href')) || 'logout.php';
    targetHref = h;
    modal.classList.add('show');
    modal.setAttribute('aria-hidden','false');
    // focus accesible
    setTimeout(function(){ confirmBtn && confirmBtn.focus(); }, 50);
  }
  function closeModal(){
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden','true');
  }

  if(openBtn){
    openBtn.addEventListener('click', openModal, false);
  }
  if(cancelBtn){
    cancelBtn.addEventListener('click', function(){ closeModal(); }, false);
  }
  if(confirmBtn){
    confirmBtn.addEventListener('click', function(){
      // redirige a logout.php
      window.location.href = targetHref;
    }, false);
  }
  // Cerrar con ESC o clic fuera
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){ closeModal(); }
  });
  modal.addEventListener('click', function(e){
    if(e.target === modal){ closeModal(); }
  });

})();
</script>

<!-- ===== Contenido ===== -->
<div class="container my-4 flex-grow-1">
  <div class="row g-4 justify-content-center">
    <div class="col-md-4">
      <div class="card p-4 text-center menu-card">
        <i class="menu-icon bi bi-people-fill"></i>
        <h4 class="mt-3">Clientes</h4>
        <p>Gestión de clientes.</p>
        <a href="clientes_D.php" class="btn btn-primary">Ingresar</a>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-4 text-center menu-card">
        <i class="menu-icon bi bi-box-seam"></i>
        <h4 class="mt-3">Productos</h4>
        <p>Administrar productos y precios.</p>
        <a href="productos_D.php" class="btn btn-primary">Ingresar</a>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card p-4 text-center menu-card">
        <i class="menu-icon bi bi-truck"></i>
        <h4 class="mt-3">Vehículos</h4>
        <p>Registro de vehículos y conductores.</p>
        <a href="vehiculos_D.php" class="btn btn-primary">Ingresar</a>
      </div>
    </div>
  </div>
</div>

<footer class="py-3 text-center">
  <small>&copy; <?php echo date('Y'); ?> - Sistema de Facturación</small>
</footer>

</body>
</html>
