<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lang.php';
$db = getDB();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// ── Flash message helpers ─────────────────────────────────────────────────────
function setFlash($msg, $type = 'success') {
    $_SESSION['payment_flash'] = ['msg' => $msg, 'type' => $type];
}
function getFlash() {
    if (!empty($_SESSION['payment_flash'])) {
        $flash = $_SESSION['payment_flash'];
        unset($_SESSION['payment_flash']);
        return $flash;
    }
    return null;
}

// ── Block cashiers from any write actions ─────────────────────────────────────
if (!$isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    setFlash('Cashiers do not have permission to modify payment methods.', 'error');
    header('Location: /sweetheaven/admin/payment_settings.php');
    exit;
}

// ── Ensure uploads directory exists ───────────────────────────────────────────
$uploadDir = __DIR__ . '/../uploads/payments/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

// ── Auto-add logo_image column if missing ─────────────────────────────────────
$hasLogoCol = (bool) $db->query("SHOW COLUMNS FROM payment_methods LIKE 'logo_image'")->fetch();
if (!$hasLogoCol) {
    try {
        $db->exec("ALTER TABLE payment_methods ADD COLUMN logo_image VARCHAR(255) DEFAULT NULL AFTER acc_no");
        $hasLogoCol = true;
    } catch (Exception $e) {
        $hasLogoCol = false;
    }
}

// ── Helper: upload an image file ──────────────────────────────────────────────
function uploadImage($fileInput, $recordId, $prefix) {
    if (empty($fileInput['name'][0])) return null;

    $uploadDir = __DIR__ . '/../uploads/payments/';
    $tmp  = $fileInput['tmp_name'][0];
    $name = $fileInput['name'][0];
    $err  = $fileInput['error'][0];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($err !== UPLOAD_ERR_OK) return null;
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) return null;

    $filename = $prefix . '_' . $recordId . '_' . time() . '.' . $ext;
    if (move_uploaded_file($tmp, $uploadDir . $filename)) {
        return 'uploads/payments/' . $filename;
    }
    return null;
}

// ── Helper: delete an image file ──────────────────────────────────────────────
function deleteImageFile($path) {
    if ($path && file_exists(__DIR__ . '/../' . $path)) {
        @unlink(__DIR__ . '/../' . $path);
    }
}

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name    = trim($_POST['payment_name'] ?? '');
    $accName = trim($_POST['acc_name'] ?? '');
    $accNo   = trim($_POST['acc_no'] ?? '');

    if ($name && $accName && $accNo) {
        $db->prepare("INSERT INTO payment_methods (payment_name, acc_name, acc_no) VALUES (?, ?, ?)")
           ->execute([$name, $accName, $accNo]);
        $newId = $db->lastInsertId();

        // Upload logo
        $logoPath = uploadImage($_FILES['logo_image'] ?? [], $newId, 'logo');
        if ($logoPath) {
            $db->prepare("UPDATE payment_methods SET logo_image=? WHERE id=?")
               ->execute([$logoPath, $newId]);
        }

        // Upload QR code
        $qrPath = uploadImage($_FILES['qr_image'] ?? [], $newId, 'qr');
        if ($qrPath) {
            $db->prepare("UPDATE payment_methods SET qr_image=? WHERE id=?")
               ->execute([$qrPath, $newId]);
        }

        setFlash('Payment method added successfully!');
    } else {
        setFlash('Please fill in all required fields.', 'error');
    }

    header('Location: /sweetheaven/admin/payment_settings.php');
    exit;
}

// ── UPDATE ────────────────────────────────────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $pmId    = (int)($_POST['payment_method_id'] ?? 0);
    $name    = trim($_POST['payment_name'] ?? '');
    $accName = trim($_POST['acc_name'] ?? '');
    $accNo   = trim($_POST['acc_no'] ?? '');

    if ($pmId && $accName && $accNo) {
        $db->prepare("UPDATE payment_methods SET payment_name=?, acc_name=?, acc_no=? WHERE id=?")
           ->execute([$name ?: 'Payment', $accName, $accNo, $pmId]);

        // Upload new logo (delete old one first)
        $logoPath = uploadImage($_FILES['logo_image'] ?? [], $pmId, 'logo');
        if ($logoPath) {
            $old = $db->prepare("SELECT logo_image FROM payment_methods WHERE id=?");
            $old->execute([$pmId]);
            deleteImageFile($old->fetchColumn());
            $db->prepare("UPDATE payment_methods SET logo_image=? WHERE id=?")
               ->execute([$logoPath, $pmId]);
        }

        // Upload new QR code (delete old one first)
        $qrPath = uploadImage($_FILES['qr_image'] ?? [], $pmId, 'qr');
        if ($qrPath) {
            $old = $db->prepare("SELECT qr_image FROM payment_methods WHERE id=?");
            $old->execute([$pmId]);
            deleteImageFile($old->fetchColumn());
            $db->prepare("UPDATE payment_methods SET qr_image=? WHERE id=?")
               ->execute([$qrPath, $pmId]);
        }

        setFlash('Payment method updated successfully!');
    } else {
        setFlash('Please fill in all required fields.', 'error');
    }

    header('Location: /sweetheaven/admin/payment_settings.php');
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pmId = (int)($_POST['payment_method_id'] ?? 0);
    if ($pmId) {
        $row = $db->prepare("SELECT logo_image, qr_image FROM payment_methods WHERE id=?");
        $row->execute([$pmId]);
        $files = $row->fetch();
        if ($files) {
            if (!empty($files['logo_image'])) deleteImageFile($files['logo_image']);
            if (!empty($files['qr_image'])) deleteImageFile($files['qr_image']);
        }
        $db->prepare("DELETE FROM payment_methods WHERE id=?")->execute([$pmId]);
        setFlash('Payment method deleted.');
    }

    header('Location: /sweetheaven/admin/payment_settings.php');
    exit;
}

