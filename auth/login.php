<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? '/sweetheaven/admin/dashboard.php' : '/sweetheaven/user/index.php'));
    exit;
}

require_once __DIR__ . '/../config/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['cart']    = $_SESSION['cart'] ?? [];
            header('Location: ' . ($user['role'] === 'admin' ? '/sweetheaven/admin/dashboard.php' : '/sweetheaven/user/index.php'));
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Sweet Heaven Bakery</title>
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
            <p class="text-gray-500 mt-2 text-sm">Sign in to your account</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-sm p-8 border border-stone-100">

            <?php if ($error): ?>
            <div class="mb-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5" id="loginForm">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" id="email" required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="you@example.com"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:border-transparent transition text-sm bg-gray-50 focus:bg-white">
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-semibold text-gray-700">Password</label>
                    </div>
                    <div class="relative">
                        <input type="password" name="password" id="password" required
                            placeholder="••••••••"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:border-transparent transition text-sm bg-gray-50 focus:bg-white pr-12">
                        <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" id="loginBtn"
                    class="w-full bg-rose-500 hover:bg-rose-600 text-white font-semibold py-3.5 rounded-xl transition-all duration-200 shadow-sm shadow-rose-100 hover:shadow-rose-100 active:scale-[0.98] flex items-center justify-center gap-2">
                    <span>Sign In</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-500">
                    Don't have an account?
                    <a href="/sweetheaven/auth/register.php" class="text-rose-500 font-semibold hover:text-rose-600 hover:underline ml-1">Sign up free</a>
                </p>
            </div>

            <!-- Demo credentials -->
            <div class="mt-4 bg-rose-50 rounded-xl p-4 text-xs text-gray-500 border border-stone-100">
                <p class="font-semibold text-rose-500 mb-1">Demo Credentials:</p>
                <p>Admin: admin@sweetheaven.com / admin123</p>
                <p>Customer: customer@sweetheaven.com / customer123</p>
            </div>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">© <?= date('Y') ?> Sweet Heaven Bakery. All rights reserved.</p>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
