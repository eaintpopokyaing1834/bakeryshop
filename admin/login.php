<?php
session_start();
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin' || $role === 'cashier') {
        header('Location: /sweetheaven/admin/dashboard.php');
    } else {
        header('Location: /sweetheaven/user/index.php');
    }
    exit;
}

$db = getDB();
try {
    $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','customer','cashier') DEFAULT 'customer'");
} catch (PDOException $e) {
}
try {
    $db->exec("ALTER TABLE users ADD COLUMN status ENUM('active','inactive') DEFAULT 'active' AFTER role");
} catch (PDOException $e) {
}

$error = '';
$active_role = $_POST['role'] ?? $_GET['role'] ?? 'admin'; // 'admin' or 'cashier'
$active_role = in_array($active_role, ['admin', 'cashier']) ? $active_role : 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_req = $_POST['role'] ?? 'admin';

    if (empty($email) || empty($password)) {
        $error = __('login_err_empty');
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (($user['status'] ?? 'active') === 'inactive') {
                $error = 'Your account has been suspended.';
            } elseif ($user['role'] !== $role_req) {
                $label = ucfirst($role_req);
                $error = "Access denied. This login is for {$label}s only.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                header('Location: /sweetheaven/admin/dashboard.php');
                exit;
            }
        } else {
            $error = __('login_err_invalid');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — Sweet Heaven</title>
    <!-- Tailwind CSS only — no custom CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    backdropBlur: { '3xl': '48px' },
                    colors: {
                        rose: { DEFAULT: '#f43f5e' },
                        amber: { DEFAULT: '#f59e0b' },
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        /* Only font override — Tailwind doesn't ship Poppins */
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="min-h-screen overflow-hidden">

    <!-- ── Full-screen crisp background (NO blur, NO tint overlay) ─── -->
    <div class="fixed inset-0 z-0">
        <img src="/sweetheaven/images/cashier4cake.jpg" alt="" class="w-full h-full object-cover" aria-hidden="true">
    </div>

    <!-- ── Page content ─────────────────────────────────────────────── -->
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">

        <!--
            Glassmorphism card:
            • bg-white/10  → very low opacity white — image shows through clearly
            • backdrop-blur-3xl → heavy frosted effect on the glass layer only
            • border-white/25  → subtle white edge for the "glass rim"
            • shadow-2xl       → deep soft shadow
        -->
        <div class="w-full max-w-md
                    bg-white/10
                    backdrop-blur-3xl
                    border border-white/25
                    rounded-3xl
                    shadow-2xl
                    overflow-hidden">

            <!-- ── Top: branding strip ──────────────────────────────── -->
            <div class="px-8 pt-8 pb-4 flex flex-col items-center">
                <img src="/sweetheaven/images/9102671.png" alt="Sweet Heaven" class="h-14 w-auto mb-3 drop-shadow-lg">
                <h1 class="text-2xl font-bold text-white tracking-tight drop-shadow">Sweet Heaven</h1>
                <p class="text-white/60 text-xs font-light mt-0.5">Staff Portal</p>
            </div>

            <!-- ── Role toggle tabs ─────────────────────────────────── -->
            <div class="px-8 pt-2 pb-5">
                <div class="flex rounded-xl bg-white/10 border border-white/15 p-1 gap-1" id="roleTabs">

                    <!-- Admin tab -->
                    <button type="button" id="tab-admin" onclick="switchRole('admin')"
                        class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all duration-250
                               <?= $active_role === 'admin' ? 'bg-rose-500 text-white shadow-lg shadow-rose-500/30' : 'text-white/60 hover:text-white hover:bg-white/10' ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Admin
                    </button>

                    <!-- Cashier tab -->
                    <button type="button" id="tab-cashier" onclick="switchRole('cashier')"
                        class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all duration-250
                               <?= $active_role === 'cashier' ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/30' : 'text-white/60 hover:text-white hover:bg-white/10' ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Cashier
                    </button>

                </div>
            </div>

            <!-- ── Form area ────────────────────────────────────────── -->
            <div class="px-8 pb-8">

                <?php if ($error): ?>
                    <div class="mb-5 flex items-start gap-3
                            bg-red-500/15 border border-red-400/30
                            text-red-200 rounded-xl px-4 py-3 text-sm">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <!-- Hidden role field — updated by JS when tab switches -->
                    <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($active_role) ?>">

                    <!-- Email -->
                    <div class="mb-4">
                        <label for="email"
                            class="block text-xs font-semibold text-white/70 uppercase tracking-wider mb-1.5">
                            Email Address
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-white/40">
                                <svg class="w-4.5 h-4.5 w-[18px] h-[18px]" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <input type="email" name="email" id="email" required
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" id-placeholder="admin"
                                placeholder="Enter your email" class="w-full pl-10 pr-4 py-3
                                          bg-white/10 border border-white/20
                                          rounded-xl text-white text-sm
                                          placeholder-white/35
                                          focus:outline-none focus:ring-2 focus:ring-white/40 focus:border-white/50
                                          transition duration-200">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password"
                                class="block text-xs font-semibold text-white/70 uppercase tracking-wider">
                                Password
                            </label>
                            <!-- <a href="#" class="text-xs text-white/50 hover:text-white transition-colors duration-150 underline underline-offset-2">
                                Forgot password?
                            </a> -->
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-white/40">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input type="password" name="password" id="password" required placeholder="••••••••" class="w-full pl-10 pr-12 py-3
                                          bg-white/10 border border-white/20
                                          rounded-xl text-white text-sm
                                          placeholder-white/35
                                          focus:outline-none focus:ring-2 focus:ring-white/40 focus:border-white/50
                                          transition duration-200">
                            <!-- Show/hide toggle -->
                            <button type="button" id="pwToggle" onclick="togglePassword()"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 transition-colors">
                                <svg id="eyeShow" class="w-[18px] h-[18px]" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg id="eyeHide" class="w-[18px] h-[18px] hidden" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Remember me -->
                    <!-- <div class="flex items-center gap-2.5 mb-6">
                        <input type="checkbox" name="remember" id="remember"
                               class="w-4 h-4 rounded border-white/30 bg-white/10
                                      accent-rose-500 cursor-pointer">
                        <label for="remember" class="text-sm text-white/60 cursor-pointer select-none">
                            Remember me
                        </label>
                    </div> -->

                    <!-- Submit button — color changes with role -->
                    <button type="submit" id="submitBtn" class="w-full py-3.5 rounded-xl font-semibold text-sm text-white
                                   flex items-center justify-center gap-2
                                   transition-all duration-250 active:scale-[0.98]
                                   <?= $active_role === 'admin'
                                       ? 'bg-rose-500 hover:bg-rose-400 shadow-lg shadow-rose-500/40'
                                       : 'bg-amber-500 hover:bg-amber-400 shadow-lg shadow-amber-500/40' ?>">
                        <span
                            id="btnLabel"><?= $active_role === 'admin' ? 'Sign In as Admin' : 'Sign In as Cashier' ?></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </button>
                </form>

            </div>

            <!-- ── Bottom divider + copyright ──────────────────────── -->
            <div class="border-t border-white/10 px-8 py-4 text-center">
                <p class="text-xs text-white/30">&copy; <?= date('Y') ?> Sweet Heaven Bakery. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        const state = {
            role: '<?= $active_role ?>',
            adminClasses: { btn: ['bg-rose-500', 'hover:bg-rose-400', 'shadow-rose-500/40'], badge: ['bg-rose-500', 'shadow-rose-500/30'] },
            cashierClasses: { btn: ['bg-amber-500', 'hover:bg-amber-400', 'shadow-amber-500/40'], badge: ['bg-amber-500', 'shadow-amber-500/30'] },
        };

        function switchRole(role) {
            state.role = role;

            // Update hidden input
            document.getElementById('roleInput').value = role;

            const tabAdmin = document.getElementById('tab-admin');
            const tabCashier = document.getElementById('tab-cashier');
            const btn = document.getElementById('submitBtn');
            const label = document.getElementById('btnLabel');

            // ── Admin tab ────────────────────────────────────────────
            if (role === 'admin') {
                tabAdmin.className = tabAdmin.className
                    .replace('text-white/60 hover:text-white hover:bg-white/10', '')
                    + ' bg-rose-500 text-white shadow-lg shadow-rose-500/30';
                tabCashier.className = tabCashier.className
                    .replace('bg-amber-500 text-white shadow-lg shadow-amber-500/30', '')
                    + ' text-white/60 hover:text-white hover:bg-white/10';

                btn.className = btn.className
                    .replace('bg-amber-500 hover:bg-amber-400 shadow-amber-500/40', '')
                    + ' bg-rose-500 hover:bg-rose-400 shadow-rose-500/40';

                label.textContent = 'Sign In as Admin';
            } else {
                // ── Cashier tab ──────────────────────────────────────
                tabCashier.className = tabCashier.className
                    .replace('text-white/60 hover:text-white hover:bg-white/10', '')
                    + ' bg-amber-500 text-white shadow-lg shadow-amber-500/30';
                tabAdmin.className = tabAdmin.className
                    .replace('bg-rose-500 text-white shadow-lg shadow-rose-500/30', '')
                    + ' text-white/60 hover:text-white hover:bg-white/10';

                btn.className = btn.className
                    .replace('bg-rose-500 hover:bg-rose-400 shadow-rose-500/40', '')
                    + ' bg-amber-500 hover:bg-amber-400 shadow-amber-500/40';

                label.textContent = 'Sign In as Cashier';
            }
        }

        function togglePassword() {
            const input = document.getElementById('password');
            const eyeShow = document.getElementById('eyeShow');
            const eyeHide = document.getElementById('eyeHide');
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            eyeShow.classList.toggle('hidden', !showing);
            eyeHide.classList.toggle('hidden', showing);
        }
    </script>
</body>

</html>