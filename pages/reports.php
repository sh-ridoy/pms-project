<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$month = $_GET['month'] ?? date('Y-m');
// Validate format and sanitize
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
[$year, $mon] = explode('-', $month);
$year = intval($year);
$mon  = intval($mon);
if ($year < 2000 || $year > 2100 || $mon < 1 || $mon > 12) {
    $year = intval(date('Y'));
    $mon  = intval(date('m'));
    $month = date('Y-m');
}

// Monthly summary
$summary = $conn->query("SELECT COUNT(*) sales_count, COALESCE(SUM(subtotal),0) subtotal, COALESCE(SUM(discount),0) discount, COALESCE(SUM(tax),0) tax, COALESCE(SUM(total),0) total FROM sales WHERE YEAR(sale_date)=$year AND MONTH(sale_date)=$mon")->fetch_assoc();

// Top selling medicines
$topMeds = $conn->query("SELECT m.name, SUM(si.quantity) qty, SUM(si.total_price) revenue FROM sale_items si JOIN medicines m ON si.medicine_id=m.id JOIN sales s ON si.sale_id=s.id WHERE YEAR(s.sale_date)=$year AND MONTH(s.sale_date)=$mon GROUP BY m.id ORDER BY qty DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Daily sales
$dailySales = $conn->query("SELECT DATE(sale_date) d, COUNT(*) cnt, SUM(total) total FROM sales WHERE YEAR(sale_date)=$year AND MONTH(sale_date)=$mon GROUP BY DATE(sale_date) ORDER BY d")->fetch_all(MYSQLI_ASSOC);

// Payment methods
$payMethods = $conn->query("SELECT payment_method, COUNT(*) cnt, SUM(total) total FROM sales WHERE YEAR(sale_date)=$year AND MONTH(sale_date)=$mon GROUP BY payment_method")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-bar-chart-fill me-2 text-success"></i>Reports</div>
        <div class="page-subtitle">Sales analytics and performance</div>
    </div>
    <form class="d-flex gap-2">
        <input type="month" name="month" class="form-control" value="<?= $month ?>">
        <button class="btn btn-outline-success"><i class="bi bi-filter"></i></button>
    </form>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#1a6b3c;--card-bg:#e8f5ee;--card-color:#1a6b3c;">
            <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            <div class="stat-value"><?= $summary['sales_count'] ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#f0a500;--card-bg:#fff8e6;--card-color:#d4900a;">
            <div class="stat-icon"><i class="bi bi-cash"></i></div>
            <div class="stat-value">৳<?= number_format($summary['subtotal'], 0) ?></div>
            <div class="stat-label">Gross Sales</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#e63946;--card-bg:#fee2e2;--card-color:#e63946;">
            <div class="stat-icon"><i class="bi bi-tag"></i></div>
            <div class="stat-value">৳<?= number_format($summary['discount'], 0) ?></div>
            <div class="stat-label">Total Discounts</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#2176ae;--card-bg:#dbeafe;--card-color:#2176ae;">
            <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-value">৳<?= number_format($summary['total'], 0) ?></div>
            <div class="stat-label">Net Revenue</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar3 me-2 text-success"></i>Daily Sales — <?= date('F Y', mktime(0,0,0,$mon,1,$year)) ?></div>
            <div class="card-body"><div class="chart-container"><canvas id="dailyChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-credit-card me-2 text-warning"></i>Payment Methods</div>
            <div class="card-body">
                <?php foreach ($payMethods as $pm): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge badge-info me-2"><?= ucfirst($pm['payment_method']) ?></span>
                        <span class="text-muted" style="font-size:12px;"><?= $pm['cnt'] ?> transactions</span>
                    </div>
                    <span class="fw-700">৳<?= number_format($pm['total'], 0) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (!$payMethods): ?><div class="text-muted text-center py-3">No data</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Selling Medicines -->
<div class="card">
    <div class="card-header"><i class="bi bi-trophy-fill me-2 text-warning"></i>Top Selling Medicines — <?= date('F Y', mktime(0,0,0,$mon,1,$year)) ?></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Medicine</th><th>Units Sold</th><th>Revenue</th><th>Share</th></tr></thead>
            <tbody>
                <?php
                $maxQty = $topMeds ? $topMeds[0]['qty'] : 1;
                foreach ($topMeds as $i => $m):
                    $pct = $maxQty > 0 ? round($m['qty'] / $maxQty * 100) : 0;
                ?>
                <tr>
                    <td>
                        <?php if ($i === 0): ?><i class="bi bi-trophy-fill text-warning"></i>
                        <?php elseif ($i === 1): ?><i class="bi bi-trophy-fill text-secondary"></i>
                        <?php elseif ($i === 2): ?><i class="bi bi-trophy-fill" style="color:#cd7f32;"></i>
                        <?php else: ?><?= $i+1 ?>
                        <?php endif; ?>
                    </td>
                    <td class="fw-600"><?= htmlspecialchars($m['name']) ?></td>
                    <td><?= number_format($m['qty']) ?></td>
                    <td class="fw-600">৳<?= number_format($m['revenue'], 2) ?></td>
                    <td style="width:160px;">
                        <div class="progress" style="height:8px; border-radius:4px;">
                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$topMeds): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No sales data</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const dLabels = <?= json_encode(array_map(fn($d) => date('d', strtotime($d['d'])), $dailySales)) ?>;
const dValues = <?= json_encode(array_map(fn($d) => floatval($d['total']), $dailySales)) ?>;

new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: dLabels,
        datasets: [{
            label: 'Revenue (৳)',
            data: dValues,
            borderColor: '#1a6b3c',
            backgroundColor: 'rgba(26,107,60,0.1)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#1a6b3c'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '৳'+v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
