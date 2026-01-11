<?php
// mapa_sistema.php — Mapa conceptual del sistema (compatible PHP 5.3)
session_start();
require_once __DIR__ . '/guard.php';
require_perm('dashboard', 'read'); // Cambia el permiso si usas otro

$usuario_actual = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Invitado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Mapa del Sistema · Facturación Carro Tanque</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{
    --azul-oscuro:#1f6691;
    --azul:#2980b9;
    --verde:#16a34a;
    --naranja:#f97316;
    --morado:#8b5cf6;
    --rojo:#ef4444;
    --gris:#64748b;
    --bg:#f3f6fb;
  }
  body{
    background:var(--bg);
    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
  }
  header{
    background:var(--azul-oscuro);
    color:#fff;
    padding:10px 20px;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
  }
  header h1{
    font-size:1.4rem;
    margin:0;
  }
  .back-btn{
    position:absolute;
    left:20px;
  }
  .user-pill{
    position:absolute;
    right:20px;
    background:rgba(15,23,42,.85);
    color:#e5e7eb;
    border-radius:999px;
    padding:4px 12px;
    font-size:.85rem;
    display:flex;
    align-items:center;
    gap:6px;
  }

  .map-container{
    max-width:1200px;
    margin:20px auto 30px auto;
    padding:16px;
  }

  .node-center{
    background:var(--azul);
    color:#fff;
    padding:14px 20px;
    border-radius:14px;
    text-align:center;
    font-weight:700;
    box-shadow:0 10px 25px rgba(15,23,42,.25);
    margin:0 auto 24px auto;
    max-width:420px;
  }
  .node-center small{font-weight:400;opacity:.9;}

  .map-grid{
    display:flex;
    flex-wrap:wrap;
    gap:18px;
    justify-content:space-between;
  }

  .map-column{
    flex:1 1 280px;
    min-width:260px;
  }

  .col-label{
    text-align:center;
    font-size:.78rem;
    text-transform:uppercase;
    letter-spacing:.14em;
    color:rgba(148,163,184,.8);
    margin-bottom:4px;
  }

  .node-title{
    padding:8px 12px;
    border-radius:10px;
    font-weight:700;
    color:#fff;
    margin-bottom:10px;
    text-align:center;
  }
  .node-title.azul{background:var(--azul-oscuro);}
  .node-title.verde{background:var(--verde);}
  .node-title.naranja{background:var(--naranja);}
  .node-title.morado{background:var(--morado);}
  .node-title.rojo{background:var(--rojo);}

  .node-box{
    background:#ffffff;
    border-radius:10px;
    padding:10px 10px 6px 10px;
    box-shadow:0 4px 10px rgba(15,23,42,.10);
    margin-bottom:10px;
  }
  .node-box-title{
    font-weight:600;
    font-size:.9rem;
    margin-bottom:4px;
  }
  .node-pill{
    display:inline-block;
    padding:3px 8px;
    border-radius:999px;
    font-size:.78rem;
    margin:2px 2px;
    text-decoration:none;
  }
  .node-pill.modulo{
    background:#e0f2fe;
    color:#0f172a;
  }
  .node-pill.tabla{
    background:#dcfce7;
    color:#14532d;
  }
  .node-pill.archivo{
    background:#fee2e2;
    color:#7f1d1d;
  }
  .node-pill.relacion{
    background:#fef3c7;
    color:#92400e;
  }
  .node-pill.paso{
    background:#e5e7eb;
    color:#111827;
    font-weight:600;
  }

  .hint{
    font-size:.8rem;
    color:var(--gris);
    margin-top:6px;
  }

  /* ===== Sección de flujo ===== */
  .flow-section{
    margin-top:28px;
    margin-bottom:26px;
  }
  .flow-title{
    font-weight:700;
    font-size:1.05rem;
    margin-bottom:10px;
    color:#0f172a;
  }
  .flow-sub{
    font-size:.85rem;
    color:var(--gris);
    margin-bottom:10px;
  }
  .flow-steps{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:stretch;
  }
  .flow-card{
    flex:1 1 180px;
    min-width:180px;
    background:#ffffff;
    border-radius:12px;
    padding:10px 10px 8px 10px;
    box-shadow:0 4px 10px rgba(15,23,42,.12);
    position:relative;
  }
  .flow-step{
    display:inline-block;
    padding:2px 8px;
    border-radius:999px;
    font-size:.75rem;
    background:#eff6ff;
    color:#1d4ed8;
    margin-bottom:4px;
    font-weight:600;
  }
  .flow-arrows{
    text-align:center;
    font-size:1.4rem;
    color:#9ca3af;
    align-self:center;
  }
  .flow-card-title{
    font-weight:600;
    font-size:.9rem;
    margin-bottom:2px;
  }
  .flow-card-desc{
    font-size:.8rem;
    color:#4b5563;
    margin-bottom:4px;
  }
  .flow-card-mini{
    font-size:.76rem;
    color:#6b7280;
  }

  /* ===== Matriz módulo-tabla ===== */
  .matrix-section{
    margin-top:10px;
  }
  .matrix-table{
    font-size:.82rem;
  }
  .matrix-table th{
    background:#e5e7eb;
    color:#111827;
    font-weight:600;
  }
  .badge-read{
    background:#dbeafe;
    color:#1d4ed8;
  }
  .badge-write{
    background:#dcfce7;
    color:#15803d;
  }

  @media (max-width:768px){
    .map-grid{
      flex-direction:column;
    }
    header h1{font-size:1.1rem;}
    .flow-arrows{display:none;}
  }
