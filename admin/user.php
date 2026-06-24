<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();

// Handle role toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_role'])) {
    header('Content-Type: application/json');
    $userId  = (int)$_POST['user_id'];
    $newRole = $_POST['role'];
    if (in_array($newRole, ['admin','customer']) && $userId !== (int)$_SESSION['user_id']) {
        $db->prepare("UPDATE users SET role=? WHERE id=?")->execute([$newRole, $userId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Cannot modify own role']);
    }
    exit;
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $userId = (int)$_POST['user_id'];
    if ($userId !== (int)$_SESSION['user_id']) {
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Cannot delete your own account']);
    }
    exit;
}

$search = trim($_GET['search'] ?? '');
$role   = $_GET['role'] ?? 'all';
$where  = [];
$params = [];

if ($role !== 'all') { $where[] = "role = ?"; $params[] = $role; }
if ($search !== '')  { $where[] = "(name LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = $db->prepare("SELECT * FROM users $whereSQL ORDER BY created_at DESC");
$users->execute($params);
$users = $users->fetchAll();

$totalCustomers = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$totalAdmins    = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= $totalCustomers + $totalAdmins ?></p>
            <p class="text-sm text-gray-400">Total Users</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= $totalCustomers ?></p>
            <p class="text-sm text-gray-400">Customers</p>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-rose-100 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= $totalAdmins ?></p>
            <p class="text-sm text-gray-400">Administrators</p>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
        <div class="flex gap-2">
            <?php foreach (['all' => 'All Users', 'customer' => 'Customers', 'admin' => 'Admins'] as $r => $label): ?>
            <a href="?role=<?= $r ?>&search=<?= urlencode($search) ?>"
               class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors
               <?= $role === $r ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
               <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
            <input type="search" name="search" placeholder="Search name or email..."
                value="<?= htmlspecialchars($search) ?>"
                class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 w-60">
            <button class="bg-rose-500 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-rose-600">Search</button>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-bold text-gray-800">Users <span class="text-gray-400 font-normal text-sm ml-2">(<?= count($users) ?> found)</span></h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left">User</th>
                    <th class="px-6 py-4 text-left">Email</th>
                    <th class="px-6 py-4 text-left">Role</th>
                    <th class="px-6 py-4 text-left">Joined</th>
                    <th class="px-6 py-4 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php if (empty($users)): ?>
                <tr><td colspan="5" class="px-6 py-16 text-center text-gray-400">No users found</td></tr>
            <?php else: ?>
            <?php foreach ($users as $u): ?>
            <tr class="hover:bg-gray-50/50 transition-colors" id="user-row-<?= $u['id'] ?>">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-stone-300 to-stone-600 flex items-center justify-center text-white font-bold">
                            <?= strtoupper(substr($u['name'],0,1)) ?>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($u['name']) ?></p>
                            <p class="text-xs text-gray-400">ID: #<?= $u['id'] ?></p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                <td class="px-6 py-4">
                    <span class="text-xs font-bold px-3 py-1 rounded-full
                        <?= $u['role'] === 'admin' ? 'bg-rose-100 text-rose-700' : 'bg-green-100 text-green-700' ?>">
                        <?= ucfirst($u['role']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-400"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                <td class="px-6 py-4">
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <div class="flex items-center gap-2">
                        <button onclick="toggleRole(<?= $u['id'] ?>, '<?= $u['role'] === 'admin' ? 'customer' : 'admin' ?>')"
                            class="text-xs font-medium px-3 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg transition-colors">
                            → <?= $u['role'] === 'admin' ? 'Make Customer' : 'Make Admin' ?>
                        </button>
                        <button onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')"
                            class="text-xs font-medium px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors">
                            Delete
                        </button>
                    </div>
                    <?php else: ?>
                    <span class="text-xs text-gray-400 italic">Your Account</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleRole(userId, newRole) {
    const label = newRole === 'admin' ? 'an Admin' : 'a Customer';
    if (!confirm(`Make this user ${label}?`)) return;
    fetch('/sweetheaven/admin/user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax_role=1&user_id=${userId}&role=${newRole}`
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.msg); });
}

function deleteUser(userId, name) {
    if (!confirm(`Delete user "${name}"? This action cannot be undone.`)) return;
    fetch('/sweetheaven/admin/user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax_delete=1&user_id=${userId}`
    }).then(r => r.json()).then(d => {
        if (d.success) document.getElementById(`user-row-${userId}`).remove();
        else alert(d.msg);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
