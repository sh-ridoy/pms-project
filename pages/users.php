<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $fullName  = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $role      = $_POST['role'] ?? 'cashier';
    $status    = $_POST['status'] ?? 'active';

    if ($action === 'add') {
        $password = $_POST['password'] ?? '';
        if (!$password) { flashMessage('error', 'Password is required.'); header('Location: users.php'); exit; }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username,password,full_name,email,phone,role,status) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('sssssss', $username, $hashed, $fullName, $email, $phone, $role, $status);
        if ($stmt->execute()) flashMessage('success', 'User created successfully!');
        else flashMessage('error', 'Username already exists or error occurred.');
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $password = $_POST['password'] ?? '';
        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name=?,email=?,phone=?,role=?,status=?,password=? WHERE id=?");
            $stmt->bind_param('ssssssi', $fullName, $email, $phone, $role, $status, $hashed, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?,email=?,phone=?,role=?,status=? WHERE id=?");
            $stmt->bind_param('sssssi', $fullName, $email, $phone, $role, $status, $id);
        }
        $stmt->execute();
        flashMessage('success', 'User updated successfully!');
    }
    header('Location: users.php'); exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id !== $_SESSION['user_id']) {
        $conn->query("UPDATE users SET status='inactive' WHERE id=$id");
        flashMessage('success', 'User deactivated.');
    } else {
        flashMessage('error', 'You cannot deactivate yourself.');
    }
    header('Location: users.php'); exit;
}

$users = $conn->query("SELECT u.*, COUNT(s.id) sale_count FROM users u LEFT JOIN sales s ON u.id=s.user_id GROUP BY u.id ORDER BY u.full_name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-person-badge-fill me-2 text-success"></i>User Management</div>
        <div class="page-subtitle">Manage staff accounts and roles</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#userModal">
        <i class="bi bi-person-plus-fill"></i> Add User
    </button>
</div>

<!-- Role Summary Cards -->
<div class="row g-3 mb-4">
    <?php
    $roles = ['admin' => ['icon' => 'shield-lock-fill', 'color' => '#7c3aed', 'bg' => '#ede9fe'],
              'pharmacist' => ['icon' => 'capsule', 'color' => '#16a34a', 'bg' => '#dcfce7'],
              'cashier' => ['icon' => 'cash-coin', 'color' => '#2563eb', 'bg' => '#dbeafe']];
    foreach ($roles as $role => $info):
        $cnt = count(array_filter($users, fn($u) => $u['role'] === $role));
    ?>
    <div class="col-md-4">
        <div class="stat-card" style="--card-accent:<?= $info['color'] ?>;--card-bg:<?= $info['bg'] ?>;--card-color:<?= $info['color'] ?>;">
            <div class="stat-icon"><i class="bi bi-<?= $info['icon'] ?>"></i></div>
            <div class="stat-value"><?= $cnt ?></div>
            <div class="stat-label"><?= ucfirst($role) ?>s</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-people-fill me-2"></i>Staff List <span class="badge bg-success ms-2"><?= count($users) ?></span></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>#</th><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Role</th><th>Sales</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td class="text-muted"><?= $i+1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:34px;height:34px;background:<?= ['admin'=>'#ede9fe','pharmacist'=>'#dcfce7','cashier'=>'#dbeafe'][$u['role']] ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:<?= ['admin'=>'#7c3aed','pharmacist'=>'#16a34a','cashier'=>'#2563eb'][$u['role']] ?>">
                                <?= strtoupper(substr($u['full_name'],0,1)) ?>
                            </div>
                            <span class="fw-600"><?= htmlspecialchars($u['full_name']) ?></span>
                            <?php if ($u['id'] == $_SESSION['user_id']): ?><span class="badge bg-secondary" style="font-size:10px;">You</span><?php endif; ?>
                        </div>
                    </td>
                    <td><code style="background:#f0f4f8;padding:2px 8px;border-radius:4px;font-size:13px;"><?= htmlspecialchars($u['username']) ?></code></td>
                    <td class="text-muted"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td><span class="badge badge-info"><?= $u['sale_count'] ?> sales</span></td>
                    <td><span class="badge <?= $u['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= ucfirst($u['status']) ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)"><i class="bi bi-pencil"></i></button>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <a href="users.php?delete=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Deactivate user '<?= addslashes($u['full_name']) ?>'?"><i class="bi bi-slash-circle"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No users found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle"><i class="bi bi-person-plus-fill me-2"></i>Add User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="userAction" value="add">
                <input type="hidden" name="id" id="userId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" id="uFullName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" id="uUsername" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="uEmail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="uPhone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Role *</label>
                            <select name="role" id="uRole" class="form-select">
                                <option value="cashier">Cashier</option>
                                <option value="pharmacist">Pharmacist</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="uStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password <span id="passNote" class="text-muted" style="font-size:11px;">(required)</span></label>
                            <input type="password" name="password" id="uPassword" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-check-circle me-1"></i>Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(u) {
    document.getElementById('userAction').value = 'edit';
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit User';
    document.getElementById('userId').value = u.id;
    document.getElementById('uFullName').value = u.full_name;
    document.getElementById('uUsername').value = u.username;
    document.getElementById('uUsername').setAttribute('readonly', true);
    document.getElementById('uEmail').value = u.email || '';
    document.getElementById('uPhone').value = u.phone || '';
    document.getElementById('uRole').value = u.role;
    document.getElementById('uStatus').value = u.status;
    document.getElementById('uPassword').value = '';
    document.getElementById('passNote').textContent = '(leave blank to keep current)';
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

document.getElementById('userModal').addEventListener('hidden.bs.modal', () => {
    document.getElementById('userAction').value = 'add';
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus-fill me-2"></i>Add User';
    document.getElementById('uUsername').removeAttribute('readonly');
    document.getElementById('passNote').textContent = '(required)';
    document.getElementById('userModal').querySelector('form').reset();
});
</script>

<?php include '../includes/footer.php'; ?>
