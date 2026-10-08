CREATE TABLE users (
 id INT PRIMARY KEY AUTO_INCREMENT, nombre VARCHAR(255) NOT NULL, apellido VARCHAR(255) NULL,
 correo VARCHAR(255) NOT NULL, telefono VARCHAR(20) NOT NULL, username VARCHAR(255) NOT NULL,
 password CHAR(60) NOT NULL, confirmado ENUM('0','1') DEFAULT '0', token CHAR(64) NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
 rol ENUM('user','admin') NOT NULL DEFAULT 'user', UNIQUE KEY users_correo_unique(correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE tipo_cuenta (
 id INT PRIMARY KEY AUTO_INCREMENT, tipo VARCHAR(255) NOT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE tipo_movimientos (
 id INT PRIMARY KEY AUTO_INCREMENT, tipo VARCHAR(255) NOT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE categorias (
 id INT PRIMARY KEY AUTO_INCREMENT, categoria VARCHAR(255) NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE cuentas (
 id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, tipo_cuenta_id INT NOT NULL,
 nombre VARCHAR(255) NOT NULL, balance DECIMAL(12,2) NOT NULL DEFAULT 0.00, color VARCHAR(6) NOT NULL,
 limite_credito DECIMAL(12,2) NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE movimientos (
 id INT PRIMARY KEY AUTO_INCREMENT, cuenta_id INT NULL, cuenta_destino_id INT NULL, user_id INT NOT NULL,
 categoria_id INT NOT NULL, tipo_movimineto_id INT NOT NULL, monto DECIMAL(12,2) NOT NULL,
 descripcion TEXT NULL, fecha DATE NULL, cuenta_nombre_historico VARCHAR(255) NULL,
 destino_nombre_historico VARCHAR(255) NULL, version INT NOT NULL DEFAULT 1,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT movimientos_cuenta_id_nullable_fk FOREIGN KEY(cuenta_id) REFERENCES cuentas(id) ON DELETE SET NULL,
 CONSTRAINT movimientos_cuenta_destino_id_nullable_fk FOREIGN KEY(cuenta_destino_id) REFERENCES cuentas(id) ON DELETE SET NULL,
 CONSTRAINT movimientos_owner_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(categoria_id) REFERENCES categorias(id), FOREIGN KEY(tipo_movimineto_id) REFERENCES tipo_movimientos(id),
 INDEX movimientos_owner_idx(user_id,fecha,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO tipo_cuenta(tipo) VALUES ('Efectivo'),('Tarjeta de Debito'),('Tarjeta de Credito'),('Inversion'),('Bancaria');
INSERT INTO tipo_movimientos(tipo) VALUES ('Ingreso'),('Gasto'),('Transferencia');
INSERT INTO categorias(categoria) VALUES ('General');
