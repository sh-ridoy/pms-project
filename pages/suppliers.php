<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $data = [
        'name'           => trim($_POST['name'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'phone'          => trim($_POST['phone'] ?? ''),
        'email'          => trim($_POST['email'] ?? ''),
        'address'        => trim($_POST['address'] ?? ''),
        'status'         => $_POST['status'] ?? 'active',
    ];
    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO suppliers (name,contact_person,phone,email,address,status) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssssss', ...array_values($data));
        $stmt->execute();
        flashMessage('success', 'Supplier added!');
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE suppliers SET name=?,contact_person=?,phone=?,email=?,address=?,status=? WHERE id=?");
        $vals = array_values($data);
        $vals[] = $id;
        $stmt->bind_param('ssssssi', ...$vals);
        $stmt->execute();
        flashMessage('success', 'Supplier updated!');
    }
    header('Location: suppliers.php'); exit;
}

if (isset($_GET['delete']) && hasRole('admin')) {
    $id = intval($_GET['delete']);
    $conn->query("UPDATE suppliers SET status='inactive' WHERE id=$id");
    flashMessage('success', 'Supplier deactivated.');
    header('Location: suppliers.php'); exit;
}

$suppliers = $conn->query("SELECT s.*, COUNT(m.id) med_count FROM suppliers s LEFT JOIN medicines m ON s.id=m.supplier_id GROUP BY s.id ORDER BY s.name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-truck me-2 text-success"></i>Suppliers</div>
        <div class="page-subtitle">Manage medicine suppliers</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#supModal">
        <i class="bi bi-plus-circle"></i> Add Supplier
    </button>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-list-ul me-2"></i>Supplier List</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>#</th><th>Company</th><th>Contact</th><th>Phone</th><th>Email</th><th>Medicines</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php foreach ($suppliers as $i => $s): ?>
                <tr>
                    <td class="text-muted"><?= $i+1 ?></td>
                    <td class="fw-600"><?= htmlspecialchars($s['name']) ?></td>
                    <td><?= htmlspecialchars($s['contact_person'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                    <td><span class="badge badge-info"><?= $s['med_count'] ?></span></td>
                    <td><span class="badge <?= $s['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editSup(<?= htmlspecialchars(json_encode($s)) ?>)"><i class="bi bi-pencil"></i></button>
                        <?php if (hasRole('admin')): ?>
                        <a href="suppliers.php?delete=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Deactivate supplier?"><i class="bi bi-slash-circle"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$suppliers): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No suppliers found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="supModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supModalTitle"><i class="bi bi-truck me-2"></i>Add Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="supAction" value="add">
                <input type="hidden" name="id" id="supId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Company Name *</label><input type="text" name="name" id="sName" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Contact Person</label><input type="text" name="contact_person" id="sContact" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="sPhone" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="sEmail" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="sStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                        <div class="col-12"><label class="form-label">Address</label><textarea name="address" id="sAddress" class="form-control" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-check-circle me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSup(s) {
    document.getElementById('supAction').value = 'edit';
    document.getElementById('supModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Supplier';
    document.getElementById('supId').value = s.id;
    document.getElementById('sName').value = s.name;
    document.getElementById('sContact').value = s.contact_person || '';
    document.getElementById('sPhone').value = s.phone || '';
    document.getElementById('sEmail').value = s.email || '';
    document.getElementById('sAddress').value = s.address || '';
    document.getElementById('sStatus').value = s.status;
    new bootstrap.Modal(document.getElementById('supModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
