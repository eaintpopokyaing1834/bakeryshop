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

// Handle add cashier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_cashier'])) {
    header('Content-Type: application/json');
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

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

    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'msg' => 'A user with this email already exists.']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, 'cashier', 'active', NOW())");
    $stmt->execute([$name, $email, $hashed]);

    $newId = $db->lastInsertId();
    echo json_encode([
        'success' => true,
        'user' => [
            'id'         => $newId,
            'name'       => $name,
            'email'      => $email,
            'role'       => 'cashier',
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]
    ]);
    exit;
}

// Handle edit cashier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit_cashier'])) {
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

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_status'])) {
    header('Content-Type: application/json');
    $userId    = (int)$_POST['user_id'];
    $newStatus = $_POST['status'];

    if (!in_array($newStatus, ['active', 'inactive'])) {
        echo json_encode(['success' => false, 'msg' => 'Invalid status value.']);
        exit;
    }

    $db->prepare("UPDATE users SET status=? WHERE id=? AND role='cashier'")->execute([$newStatus, $userId]);
    echo json_encode(['success' => true, 'status' => $newStatus]);
    exit;
}

// Handle delete cashier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete'])) {
    header('Content-Type: application/json');
    $userId = (int)$_POST['user_id'];
    $db->prepare("DELETE FROM users WHERE id=? AND role='cashier'")->execute([$userId]);
    echo json_encode(['success' => true]);
    exit;
}

// Fetch cashiers for display
$cashiers = $db->query("SELECT * FROM users WHERE role='cashier' ORDER BY created_at DESC")->fetchAll();

$pageTitle = __('cashier_page_title');

// Translation strings for JavaScript
$_js = [
    'adding'     => __('user_btn_adding'),
    'saving'     => __('user_btn_saving'),
    'addBtn'     => __('cashier_btn_add'),
    'saveBtn'    => __('user_btn_save'),
    'errorGeneric' => __('cashier_error_generic'),
    'errorRequest' => __('cashier_error_request'),
    'confirmDelete' => __('cashier_confirm_delete'),
];
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6 px-4">
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= count($cashiers) ?></p>
            <p class="text-sm text-gray-400"><?= __('cashier_total') ?></p>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= count(array_filter($cashiers, fn($c) => $c['status'] === 'active')) ?></p>
            <p class="text-sm text-gray-400"><?= __('admin_active') ?></p>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-red-100 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-800"><?= count(array_filter($cashiers, fn($c) => $c['status'] !== 'active')) ?></p>
            <p class="text-sm text-gray-400"><?= __('admin_inactive') ?></p>
        </div>
    </div>
</div>

