<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// Create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS customer_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle actions
$action = $_GET['action'] ?? '';
$reviewId = (int) ($_GET['id'] ?? 0);

if ($action === 'approve' && $reviewId) {
    $db->prepare("UPDATE customer_reviews SET status='approved' WHERE id=?")->execute([$reviewId]);
    header('Location: /sweetheaven/admin/review.php?msg=Approved');
    exit;
}
if ($action === 'reject' && $reviewId) {
    $db->prepare("UPDATE customer_reviews SET status='rejected' WHERE id=?")->execute([$reviewId]);
    header('Location: /sweetheaven/admin/review.php?msg=Rejected');
    exit;
}
if ($action === 'delete' && $reviewId) {
    $db->prepare("DELETE FROM customer_reviews WHERE id=?")->execute([$reviewId]);
    header('Location: /sweetheaven/admin/review.php?msg=Deleted');
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$where = '';
$params = [];
if ($statusFilter !== 'all') {
    $where = 'WHERE status = ?';
    $params[] = $statusFilter;
}

// Count for pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM customer_reviews $where");
$countStmt->execute($params);
$totalReviews = (int)$countStmt->fetchColumn();

$perPage     = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages  = max(1, (int)ceil($totalReviews / $perPage));
$page = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT * FROM customer_reviews $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->execute();
$reviews = $stmt->fetchAll();

$msg = htmlspecialchars($_GET['msg'] ?? '');

$pageTitle = 'Review Management';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($msg): ?>
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-3 rounded-xl text-sm font-medium">
        Review <?= $msg ?> successfully.
    </div>
<?php endif; ?>

<section class="px-4">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <h3 class="font-bold text-gray-800">Customer Reviews(<?= count($reviews) ?>)</h3>
        <div class="flex gap-2">
            <?php foreach (['all', 'pending', 'approved', 'rejected'] as $s): ?>
                <a href="?status=<?= $s ?>"
                    class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors
                    <?= $statusFilter === $s ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    <?= ucfirst($s) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left">Customer</th>
                    <th class="px-6 py-4 text-left">Email</th>
                    <th class="px-6 py-4 text-left">Review</th>
                    <th class="px-6 py-4 text-left">Date</th>
                    <th class="px-6 py-4 text-left">Status</th>
                    <th class="px-6 py-4 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center text-gray-400">
                            <p class="text-4xl mb-3">💬</p>
                            No reviews found
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 font-bold text-sm">
                                        <?= strtoupper(substr($r['name'], 0, 1)) ?>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($r['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($r['email']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs">
                                <p class="line-clamp-2"><?= htmlspecialchars($r['message']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-400 whitespace-nowrap">
                                <?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-semibold px-3 py-1.5 rounded-full
                                    <?= $r['status'] === 'approved' ? 'bg-green-100 text-green-700' : '' ?>
                                    <?= $r['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : '' ?>
                                    <?= $r['status'] === 'rejected' ? 'bg-red-100 text-red-700' : '' ?>">
                                    <?= ucfirst($r['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2">
                                    <?php if ($r['status'] !== 'approved'): ?>
                                        <a href="?action=approve&id=<?= $r['id'] ?>"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition-colors">Approve</a>
                                    <?php endif; ?>
                                    <?php if ($r['status'] !== 'rejected'): ?>
                                        <a href="?action=reject&id=<?= $r['id'] ?>"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors">Reject</a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?= $r['id'] ?>" onclick="return confirm('Delete this review?')"
                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-stone-100 text-stone-600 hover:bg-stone-200 transition-colors">Delete</a>
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
        <p class="text-sm text-gray-400">Page <?= $page ?> of <?= $totalPages ?></p>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">← Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $i === $page ? 'bg-rose-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">Next →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</section>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>