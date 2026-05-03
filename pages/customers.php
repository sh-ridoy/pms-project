<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $address= trim($_POST['address'] ?? '');

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO customers (name,phone,email,address) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $name, $phone, $email, $address);
        $stmt->execute();
        flashMessage('success', 'Customer added!');
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE customers SET name=?,phone=?,email=?,address=? WHERE id=?");
        $stmt->bind_param('ssssi', $name, $phone, $email, $address, $id);
        $stmt->execute();
        flashMessage('success', 'Customer updated!');
    }
    header('Location: customers.php'); exit;
}

if (isset($_GET['delete']) && hasRole('admin')) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM customers WHERE id=? AND id NOT IN (SELECT customer_id FROM sales)");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    flashMessage('success', 'Customer deleted.');
    header('Location: customers.php'); exit;
}

$customers = $conn->query("SELECT c.*, COUNT(s.id) sale_count, COALESCE(SUM(s.total),0) total_spent FROM customers c LEFT JOIN sales s ON c.id=s.customer_id GROUP BY c.id ORDER BY c.name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-people-fill me-2 text-success"></i>Customers</div>
        <div class="page-subtitle">Manage customer records</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#custModal">
        <i class="bi bi-plus-circle"></i> Add Customer
    </button>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-list-ul me-2"></i>Customer List <span class="badge bg-success ms-2"><?= count($customers) ?></span></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Total Orders</th><th>Total Spent</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php foreach ($customers as $i => $c): ?>
                <tr>
                    <td class="text-muted"><?= $i+1 ?></td>
                    <td class="fw-600"><?= htmlspecialchars($c['name']) ?></td>
                    <td><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                    <td><span class="badge badge-info"><?= $c['sale_count'] ?></span></td>
                    <td class="fw-600">৳<?= number_format($c['total_spent'], 2) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editCust(<?= htmlspecialchars(json_encode($c)) ?>)"><i class="bi bi-pencil"></i></button>
                        <?php if (hasRole('admin') && $c['sale_count'] == 0): ?>
                        <a href="customers.php?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete customer?"><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$customers): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No customers found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="custModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="custModalTitle"><i class="bi bi-person-plus me-2"></i>Add Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="custAction" value="add">
                <input type="hidden" name="id" id="custId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Full Name *</label><input type="text" name="name" id="cName" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" id="cPhone" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="cEmail" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Address</label><textarea name="address" id="cAddress" class="form-control" rows="2"></textarea></div>
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
function editCust(c) {
    document.getElementById('custAction').value = 'edit';
    document.getElementById('custModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Customer';
    document.getElementById('custId').value = c.id;
    document.getElementById('cName').value = c.name;
    document.getElementById('cPhone').value = c.phone || '';
    document.getElementById('cEmail').value = c.email || '';
    document.getElementById('cAddress').value = c.address || '';
    new bootstrap.Modal(document.getElementById('custModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
