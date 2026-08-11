<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lang.php';

$db = getDB();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Handle approve/reject with price and note (cashier only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($isAdmin) {
        echo json_encode(['success' => false, 'error' => 'View-only access']);
        exit;
    }
    $requestId = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $adminPrice = !empty($_POST['admin_price']) ? (float)$_POST['admin_price'] : null;
    $adminNote = trim($_POST['admin_note'] ?? '');

    if ($action === 'approve') {
        if (!$adminPrice) {
            echo json_encode(['success' => false, 'error' => __('customize_price_error')]);
            exit;
        }
        $db->prepare("UPDATE customize_requests SET status='approved', admin_price=?, admin_note=? WHERE id=?")
            ->execute([$adminPrice, $adminNote ?: null, $requestId]);

        // Notify customer
        $req = $db->prepare("SELECT user_id FROM customize_requests WHERE id=?");
        $req->execute([$requestId]);
        $reqData = $req->fetch();
        if ($reqData) {
            $db->prepare("INSERT INTO notifications (user_id, type, message, is_seen) VALUES (?, 'customize_approved', ?, 0)")
                ->execute([
                    $reqData['user_id'],
                    "Your cake customization request #" . str_pad($requestId, 4, '0', STR_PAD_LEFT) . " has been approved! Price: " . formatPrice($adminPrice) . ". You can now proceed to order."
                ]);
        }
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE customize_requests SET status='rejected', admin_note=? WHERE id=?")
            ->execute([$adminNote ?: null, $requestId]);

        $req = $db->prepare("SELECT user_id FROM customize_requests WHERE id=?");
        $req->execute([$requestId]);
        $reqData = $req->fetch();
        if ($reqData) {
            $db->prepare("INSERT INTO notifications (user_id, type, message, is_seen) VALUES (?, 'customize_rejected', ?, 0)")
                ->execute([
                    $reqData['user_id'],
                    "Your cake customization request #" . str_pad($requestId, 4, '0', STR_PAD_LEFT) . " has been rejected. " . ($adminNote ? "Reason: " . $adminNote : "Unfortunately, we cannot accommodate this design at this time.")
                ]);
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$where = [];
$params = [];
if ($statusFilter !== 'all') { $where[] = "cr.status = ?"; $params[] = $statusFilter; }
if ($search !== '') { $where[] = "(u.name LIKE ? OR cr.id LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count for pagination
$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM customize_requests cr
    JOIN users u ON cr.user_id = u.id
    $whereSQL
");
$countStmt->execute($params);
$totalRequests = (int)$countStmt->fetchColumn();

$perPage     = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages  = max(1, (int)ceil($totalRequests / $perPage));
$page = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT cr.*, u.name AS customer_name, u.email AS customer_email
    FROM customize_requests cr
    JOIN users u ON cr.user_id = u.id
    $whereSQL
    ORDER BY cr.created_at DESC
    LIMIT $perPage OFFSET $offset
");
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->execute();
$requests = $stmt->fetchAll();

$pageTitle = __('customize_page_title_admin');
require_once __DIR__ . '/../includes/admin_header.php';

$statusColors = [
    'pending' => 'bg-amber-100 text-amber-700 border-amber-200',
    'approved' => 'bg-green-100 text-green-700 border-green-200',
    'rejected' => 'bg-red-100 text-red-700 border-red-200',
    'ordered' => 'bg-blue-100 text-blue-700 border-blue-200',
];
?>

<style>
    [x-cloak] { display: none !important; }
</style>

<!-- Filters Bar -->
 <div class="px-4">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
        <div class="flex flex-wrap gap-2">
            <?php foreach (['all', 'pending', 'approved', 'rejected', 'ordered'] as $s): ?>
                <a href="?status=<?= $s ?>&search=<?= urlencode($search) ?>"
                    class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors
                    <?= $statusFilter === $s ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    <?= $s === 'all' ? __('admin_all') : ucfirst(__("status_$s")) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="search" name="search" placeholder="<?= __('customize_search_ph') ?>"
                value="<?= htmlspecialchars($search) ?>"
                class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 w-60">
            <button class="bg-rose-500 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-rose-600"><?= __('admin_search') ?></button>
        </form>
    </div>
</div>
</div>
<!-- Requests Table -->
 <section class="px-4">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-bold text-gray-800"><?= __('customize_requests') ?> <span class="text-gray-400 font-normal text-sm ml-2">(<?= localizeNumber($totalRequests) ?> <?= __('admin_total') ?>)</span></h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left"><?= __('customize_col_id') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_customer') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('customize_col_details') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('customize_col_delivery') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_status') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_date') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('customize_col_actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($requests)): ?>
                    <tr><td colspan="7" class="px-6 py-16 text-center text-gray-400">
                        <p class="text-4xl mb-3">🎂</p>
                        <?= __('customize_no_requests') ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors" id="request-row-<?= $req['id'] ?>">
                            <td class="px-6 py-4 font-mono text-rose-500 font-bold text-sm">#<?= localizeNumber(str_pad($req['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 font-bold text-sm">
                                        <?= strtoupper(substr($req['customer_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($req['customer_name']) ?></p>
                                        <p class="text-xs text-gray-400"><?= htmlspecialchars($req['customer_email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <p><span class="font-semibold"><?= __('customize_size_label') ?></span> <?= htmlspecialchars($req['size']) ?></p>
                                <p><span class="font-semibold"><?= __('customize_flavor_label') ?></span> <?= htmlspecialchars($req['flavor']) ?></p>
                                <?php if ($req['color']): ?><p><span class="font-semibold"><?= __('customize_color_label') ?></span> <?= htmlspecialchars($req['color']) ?></p><?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= localizeDate($req['delivery_date'], 'M j, Y') ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColors[$req['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                    <?= ucfirst(__("status_{$req['status']}")) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-400"><?= localizeDate($req['created_at'], 'M j, Y') ?></td>
                            <td class="px-6 py-4">
                                <button onclick="toggleRequestDetails(<?= $req['id'] ?>)"
                                    class="text-sm text-rose-500 hover:text-rose-600 font-medium flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    <?= __('customize_details_btn') ?>
                                </button>
                            </td>
                        </tr>
                        <tr id="details-<?= $req['id'] ?>" class="hidden bg-rose-50/30">
                            <td colspan="7" class="px-8 py-4">
                                <div class="grid sm:grid-cols-2 gap-6">
                                    <div>
                                        <h4 class="font-bold text-gray-700 mb-3 text-sm uppercase tracking-wider"><?= __('customize_request_details') ?></h4>
                                        <div class="space-y-2 text-sm">
                                            <p><span class="font-semibold text-gray-600"><?= __('customize_size_label') ?></span> <?= htmlspecialchars($req['size']) ?></p>
                                            <p><span class="font-semibold text-gray-600"><?= __('customize_flavor_label') ?></span> <?= htmlspecialchars($req['flavor']) ?></p>
                                            <?php if ($req['color']): ?><p><span class="font-semibold text-gray-600"><?= __('customize_color_theme') ?></span> <?= htmlspecialchars($req['color']) ?></p><?php endif; ?>
                                            <?php if ($req['cake_message']): ?><p><span class="font-semibold text-gray-600"><?= __('customize_message_label') ?></span> <?= htmlspecialchars($req['cake_message']) ?></p><?php endif; ?>
                                            <p><span class="font-semibold text-gray-600"><?= __('customize_delivery_date') ?></span> <?= localizeDate($req['delivery_date'], 'M j, Y') ?></p>
                                            <?php if ($req['additional_notes']): ?><p><span class="font-semibold text-gray-600"><?= __('customize_notes_label') ?></span> <?= htmlspecialchars($req['additional_notes']) ?></p><?php endif; ?>
                                            <?php if ($req['admin_price']): ?><p><span class="font-semibold text-gray-600"><?= __('customize_price_label') ?></span> <?= formatPrice($req['admin_price']) ?></p><?php endif; ?>
                                            <?php if ($req['admin_note']): ?><p><span class="font-semibold text-gray-600"><?= __('customize_admin_note_label') ?></span> <?= htmlspecialchars($req['admin_note']) ?></p><?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($req['reference_image']): ?>
                                            <h4 class="font-bold text-gray-700 mb-3 text-sm uppercase tracking-wider"><?= __('customize_reference_image') ?></h4>
                                            <a href="/sweetheaven/<?= htmlspecialchars($req['reference_image']) ?>" target="_blank">
                                                <img src="/sweetheaven/<?= htmlspecialchars($req['reference_image']) ?>"
                                                    class="w-48 h-48 object-cover rounded-xl border border-stone-200">
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($req['status'] === 'pending'): ?>
                                            <?php if ($isAdmin): ?>
                                                <div class="mt-4 p-4 bg-amber-50 rounded-xl border border-amber-200">
                                                    <p class="text-sm font-semibold text-amber-700">⏳ <?= __('status_pending') ?></p>
                                                    <p class="text-xs text-amber-600 mt-1"><?= __('customize_no_action_view_only') ?></p>
                                                </div>
                                            <?php else: ?>
                                            <div class="mt-4 p-4 bg-white rounded-xl border border-stone-200">
                                                <h4 class="font-bold text-gray-700 mb-3 text-sm uppercase tracking-wider"><?= __('customize_review_request') ?></h4>
                                                <div class="space-y-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-600 mb-1"><?= __('customize_set_price') ?></label>
                                                        <input type="number" id="price-<?= $req['id'] ?>" placeholder="<?= __('customize_ph_price') ?>"
                                                            class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-600 mb-1"><?= __('customize_admin_note') ?> <span class="text-gray-400">(<?= __('customize_optional') ?>)</span></label>
                                                        <textarea id="note-<?= $req['id'] ?>" rows="2" placeholder="<?= __('customize_ph_note') ?>"
                                                            class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <button onclick="handleAction(<?= $req['id'] ?>, 'approve')"
                                                            class="flex-1 text-xs font-semibold px-3 py-2 rounded-lg bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition-colors">
                                                            <?= __('admin_approve') ?>
                                                        </button>
                                                        <button onclick="handleAction(<?= $req['id'] ?>, 'reject')"
                                                            class="flex-1 text-xs font-semibold px-3 py-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors">
                                                            <?= __('admin_reject') ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php elseif ($req['status'] === 'approved'): ?>
                                            <div class="mt-4 p-4 bg-green-50 rounded-xl border border-green-200">
                                                <p class="text-sm font-semibold text-green-700">✅ <?= __('status_approved') ?></p>
                                                <?php if ($req['admin_price']): ?>
                                                    <p class="text-sm text-green-600 mt-1"><?= __('customize_price_label') ?> <?= formatPrice($req['admin_price']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($req['status'] === 'rejected'): ?>
                                            <div class="mt-4 p-4 bg-red-50 rounded-xl border border-red-200">
                                                <p class="text-sm font-semibold text-red-700">❌ <?= __('status_rejected') ?></p>
                                            </div>
                                        <?php elseif ($req['status'] === 'ordered'): ?>
                                            <div class="mt-4 p-4 bg-blue-50 rounded-xl border border-blue-200">
                                                <p class="text-sm font-semibold text-blue-700">📦 <?= __('status_ordered') ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-sm text-gray-400"><?= sprintf(__('admin_page_of'), $page, $totalPages) ?></p>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">← <?= __('admin_prev') ?></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $i === $page ? 'bg-rose-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"><?= __('admin_next') ?> →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</section>

<script>
function toggleRequestDetails(id) {
    const row = document.getElementById(`details-${id}`);
    row.classList.toggle('hidden');
}

function handleAction(requestId, action) {
    const price = document.getElementById(`price-${requestId}`)?.value;
    const note = document.getElementById(`note-${requestId}`)?.value;

    if (action === 'approve' && !price) {
        showToast('<?= __("customize_toast_price") ?>');
        return;
    }

    fetch('/sweetheaven/admin/customize.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=${action}&request_id=${requestId}&admin_price=${price || ''}&admin_note=${encodeURIComponent(note || '')}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('<?= __("customize_toast_approved") ?>');
            location.reload();
        } else {
            showToast(data.error || '<?= __("customize_toast_error") ?>');
        }
    });
}

function showToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    t.className = 'fixed bottom-6 right-6 bg-stone-800 text-white px-5 py-3 rounded-xl shadow-md text-sm font-medium z-50 transition-all duration-300';
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
