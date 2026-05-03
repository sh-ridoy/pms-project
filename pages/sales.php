<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

// Handle delete
if (isset($_GET['delete']) && hasRole('admin')) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM sale_items WHERE sale_id=$id");
    $conn->query("DELETE FROM sales WHERE id=$id");
    flashMessage('success', 'Sale deleted successfully.');
    header('Location: sales.php'); exit;
}

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo   = $_GET['to']   ?? date('Y-m-d');
$search   = trim($_GET['search'] ?? '');

$where = "WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];
if ($search) { $where .= " AND (s.invoice_no LIKE ? OR c.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$sql = "SELECT s.*, c.name customer_name, u.full_name staff FROM sales s LEFT JOIN customers c ON s.customer_id=c.id LEFT JOIN users u ON s.user_id=u.id $where ORDER BY s.sale_date DESC";
$stmt = $conn->prepare($sql);
$types = str_repeat('s', count($params));
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalAmt = array_sum(array_column($sales, 'total'));

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-receipt-cutoff me-2 text-success"></i>Sales History</div>
        <div class="page-subtitle">View and manage all sales transactions</div>
    </div>
    <a href="pos.php" class="btn-primary-custom"><i class="bi bi-plus-circle"></i> New Sale</a>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Invoice or customer..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="to" class="form-control" value="<?= $dateTo ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-success"><i class="bi bi-filter me-1"></i>Filter</button>
                <a href="sales.php" class="btn btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="stat-card" style="--card-accent:#1a6b3c;--card-bg:#e8f5ee;--card-color:#1a6b3c;">
            <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            <div class="stat-value"><?= count($sales) ?></div>
            <div class="stat-label">Total Transactions</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="--card-accent:#f0a500;--card-bg:#fff8e6;--card-color:#d4900a;">
            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-value">৳<?= number_format($totalAmt, 0) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="--card-accent:#2176ae;--card-bg:#dbeafe;--card-color:#2176ae;">
            <div class="stat-icon"><i class="bi bi-calculator"></i></div>
            <div class="stat-value">৳<?= count($sales) ? number_format($totalAmt / count($sales), 0) : '0' ?></div>
            <div class="stat-label">Avg. Sale Value</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="--card-accent:#16a34a;--card-bg:#dcfce7;--card-color:#16a34a;">
            <div class="stat-icon"><i class="bi bi-calendar-range"></i></div>
            <div class="stat-value"><?= date('d M', strtotime($dateFrom)) ?> – <?= date('d M', strtotime($dateTo)) ?></div>
            <div class="stat-label">Period</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>Sales Records</span>
        <span class="badge bg-success"><?= count($sales) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>Invoice</th><th>Customer</th><th>Subtotal</th><th>Discount</th><th>Total</th><th>Paid</th><th>Payment</th><th>Staff</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php foreach ($sales as $s): ?>
                <tr>
                    <td><a href="invoice.php?id=<?= $s['id'] ?>" class="text-success fw-600"><?= htmlspecialchars($s['invoice_no']) ?></a></td>
                    <td><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></td>
                    <td>৳<?= number_format($s['subtotal'], 2) ?></td>
                    <td class="text-danger"><?= $s['discount'] > 0 ? '-৳'.number_format($s['discount'],2) : '—' ?></td>
                    <td class="fw-700">৳<?= number_format($s['total'], 2) ?></td>
                    <td>৳<?= number_format($s['paid_amount'], 2) ?></td>
                    <td><span class="badge badge-info"><?= ucfirst($s['payment_method']) ?></span></td>
                    <td class="text-muted" style="font-size:12px;"><?= htmlspecialchars($s['staff'] ?? '') ?></td>
                    <td class="text-muted" style="font-size:12px;"><?= date('d M Y h:i A', strtotime($s['sale_date'])) ?></td>
                    <td>
                        <a href="invoice.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-printer"></i></a>
                        <?php if (hasRole('admin')): ?>
                        <a href="sales.php?delete=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" data-confirm="Delete this sale? Stock will NOT be restored."><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$sales): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No sales in this period</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($sales): ?>
            <tfoot>
                <tr class="table-light fw-700">
                    <td colspan="4" class="text-end">Total Revenue:</td>
                    <td>৳<?= number_format($totalAmt, 2) ?></td>
                    <td colspan="5"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
