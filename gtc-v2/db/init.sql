SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS gametrade CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gametrade;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    rarity VARCHAR(20) NOT NULL,
    wear VARCHAR(30) DEFAULT 'Factory New',
    price DECIMAL(10, 2) DEFAULT 0.00,
    image_url VARCHAR(255),
    owner_id INT,
    FOREIGN KEY (owner_id) REFERENCES users(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255),
    amount_change DECIMAL(10, 2),
    user_id INT,
    items_json TEXT,
    status VARCHAR(20) DEFAULT 'completed',
    trade_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO users (username, balance) VALUES ('Gamer_1', 1000.00);
INSERT INTO users (username, balance) VALUES ('Market_Bot', 500000.00);

INSERT INTO items (name, type, rarity, wear, price, image_url, owner_id) VALUES 
('M4A1-S | Hyper Beast', 'rifles', 'legendary', 'Field-Tested', 2450.00, 'assets/weapon_m4a1.png', 1),
('AK-47 | Asiimov', 'rifles', 'legendary', 'Minimal Wear', 120.00, 'assets/weapon_ak47.png', 1),
('Butterfly Knife | Fade', 'knives', 'legendary', 'Factory New', 1850.00, 'assets/weapon_knife_butterfl.png', 1);

INSERT INTO items (name, type, rarity, wear, price, image_url, owner_id) VALUES 
('Karambit | Doppler', 'knives', 'legendary', 'Factory New', 950.00, 'assets/weapon_knife_karambit.png', 2),
('AWP | Dragon Lore', 'snipers', 'legendary', 'Factory New', 5500.00, 'assets/weapon_awp_cu_medieval_dragon.png', 2),
('Sport Gloves | Amphibious', 'gloves', 'legendary', 'Factory New', 890.00, 'assets/sporty_gloves_sporty_bluee.png', 2),
('Desert Eagle | Blaze', 'pistols', 'rare', 'Factory New', 650.00, 'assets/weapon_deagle_aa_flames.png', 2);