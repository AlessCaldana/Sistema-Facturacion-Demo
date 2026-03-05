# Sistema de Facturación - Guía Completa

## 📋 Descripción del Proyecto

Este es un **Sistema de Facturación Web** desarrollado en PHP que permite gestionar de manera integral el proceso de facturación de servicios. Está diseñado para empresas que necesitan controlar ventas, pagos, entregas y reportes de manera eficiente.

### 🎯 Funcionalidades Principales
- ✅ **Generación de Facturas**: Crear facturas con códigos QR y PDF automáticos
- ✅ **Consulta de Facturas**: Buscar y revisar facturas por número o estado
- ✅ **Control de Pagos**: Registrar y rastrear pagos de facturas
- ✅ **Control de Entregas**: Gestionar entregas y marcar como entregadas
- ✅ **Reportes**: Generar reportes de ventas, entregas y estadísticas
- ✅ **Mantenimiento**: Gestionar tablas maestras (clientes, productos, usuarios)
- ✅ **Dashboard**: Panel de control con indicadores clave
- ✅ **Sistema de Usuarios**: Control de acceso por roles y permisos

## 🛠️ Tecnologías Utilizadas

### Backend
- **PHP 5.3+**: Lenguaje principal del servidor
- **MySQL**: Base de datos relacional
- **PDO**: Extensión para conexión segura a base de datos

### Frontend
- **HTML5**: Estructura de páginas
- **CSS3**: Estilos modernos con variables CSS
- **JavaScript**: Interactividad básica
- **Bootstrap Icons**: Iconografía profesional

### Librerías Externas
- **FPDF**: Generación de documentos PDF
- **PHPMailer**: Envío de correos electrónicos
- **PHP QR Code**: Generación de códigos QR
- **PHPExcel**: Manejo de archivos Excel (opcional)

### Entorno de Desarrollo
- **XAMPP**: Servidor local (Apache + MySQL + PHP)
- **Windows**: Sistema operativo compatible

## 📁 Estructura del Proyecto

```
sistema-facturacion-demo/
├── index.php                 # Punto de entrada principal
├── route.php                 # Router de compatibilidad
├── .htaccess                 # Reglas de reescritura URL
├── README_ESTRUCTURA.md      # Esta documentación
│
├── frontend/                 # 👥 Capa de presentación (usuario)
│   ├── login.php            # Página de inicio de sesión
│   ├── logout.php           # Cierre de sesión
│   ├── menu_principal.php   # Menú principal con módulos
│   ├── dashboard.php        # Panel de control
│   ├── generar_factura.php  # Crear nuevas facturas
│   ├── consultar_factura.php # Buscar facturas existentes
│   ├── control_pagos.php    # Gestionar pagos
│   ├── control_entregas.php # Gestionar entregas
│   ├── reportes.php         # Ver reportes
│   ├── mantenimiento_tablas_D.php # Gestionar datos maestros
│   ├── usuarios_mantenimiento.php # Gestionar usuarios
│   └── [otros archivos PHP]
│
├── backend/                  # 🔧 Capa de lógica y configuración
│   ├── conexion.php         # Conexión a base de datos
│   ├── guard.php            # Control de sesiones y permisos
│   ├── permisos.php         # Definición de roles
│   └── demo_config.php      # Configuración del modo demo
│
├── tools/                    # 🛠️ Herramientas de desarrollo
│   ├── barcode.php          # Generador de códigos de barras
│   ├── gd_check.php         # Verificación de GD
│   └── [otros scripts]
│
├── database/                 # 💾 Scripts de base de datos
│   └── [archivos SQL]
│
├── facturas_pdf/             # 📄 PDFs generados
├── qrcodes/                  # 📱 Códigos QR generados
├── img/                      # 🖼️ Imágenes y recursos
├── logs/                     # 📝 Registros del sistema
├── libs/                     # 📚 Librerías externas
│   ├── phpqrcode/           # Generador QR
│   └── [otras]
├── Classes/                  # 📦 Clases adicionales
├── PHPMailer/                # ✉️ Librería de correos
├── fpdf/                     # 📋 Librería PDF
├── security/                 # 🔐 Archivos de seguridad
├── archive/                  # 📦 Archivos antiguos
└── barcode/                  # 📊 Utilidades de códigos
```