// ── READ ──────────────────────────────────────────────────────────────────────
$paymentMethods = $db->query("SELECT * FROM payment_methods ORDER BY id ASC")->fetchAll();
$flash = getFlash();
$pageTitle = __('payment_page_title');
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($flash): ?>
<div class="mb-6 px-5 py-3 rounded-xl text-sm font-medium <?= $flash['type'] === 'error' ? 'bg-amber-50 border border-amber-200 text-amber-700' : 'bg-emerald-50 border border-emerald-200 text-emerald-700' ?>">
    <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<!-- Header Row -->
<div class="flex items-center justify-between mb-6 px-4">
    <div>
        <h2 class="text-xl font-bold text-gray-800"><?= __('payment_heading') ?></h2>
        <p class="text-sm text-gray-400 mt-0.5"><?= sprintf(__('payment_methods_count'), localizeNumber(count($paymentMethods))) ?></p>
    </div>
    <?php if ($isAdmin): ?>
    <button onclick="openAddModal()"
        class="flex items-center gap-2 bg-rose-500 hover:bg-rose-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-all shadow-sm hover:shadow-md active:scale-95">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <?= __('payment_add') ?>
    </button>
    <?php endif; ?>
</div>

<!-- Payment Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-4 pb-8">
    <?php foreach ($paymentMethods as $pm): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">

        <!-- Card Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50/60">
            <div class="flex items-center gap-3">
                <?php
                $methodName = strtolower($pm['payment_name'] ?? '');
                ?>
                <?php if (!empty($pm['logo_image'])): ?>
                    <img src="/sweetheaven/<?= htmlspecialchars($pm['logo_image']) ?>"
                         class="w-10 h-10 rounded-xl object-contain bg-gray-100" alt="Logo">
                <?php elseif (strpos($methodName, 'kbz') !== false && file_exists(__DIR__ . '/../images/kbz.png')): ?>
                    <img src="../images/kbz.png" class="w-10 h-10 rounded-xl object-contain bg-gray-100" alt="KBZ Pay">
                <?php elseif (strpos($methodName, 'wave') !== false && file_exists(__DIR__ . '/../images/wave.png')): ?>
                    <img src="../images/wave.png" class="w-10 h-10 rounded-xl object-contain bg-gray-100" alt="Wave Pay">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center text-rose-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                <?php endif; ?>
                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($pm['payment_name']) ?></h3>
            </div>
            <?php if ($isAdmin): ?>
            <form method="POST" onsubmit="return confirm('<?= sprintf(__('admin_confirm_delete'), htmlspecialchars(addslashes($pm['payment_name']))) ?>');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="payment_method_id" value="<?= $pm['id'] ?>">
                <button type="submit" title="<?= __('admin_delete_tooltip') ?>"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                    <img src="../images/delete1.png" class="w-5 h-5">
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Payment Details -->
        <?php if ($isAdmin): ?>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4" onsubmit="disableSubmit(this)">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="payment_method_id" value="<?= $pm['id'] ?>">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_method') ?></label>
                <input type="text" name="payment_name" required
                    value="<?= htmlspecialchars($pm['payment_name'] ?? '') ?>"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_account') ?></label>
                    <input type="text" name="acc_name" required
                        value="<?= htmlspecialchars($pm['acc_name'] ?? '') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_phone') ?></label>
                    <input type="number" name="acc_no" required
                        value="<?= htmlspecialchars($pm['acc_no'] ?? '') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
            </div>

            <!-- Logo Upload -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_logo') ?></label>
                <?php if (!empty($pm['logo_image'])): ?>
                <div class="mb-3 flex items-center gap-4">
                    <img src="/sweetheaven/<?= htmlspecialchars($pm['logo_image']) ?>"
                        class="w-16 h-16 object-contain border border-gray-200 rounded-xl bg-gray-50" alt="Logo">
                    <div>
                        <p class="text-xs text-gray-500 font-medium"><?= __('payment_logo_current') ?></p>
                        <p class="text-xs text-gray-400 mt-0.5"><?= __('payment_qr_upload') ?></p>
                    </div>
                </div>
                <?php endif; ?>
                <input type="file" name="logo_image[]" accept="image/jpeg,image/png,image/webp"
                    class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100">
                <p class="text-xs text-gray-400 mt-1"><?= __('payment_logo_optional') ?></p>
            </div>

            <!-- QR Code Upload -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_qr') ?></label>
                <?php if (!empty($pm['qr_image'])): ?>
                <div class="mb-3 flex items-center gap-4">
                    <img src="/sweetheaven/<?= htmlspecialchars($pm['qr_image']) ?>"
                        class="w-24 h-24 object-contain border border-gray-200 rounded-xl bg-gray-50" alt="QR">
                    <div>
                        <p class="text-xs text-gray-500 font-medium"><?= __('payment_qr_current') ?></p>
                        <p class="text-xs text-gray-400 mt-0.5"><?= __('payment_qr_upload') ?></p>
                    </div>
                </div>
                <?php endif; ?>
                <input type="file" name="qr_image[]" accept="image/jpeg,image/png,image/webp"
                    class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100">
                <p class="text-xs text-gray-400 mt-1"><?= __('payment_allowed') ?></p>
            </div>

            <button type="submit"
                class="submit-btn w-full bg-rose-500 hover:bg-rose-600 text-white font-semibold px-6 py-2.5 rounded-xl text-sm transition-colors shadow-sm hover:shadow-md active:scale-[0.98]">
                <?= __('payment_save') ?>
            </button>
        </form>
        <?php else: ?>
        <!-- Cashier / Read-only view -->
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_method') ?></label>
                <p class="text-sm text-gray-700"><?= htmlspecialchars($pm['payment_name'] ?? '') ?></p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_account') ?></label>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($pm['acc_name'] ?? '') ?></p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_phone') ?></label>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($pm['acc_no'] ?? '') ?></p>
                </div>
            </div>
            <?php if (!empty($pm['qr_image'])): ?>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_qr') ?></label>
                <img src="/sweetheaven/<?= htmlspecialchars($pm['qr_image']) ?>"
                    class="w-24 h-24 object-contain border border-gray-200 rounded-xl bg-gray-50" alt="QR">
            </div>
            <?php endif; ?>
            <p class="text-xs text-gray-400 italic"><?= __('product_view_only') ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($paymentMethods)): ?>
    <div class="md:col-span-2 text-center py-16 text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-4 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
        </svg>
        <p class="font-semibold text-gray-500"><?= __('payment_no_methods') ?></p>
        <p class="text-sm mt-1"><?= __('payment_no_methods_hint') ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Add New Payment Modal -->
