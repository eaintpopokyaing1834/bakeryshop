<?php
/**
 * One-time script to fix corrupted password hashes in the users table.
 * Visit: /sweetheaven/admin/fix_passwords.php
 * DELETE THIS FILE after running it.
 */
session_start();
require_once __DIR__ . '/../config/db.php';

$db = getDB();

// Fetch all users
$users = $db->query("SELECT id, name, email, password, role FROM users")->fetchAll();

echo "<h2>Password Hash Repair Tool</h2>";
echo "<p>Existing users and hash validity:</p>";
echo "<table border='1' cellpadding='8' cellspacing='0'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Hash Valid?</th><th>Action</th></tr>";

foreach ($users as $user) {
    $isValid = password_verify('admin123', $user['password']) || password_verify('customer123', $user['password']);
    // Also check if the hash format is valid (starts with $2y$)
    $hashFormatValid = substr($user['password'], 0, 4) === '$2y$';

    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>" . htmlspecialchars($user['name']) . "</td>";
    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
    echo "<td>{$user['role']}</td>";
    echo "<td>" . ($hashFormatValid ? 'YES' : '<strong style="color:red">NO — corrupted hash</strong>') . "</td>";
    echo "<td>";

    if (!$hashFormatValid) {
        // Determine default password based on role
        $defaultPass = ($user['role'] === 'admin') ? 'admin123' : 'customer123';
        $newHash = password_hash($defaultPass, PASSWORD_BCRYPT);

        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $user['id']]);

        echo "<span style='color:green'>Fixed! Default password: <code>{$defaultPass}</code></span>";
    } else {
        echo "—";
    }

    echo "</td></tr>";
}

echo "</table>";
echo "<br><p><strong style='color:red'>DELETE THIS FILE (fix_passwords.php) after use!</strong></p>";
echo "<p><a href='/sweetheaven/admin/dashboard.php'>Go to Dashboard</a></p>";