## 🚀 Instalación y Configuración

### Prerrequisitos
- **XAMPP** instalado (versión con PHP 5.3+ y MySQL)
- **Navegador web** moderno (Chrome, Firefox, Edge)
- **Conexión a internet** (para Bootstrap Icons)

### Pasos de Instalación

1. **Descargar el proyecto**
   ```bash
   # Copiar la carpeta del proyecto a:
   C:\xampp\htdocs\sistema-facturacion-demo\
   ```

2. **Configurar XAMPP**
   - Abrir XAMPP Control Panel
   - Iniciar **Apache** y **MySQL**

3. **Crear la base de datos**
   ```sql
   -- Crear base de datos en phpMyAdmin
   CREATE DATABASE sistema_facturacion_demo;
   
   -- Importar scripts de database/ si existen
   -- O crear tablas manualmente según el esquema
   ```

4. **Configurar conexión**
   - Editar `backend/conexion.php`
   - Verificar credenciales de MySQL:
     ```php
     $host = 'localhost';
     $db = 'sistema_facturacion_demo';
     $user = 'root';  // Usuario por defecto de XAMPP
     $pass = '';      // Contraseña vacía por defecto
     ```

5. **Configurar modo demo (opcional)**
   - Editar `backend/demo_config.php`
   - Para acceso público sin login:
     ```php
     define('DEMO_PUBLICO', true);
     ```

6. **Acceder al sistema**
   - Abrir navegador
   - Ir a: `http://localhost/sistema-facturacion-demo/`
   - Si está en modo demo, entrará automáticamente
   - Si no, irá a la página de login

## 👤 Sistema de Usuarios y Roles

### Roles Disponibles
- **admin**: Acceso completo a todos los módulos
- **vendedor**: Crear facturas, consultar, reportes
- **despachador**: Control de entregas, consultar facturas
- **consulta**: Solo lectura de facturas y reportes

### Gestión de Usuarios
- Acceder desde el menú: "6. Mantenimiento de Usuarios"
- Crear, editar y eliminar usuarios
- Asignar roles y permisos

### Modo Demo
- Usuario automático: `demo`
- Acceso sin contraseña requerida
- Datos temporales que se borran al cerrar sesión

## 📊 Módulos del Sistema

### 1. Mantenimiento de Tablas
**Ubicación**: `frontend/mantenimiento_tablas_D.php`
- Gestionar **clientes**, **productos**, **vehículos**
- Crear, editar, eliminar registros maestros
- Datos base para crear facturas

### 2. Generar Factura
**Ubicación**: `frontend/generar_factura.php`
- Crear nuevas facturas desde cero
- Seleccionar cliente, productos, cantidades
- Genera PDF automáticamente con código QR
- Código de barras personalizado

### 3. Control de Pagos
**Ubicación**: `frontend/control_pagos.php`
- Buscar facturas por número
- Marcar facturas como **pagadas**
- Actualizar fecha de pago automáticamente
- Ver estado de pagos

### 4. Control de Entregas
**Ubicación**: `frontend/control_entregas.php`
- Buscar facturas pendientes de entrega
- Marcar como **entregadas**
- Registrar fecha y usuario de entrega
- Control de despachos

### 5. Consulta de Facturas
**Ubicación**: `frontend/consultar_factura.php`
- Buscar facturas por número
- Listar por estado (PAGADA, PENDIENTE, ENTREGADA)
- Ver detalles completos con PDF
- Paginación para listas grandes

### 6. Mantenimiento de Usuarios
**Ubicación**: `frontend/usuarios_mantenimiento.php`
- Gestionar usuarios del sistema
- Asignar roles y permisos
- Solo para administradores

### 7. Reportes
**Ubicación**: `frontend/reportes.php`
- Reportes de ventas por período
- Estadísticas de entregas
- Gráficos y tablas resumen

### Dashboard
**Ubicación**: `frontend/dashboard.php`
- Indicadores clave (KPIs)
- Resumen de facturas del día
- Gráficos de rendimiento

## 💾 Estructura de Base de Datos

