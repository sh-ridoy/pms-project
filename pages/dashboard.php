<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

// Stats
$todaySales = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(total),0) t FROM sales WHERE DATE(sale_date)=CURDATE()")->fetch_assoc();
$monthSales = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(total),0) t FROM sales WHERE MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())")->fetch_assoc();
$totalMeds   = $conn->query("SELECT COUNT(*) c FROM medicines WHERE status='active'")->fetch_assoc()['c'];
$lowStock    = $conn->query("SELECT COUNT(*) c FROM medicines WHERE stock_qty <= min_stock AND status='active'")->fetch_assoc()['c'];
$expiringSoon= $conn->query("SELECT COUNT(*) c FROM medicines WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND expiry_date >= CURDATE()")->fetch_assoc()['c'];
$totalCustomers = $conn->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'];

// Returns today
$todayReturns = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(refund_amount),0) t FROM returns WHERE DATE(return_date)=CURDATE() AND status='completed'")->fetch_assoc();

// Month profit snapshot
$monthRevenue = floatval($monthSales['t']);
$monthCogs = floatval($conn->query("SELECT COALESCE(SUM(si.quantity * m.purchase_price),0) v FROM sale_items si JOIN medicines m ON si.medicine_id=m.id JOIN sales s ON si.sale_id=s.id WHERE MONTH(s.sale_date)=MONTH(CURDATE()) AND YEAR(s.sale_date)=YEAR(CURDATE())")->fetch_assoc()['v']);
$monthRefunds = floatval($conn->query("SELECT COALESCE(SUM(refund_amount),0) v FROM returns WHERE MONTH(return_date)=MONTH(CURDATE()) AND YEAR(return_date)=YEAR(CURDATE()) AND status='completed'")->fetch_assoc()['v']);
$monthProfit = $monthRevenue - $monthCogs - $monthRefunds;

