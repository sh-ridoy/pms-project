<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $fields = ['name','generic_name','unit','purchase_price','sale_price','stock_qty','min_stock','batch_number','manufacture_date','expiry_date','description','status'];
        $data = [];
        foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');
        $data['category_id'] = intval($_POST['category_id'] ?? 0) ?: null;
        $data['supplier_id']  = intval($_POST['supplier_id'] ?? 0) ?: null;
        $data['manufacture_date'] = $data['manufacture_date'] ?: null;
        $data['expiry_date'] = $data['expiry_date'] ?: null;

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO medicines (name,generic_name,category_id,supplier_id,unit,purchase_price,sale_price,stock_qty,min_stock,batch_number,manufacture_date,expiry_date,description,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('ssiisddiiissss', $data['name'],$data['generic_name'],$data['category_id'],$data['supplier_id'],$data['unit'],$data['purchase_price'],$data['sale_price'],$data['stock_qty'],$data['min_stock'],$data['batch_number'],$data['manufacture_date'],$data['expiry_date'],$data['description'],$data['status']);
            $stmt->execute();
            flashMessage('success', 'Medicine added successfully!');
        } else {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE medicines SET name=?,generic_name=?,category_id=?,supplier_id=?,unit=?,purchase_price=?,sale_price=?,stock_qty=?,min_stock=?,batch_number=?,manufacture_date=?,expiry_date=?,description=?,status=? WHERE id=?");
            $stmt->bind_param('ssiisddiiissssi', $data['name'],$data['generic_name'],$data['category_id'],$data['supplier_id'],$data['unit'],$data['purchase_price'],$data['sale_price'],$data['stock_qty'],$data['min_stock'],$data['batch_number'],$data['manufacture_date'],$data['expiry_date'],$data['description'],$data['status'],$id);
            $stmt->execute();
            flashMessage('success', 'Medicine updated successfully!');
        }
        header('Location: medicines.php'); exit;
    }
}

if (isset($_GET['delete']) && hasRole('admin')) {
    $id = intval($_GET['delete']);
    $conn->query("UPDATE medicines SET status='inactive' WHERE id=$id");
    flashMessage('success', 'Medicine deactivated.');
    header('Location: medicines.php'); exit;
}

