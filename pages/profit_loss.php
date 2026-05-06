<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo   = $_GET['to']   ?? date('Y-m-d');
$groupBy  = $_GET['group'] ?? 'daily';

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');

// ── REVENUE from sales ──
$revenueData = $conn->query("
    SELECT 
        COALESCE(SUM(total),0)   AS gross_revenue,
        COALESCE(SUM(discount),0) AS total_discount,
        COALESCE(SUM(tax),0)     AS total_tax,
        COUNT(*)                  AS total_sales
    FROM sales 
    WHERE DATE(sale_date) BETWEEN '$dateFrom' AND '$dateTo'
")->fetch_assoc();

// ── COST OF GOODS SOLD (purchase_price × qty sold) ──
$cogsData = $conn->query("
    SELECT COALESCE(SUM(si.quantity * m.purchase_price), 0) AS cogs
    FROM sale_items si
    JOIN medicines m ON si.medicine_id = m.id
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'
")->fetch_assoc();

// ── RETURNS (refunds given) ──
$returnData = $conn->query("
    SELECT COALESCE(SUM(refund_amount),0) AS total_refunds, COUNT(*) AS return_count
    FROM returns
    WHERE DATE(return_date) BETWEEN '$dateFrom' AND '$dateTo' AND status='completed'
")->fetch_assoc();

// ── PURCHASE EXPENSES ──
$purchaseData = $conn->query("
    SELECT COALESCE(SUM(total),0) AS total_purchases, COUNT(*) AS purchase_count
    FROM purchases
    WHERE DATE(purchase_date) BETWEEN '$dateFrom' AND '$dateTo'
")->fetch_assoc();

// Calculations
$grossRevenue   = floatval($revenueData['gross_revenue']);
$totalDiscount  = floatval($revenueData['total_discount']);
$totalTax       = floatval($revenueData['total_tax']);
$netRevenue     = $grossRevenue; // already after discount in the sales table
$cogs           = floatval($cogsData['cogs']);
$totalRefunds   = floatval($returnData['total_refunds']);
$totalPurchases = floatval($purchaseData['total_purchases']);
$totalSales     = intval($revenueData['total_sales']);

$grossProfit    = $netRevenue - $cogs - $totalRefunds;
$netProfit      = $grossProfit; // can add more expenses later
$profitMargin   = $netRevenue > 0 ? ($grossProfit / $netRevenue) * 100 : 0;

// ── Per medicine profit breakdown ──
$medicineProfit = $conn->query("
    SELECT 
        m.name,
        m.generic_name,
        SUM(si.quantity) AS qty_sold,
        SUM(si.total_price) AS revenue,
        SUM(si.quantity * m.purchase_price) AS cost,
        SUM(si.total_price) - SUM(si.quantity * m.purchase_price) AS profit
    FROM sale_items si
    JOIN medicines m ON si.medicine_id = m.id
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY m.id
    ORDER BY profit DESC
    LIMIT 15
")->fetch_all(MYSQLI_ASSOC);

// ── Trend data ──
if ($groupBy === 'monthly') {
    $trendSQL = "SELECT DATE_FORMAT(s.sale_date,'%Y-%m') period,
        SUM(s.total) revenue,
        SUM(si_agg.cost) cost
    FROM sales s
    LEFT JOIN (
        SELECT si2.sale_id, SUM(si2.quantity * m2.purchase_price) cost
        FROM sale_items si2 JOIN medicines m2 ON si2.medicine_id=m2.id
        GROUP BY si2.sale_id
    ) si_agg ON si_agg.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE_FORMAT(s.sale_date,'%Y-%m')
    ORDER BY period ASC";
} else {
    $trendSQL = "SELECT DATE(s.sale_date) period,
        SUM(s.total) revenue,
        SUM(si_agg.cost) cost
    FROM sales s
    LEFT JOIN (
        SELECT si2.sale_id, SUM(si2.quantity * m2.purchase_price) cost
        FROM sale_items si2 JOIN medicines m2 ON si2.medicine_id=m2.id
        GROUP BY si2.sale_id
    ) si_agg ON si_agg.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(s.sale_date)
    ORDER BY period ASC";
}
$trendData = $conn->query($trendSQL)->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Profit & Loss</div>
        <div class="page-subtitle">Financial performance analysis</div>
    </div>
    <form class="d-flex gap-2 align-items-center">
        <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>" style="width:140px;">
        <input type="date" name="to" class="form-control" value="<?= $dateTo ?>" style="width:140px;">
        <select name="group" class="form-select" style="width:130px;">
            <option value="daily" <?= $groupBy==='daily'?'selected':'' ?>>Daily</option>
            <option value="monthly" <?= $groupBy==='monthly'?'selected':'' ?>>Monthly</option>
        </select>
        <button class="btn btn-outline-success"><i class="bi bi-filter"></i></button>
    </form>
</div>

<!-- P&L Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#1a6b3c;--card-bg:#e8f5ee;--card-color:#1a6b3c;">
            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-value">৳<?= number_format($netRevenue, 0) ?></div>
            <div class="stat-label">Net Revenue</div>
            <div class="stat-change text-muted"><?= $totalSales ?> sales</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#e63946;--card-bg:#fee2e2;--card-color:#e63946;">
            <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
            <div class="stat-value">৳<?= number_format($cogs, 0) ?></div>
            <div class="stat-label">Cost of Goods Sold</div>
            <div class="stat-change text-muted">Purchase cost</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:<?= $grossProfit >= 0 ? '#16a34a' : '#e63946' ?>;--card-bg:<?= $grossProfit >= 0 ? '#dcfce7' : '#fee2e2' ?>;--card-color:<?= $grossProfit >= 0 ? '#16a34a' : '#e63946' ?>;">
            <div class="stat-icon"><i class="bi bi-<?= $grossProfit >= 0 ? 'trending-up' : 'trending-down' ?>"></i></div>
            <div class="stat-value">৳<?= number_format(abs($grossProfit), 0) ?></div>
            <div class="stat-label">Gross Profit <?= $grossProfit < 0 ? '(Loss)' : '' ?></div>
            <div class="stat-change" style="color:<?= $grossProfit >= 0 ? '#16a34a' : '#e63946' ?>;">
                <?= number_format(abs($profitMargin), 1) ?>% margin
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#f0a500;--card-bg:#fff8e6;--card-color:#d4900a;">
            <div class="stat-icon"><i class="bi bi-arrow-return-left"></i></div>
            <div class="stat-value">৳<?= number_format($totalRefunds, 0) ?></div>
            <div class="stat-label">Total Refunds</div>
            <div class="stat-change text-muted"><?= $returnData['return_count'] ?> returns</div>
        </div>
    </div>
</div>

<!-- Detailed P&L Statement -->
<div class="row g-3 mb-4">
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-journal-text me-2 text-success"></i>P&L Statement</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <tbody>
                        <tr class="table-light">
                            <th colspan="2" class="px-3 py-2" style="font-size:11px; text-transform:uppercase; letter-spacing:1px;">INCOME</th>
                        </tr>
                        <tr>
                            <td class="px-3">Gross Sales</td>
                            <td class="px-3 text-end fw-600 text-success">৳<?= number_format($grossRevenue, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="px-3 text-muted" style="font-size:13px;">Less: Discounts Given</td>
                            <td class="px-3 text-end text-danger" style="font-size:13px;">-৳<?= number_format($totalDiscount, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="px-3 text-muted" style="font-size:13px;">Add: Tax Collected</td>
                            <td class="px-3 text-end text-muted" style="font-size:13px;">+৳<?= number_format($totalTax, 2) ?></td>
                        </tr>
                        <tr class="fw-700">
                            <td class="px-3">Net Revenue</td>
                            <td class="px-3 text-end text-success">৳<?= number_format($netRevenue, 2) ?></td>
                        </tr>
                        <tr class="table-light">
                            <th colspan="2" class="px-3 py-2" style="font-size:11px; text-transform:uppercase; letter-spacing:1px;">COST OF GOODS</th>
                        </tr>
                        <tr>
                            <td class="px-3">Purchase Cost of Sold Items</td>
                            <td class="px-3 text-end text-danger">-৳<?= number_format($cogs, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="px-3">Refunds Paid</td>
                            <td class="px-3 text-end text-danger">-৳<?= number_format($totalRefunds, 2) ?></td>
                        </tr>
                        <tr class="table-light">
                            <th colspan="2" class="px-3 py-2" style="font-size:11px; text-transform:uppercase; letter-spacing:1px;">RESULT</th>
                        </tr>
                        <tr class="<?= $grossProfit >= 0 ? 'table-success' : 'table-danger' ?> fw-700 fs-6">
                            <td class="px-3">Gross Profit <?= $grossProfit < 0 ? '/ Loss' : '' ?></td>
                            <td class="px-3 text-end <?= $grossProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $grossProfit < 0 ? '-' : '' ?>৳<?= number_format(abs($grossProfit), 2) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-3 text-muted">Profit Margin</td>
                            <td class="px-3 text-end fw-600 <?= $profitMargin >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($profitMargin, 2) ?>%
                            </td>
                        </tr>
                        <tr class="table-light">
                            <th colspan="2" class="px-3 py-2" style="font-size:11px; text-transform:uppercase; letter-spacing:1px;">PURCHASES (Inventory Investment)</th>
                        </tr>
                        <tr>
                            <td class="px-3">Total Purchased</td>
                            <td class="px-3 text-end text-muted">৳<?= number_format($totalPurchases, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="px-3 text-muted" style="font-size:12px;"><?= $purchaseData['purchase_count'] ?> purchase orders</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Trend Chart -->
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart-line me-2 text-success"></i>Revenue vs Cost Trend</div>
            <div class="card-body">
                <div class="chart-container" style="height:300px;">
                    <canvas id="plChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Medicine Profit Breakdown -->
<div class="card">
    <div class="card-header"><i class="bi bi-capsule me-2 text-warning"></i>Medicine-wise Profit Breakdown</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>#</th><th>Medicine</th><th>Qty Sold</th><th>Revenue</th><th>Cost</th><th>Gross Profit</th><th>Margin</th>
            </tr></thead>
            <tbody>
                <?php if ($medicineProfit): ?>
                <?php foreach ($medicineProfit as $i => $mp): 
                    $margin = $mp['revenue'] > 0 ? ($mp['profit'] / $mp['revenue']) * 100 : 0;
                    $isLoss = $mp['profit'] < 0;
                ?>
                <tr>
                    <td class="text-muted"><?= $i+1 ?></td>
                    <td>
                        <span class="fw-600"><?= htmlspecialchars($mp['name']) ?></span><br>
                        <small class="text-muted"><?= htmlspecialchars($mp['generic_name'] ?? '') ?></small>
                    </td>
                    <td><?= number_format($mp['qty_sold']) ?></td>
                    <td>৳<?= number_format($mp['revenue'], 2) ?></td>
                    <td class="text-danger">৳<?= number_format($mp['cost'], 2) ?></td>
                    <td class="fw-700 <?= $isLoss ? 'text-danger' : 'text-success' ?>">
                        <?= $isLoss ? '-' : '' ?>৳<?= number_format(abs($mp['profit']), 2) ?>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:8px; border-radius:4px; min-width:80px;">
                                <div class="progress-bar <?= $isLoss ? 'bg-danger' : 'bg-success' ?>" 
                                     style="width:<?= min(100, abs($margin)) ?>%"></div>
                            </div>
                            <span class="<?= $isLoss ? 'text-danger' : 'text-success' ?>" style="font-size:12px; min-width:45px;">
                                <?= number_format($margin, 1) ?>%
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No sales data for this period</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const plLabels  = <?= json_encode(array_column($trendData, 'period')) ?>;
const plRevenue = <?= json_encode(array_map(fn($d) => floatval($d['revenue']), $trendData)) ?>;
const plCost    = <?= json_encode(array_map(fn($d) => floatval($d['cost']), $trendData)) ?>;
const plProfit  = plRevenue.map((r, i) => r - plCost[i]);

new Chart(document.getElementById('plChart'), {
    type: 'bar',
    data: {
        labels: plLabels,
        datasets: [
            {
                label: 'Revenue (৳)',
                data: plRevenue,
                backgroundColor: 'rgba(26,107,60,0.7)',
                borderColor: '#1a6b3c',
                borderWidth: 1,
                borderRadius: 4,
                order: 2
            },
            {
                label: 'Cost (৳)',
                data: plCost,
                backgroundColor: 'rgba(230,57,70,0.6)',
                borderColor: '#e63946',
                borderWidth: 1,
                borderRadius: 4,
                order: 3
            },
            {
                label: 'Profit (৳)',
                data: plProfit,
                type: 'line',
                borderColor: '#f0a500',
                backgroundColor: 'rgba(240,165,0,0.1)',
                borderWidth: 2.5,
                pointRadius: 4,
                pointBackgroundColor: '#f0a500',
                fill: false,
                tension: 0.4,
                order: 1
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { font: { size: 12 }, padding: 12 } },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.label + ': ৳' + ctx.raw.toLocaleString()
                }
            }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '৳' + v.toLocaleString() }, grid: { color: '#f0f0f0' } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
