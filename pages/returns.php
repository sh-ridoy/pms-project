<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

// ============================================================
// CUSTOMER RETURN (from sale)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_return') {
    $saleId     = intval($_POST['sale_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');
    $returnType = $_POST['return_type'] ?? 'refund';
    $items      = json_decode($_POST['return_items'] ?? '[]', true);
    $userId     = $_SESSION['user_id'];

    if ($saleId && !empty($items)) {
        $refundTotal = 0;
        foreach ($items as $item) {
            $refundTotal += floatval($item['unit_price']) * intval($item['return_qty']);
        }

        $invoice = generateInvoice('RET');

        // ✅ FIX: siissd = s(invoice) i(saleId) i(userId) s(returnType) s(reason) d(refundTotal) = 6টি
        $stmt = $conn->prepare("INSERT INTO returns (invoice_no, sale_id, user_id, return_type, reason, refund_amount, status) VALUES (?,?,?,?,?,?,'completed')");
        $stmt->bind_param('siissd', $invoice, $saleId, $userId, $returnType, $reason, $refundTotal);
        $stmt->execute();
        $returnId = $conn->insert_id;

        // ✅ FIX: iiiidd = i(returnId) i(saleItemId) i(medId) i(qty) d(unitPrice) d(totalP) = 6টি
        $ri  = $conn->prepare("INSERT INTO return_items (return_id, sale_item_id, medicine_id, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)");
        $upd = $conn->prepare("UPDATE medicines SET stock_qty = stock_qty + ? WHERE id = ?");

        foreach ($items as $item) {
            $qty        = intval($item['return_qty']);
            $medId      = intval($item['medicine_id']);
            $saleItemId = intval($item['sale_item_id']);
            $unitPrice  = floatval($item['unit_price']);
            $totalP     = $unitPrice * $qty;

            $ri->bind_param('iiiidd', $returnId, $saleItemId, $medId, $qty, $unitPrice, $totalP);
            $ri->execute();

            $upd->bind_param('ii', $qty, $medId);
            $upd->execute();
        }

        flashMessage('success', "✅ Return processed! Invoice: $invoice | Refund: ৳" . number_format($refundTotal, 2));
        header('Location: returns.php'); exit;
    } else {
        flashMessage('error', 'Please select items to return.');
    }
}

// ============================================================
// SUPPLIER RETURN (return to company)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_supplier_return') {
    $supplierId  = intval($_POST['supplier_id'] ?? 0);
    $medicineId  = intval($_POST['medicine_id'] ?? 0);
    $qty         = intval($_POST['qty'] ?? 0);
    $unitPrice   = floatval($_POST['unit_price'] ?? 0);
    $reason      = trim($_POST['reason'] ?? '');
    $userId      = $_SESSION['user_id'];

    if ($supplierId && $medicineId && $qty > 0) {
        $totalAmt = $unitPrice * $qty;
        $invoice  = generateInvoice('SRN'); // Supplier Return Note

        // Check if supplier_returns table exists, if not create it
        $conn->query("CREATE TABLE IF NOT EXISTS supplier_returns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_no VARCHAR(20) NOT NULL UNIQUE,
            supplier_id INT,
            medicine_id INT NOT NULL,
            user_id INT,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) DEFAULT 0.00,
            total_amount DECIMAL(10,2) DEFAULT 0.00,
            reason TEXT,
            status ENUM('completed','pending') DEFAULT 'completed',
            return_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
            FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");

        // ✅ FIX: siiiidds = s(invoice) i(supplierId) i(medicineId) i(userId) i(qty) d(unitPrice) d(totalAmt) s(reason) = 8টি
        $stmt = $conn->prepare("INSERT INTO supplier_returns (invoice_no, supplier_id, medicine_id, user_id, quantity, unit_price, total_amount, reason) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('siiiidds', $invoice, $supplierId, $medicineId, $userId, $qty, $unitPrice, $totalAmt, $reason);
        $stmt->execute();

        // Deduct stock
        $conn->query("UPDATE medicines SET stock_qty = stock_qty - $qty WHERE id = $medicineId AND stock_qty >= $qty");

        flashMessage('success', "✅ Supplier return processed! Invoice: $invoice | Amount: ৳" . number_format($totalAmt, 2));
        header('Location: returns.php'); exit;
    } else {
        flashMessage('error', 'Please fill all required fields correctly.');
    }
}

// Handle delete customer return
if (isset($_GET['delete']) && hasRole('admin')) {
    $id = intval($_GET['delete']);
    $ritems = $conn->query("SELECT medicine_id, quantity FROM return_items WHERE return_id=$id")->fetch_all(MYSQLI_ASSOC);
    foreach ($ritems as $ri) {
        $conn->query("UPDATE medicines SET stock_qty = stock_qty - {$ri['quantity']} WHERE id={$ri['medicine_id']}");
    }
    $conn->query("DELETE FROM return_items WHERE return_id=$id");
    $conn->query("DELETE FROM returns WHERE id=$id");
    flashMessage('success', 'Return record deleted.');
    header('Location: returns.php'); exit;
}