// Recent Sales
$recentSales = $conn->query("SELECT s.*, c.name AS customer_name, u.full_name AS staff_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id LEFT JOIN users u ON s.user_id=u.id ORDER BY s.sale_date DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

// Low stock medicines
$lowStockMeds = $conn->query("SELECT m.*, cat.name cat_name FROM medicines m LEFT JOIN categories cat ON m.category_id=cat.id WHERE m.stock_qty <= m.min_stock AND m.status='active' ORDER BY m.stock_qty ASC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

// Chart: last 7 days sales
$chartData = $conn->query("SELECT DATE(sale_date) d, COALESCE(SUM(total),0) t FROM sales WHERE sale_date >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(sale_date) ORDER BY d ASC")->fetch_all(MYSQLI_ASSOC);

// Sales by category (pie)
$catSales = $conn->query("SELECT cat.name, COALESCE(SUM(si.total_price),0) total FROM sale_items si JOIN medicines m ON si.medicine_id=m.id JOIN categories cat ON m.category_id=cat.id JOIN sales s ON si.sale_id=s.id WHERE MONTH(s.sale_date)=MONTH(CURDATE()) GROUP BY cat.name ORDER BY total DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title">Dashboard</div>
        <div class="page-subtitle">Welcome back, <?= htmlspecialchars($user['full_name']) ?>! Here's today's overview.</div>
    </div>
    <span class="text-muted" style="font-size:13px;"><i class="bi bi-calendar3 me-1"></i><?= date('l, d F Y') ?></span>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#1a6b3c;--card-bg:#e8f5ee;--card-color:#1a6b3c;">
            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-value">৳<?= number_format($todaySales['t'], 0) ?></div>
            <div class="stat-label">Today's Sales</div>
            <div class="stat-change text-muted"><?= $todaySales['c'] ?> transactions</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#f0a500;--card-bg:#fff8e6;--card-color:#d4900a;">
            <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
            <div class="stat-value">৳<?= number_format($monthSales['t'], 0) ?></div>
            <div class="stat-label">This Month</div>
            <div class="stat-change text-muted"><?= $monthSales['c'] ?> sales</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#2176ae;--card-bg:#dbeafe;--card-color:#2176ae;">
            <div class="stat-icon"><i class="bi bi-capsule"></i></div>
            <div class="stat-value"><?= $totalMeds ?></div>
            <div class="stat-label">Active Medicines</div>
            <div class="stat-change <?= $lowStock > 0 ? 'text-danger' : 'text-muted' ?>"><?= $lowStock ?> low stock</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#e63946;--card-bg:#fee2e2;--card-color:#e63946;">
            <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-value"><?= $expiringSoon ?></div>
            <div class="stat-label">Expiring Soon</div>
            <div class="stat-change text-muted"><?= $totalCustomers ?> customers</div>
        </div>
    </div>
</div>

<!-- Profit & Return Quick Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card" style="--card-accent:<?= $monthProfit >= 0 ? '#16a34a' : '#e63946' ?>;--card-bg:<?= $monthProfit >= 0 ? '#dcfce7' : '#fee2e2' ?>;--card-color:<?= $monthProfit >= 0 ? '#16a34a' : '#e63946' ?>;">
            <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-value">৳<?= number_format(abs($monthProfit), 0) ?></div>
            <div class="stat-label">This Month Profit <?= $monthProfit < 0 ? '(Loss)' : '' ?></div>
            <div class="stat-change"><a href="profit_loss.php" style="color:inherit; text-decoration:none; font-size:11px;">View P&L →</a></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card" style="--card-accent:#f0a500;--card-bg:#fff8e6;--card-color:#d4900a;">
            <div class="stat-icon"><i class="bi bi-arrow-return-left"></i></div>
            <div class="stat-value"><?= $todayReturns['c'] ?></div>
            <div class="stat-label">Today's Returns</div>
            <div class="stat-change text-muted">৳<?= number_format($todayReturns['t'], 0) ?> refunded</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card" style="--card-accent:#7c3aed;--card-bg:#f3f0ff;--card-color:#7c3aed;">
            <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
            <div class="stat-value">৳<?= number_format($monthCogs, 0) ?></div>
            <div class="stat-label">Month COGS</div>
            <div class="stat-change text-muted">Cost of goods sold</div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart-line-fill text-success me-2"></i>Sales — Last 7 Days</div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart-fill text-warning me-2"></i>Sales by Category</div>
            <div class="card-body d-flex align-items-center">
                <div class="chart-container w-100" style="height:220px;">
                    <canvas id="catChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tables -->
<div class="row g-3">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header justify-content-between">
                <span><i class="bi bi-receipt me-2 text-success"></i>Recent Sales</span>
                <a href="sales.php" class="btn btn-sm btn-outline-success ms-auto">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Invoice</th><th>Customer</th><th>Amount</th><th>Payment</th><th>Date</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($recentSales as $s): ?>
                        <tr>
                            <td><a href="invoice.php?id=<?= $s['id'] ?>" class="text-success fw-600"><?= htmlspecialchars($s['invoice_no']) ?></a></td>
                            <td><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></td>
                            <td class="fw-600">৳<?= number_format($s['total'], 2) ?></td>
                            <td><span class="badge badge-info"><?= ucfirst($s['payment_method']) ?></span></td>
                            <td class="text-muted" style="font-size:12px;"><?= date('d M, h:i A', strtotime($s['sale_date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentSales): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No sales today</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header justify-content-between">
                <span><i class="bi bi-exclamation-circle me-2 text-danger"></i>Low Stock Alert</span>
                <a href="medicines.php" class="btn btn-sm btn-outline-danger ms-auto">Manage</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Medicine</th><th>Stock</th><th>Min</th></tr></thead>
                    <tbody>
                        <?php foreach ($lowStockMeds as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['name']) ?><br><small class="text-muted"><?= htmlspecialchars($m['cat_name'] ?? '') ?></small></td>
                            <td class="stock-low"><?= $m['stock_qty'] ?></td>
                            <td class="text-muted"><?= $m['min_stock'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$lowStockMeds): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3"><i class="bi bi-check-circle text-success me-1"></i>All stocks OK</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Credit Footer -->
<script>
// Sales Chart
const chartDays = <?= json_encode(array_column($chartData, 'd')) ?>;
const chartTotals = <?= json_encode(array_column($chartData, 't')) ?>;

// Fill missing days
const labels = [], values = [];
for (let i = 6; i >= 0; i--) {
    const d = new Date(); d.setDate(d.getDate() - i);
    const key = d.toISOString().split('T')[0];
    const idx = chartDays.indexOf(key);
    labels.push(d.toLocaleDateString('en-US',{month:'short',day:'numeric'}));
    values.push(idx >= 0 ? parseFloat(chartTotals[idx]) : 0);
}

new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Sales (৳)',
            data: values,
            backgroundColor: 'rgba(26,107,60,0.15)',
            borderColor: '#1a6b3c',
            borderWidth: 2,
            borderRadius: 8,
            fill: true
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { callback: v => '৳' + v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});

// Category Pie
const catLabels = <?= json_encode(array_column($catSales, 'name')) ?>;
const catValues = <?= json_encode(array_column($catSales, 'total')) ?>;
new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catValues,
            backgroundColor: ['#1a6b3c','#f0a500','#2176ae','#e63946','#7c3aed','#16a34a'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } } },
        cutout: '65%'
    }
});
</script>

<?php include '../includes/footer.php'; ?>