// Fetch
$search = trim($_GET['search'] ?? '');
$catFilter = intval($_GET['cat'] ?? 0);
$where = "WHERE m.status='active'";
$params = [];
$types = '';
if ($search) { $where .= " AND (m.name LIKE ? OR m.generic_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'ss'; }
if ($catFilter) { $where .= " AND m.category_id=?"; $params[] = $catFilter; $types .= 'i'; }

$sql = "SELECT m.*, c.name cat_name, s.name sup_name FROM medicines m LEFT JOIN categories c ON m.category_id=c.id LEFT JOIN suppliers s ON m.supplier_id=s.id $where ORDER BY m.name";
if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $medicines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $medicines = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$suppliers  = $conn->query("SELECT * FROM suppliers WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-capsule me-2 text-success"></i>Medicines</div>
        <div class="page-subtitle">Manage medicine inventory</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle"></i> Add Medicine
    </button>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by name or generic name..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="cat" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-success"><i class="bi bi-search me-1"></i>Search</button>
                <a href="medicines.php" class="btn btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>Medicine List</span>
        <span class="badge bg-success"><?= count($medicines) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>#</th><th>Name</th><th>Category</th><th>Stock</th><th>Buy Price</th><th>Sale Price</th><th>Expiry</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php foreach ($medicines as $i => $m):
                    $expired = isExpired($m['expiry_date'] ?? '');
                    $expiring = !$expired && isExpiringSoon($m['expiry_date'] ?? '9999-12-31');
                    $rowClass = $expired ? 'expiry-critical' : ($expiring ? 'expiry-warning' : '');
                ?>
                <tr class="<?= $rowClass ?>">
                    <td class="text-muted" style="font-size:12px;"><?= $i+1 ?></td>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($m['name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($m['generic_name'] ?? '') ?> &middot; <?= htmlspecialchars($m['unit']) ?></small>
                    </td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($m['cat_name'] ?? 'N/A') ?></span></td>
                    <td class="<?= $m['stock_qty'] <= $m['min_stock'] ? 'stock-low' : 'stock-ok' ?>">
                        <?= $m['stock_qty'] ?>
                        <?php if ($m['stock_qty'] <= $m['min_stock']): ?><i class="bi bi-exclamation-circle-fill ms-1"></i><?php endif; ?>
                    </td>
                    <td>৳<?= number_format($m['purchase_price'], 2) ?></td>
                    <td class="fw-600">৳<?= number_format($m['sale_price'], 2) ?></td>
                    <td>
                        <?php if ($m['expiry_date']): ?>
                        <span class="<?= $expired ? 'text-danger fw-600' : ($expiring ? 'text-warning fw-600' : 'text-muted') ?>">
                            <?= date('d M Y', strtotime($m['expiry_date'])) ?>
                            <?php if ($expired): ?><i class="bi bi-x-circle-fill ms-1"></i><?php elseif ($expiring): ?><i class="bi bi-clock-fill ms-1"></i><?php endif; ?>
                        </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="badge badge-active"><?= ucfirst($m['status']) ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editMedicine(<?= htmlspecialchars(json_encode($m)) ?>)"><i class="bi bi-pencil"></i></button>
                        <?php if (hasRole('admin')): ?>
                        <a href="medicines.php?delete=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Deactivate this medicine?"><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$medicines): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No medicines found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle"><i class="bi bi-capsule me-2"></i>Add Medicine</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="editId" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Medicine Name *</label>
                            <input type="text" name="name" id="fName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Generic Name</label>
                            <input type="text" name="generic_name" id="fGeneric" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="fCat" class="form-select">
                                <option value="">Select</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" id="fSup" class="form-select">
                                <option value="">Select</option>
                                <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit</label>
                            <select name="unit" id="fUnit" class="form-select">
                                <option value="tablet">Tablet</option><option value="capsule">Capsule</option>
                                <option value="syrup">Syrup</option><option value="injection">Injection</option>
                                <option value="cream">Cream</option><option value="bottle">Bottle</option>
                                <option value="pcs">Pcs</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Purchase Price (৳)</label>
                            <input type="number" name="purchase_price" id="fBuy" class="form-control" step="0.01" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sale Price (৳)</label>
                            <input type="number" name="sale_price" id="fSale" class="form-control" step="0.01" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="stock_qty" id="fStock" class="form-control" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Min Stock</label>
                            <input type="number" name="min_stock" id="fMin" class="form-control" value="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Batch Number</label>
                            <input type="text" name="batch_number" id="fBatch" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Manufacture Date</label>
                            <input type="date" name="manufacture_date" id="fMfg" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" id="fExp" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="fDesc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="fStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-check-circle me-1"></i>Save Medicine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMedicine(m) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Medicine';
    document.getElementById('editId').value = m.id;
    document.getElementById('fName').value = m.name;
    document.getElementById('fGeneric').value = m.generic_name || '';
    document.getElementById('fCat').value = m.category_id || '';
    document.getElementById('fSup').value = m.supplier_id || '';
    document.getElementById('fUnit').value = m.unit;
    document.getElementById('fBuy').value = m.purchase_price;
    document.getElementById('fSale').value = m.sale_price;
    document.getElementById('fStock').value = m.stock_qty;
    document.getElementById('fMin').value = m.min_stock;
    document.getElementById('fBatch').value = m.batch_number || '';
    document.getElementById('fMfg').value = m.manufacture_date || '';
    document.getElementById('fExp').value = m.expiry_date || '';
    document.getElementById('fDesc').value = m.description || '';
    document.getElementById('fStatus').value = m.status;
    new bootstrap.Modal(document.getElementById('addModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
