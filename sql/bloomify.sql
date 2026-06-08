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

-- ─── CART ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cart (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    flower_id  INT NOT NULL,
    quantity   INT DEFAULT 1,
    UNIQUE KEY unique_cart (user_id, flower_id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (flower_id) REFERENCES flowers(id) ON DELETE CASCADE
);

-- ─── ORDERS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    total      DECIMAL(10,2) NOT NULL,
    status     ENUM('pending','processing','delivered','cancelled') DEFAULT 'pending',
    notes      TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── ORDER ITEMS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_items (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    order_id  INT NOT NULL,
    flower_id INT NOT NULL,
    quantity  INT NOT NULL,
    price     DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)  REFERENCES orders(id)  ON DELETE CASCADE,
    FOREIGN KEY (flower_id) REFERENCES flowers(id) ON DELETE CASCADE
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
('Orchid',    '🪷', 20.00, 'Elegance & Strength','Premium',     100)
ON DUPLICATE KEY UPDATE id = id;
