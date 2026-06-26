<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$userId = (int)$_SESSION['user_id'];

// ── Update Profile ────────────────────────────────────
$profileMsg = $profileError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $passNew = $_POST['new_password'] ?? '';
    $passCur = $_POST['current_password'] ?? '';

    $user = $db->prepare("SELECT * FROM users WHERE id=?");
    $user->execute([$userId]);
    $user = $user->fetch();

    if (!$name || !$email) {
        $profileError = 'Name and email are required.';
    } elseif ($passNew && !password_verify($passCur, $user['password'])) {
        $profileError = 'Current password is incorrect.';
    } else {
        $profileImage = $user['profile_image'];
        if (!empty($_FILES['profile_image']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../uploads/profiles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
            $ext  = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
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
        $profileMsg = 'Profile updated successfully!';
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

// Wishlist
$wishlist = $db->prepare("
    SELECT p.*, pi.image_url AS primary_image FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
    WHERE w.user_id=?
");
$wishlist->execute([$userId]);
$wishlist = $wishlist->fetchAll();

$activeTab   = $_GET['tab'] ?? 'account';
$statusColors = [
    'pending'    => 'bg-amber-100 text-amber-700',
    'processing' => 'bg-blue-100 text-blue-700',
    'shipped'    => 'bg-indigo-100 text-indigo-700',
    'delivered'  => 'bg-green-100 text-green-700',
    'cancelled'  => 'bg-red-100 text-red-700',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Sweet Heaven Bakery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-5xl mx-auto px-6 py-10">

    <!-- Profile Header -->
    <div class="bg-gradient-to-br from-rose-500 to-stone-800 rounded-3xl p-8 mb-8 text-white flex items-center gap-6">
        <div class="w-20 h-20 rounded-full overflow-hidden border-4 border-white/30 bg-stone-300 flex items-center justify-center shrink-0">
            <?php if ($user['profile_image']): ?>
            <img src="/sweetheaven/<?= htmlspecialchars($user['profile_image']) ?>" class="w-full h-full object-cover" alt="Profile">
            <?php else: ?>
            <span class="text-3xl font-bold"><?= strtoupper(substr($user['name'],0,1)) ?></span>
            <?php endif; ?>
        </div>
        <div>
            <h1 class="text-2xl font-bold"><?= htmlspecialchars($user['name']) ?></h1>
            <p class="text-stone-400 text-sm"><?= htmlspecialchars($user['email']) ?></p>
            <div class="flex items-center gap-3 mt-3">
                <span class="bg-white/20 text-xs px-3 py-1 rounded-full font-semibold"><?= ucfirst($user['role']) ?></span>
                <span class="text-stone-400 text-xs">Member since <?= date('M Y', strtotime($user['created_at'])) ?></span>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6 bg-white rounded-2xl p-2 shadow-sm border border-gray-100">
        <?php foreach (['account' => '👤 Account', 'orders' => '📋 My Orders', 'wishlist' => '❤️ Wishlist'] as $t => $label): ?>
        <a href="?tab=<?= $t ?>"
           class="flex-1 text-center py-3 px-4 rounded-xl text-sm font-semibold transition-colors
           <?= $activeTab === $t ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'text-gray-500 hover:text-rose-500 hover:bg-rose-50' ?>">
           <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Account Tab -->
    <?php if ($activeTab === 'account'): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <h2 class="text-xl font-bold text-gray-800 mb-6">Account Settings</h2>

        <?php if ($profileMsg): ?>
        <div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✅ <?= $profileMsg ?></div>
        <?php endif; ?>
        <?php if ($profileError): ?>
        <div class="mb-5 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm">⚠️ <?= $profileError ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="update_profile" value="1">

            <!-- Profile Image -->
            <div class="flex items-center gap-6 mb-2">
                <div class="w-16 h-16 rounded-full overflow-hidden bg-rose-50 shrink-0">
                    <?php if ($user['profile_image']): ?>
                    <img src="/sweetheaven/<?= htmlspecialchars($user['profile_image']) ?>" class="w-full h-full object-cover" id="profilePreview">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-2xl font-bold text-rose-400" id="profilePreview"><?= strtoupper(substr($user['name'],0,1)) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="cursor-pointer bg-rose-50 hover:bg-rose-50 text-rose-600 px-4 py-2 rounded-xl text-sm font-semibold transition-colors">
                        Change Photo
                        <input type="file" name="profile_image" accept="image/*" class="hidden" onchange="previewImage(this)">
                    </label>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($user['name']) ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address *</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                    <input type="password" name="current_password" placeholder="Required to change password"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                    <input type="password" name="new_password" placeholder="Leave blank to keep current"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
            </div>

            <button type="submit" class="bg-rose-500 hover:bg-rose-600 text-white font-semibold px-8 py-3 rounded-xl transition-colors">
                Save Changes
            </button>
        </form>
    </div>

    <!-- Orders Tab -->
    <?php elseif ($activeTab === 'orders'): ?>
    <div class="space-y-4">
        <?php if (empty($orders)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
            <p class="text-5xl mb-4">📋</p>
            <h3 class="text-xl font-bold text-gray-700 mb-2">No orders yet</h3>
            <a href="/sweetheaven/user/products.php" class="bg-rose-500 text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-rose-600 transition-colors mt-4 inline-block">Shop Now</a>
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
                    <p class="font-bold text-gray-800">#<?= str_pad($order['id'],4,'0',STR_PAD_LEFT) ?></p>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                        <?= ucfirst($order['status']) ?>
                    </span>
                </div>
                <div class="text-right">
                    <p class="font-bold text-rose-500"><?= number_format($order['total_amount']) ?> MMK</p>
                    <p class="text-xs text-gray-400"><?= date('M j, Y', strtotime($order['order_date'])) ?></p>
                </div>
            </div>
            <div class="px-6 py-4">
                <div class="space-y-2">
                    <?php foreach ($orderItems as $item): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600"><?= htmlspecialchars($item['name']) ?> × <?= $item['quantity'] ?></span>
                        <span class="font-semibold text-gray-700"><?= number_format($item['price'] * $item['quantity']) ?> MMK</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($order['payment_name']): ?>
                <p class="text-xs text-gray-400 mt-3">Payment: <?= htmlspecialchars($order['payment_name']) ?>
                    <?php if ($order['pay_status']): ?>
                    <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full ml-1
                        <?= $order['pay_status'] === 'approved' ? 'bg-green-100 text-green-700' : '' ?>
                        <?= $order['pay_status'] === 'pending' ? 'bg-amber-100 text-amber-700' : '' ?>
                        <?= $order['pay_status'] === 'rejected' ? 'bg-red-100 text-red-700' : '' ?>">
                        <?= ucfirst($order['pay_status']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($order['screenshot'])): ?>
                    &middot; <a href="/sweetheaven/<?= htmlspecialchars($order['screenshot']) ?>" target="_blank" class="text-rose-500 hover:underline">View Receipt</a>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                <p class="text-xs text-gray-400">Shipping to: <?= htmlspecialchars($order['shipping_address']) ?></p>
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
        <h3 class="text-xl font-bold text-gray-700 mb-2">Your wishlist is empty</h3>
        <a href="/sweetheaven/user/products.php" class="bg-rose-500 text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-rose-600 transition-colors mt-4 inline-block">Explore Products</a>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <?php foreach ($wishlist as $item): ?>
        <?php $imgSrc = $item['primary_image'] ? '/sweetheaven/'.$item['primary_image'] : '/sweetheaven/images/maincake.jpg'; ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-sm transition-shadow">
            <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-40 object-cover" alt="">
            <div class="p-4">
                <p class="font-bold text-gray-700 text-sm mb-1 line-clamp-1"><?= htmlspecialchars($item['name']) ?></p>
                <p class="text-rose-500 font-bold text-sm mb-3"><?= number_format($item['price']) ?> MMK</p>
                <div class="flex gap-2">
                    <button onclick="addToCart(<?= $item['id'] ?>)" class="flex-1 bg-rose-500 text-white text-xs font-semibold py-2 rounded-xl hover:bg-rose-600 transition-colors">Add to Cart</button>
                    <button onclick="removeFromWishlist(<?= $item['id'] ?>, this)" class="p-2 text-gray-300 hover:text-red-500 transition-colors">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div id="toast" class="hidden fixed bottom-6 right-6 bg-stone-800 text-white px-5 py-3 rounded-xl shadow-md text-sm font-medium z-50 items-center gap-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
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
            else { const img = document.createElement('img'); img.src = e.target.result; img.className='w-full h-full object-cover'; preview.replaceWith(img); }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function addToCart(productId) {
    fetch('/sweetheaven/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add&product_id=${productId}&qty=1`
    }).then(r=>r.json()).then(data=>{
        if (data.success) { showToast('Added to cart!'); const b=document.getElementById('cartBadge'); if(b){b.textContent=data.cart_count;b.classList.remove('hidden');} }
    });
}

function removeFromWishlist(productId, btn) {
    fetch('/sweetheaven/api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${productId}`
    }).then(r=>r.json()).then(data=>{
        if (data.success) {
            btn.closest('.bg-white').remove();
            showToast('Removed from wishlist');
            if (typeof updateWishlistBadge === 'function') updateWishlistBadge(data.wishlist_count);
        }
    });
}

function showToast(msg) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.remove('hidden'); t.classList.add('flex');
    setTimeout(()=>{ t.classList.add('hidden'); t.classList.remove('flex'); }, 3000);
}
</script>
</body>
</html>
