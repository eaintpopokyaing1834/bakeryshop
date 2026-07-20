<?php
require_once __DIR__ . '/../middleware/admin_only_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lang.php';

$db = getDB();

// Ensure role ENUM includes 'cashier'
try {
    $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','customer','cashier') DEFAULT 'customer'");
} catch (PDOException $e) {
    // ignore
}

// Ensure status column exists on users table
try {
    $db->exec("ALTER TABLE users ADD COLUMN status ENUM('active','inactive') DEFAULT 'active' AFTER role");
} catch (PDOException $e) {
    // Column already exists — ignore
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_status'])) {
    header('Content-Type: application/json');
    $userId   = (int)$_POST['user_id'];
    $newStatus = $_POST['status'];

    if (!in_array($newStatus, ['active', 'inactive'])) {
        echo json_encode(['success' => false, 'msg' => 'Invalid status value.']);
        exit;
    }
    if ($userId === (int)$_SESSION['user_id']) {
        echo json_encode(['success' => false, 'msg' => 'You cannot change your own status.']);
        exit;
    }

    // Prevent changing status of admin users
    $checkRole = $db->prepare("SELECT role FROM users WHERE id = ?");
    $checkRole->execute([$userId]);
    $row = $checkRole->fetch();
    if (!$row) {
        echo json_encode(['success' => false, 'msg' => 'User not found.']);
        exit;
    }
    if ($row['role'] === 'admin') {
        echo json_encode(['success' => false, 'msg' => 'Cannot change the status of an admin account.']);
        exit;
    }

    $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$newStatus, $userId]);
    echo json_encode(['success' => true, 'status' => $newStatus]);
    exit;
}

// Handle edit admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit_admin'])) {
    header('Content-Type: application/json');
    $userId   = (int)$_POST['user_id'];
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'msg' => 'Name and email are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid email address.']);
        exit;
    }

    // Check email uniqueness (exclude current user)
    $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $userId]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'msg' => 'Email is already taken by another user.']);
        exit;
    }

    if (!empty($password)) {
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'msg' => 'Password must be at least 6 characters.']);
            exit;
        }
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET name=?, email=?, password=? WHERE id=?")->execute([$name, $email, $hashed, $userId]);
    } else {
        $db->prepare("UPDATE users SET name=?, email=? WHERE id=?")->execute([$name, $email, $userId]);
    }

    echo json_encode(['success' => true]);
    exit;
}

// Handle add new admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_admin'])) {
    header('Content-Type: application/json');

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $status   = $_POST['status'] ?? 'active';

    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'msg' => 'All fields are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid email address.']);
        exit;
    }
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'msg' => 'Password must be at least 6 characters.']);
        exit;
    }
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }

    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'msg' => 'A user with this email already exists.']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, 'admin', ?, NOW())");
    $stmt->execute([$name, $email, $hashed, $status]);

    $newId = $db->lastInsertId();
    echo json_encode([
        'success' => true,
        'user' => [
            'id'         => $newId,
            'name'       => $name,
            'email'      => $email,
            'role'       => 'admin',
            'status'     => $status,
            'created_at' => date('Y-m-d H:i:s'),
        ]
    ]);
    exit;
}

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

// FIXED: Variable separation logic to prevent collisions with admin_header overrides
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$currentRoleFilter = isset($_GET['role']) ? trim($_GET['role']) : 'all';

if (!in_array($currentRoleFilter, ['all', 'customer', 'admin'])) {
    $currentRoleFilter = 'all';
}

$where  = [];
$params = [];

if ($currentRoleFilter !== 'all') { 
    $where[] = "role = ?"; 
    $params[] = $currentRoleFilter; 
}
if ($search !== '')  { 
    $where[] = "(name LIKE ? OR email LIKE ?)"; 
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}
$whereSQL = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Count for pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM users" . $whereSQL);
$countStmt->execute($params);
$totalUsers = (int)$countStmt->fetchColumn();

