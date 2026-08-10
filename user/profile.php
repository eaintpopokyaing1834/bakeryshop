<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$userId = (int) $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] === 'admin');

// ── Update Profile ────────────────────────────────────
$profileMsg = $profileError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $passNew = $_POST['new_password'] ?? '';
    $passCur = $_POST['current_password'] ?? '';

    $user = $db->prepare("SELECT * FROM users WHERE id=?");
    $user->execute([$userId]);
    $user = $user->fetch();

    if (!$name || !$email) {
        $profileError = __('profile_err_required');
    } elseif ($passNew && !password_verify($passCur, $user['password'])) {
        $profileError = __('profile_err_password');
    } else {
        $profileImage = $user['profile_image'];
        if (!empty($_FILES['profile_image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../uploads/profiles/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0775, true);
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $file = 'profile_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $file)) {
                $profileImage = 'uploads/profiles/' . $file;
            }
        }
        if ($passNew) {
            $db->prepare("UPDATE users SET name=?,email=?,password=?,profile_image=? WHERE id=?")
                ->execute([$name, $email, password_hash($passNew, PASSWORD_BCRYPT), $profileImage, $userId]);
        } else {
            $db->prepare("UPDATE users SET name=?,email=?,profile_image=? WHERE id=?")
                ->execute([$name, $email, $profileImage, $userId]);
        }
        $_SESSION['name'] = $name;
        $profileMsg = __('profile_success');
    }
}

$user = $db->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$userId]);
$user = $user->fetch();

