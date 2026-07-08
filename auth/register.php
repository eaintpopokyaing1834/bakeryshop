<?php
session_start();
require_once __DIR__ . '/../includes/lang.php';
if (isset($_SESSION['user_id'])) {
    header('Location: /sweetheaven/user/index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
$db = getDB();

// Ensure role ENUM includes 'cashier'
try {
    $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','customer','cashier') DEFAULT 'customer'");
} catch (PDOException $e) {
    // ignore
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = __('register_err_empty');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('register_err_email');
    } elseif (strlen($password) < 6) {
        $error = __('register_err_short');
    } elseif ($password !== $confirm) {
        $error = __('register_err_match');
    } else {
        $db = getDB();
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = __('register_err_exists');
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt   = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
            $stmt->execute([$name, $email, $hashed]);

            $success = __('register_success');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('register_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-stone-50 via-rose-50 to-rose-50/50 flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="/sweetheaven/user/index.php" class="inline-flex items-center gap-3">
                <img src="/sweetheaven/images/shoplogo.png" class="h-12 w-auto" alt="Logo">
                <span class="text-3xl font-bold text-stone-800">Sweet Heaven</span>
            </a>
            <p class="text-gray-500 mt-2 text-sm"><?= __('register_subtitle') ?></p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-sm p-8 border border-stone-100">

            <?php if ($error): ?>
            <div class="mb-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <?= htmlspecialchars($success) ?>
                <a href="/sweetheaven/auth/login.php" class="underline font-semibold ml-1"><?= __('register_login_now') ?></a>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('register_name_label') ?></label>
                    <input type="text" name="name" required
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                        placeholder="<?= __('register_name_ph') ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:border-transparent transition text-sm bg-gray-50 focus:bg-white">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('register_email_label') ?></label>
                    <input type="email" name="email" required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="you@example.com"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:border-transparent transition text-sm bg-gray-50 focus:bg-white">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('register_pass_label') ?></label>
                    <input type="password" name="password" required
                        placeholder="<?= __('register_pass_ph') ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:border-transparent transition text-sm bg-gray-50 focus:bg-white">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('register_confirm_label') ?></label>
                    <input type="password" name="confirm_password" required
                        placeholder="<?= __('register_confirm_ph') ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:border-transparent transition text-sm bg-gray-50 focus:bg-white">
                </div>

                <button type="submit"
                    class="w-full bg-rose-500 hover:bg-rose-600 text-white font-semibold py-3.5 rounded-xl transition-all duration-200 shadow-sm shadow-rose-100 hover:shadow-rose-100 active:scale-[0.98]">
                    <?= __('register_btn') ?>
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-500">
                    <?= __('register_have_account') ?>
                    <a href="/sweetheaven/auth/login.php" class="text-rose-500 font-semibold hover:text-rose-600 hover:underline ml-1"><?= __('register_signin_link') ?></a>
                </p>
            </div>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6"><?= sprintf(__('footer_copyright'), date('Y')) ?></p>
    </div>
</body>
</html>
