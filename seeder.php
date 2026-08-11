<?php
// seeder.php - Standalone script to populate initial users

require_once __DIR__ . '/config/db.php';

try {
    $pdo = getDB();
    
    $usersToSeed = [
        [
            'name' => 'Admin User',
            'email' => 'eaimt@gmail.com',
            'password' => 'eaint123', // You can change this default password
            'role' => 'admin'
        ],
        [
            'name' => 'Cashier User',
            'email' => 'yoonnadilwin@gmail.com',
            'password' => 'yoonnadilwin1234567', // You can change this default password
            'role' => 'cashier'
        ],
        [
            'name' => 'Regular User',
            'email' => 'yar@gmail.com',
            'password' => 'yaryar', // You can change this default password
            'role' => 'customer' // The database uses 'customer' for regular users
        ]
    ];

    $inserted = 0;
    $skipped = 0;

    echo "<h2>Seeding Initial Users</h2>";

    foreach ($usersToSeed as $userData) {
        // Check if user already exists based on email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $userData['email']]);
        
        if ($stmt->fetch()) {
            echo "<p style='color: orange;'>Skipping: User with email <strong>{$userData['email']}</strong> already exists.</p>";
            $skipped++;
        } else {
            // Hash the password
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Insert new user
            $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
            $insertStmt->execute([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $hashedPassword,
                'role' => $userData['role']
            ]);
            echo "<p style='color: green;'>Success: Created {$userData['role']} account for <strong>{$userData['name']}</strong>.</p>";
            $inserted++;
        }
    }

    echo "<h3>Seeding Complete!</h3>";
    echo "<ul>";
    echo "<li><strong>Successfully Inserted:</strong> $inserted</li>";
    echo "<li><strong>Skipped (Already Existed):</strong> $skipped</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error during seeding:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
