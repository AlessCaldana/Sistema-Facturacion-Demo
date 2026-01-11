CREATE TABLE facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_factura VARCHAR(50) NOT NULL UNIQUE,
    nombre_cliente VARCHAR(100) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    estado_pago TINYINT(1) NOT NULL DEFAULT 0
);