// Active tab
$activeTab = $_GET['tab'] ?? 'customer';

// Filters
$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo   = $_GET['to']   ?? date('Y-m-d');
$search   = trim($_GET['search'] ?? '');

$where = "WHERE DATE(r.return_date) BETWEEN '$dateFrom' AND '$dateTo'";
if ($search) $where .= " AND (r.invoice_no LIKE '%$search%' OR s.invoice_no LIKE '%$search%')";

$returns = $conn->query("
    SELECT r.*, s.invoice_no sale_invoice, c.name customer_name, u.full_name staff_name
    FROM returns r
    LEFT JOIN sales s ON r.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON r.user_id = u.id
    $where
    ORDER BY r.return_date DESC
")->fetch_all(MYSQLI_ASSOC);

$totalRefund = array_sum(array_column($returns, 'refund_amount'));

// Supplier returns
$conn->query("CREATE TABLE IF NOT EXISTS supplier_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) NOT NULL UNIQUE,
    supplier_id INT,
    medicine_id INT NOT NULL,
    user_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    reason TEXT,
    status ENUM('completed','pending') DEFAULT 'completed',
    return_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$supplierReturns = $conn->query("
    SELECT sr.*, s.name supplier_name, m.name medicine_name, u.full_name staff_name
    FROM supplier_returns sr
    LEFT JOIN suppliers s ON sr.supplier_id = s.id
    LEFT JOIN medicines m ON sr.medicine_id = m.id
    LEFT JOIN users u ON sr.user_id = u.id
    ORDER BY sr.return_date DESC
")->fetch_all(MYSQLI_ASSOC);
$totalSupplierReturn = array_sum(array_column($supplierReturns, 'total_amount'));

// Search sale for return
$searchInvoice = trim($_GET['find_invoice'] ?? '');
$foundSale = null;
if ($searchInvoice) {
    $esc = $conn->real_escape_string($searchInvoice);
    $foundSale = $conn->query("SELECT s.*, c.name customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE s.invoice_no='$esc'")->fetch_assoc();
    if (!$foundSale) flashMessage('error', "Invoice '$searchInvoice' not found.");
}

// For supplier return form
$suppliers = $conn->query("SELECT * FROM suppliers WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$medicines = $conn->query("SELECT id, name, purchase_price, stock_qty FROM medicines WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// View return details
$viewReturn = null;
$viewItems  = [];
if (isset($_GET['view'])) {
    $vid = intval($_GET['view']);
    $viewReturn = $conn->query("SELECT r.*, s.invoice_no sale_invoice, c.name customer_name, u.full_name staff_name FROM returns r LEFT JOIN sales s ON r.sale_id=s.id LEFT JOIN customers c ON s.customer_id=c.id LEFT JOIN users u ON r.user_id=u.id WHERE r.id=$vid")->fetch_assoc();
    $viewItems  = $conn->query("SELECT ri.*, m.name med_name FROM return_items ri JOIN medicines m ON ri.medicine_id=m.id WHERE ri.return_id=$vid")->fetch_all(MYSQLI_ASSOC);
}

include '../includes/header.php';
?>

<!-- ============================================================
     RETURNS PAGE - REDESIGNED
     ============================================================ -->

<style>
/* ---- Page-specific overrides ---- */
.ret-hero {
    background: linear-gradient(135deg, #0f2027 0%, #1a3a4a 50%, #0d3d22 100%);
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.ret-hero::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 200px; height: 200px;
    background: rgba(240,165,0,0.08);
    border-radius: 50%;
}
.ret-hero::after {
    content: '';
    position: absolute;
    bottom: -60px; left: 60px;
    width: 160px; height: 160px;
    background: rgba(26,107,60,0.15);
    border-radius: 50%;
}
.ret-hero-title {
    font-size: 24px;
    font-weight: 800;
    color: #fff;
    margin-bottom: 4px;
    letter-spacing: -0.3px;
}
.ret-hero-sub {
    color: rgba(255,255,255,0.5);
    font-size: 13px;
}
.ret-hero-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Stat cards */
.ret-stat {
    background: #fff;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: transform .2s, box-shadow .2s;
    position: relative;
    overflow: hidden;
}
.ret-stat:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
.ret-stat-accent {
    position: absolute;
    top: 0; left: 0;
    width: 4px; height: 100%;
    border-radius: 4px 0 0 4px;
}
.ret-stat-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    margin-bottom: 12px;
}
.ret-stat-val {
    font-size: 22px;
    font-weight: 800;
    color: #1a1f2e;
    line-height: 1;
    margin-bottom: 4px;
}
.ret-stat-label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .5px;
}

/* Tabs */
.ret-tabs {
    display: flex;
    gap: 4px;
    background: #f0f4f8;
    padding: 4px;
    border-radius: 12px;
    margin-bottom: 20px;
    width: fit-content;
}
.ret-tab {
    padding: 9px 20px;
    border-radius: 9px;
    border: none;
    background: transparent;
    font-size: 13.5px;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all .2s;
    display: flex; align-items: center; gap: 7px;
    text-decoration: none;
}
.ret-tab:hover { color: #1a1f2e; background: rgba(255,255,255,0.6); }
.ret-tab.active {
    background: #fff;
    color: #1a6b3c;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.ret-tab .tab-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: currentColor;
    opacity: 0.5;
}
.ret-tab.active .tab-dot { opacity: 1; }

/* Table card */
.ret-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    overflow: hidden;
}
.ret-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #f0f4f8;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fafbfc;
}
.ret-card-title {
    font-size: 14px;
    font-weight: 700;
    color: #1a1f2e;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Filter bar */
.ret-filter {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 14px 18px;
    margin-bottom: 16px;
}

/* Badge styles */
.badge-refund  { background: #fee2e2; color: #e63946; font-size: 11px; padding: 3px 9px; border-radius: 20px; font-weight: 600; }
.badge-exchange{ background: #dbeafe; color: #2176ae; font-size: 11px; padding: 3px 9px; border-radius: 20px; font-weight: 600; }
.badge-supplier{ background: #fef3c7; color: #d97706; font-size: 11px; padding: 3px 9px; border-radius: 20px; font-weight: 600; }
.badge-completed{ background: #e8f5ee; color: #1a6b3c; font-size: 11px; padding: 3px 9px; border-radius: 20px; font-weight: 600; }

/* Buttons */
.btn-ret-primary {
    background: linear-gradient(135deg, #1a6b3c, #1d7a44);
    color: #fff;
    border: none;
    padding: 9px 18px;
    border-radius: 9px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all .2s;
    text-decoration: none;
}
.btn-ret-primary:hover { background: linear-gradient(135deg, #124d2c, #1a6b3c); color: #fff; transform: translateY(-1px); }

.btn-ret-warning {
    background: linear-gradient(135deg, #f0a500, #e09500);
    color: #fff;
    border: none;
    padding: 9px 18px;
    border-radius: 9px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all .2s;
    text-decoration: none;
}
.btn-ret-warning:hover { transform: translateY(-1px); color: #fff; }

/* Table */
.ret-table { width: 100%; border-collapse: collapse; }
.ret-table thead th {
    background: #f8fafc;
    padding: 11px 14px;
    font-size: 11px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.ret-table tbody td {
    padding: 12px 14px;
    font-size: 13.5px;
    color: #374151;
    border-bottom: 1px solid #f0f4f8;
    vertical-align: middle;
}
.ret-table tbody tr:hover { background: #fafbff; }
.ret-table tbody tr:last-child td { border-bottom: none; }
.ret-table tfoot td {
    padding: 12px 14px;
    background: #fef3c7;
    font-weight: 700;
    font-size: 13.5px;
}

/* Invoice number */
.inv-badge {
    font-family: 'Space Mono', monospace;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
}
.inv-ret  { background: #fff3cd; color: #d97706; }
.inv-srn  { background: #fce7f3; color: #9d174d; }
.inv-sale { background: #e8f5ee; color: #1a6b3c; }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 48px 24px;
}
.empty-state-icon { font-size: 48px; margin-bottom: 12px; opacity: .3; }
.empty-state-text { color: #9ca3af; font-size: 14px; }

/* Modal enhancements */
.modal-content { border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-header {
    background: linear-gradient(135deg, #0d3d22, #1a6b3c);
    color: #fff;
    border-radius: 16px 16px 0 0;
    border-bottom: none;
    padding: 18px 24px;
}
.modal-header .modal-title { color: #fff; font-weight: 700; }
.modal-header .btn-close { filter: invert(1); }
.modal-body { padding: 24px; }
.modal-footer { border-top: 1px solid #f0f4f8; padding: 16px 24px; }

.section-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #9ca3af;
    margin-bottom: 8px;
}

.item-row-check { cursor: pointer; transition: background .15s; }
.item-row-check:hover { background: #f0f9f4 !important; }
.item-row-check.selected { background: #e8f5ee !important; }

.refund-total-bar {
    background: linear-gradient(135deg, #1a6b3c 0%, #1d7a44 100%);
    border-radius: 10px;
    padding: 14px 20px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;
}
.refund-total-bar .amount { font-size: 22px; font-weight: 800; }

/* Supplier return modal header */
.modal-header-supplier {
    background: linear-gradient(135deg, #7c2d12, #d97706) !important;
}

/* Process return modal header */
.modal-header-process {
    background: linear-gradient(135deg, #1e3a5f, #2176ae) !important;
}

/* View modal header */
.modal-header-view {
    background: linear-gradient(135deg, #374151, #1a1f2e) !important;
}

@media (max-width: 768px) {
    .ret-hero { padding: 20px; }
    .ret-hero-title { font-size: 18px; }
    .ret-tabs { width: 100%; }
    .ret-tab { flex: 1; justify-content: center; }
}
</style>

<!-- ============================================================
     HERO HEADER
     ============================================================ -->
<div class="ret-hero">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3" style="position:relative;z-index:1;">
        <div>
            <div class="ret-hero-title">
                <i class="bi bi-arrow-return-left me-2" style="color:#f0a500;"></i>Returns Management
            </div>
            <div class="ret-hero-sub">Manage customer refunds, exchanges & supplier returns</div>
        </div>
        <div class="ret-hero-actions">
            <button class="btn-ret-primary" data-bs-toggle="modal" data-bs-target="#findSaleModal">
                <i class="bi bi-person-dash"></i> Customer Return
            </button>
            <button class="btn-ret-warning" data-bs-toggle="modal" data-bs-target="#supplierReturnModal">
                <i class="bi bi-truck"></i> Return to Company
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     STATS ROW
     ============================================================ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="ret-stat">
            <div class="ret-stat-accent" style="background:#e63946;"></div>
            <div class="ret-stat-icon" style="background:#fee2e2; color:#e63946;">
                <i class="bi bi-arrow-return-left"></i>
            </div>
            <div class="ret-stat-val"><?= count($returns) ?></div>
            <div class="ret-stat-label">Customer Returns</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ret-stat">
            <div class="ret-stat-accent" style="background:#f0a500;"></div>
            <div class="ret-stat-icon" style="background:#fff8e6; color:#d97706;">
                <i class="bi bi-cash-coin"></i>
            </div>
            <div class="ret-stat-val">৳<?= number_format($totalRefund, 0) ?></div>
            <div class="ret-stat-label">Total Refunded</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ret-stat">
            <div class="ret-stat-accent" style="background:#d97706;"></div>
            <div class="ret-stat-icon" style="background:#fef3c7; color:#d97706;">
                <i class="bi bi-truck"></i>
            </div>
            <div class="ret-stat-val"><?= count($supplierReturns) ?></div>
            <div class="ret-stat-label">Supplier Returns</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="ret-stat">
            <div class="ret-stat-accent" style="background:#2176ae;"></div>
            <div class="ret-stat-icon" style="background:#dbeafe; color:#2176ae;">
                <i class="bi bi-arrow-left-right"></i>
            </div>
            <div class="ret-stat-val"><?= count(array_filter($returns, fn($r) => $r['return_type'] === 'exchange')) ?></div>
            <div class="ret-stat-label">Exchanges</div>
        </div>
    </div>
</div>

<!-- ============================================================
     TABS
     ============================================================ -->
<div class="ret-tabs">
    <a href="?tab=customer" class="ret-tab <?= $activeTab === 'customer' ? 'active' : '' ?>">
        <span class="tab-dot"></span>
        <i class="bi bi-person-dash" style="font-size:14px;"></i> Customer Returns
    </a>
    <a href="?tab=supplier" class="ret-tab <?= $activeTab === 'supplier' ? 'active' : '' ?>">
        <span class="tab-dot"></span>
        <i class="bi bi-truck" style="font-size:14px;"></i> Supplier Returns
    </a>
</div>

<!-- ============================================================
     CUSTOMER RETURNS TAB
     ============================================================ -->
<?php if ($activeTab === 'customer'): ?>

<!-- Filter -->
<div class="ret-filter mb-3">
    <form class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="customer">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="🔍 Search return / sale invoice..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0 text-muted small">From</label>
        </div>
        <div class="col-md-2">
            <input type="date" name="from" class="form-control form-control-sm" value="<?= $dateFrom ?>">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0 text-muted small">To</label>
        </div>
        <div class="col-md-2">
            <input type="date" name="to" class="form-control form-control-sm" value="<?= $dateTo ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-success"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="returns.php?tab=customer" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="ret-card">
    <div class="ret-card-header">
        <div class="ret-card-title">
            <i class="bi bi-list-ul" style="color:#e63946;"></i>
            Customer Return Records
        </div>
        <span class="badge bg-danger bg-opacity-10 text-danger fw-600" style="border-radius:20px; padding:5px 12px;">
            <?= count($returns) ?> records
        </span>
    </div>
    <div class="table-responsive">
        <table class="ret-table">
            <thead>
                <tr>
                    <th>Return Invoice</th>
                    <th>Sale Invoice</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th>Refund Amount</th>
                    <th>Staff</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($returns as $r): ?>
                <tr>
                    <td><span class="inv-badge inv-ret"><?= htmlspecialchars($r['invoice_no']) ?></span></td>
                    <td>
                        <a href="invoice.php?id=<?= $r['sale_id'] ?>" class="inv-badge inv-sale" style="text-decoration:none;">
                            <?= htmlspecialchars($r['sale_invoice'] ?? '—') ?>
                        </a>
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($r['customer_name'] ?? 'Walk-in') ?></div>
                    </td>
                    <td>
                        <?php if ($r['return_type'] === 'exchange'): ?>
                            <span class="badge-exchange">🔄 Exchange</span>
                        <?php else: ?>
                            <span class="badge-refund">💰 Refund</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:150px; font-size:12px; color:#6b7280;">
                        <?= htmlspecialchars($r['reason'] ?? '—') ?>
                    </td>
                    <td style="font-weight:800; color:#e63946; font-size:14px;">
                        ৳<?= number_format($r['refund_amount'], 2) ?>
                    </td>
                    <td style="font-size:12px; color:#9ca3af;"><?= htmlspecialchars($r['staff_name'] ?? '') ?></td>
                    <td style="font-size:12px; color:#9ca3af; white-space:nowrap;">
                        <?= date('d M Y', strtotime($r['return_date'])) ?><br>
                        <span style="font-size:11px;"><?= date('h:i A', strtotime($r['return_date'])) ?></span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="returns.php?view=<?= $r['id'] ?>&tab=customer" class="btn btn-sm btn-outline-secondary" title="View Details" style="border-radius:7px;">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (hasRole('admin')): ?>
                            <a href="returns.php?delete=<?= $r['id'] ?>&tab=customer" class="btn btn-sm btn-outline-danger" style="border-radius:7px;" data-confirm="Delete this return? Stock will be reversed.">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (!$returns): ?>
                <tr><td colspan="9">
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <div class="empty-state-text">No customer returns in this period</div>
                    </div>
                </td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($returns): ?>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right; color:#6b7280;">Total Refunds:</td>
                    <td style="color:#e63946; font-weight:800;">৳<?= number_format($totalRefund, 2) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php else: ?>
<!-- ============================================================
     SUPPLIER RETURNS TAB
     ============================================================ -->
<div class="ret-card">
    <div class="ret-card-header">
        <div class="ret-card-title">
            <i class="bi bi-truck" style="color:#d97706;"></i>
            Supplier / Company Return Records
        </div>
        <span class="badge bg-warning bg-opacity-10 text-warning fw-600" style="border-radius:20px; padding:5px 12px;">
            <?= count($supplierReturns) ?> records
        </span>
    </div>
    <div class="table-responsive">
        <table class="ret-table">
            <thead>
                <tr>
                    <th>SRN Invoice</th>
                    <th>Medicine</th>
                    <th>Supplier / Company</th>
                    <th>Qty Returned</th>
                    <th>Unit Price</th>
                    <th>Total Value</th>
                    <th>Reason</th>
                    <th>Staff</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($supplierReturns as $sr): ?>
                <tr>
                    <td><span class="inv-badge inv-srn"><?= htmlspecialchars($sr['invoice_no']) ?></span></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($sr['medicine_name'] ?? '—') ?></td>
                    <td>
                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($sr['supplier_name'] ?? '—') ?></div>
                    </td>
                    <td>
                        <span style="background:#fef3c7; color:#d97706; font-weight:700; padding:3px 10px; border-radius:20px; font-size:12px;">
                            <?= $sr['quantity'] ?> units
                        </span>
                    </td>
                    <td>৳<?= number_format($sr['unit_price'], 2) ?></td>
                    <td style="font-weight:800; color:#d97706; font-size:14px;">
                        ৳<?= number_format($sr['total_amount'], 2) ?>
                    </td>
                    <td style="font-size:12px; color:#6b7280; max-width:140px;"><?= htmlspecialchars($sr['reason'] ?? '—') ?></td>
                    <td style="font-size:12px; color:#9ca3af;"><?= htmlspecialchars($sr['staff_name'] ?? '') ?></td>
                    <td style="font-size:12px; color:#9ca3af; white-space:nowrap;">
                        <?= date('d M Y', strtotime($sr['return_date'])) ?><br>
                        <span style="font-size:11px;"><?= date('h:i A', strtotime($sr['return_date'])) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (!$supplierReturns): ?>
                <tr><td colspan="9">
                    <div class="empty-state">
                        <div class="empty-state-icon">🏭</div>
                        <div class="empty-state-text">No supplier returns recorded yet</div>
                    </div>
                </td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($supplierReturns): ?>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right; color:#6b7280;">Total Value Returned:</td>
                    <td style="color:#d97706; font-weight:800;">৳<?= number_format($totalSupplierReturn, 2) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php endif; ?>


<!-- ============================================================
     MODAL: VIEW CUSTOMER RETURN DETAILS
     ============================================================ -->
<?php if ($viewReturn): ?>
<div class="modal fade show d-block" style="background:rgba(0,0,0,0.6);" id="viewModal">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header modal-header-view">
        <h5 class="modal-title">
            <i class="bi bi-receipt me-2" style="color:#f0a500;"></i>
            Return Details — <?= htmlspecialchars($viewReturn['invoice_no']) ?>
        </h5>
        <a href="returns.php?tab=customer" class="btn-close"></a>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div style="background:#f8fafc; border-radius:10px; padding:16px;">
                    <div class="section-label">Sale Information</div>
                    <p class="mb-1"><strong>Sale Invoice:</strong>
                        <a href="invoice.php?id=<?= $viewReturn['sale_id'] ?>" class="text-success">
                            <?= htmlspecialchars($viewReturn['sale_invoice']) ?>
                        </a>
                    </p>
                    <p class="mb-1"><strong>Customer:</strong> <?= htmlspecialchars($viewReturn['customer_name'] ?? 'Walk-in') ?></p>
                    <p class="mb-0"><strong>Return Type:</strong>
                        <?php if ($viewReturn['return_type'] === 'exchange'): ?>
                            <span class="badge-exchange">🔄 Exchange</span>
                        <?php else: ?>
                            <span class="badge-refund">💰 Refund</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div style="background:#f8fafc; border-radius:10px; padding:16px;">
                    <div class="section-label">Return Information</div>
                    <p class="mb-1"><strong>Staff:</strong> <?= htmlspecialchars($viewReturn['staff_name'] ?? '') ?></p>
                    <p class="mb-1"><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($viewReturn['return_date'])) ?></p>
                    <p class="mb-0"><strong>Reason:</strong> <span style="color:#6b7280;"><?= htmlspecialchars($viewReturn['reason'] ?? 'N/A') ?></span></p>
                </div>
            </div>
        </div>

        <div class="section-label">Returned Items</div>
        <table class="ret-table" style="border-radius:10px; overflow:hidden; border:1px solid #e2e8f0;">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($viewItems as $vi): ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($vi['med_name']) ?></td>
                    <td style="text-align:center;">
                        <span style="background:#dbeafe; color:#2176ae; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:700;">
                            <?= $vi['quantity'] ?>
                        </span>
                    </td>
                    <td style="text-align:right;">৳<?= number_format($vi['unit_price'], 2) ?></td>
                    <td style="text-align:right; font-weight:700;">৳<?= number_format($vi['total_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="refund-total-bar mt-3">
            <div style="font-size:13px; opacity:.8;">Total Refund Amount</div>
            <div class="amount">৳<?= number_format($viewReturn['refund_amount'], 2) ?></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="returns.php?tab=customer" class="btn btn-outline-secondary">
            <i class="bi bi-x me-1"></i>Close
        </a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>


<!-- ============================================================
     MODAL: FIND SALE (Customer Return Step 1)
     ============================================================ -->
<div class="modal fade" id="findSaleModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
            <i class="bi bi-search me-2" style="color:#f0a500;"></i>Find Sale Invoice
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="GET">
        <input type="hidden" name="tab" value="customer">
        <div class="modal-body">
          <div class="section-label">Enter Sale Invoice Number</div>
          <input type="text" name="find_invoice" class="form-control form-control-lg"
                 placeholder="e.g. INV-20250503-001"
                 value="<?= htmlspecialchars($searchInvoice) ?>"
                 style="border-radius:10px; font-family:monospace; font-size:15px; letter-spacing:.5px;"
                 required autofocus>
          <div class="form-text mt-2" style="color:#9ca3af;">
              <i class="bi bi-info-circle me-1"></i>
              Enter the original sale invoice to select items for return.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-ret-primary">
              <i class="bi bi-search"></i>Find Invoice
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ============================================================
     MODAL: PROCESS CUSTOMER RETURN (Step 2 - auto show)
     ============================================================ -->
<?php if ($foundSale): ?>
<div class="modal fade show d-block" style="background:rgba(0,0,0,0.65);" id="returnModal">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header modal-header-process">
        <h5 class="modal-title">
            <i class="bi bi-arrow-return-left me-2" style="color:#f0a500;"></i>
            Process Return — <span style="font-family:monospace; font-size:14px;"><?= htmlspecialchars($foundSale['invoice_no']) ?></span>
        </h5>
        <a href="returns.php?tab=customer" class="btn-close"></a>
      </div>
      <form method="POST" id="returnForm">
        <input type="hidden" name="action" value="process_return">
        <input type="hidden" name="sale_id" value="<?= $foundSale['id'] ?>">
        <input type="hidden" name="return_items" id="returnItemsInput">
        <div class="modal-body">

          <!-- Sale summary -->
          <div style="background:linear-gradient(135deg,#e8f5ee,#dbeafe); border-radius:12px; padding:14px 18px; margin-bottom:20px; border-left:4px solid #1a6b3c;">
            <div class="row g-2">
                <div class="col"><span style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:700;">Customer</span><br><strong><?= htmlspecialchars($foundSale['customer_name'] ?? 'Walk-in') ?></strong></div>
                <div class="col"><span style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:700;">Sale Total</span><br><strong style="color:#1a6b3c;">৳<?= number_format($foundSale['total'], 2) ?></strong></div>
                <div class="col"><span style="font-size:11px;color:#6b7280;text-transform:uppercase;font-weight:700;">Sale Date</span><br><strong><?= date('d M Y', strtotime($foundSale['sale_date'])) ?></strong></div>
            </div>
          </div>

          <!-- Items table -->
          <div class="section-label">Select Items to Return</div>
          <div class="table-responsive" style="border-radius:10px; border:1px solid #e2e8f0; overflow:hidden;">
            <table class="ret-table">
              <thead>
                <tr>
                    <th style="width:40px;">
                        <input type="checkbox" id="selectAll" class="form-check-input" style="cursor:pointer;">
                    </th>
                    <th>Medicine</th>
                    <th>Sold</th>
                    <th>Returned</th>
                    <th>Available</th>
                    <th>Return Qty</th>
                    <th>Unit Price</th>
                    <th>Refund</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $saleItemsQ = $conn->query("
                    SELECT si.*, m.name med_name,
                           COALESCE((SELECT SUM(ri.quantity) FROM return_items ri
                                      JOIN returns r ON ri.return_id=r.id
                                      WHERE ri.sale_item_id=si.id AND r.status='completed'), 0) returned_qty
                    FROM sale_items si
                    JOIN medicines m ON si.medicine_id=m.id
                    WHERE si.sale_id=" . $foundSale['id']
                );
                $saleItemsData = $saleItemsQ->fetch_all(MYSQLI_ASSOC);
                foreach ($saleItemsData as $si):
                    $available = $si['quantity'] - $si['returned_qty'];
                ?>
                <tr class="item-row-check" id="row-<?= $si['id'] ?>">
                    <td>
                        <input type="checkbox" class="form-check-input item-check"
                               data-id="<?= $si['id'] ?>" data-med="<?= $si['medicine_id'] ?>"
                               data-price="<?= $si['unit_price'] ?>" data-max="<?= $available ?>"
                               <?= $available <= 0 ? 'disabled' : '' ?>>
                    </td>
                    <td style="font-weight:600;"><?= htmlspecialchars($si['med_name']) ?></td>
                    <td><?= $si['quantity'] ?></td>
                    <td>
                        <?php if ($si['returned_qty'] > 0): ?>
                        <span style="color:#e63946; font-weight:600;"><?= $si['returned_qty'] ?></span>
                        <?php else: ?>
                        <span style="color:#9ca3af;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($available > 0): ?>
                        <span style="background:#e8f5ee; color:#1a6b3c; font-weight:700; padding:2px 9px; border-radius:20px; font-size:12px;"><?= $available ?></span>
                        <?php else: ?>
                        <span style="background:#fee2e2; color:#e63946; font-weight:600; padding:2px 9px; border-radius:20px; font-size:12px;">Fully Returned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($available > 0): ?>
                        <input type="number" class="form-control form-control-sm qty-input"
                               min="1" max="<?= $available ?>" value="<?= $available ?>"
                               data-row="<?= $si['id'] ?>"
                               style="width:75px; border-radius:8px; text-align:center; font-weight:700;">
                        <?php else: ?>
                        <span style="color:#9ca3af; font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>৳<?= number_format($si['unit_price'], 2) ?></td>
                    <td class="fw-700 refund-cell" id="ref-<?= $si['id'] ?>" style="color:#e63946;">৳0.00</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Refund total bar -->
          <div class="refund-total-bar">
            <div>
                <div style="font-size:11px; opacity:.7; text-transform:uppercase; letter-spacing:.5px;">Total Refund Amount</div>
            </div>
            <div class="amount" id="totalRefundDisplay">৳0.00</div>
          </div>

          <!-- Return type & reason -->
          <div class="row g-3 mt-1">
            <div class="col-md-5">
              <label class="section-label">Return Type</label>
              <select name="return_type" class="form-select" style="border-radius:10px;">
                <option value="refund">💰 Refund — Money back to customer</option>
                <option value="exchange">🔄 Exchange — Replace with another product</option>
              </select>
            </div>
            <div class="col-md-7">
              <label class="section-label">Reason for Return</label>
              <input type="text" name="reason" class="form-control"
                     placeholder="e.g. Damaged product, Wrong medicine, Expired..."
                     style="border-radius:10px;" required>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <a href="returns.php?tab=customer" class="btn btn-outline-secondary">
              <i class="bi bi-x me-1"></i>Cancel
          </a>
          <button type="submit" class="btn-ret-primary" id="processReturnBtn" disabled>
              <i class="bi bi-check2-circle"></i>Process Return
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checks   = document.querySelectorAll('.item-check:not([disabled])');
    const selectAll = document.getElementById('selectAll');
    const processBtn = document.getElementById('processReturnBtn');

    function calcRefund() {
        let total = 0, selected = [];
        checks.forEach(cb => {
            const row   = cb.dataset.id;
            const price = parseFloat(cb.dataset.price);
            const qtyIn = document.querySelector('.qty-input[data-row="' + row + '"]');
            const qty   = qtyIn ? (parseInt(qtyIn.value) || 0) : 0;
            const cell  = document.getElementById('ref-' + row);
            const tr    = document.getElementById('row-' + row);
            if (cb.checked) {
                const refund = price * qty;
                total += refund;
                if (cell) cell.textContent = '৳' + refund.toFixed(2);
                if (tr)   tr.classList.add('selected');
                selected.push({ sale_item_id: parseInt(row), medicine_id: parseInt(cb.dataset.med), unit_price: price, return_qty: qty });
            } else {
                if (cell) cell.textContent = '৳0.00';
                if (tr)   tr.classList.remove('selected');
            }
        });
        document.getElementById('totalRefundDisplay').textContent = '৳' + total.toFixed(2);
        document.getElementById('returnItemsInput').value = JSON.stringify(selected);
        processBtn.disabled = selected.length === 0;
    }

    checks.forEach(cb => cb.addEventListener('change', calcRefund));
    document.querySelectorAll('.qty-input').forEach(inp => inp.addEventListener('input', calcRefund));
    selectAll.addEventListener('change', function () { checks.forEach(cb => cb.checked = this.checked); calcRefund(); });
});
</script>
<?php endif; ?>


<!-- ============================================================
     MODAL: SUPPLIER / COMPANY RETURN
     ============================================================ -->
<div class="modal fade" id="supplierReturnModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header modal-header-supplier">
        <h5 class="modal-title">
            <i class="bi bi-truck me-2" style="color:#fbbf24;"></i>Return to Company / Supplier
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="process_supplier_return">
        <div class="modal-body">

          <!-- Info banner -->
          <div style="background:#fef3c7; border-left:4px solid #f0a500; border-radius:10px; padding:12px 16px; margin-bottom:20px;">
            <div style="font-weight:700; color:#92400e; font-size:13px;">
                <i class="bi bi-info-circle me-1"></i> Return to Company
            </div>
            <div style="font-size:12px; color:#78350f; margin-top:3px;">
                This will deduct the selected quantity from your stock and record a Supplier Return Note (SRN).
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="section-label">Supplier / Company *</label>
              <select name="supplier_id" class="form-select" style="border-radius:10px;" required>
                <option value="">— Select Supplier —</option>
                <?php foreach ($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="section-label">Medicine / Product *</label>
              <select name="medicine_id" id="medicineSel" class="form-select" style="border-radius:10px;" required onchange="fillPrice(this)">
                <option value="">— Select Medicine —</option>
                <?php foreach ($medicines as $m): ?>
                <option value="<?= $m['id'] ?>" data-price="<?= $m['purchase_price'] ?>" data-stock="<?= $m['stock_qty'] ?>">
                    <?= htmlspecialchars($m['name']) ?> (Stock: <?= $m['stock_qty'] ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="section-label">Quantity to Return *</label>
              <input type="number" name="qty" id="retQty" class="form-control"
                     min="1" placeholder="0"
                     style="border-radius:10px; font-size:16px; font-weight:700; text-align:center;"
                     required oninput="calcSRNTotal()">
              <div class="form-text" id="stockHint" style="color:#f0a500;"></div>
            </div>
            <div class="col-md-4">
              <label class="section-label">Unit Price (Purchase Price)</label>
              <div class="input-group">
                  <span class="input-group-text" style="border-radius:10px 0 0 10px;">৳</span>
                  <input type="number" name="unit_price" id="retUnitPrice" class="form-control"
                         min="0" step="0.01" placeholder="0.00"
                         style="border-radius:0 10px 10px 0; font-weight:700;"
                         oninput="calcSRNTotal()">
              </div>
            </div>
            <div class="col-md-4">
              <label class="section-label">Total Return Value</label>
              <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px; font-size:18px; font-weight:800; color:#d97706;" id="srnTotal">
                  ৳0.00
              </div>
            </div>
            <div class="col-12">
              <label class="section-label">Reason for Return *</label>
              <input type="text" name="reason" class="form-control"
                     placeholder="e.g. Expired batch, Damaged packaging, Wrong product received..."
                     style="border-radius:10px;" required>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-ret-warning">
              <i class="bi bi-truck"></i>Submit Supplier Return
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function fillPrice(sel) {
    const opt = sel.options[sel.selectedIndex];
    const price = opt.dataset.price || 0;
    const stock = opt.dataset.stock || 0;
    document.getElementById('retUnitPrice').value = parseFloat(price).toFixed(2);
    document.getElementById('retQty').max = stock;
    document.getElementById('stockHint').textContent = stock > 0 ? 'Available stock: ' + stock + ' units' : '⚠ Out of stock';
    calcSRNTotal();
}
function calcSRNTotal() {
    const qty   = parseFloat(document.getElementById('retQty').value) || 0;
    const price = parseFloat(document.getElementById('retUnitPrice').value) || 0;
    document.getElementById('srnTotal').textContent = '৳' + (qty * price).toFixed(2);
}
</script>

<?php include '../includes/footer.php'; ?>