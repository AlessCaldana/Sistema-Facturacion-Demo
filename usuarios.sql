CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL
);

-- Usuario: admin / Clave: admin123
INSERT INTO usuarios (usuario, clave) VALUES (
    'admin',
    '$2y$10$QHkHcvN0EWI7eNOwG2G52e9Z2A0qMH4TPO8e4k9mjMxHrsx9lM4Wa'
);