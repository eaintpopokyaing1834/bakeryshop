<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lang.php';
$db = getDB();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Handle actions (cashier only)
$action = $_GET['action'] ?? '';
$reviewId = (int) ($_GET['id'] ?? 0);

if ($action === 'approve' && $reviewId) {
    if ($isAdmin) {
        header('Location: /sweetheaven/admin/review.php?msg=View-only+access');
        exit;
    }
    $db->prepare("UPDATE reviews SET status='approved' WHERE id=?")->execute([$reviewId]);
    header('Location: /sweetheaven/admin/review.php?msg=Approved');
    exit;
}
if ($action === 'reject' && $reviewId) {
    if ($isAdmin) {
        header('Location: /sweetheaven/admin/review.php?msg=View-only+access');
        exit;
    }
    $db->prepare("UPDATE reviews SET status='rejected' WHERE id=?")->execute([$reviewId]);
    header('Location: /sweetheaven/admin/review.php?msg=Rejected');
    exit;
}
if ($action === 'delete' && $reviewId) {
    if ($isAdmin) {
        header('Location: /sweetheaven/admin/review.php?msg=View-only+access');
        exit;
    }
    $db->prepare("DELETE FROM reviews WHERE id=?")->execute([$reviewId]);
    header('Location: /sweetheaven/admin/review.php?msg=Deleted');
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$where = '';
$params = [];
if ($statusFilter !== 'all') {
    $where = 'WHERE r.status = ?';
    $params[] = $statusFilter;
} else {
    $where = 'WHERE 1=1';
}

// Count for pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM reviews r $where");
$countStmt->execute($params);
$totalReviews = (int)$countStmt->fetchColumn();

$perPage     = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages  = max(1, (int)ceil($totalReviews / $perPage));
$page = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT r.*, u.name AS customer_name, u.email AS customer_email, r.comment AS review_text
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    $where
    ORDER BY r.created_at DESC
    LIMIT $perPage OFFSET $offset
");
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->execute();
$reviews = $stmt->fetchAll();

$msg = htmlspecialchars($_GET['msg'] ?? '');

$pageTitle = __('review_page_title');
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($msg): ?>
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-3 rounded-xl text-sm font-medium">
        <?= __('review_heading') ?> <?= $msg ?>
    </div>
<?php endif; ?>

<section class="px-4">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <h3 class="font-bold text-gray-800"><?= __('review_heading') ?> (<?= count($reviews) ?>)</h3>
        <div class="flex gap-2">
            <?php foreach (['all', 'pending', 'approved', 'rejected'] as $s): ?>
                <a href="?status=<?= $s ?>"
                    class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors
                    <?= $statusFilter === $s ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    <?= match($s) { 'pending' => __('status_pending'), 'approved' => __('status_approved'), 'rejected' => __('status_rejected'), default => ucfirst($s) } ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left"><?= __('admin_customer') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('review_col_email') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('review_col_review') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_date') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_status') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center text-gray-400">
                            <p class="text-4xl mb-3">💬</p>
                            <?= __('review_no_reviews') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 font-bold text-sm">
                                        <?= strtoupper(substr($r['customer_name'], 0, 1)) ?>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($r['customer_name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($r['customer_email']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs">
                                <?php if ($r['rating']): ?>
                                    <div class="flex items-center gap-1 mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <svg class="w-3 h-3 <?= $i <= $r['rating'] ? 'text-amber-400' : 'text-gray-200' ?>" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        <?php endfor; ?>
                                        <span class="text-xs text-gray-400 ml-1"><?= $r['rating'] ?>/5</span>
                                    </div>
                                <?php endif; ?>
                                <p class="line-clamp-2"><?= htmlspecialchars($r['review_text']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-400 whitespace-nowrap">
                                <?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-semibold px-3 py-1.5 rounded-full
                                    <?= $r['status'] === 'approved' ? 'bg-green-100 text-green-700' : '' ?>
                                    <?= $r['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : '' ?>
                                    <?= $r['status'] === 'rejected' ? 'bg-red-100 text-red-700' : '' ?>">
                                    <?= match($r['status']) { 'pending' => __('status_pending'), 'approved' => __('status_approved'), 'rejected' => __('status_rejected'), default => $r['status'] } ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($isAdmin): ?>
                                    <span class="text-xs text-gray-400 italic">View only</span>
                                <?php else: ?>
                                <div class="flex gap-2">
                                    <?php if ($r['status'] !== 'approved'): ?>
                                        <a href="?action=approve&id=<?= $r['id'] ?>"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition-colors"><?= __('review_action_approve') ?></a>
                                    <?php endif; ?>
                                    <?php if ($r['status'] !== 'rejected'): ?>
                                        <a href="?action=reject&id=<?= $r['id'] ?>"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors"><?= __('review_action_reject') ?></a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?= $r['id'] ?>" onclick="return confirm('Delete this review?')"
                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-stone-100 text-stone-600 hover:bg-stone-200 transition-colors"><?= __('review_action_delete') ?></a>
                                </div>
                                <?php endif; ?>
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
            <a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">← <?= __('admin_prev') ?></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $i === $page ? 'bg-rose-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"><?= __('admin_next') ?> →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</section>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>