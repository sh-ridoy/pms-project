<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$expired    = $conn->query("SELECT m.*, c.name cat_name FROM medicines m LEFT JOIN categories c ON m.category_id=c.id WHERE m.expiry_date < CURDATE() AND m.status='active' ORDER BY m.expiry_date")->fetch_all(MYSQLI_ASSOC);
$within30   = $conn->query("SELECT m.*, c.name cat_name FROM medicines m LEFT JOIN categories c ON m.category_id=c.id WHERE m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND m.status='active' ORDER BY m.expiry_date")->fetch_all(MYSQLI_ASSOC);
$within90   = $conn->query("SELECT m.*, c.name cat_name FROM medicines m LEFT JOIN categories c ON m.category_id=c.id WHERE m.expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 31 DAY) AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND m.status='active' ORDER BY m.expiry_date")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Expiry Alerts</div>
        <div class="page-subtitle">Monitor medicine expiry dates</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card" style="--card-accent:#e63946;--card-bg:#fee2e2;--card-color:#e63946;">
            <div class="stat-icon"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-value"><?= count($expired) ?></div>
            <div class="stat-label">Already Expired</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="--card-accent:#f0a500;--card-bg:#fff8e6;--card-color:#d4900a;">
            <div class="stat-icon"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-value"><?= count($within30) ?></div>
            <div class="stat-label">Expiring in 30 Days</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card" style="--card-accent:#2176ae;--card-bg:#dbeafe;--card-color:#2176ae;">
            <div class="stat-icon"><i class="bi bi-calendar-warning"></i></div>
            <div class="stat-value"><?= count($within90) ?></div>
            <div class="stat-label">Expiring in 90 Days</div>
        </div>
    </div>
</div>

<!-- Expired -->
<?php if ($expired): ?>
<div class="card mb-3 border-danger">
    <div class="card-header text-danger"><i class="bi bi-x-circle-fill me-2"></i>Expired Medicines (<?= count($expired) ?>)</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Medicine</th><th>Category</th><th>Stock</th><th>Expiry Date</th><th>Days Expired</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($expired as $m): $days = round((time() - strtotime($m['expiry_date'])) / 86400); ?>
                <tr class="expiry-critical">
                    <td class="fw-600"><?= htmlspecialchars($m['name']) ?><br><small class="text-muted"><?= htmlspecialchars($m['generic_name'] ?? '') ?></small></td>
                    <td><?= htmlspecialchars($m['cat_name'] ?? '—') ?></td>
                    <td><?= $m['stock_qty'] ?> <?= htmlspecialchars($m['unit']) ?></td>
                    <td class="text-danger fw-600"><?= date('d M Y', strtotime($m['expiry_date'])) ?></td>
                    <td><span class="badge bg-danger"><?= $days ?> days ago</span></td>
                    <td><a href="medicines.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-pencil me-1"></i>Update</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Expiring in 30 days -->
<?php if ($within30): ?>
<div class="card mb-3 border-warning">
    <div class="card-header text-warning"><i class="bi bi-clock-fill me-2"></i>Expiring Within 30 Days (<?= count($within30) ?>)</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Medicine</th><th>Category</th><th>Stock</th><th>Expiry Date</th><th>Days Left</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($within30 as $m): $days = round((strtotime($m['expiry_date']) - time()) / 86400); ?>
                <tr class="expiry-warning">
                    <td class="fw-600"><?= htmlspecialchars($m['name']) ?></td>
                    <td><?= htmlspecialchars($m['cat_name'] ?? '—') ?></td>
                    <td><?= $m['stock_qty'] ?> <?= htmlspecialchars($m['unit']) ?></td>
                    <td class="fw-600" style="color:#d97706;"><?= date('d M Y', strtotime($m['expiry_date'])) ?></td>
                    <td><span class="badge bg-warning text-dark"><?= $days ?> days</span></td>
                    <td><a href="medicines.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil me-1"></i>Update</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Expiring in 90 days -->
<?php if ($within90): ?>
<div class="card mb-3">
    <div class="card-header text-info"><i class="bi bi-calendar-event me-2"></i>Expiring Within 90 Days (<?= count($within90) ?>)</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Medicine</th><th>Category</th><th>Stock</th><th>Expiry Date</th><th>Days Left</th></tr></thead>
            <tbody>
                <?php foreach ($within90 as $m): $days = round((strtotime($m['expiry_date']) - time()) / 86400); ?>
                <tr>
                    <td class="fw-600"><?= htmlspecialchars($m['name']) ?></td>
                    <td><?= htmlspecialchars($m['cat_name'] ?? '—') ?></td>
                    <td><?= $m['stock_qty'] ?></td>
                    <td><?= date('d M Y', strtotime($m['expiry_date'])) ?></td>
                    <td><span class="badge badge-info"><?= $days ?> days</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!$expired && !$within30 && !$within90): ?>
<div class="card"><div class="card-body text-center py-5 text-muted">
    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-3"></i>
    <h5>No expiry alerts!</h5>
    <p>All medicines are within safe expiry range.</p>
</div></div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
