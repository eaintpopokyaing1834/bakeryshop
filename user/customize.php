<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../middleware/customer_check.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();

$success = '';
$error = '';

// Earliest allowed delivery date (today + lead time)
$minDate = date('Y-m-d', strtotime('+' . CUSTOMIZE_LEAD_DAYS . ' days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $size = trim($_POST['size'] ?? '');
    $flavor = trim($_POST['flavor'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $cakeMessage = trim($_POST['cake_message'] ?? '');
    $deliveryDate = trim($_POST['delivery_date'] ?? '');
    $additionalNotes = trim($_POST['additional_notes'] ?? '');

    if (!$size || !$flavor || !$deliveryDate) {
        $error = __('customize_err_fields');
    } elseif (($size === 'Custom' || $flavor === 'Custom' || $color === 'Custom') && empty($additionalNotes)) {
        $error = __('customize_custom_note_required');
    } elseif ($deliveryDate < $minDate) {
        $error = sprintf(__('customize_err_date'), CUSTOMIZE_LEAD_DAYS);
    } else {
        // ── Server-side guard: block if user has 3 or more active requests ──────────────
        $activeCheck = $db->prepare("SELECT COUNT(*) FROM customize_requests WHERE user_id=? AND status IN ('pending','approved')");
        $activeCheck->execute([$_SESSION['user_id']]);
        if ($activeCheck->fetchColumn() >= 3) {
            $error = 'You can only have up to 3 active customize requests at a time. Please wait for them to be processed.';
        } else {
            $referenceImage = null;
            if (!empty($_FILES['reference_image']['tmp_name'])) {
                $file = $_FILES['reference_image'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $allowed)) {
                    $error = __('customize_err_filetype');
                } else {
                    $uploadDir = __DIR__ . '/../uploads/customize/';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0777, true);
                    $filename = 'customize_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
                    move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
                    $referenceImage = 'uploads/customize/' . $filename;
                }
            }

            if (!$error) {
                $db->prepare("INSERT INTO customize_requests (user_id, size, flavor, color, cake_message, reference_image, delivery_date, additional_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')")
                    ->execute([$_SESSION['user_id'], $size, $flavor, $color ?: null, $cakeMessage ?: null, $referenceImage, $deliveryDate, $additionalNotes ?: null]);

                // Notify admin
                $db->prepare("INSERT INTO notifications (type, title, message) VALUES ('customize_request', ?, ?)")
                    ->execute([
                        __('customize_notif_title'),
                        sprintf(__('customize_notif_body'), htmlspecialchars($_SESSION['name']))
                    ]);

                // Post-Redirect-Get: prevents duplicate submission on browser refresh/back
                header('Location: /sweetheaven/user/customize.php?submitted=1');
                exit;
            }
        }
    }
}

// Fetch user's existing customize requests
$myRequests = $db->prepare("SELECT * FROM customize_requests WHERE user_id=? ORDER BY created_at DESC");
$myRequests->execute([$_SESSION['user_id']]);
$myRequests = $myRequests->fetchAll();

// Check if user already has 3 active (pending/approved) requests — block new submissions
$activeCount = 0;
foreach ($myRequests as $r) {
    if (in_array($r['status'], ['pending', 'approved'])) {
        $activeCount++;
    }
}
$hasActiveRequest = ($activeCount >= 3);

// Show success message after redirect
if (isset($_GET['submitted'])) {
    $success = __('customize_success_msg');
}

$reqStatusColors = [
    'pending' => 'bg-amber-100 text-amber-700',
    'approved' => 'bg-green-100 text-green-700',
    'rejected' => 'bg-red-100 text-red-700',
    'ordered' => 'bg-blue-100 text-blue-700',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('customize_title_tag') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght=300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    <div class="max-w-7xl mx-auto px-6 py-10">
        <div class="flex items-center gap-4 mb-8">
            <div class="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center">
                <svg class="w-7 h-7 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><?= __('customize_page_head') ?></h1>
                <p class="text-gray-400 text-sm"><?= __('customize_subtitle') ?></p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✅
                <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">⚠️ <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-2 gap-8 items-start">
            <?php if ($hasActiveRequest): ?>
                <!-- Blocked: user already has a pending/approved request -->
                <div class="bg-white rounded-2xl shadow-sm border border-amber-200 p-8 text-center">
                    <div class="w-16 h-16 bg-amber-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Maximum requests reached</h3>
                    <p class="text-sm text-gray-500 mb-5">
                        You already have <strong>3 pending or approved</strong> customize requests.
                        Please wait for them to be processed, or place your order if they have been approved.
                        Once an order is placed or a request is rejected, you can submit new requests.
                    </p>
                    <a href="#my-requests"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-rose-500 hover:text-rose-600 transition-colors">
                        View my requests ↓
                    </a>
                </div>
            <?php else: ?>
                <form id="customizeForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-2"><?= __('customize_size_label') ?></label>
                                <select name="size" required
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                                    <option value=""><?= __('customize_size_default') ?></option>
                                    <option value="6 inch"><?= __('customize_size_6inch') ?></option>
                                    <option value="8 inch"><?= __('customize_size_8inch') ?></option>
                                    <option value="10 inch"><?= __('customize_size_10inch') ?></option>
                                    <option value="12 inch"><?= __('customize_size_12inch') ?></option>
                                    <option value="Custom"><?= __('customize_size_custom') ?></option>

                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-2"><?= __('customize_flavor_label') ?></label>
                                <select name="flavor" required
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                                    <option value=""><?= __('customize_flavor_default') ?></option>
                                    <option value="Chocolate"><?= __('customize_flavor_chocolate') ?></option>
                                    <option value="Vanilla"><?= __('customize_flavor_vanilla') ?></option>
                                    <option value="Red Velvet"><?= __('customize_flavor_red_velvet') ?></option>
                                    <option value="Lemon"><?= __('customize_flavor_lemon') ?></option>
                                    <option value="Strawberry"><?= __('customize_flavor_strawberry') ?></option>
                                    <option value="Coffee"><?= __('customize_flavor_coffee') ?></option>
                                    <option value="Matcha"><?= __('customize_flavor_matcha') ?></option>
                                    <option value="Pandan"><?= __('customize_flavor_pandan') ?></option>
                                    <option value="Mango"><?= __('customize_flavor_mango') ?></option>
                                    <option value="Black Forest"><?= __('customize_flavor_black_forest') ?></option>
                                    <option value="Custom"><?= __('customize_flavor_custom') ?></option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-semibold text-gray-700 mb-2"><?= __('customize_color_label') ?></label>
                            <select name="color"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                                <option value=""><?= __('customize_color_default') ?></option>
                                <option value="White"><?= __('customize_color_white') ?></option>
                                <option value="Pink"><?= __('customize_color_pink') ?></option>
                                <option value="Red"><?= __('customize_color_red') ?></option>
                                <option value="Blue"><?= __('customize_color_blue') ?></option>
                                <option value="Green"><?= __('customize_color_green') ?></option>
                                <option value="Yellow"><?= __('customize_color_yellow') ?></option>
                                <option value="Black"><?= __('customize_color_black') ?></option>
                                <option value="Purple"><?= __('customize_color_purple') ?></option>
                                <option value="Brown"><?= __('customize_color_brown') ?></option>
                                <option value="Orange"><?= __('customize_color_orange') ?></option>
                                <option value="Custom"><?= __('customize_color_custom') ?></option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-semibold text-gray-700 mb-2"><?= __('customize_msg_label') ?></label>
                            <textarea name="cake_message" rows="2" placeholder="<?= __('customize_msg_ph') ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-semibold text-gray-700 mb-2"><?= __('customize_image_label') ?></label>
                            <div class="border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center hover:border-rose-300 transition-colors cursor-pointer"
                                id="uploadDropzone">
                                <input type="file" name="reference_image" id="referenceImage"
                                    accept="image/jpeg,image/png,image/webp" class="hidden">
                                <div id="uploadPlaceholder">
                                    <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-sm text-gray-400"><?= __('customize_image_upload') ?></p>
                                    <p class="text-xs text-gray-300 mt-1"><?= __('customize_image_formats') ?></p>
                                </div>
                                <div id="uploadPreview" class="hidden relative">
                                    <img id="previewImage" class="max-h-48 mx-auto rounded-xl shadow-sm">
                                    <button type="button" id="removeImage"
                                        class="absolute -top-2 -right-2 w-7 h-7 bg-red-500 text-white rounded-full text-sm font-bold hover:bg-red-600 transition-colors shadow-md">✕</button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-semibold text-gray-700 mb-2"><?= __('customize_date_label') ?></label>
                            <input type="date" name="delivery_date" required min="<?= $minDate ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                            <p class="text-xs text-gray-400 mt-1">
                                <?= sprintf(__('customize_date_hint'), date('M j, Y', strtotime($minDate))) ?></p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('customize_notes_label') ?>
                                <span class="text-gray-400 font-normal"><?= __('customize_notes_optional') ?></span></label>
                            <textarea name="additional_notes" rows="3" placeholder="<?= __('customize_notes_ph') ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full   bg-rose-500 hover:bg-rose-600 text-white font-bold py-4 rounded-2xl transition-colors shadow-sm shadow-rose-100 text-base">
                        <?= __('customize_submit_btn') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div id="my-requests">
            <?php if (!empty($myRequests)): ?>
                <div class="flex items-center gap-3 m-6">
                    <div class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800"><?= __('customize_my_requests') ?></h2>
                        <p class="text-sm text-gray-400"><?= __('customize_my_requests_sub') ?></p>
                    </div>
                </div>

                <div class="space-y-4">
                    <?php foreach ($myRequests as $req): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 flex items-center justify-between border-b border-gray-50">
                                <div class="flex items-center gap-4">
                                    <p class="font-bold text-gray-800">#<?= str_pad($req['id'], 4, '0', STR_PAD_LEFT) ?>
                                    </p>
                                    <span
                                        class="text-xs font-bold px-2.5 py-1 rounded-full <?= $reqStatusColors[$req['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                        <?= ucfirst($req['status']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400"><?= date('M j, Y', strtotime($req['created_at'])) ?>
                                </p>
                            </div>
                            <div class="px-6 py-4">
                                <div class="grid sm:grid-cols-2 gap-4 text-sm">
                                    <div class="space-y-1">
                                        <p><span class="font-semibold text-gray-600"><?= __('customize_req_size') ?></span>
                                            <?= htmlspecialchars($req['size']) ?>
                                        </p>
                                        <p><span class="font-semibold text-gray-600"><?= __('customize_req_flavor') ?></span>
                                            <?= htmlspecialchars($req['flavor']) ?>
                                        </p>
                                        <?php if ($req['color']): ?>
                                            <p><span class="font-semibold text-gray-600"><?= __('customize_req_color') ?></span>
                                                <?= htmlspecialchars($req['color']) ?>
                                            </p><?php endif; ?>
                                        <?php if ($req['cake_message']): ?>
                                            <p><span class="font-semibold text-gray-600"><?= __('customize_req_message') ?></span>
                                                <?= htmlspecialchars($req['cake_message']) ?>
                                            </p><?php endif; ?>
                                        <p><span class="font-semibold text-gray-600"><?= __('customize_req_delivery') ?></span>
                                            <?= date('M j, Y', strtotime($req['delivery_date'])) ?>
                                        </p>
                                    </div>
                                    <div class="space-y-1">
                                        <?php if ($req['reference_image']): ?>
                                            <div>
                                                <span
                                                    class="font-semibold text-gray-600"><?= __('customize_req_reference') ?></span>
                                                <a href="/sweetheaven/<?= htmlspecialchars($req['reference_image']) ?>"
                                                    target="_blank" class="block mt-1">
                                                    <img src="/sweetheaven/<?= htmlspecialchars($req['reference_image']) ?>"
                                                        class="w-20 h-20 object-cover rounded-lg border border-gray-200">
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($req['status'] === 'approved' && $req['admin_price']): ?>
                                            <p class="mt-2"><span
                                                    class="font-semibold text-gray-600"><?= __('customize_req_price') ?></span>
                                                <span
                                                    class="text-rose-500 font-bold text-base"><?= number_format($req['admin_price']) ?>
                                                    <?= __('common_mmk') ?></span></p>
                                        <?php endif; ?>
                                        <?php if ($req['admin_note']): ?>
                                            <p><span
                                                    class="font-semibold text-gray-600"><?= __('customize_req_admin_note') ?></span>
                                                <?= htmlspecialchars($req['admin_note']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($req['additional_notes']): ?>
                                    <p class="text-xs text-gray-400 mt-2">📝
                                        <?= htmlspecialchars($req['additional_notes']) ?>
                                    </p>
                                <?php endif; ?>
                                <div class="mt-4 flex gap-3">
                                    <?php if ($req['status'] === 'approved'): ?>
                                        <a href="/sweetheaven/user/checkout.php?customize_id=<?= $req['id'] ?>"
                                            class="inline-flex items-center gap-2 bg-rose-500 hover:bg-rose-600 text-white font-semibold px-6 py-3 rounded-xl transition-colors text-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <?= __('customize_req_order') ?>
                                        </a>
                                    <?php elseif ($req['status'] === 'pending'): ?>
                                        <span
                                            class="inline-flex items-center gap-2 text-amber-600 bg-amber-50 px-4 py-2.5 rounded-xl text-sm font-medium">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <?= __('customize_status_pending') ?>
                                        </span>
                                    <?php elseif ($req['status'] === 'rejected'): ?>
                                        <span
                                            class="inline-flex items-center gap-2 text-red-600 bg-red-50 px-4 py-2.5 rounded-xl text-sm font-medium">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <?= __('customize_status_rejected') ?>
                                        </span>
                                    <?php elseif ($req['status'] === 'ordered'): ?>
                                        <span
                                            class="inline-flex items-center gap-2 text-blue-600 bg-blue-50 px-4 py-2.5 rounded-xl text-sm font-medium">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                            <?= __('customize_status_ordered') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
                    <p class="text-4xl mb-3">🎂</p>
                    <h3 class="text-lg font-bold text-gray-700 mb-1"><?= __('customize_no_requests_title') ?></h3>
                    <p class="text-sm text-gray-400"><?= __('customize_no_requests_sub') ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        const fileInput = document.getElementById('referenceImage');
        const dropzone = document.getElementById('uploadDropzone');
        const placeholder = document.getElementById('uploadPlaceholder');
        const preview = document.getElementById('uploadPreview');
        const previewImg = document.getElementById('previewImage');

        dropzone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) {
                placeholder.classList.remove('hidden');
                preview.classList.add('hidden');
                return;
            }
            const reader = new FileReader();
            reader.onload = e => {
                previewImg.src = e.target.result;
                placeholder.classList.add('hidden');
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });
        document.getElementById('removeImage').addEventListener('click', (e) => {
            e.stopPropagation();
            fileInput.value = '';
            placeholder.classList.remove('hidden');
            preview.classList.add('hidden');
        });

        const customizeForm = document.getElementById('customizeForm');
        if (customizeForm) {
            const sizeSelect = customizeForm.querySelector('[name="size"]');
            const flavorSelect = customizeForm.querySelector('[name="flavor"]');
            const colorSelect = customizeForm.querySelector('[name="color"]');
            const notesInput = customizeForm.querySelector('[name="additional_notes"]');
            const notesLabel = notesInput.previousElementSibling;

            function checkCustomRequired() {
                const isCustom = sizeSelect.value === 'Custom' || flavorSelect.value === 'Custom' || colorSelect.value === 'Custom';
                
                // Visual feedback: Add/remove asterisk on the label
                if (isCustom) {
                    if (!notesLabel.querySelector('.req-star')) {
                        notesLabel.innerHTML += ' <span class="text-rose-500 req-star">*</span>';
                    }
                } else {
                    const star = notesLabel.querySelector('.req-star');
                    if (star) star.remove();
                }

                // Native HTML5 validation
                if (isCustom && notesInput.value.trim() === '') {
                    notesInput.setCustomValidity('<?= addslashes(__('customize_custom_note_required')) ?>');
                } else {
                    notesInput.setCustomValidity('');
                }
            }

            // Run check when selections change
            sizeSelect.addEventListener('change', checkCustomRequired);
            flavorSelect.addEventListener('change', checkCustomRequired);
            colorSelect.addEventListener('change', checkCustomRequired);
            notesInput.addEventListener('input', checkCustomRequired);

            // Run check on submit just to be safe
            customizeForm.addEventListener('submit', function(e) {
                checkCustomRequired();
                if (!customizeForm.checkValidity()) {
                    e.preventDefault();
                    notesInput.reportValidity();
                }
            });
        }
    </script>
</body>

</html>