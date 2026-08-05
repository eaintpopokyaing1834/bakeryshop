-- ============================================================
--  Sweetheaven Bakery — Database Schema
--  Normal Form  : 3NF (Third Normal Form)
--  Engine       : InnoDB
--  Charset      : utf8mb4 / utf8mb4_unicode_ci
--
--  3NF VERIFICATION SUMMARY
--  ─────────────────────────────────────────────────────────
--  All 14 tables satisfy 1NF, 2NF, and 3NF.
--
--  KEY DECISION — shipping_method stays as ENUM:
--    A 3NF transitive dependency on shipping_method would only
--    exist if orders stored a separate shipping_fee column:
--      order_id → shipping_method → shipping_fee  (violation)
--    There is NO shipping_fee column in orders. The fee is
--    computed in PHP at checkout and folded into total_amount.
--    The ENUM stores only a label — not a dependent fact.
--    Therefore NO transitive dependency exists.  ENUM ✅ 3NF.
--
--  JUSTIFIED EXCEPTION — orders.total_amount:
--    Derivable from order_items, but kept as a historical
--    snapshot (prices change over time). This is the standard
--    accepted practice in financial/order systems.
-- ============================================================

CREATE DATABASE IF NOT EXISTS sweetheaven_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sweetheaven_db;

-- ────────────────────────────────────────────────────────────
--  TABLE CREATION ORDER (respects all FK dependencies)
--
--   1. users
--   2. categories
--   3. discounts
--   4. products
--   5. product_images
--   6. payment_methods
--   7. customize_requests
--   8. orders
--   9. order_items
--  10. payment
--  11. wishlist
--  12. reviews
--  13. notifications
--  14. contact_messages
-- ────────────────────────────────────────────────────────────


