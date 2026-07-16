<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();
$userId = (int)$_SESSION['user_id'];

// Fetch current user data
$user = $db->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$userId]);
$user = $user->fetch();

if (!$user) {
    header('Location: /sweetheaven/auth/logout.php');
    exit;
}

$profileMsg = $profileError = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $passNew = $_POST['new_password'] ?? '';
    $passConf = $_POST['confirm_password'] ?? '';
    $passCur = $_POST['current_password'] ?? '';

    if (empty($name) || empty($email)) {
        $profileError = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileError = 'Invalid email address.';
    } else {
        // Check email uniqueness (exclude self)
        $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            $profileError = 'Email is already taken by another user.';
        } elseif ($passNew && !password_verify($passCur, $user['password'])) {
            $profileError = 'Current password is incorrect.';
        } elseif ($passNew && strlen($passNew) < 6) {
            $profileError = 'New password must be at least 6 characters.';
        } elseif ($passNew && $passNew !== $passConf) {
            $profileError = 'New password and confirmation do not match.';
        } else {
            if ($passNew) {
                $hashed = password_hash($passNew, PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET name=?, email=?, password=? WHERE id=?")
                   ->execute([$name, $email, $hashed, $userId]);
            } else {
                $db->prepare("UPDATE users SET name=?, email=? WHERE id=?")
                   ->execute([$name, $email, $userId]);
            }
            $_SESSION['name'] = $name;
            $profileMsg = 'Profile updated successfully!';
            // Refresh user data
            $user['name'] = $name;
            $user['email'] = $email;
        }
    }
}

$pageTitle = 'Edit Profile';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-800"><?= __('profile_edit_heading') ?></h3>
            <p class="text-sm text-gray-400 mt-1"><?= __('profile_edit_subtitle') ?></p>
        </div>

        <?php if ($profileMsg): ?>
            <div class="mx-6 mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <?= htmlspecialchars($profileMsg) ?>
            </div>
        <?php endif; ?>

        <?php if ($profileError): ?>
            <div class="mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                <?= htmlspecialchars($profileError) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="px-6 py-5 space-y-4">
            <input type="hidden" name="update_profile" value="1">

            <!-- Avatar -->
            <div class="flex items-center gap-4 mb-2">
                <div class="w-16 h-16 rounded-full bg-pink-500 flex items-center justify-center text-white text-2xl font-bold shrink-0">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($user['name']) ?></p>
                    <p class="text-sm text-gray-400"><?= ucfirst($_SESSION['role']) ?></p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('profile_edit_label_name') ?></label>
                <input type="text" name="name" required value="<?= htmlspecialchars($user['name']) ?>"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('profile_edit_label_email') ?></label>
                <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
            </div>

            <div class="border-t border-gray-100 pt-4 mt-4">
                <p class="text-sm font-semibold text-gray-700 mb-3"><?= __('profile_edit_change_pass') ?> <span class="font-normal text-gray-400">(<?= __('profile_edit_hint') ?>)</span></p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('profile_edit_cur_pass') ?></label>
                <input type="password" name="current_password" placeholder="<?= __('profile_edit_ph_cur_pass') ?>"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('profile_edit_new_pass') ?></label>
                <input type="password" name="new_password" minlength="6" placeholder="<?= __('profile_edit_ph_new_pass') ?>"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('profile_edit_confirm_pass') ?></label>
                <input type="password" name="confirm_password" minlength="6" placeholder="<?= __('profile_edit_ph_confirm_pass') ?>"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="flex-1 px-4 py-2.5 rounded-xl bg-rose-500 text-white text-sm font-semibold hover:bg-rose-600 transition-colors">
                    <?= __('profile_edit_save') ?>
                </button>
                <a href="/sweetheaven/admin/dashboard.php"
                    class="px-6 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors text-center">
                    <?= __('profile_edit_cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
