CREATE DATABASE IF NOT EXISTS sweetheaven_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sweetheaven_db;

CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(150) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, role ENUM('admin','customer') DEFAULT 'customer', profile_image VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, description TEXT, image VARCHAR(255) DEFAULT NULL);

CREATE TABLE IF NOT EXISTS products (id INT AUTO_INCREMENT PRIMARY KEY, category_id INT NOT NULL, name VARCHAR(200) NOT NULL, price DECIMAL(10,2) NOT NULL, stock INT DEFAULT 0, description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE);

CREATE TABLE IF NOT EXISTS product_images (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, image_url VARCHAR(255) NOT NULL, is_primary TINYINT(1) DEFAULT 0, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE);

CREATE TABLE IF NOT EXISTS wishlist (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_wishlist (user_id, product_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE);

CREATE TABLE IF NOT EXISTS reviews (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, product_id INT NOT NULL, rating TINYINT NOT NULL, comment TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_review (user_id, product_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE);

CREATE TABLE IF NOT EXISTS orders (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, phone VARCHAR(20), order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, request_note TEXT, shipping_method ENUM('standard','express') DEFAULT 'standard', shipping_address TEXT NOT NULL, total_amount DECIMAL(12,2) NOT NULL, status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending', FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);

CREATE TABLE IF NOT EXISTS order_items (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL, price DECIMAL(10,2) NOT NULL, FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id));

CREATE TABLE IF NOT EXISTS payment_methods (id INT AUTO_INCREMENT PRIMARY KEY, payment_name VARCHAR(100) NOT NULL, acc_name VARCHAR(100), acc_no VARCHAR(50), qr_image VARCHAR(255));

CREATE TABLE IF NOT EXISTS payment (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, payment_method_id INT NOT NULL, payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('pending','paid','failed') DEFAULT 'pending', paid_at TIMESTAMP NULL DEFAULT NULL, FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE, FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id));

CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, order_id INT NULL, type VARCHAR(50) NOT NULL DEFAULT 'general', title VARCHAR(200) NULL, message TEXT, is_seen TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_user_seen (user_id, is_seen), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL);

INSERT IGNORE INTO users (name, email, password, role) VALUES ('Admin', 'admin@sweetheaven.com', '\\\.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
INSERT IGNORE INTO users (name, email, password, role) VALUES ('Ma Aye', 'customer@sweetheaven.com', '\\\.PJy4cUH0.2w7j1OeivX.BoFxWxHXNAuPJ0eE.iDN4XkZi', 'customer');

INSERT IGNORE INTO categories (id, name, description, image) VALUES (1,'Ceremony Cakes','Beautiful cakes for weddings and celebrations','../images/ceremony.jpg'),(2,'Slice Cakes','Individual cake slices in various flavors','../images/slicecake.jpg'),(3,'Cup Cakes','Freshly baked cupcakes with frosting','../images/cupcake1.jpg'),(4,'Breads','Artisan breads baked fresh every morning','../images/bread1.jpg'),(5,'Pastries','Flaky and buttery pastries','../images/pastry.jpg'),(6,'Donuts','Glazed and filled donuts','../images/donut4.jpg'),(7,'Savory Items','Savory baked goods','../images/pizza2.jpg'),(8,'Desserts','Sweet desserts and puddings','../images/pudd.jpg');

INSERT IGNORE INTO products (id,category_id,name,price,stock,description) VALUES (1,1,'Classic Birthday Cake',35000,15,'A stunning layered birthday cake with fresh cream.'),(2,1,'Wedding Tier Cake',150000,5,'Elegant 3-tier wedding cake with fondant decoration.'),(3,2,'Chocolate Fudge Slice',4500,50,'Rich chocolate fudge cake slice with ganache.'),(4,2,'Lemon Chiffon Slice',3500,40,'Light lemon chiffon with lemon cream frosting.'),(5,3,'Vanilla Cupcake Box of 6',9000,30,'Fluffy vanilla cupcakes with buttercream.'),(6,3,'Red Velvet Cupcake Box of 6',12000,25,'Red velvet with cream cheese frosting.'),(7,4,'Sourdough Loaf',6000,20,'Naturally leavened sourdough.'),(8,6,'Glazed Donut Pack of 6',13000,35,'Classic glazed donuts.'),(9,5,'Butter Croissant',2500,60,'Flaky French-style croissant.'),(10,8,'Mango Pudding',3000,45,'Silky smooth mango pudding.');

INSERT IGNORE INTO product_images (product_id,image_url,is_primary) VALUES (1,'../images/diana.jpg',1),(2,'../images/ceremony.jpg',1),(3,'../images/slice.jpg',1),(4,'../images/lemon.jpg',1),(5,'../images/cupcake1.jpg',1),(6,'../images/cupcake1.jpg',1),(7,'../images/bread.jpg',1),(8,'../images/do.jpg',1),(9,'../images/pastry.jpg',1),(10,'../images/pudd.jpg',1);

INSERT IGNORE INTO payment_methods (id,payment_name,acc_name,acc_no) VALUES (1,'KBZ Pay','Sweet Heaven Bakery','09 4500 12345'),(2,'Wave Pay','Sweet Heaven Bakery','09 7800 67890');