</style>
</head>
<body>
<header>
  <a href="menu_principal.php" class="btn btn-light btn-sm back-btn">
    ← Volver
  </a>
  <h1>Mapa del Sistema · Facturación Carro Tanque</h1>
  <div class="user-pill">
    <span>👤 <?php echo htmlspecialchars($usuario_actual); ?></span>
  </div>
</header>

<div class="map-container">

  <!-- Nodo central -->
  <div class="node-center">
    Sistema de Facturación · Carro Tanque<br>
    <small>FacturaCT · EMDUPAR</small>
  </div>

  <!-- ===== 1. Mapa alto nivel (como ya lo viste) ===== -->
  <div class="map-grid">

    <!-- Columna 1: Aplicación PHP -->
    <div class="map-column">
      <div class="col-label">Aplicación PHP</div>
      <div class="node-title azul">Módulos principales</div>

      <div class="node-box">
        <div class="node-box-title">Autenticación y seguridad</div>
        <span class="node-pill modulo">login.php</span>
        <span class="node-pill modulo">logout.php</span>
        <span class="node-pill modulo">guard.php</span>
        <div class="hint">Valida usuario, rol y permisos. Protección de todo el sistema.</div>
      </div>

      <div class="node-box">
        <div class="node-box-title">Navegación principal</div>
        <span class="node-pill modulo">menu_principal.php</span>
        <span class="node-pill modulo">dashboard.php</span>
        <div class="hint">Muestra indicadores y enlaces a facturación, reportes y mantenimientos.</div>
      </div>

      <div class="node-box">
        <div class="node-box-title">Facturación</div>
        <span class="node-pill modulo">generar_factura.php</span>
        <span class="node-pill modulo">consultar_factura.php</span>
        <span class="node-pill modulo">control_pagos.php</span>
        <div class="hint">
          Creación de ventas/facturas, generación de PDF y QR, registro y consulta de pagos.
        </div>
      </div>

      <div class="node-box">
        <div class="node-box-title">Mantenimientos</div>
        <span class="node-pill modulo">usuarios_mantenimiento.php</span>
        <span class="node-pill modulo">clientes_D.php</span>
        <span class="node-pill modulo">vehiculos_D.php</span>
        <span class="node-pill modulo">productos_D.php</span>
        <div class="hint">ABM de usuarios, clientes, vehículos y productos.</div>
      </div>

      <div class="node-box">
        <div class="node-box-title">Reportes</div>
        <span class="node-pill modulo">reportes.php</span>
        <span class="node-pill modulo">rep_ventas.php</span>
        <span class="node-pill modulo">rep_entregas.php</span>
        <div class="hint">Consultas de información histórica para control y análisis.</div>
      </div>
    </div>

    <!-- Columna 2: Base de datos -->
    <div class="map-column">
      <div class="col-label">Base de datos</div>
      <div class="node-title verde">emdupargov_carrotanque</div>

      <div class="node-box">
        <div class="node-box-title">Seguridad y permisos</div>
        <span class="node-pill tabla">usuarios</span>
        <span class="node-pill tabla">roles</span>
        <span class="node-pill tabla">permisos</span>
        <span class="node-pill tabla">permisos_usuario</span>
        <div class="hint">Define quién entra al sistema y qué acciones puede realizar.</div>
      </div>

      <div class="node-box">
        <div class="node-box-title">Catálogos</div>
        <span class="node-pill tabla">clientes</span>
        <span class="node-pill tabla">vehiculos</span>
        <span class="node-pill tabla">productos</span>
        <span class="node-pill tabla">bancos</span>
        <div class="hint">Información base reutilizada en cada factura o venta.</div>
      </div>

      <div class="node-box">
        <div class="node-box-title">Operaciones</div>
        <span class="node-pill tabla">ventas</span>
        <span class="node-pill tabla">facturas</span>
        <span class="node-pill tabla">pagos</span>
        <span class="node-pill tabla">kardex</span>
        <div class="hint">Registra el servicio prestado, su facturación, pagos y movimiento de inventario.</div>
      </div>

      <div class="node-box">
        <div class="node-box-title">Relaciones clave</div>
        <span class="node-pill relacion">ventas.VENTCLIE → clientes.CLIEDOCU</span><br>
        <span class="node-pill relacion">ventas.VENTPROD → productos.PRODCODI</span><br>
        <span class="node-pill relacion">ventas.VENTPLAC → vehiculos.VEHIPLAC</span><br>
        <span class="node-pill relacion">facturas.factreci → ventas.VENTRECI</span><br>
        <span class="node-pill relacion">pagos.PAGORECI → ventas.VENTRECI</span>
        <div class="hint">Conecta al cliente, el vehículo, el producto y el pago en torno a la factura.</div>
      </div>
    </div>

    <!-- Columna 3: Archivos generados / Integraciones -->
    <div class="map-column">
      <div class="col-label">Archivos & librerías</div>
      <div class="node-title naranja">Archivos e integraciones</div>

      <div class="node-box">
        <div class="node-box-title">Archivos generados</div>
        <span class="node-pill archivo">facturas_pdf/factura_X.pdf</span>
        <span class="node-pill archivo">qrcodes/qr_fact_X.png</span>
        <span class="node-pill archivo">tmp_bar/*.png</span>
        <div class="hint">Salidas de FPDF, QRCode y Barcode para impresión y consulta.</div>
      </div>

      <div class="node-box">
        <div class="node-box-title">Librerías</div>
        <span class="node-pill archivo">fpdf/</span>
        <span class="node-pill archivo">libs/phpqrcode/</span>
        <span class="node-pill archivo">barcode/</span>
        <span class="node-pill archivo">PHPMailer/</span>
        <div class="hint">Módulos externos para PDF, códigos QR, código de barras y correo.</div>
      </div>

      <div class="node-box">
        <div class="node-box-title">Conexión y configuración</div>
        <span class="node-pill archivo">conexion.php</span>
        <span class="node-pill archivo">config_recaudo.php</span>
        <div class="hint">Datos de conexión a MySQL y parámetros de negocio del sistema.</div>
      </div>

      <div class="node-box">
        <div class="node-box-title">Ideas futuras</div>
        <span class="node-pill">API REST (emdupar_api)</span>
        <span class="node-pill">Panel de recaudo en línea</span>
        <span class="node-pill">Histórico de envíos a conductores</span>
        <div class="hint">Zona reservada para crecimiento del sistema.</div>
      </div>
    </div>
  </div>

  <!-- ===== 2. Flujo principal de facturación ===== -->
  <div class="flow-section">
    <div class="flow-title">Flujo principal de facturación</div>
    <div class="flow-sub">
      De forma sencilla, así viaja la información desde que el usuario entra al sistema hasta que se genera la factura y el PDF.
    </div>
    <div class="flow-steps">

      <div class="flow-card">
        <div class="flow-step">Paso 1</div>
        <div class="flow-card-title">Inicio de sesión</div>
        <div class="flow-card-desc">El usuario se identifica con documento y contraseña.</div>
        <div class="flow-card-mini">
          Módulos: <span class="node-pill modulo">login.php</span><br>
          Tablas: <span class="node-pill tabla">usuarios</span>,
          <span class="node-pill tabla">permisos_usuario</span>
        </div>
      </div>

      <div class="flow-arrows">⟶</div>

      <div class="flow-card">
        <div class="flow-step">Paso 2</div>
        <div class="flow-card-title">Ingreso al menú</div>
        <div class="flow-card-desc">Se muestra el panel principal con accesos a facturación y reportes.</div>
        <div class="flow-card-mini">
          Módulos: <span class="node-pill modulo">menu_principal.php</span><br>
          Tablas (lectura): <span class="node-pill tabla">facturas</span>,
          <span class="node-pill tabla">ventas</span>
        </div>
      </div>

      <div class="flow-arrows">⟶</div>

      <div class="flow-card">
        <div class="flow-step">Paso 3</div>
        <div class="flow-card-title">Selección de datos</div>
        <div class="flow-card-desc">Se elige cliente, vehículo, producto y cantidad en el formulario.</div>
        <div class="flow-card-mini">
          Módulo: <span class="node-pill modulo">generar_factura.php</span><br>
          Tablas (lectura): <span class="node-pill tabla">clientes</span>,
          <span class="node-pill tabla">vehiculos</span>,
          <span class="node-pill tabla">productos</span>
        </div>
      </div>

      <div class="flow-arrows">⟶</div>

      <div class="flow-card">
        <div class="flow-step">Paso 4</div>
        <div class="flow-card-title">Registro de venta y factura</div>
        <div class="flow-card-desc">Se guarda la operación y se calcula el valor total.</div>
        <div class="flow-card-mini">
          Módulo: <span class="node-pill modulo">generar_factura.php</span><br>
          Tablas (escritura):
          <span class="node-pill tabla">ventas</span>,
          <span class="node-pill tabla">facturas</span>,
          <span class="node-pill tabla">kardex</span> (según configuración)
        </div>
      </div>

      <div class="flow-arrows">⟶</div>

      <div class="flow-card">
        <div class="flow-step">Paso 5</div>
        <div class="flow-card-title">Generación de PDF y QR</div>
        <div class="flow-card-desc">Se crea el archivo PDF y el código QR con el enlace de consulta.</div>
        <div class="flow-card-mini">
          Módulo: <span class="node-pill modulo">generar_factura.php</span><br>
          Archivos:
          <span class="node-pill archivo">facturas_pdf/factura_X.pdf</span>,
          <span class="node-pill archivo">qrcodes/qr_fact_X.png</span>
        </div>
      </div>

      <div class="flow-arrows">⟶</div>

      <div class="flow-card">
        <div class="flow-step">Paso 6</div>
        <div class="flow-card-title">Consulta y pago</div>
        <div class="flow-card-desc">El talón/QR permite consultar la factura y registrar el pago.</div>
        <div class="flow-card-mini">
          Módulos:
          <span class="node-pill modulo">consultar_factura.php</span>,
          <span class="node-pill modulo">control_pagos.php</span><br>
          Tablas:
          <span class="node-pill tabla">facturas</span>,
          <span class="node-pill tabla">pagos</span>
        </div>
      </div>

    </div>
  </div>

  <!-- ===== 3. Matriz módulo ↔ tablas ===== -->
  <div class="matrix-section">
    <div class="flow-title">Conexiones módulo ↔ base de datos</div>
    <div class="flow-sub">
      Esta tabla resume qué módulos leen o escriben cada tabla principal. Ideal para que alguien nuevo entienda
      dónde se toca cada dato.
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-bordered matrix-table align-middle">
        <thead>
          <tr>
            <th>Módulo</th>
            <th>Tablas que usa</th>
            <th style="width:130px;">Tipo de acceso</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><code>login.php</code></td>
            <td>usuarios, permisos_usuario</td>
            <td><span class="badge badge-read">Lectura</span></td>
          </tr>
          <tr>
            <td><code>usuarios_mantenimiento.php</code></td>
            <td>usuarios, roles, permisos_usuario</td>
            <td>
              <span class="badge badge-read">Lectura</span>
              <span class="badge badge-write">Escritura</span>
            </td>
          </tr>
          <tr>
            <td><code>clientes_D.php</code></td>
            <td>clientes</td>
            <td>
              <span class="badge badge-read">Lectura</span>
              <span class="badge badge-write">Escritura</span>
            </td>
          </tr>
          <tr>
            <td><code>vehiculos_D.php</code></td>
            <td>vehiculos</td>
            <td>
              <span class="badge badge-read">Lectura</span>
              <span class="badge badge-write">Escritura</span>
            </td>
          </tr>
          <tr>
            <td><code>productos_D.php</code></td>
            <td>productos</td>
            <td>
              <span class="badge badge-read">Lectura</span>
              <span class="badge badge-write">Escritura</span>
            </td>
          </tr>
          <tr>
            <td><code>generar_factura.php</code></td>
            <td>clientes, vehiculos, productos, ventas, facturas, kardex</td>
            <td>
              <span class="badge badge-read">Lectura</span>
              <span class="badge badge-write">Escritura</span>
            </td>
          </tr>
          <tr>
            <td><code>consultar_factura.php</code></td>
            <td>facturas, ventas, clientes, productos, vehiculos</td>
            <td><span class="badge badge-read">Lectura</span></td>
          </tr>
          <tr>
            <td><code>control_pagos.php</code></td>
            <td>pagos, facturas, ventas, bancos</td>
            <td>
              <span class="badge badge-read">Lectura</span>
              <span class="badge badge-write">Escritura</span>
            </td>
          </tr>
          <tr>
            <td><code>reportes.php / rep_ventas.php</code></td>
            <td>facturas, ventas, clientes, productos, vehiculos</td>
            <td><span class="badge badge-read">Lectura</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<footer class="text-center py-2" style="background:#0f172a;color:#e5e7eb;font-size:.8rem;">
  © 2025 · Sistema de Facturación Carro Tanque · Mapa conceptual
</footer>
</body>
</html>