// Orders
$orders = $db->prepare("
    SELECT o.*, pm.payment_name, p.status AS pay_status, p.screenshot FROM orders o
    LEFT JOIN payment p ON p.order_id = o.id
    LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
    WHERE o.user_id=?
    ORDER BY o.order_date DESC
");
$orders->execute([$userId]);
$orders = $orders->fetchAll();

// Customize Requests
$customizeRequests = $db->prepare("
    SELECT * FROM customize_requests
    WHERE user_id=?
    ORDER BY created_at DESC
");
$customizeRequests->execute([$userId]);
$customizeRequests = $customizeRequests->fetchAll();

// Wishlist
$wishlist = $db->prepare("
    SELECT p.*, pi.image_url AS primary_image FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
    WHERE w.user_id=?
");
$wishlist->execute([$userId]);
$wishlist = $wishlist->fetchAll();

$activeTab = $_GET['tab'] ?? 'account';
$statusColors = [
    'pending' => 'bg-amber-100 text-amber-700',
    'processing' => 'bg-blue-100 text-blue-700',
    'shipped' => 'bg-indigo-100 text-indigo-700',
    'delivered' => 'bg-green-100 text-green-700',
    'cancelled' => 'bg-red-100 text-red-700',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Sweet Heaven Bakery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"
        integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
    </style>
    <style>
        @page {
            size: A4 portrait;
            /* margin: 0 suppresses browser-added date/time/title headers.
               Browsers render those in the @page margin space —
               with no margin space they have nowhere to render. */
            margin: 0;
        }

        @media print {

            html,
            body {
                width: 210mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                overflow: visible !important;
            }

            body> :not(#voucherModal) {
                display: none !important;
            }

            #voucherModal {
                position: static !important;
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                overflow: visible !important;
                align-items: unset !important;
                justify-content: unset !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            #voucherModal>div {
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                overflow: visible !important;
                background: #fff !important;
            }

            #voucherModal button,
            #voucherModal .no-print {
                display: none !important;
            }

            #voucherContent {
                padding: 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            #voucherPrintArea {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                margin: 0 !important;
                padding: 15mm !important;
                /* compensates for @page margin: 0 */
                background: #fff !important;
                overflow: visible !important;
            }

            #voucherPrintArea *,
            #voucherPrintArea *::before,
            #voucherPrintArea *::after {
                box-sizing: border-box !important;
                overflow: visible !important;
            }

            #voucherPrintArea .flex {
                display: flex !important;
                flex-wrap: nowrap !important;
                width: 100% !important;
            }

            #voucherPrintArea .flex .label-col {
                flex: 1 1 auto;
                min-width: 0;
                overflow-wrap: break-word;
                word-break: break-word;
                padding-right: 8px;
            }

            #voucherPrintArea .flex .amount-col {
                flex: 0 0 auto;
                white-space: nowrap;
                text-align: right;
            }

            #vItems .flex .label-col,
            #vSummary .flex span:first-child {
                flex: 1 1 auto;
                min-width: 0;
                overflow-wrap: break-word;
                padding-right: 8px;
            }

            #vItems .flex span:last-child,
            #vSummary .flex span:last-child {
                flex: 0 0 auto;
                white-space: nowrap;
                text-align: right;
            }

            #voucherModal.hidden {
                display: block !important;
            }

            #voucherBody.hidden {
                display: block !important;
            }

            #voucherLoading {
                display: none !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-5xl mx-auto px-6 py-10">

        <!-- Profile Header -->
        <div class="bg-pink-400 rounded-3xl p-8 mb-8 text-white flex items-center gap-6">
            <div
                class="w-20 h-20 rounded-full overflow-hidden border-4 border-white/30 bg-pink-400 flex items-center justify-center shrink-0">
                <?php if ($user['profile_image']): ?>
                    <img src="/sweetheaven/<?= htmlspecialchars($user['profile_image']) ?>"
                        class="w-full h-full object-cover" alt="Profile">
                <?php else: ?>
                    <span class="text-3xl font-bold"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <h1 class="text-2xl font-bold"><?= htmlspecialchars($user['name']) ?></h1>
                <p class="text-slate-600 text-sm"><?= htmlspecialchars($user['email']) ?></p>
                <div class="flex items-center gap-3 mt-3">
                    <span
                        class="bg-white/20 text-xs px-3 py-1 rounded-full font-semibold"><?= ucfirst($user['role']) ?></span>
                    <span class="text-slate-600 text-xs"><?= __('profile_member_since') ?>
                        <?= date('M Y', strtotime($user['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6 bg-white rounded-2xl p-2 shadow-sm border border-gray-100">
            <?php $tabs = $isAdmin ? ['account' => __('profile_tab_account')] : ['account' => __('profile_tab_account'), 'orders' => __('profile_tab_orders'), 'customize' => __('profile_tab_customize'), 'wishlist' => __('profile_tab_wishlist')]; ?>
            <?php foreach ($tabs as $t => $label): ?>
                <a href="?tab=<?= $t ?>"
                    class="flex-1 text-center py-3 px-4 rounded-xl text-sm font-semibold transition-colors
           <?= $activeTab === $t ? 'bg-pink-500 text-white shadow-md shadow-rose-100' : 'text-gray-500 hover:text-rose-500 hover:bg-rose-50' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Account Tab -->
        <?php if ($activeTab === 'account'): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-xl font-bold text-gray-800 mb-6"><?= __('profile_account_settings') ?></h2>

                <?php if ($profileMsg): ?>
                    <div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✅
                        <?= $profileMsg ?></div>
                <?php endif; ?>
                <?php if ($profileError): ?>
                    <div class="mb-5 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm">⚠️
                        <?= $profileError ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="update_profile" value="1">

                    <!-- Profile Image -->
                    <div class="flex items-center gap-6 mb-2">
                        <div class="w-16 h-16 rounded-full overflow-hidden bg-rose-50 shrink-0">
                            <?php if ($user['profile_image']): ?>
                                <img src="/sweetheaven/<?= htmlspecialchars($user['profile_image']) ?>"
                                    class="w-full h-full object-cover" id="profilePreview">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-2xl font-bold text-rose-400"
                                    id="profilePreview"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label
                                class="cursor-pointer bg-rose-50 hover:bg-rose-50 text-rose-600 px-4 py-2 rounded-xl text-sm font-semibold transition-colors">
                                <?= __('profile_change_photo') ?>
                                <input type="file" name="profile_image" accept="image/*" class="hidden"
                                    onchange="previewImage(this)">
                            </label>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('profile_full_name') ?></label>
                            <input type="text" name="name" required value="<?= htmlspecialchars($user['name']) ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('profile_email') ?></label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('profile_cur_pass') ?></label>
                            <input type="password" name="current_password" placeholder="<?= __('profile_cur_pass_ph') ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('profile_new_pass') ?></label>
                            <input type="password" name="new_password" placeholder="<?= __('profile_new_pass_ph') ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        </div>
                    </div>

                    <button type="submit"
                        class="bg-pink-500 hover:bg-pink-600 text-white font-semibold px-8 py-3 rounded-xl transition-colors">
                        <?= __('profile_save') ?>
                    </button>
                </form>
            </div>

            <!-- Orders Tab -->
        <?php elseif ($activeTab === 'orders'): ?>
            <div class="space-y-4">
                <?php if (empty($orders)): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
                        <p class="text-5xl mb-4">📋</p>
                        <h3 class="text-xl font-bold text-gray-700 mb-2"><?= __('profile_no_orders') ?></h3>
                        <a href="/sweetheaven/user/products.php"
                            class="bg-rose-500 text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-rose-600 transition-colors mt-4 inline-block"><?= __('profile_shop_now') ?></a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $orderItems = $db->prepare("SELECT oi.quantity, oi.price, p.name FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
                        $orderItems->execute([$order['id']]);
                        $orderItems = $orderItems->fetchAll();
                        ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 flex items-center justify-between border-b border-gray-50">
                                <div class="flex items-center gap-4">
                                    <p class="font-bold text-gray-800">#<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></p>
                                    <span
                                        class="text-xs font-bold px-2.5 py-1 rounded-full <?= $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-rose-500"><?= formatPrice($order['total_amount']) ?></p>
                                    <p class="text-xs text-gray-400"><?= date('M j, Y', strtotime($order['order_date'])) ?></p>
                                </div>
                            </div>
                            <div class="px-6 py-4">
                                <div class="space-y-2">
                                    <?php foreach ($orderItems as $item): ?>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600"><?= htmlspecialchars($item['name']) ?> ×
                                                <?= $item['quantity'] ?></span>
                                            <span
                                                class="font-semibold text-gray-700"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($order['payment_name']): ?>
                                    <p class="text-xs text-gray-400 mt-3"><?= __('profile_payment') ?> <?= htmlspecialchars($order['payment_name']) ?>
                                        <?php if ($order['pay_status']): ?>
                                            <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full ml-1
                        <?= $order['pay_status'] === 'approved' ? 'bg-green-100 text-green-700' : '' ?>
                        <?= $order['pay_status'] === 'pending' ? 'bg-amber-100 text-amber-700' : '' ?>
                        <?= $order['pay_status'] === 'rejected' ? 'bg-red-100 text-red-700' : '' ?>">
                                                <?= ucfirst($order['pay_status']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($order['pay_status'] === 'approved' && in_array($order['status'], ['processing', 'shipped', 'delivered'])): ?>
                                            &middot; <button onclick="openVoucher(<?= $order['id'] ?>)"
                                                class="text-rose-500 hover:underline font-semibold"><?= __('profile_view_voucher') ?></button>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-400"><?= __('profile_shipping_to') ?> <?= htmlspecialchars($order['shipping_address']) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Customize Requests Tab -->
        <?php elseif ($activeTab === 'customize'): ?>
            <div class="space-y-4">
                <?php if (empty($customizeRequests)): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
                        <p class="text-5xl mb-4">🎂</p>
                        <h3 class="text-xl font-bold text-gray-700 mb-2"><?= __('profile_no_customize') ?></h3>
                        <a href="/sweetheaven/user/customize.php"
                            class="bg-rose-500 text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-rose-600 transition-colors mt-4 inline-block"><?= __('profile_customize_now') ?></a>
                    </div>
                <?php else: ?>
                    <?php foreach ($customizeRequests as $cr): ?>
                        <?php
                        $crStatusColors = [
                            'pending' => 'bg-amber-100 text-amber-700',
                            'approved' => 'bg-green-100 text-green-700',
                            'rejected' => 'bg-red-100 text-red-700',
                            'ordered' => 'bg-blue-100 text-blue-700',
                        ];
                        ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                            <div class="px-6 py-4 flex items-center justify-between border-b border-gray-50">
                                <div class="flex items-center gap-4">
                                    <p class="font-bold text-gray-800">#<?= str_pad($cr['id'], 4, '0', STR_PAD_LEFT) ?></p>
                                    <span
                                        class="text-xs font-bold px-2.5 py-1 rounded-full <?= $crStatusColors[$cr['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                        <?= ucfirst($cr['status']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400"><?= date('M j, Y', strtotime($cr['created_at'])) ?></p>
                            </div>
                            <div class="px-6 py-4">
                                <div class="grid sm:grid-cols-2 gap-4 text-sm">
                                    <div class="space-y-1">
                                        <p><span class="font-semibold text-gray-600"><?= __('profile_size') ?></span>
                                            <?= htmlspecialchars($cr['size']) ?></p>
                                        <p><span class="font-semibold text-gray-600"><?= __('profile_flavor') ?></span>
                                            <?= htmlspecialchars($cr['flavor']) ?></p>
                                        <?php if ($cr['color']): ?>
                                            <p><span class="font-semibold text-gray-600"><?= __('profile_color') ?></span>
                                                <?= htmlspecialchars($cr['color']) ?></p><?php endif; ?>
                                        <?php if ($cr['cake_message']): ?>
                                            <p><span class="font-semibold text-gray-600"><?= __('profile_message') ?></span>
                                                <?= htmlspecialchars($cr['cake_message']) ?></p><?php endif; ?>
                                        <p><span class="font-semibold text-gray-600"><?= __('profile_delivery') ?></span>
                                            <?= date('M j, Y', strtotime($cr['delivery_date'])) ?></p>
                                    </div>
                                    <div class="space-y-1">
                                        <?php if ($cr['reference_image']): ?>
                                            <div>
                                                <span class="font-semibold text-gray-600"><?= __('profile_reference') ?></span>
                                                <a href="/sweetheaven/<?= htmlspecialchars($cr['reference_image']) ?>" target="_blank"
                                                    class="text-rose-500 hover:underline block mt-1">
                                                    <img src="/sweetheaven/<?= htmlspecialchars($cr['reference_image']) ?>"
                                                        class="w-20 h-20 object-cover rounded-lg border border-gray-200">
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($cr['admin_price']): ?>
                                                <p><span class="font-semibold text-gray-600"><?= __('customize_price_label') ?></span>
                                                <span
                                                    class="text-rose-500 font-bold"><?= formatPrice($cr['admin_price']) ?></span></p>
                                        <?php endif; ?>
                                        <?php if ($cr['admin_note']): ?>
                                            <p><span class="font-semibold text-gray-600"><?= __('profile_note') ?></span>
                                                <?= htmlspecialchars($cr['admin_note']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($cr['additional_notes']): ?>
                                    <p class="text-xs text-gray-400 mt-2">📝 <?= htmlspecialchars($cr['additional_notes']) ?></p>
                                <?php endif; ?>
                                <?php if ($cr['status'] === 'approved'): ?>
                                    <a href="/sweetheaven/user/checkout.php?customize_id=<?= $cr['id'] ?>"
                                        class="mt-4 inline-block bg-rose-500 hover:bg-rose-600 text-white font-semibold px-6 py-3 rounded-xl transition-colors text-sm">
                                        <?= __('profile_proceed_order') ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Wishlist Tab -->
        <?php elseif ($activeTab === 'wishlist'): ?>
            <?php if (empty($wishlist)): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
                    <p class="text-5xl mb-4">❤️</p>
                    <h3 class="text-xl font-bold text-gray-700 mb-2"><?= __('profile_no_wishlist') ?></h3>
                    <a href="/sweetheaven/user/products.php"
                        class="bg-rose-500 text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-rose-600 transition-colors mt-4 inline-block"><?= __('profile_explore') ?></a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <?php foreach ($wishlist as $item): ?>
                        <?php $imgSrc = $item['primary_image'] ? '/sweetheaven/' . $item['primary_image'] : '/sweetheaven/images/maincake.jpg'; ?>
                        <div
                            class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-sm transition-shadow">
                            <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-60 object-cover" alt="">
                            <div class="p-4">
                                <p class="font-bold text-gray-700 text-sm mb-1 line-clamp-1"><?= htmlspecialchars($item['name']) ?>
                                </p>
                                <p class="text-rose-500 font-bold text-sm mb-3"><?= formatPrice($item['price']) ?></p>
                                <div class="flex gap-2">
                                    <button onclick="addToCart(<?= $item['id'] ?>)"
                                        class="flex-1 bg-pink-500 text-white text-xs font-semibold py-2 rounded-xl hover:bg-rose-600 transition-colors"><?= __('profile_add_cart') ?></button>
                                    <button onclick="removeFromWishlist(<?= $item['id'] ?>, this)"
                                        class="p-2 text-gray-300 hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Voucher Modal -->
    <div id="voucherModal"
        class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 p-2 sm:p-4 flex items-start justify-center">
        <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full relative mt-4 mb-4 shrink-0"
            onclick="event.stopPropagation()" style="box-sizing:border-box;">
            <button onclick="closeVoucher()"
                class="absolute top-4 right-4 w-8 h-8 bg-gray-100 hover:bg-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-gray-700 transition-colors z-10 no-print">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <div id="voucherContent" class="p-4 sm:p-6" style="box-sizing:border-box;width:100%;">
                <div id="voucherPrintArea" style="box-sizing:border-box;width:100%;max-width:800px;margin:0 auto;">
                    <div class="text-center mb-4" id="voucherLoading">
                        <svg class="animate-spin h-8 w-8 text-rose-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                    </div>
                    <div id="voucherBody" class="hidden">
                        <!-- Compact Header -->
                        <div class="flex flex-col sm:flex-row justify-between items-center sm:items-end border-b border-gray-200 pb-3 mb-4">
                            <div class="flex items-center gap-3 text-center sm:text-left mb-3 sm:mb-0">
                                <img src="/sweetheaven/images/9102671.png" class="h-10 w-auto" alt="Sweet Heaven">
                                <div>
                                    <h1 class="text-xl font-bold text-stone-800 uppercase tracking-wider mb-0.5">Sweet Heaven</h1>
                                    <p class="text-xs text-gray-500"><?= __('voucher_shop_address') ?></p>
                                    <p class="text-xs text-gray-500 mt-0.5"><?= __('voucher_shop_phone') ?></p>
                                </div>
                            </div>
                            <div class="text-xs text-gray-600 text-center sm:text-right">
                                <p><span class="font-semibold text-gray-800"><?= __('voucher_order_id') ?>:</span> <span id="vCustOrderId"></span></p>
                                <p class="mt-0.5"><span class="font-semibold text-gray-800"><?= __('voucher_purchase_date') ?>:</span> <span id="vCustDate"></span></p>
                            </div>
                        </div>

                        <!-- Customer Information -->
                        <div class="mb-4 text-xs">
                            <h2 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100 pb-1 mb-2"><?= __('voucher_customer_info') ?></h2>
                            <div class="space-y-1">
                                <p><span class="text-gray-500 inline-block w-20"><?= __('voucher_customer_name') ?>:</span> <span class="font-semibold text-gray-800" id="vCustName"></span></p>
                                <p><span class="text-gray-500 inline-block w-20"><?= __('voucher_email') ?>:</span> <span class="text-gray-800 overflow-hidden text-ellipsis whitespace-nowrap align-bottom" id="vCustEmail"></span></p>
                                <p><span class="text-gray-500 inline-block w-20"><?= __('voucher_phone') ?>:</span> <span class="text-gray-800" id="vCustPhone"></span></p>
                                <p><span class="text-gray-500 inline-block w-20"><?= __('voucher_shipping_method') ?>:</span> <span class="text-gray-800" id="vShipping"></span></p>
                            </div>
                        </div>

                        <!-- Order Details -->
                        <div class="mb-4">
                            <h2 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100 pb-1 mb-2"><?= __('voucher_order_details') ?></h2>
                            <table class="w-full text-xs text-left border-collapse" style="width:100%;">
                                <thead>
                                    <tr class="border-b border-gray-100 text-gray-500 text-[11px] uppercase tracking-wider">
                                        <th class="py-1.5 font-semibold" colspan="2"><?= __('voucher_product') ?></th>
                                        <th class="py-1.5 font-semibold text-center"><?= __('voucher_qty') ?></th>
                                        <th class="py-1.5 font-semibold text-right"><?= __('voucher_unit_price') ?></th>
                                        <th class="py-1.5 font-semibold text-right"><?= __('voucher_subtotal') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="vItems" class="divide-y divide-gray-50">
                                </tbody>
                            </table>
                        </div>

                        <!-- Order Summary -->
                        <div class="flex justify-end">
                            <div class="w-full max-w-xs space-y-1 text-xs mb-4" id="vSummary">
                                <div class="flex justify-between text-gray-500">
                                    <span><?= __('voucher_subtotal') ?></span>
                                    <span id="vOriginalSubtotal"></span>
                                </div>
                                <div class="flex justify-between text-gray-500" id="vShippingFeeRow">
                                    <span><?= __('checkout_shipping_fee') ?></span>
                                    <span id="vShippingFee"></span>
                                </div>
                                <div id="vProductDiscountRow" style="display:none;" class="flex justify-between text-green-600">
                                    <span><?= __('voucher_discount_applied') ?></span>
                                    <span id="vProductDiscount"></span>
                                </div>
                                <div id="vFirstOrderRow" style="display:none;" class="flex justify-between text-blue-600">
                                    <span><?= __('voucher_first_order_discount') ?></span>
                                    <span id="vFirstOrderDiscount"></span>
                                </div>
                                <div class="flex justify-between font-bold text-base text-rose-500 border-t border-gray-100 pt-1.5 mt-1.5">
                                    <span><?= __('voucher_total_amount') ?></span>
                                    <span id="vTotal"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Thank you message -->
                        <div class="text-center pt-3 border-t border-gray-100 text-xs text-gray-500">
                            <p><?= __('voucher_thank_you') ?></p>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 mt-4 no-print">
                    <button onclick="printVoucher()" class="flex-1 bg-stone-800 hover:bg-stone-900 text-white font-semibold py-2.5 px-4 rounded-xl transition-colors text-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <?= __('voucher_print') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="toast"
        class="hidden fixed bottom-6 right-6 bg-stone-800 text-white px-5 py-3 rounded-xl shadow-md text-sm font-medium z-50 items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span id="toastMsg"></span>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    const preview = document.getElementById('profilePreview');
                    if (preview.tagName === 'IMG') preview.src = e.target.result;
                    else { const img = document.createElement('img'); img.src = e.target.result; img.className = 'w-full h-full object-cover'; preview.replaceWith(img); }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function addToCart(productId) {
            fetch('/sweetheaven/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add&product_id=${productId}&qty=1`
            }).then(r => r.json()).then(data => {
                if (data.success) { showToast('<?= addslashes(__('toast_added_cart')) ?>'); const b = document.getElementById('cartBadge'); if (b) { b.textContent = data.cart_count; b.classList.remove('hidden'); } }
            });
        }

        function removeFromWishlist(productId, btn) {
            const grid = btn.closest('.grid');
            fetch('/sweetheaven/api/wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}`
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    btn.closest('.bg-white').remove();
                    showToast('<?= addslashes(__('toast_removed_wishlist')) ?>');
                    if (typeof updateWishlistBadge === 'function') updateWishlistBadge(data.wishlist_count);
                    if (data.wishlist_count === 0 && grid) {
                        const parent = grid.parentNode;
                        grid.remove();
                        const emptyHtml = '<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">' +
                            '<p class="text-5xl mb-4">❤️</p>' +
                            '<h3 class="text-xl font-bold text-gray-700 mb-2"><?= addslashes(__('profile_no_wishlist')) ?></h3>' +
                            '<a href="/sweetheaven/user/products.php" class="bg-rose-500 text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-rose-600 transition-colors mt-4 inline-block"><?= addslashes(__('profile_explore')) ?></a>' +
                            '</div>';
                        parent.insertAdjacentHTML('beforeend', emptyHtml);
                    }
                }
            });
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = msg;
            t.classList.remove('hidden'); t.classList.add('flex');
            setTimeout(() => { t.classList.add('hidden'); t.classList.remove('flex'); }, 3000);
        }

        const shippingLabels = {
            'free_delivery': '<?= __('order_ship_free') ?>',
            'pickup': '<?= __('order_ship_pickup') ?>',
            'express': '<?= __('order_ship_express') ?>',
            'standard': '<?= __('order_ship_standard') ?>',
        };

        function openVoucher(orderId) {
            const modal = document.getElementById('voucherModal');
            const loading = document.getElementById('voucherLoading');
            const body = document.getElementById('voucherBody');
            modal.classList.remove('hidden');
            loading.classList.remove('hidden');
            body.classList.add('hidden');

            fetch('/sweetheaven/api/voucher.php?order_id=' + orderId)
                .then(r => r.json())
                .then(data => {
                    if (data.error) { showToast(data.error); closeVoucher(); return; }
                    loading.classList.add('hidden');
                    body.classList.remove('hidden');
                    populateVoucher(data);
                })
                .catch(() => { showToast('Failed to load voucher'); closeVoucher(); });
        }

        function closeVoucher() {
            document.getElementById('voucherModal').classList.add('hidden');
        }

        function formatVoucherDate(value) {
            if (!value) return 'N/A';
            const parts = String(value).split(' ')[0].split('-');
            if (parts.length !== 3) return value;
            const date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        }

        function populateVoucher(data) {
            document.getElementById('vCustName').textContent = data.customer_name;
            document.getElementById('vCustDate').textContent = formatVoucherDate(data.order_date);
            document.getElementById('vCustOrderId').textContent = '#' + String(data.order_id).padStart(4, '0');
            document.getElementById('vCustEmail').textContent = data.email;
            document.getElementById('vCustPhone').textContent = data.phone || 'N/A';

            const itemsHtml = data.items.map(item => {
                const total = Number(item.discounted_price) * Number(item.quantity);
                const imgSrc = item.product_image ? ('/sweetheaven/' + item.product_image) : '/sweetheaven/images/default_cake.png';
                return `<tr class="border-b border-gray-50">
                    <td class="py-1.5 align-middle w-10">
                        <img src="${imgSrc}" class="w-7 h-7 object-cover rounded border border-gray-100" alt="Product" onerror="this.src='/sweetheaven/images/default_cake.png'">
                    </td>
                    <td class="py-1.5 align-middle font-medium text-gray-800">${escHtml(item.product_name)}</td>
                    <td class="py-1.5 align-middle text-center text-gray-600">${item.quantity}</td>
                    <td class="py-1.5 align-middle text-right text-gray-600">${Number(item.discounted_price).toLocaleString()}</td>
                    <td class="py-1.5 align-middle text-right font-semibold text-gray-800">${Number(total).toLocaleString()}</td>
                </tr>`;
            }).join('');
            document.getElementById('vItems').innerHTML = itemsHtml;

            document.getElementById('vOriginalSubtotal').textContent = formatPriceJS(data.original_subtotal);
            document.getElementById('vTotal').textContent = formatPriceJS(data.total_amount);

            const shipLabel = shippingLabels[data.shipping_method] || data.shipping_method;
            document.getElementById('vShipping').textContent = shipLabel;
            
            document.getElementById('vShippingFee').textContent = data.shipping_fee > 0 ? formatPriceJS(data.shipping_fee) : 'Free';

            const prodDiscRow = document.getElementById('vProductDiscountRow');
            if (data.product_discount > 0) {
                prodDiscRow.style.display = 'flex';
                document.getElementById('vProductDiscount').textContent = '-' + formatPriceJS(data.product_discount);
            } else {
                prodDiscRow.style.display = 'none';
            }

            const firstOrderRow = document.getElementById('vFirstOrderRow');
            if (data.first_order_discount > 0) {
                firstOrderRow.style.display = 'flex';
                document.getElementById('vFirstOrderDiscount').textContent = '-' + formatPriceJS(data.first_order_discount);
            } else {
                firstOrderRow.style.display = 'none';
            }
        }

        document.getElementById('voucherModal').addEventListener('click', closeVoucher);

        function printVoucher() {
            window.print();
        }
    </script>
</body>

</html>