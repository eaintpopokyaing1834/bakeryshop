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
    if ($isAdmin) { header('Location: /sweetheaven/admin/review.php?msg=View-only+access'); exit; }
    $db->prepare("UPDATE reviews SET status='approved' WHERE id=?")->execute([$reviewId]);
    header('Location: /sweetheaven/admin/review.php?msg=Approved'); exit;
}
if ($action === 'reject' && $reviewId) {
    if ($isAdmin) { header('Location: /sweetheaven/admin/review.php?msg=View-only+access'); exit; }
    $db->prepare("UPDATE reviews SET status='rejected' WHERE id=?")->execute([$reviewId]);
    header('Location: /sweetheaven/admin/review.php?msg=Rejected'); exit;
}
if ($action === 'delete' && $reviewId) {
    if ($isAdmin) { header('Location: /sweetheaven/admin/review.php?msg=View-only+access'); exit; }
    $db->prepare("DELETE FROM reviews WHERE id=?")->execute([$reviewId]);
    header('Location: /sweetheaven/admin/review.php?msg=Deleted'); exit;
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

$countStmt = $db->prepare("SELECT COUNT(*) FROM reviews r $where");
$countStmt->execute($params);
$totalReviews = (int)$countStmt->fetchColumn();

$perPage     = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages  = max(1, (int)ceil($totalReviews / $perPage));
$page = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT r.*, u.name AS customer_name, u.email AS customer_email, r.comment AS review_text,
           p.name AS product_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN products p ON r.product_id = p.id
    $where
    ORDER BY r.created_at DESC
    LIMIT $perPage OFFSET $offset
");
foreach ($params as $i => $val) { $stmt->bindValue($i + 1, $val); }
$stmt->execute();
$reviews = $stmt->fetchAll();

$msg = htmlspecialchars($_GET['msg'] ?? '');

$pageTitle = __('review_page_title');
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($msg): ?>
    <div class="flash-success mb-5 mx-4">
        <svg class="w-4 h-4 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <?= __('review_heading') ?> — <?= $msg ?>
    </div>
<?php endif; ?>

<section class="px-4 pb-6">
<div class="section-card">
    <!-- Header with filter pills -->
    <div class="section-card-header flex-wrap gap-3">
        <div>
            <h3><?= __('review_heading') ?></h3>
            <p class="sub"><?= localizeNumber($totalReviews) ?> <?= __('admin_total') ?></p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php foreach (['all', 'pending', 'approved', 'rejected'] as $s): ?>
                <a href="?status=<?= $s ?>"
                   class="filter-pill <?= $statusFilter === $s ? 'filter-pill-active' : 'filter-pill-default' ?>">
                    <?= match($s) {
                        'pending'  => __('status_pending'),
                        'approved' => __('status_approved'),
                        'rejected' => __('status_rejected'),
                        default    => ucfirst($s)
                    } ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= __('admin_customer') ?></th>
                    <th><?= __('review_col_email') ?></th>
                    <th>Product</th>
                    <th><?= __('review_col_review') ?></th>
                    <th><?= __('admin_date') ?></th>
                    <th><?= __('admin_status') ?></th>
                    <th><?= __('admin_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <span class="empty-state-icon">💬</span>
                                <p class="empty-state-text"><?= __('review_no_reviews') ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <div class="user-avatar"><?= strtoupper(substr($r['customer_name'], 0, 1)) ?></div>
                                    <span class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($r['customer_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="text-sm text-gray-500"><?= htmlspecialchars($r['customer_email']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($r['product_name'])): ?>
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-rose-700 bg-rose-50 border border-rose-100 px-2 py-1 rounded-lg">
                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                        </svg>
                                        <?= htmlspecialchars($r['product_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-300 italic">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="max-w-xs">
                                <?php if ($r['rating']): ?>
                                <div class="flex items-center gap-0.5 mb-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg class="w-3 h-3 <?= $i <= $r['rating'] ? 'text-amber-400' : 'text-gray-200' ?>" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    <?php endfor; ?>
                                    <span class="text-xs text-gray-400 ml-1"><?= localizeNumber($r['rating']) ?>/<?= localizeNumber(5) ?></span>
                                </div>
                                <?php endif; ?>
                                <p class="text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($r['review_text']) ?></p>
                            </td>
                            <td>
                                <span class="text-sm text-gray-400 whitespace-nowrap"><?= localizeDate($r['created_at'], 'M j, Y') ?></span>
                            </td>
                            <td>
                                <span class="status-badge badge-<?= $r['status'] ?>">
                                    <?= match($r['status']) {
                                        'pending'  => __('status_pending'),
                                        'approved' => __('status_approved'),
                                        'rejected' => __('status_rejected'),
                                        default    => $r['status']
                                    } ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isAdmin): ?>
                                    <span class="text-xs text-gray-400 italic">View only</span>
                                <?php else: ?>
                                <div class="flex items-center gap-1.5">
                                    <?php if ($r['status'] !== 'approved'): ?>
                                        <a href="?action=approve&id=<?= $r['id'] ?>" class="btn-approve">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <?= __('review_action_approve') ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($r['status'] !== 'rejected'): ?>
                                        <a href="?action=reject&id=<?= $r['id'] ?>" class="btn-reject">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            <?= __('review_action_reject') ?>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?= $r['id'] ?>" onclick="return confirm('Delete this review?')" class="btn-delete">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        <?= __('review_action_delete') ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
        <p class="pagination-info"><?= sprintf(__('admin_page_of'), $page, $totalPages) ?></p>
        <div class="pagination-pills">
            <?php if ($page > 1): ?>
                <a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $page - 1 ?>" class="pg-btn pg-btn-nav">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <?= __('admin_prev') ?>
                </a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $i ?>" class="pg-btn <?= $i === $page ? 'pg-btn-active' : 'pg-btn-default' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $page + 1 ?>" class="pg-btn pg-btn-nav">
                    <?= __('admin_next') ?>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</section>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>