### Tablas Principales
- **clientes**: Información de clientes (id, nombre, dirección)
- **productos**: Catálogo de productos (id, descripción, precio)
- **facturas**: Cabecera de facturas (id, cliente_id, fecha, total, estado)
- **ventas**: Detalle de ventas (factura_id, producto_id, cantidad)
- **usuarios**: Usuarios del sistema (id, usuario, rol)
- **vehiculos**: Información de vehículos para entregas

### Relaciones
- Una **factura** pertenece a un **cliente**
- Una **factura** tiene múltiples **ventas** (productos)
- Una **venta** pertenece a un **producto**
- Una **factura** puede tener un **vehículo** asignado

## 🔧 Configuración Avanzada

### Variables de Entorno
Editar `backend/demo_config.php` para:
- Activar/desactivar modo demo
- Configurar borrado automático de datos al cerrar sesión
- Definir usuario demo por defecto

### Personalización de PDF
- Logos: Colocar imágenes en `img/` (LogoEMD.jpg, etc.)
- Fuentes: Modificar en `fpdf/`
- Diseño: Editar `generar_factura.php`

### Correos Electrónicos
- Configurar PHPMailer en archivos relevantes
- Usar para notificaciones de facturas

## 🐛 Solución de Problemas

### Error de conexión a BD
- Verificar que MySQL esté ejecutándose en XAMPP
- Revisar credenciales en `backend/conexion.php`
- Crear base de datos si no existe

### PDFs no se generan
- Verificar permisos de escritura en `facturas_pdf/`
- Instalar extensión GD en PHP si falta
- Revisar logs en `logs/error_log`

### Problemas de permisos
- Verificar rol del usuario en sesión
- Revisar `backend/guard.php` para reglas de acceso
- Usar cuenta admin para acceso completo

### Modo demo no funciona
- Verificar `DEMO_PUBLICO = true` en `demo_config.php`
- Revisar configuración de usuario demo

## 📞 Soporte y Contacto

Para soporte técnico o reportar bugs:
- Revisar logs en `logs/error_log`
- Verificar configuración en archivos de `backend/`
- Asegurar compatibilidad con PHP 5.3+

## 📝 Notas de Versión

### Versión Actual
- **Framework**: PHP nativo sin frameworks
- **UI**: CSS moderno con variables
- **BD**: MySQL con PDO
- **Compatible**: PHP 5.3+, MySQL 5.5+

### Características Destacadas
- ✅ Interfaz responsive
- ✅ Generación automática de PDFs
- ✅ Códigos QR integrados
- ✅ Sistema de roles y permisos
- ✅ Modo demo para pruebas
- ✅ Limpieza automática de datos demo

---

**¡Gracias por usar el Sistema de Facturación!** 🎉

Este sistema está diseñado para ser intuitivo y eficiente. Si sigues esta guía, podrás instalarlo y usarlo sin problemas.
- Demo publica: `backend/demo_config.php`
- Seguridad/permisos: `backend/guard.php`, `backend/permisos.php`
- Logs: `logs/error_log`

## 10. Problemas comunes
- No carga una pagina:
  - Verificar Apache activo.
  - Verificar ruta del proyecto en `htdocs`.
  - Verificar `.htaccess` habilitado (`mod_rewrite`).
- Error de base de datos:
  - Revisar credenciales en `backend/conexion.php`.
  - Confirmar que la BD exista y tenga tablas.
- Error 403/acceso denegado:
  - Revisar rol de usuario y reglas en `backend/guard.php`.
- En demo no entra automatico:
  - Verificar `DEMO_PUBLICO` en `backend/demo_config.php`.

## 11. Recomendacion de uso para usuarios finales
- Para operar el sistema, siempre entrar por:
  - `http://localhost/sistema-facturacion-demo/`
- No editar archivos dentro de `backend/`, `tools/` o `security/` sin soporte tecnico.

- ## 👨‍💻 Desarrollador
**Alessandro Caldana**
- 💼 [LinkedIn]([https://linkedin.com/in/tu-perfil](https://www.linkedin.com/in/alessandro-caldana-3978b11b5/))
- 📧 alessandroca73@gmail.com
- 🐙 +57 3156166433
