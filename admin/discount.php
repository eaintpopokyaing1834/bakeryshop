<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lang.php';

$db = getDB();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$message = $error = '';
$activeTab = $_GET['tab'] ?? 'discounts';

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Block cashiers from any write actions
if (!$isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => 'Cashiers do not have permission to modify discounts.']);
    exit;
}

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax'];
    if ($action === 'delete_discount') {
        $id = (int) $_POST['id'];
        $db->prepare("UPDATE products SET discount_id=NULL WHERE discount_id=?")->execute([$id]);
        $db->prepare("DELETE FROM discounts WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
    }
    exit;
}

// Save discount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_discount'])) {
    $id = (int) ($_POST['discount_id'] ?? 0);
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $value = (float) $_POST['value'];
    $status = (int) ($_POST['status'] ?? 1);

    if ($id > 0) {
        $db->prepare("UPDATE discounts SET name=?, type=?, value=?, status=? WHERE id=?")
            ->execute([$name, $type, $value, $status, $id]);
    } else {
        $db->prepare("INSERT INTO discounts (name,type,value,status) VALUES (?,?,?,?)")
            ->execute([$name, $type, $value, $status]);
    }
    $_SESSION['flash_message'] = 'Discount saved successfully!';
    header('Location: ?tab=discounts');
    exit;
}

$discounts = $db->query("SELECT d.*, (SELECT COUNT(*) FROM products WHERE discount_id=d.id) AS product_count FROM discounts d ORDER BY d.created_at DESC")->fetchAll();

$pageTitle = __('discount_page_title');
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
    ✅ <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<section class="px-4">
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-bold text-gray-800"><?= __('discount_heading') ?> <span class="text-gray-400 font-normal text-sm ml-2">(<?= count($discounts) ?>)</span></h3>
        <?php if ($isAdmin): ?>
        <button onclick="openDiscountModal()"
            class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <?= __('discount_add') ?>
        </button>
        <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left"><?= __('admin_id') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_name') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('discount_col_type') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('discount_col_value') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_status') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('discount_col_products') ?></th>
                    <th class="px-6 py-4 text-left"><?= __('admin_actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($discounts as $d): ?>
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-6 py-4 font-mono text-gray-500 text-sm"><?= $d['id'] ?></td>
                    <td class="px-6 py-4 font-semibold text-gray-700 text-sm"><?= htmlspecialchars($d['name']) ?></td>
                    <td class="px-6 py-4 text-sm">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $d['type'] === 'percentage' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' ?>">
                            <?= $d['type'] === 'percentage' ? __('discount_type_percent') : __('discount_type_fixed') ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 font-bold text-gray-700 text-sm">
                        <?= $d['type'] === 'percentage' ? $d['value'] . '%' : formatPrice($d['value']) ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $d['status'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                            <?= $d['status'] ? __('admin_active') : __('admin_inactive') ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500"><?= $d['product_count'] ?></td>
                    <td class="px-6 py-4">
                        <?php if ($isAdmin): ?>
                        <div class="flex items-center gap-2">
                            <button onclick="editDiscount(<?= htmlspecialchars(json_encode($d)) ?>)"
                                class="text-blue-600 hover:text-blue-800 text-sm font-medium px-3 py-1.5 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"><?= __('admin_edit') ?></button>
                            <button onclick="deleteDiscount(<?= $d['id'] ?>, '<?= addslashes($d['name']) ?>')"
                                class="text-red-600 hover:text-red-800 text-sm font-medium px-3 py-1.5 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"><?= __('admin_delete') ?></button>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-gray-400"><?= __('product_view_only') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Discount Modal -->
<div id="discountModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-sm w-full max-w-md">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800" id="discountModalTitle"><?= __('discount_add_title') ?></h3>
            <button onclick="closeModal('discountModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="save_discount" value="1">
            <input type="hidden" name="discount_id" id="discountId" value="0">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('discount_label_name') ?></label>
                <input type="text" name="name" id="discountName" required placeholder="<?= __('discount_ph_name') ?>"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('discount_label_type') ?></label>
                <select name="type" id="discountType" required
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm"
                    onchange="document.getElementById('valueUnit').textContent = this.value === 'percentage' ? '%' : '<?= __('admin_mmk') ?>'">
                    <option value="percentage"><?= __('discount_type_percent') ?></option>
                    <option value="fixed"><?= __('discount_type_fixed') ?></option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('discount_label_value') ?></label>
                <div class="flex items-center gap-2">
                    <input type="number" name="value" id="discountValue" required min="0" step="0.01" placeholder="<?= __('discount_ph_value') ?>"
                        class="flex-1 px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                    <span id="valueUnit" class="text-sm font-bold text-gray-500 w-12">%</span>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('discount_label_status') ?></label>
                <select name="status" id="discountStatus"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                    <option value="1"><?= __('admin_active') ?></option>
                    <option value="0"><?= __('admin_inactive') ?></option>
                </select>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="flex-1 bg-rose-500 hover:bg-rose-600 text-white font-semibold py-3 rounded-xl transition-colors"><?= __('discount_save') ?></button>
                <button type="button" onclick="closeModal('discountModal')"
                    class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-xl transition-colors"><?= __('admin_cancel') ?></button>
            </div>
        </form>
    </div>
</div>
</section>

<script>
function openDiscountModal(data = null) {
    document.getElementById('discountId').value = data ? data.id : 0;
    document.getElementById('discountName').value = data ? data.name : '';
    document.getElementById('discountType').value = data ? data.type : 'percentage';
    document.getElementById('discountValue').value = data ? data.value : '';
    document.getElementById('discountStatus').value = data ? data.status : '1';
    document.getElementById('discountModalTitle').textContent = data ? '<?= __('discount_edit_title') ?>' : '<?= __('discount_add_title') ?>';
    document.getElementById('valueUnit').textContent = (data ? data.type : 'percentage') === 'percentage' ? '%' : '<?= __('admin_mmk') ?>';
    document.getElementById('discountModal').classList.remove('hidden');
}
function editDiscount(data) { openDiscountModal(data); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function deleteDiscount(id, name) {
    if (!confirm(`<?= __('discount_confirm_delete') ?>`.replace('{name}', name))) return;
    fetch('/sweetheaven/admin/discount.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax=delete_discount&id=${id}`
    }).then(r => r.json()).then(d => { if (d.success) window.location.href = window.location.href; });
}

document.getElementById('discountModal')?.addEventListener('click', e => { if (e.target === document.getElementById('discountModal')) closeModal('discountModal'); });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
