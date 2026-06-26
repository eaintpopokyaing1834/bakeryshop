<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$msg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pmId = (int) ($_POST['payment_method_id'] ?? 0);
    $accName = trim($_POST['acc_name'] ?? '');
    $accNo = trim($_POST['acc_no'] ?? '');

    if ($pmId && $accName && $accNo) {
        $db->prepare("UPDATE payment_methods SET acc_name=?, acc_no=? WHERE id=?")
            ->execute([$accName, $accNo, $pmId]);

        // Handle QR upload
        if (!empty($_FILES['qr_image']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/payments/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0775, true);

            foreach ($_FILES['qr_image']['tmp_name'] as $i => $tmp) {
                if ($_FILES['qr_image']['error'][$i] !== UPLOAD_ERR_OK)
                    continue;
                $ext = strtolower(pathinfo($_FILES['qr_image']['name'][$i], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $allowed))
                    continue;
                $filename = 'qr_' . $pmId . '_' . time() . '.' . $ext;
                move_uploaded_file($tmp, $uploadDir . $filename);
                $db->prepare("UPDATE payment_methods SET qr_image=? WHERE id=?")
                    ->execute(['uploads/payments/' . $filename, $pmId]);
            }
        }

        $msg = 'Payment method updated successfully!';
    } else {
        $msg = 'Please fill in all required fields.';
    }
}

$paymentMethods = $db->query("SELECT * FROM payment_methods")->fetchAll();
$pageTitle = 'Payment Settings';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($msg): ?>
    <div
        class="mb-6 px-5 py-3 rounded-xl text-sm font-medium <?= strpos($msg, 'success') ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-amber-50 border border-amber-200 text-amber-700' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<div class="space-y-6">
    <?php foreach ($paymentMethods as $pm): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-bold text-gray-800 text-lg mb-5"><?= htmlspecialchars($pm['payment_name']) ?></h3>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="payment_method_id" value="<?= $pm['id'] ?>">

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Account Name</label>
                        <input type="text" name="acc_name" required value="<?= htmlspecialchars($pm['acc_name'] ?? '') ?>"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                        <input type="text" name="acc_no" required value="<?= htmlspecialchars($pm['acc_no'] ?? '') ?>"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">QR Code Image</label>
                    <?php if (!empty($pm['qr_image'])): ?>
                        <div class="mb-3">
                            <img src="/sweetheaven/<?= htmlspecialchars($pm['qr_image']) ?>"
                                class="w-32 h-32 object-contain border border-gray-200 rounded-xl" alt="Current QR">
                            <p class="text-xs text-gray-400 mt-1">Current QR code</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="qr_image[]" accept="image/jpeg,image/png,image/webp"
                        class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100">
                    <p class="text-xs text-gray-400 mt-1">Allowed: JPG, JPEG, PNG, WEBP</p>
                </div>

                <button type="submit"
                    class="bg-rose-500 hover:bg-rose-600 text-white font-semibold px-6 py-2.5 rounded-xl text-sm transition-colors">
                    Update <?= htmlspecialchars($pm['payment_name']) ?>
                </button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>