-- ============================================================
--  1. USERS
--  Functional Dependencies:
--    id → name, email, password, role, status,
--          profile_image, created_at, updated_at
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) UNIQUE NOT NULL,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('admin','customer','cashier') NOT NULL DEFAULT 'customer',
    status        ENUM('active','inactive')          NOT NULL DEFAULT 'active',
    profile_image VARCHAR(255) DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role   (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  2. CATEGORIES
--  Functional Dependencies:
--    id → name, description, image
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT         DEFAULT NULL,
    image       VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  3. DISCOUNTS
--  Functional Dependencies:
--    id → name, type, value, status, created_at
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS discounts (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    type       ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    value      DECIMAL(10,2) NOT NULL,
    status     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  4. PRODUCTS
--  Functional Dependencies:
--    id → category_id (FK), name, price, discount_id (FK),
--          stock, description, created_at, updated_at
--  category_id and discount_id are FK references — not
--  transitive dependencies — their details live in their own
--  normalized tables.
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    category_id INT           NOT NULL,
    name        VARCHAR(200)  NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    discount_id INT           DEFAULT NULL,
    stock       INT           NOT NULL DEFAULT 0,
    description TEXT          DEFAULT NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (discount_id) REFERENCES discounts(id)  ON DELETE SET NULL,
    INDEX idx_products_category (category_id),
    INDEX idx_products_discount (discount_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  5. PRODUCT IMAGES
--  Functional Dependencies:
--    id → product_id (FK), image_url, is_primary, created_at
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS product_images (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    product_id INT          NOT NULL,
    image_url  VARCHAR(255) NOT NULL,
    is_primary TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_images_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  6. PAYMENT METHODS
--  Functional Dependencies:
--    id → payment_name, acc_name, acc_no, qr_image, is_active
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS payment_methods (
    id           INT          AUTO_INCREMENT PRIMARY KEY,
    payment_name VARCHAR(100) NOT NULL,
    acc_name     VARCHAR(100) DEFAULT NULL,
    acc_no       VARCHAR(50)  DEFAULT NULL,
    qr_image     VARCHAR(255) DEFAULT NULL,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  7. CUSTOMIZE REQUESTS
--  Functional Dependencies:
--    id → user_id (FK), size, flavor, color, cake_message,
--          reference_image, delivery_date, additional_notes,
--          status, admin_price, admin_note, created_at, updated_at
--  All attributes describe this specific custom cake request.
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS customize_requests (
    id               INT           AUTO_INCREMENT PRIMARY KEY,
    user_id          INT           NOT NULL,
    size             VARCHAR(50)   NOT NULL,
    flavor           VARCHAR(100)  NOT NULL,
    color            VARCHAR(100)  DEFAULT NULL,
    cake_message     TEXT          DEFAULT NULL,
    reference_image  VARCHAR(255)  DEFAULT NULL,
    delivery_date    DATE          NOT NULL,
    additional_notes TEXT          DEFAULT NULL,
    status           ENUM('pending','approved','rejected','ordered') NOT NULL DEFAULT 'pending',
    admin_price      DECIMAL(10,2) DEFAULT NULL,
    admin_note       TEXT          DEFAULT NULL,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_customize_user   (user_id),
    INDEX idx_customize_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  8. ORDERS
--  Functional Dependencies:
--    id → user_id (FK), customize_request_id (FK), phone,
--          order_date, request_note, shipping_method,
--          shipping_address, total_amount*, status
--
--  WHY shipping_method stays as ENUM (not a 3NF violation):
--    A transitive dependency would require a shipping_fee column
--    inside orders.  There is none — the fee is computed in PHP
--    and absorbed into total_amount.  shipping_method is simply
--    an atomic label attribute of the order. ✅ 3NF
--
--  WHY total_amount is kept (justified exception):
--    total_amount = SUM(items × price) - discounts + fee.
--    Product prices change over time, so this is a historical
--    snapshot, not a purely derived value.  Keeping it is the
--    accepted standard for financial order records.
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id                   INT           AUTO_INCREMENT PRIMARY KEY,
    user_id              INT           NOT NULL,
    customize_request_id INT           DEFAULT NULL,
    phone                VARCHAR(20)   DEFAULT NULL,
    order_date           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    request_note         TEXT          DEFAULT NULL,
    shipping_method      ENUM('express', 'free_delivery', 'pickup') NOT NULL DEFAULT 'free_delivery',    shipping_address     TEXT          NOT NULL,
    total_amount         DECIMAL(12,2) NOT NULL,    -- historical snapshot (see note above)
    status               ENUM('pending','processing','shipped','delivered','cancelled')
                         NOT NULL DEFAULT 'pending',
    FOREIGN KEY (user_id)              REFERENCES users(id)              ON DELETE CASCADE,
    FOREIGN KEY (customize_request_id) REFERENCES customize_requests(id) ON DELETE SET NULL,
    INDEX idx_orders_user     (user_id),
    INDEX idx_orders_status   (status),
    INDEX idx_orders_date     (order_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  9. ORDER ITEMS
--  Functional Dependencies:
--    id → order_id (FK), product_id (FK, nullable), quantity, price
--
--  NOTE — price:
--    Stores the unit price AT TIME OF PURCHASE (snapshot).
--    Not derived from products.price (which changes over time).
--    Directly dependent on this row's id. ✅ 3NF
--
--  NOTE — product_id NULL:
--    Custom cake orders have no linked product row.
--    NULL is intentional (checkout.php inserts NULL product_id
--    for custom cake line items).
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    id         INT           AUTO_INCREMENT PRIMARY KEY,
    order_id   INT           NOT NULL,
    product_id INT           DEFAULT NULL,       -- NULL for custom cake line items
    quantity   INT           NOT NULL,
    price      DECIMAL(10,2) NOT NULL,           -- unit price snapshot at purchase time
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_order_items_order   (order_id),
    INDEX idx_order_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  10. PAYMENT
--  Functional Dependencies:
--    id → order_id (FK), payment_method_id (FK), screenshot,
--          payment_date, status, paid_at
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS payment (
    id                INT          AUTO_INCREMENT PRIMARY KEY,
    order_id          INT          NOT NULL,
    payment_method_id INT          NOT NULL,
    screenshot        VARCHAR(255) DEFAULT NULL,
    payment_date      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    status            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    paid_at           TIMESTAMP    NULL DEFAULT NULL,
    FOREIGN KEY (order_id)          REFERENCES orders(id)          ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE RESTRICT,
    INDEX idx_payment_order  (order_id),
    INDEX idx_payment_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  11. WISHLIST
--  Functional Dependencies:
--    id → user_id (FK), product_id (FK), created_at
--  Natural uniqueness enforced via UNIQUE KEY on (user_id, product_id).
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS wishlist (
    id         INT       AUTO_INCREMENT PRIMARY KEY,
    user_id    INT       NOT NULL,
    product_id INT       NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  12. REVIEWS
--  Functional Dependencies:
--    id → user_id (FK), product_id (FK), rating,
--          comment, status, created_at
--  rating is an opinion about a specific product by a specific
--  user — not derivable from any other non-key attribute.
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS reviews (
    id         INT       AUTO_INCREMENT PRIMARY KEY,
    user_id    INT       NOT NULL,
    product_id INT       NOT NULL,
    rating     TINYINT   NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT      DEFAULT NULL,
    status     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_reviews_product (product_id),
    INDEX idx_reviews_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  13. NOTIFICATIONS
--  Functional Dependencies:
--    id → user_id (FK, nullable), order_id (FK, nullable),
--          type, title, message, is_seen, created_at
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NULL DEFAULT NULL,
    order_id   INT          NULL DEFAULT NULL,
    type       VARCHAR(50)  NOT NULL DEFAULT 'general',
    title      VARCHAR(200) DEFAULT NULL,
    message    TEXT         DEFAULT NULL,
    is_seen    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_notif_user_seen (user_id, is_seen),
    INDEX idx_notif_type      (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  14. CONTACT MESSAGES
--  Functional Dependencies:
--    id → name, email, phone, message, is_read, created_at
--  1NF ✅  2NF ✅  3NF ✅
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    phone      VARCHAR(50)  DEFAULT NULL,
    message    TEXT         NOT NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  SEED DATA
-- ============================================================

-- Users: admin + sample customer
INSERT IGNORE INTO users (name, email, password, role, status) VALUES
    ('Admin',  'admin@sweetheaven.com',
     '$2y$10$RNmAunjZMJ/zJRqmr.oCdOibv9w2LkwcmVnh5.30EYv23HoLkRhY.',
     'admin', 'active'),
    ('Ma Aye', 'customer@sweetheaven.com',
     '$2y$10$YePnUK1w7c30DOlrrtq2zuSFKCqCPu8Y94Q6kpef/QvFOUoAGk40e',
     'customer', 'active');

-- Product categories
INSERT IGNORE INTO categories (id, name, description, image) VALUES
    (1, 'Ceremony Cakes', 'Beautiful cakes for weddings and celebrations', '/sweetheaven/images/ceremony.jpg'),
    (2, 'Slice Cakes',    'Individual cake slices in various flavors',     '/sweetheaven/images/slicecake.jpg'),
    (3, 'Cup Cakes',      'Freshly baked cupcakes with frosting',          '/sweetheaven/images/cupcake1.jpg'),
    (4, 'Breads',         'Artisan breads baked fresh every morning',      '/sweetheaven/images/bread1.jpg'),
    (5, 'Pastries',       'Flaky and buttery pastries',                    '/sweetheaven/images/pastry.jpg'),
    (6, 'Donuts',         'Glazed and filled donuts',                      '/sweetheaven/images/donut4.jpg'),
    (7, 'Savory Items',   'Savory baked goods',                            '/sweetheaven/images/pizza2.jpg'),
    (8, 'Desserts',       'Sweet desserts and puddings',                   '/sweetheaven/images/pudd.jpg');

-- Sample products
INSERT IGNORE INTO products (id, category_id, name, price, stock, description) VALUES
    (1,  1, 'Classic Birthday Cake',        35000, 15, 'A stunning layered birthday cake with fresh cream.'),
    (2,  1, 'Wedding Tier Cake',           150000,  5, 'Elegant 3-tier wedding cake with fondant decoration.'),
    (3,  2, 'Chocolate Fudge Slice',         4500, 50, 'Rich chocolate fudge cake slice with ganache.'),
    (4,  2, 'Lemon Chiffon Slice',           3500, 40, 'Light lemon chiffon with lemon cream frosting.'),
    (5,  3, 'Vanilla Cupcake Box of 6',      9000, 30, 'Fluffy vanilla cupcakes with buttercream.'),
    (6,  3, 'Red Velvet Cupcake Box of 6',  12000, 25, 'Red velvet with cream cheese frosting.'),
    (7,  4, 'Sourdough Loaf',                6000, 20, 'Naturally leavened sourdough.'),
    (8,  6, 'Glazed Donut Pack of 6',       13000, 35, 'Classic glazed donuts.'),
    (9,  5, 'Butter Croissant',              2500, 60, 'Flaky French-style croissant.'),
    (10, 8, 'Mango Pudding',                 3000, 45, 'Silky smooth mango pudding.');

-- Product images
INSERT IGNORE INTO product_images (product_id, image_url, is_primary) VALUES
    (1,  '/sweetheaven/images/diana.jpg',    1),
    (2,  '/sweetheaven/images/ceremony.jpg', 1),
    (3,  '/sweetheaven/images/slice.jpg',    1),
    (4,  '/sweetheaven/images/lemon.jpg',    1),
    (5,  '/sweetheaven/images/cupcake1.jpg', 1),
    (6,  '/sweetheaven/images/cupcake1.jpg', 1),
    (7,  '/sweetheaven/images/bread.jpg',    1),
    (8,  '/sweetheaven/images/do.jpg',       1),
    (9,  '/sweetheaven/images/pastry.jpg',   1),
    (10, '/sweetheaven/images/pudd.jpg',     1);

-- Discount presets
INSERT IGNORE INTO discounts (id, name, type, value, status) VALUES
    (1, '10% OFF',       'percentage', 10.00,   1),
    (2, '15% OFF',       'percentage', 15.00,   1),
    (3, '20% OFF',       'percentage', 20.00,   1),
    (4, '5,000 MMK OFF', 'fixed',      5000.00, 1);

-- Payment methods
INSERT IGNORE INTO payment_methods (id, payment_name, acc_name, acc_no, is_active) VALUES
    (1, 'KBZ Pay',  'Sweet Heaven Bakery', '09 4500 12345', 1),
    (2, 'Wave Pay', 'Sweet Heaven Bakery', '09 7800 67890', 1);
