-- =============================================
-- Sweet Heaven Bakery — Full Database Schema
-- Database: sweetheaven_db
-- =============================================

CREATE DATABASE IF NOT EXISTS sweetheaven_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sweetheaven_db;

-- -----------------------------------------------
-- Table: users
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') DEFAULT 'customer',
    profile_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -----------------------------------------------
-- Table: categories
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255) DEFAULT NULL
);

-- -----------------------------------------------
-- Table: products
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- -----------------------------------------------
-- Table: discounts
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    value DECIMAL(10,2) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add discount_id to products
ALTER TABLE products ADD COLUMN discount_id INT DEFAULT NULL AFTER price,
    ADD FOREIGN KEY (discount_id) REFERENCES discounts(id) ON DELETE SET NULL;

-- Seed discount data
INSERT IGNORE INTO discounts (id, name, type, value, status) VALUES
(1, '10% OFF', 'percentage', 10, 1),
(2, '15% OFF', 'percentage', 15, 1),
(3, '20% OFF', 'percentage', 20, 1),
(4, '5,000 MMK OFF', 'fixed', 5000, 1);

-- -----------------------------------------------
-- Table: product_images
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- -----------------------------------------------
-- Table: wishlist
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- -----------------------------------------------
-- Table: reviews
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
);

-- -----------------------------------------------
-- Table: orders
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone VARCHAR(20),
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    request_note TEXT,
    shipping_method ENUM('standard', 'express') DEFAULT 'standard',
    shipping_address TEXT NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -----------------------------------------------
-- Table: order_items
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- -----------------------------------------------
-- Table: payment_methods
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_name VARCHAR(100) NOT NULL,
    acc_name VARCHAR(100),
    acc_no VARCHAR(50),
    logo_image VARCHAR(255) DEFAULT NULL,
    qr_image VARCHAR(255)
);

-- Run this if table already exists without logo_image column:
-- ALTER TABLE payment_methods ADD COLUMN logo_image VARCHAR(255) DEFAULT NULL AFTER acc_no;

-- -----------------------------------------------
-- Table: payment
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS payment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    paid_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
);

-- -----------------------------------------------
-- Table: customize_requests
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS customize_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    size VARCHAR(50) NOT NULL,
    flavor VARCHAR(100) NOT NULL,
    color VARCHAR(100) DEFAULT NULL,
    cake_message TEXT DEFAULT NULL,
    reference_image VARCHAR(255) DEFAULT NULL,
    delivery_date DATE NOT NULL,
    additional_notes TEXT DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected', 'ordered') DEFAULT 'pending',
    admin_price DECIMAL(10,2) DEFAULT NULL,
    admin_note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add customize_request_id to orders (if not exists)
-- This migration is done separately to avoid breaking existing setups
-- ALTER TABLE orders ADD COLUMN customize_request_id INT DEFAULT NULL AFTER pickup_date;

-- =============================================
-- SEED DATA
-- =============================================

-- Admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@sweetheaven.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample customer (password: customer123)
INSERT INTO users (name, email, password, role) VALUES
('Ma Aye', 'customer@sweetheaven.com', '$2y$10$TKh8H1.PJy4cUH0.2w7j1OeivX.BoFxWxHXNAuPJ0eE.iDN4XkZi', 'customer');

-- Categories
INSERT INTO categories (name, description, image) VALUES
('Ceremony Cakes', 'Beautiful cakes for weddings, anniversaries, and special celebrations', '../images/ceremony.jpg'),
('Slice Cakes', 'Individual cake slices in various flavors', '../images/slicecake.jpg'),
('Cup Cakes', 'Freshly baked cupcakes with delicious frosting', '../images/cupcake1.jpg'),
('Breads', 'Artisan breads baked fresh every morning', '../images/bread1.jpg'),
('Pastries', 'Flaky and buttery pastries made with premium ingredients', '../images/pastry.jpg'),
('Donuts', 'Glazed and filled donuts in many flavors', '../images/donut4.jpg'),
('Savory Items', 'Savory baked goods including pizzas and burgers', '../images/pizza2.jpg'),
('Desserts', 'Sweet desserts and puddings', '../images/pudd.jpg');

-- Products
INSERT INTO products (category_id, name, price, stock, description) VALUES
(1, 'Classic Birthday Cake', 35000, 15, 'A stunning layered birthday cake with fresh cream and seasonal fruits. Perfect for any celebration.'),
(1, 'Wedding Tier Cake', 150000, 5, 'Elegant 3-tier wedding cake with fondant decoration. Customizable to your theme.'),
(2, 'Chocolate Fudge Slice', 4500, 50, 'Rich and indulgent chocolate fudge cake slice with ganache topping.'),
(2, 'Lemon Chiffon Slice', 3500, 40, 'Light and zesty lemon chiffon cake with delicate lemon cream frosting.'),
(3, 'Vanilla Cupcake (Box of 6)', 9000, 30, 'Fluffy vanilla cupcakes with swirled buttercream frosting. Great for gifting.'),
(3, 'Red Velvet Cupcake (Box of 6)', 12000, 25, 'Classic red velvet cupcakes with cream cheese frosting.'),
(4, 'Sourdough Loaf', 6000, 20, 'Naturally leavened sourdough with a crispy crust and chewy interior.'),
(6, 'Glazed Donut (Pack of 6)', 13000, 35, 'Classic glazed donuts, light and fluffy. Available in original and strawberry glaze.'),
(5, 'Butter Croissant', 2500, 60, 'Perfectly flaky and buttery French-style croissant baked fresh every morning.'),
(8, 'Mango Pudding', 3000, 45, 'Silky smooth mango pudding made with real mango pulp and fresh cream.');

-- Product Images
INSERT INTO product_images (product_id, image_url, is_primary) VALUES
(1, '../images/diana.jpg', 1),
(2, '../images/ceremony.jpg', 1),
(3, '../images/slice.jpg', 1),
(4, '../images/lemon.jpg', 1),
(5, '../images/cupcake1.jpg', 1),
(6, '../images/cupcake1.jpg', 1),
(7, '../images/bread.jpg', 1),
(8, '../images/do.jpg', 1),
(9, '../images/pastry.jpg', 1),
(10, '../images/pudd.jpg', 1);

-- Payment Methods
INSERT INTO payment_methods (payment_name, acc_name, acc_no, qr_image) VALUES
('KBZ Pay', 'Sweet Heaven Bakery', '09 4500 12345', NULL),
('Wave Pay', 'Sweet Heaven Bakery', '09 7800 67890', NULL);
