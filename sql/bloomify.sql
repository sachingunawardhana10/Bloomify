-- ============================================================
--  BLOOMIFY - Complete Database Setup
--  HOW TO RUN:
--    1. Open phpMyAdmin, then click the SQL tab at the top.
--    2. Paste this entire file, then click Go.
--    OR in terminal: mysql -u root -p < bloomify.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS bloomify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bloomify;

-- USERS -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    role       ENUM('customer','admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- FLOWERS -----------------------------------------------------
CREATE TABLE IF NOT EXISTS flowers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    emoji      VARCHAR(10)   NOT NULL DEFAULT '🌸',
    image      VARCHAR(255)  NOT NULL DEFAULT 'images/flowers/default.jpg',
    price      DECIMAL(10,2) NOT NULL,
    meaning    VARCHAR(200)  NOT NULL,
    tag        VARCHAR(50)   DEFAULT NULL,
    subcategory VARCHAR(50)  DEFAULT NULL,
    stock      INT           DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_flower_name (name)
);

-- CART --------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    flower_id  INT NOT NULL,
    quantity   INT DEFAULT 1,
    UNIQUE KEY unique_cart (user_id, flower_id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (flower_id) REFERENCES flowers(id) ON DELETE CASCADE
);

-- ORDERS ------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    total      DECIMAL(10,2) NOT NULL,
    status     ENUM('pending','processing','delivered','cancelled') DEFAULT 'pending',
    notes      TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ORDER ITEMS -------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    order_id  INT NOT NULL,
    flower_id INT NOT NULL,
    quantity  INT NOT NULL,
    price     DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)  REFERENCES orders(id)  ON DELETE CASCADE,
    FOREIGN KEY (flower_id) REFERENCES flowers(id) ON DELETE CASCADE
);

-- SEED: ADMIN USER --------------------------------------------
-- Login: admin@bloomify.com / admin123
INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@bloomify.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin')
ON DUPLICATE KEY UPDATE id = id;

-- SEED: FLOWERS -----------------------------------------------
-- Tags match the catalog tabs. Subcategories power the Emotion and Occasion cards.
INSERT INTO flowers (name, emoji, image, price, meaning, tag, subcategory, stock) VALUES
('Rose',              '🌹', 'images/flowers/rose.jpg',      12.00, 'Love & Romance',         'Popular',  NULL,              100),
('Tulip',             '🌷', 'images/flowers/tulip.jpg',     10.00, 'Joy & Happiness',        'Popular',  NULL,              100),
('Lily',              '💐', 'images/flowers/lily.jpg',      15.00, 'Purity & Grace',         'New',      NULL,              100),
('Orchid',            '🪷', 'images/flowers/default.jpg',   20.00, 'Elegance & Strength',    'New',      NULL,              100),
('Red Rose',          '🌹', 'images/flowers/rose.jpg',      12.00, 'Love & Romance',         'Emotion',  'Love',            100),
('Sunflower',         '🌻', 'images/flowers/sunflower.jpg',  8.00, 'Warmth & Positivity',    'Emotion',  'Happiness',       100),
('Yellow Tulip',      '🌷', 'images/flowers/tulip.jpg',      9.00, 'Friendship & Cheer',     'Emotion',  'Friendship',      100),
('Pink Peony',        '🌸', 'images/flowers/peony.jpg',     18.00, 'Gratitude & Beauty',     'Emotion',  'Gratitude',       100),
('White Lily',        '💐', 'images/flowers/lily.jpg',      15.00, 'Peace & Sympathy',       'Emotion',  'Sympathy',        100),
('Daisy',             '🌼', 'images/flowers/sunflower.jpg',  9.00, 'Apology & Fresh Starts', 'Emotion',  'Apology',         100),
('Birthday Daisy',    '🌼', 'images/flowers/sunflower.jpg', 10.00, 'Bright Birthday Joy',    'Occasion', 'Birthday',        100),
('Anniversary Rose',  '🌹', 'images/flowers/rose.jpg',      16.00, 'Lasting Romance',        'Occasion', 'Anniversary',     100),
('Wedding Lily',      '💐', 'images/flowers/lily.jpg',      17.00, 'Elegant New Beginnings', 'Occasion', 'Wedding',         100),
('Graduation Tulip',  '🌷', 'images/flowers/tulip.jpg',     11.00, 'Achievement & Pride',    'Occasion', 'Graduation',      100),
('Mother''s Peony',   '🌸', 'images/flowers/peony.jpg',     19.00, 'Tender Appreciation',    'Occasion', 'Mother''s Day',   100),
('Valentine Rose',    '🌹', 'images/flowers/rose.jpg',      14.00, 'Classic Valentine Love', 'Occasion', 'Valentine''s Day', 100)
ON DUPLICATE KEY UPDATE
    emoji = VALUES(emoji),
    image = VALUES(image),
    price = VALUES(price),
    meaning = VALUES(meaning),
    tag = VALUES(tag),
    subcategory = VALUES(subcategory),
    stock = VALUES(stock);
