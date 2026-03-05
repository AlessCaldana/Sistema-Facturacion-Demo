-- Sistema de Facturación - Script de Creación de Base de Datos
-- Ejecutar en phpMyAdmin o MySQL Workbench
-- Base de datos: sistema_facturacion_demo

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS sistema_facturacion_demo
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Usar la base de datos
USE sistema_facturacion_demo;

-- ===========================================
-- TABLA: clientes
-- ===========================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    direccion VARCHAR(500),
    telefono VARCHAR(20),
    email VARCHAR(100),
    documento VARCHAR(20) UNIQUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_documento (documento),
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- TABLA: productos
-- ===========================================
CREATE TABLE IF NOT EXISTS productos (
    PRODCODI INT AUTO_INCREMENT PRIMARY KEY,
    PRODDESC VARCHAR(255) NOT NULL,
    precio DECIMAL(10,2) DEFAULT 0.00,
    stock INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_descripcion (PRODDESC),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- TABLA: usuarios
-- ===========================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100),
    rol ENUM('admin', 'vendedor', 'despachador', 'consulta') DEFAULT 'consulta',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL,
    INDEX idx_usuario (usuario),
    INDEX idx_rol (rol),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- TABLA: vehiculos
-- ===========================================
CREATE TABLE IF NOT EXISTS vehiculos (
    placa VARCHAR(20) PRIMARY KEY,
    cedula_conductor VARCHAR(20),
    nombre_conductor VARCHAR(100),
    modelo VARCHAR(50),
    marca VARCHAR(50),
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conductor (cedula_conductor),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- TABLA: facturas
-- ===========================================
CREATE TABLE IF NOT EXISTS facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estado ENUM('PENDIENTE', 'PAGADA', 'ENTREGADA') DEFAULT 'PENDIENTE',
    fecha_entrega TIMESTAMP NULL,
    fecha_pago TIMESTAMP NULL,
    placa VARCHAR(20),
    referencia VARCHAR(50),
    usuario_entrega VARCHAR(50),
    usuario_nombre VARCHAR(100),
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (placa) REFERENCES vehiculos(placa) ON DELETE SET NULL,
    INDEX idx_cliente (cliente_id),
    INDEX idx_fecha (fecha),
    INDEX idx_estado (estado),
    INDEX idx_placa (placa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- TABLA: ventas (detalle de facturas)
-- ===========================================
CREATE TABLE IF NOT EXISTS ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    producto_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(PRODCODI) ON DELETE CASCADE,
    INDEX idx_factura (factura_id),
    INDEX idx_producto (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================
-- DATOS INICIALES
-- ===========================================

-- Usuario administrador por defecto
INSERT INTO usuarios (usuario, password, nombre, rol) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin')
ON DUPLICATE KEY UPDATE usuario=usuario;

-- Usuario demo
INSERT INTO usuarios (usuario, password, nombre, rol) VALUES
('demo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Usuario Demo', 'admin')
ON DUPLICATE KEY UPDATE usuario=usuario;

-- Clientes de ejemplo
INSERT INTO clientes (nombre, documento, direccion, telefono) VALUES
('Cliente Ejemplo 1', '12345678', 'Calle 123 #45-67', '3001234567'),
('Cliente Ejemplo 2', '87654321', 'Carrera 89 #12-34', '3019876543')
ON DUPLICATE KEY UPDATE nombre=nombre;

-- Productos de ejemplo
INSERT INTO productos (PRODDESC, precio, stock) VALUES
('Producto A', 15000.00, 100),
('Producto B', 25000.00, 50),
('Producto C', 35000.00, 25)
ON DUPLICATE KEY UPDATE PRODDESC=PRODDESC;

-- Vehículos de ejemplo
INSERT INTO vehiculos (placa, cedula_conductor, nombre_conductor) VALUES
('ABC123', '11223344', 'Juan Pérez'),
('DEF456', '55667788', 'María García')
ON DUPLICATE KEY UPDATE placa=placa;

-- ===========================================
-- PERMISOS Y OPTIMIZACIONES
-- ===========================================

-- Otorgar permisos (ejecutar como root si es necesario)
-- GRANT ALL PRIVILEGES ON sistema_facturacion_demo.* TO 'usuario'@'localhost';

-- Optimizar tablas
OPTIMIZE TABLE clientes, productos, usuarios, vehiculos, facturas, ventas;

COMMIT;