<div id="addPaymentModal"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" onclick="event.stopPropagation()">

        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
            <div>
                <h3 class="font-bold text-gray-800 text-lg"><?= __('payment_add_title') ?></h3>
                <p class="text-sm text-gray-400 mt-0.5"><?= __('payment_add_subtitle') ?></p>
            </div>
            <button onclick="closeAddModal()"
                class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4" onsubmit="disableSubmit(this)">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_method') ?> <span class="text-rose-500">*</span></label>
                <input type="text" name="payment_name" required placeholder="<?= __('payment_ph_method') ?>"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_account') ?> <span class="text-rose-500">*</span></label>
                <input type="text" name="acc_name" required placeholder="<?= __('payment_ph_account') ?>"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_phone') ?> <span class="text-rose-500">*</span></label>
                <input type="text" name="acc_no" required placeholder="<?= __('payment_ph_phone') ?>"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>

            <!-- Logo Upload -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_logo') ?></label>
                <input type="file" name="logo_image[]" accept="image/jpeg,image/png,image/webp"
                    class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100">
                <p class="text-xs text-gray-400 mt-1"><?= __('payment_logo_optional') ?></p>
            </div>

            <!-- QR Code Upload -->
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5"><?= __('payment_label_qr') ?></label>
                <input type="file" name="qr_image[]" accept="image/jpeg,image/png,image/webp"
                    class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100">
                <p class="text-xs text-gray-400 mt-1"><?= __('payment_qr_optional') ?></p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeAddModal()"
                    class="flex-1 px-6 py-2.5 rounded-xl border border-gray-200 text-gray-600 font-semibold text-sm hover:bg-gray-50 transition-colors">
                    <?= __('admin_cancel') ?>
                </button>
                <button type="submit"
                    class="submit-btn flex-1 bg-rose-500 hover:bg-rose-600 text-white font-semibold px-6 py-2.5 rounded-xl text-sm transition-colors shadow-sm hover:shadow-md active:scale-[0.98]">
                    <?= __('payment_btn_add') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addPaymentModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeAddModal() {
    document.getElementById('addPaymentModal').classList.add('hidden');
    document.body.style.overflow = '';
}
document.getElementById('addPaymentModal').addEventListener('click', closeAddModal);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAddModal(); });

// Disable submit button after click to prevent duplicate submissions
function disableSubmit(form) {
    const btn = form.querySelector('.submit-btn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving...';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