$perPage     = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages  = max(1, (int)ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT * FROM users" . $whereSQL . " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->execute();
$users = $stmt->fetchAll();

$totalCustomers = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$totalAdmins    = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

$pageTitle = __('user_page_title');

// Prevent browser caching so filter tabs always reflect current state
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6 px-4">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= $totalCustomers + $totalAdmins ?></p>
            <p class="text-sm text-gray-400"><?= __('user_total_users') ?></p>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= $totalCustomers ?></p>
            <p class="text-sm text-gray-400"><?= __('user_customers') ?></p>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-rose-100 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= $totalAdmins ?></p>
            <p class="text-sm text-gray-400"><?= __('user_administrators') ?></p>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="px-4">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
        <div class="flex gap-2">
            <!-- All Users Button -->
            <a href="?role=all&search=<?= urlencode($search) ?>"
               class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors <?= $currentRoleFilter === 'all' ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
               <?= __('user_filter_all') ?>
            </a>
            
            <!-- Customers Button -->
            <a href="?role=customer&search=<?= urlencode($search) ?>"
               class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors <?= $currentRoleFilter === 'customer' ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
               <?= __('user_filter_customers') ?>
            </a>
            
            <!-- Admins Button -->
            <a href="?role=admin&search=<?= urlencode($search) ?>"
               class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors <?= $currentRoleFilter === 'admin' ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
               <?= __('user_filter_admins') ?>
            </a>
        </div>
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="role" value="<?= htmlspecialchars($currentRoleFilter) ?>">
            <input type="search" name="search" placeholder="<?= __('user_search_ph') ?>"
                value="<?= htmlspecialchars($search) ?>"
                class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 w-60">
            <button class="bg-rose-500 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-rose-600"><?= __('admin_search') ?></button>
        </form>
    </div>
</div>
</div>

<!-- Users Table -->
<section class="px-4">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100">
        <h3 class="font-bold text-gray-800"><?= __('user_heading') ?> <span class="text-gray-400 font-normal text-sm ml-2">(<?= $totalUsers ?> found)</span></h3>
        
        <!-- FIXED: Only render button explicitly on Admin filter -->
        <?php if ($currentRoleFilter === 'admin'): ?>
            <button onclick="openModal()" class="px-4 py-2 bg-rose-500 text-center text-white font-semibold rounded-xl hover:bg-rose-600 transition-colors shadow-md shadow-rose-100"><?= __('user_add_admin') ?></button>
        <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left">ID</th>
                    <th class="px-6 py-4 text-left">Name</th>
                    <th class="px-6 py-4 text-left"><?= __('admin_email') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_role') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_joined') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('user_col_status') ?></th>
                    <th class="px-6 py-4 text-right"><?= __('admin_actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php if (empty($users)): ?>
                <tr><td colspan="7" class="px-6 py-16 text-center text-gray-400"><?= __('user_no_users') ?></td></tr>
            <?php else: ?>
            <?php foreach ($users as $u): ?>
            <tr class="hover:bg-gray-50/50 transition-colors" id="user-row-<?= $u['id'] ?>">
                <td class="px-6 py-4 text-sm text-gray-400">#<?= $u['id'] ?></td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-pink-500 flex items-center justify-center text-white font-bold">
                            <?= strtoupper(substr($u['name'],0,1)) ?>
                        </div>
                        <p class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($u['name']) ?></p>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                <td class="px-6 py-4">
                    <span class="text-xs font-bold px-3 py-1 rounded-full
                        <?= $u['role'] === 'admin' ? 'bg-rose-100 text-rose-700' : 'bg-green-100 text-green-700' ?>">
                        <?= $u['role'] === 'customer' ? __('admin_customer') : ucfirst($u['role']) ?>
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-400"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                
                 <td class="px-6 py-4">
                    <?php if ($u['role'] === 'admin'): ?>
                        <span class="text-xs font-medium px-3 py-1.5 rounded-lg <?= ($u['status'] ?? 'active') === 'active' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' ?>">
                            <?= ($u['status'] ?? 'active') === 'active' ? __('admin_active') : __('admin_inactive') ?>
                        </span>
                    <?php else: ?>
                        <select onchange="toggleStatus(<?= $u['id'] ?>, this.value, this)"
                            class="text-xs font-medium px-3 py-1.5 rounded-lg border-0 focus:ring-2 focus:ring-rose-300 cursor-pointer <?= ($u['status'] ?? 'active') === 'active' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' ?>">
                            <option value="active" <?= ($u['status'] ?? 'active') === 'active' ? 'selected' : '' ?>><?= __('admin_active') ?></option>
                            <option value="inactive" <?= ($u['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>><?= __('admin_inactive') ?></option>
                        </select>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <button onclick='openEditModal(<?= json_encode(["id" => $u["id"], "name" => $u["name"], "email" => $u["email"], "role" => $u["role"]]) ?>)'
                            class="text-xs font-medium px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors">
                            <?= __('admin_edit') ?>
                        </button>
                        <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                        <button onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>')"
                            class="text-xs font-medium px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors">
                            <?= __('admin_delete') ?>
                        </button>
                        <?php endif; ?>
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
            <a href="?role=<?= urlencode($currentRoleFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"><?= __('admin_prev') ?></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?role=<?= urlencode($currentRoleFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $i === $page ? 'bg-rose-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?role=<?= urlencode($currentRoleFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"><?= __('admin_next') ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</section>

<!-- Add New Admin Modal -->
<div id="addAdminModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-800"><?= __('user_add_title') ?></h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="addAdminForm" class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_name') ?></label>
                    <input type="text" name="name" required placeholder="<?= __('user_ph_name') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_email') ?></label>
                    <input type="email" name="email" required placeholder="<?= __('user_ph_email') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_password') ?></label>
                    <input type="password" name="password" required minlength="6" placeholder="<?= __('user_ph_password') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_status') ?></label>
                    <select name="status"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 bg-white">
                        <option value="active"><?= __('admin_active') ?></option>
                        <option value="inactive"><?= __('admin_inactive') ?></option>
                    </select>
                </div>
                <div id="addAdminError" class="text-red-500 text-sm hidden"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModal()"
                        class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="addAdminBtn"
                        class="flex-1 px-4 py-2.5 rounded-xl bg-rose-500 text-white text-sm font-semibold hover:bg-rose-600 transition-colors">
                        <?= __('user_btn_add') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div id="editAdminModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeEditModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-800"><?= __('user_edit_title') ?></h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="editAdminForm" class="px-6 py-5 space-y-4">
                <input type="hidden" name="user_id" id="editUserId">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_name') ?></label>
                    <input type="text" name="name" id="editName" required placeholder="<?= __('user_ph_name') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_email') ?></label>
                    <input type="email" name="email" id="editEmail" required placeholder="<?= __('user_ph_email') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_password') ?> <span class="font-normal text-gray-400">(<?= __('user_hint_blank') ?>)</span></label>
                    <input type="password" name="password" id="editPassword" minlength="6" placeholder="<?= __('user_ph_password') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div id="editAdminError" class="text-red-500 text-sm hidden"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()"
                        class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="editAdminBtn"
                        class="flex-1 px-4 py-2.5 rounded-xl bg-blue-500 text-white text-sm font-semibold hover:bg-blue-600 transition-colors">
                        <?= __('user_btn_save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRole(userId, newRole) {
    const confirmMsg = newRole === 'admin' ? '<?= __('user_confirm_admin') ?>' : '<?= __('user_confirm_customer') ?>';
    if (!confirm(confirmMsg)) return;
    fetch('/sweetheaven/admin/user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax_role=1&user_id=${userId}&role=${newRole}`,
        credentials: 'same-origin'
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.msg); });
}

function deleteUser(userId, name) {
    if (!confirm('<?= __('user_confirm_delete') ?>'.replace('{name}', name))) return;
    fetch('/sweetheaven/admin/user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax_delete=1&user_id=${userId}`,
        credentials: 'same-origin'
    }).then(r => r.json()).then(d => {
        if (d.success) document.getElementById(`user-row-${userId}`).remove();
        else alert(d.msg);
    });
}

/* ---- Toggle User Status ---- */
function toggleStatus(userId, newStatus, selectEl) {
    const confirmMsg = newStatus === 'active' ? '<?= __('user_confirm_activate') ?>' : '<?= __('user_confirm_deactivate') ?>';
    if (!confirm(confirmMsg)) {
        selectEl.value = newStatus === 'active' ? 'inactive' : 'active';
        return;
    }
    fetch('/sweetheaven/admin/user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax_status=1&user_id=${userId}&status=${newStatus}`,
        credentials: 'same-origin'
    }).then(r => r.json()).then(d => {
        if (d.success) {
            selectEl.className = `text-xs font-medium px-3 py-1.5 rounded-lg border-0 focus:ring-2 focus:ring-rose-300 cursor-pointer ${newStatus === 'active' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'}`;
        } else {
            alert(d.msg);
            selectEl.value = newStatus === 'active' ? 'inactive' : 'active';
        }
    }).catch(() => {
        alert('<?= __('user_error_request') ?>');
        selectEl.value = newStatus === 'active' ? 'inactive' : 'active';
    });
}

