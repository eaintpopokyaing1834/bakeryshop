<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lang.php';
$db = getDB();
$role = $_SESSION['role'] ?? '';

// Handle delete (admin only)
$action = $_GET['action'] ?? '';
$msgId  = (int) ($_GET['id'] ?? 0);

if ($action === 'delete' && $msgId && $role === 'admin') {
    $db->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$msgId]);
    header('Location: /sweetheaven/admin/contact_messages.php?msg=Deleted');
    exit;
}

// Pagination
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 10;
$totalRows = (int) $db->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$messages = $db->prepare("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$messages->execute();
$messages = $messages->fetchAll();

$msg = htmlspecialchars($_GET['msg'] ?? '');

$pageTitle = __('admin_nav_contact_messages');
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($msg): ?>
    <div class="flash-success mb-5 mx-4">
        <svg class="w-4 h-4 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <?= $msg ?>
    </div>
<?php endif; ?>

<section class="px-4 pb-6">
<div class="section-card">
    <!-- Card Header -->
    <div class="section-card-header">
        <div>
            <h3><?= __('admin_nav_contact_messages') ?></h3>
            <p class="sub"><?= $totalRows ?> <?= __('admin_total') ?? 'total messages' ?></p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-full">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <?= $totalRows ?> messages
        </span>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?= __('admin_customer') ?></th>
                    <th><?= __('review_col_email') ?></th>
                    <th>Phone</th>
                    <th>Message</th>
                    <th><?= __('admin_date') ?></th>
                    <?php if ($role === 'admin'): ?>
                        <th><?= __('admin_actions') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="<?= $role === 'admin' ? 7 : 6 ?>">
                            <div class="empty-state">
                                <span class="empty-state-icon">📭</span>
                                <p class="empty-state-text">No contact messages yet.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <tr>
                            <td>
                                <span class="text-xs font-mono text-gray-400">#<?= $m['id'] ?></span>
                            </td>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <div class="user-avatar"><?= strtoupper(substr($m['name'], 0, 1)) ?></div>
                                    <span class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($m['name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($m['email']) ?>" class="text-sm text-blue-500 hover:text-blue-600 hover:underline"><?= htmlspecialchars($m['email']) ?></a>
                            </td>
                            <td>
                                <span class="text-sm text-gray-500"><?= htmlspecialchars($m['phone'] ?: '—') ?></span>
                            </td>
                            <td class="max-w-xs">
                                <p class="text-sm text-gray-600 line-clamp-2 leading-relaxed"><?= htmlspecialchars($m['message']) ?></p>
                            </td>
                            <td>
                                <span class="text-sm text-gray-400 whitespace-nowrap"><?= date('M j, Y', strtotime($m['created_at'])) ?></span>
                            </td>
                            <?php if ($role === 'admin'): ?>
                                <td>
                                    <a href="?action=delete&id=<?= $m['id'] ?>"
                                       onclick="return confirm('Delete this message?')"
                                       class="btn-delete">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        <?= __('review_action_delete') ?>
                                    </a>
                                </td>
                            <?php endif; ?>
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
                <a href="?page=<?= $page - 1 ?>" class="pg-btn pg-btn-nav">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <?= __('admin_prev') ?>
                </a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="pg-btn <?= $i === $page ? 'pg-btn-active' : 'pg-btn-default' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="pg-btn pg-btn-nav">
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
