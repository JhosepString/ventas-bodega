-- ============================================================
-- schema.sql — Bodega de Ventas "La Esquina"
-- Motor: MySQL 8.4 (Laragon)
-- Ejecutar en phpMyAdmin: importar o pegar en pestaña SQL.
-- ============================================================

CREATE DATABASE IF NOT EXISTS bodega_ventas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bodega_ventas;

-- ------------------------------------------------------------ --
-- Tabla: producto                                               --
-- ------------------------------------------------------------ --
DROP TABLE IF EXISTS venta;
DROP TABLE IF EXISTS producto;

CREATE TABLE producto (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    codigo        VARCHAR(50)   NOT NULL,
    nombre        VARCHAR(150)  NOT NULL,
    categoria     VARCHAR(80)   NOT NULL,
    precio_venta  DECIMAL(10,2) NOT NULL,
    stock_actual  INT           NOT NULL DEFAULT 0,
    stock_minimo  INT           NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_producto_codigo (codigo),
    CONSTRAINT chk_producto_precio    CHECK (precio_venta >= 0),
    CONSTRAINT chk_producto_stock_act CHECK (stock_actual >= 0),
    CONSTRAINT chk_producto_stock_min CHECK (stock_minimo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------ --
-- Tabla: venta                                                 --
-- ------------------------------------------------------------ --
CREATE TABLE venta (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    producto_id      INT UNSIGNED  NOT NULL,
    cantidad         INT           NOT NULL,
    precio_unitario  DECIMAL(10,2) NOT NULL,
    total            DECIMAL(10,2) NOT NULL,
    fecha_hora       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_venta_fecha (fecha_hora),
    CONSTRAINT fk_venta_producto
        FOREIGN KEY (producto_id) REFERENCES producto(id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_venta_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_venta_precio   CHECK (precio_unitario >= 0),
    CONSTRAINT chk_venta_total    CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
