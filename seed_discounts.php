<?php
require 'config/db.php';
$db = getDB();
$db->exec("INSERT INTO discounts (name, scope, type, value, min_order_amount, is_first_order, status) VALUES ('First Order Discount', 'order', 'percentage', 5.00, 0, 1, 1), ('Free Gift Over 50K', 'order', 'free_gift', 0, 50000, 0, 1)");
echo 'Success';
