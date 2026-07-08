<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$msg     = '';
$msgType = 'success';

// ── CREATE ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name    = trim($_POST['payment_name'] ?? '');
    $accName = trim($_POST['acc_name'] ?? '');
    $accNo   = trim($_POST['acc_no'] ?? '');

    if ($name && $accName && $accNo) {
        $db->prepare("INSERT INTO payment_methods (payment_name, acc_name, acc_no) VALUES (?, ?, ?)")
           ->execute([$name, $accName, $accNo]);
        $newId = $db->lastInsertId();

        if (!empty($_FILES['qr_image']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/payments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
            $tmp = $_FILES['qr_image']['tmp_name'][0];
            $ext = strtolower(pathinfo($_FILES['qr_image']['name'][0], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['qr_image']['error'][0] === UPLOAD_ERR_OK) {
                $filename = 'qr_' . $newId . '_' . time() . '.' . $ext;
                move_uploaded_file($tmp, $uploadDir . $filename);
                $db->prepare("UPDATE payment_methods SET qr_image=? WHERE id=?")
                   ->execute(['uploads/payments/' . $filename, $newId]);
            }
        }
        $msg = 'Payment method added successfully!';
    } else {
        $msg = 'Please fill in all required fields.';
        $msgType = 'error';
    }
}

// ── UPDATE ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $pmId    = (int)($_POST['payment_method_id'] ?? 0);
    $name    = trim($_POST['payment_name'] ?? '');
    $accName = trim($_POST['acc_name'] ?? '');
    $accNo   = trim($_POST['acc_no'] ?? '');

    if ($pmId && $accName && $accNo) {
        $db->prepare("UPDATE payment_methods SET payment_name=?, acc_name=?, acc_no=? WHERE id=?")
           ->execute([$name ?: 'Payment', $accName, $accNo, $pmId]);

        if (!empty($_FILES['qr_image']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/payments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
            $tmp = $_FILES['qr_image']['tmp_name'][0];
            $ext = strtolower(pathinfo($_FILES['qr_image']['name'][0], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['qr_image']['error'][0] === UPLOAD_ERR_OK) {
                $filename = 'qr_' . $pmId . '_' . time() . '.' . $ext;
                move_uploaded_file($tmp, $uploadDir . $filename);
                $db->prepare("UPDATE payment_methods SET qr_image=? WHERE id=?")
                   ->execute(['uploads/payments/' . $filename, $pmId]);
            }
        }
        $msg = 'Payment method updated successfully!';
    } else {
        $msg = 'Please fill in all required fields.';
        $msgType = 'error';
    }
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pmId = (int)($_POST['payment_method_id'] ?? 0);
    if ($pmId) {
        $row = $db->prepare("SELECT qr_image FROM payment_methods WHERE id=?");
        $row->execute([$pmId]);
        $qr = $row->fetchColumn();
        if ($qr && file_exists(__DIR__ . '/../' . $qr)) @unlink(__DIR__ . '/../' . $qr);
        $db->prepare("DELETE FROM payment_methods WHERE id=?")->execute([$pmId]);
        $msg = 'Payment method deleted.';
    }
}

$paymentMethods = $db->query("SELECT * FROM payment_methods ORDER BY id ASC")->fetchAll();
$pageTitle = 'Payment Settings';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($msg): ?>
<div class="mb-6 px-5 py-3 rounded-xl text-sm font-medium <?= ($msgType ?? 'success') === 'error' ? 'bg-amber-50 border border-amber-200 text-amber-700' : 'bg-emerald-50 border border-emerald-200 text-emerald-700' ?>">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Header Row -->
<div class="flex items-center justify-between mb-6 px-4">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Payment Methods</h2>
        <p class="text-sm text-gray-400 mt-0.5"><?= count($paymentMethods) ?> method<?= count($paymentMethods) !== 1 ? 's' : '' ?> configured</p>
    </div>
    <button onclick="openAddModal()"
        class="flex items-center gap-2 bg-rose-500 hover:bg-rose-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-all shadow-sm hover:shadow-md active:scale-95">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add New Payment
    </button>
</div>

<!-- Payment Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-4 pb-8">
    <?php foreach ($paymentMethods as $pm): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">

        <!-- Card Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50/60">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center text-rose-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($pm['payment_name']) ?></h3>
            </div>
            <form method="POST" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($pm['payment_name'])) ?>? This cannot be undone.');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="payment_method_id" value="<?= $pm['id'] ?>">
                <button type="submit" title="Delete"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </form>
        </div>

        <!-- Update Form -->
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="payment_method_id" value="<?= $pm['id'] ?>">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Method Name</label>
                <input type="text" name="payment_name" required
                    value="<?= htmlspecialchars($pm['payment_name'] ?? '') ?>"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Account Name</label>
                    <input type="text" name="acc_name" required
                        value="<?= htmlspecialchars($pm['acc_name'] ?? '') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input type="number" name="acc_no" required
                        value="<?= htmlspecialchars($pm['acc_no'] ?? '') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">QR Code Image</label>
                <?php if (!empty($pm['qr_image'])): ?>
                <div class="mb-3 flex items-center gap-4">
                    <img src="/sweetheaven/<?= htmlspecialchars($pm['qr_image']) ?>"
                        class="w-24 h-24 object-contain border border-gray-200 rounded-xl bg-gray-50" alt="QR">
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Current QR code</p>
                        <p class="text-xs text-gray-400 mt-0.5">Upload new file to replace</p>
                    </div>
                </div>
                <?php endif; ?>
                <input type="file" name="qr_image[]" accept="image/jpeg,image/png,image/webp"
                    class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100">
                <p class="text-xs text-gray-400 mt-1">Allowed: JPG, JPEG, PNG, WEBP</p>
            </div>

            <button type="submit"
                class="w-full bg-rose-500 hover:bg-rose-600 text-white font-semibold px-6 py-2.5 rounded-xl text-sm transition-colors shadow-sm hover:shadow-md active:scale-[0.98]">
                Save Changes
            </button>
        </form>
    </div>
    <?php endforeach; ?>

    <?php if (empty($paymentMethods)): ?>
    <div class="md:col-span-2 text-center py-16 text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-4 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
        </svg>
        <p class="font-semibold text-gray-500">No payment methods yet.</p>
        <p class="text-sm mt-1">Click <strong>Add New Payment</strong> to get started.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Add New Payment Modal -->
<div id="addPaymentModal"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" onclick="event.stopPropagation()">

        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
            <div>
                <h3 class="font-bold text-gray-800 text-lg">Add New Payment Method</h3>
                <p class="text-sm text-gray-400 mt-0.5">Fill in the details below</p>
            </div>
            <button onclick="closeAddModal()"
                class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Method Name <span class="text-rose-500">*</span></label>
                <input type="text" name="payment_name" required placeholder="e.g. KBZ Pay, AYA Pay…"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Account Name <span class="text-rose-500">*</span></label>
                <input type="text" name="acc_name" required placeholder="e.g. Sweet Heaven Bakery"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Phone Number <span class="text-rose-500">*</span></label>
                <input type="text" name="acc_no" required placeholder="e.g. 09 1234 56789"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">QR Code Image</label>
                <input type="file" name="qr_image[]" accept="image/jpeg,image/png,image/webp"
                    class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100">
                <p class="text-xs text-gray-400 mt-1">Optional — Allowed: JPG, JPEG, PNG, WEBP</p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeAddModal()"
                    class="flex-1 px-6 py-2.5 rounded-xl border border-gray-200 text-gray-600 font-semibold text-sm hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="flex-1 bg-rose-500 hover:bg-rose-600 text-white font-semibold px-6 py-2.5 rounded-xl text-sm transition-colors shadow-sm hover:shadow-md active:scale-[0.98]">
                    Add Payment Method
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
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
