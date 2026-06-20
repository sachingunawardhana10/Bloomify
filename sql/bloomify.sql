-- ============================================================
--  BLOOMIFY — Complete Database Setup
--  HOW TO RUN:
--    1. Open phpMyAdmin → Click "SQL" tab at the top
--    2. Paste this entire file → Click "Go"
--    OR in terminal: mysql -u root -p < bloomify.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS bloomify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bloomify;

-- ─── USERS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    role       ENUM('customer','admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── FLOWERS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS flowers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    emoji      VARCHAR(10)   NOT NULL DEFAULT '🌸',
    price      DECIMAL(10,2) NOT NULL,
    meaning    VARCHAR(200)  NOT NULL,
    tag        VARCHAR(50)   DEFAULT NULL,
    stock      INT           DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── FLOWER VARIETIES ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS flower_varieties (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    flower_id    INT NOT NULL,
    variety_name VARCHAR(100) NOT NULL,
    color_hex    VARCHAR(7)   NOT NULL,
    price        DECIMAL(10,2) NOT NULL,
    stock        INT NOT NULL DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_flower_variety (flower_id, variety_name),
    FOREIGN KEY (flower_id) REFERENCES flowers(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ─── CART ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cart (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    flower_id  INT NOT NULL,
    variety_id INT NOT NULL,
    quantity   INT DEFAULT 1,
    UNIQUE KEY unique_cart (user_id, flower_id, variety_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (flower_id)  REFERENCES flowers(id) ON DELETE CASCADE,
    FOREIGN KEY (variety_id) REFERENCES flower_varieties(id) ON DELETE CASCADE
);

-- ─── ORDERS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    total      DECIMAL(10,2) NOT NULL,
    status     ENUM('pending','processing','delivered','cancelled') DEFAULT 'pending',
    notes      TEXT DEFAULT NULL,
    recipient_name VARCHAR(120) DEFAULT NULL,
    recipient_phone VARCHAR(30) DEFAULT NULL,
    delivery_address TEXT DEFAULT NULL,
    delivery_date DATE DEFAULT NULL,
    delivery_time VARCHAR(120) DEFAULT NULL,
    payment_method VARCHAR(40) NOT NULL DEFAULT 'Cash on Delivery',
    payment_status VARCHAR(40) NOT NULL DEFAULT 'Unpaid',
    payment_reference VARCHAR(100) DEFAULT NULL,
    cod_collected_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS recipient_name VARCHAR(120) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS recipient_phone VARCHAR(30) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS delivery_address TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS delivery_date DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS delivery_time VARCHAR(120) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(40) NOT NULL DEFAULT 'Cash on Delivery',
    ADD COLUMN IF NOT EXISTS payment_status VARCHAR(40) NOT NULL DEFAULT 'Unpaid',
    ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS cod_collected_at TIMESTAMP NULL DEFAULT NULL;

-- ─── ORDER ITEMS ──────────────────────────────────────────
-- CONTACT MESSAGES
CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    subject    VARCHAR(150) NOT NULL,
    message    TEXT NOT NULL,
    status     ENUM('new','read','archived') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS payments (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    order_id           INT NOT NULL,
    payment_gateway    VARCHAR(80) NOT NULL,
    gateway_payment_id VARCHAR(100) NOT NULL,
    amount             DECIMAL(10,2) NOT NULL,
    currency           VARCHAR(10) NOT NULL,
    method             VARCHAR(40) DEFAULT NULL,
    status_code        VARCHAR(20) DEFAULT NULL,
    status_message     VARCHAR(255) DEFAULT NULL,
    md5sig             VARCHAR(255) DEFAULT NULL,
    raw_payload        JSON NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_gateway_payment (gateway_payment_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    order_id   INT NOT NULL,
    flower_id  INT NOT NULL,
    variety_id INT NOT NULL,
    quantity   INT NOT NULL,
    price      DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)    REFERENCES orders(id)  ON DELETE CASCADE,
    FOREIGN KEY (flower_id)   REFERENCES flowers(id) ON DELETE CASCADE,
    FOREIGN KEY (variety_id)  REFERENCES flower_varieties(id) ON DELETE CASCADE
);

-- ─── SEED: ADMIN USER ─────────────────────────────────────
-- Login: admin@bloomify.com / admin123
INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@bloomify.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin')
ON DUPLICATE KEY UPDATE id = id;

-- ─── SEED: FLOWERS ────────────────────────────────────────
INSERT INTO flowers (name, emoji, price, meaning, tag, stock) VALUES
('Rose',      '🌹', 12.00, 'Love & Romance',     'Best Seller', 100),
('Tulip',     '🌷', 10.00, 'Joy & Happiness',    'Popular',     100),
('Lily',      '💐', 15.00, 'Purity & Grace',     'New',         100),
('Sunflower', '🌻',  8.00, 'Warmth & Positivity','Cheerful',    100),
('Peony',     '🌸', 18.00, 'Prosperity & Beauty','Luxury',      100),
('Orchid',    '🪷', 20.00, 'Elegance & Strength','Premium',     100),
('Daisy',     '🌼',  7.00, 'Innocence & New Life','Fresh',      100),
('Lavender',  '💜', 11.00, 'Calm & Serenity',    'Relaxing',   100)
ON DUPLICATE KEY UPDATE id = id;

-- ─── FLOWER VARIETY ────────────────────────────────────────
USE bloomify;
-- 1 = Rose
INSERT INTO flower_varieties (flower_id, variety_name, color_hex, price, stock) VALUES
  (1, 'Red',    '#C0392B', 12.00, 25),
  (1, 'Pink',   '#F1A7B7', 13.00, 20),
  (1, 'Yellow', '#F4D35E', 11.00, 15),
  (1, 'White',  '#FAFAF5', 14.00, 10);

-- 2 = Tulip
INSERT INTO flower_varieties (flower_id, variety_name, color_hex, price, stock) VALUES
  (2, 'Pink',   '#F3B6CC', 10.00, 20),
  (2, 'Yellow', '#F6E27A', 9.00,  15),
  (2, 'Red',    '#C8413B', 11.00, 10),
  (2, 'Purple', '#9B7EBD', 12.00, 8);

-- 3 = Lily
INSERT INTO flower_varieties (flower_id, variety_name, color_hex, price, stock) VALUES
  (3, 'Pink',   '#E8AFC2', 15.00, 15),
  (3, 'White',  '#FAFAF5', 16.00, 10),
  (3, 'Orange', '#E8965A', 17.00, 8);

-- 4 = Sunflower
INSERT INTO flower_varieties (flower_id, variety_name, color_hex, price, stock) VALUES
  (4, 'Yellow',   '#F2C336', 8.00,  35),
  (4, 'Burgundy', '#7B3F3F', 10.00, 12);

-- 5 = Peony
INSERT INTO flower_varieties (flower_id, variety_name, color_hex, price, stock) VALUES
  (5, 'Pink',  '#EFB3C4', 18.00, 12),
  (5, 'White', '#FAFAF5', 19.00, 8),
  (5, 'Coral', '#E98A72', 20.00, 6);

-- 6 = Orchid
INSERT INTO flower_varieties (flower_id, variety_name, color_hex, price, stock) VALUES
  (6, 'Purple', '#9B7EBD', 20.00, 10),
  (6, 'White',  '#FAFAF5', 22.00, 6),
  (6, 'Pink',   '#E8AFC2', 21.00, 6);

-- 7 = Daisy
INSERT INTO flower_varieties (flower_id, variety_name, color_hex, price, stock) VALUES
  (7, 'White',  '#FAFAF5', 7.00, 25),
  (7, 'Yellow', '#F2D24B', 7.50, 15),
  (7, 'Pink',   '#EFB3C4', 8.00, 10);

-- 8 = Lavender
INSERT INTO flower_varieties (flower_id, variety_name, color_hex, price, stock) VALUES
  (8, 'Purple', '#9B7EBD', 11.00, 18),
  (8, 'Blue',   '#7C93C4', 12.00, 10);

-- Now remove the placeholder "Standard" rows for all 8 flowers.
DELETE FROM flower_varieties WHERE variety_name = 'Standard' AND flower_id IN (1,2,3,4,5,6,7,8);

