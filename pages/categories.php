<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');

    if ($action === 'add' && $name) {
        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?,?)");
        $stmt->bind_param('ss', $name, $desc);
        $stmt->execute();
        flashMessage('success', 'Category added!');
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
        $stmt->bind_param('ssi', $name, $desc, $id);
        $stmt->execute();
        flashMessage('success', 'Category updated!');
    }
    header('Location: categories.php'); exit;
}

if (isset($_GET['delete']) && hasRole('admin')) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    flashMessage('success', 'Category deleted.');
    header('Location: categories.php'); exit;
}

$categories = $conn->query("SELECT c.*, COUNT(m.id) med_count FROM categories c LEFT JOIN medicines m ON c.id=m.category_id GROUP BY c.id ORDER BY c.name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-tags-fill me-2 text-success"></i>Categories</div>
        <div class="page-subtitle">Manage medicine categories</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#catModal">
        <i class="bi bi-plus-circle"></i> Add Category
    </button>
</div>

<div class="row g-3">
    <?php foreach ($categories as $cat): ?>
    <div class="col-md-4 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-700 mb-1"><?= htmlspecialchars($cat['name']) ?></div>
                        <div class="text-muted" style="font-size:12px;"><?= htmlspecialchars($cat['description'] ?? '') ?></div>
                        <div class="mt-2"><span class="badge badge-info"><?= $cat['med_count'] ?> medicines</span></div>
                    </div>
                    <div class="d-flex gap-1 flex-column">
                        <button class="btn btn-sm btn-outline-primary" onclick="editCat(<?= $cat['id'] ?>,'<?= addslashes($cat['name']) ?>','<?= addslashes($cat['description'] ?? '') ?>')"><i class="bi bi-pencil"></i></button>
                        <?php if (hasRole('admin') && $cat['med_count'] == 0): ?>
                        <a href="categories.php?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete category '<?= addslashes($cat['name']) ?>'?"><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="catModalTitle"><i class="bi bi-tags me-2"></i>Add Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" id="catAction" value="add">
                <input type="hidden" name="id" id="catId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="name" id="catName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="catDesc" class="form-control" rows="3"></textarea>
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
function editCat(id, name, desc) {
    document.getElementById('catAction').value = 'edit';
    document.getElementById('catModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Category';
    document.getElementById('catId').value = id;
    document.getElementById('catName').value = name;
    document.getElementById('catDesc').value = desc;
    new bootstrap.Modal(document.getElementById('catModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
