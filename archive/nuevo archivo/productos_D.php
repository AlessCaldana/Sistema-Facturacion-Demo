<?php
/* productos_D.php — PHP 5.3 compatible */
session_start();
require 'conexion.php'; // Debe proveer $pdo (PDO)

if (!ini_get('date.timezone')) { date_default_timezone_set('America/Bogota'); }
$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';

/* ── Normalización defensiva ── */
try {
    $pdo->exec("UPDATE productos SET PRODCANT = 0 WHERE PRODCANT IS NULL OR PRODCANT < 0");
} catch (Exception $e) {}

/* Catálogo permitido */
$permitidos = array(
    'AGUA CRUDA',
    'AGUA TRATADA',
    'SUPERVISION INSTALACION DE MEDIDORES'
);

/* Helper para normalizar texto */
if (!function_exists('norm')) {
    function norm($s){
        $s = trim((string)$s);
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($s, 'UTF-8');
        }
        return strtoupper($s);
    }
}

/* ===== Consulta (solo productos permitidos) ===== */
$vals = array();
foreach ($permitidos as $p){ $vals[] = norm($p); }

$ph = implode(',', array_fill(0, count($vals), '?'));

$sql = "
  SELECT PRODCODI, PRODDESC, IFNULL(PRODCANT,0) AS PRODCANT
    FROM productos
   WHERE TRIM(UPPER(PRODDESC)) IN ($ph)
   ORDER BY PRODDESC, PRODCODI
";
$st = $pdo->prepare($sql);
$st->execute($vals);
$productos = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gestión de Productos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{ --azul:#1f6691; --texto:#333; --txt:#0b3850; --danger:#d9534f; --danger-2:#b52b27; }
body{ background:#fff; color:var(--texto); min-height:100vh; display:flex; flex-direction:column; }

header{
  background:var(--azul); color:#fff; padding:12px 24px;
  display:flex; align-items:center; justify-content:space-between; position:relative;
}
header h2{ margin:0; text-align:center; flex-grow:1; }

.user-btn{ cursor:pointer; display:flex; align-items:center; gap:6px; padding:6px 10px; border-radius:10px; }
.user-menu{
  display:none; position:absolute; right:24px; top:58px; background:#fff; color:var(--txt);
  border:1px solid #e5e7eb; border-radius:12px; padding:6px; min-width:190px;
  box-shadow:0 12px 28px rgba(0,0,0,.20); z-index:9999;
}
.user-item{ display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:8px;
  font-weight:600; text-decoration:none; color:var(--danger); }
.user-item:hover{ background:#ffe5e5; color:var(--danger-2); }

.card-glass{ background:#fff; border:1px solid #e9f1f8; border-radius:14px; box-shadow:0 8px 18px rgba(0,0,0,.06); }
table thead{ background:#e8f2fb; }

footer{ background:var(--azul); color:#fff; text-align:center; padding:12px; margin-top:auto; }

.modal-header.grad{ background:linear-gradient(135deg,#ffefef,#ffe5e5); border-bottom:1px solid #f3c7c7; }
.modal-footer.soft{ background:#fafbfd; border-top:1px solid #eef1f5; }
</style>
</head>
<body>

<header>
  <a href="mantenimiento_tablas_D.php" class="btn btn-light">
    <i class="bi bi-arrow-left-circle"></i> Volver
  </a>

  <h2>Gestión de Productos</h2>

  <div class="user-btn" id="userBtn">
    <i class="bi bi-person-fill"></i> <?php echo htmlspecialchars($usuario); ?>
    <div class="user-menu" id="userMenu">
      <a href="#" id="openLogout" class="user-item">
        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
      </a>
    </div>
  </div>
</header>

<div class="container my-4">

  <div class="card card-glass p-3">
    <h4 class="mb-3">Lista de Productos</h4>
    <div class="table-responsive">
      <table class="table table-bordered table-striped mb-0 align-middle">
        <thead>
          <tr>
            <th>Producto</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($productos)): foreach ($productos as $p): ?>
            <tr>
              <td><?php echo htmlspecialchars(norm($p['PRODDESC'])); ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td class="text-center text-muted">Sin registros.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>  
    </div>
  </div>

</div>

<footer class="mt-auto text-center py-3">
  <small>&copy; 2025 - Sistema de Facturación</small>
</footer>

<!-- Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header grad">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger"></i> Confirmar cierre de sesión</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">¿Seguro que deseas cerrar tu sesión?</div>
      <div class="modal-footer soft">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right"></i> Sí, salir</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  var btn = document.getElementById('userBtn');
  var menu = document.getElementById('userMenu');
  if(btn && menu){
    btn.onclick = function(e){
      e = e || window.event; if(e.stopPropagation) e.stopPropagation();
      menu.style.display = (menu.style.display==='block' ? 'none' : 'block');
    };
    document.addEventListener('click', function(ev){
      if(!btn.contains(ev.target)) menu.style.display='none';
    }, false);
  }

  var openLogout = document.getElementById('openLogout');
  if(openLogout){
    openLogout.onclick = function(e){
      e.preventDefault();
      var m = new bootstrap.Modal(document.getElementById('logoutModal'));
      m.show();
    };
  }
})();
</script>
</body>
</html>