/* ---- Edit Admin Modal ---- */
function openEditModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editName').value = user.name;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editPassword').value = '';
    document.getElementById('editAdminError').classList.add('hidden');
    document.getElementById('editAdminModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editAdminModal').classList.add('hidden');
}

document.getElementById('editAdminForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('editAdminBtn');
    const errDiv = document.getElementById('editAdminError');
    const form = new FormData(this);
    form.append('ajax_edit_admin', '1');

    btn.textContent = '<?= __('user_btn_saving') ?>';
    btn.disabled = true;

    fetch('/sweetheaven/admin/user.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            closeEditModal();
            location.reload();
        } else {
            errDiv.textContent = d.msg;
            errDiv.classList.remove('hidden');
        }
    })
    .catch(() => {
        errDiv.textContent = '<?= __('user_error_generic') ?>';
        errDiv.classList.remove('hidden');
    })
    .finally(() => {
        btn.textContent = '<?= __('user_btn_save') ?>';
        btn.disabled = false;
    });
});

/* ---- Add New Admin Modal ---- */
function openModal() {
    document.getElementById('addAdminModal').classList.remove('hidden');
    document.getElementById('addAdminForm').reset();
    document.getElementById('addAdminError').classList.add('hidden');
}

function closeModal() {
    document.getElementById('addAdminModal').classList.add('hidden');
}

document.getElementById('addAdminForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('addAdminBtn');
    const errDiv = document.getElementById('addAdminError');
    const form = new FormData(this);
    form.append('ajax_add_admin', '1');

    btn.textContent = '<?= __('user_btn_adding') ?>';
    btn.disabled = true;

    fetch('/sweetheaven/admin/user.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            closeModal();
            location.reload();
        } else {
            errDiv.textContent = d.msg;
            errDiv.classList.remove('hidden');
        }
    })
    .catch(() => {
        errDiv.textContent = '<?= __('user_error_generic') ?>';
        errDiv.classList.remove('hidden');
    })
    .finally(() => {
        btn.textContent = '<?= __('user_btn_add') ?>';
        btn.disabled = false;
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>