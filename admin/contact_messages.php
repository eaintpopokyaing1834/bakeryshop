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
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-3 rounded-xl text-sm font-medium">
        <?= $msg ?>
    </div>
<?php endif; ?>

<section class="px-4">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-bold text-gray-800"><?= __('admin_nav_contact_messages') ?> (<?= $totalRows ?>)</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-yellow-100 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left">ID</th>
                    <th class="px-6 py-4 text-left"><?= __('admin_customer') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('review_col_email') ?></th>
                    <th class="px-6 py-4 text-left">Phone</th>
                    <th class="px-6 py-4 text-left">Message</th>
                    <th class="px-6 py-4 text-left"><?= __('admin_date') ?></th>
                    <?php if ($role === 'admin'): ?>
                        <th class="px-6 py-4 text-left"><?= __('admin_actions') ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="<?= $role === 'admin' ? 7 : 6 ?>" class="px-6 py-16 text-center text-gray-400">
                            <p class="text-4xl mb-3">📭</p>
                            <p>No contact messages yet.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 text-sm text-gray-400">#<?= $m['id'] ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 font-bold text-sm">
                                        <?= strtoupper(substr($m['name'], 0, 1)) ?>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($m['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($m['email']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($m['phone'] ?: '—') ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs">
                                <p class="line-clamp-2"><?= htmlspecialchars($m['message']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-400 whitespace-nowrap">
                                <?= date('M j, Y', strtotime($m['created_at'])) ?>
                            </td>
                            <?php if ($role === 'admin'): ?>
                                <td class="px-6 py-4">
                                    <a href="?action=delete&id=<?= $m['id'] ?>" onclick="return confirm('Delete this message?')"
                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-stone-100 text-stone-600 hover:bg-stone-200 transition-colors"><?= __('review_action_delete') ?></a>
                                </td>
                            <?php endif; ?>
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
                <a href="?page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">← <?= __('admin_prev') ?></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $i === $page ? 'bg-rose-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"><?= __('admin_next') ?> →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</section>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
