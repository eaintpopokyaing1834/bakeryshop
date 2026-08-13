<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

try {
    echo "Starting migration...\n";

    // 1. Add scope column
    try {
        $db->exec("ALTER TABLE discounts ADD COLUMN scope ENUM('product', 'order') NOT NULL DEFAULT 'product' AFTER name");
        echo "Added scope column.\n";
    } catch (Exception $e) {
        echo "scope column exists or error: " . $e->getMessage() . "\n";
    }

    // 2. Add min_order_amount column
    try {
        $db->exec("ALTER TABLE discounts ADD COLUMN min_order_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER value");
        echo "Added min_order_amount column.\n";
    } catch (Exception $e) {
        echo "min_order_amount column exists or error: " . $e->getMessage() . "\n";
    }

    // 3. Add is_first_order column
    try {
        $db->exec("ALTER TABLE discounts ADD COLUMN is_first_order TINYINT(1) NOT NULL DEFAULT 0 AFTER min_order_amount");
        echo "Added is_first_order column.\n";
    } catch (Exception $e) {
        echo "is_first_order column exists or error: " . $e->getMessage() . "\n";
    }

    // 4. Modify type column to include free_gift
    try {
        $db->exec("ALTER TABLE discounts MODIFY COLUMN type ENUM('percentage', 'fixed', 'free_gift') NOT NULL DEFAULT 'percentage'");
        echo "Modified type column to include free_gift.\n";
    } catch (Exception $e) {
        echo "type column modify error: " . $e->getMessage() . "\n";
    }

    // 5. Insert default First Order discount
    $stmt = $db->prepare("SELECT COUNT(*) FROM discounts WHERE scope = 'order' AND is_first_order = 1");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $db->prepare("INSERT INTO discounts (name, scope, type, value, min_order_amount, is_first_order, status) 
                      VALUES ('First Order Discount', 'order', 'percentage', 5, 0, 1, 1)")->execute();
        echo "Inserted First Order Discount.\n";
    } else {
        echo "First Order Discount already exists.\n";
    }

    // 6. Insert default Free Gift discount
    $stmt = $db->prepare("SELECT COUNT(*) FROM discounts WHERE scope = 'order' AND type = 'free_gift'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $db->prepare("INSERT INTO discounts (name, scope, type, value, min_order_amount, is_first_order, status) 
                      VALUES ('Free Gift over 50k', 'order', 'free_gift', 0, 50000, 0, 1)")->execute();
        echo "Inserted Free Gift Discount.\n";
    } else {
        echo "Free Gift Discount already exists.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