<!-- Cashiers Table -->
<section class="px-4">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="flex justify-between px-6 py-4 border-b border-gray-100">
        <h3 class="font-bold text-gray-800"><?= __('cashier_heading') ?> <span class="text-gray-400 font-normal text-sm ml-2">(<?= localizeNumber(count($cashiers)) ?> <?= __('admin_total') ?>)</span></h3>
        <button onclick="openAddModal()" class="p-2 bg-rose-500 text-center text-white font-semibold rounded-xl"> <?= __('cashier_add') ?></button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left">ID</th>
                    <th class="px-6 py-4 text-left"><?= __('cashier_heading') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_email') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_joined') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('user_col_status') ?></th>
                    <th class="px-6 py-4 text-right"><?= __('admin_actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php if (empty($cashiers)): ?>
                <tr><td colspan="6" class="px-6 py-16 text-center text-gray-400"><?= __('cashier_no_cashiers') ?></td></tr>
            <?php else: ?>
            <?php foreach ($cashiers as $c): ?>
            <tr class="hover:bg-gray-50/50 transition-colors" id="cashier-row-<?= $c['id'] ?>">
                <td class="px-6 py-4 text-sm text-gray-600 font-mono"><?= localizeNumber($c['id']) ?></td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                            <?= strtoupper(substr($c['name'],0,1)) ?>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($c['name']) ?></p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($c['email']) ?></td>
                <td class="px-6 py-4 text-sm text-gray-400"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                <td class="px-6 py-4">
                    <select onchange="toggleStatus(<?= $c['id'] ?>, this.value, this)"
                        class="text-xs font-medium px-3 py-1.5 rounded-lg border-0 focus:ring-2 focus:ring-rose-300 cursor-pointer <?= ($c['status'] ?? 'active') === 'active' ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' ?>">
                        <option value="active" <?= ($c['status'] ?? 'active') === 'active' ? 'selected' : '' ?>><?= __('admin_active') ?></option>
                        <option value="inactive" <?= ($c['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>><?= __('admin_inactive') ?></option>
                    </select>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <button onclick='openEditModal(<?= json_encode(["id" => $c["id"], "name" => $c["name"], "email" => $c["email"]]) ?>)'
                            class="text-xs font-medium px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors">
                            <?= __('admin_edit') ?>
                        </button>
                        <button onclick="deleteCashier(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>')"
                            class="text-xs font-medium px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors">
                            <?= __('admin_delete') ?>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</section>

<!-- Add Cashier Modal -->
<div id="addCashierModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeAddModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-800"><?= __('cashier_add_title') ?></h3>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="addCashierForm" class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_name') ?></label>
                    <input type="text" name="name" required placeholder="<?= __('user_ph_name') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_email') ?></label>
                    <input type="email" name="email" required placeholder="<?= __('cashier_ph_email') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_password') ?></label>
                    <input type="password" name="password" required minlength="6" placeholder="<?= __('user_ph_password') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div id="addError" class="text-red-500 text-sm hidden"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeAddModal()"
                        class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        <?= __('admin_cancel') ?>
                    </button>
                    <button type="submit" id="addBtn"
                        class="flex-1 px-4 py-2.5 rounded-xl bg-rose-500 text-white text-sm font-semibold hover:bg-rose-600 transition-colors">
                        <?= __('cashier_btn_add') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Cashier Modal -->
<div id="editCashierModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeEditModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-800"><?= __('cashier_edit_title') ?></h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="editCashierForm" class="px-6 py-5 space-y-4">
                <input type="hidden" name="user_id" id="editUserId">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_name') ?></label>
                    <input type="text" name="name" id="editName" required placeholder="<?= __('user_ph_name') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_email') ?></label>
                    <input type="email" name="email" id="editEmail" required placeholder="<?= __('cashier_ph_email') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1"><?= __('user_label_password') ?> <span class="font-normal text-gray-400">(<?= __('user_hint_blank') ?>)</span></label>
                    <input type="password" name="password" id="editPassword" minlength="6" placeholder="<?= __('user_ph_password') ?>"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
                <div id="editError" class="text-red-500 text-sm hidden"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()"
                        class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        <?= __('admin_cancel') ?>
                    </button>
                    <button type="submit" id="editBtn"
                        class="flex-1 px-4 py-2.5 rounded-xl bg-blue-500 text-white text-sm font-semibold hover:bg-blue-600 transition-colors">
                        <?= __('user_btn_save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* ---- Add Cashier ---- */
function openAddModal() {
    document.getElementById('addCashierForm').reset();
    document.getElementById('addError').classList.add('hidden');
    document.getElementById('addCashierModal').classList.remove('hidden');
}
function closeAddModal() {
    document.getElementById('addCashierModal').classList.add('hidden');
}

document.getElementById('addCashierForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('addBtn');
    const errDiv = document.getElementById('addError');
    const form = new FormData(this);
    form.append('ajax_add_cashier', '1');
    btn.textContent = <?= json_encode($_js['adding']) ?>;
    btn.disabled = true;

    fetch('/sweetheaven/admin/manage_cashiers.php', { method: 'POST', body: form, credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
        if (d.success) { closeAddModal(); location.reload(); }
        else { errDiv.textContent = d.msg; errDiv.classList.remove('hidden'); }
    })
    .catch(() => { errDiv.textContent = <?= json_encode($_js['errorGeneric']) ?>; errDiv.classList.remove('hidden'); })
    .finally(() => { btn.textContent = <?= json_encode($_js['addBtn']) ?>; btn.disabled = false; });
});

/* ---- Edit Cashier ---- */
function openEditModal(cashier) {
    document.getElementById('editUserId').value = cashier.id;
    document.getElementById('editName').value = cashier.name;
    document.getElementById('editEmail').value = cashier.email;
    document.getElementById('editPassword').value = '';
    document.getElementById('editError').classList.add('hidden');
    document.getElementById('editCashierModal').classList.remove('hidden');
}
function closeEditModal() {
    document.getElementById('editCashierModal').classList.add('hidden');
}

document.getElementById('editCashierForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('editBtn');
    const errDiv = document.getElementById('editError');
    const form = new FormData(this);
    form.append('ajax_edit_cashier', '1');
    btn.textContent = <?= json_encode($_js['saving']) ?>;
    btn.disabled = true;

    fetch('/sweetheaven/admin/manage_cashiers.php', { method: 'POST', body: form, credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
        if (d.success) { closeEditModal(); location.reload(); }
        else { errDiv.textContent = d.msg; errDiv.classList.remove('hidden'); }
    })
    .catch(() => { errDiv.textContent = <?= json_encode($_js['errorGeneric']) ?>; errDiv.classList.remove('hidden'); })
    .finally(() => { btn.textContent = <?= json_encode($_js['saveBtn']) ?>; btn.disabled = false; });
});

/* ---- Status Toggle ---- */
function toggleStatus(userId, newStatus, selectEl) {
    fetch('/sweetheaven/admin/manage_cashiers.php', {
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
        alert(<?= json_encode($_js['errorRequest']) ?>);
        selectEl.value = newStatus === 'active' ? 'inactive' : 'active';
    });
}

/* ---- Delete Cashier ---- */
function deleteCashier(userId, name) {
    if (!confirm(`${<?= json_encode($_js['confirmDelete']) ?>} "${name}"?`)) return;
    fetch('/sweetheaven/admin/manage_cashiers.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax_delete=1&user_id=${userId}`,
        credentials: 'same-origin'
    }).then(r => r.json()).then(d => {
        if (d.success) document.getElementById(`cashier-row-${userId}`).remove();
        else alert(d.msg);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
