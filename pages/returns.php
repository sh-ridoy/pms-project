<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

// Process return
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
        $stmt = $conn->prepare("INSERT INTO returns (invoice_no, sale_id, user_id, return_type, reason, refund_amount, status) VALUES (?,?,?,?,?,?,'completed')");
        $stmt->bind_param('ssisd', $invoice, $saleId, $userId, $returnType, $reason, $refundTotal);
        $stmt->execute();
        $returnId = $conn->insert_id;

        $ri  = $conn->prepare("INSERT INTO return_items (return_id, sale_item_id, medicine_id, quantity, unit_price, total_price) VALUES (?,?,?,?,?,?)");
        $upd = $conn->prepare("UPDATE medicines SET stock_qty = stock_qty + ? WHERE id = ?");

        foreach ($items as $item) {
            $qty       = intval($item['return_qty']);
            $medId     = intval($item['medicine_id']);
            $saleItemId= intval($item['sale_item_id']);
            $unitPrice = floatval($item['unit_price']);
            $totalP    = $unitPrice * $qty;

            $ri->bind_param('iiidd', $returnId, $saleItemId, $medId, $qty, $unitPrice, $totalP);
            $ri->execute();

            // Restore stock
            $upd->bind_param('ii', $qty, $medId);
            $upd->execute();
        }

        flashMessage('success', "Return processed! Invoice: $invoice | Refund: ৳" . number_format($refundTotal, 2));
        header('Location: returns.php'); exit;
    } else {
        flashMessage('error', 'Please select items to return.');
    }
}

// Handle delete return (admin only)
if (isset($_GET['delete']) && hasRole('admin')) {
    $id = intval($_GET['delete']);
    // Reverse stock
    $ritems = $conn->query("SELECT medicine_id, quantity FROM return_items WHERE return_id=$id")->fetch_all(MYSQLI_ASSOC);
    foreach ($ritems as $ri) {
        $conn->query("UPDATE medicines SET stock_qty = stock_qty - {$ri['quantity']} WHERE id={$ri['medicine_id']}");
    }
    $conn->query("DELETE FROM return_items WHERE return_id=$id");
    $conn->query("DELETE FROM returns WHERE id=$id");
    flashMessage('success', 'Return record deleted.');
    header('Location: returns.php'); exit;
}

// Fetch sale for return modal
$saleForReturn = null;
$saleItems = [];
if (isset($_GET['sale_id'])) {
    $sid = intval($_GET['sale_id']);
    $saleForReturn = $conn->query("SELECT s.*, c.name customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE s.id=$sid")->fetch_assoc();
    if ($saleForReturn) {
        $saleItems = $conn->query("
            SELECT si.*, m.name med_name, m.purchase_price,
                   COALESCE((SELECT SUM(ri.quantity) FROM return_items ri 
                              JOIN returns r ON ri.return_id=r.id 
                              WHERE ri.sale_item_id=si.id AND r.status='completed'), 0) returned_qty
            FROM sale_items si 
            JOIN medicines m ON si.medicine_id=m.id 
            WHERE si.sale_id=$sid
        ")->fetch_all(MYSQLI_ASSOC);
    }
}

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

// Search sale for return
$searchInvoice = trim($_GET['find_invoice'] ?? '');
$foundSale = null;
if ($searchInvoice) {
    $esc = $conn->real_escape_string($searchInvoice);
    $foundSale = $conn->query("SELECT s.*, c.name customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE s.invoice_no='$esc'")->fetch_assoc();
    if (!$foundSale) flashMessage('error', "Invoice '$searchInvoice' not found.");
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-arrow-return-left me-2 text-warning"></i>Product Returns</div>
        <div class="page-subtitle">Manage product returns and refunds</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#findSaleModal">
        <i class="bi bi-plus-circle"></i> New Return
    </button>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#e63946;--card-bg:#fee2e2;--card-color:#e63946;">
            <div class="stat-icon"><i class="bi bi-arrow-return-left"></i></div>
            <div class="stat-value"><?= count($returns) ?></div>
            <div class="stat-label">Total Returns</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#f0a500;--card-bg:#fff8e6;--card-color:#d4900a;">
            <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="stat-value">৳<?= number_format($totalRefund, 0) ?></div>
            <div class="stat-label">Total Refunded</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#2176ae;--card-bg:#dbeafe;--card-color:#2176ae;">
            <div class="stat-icon"><i class="bi bi-calendar-range"></i></div>
            <div class="stat-value"><?= date('d M', strtotime($dateFrom)) ?> – <?= date('d M', strtotime($dateTo)) ?></div>
            <div class="stat-label">Period</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="--card-accent:#1a6b3c;--card-bg:#e8f5ee;--card-color:#1a6b3c;">
            <div class="stat-icon"><i class="bi bi-box-arrow-in-down"></i></div>
            <div class="stat-value"><?= count(array_filter($returns, fn($r) => $r['return_type'] === 'exchange')) ?></div>
            <div class="stat-label">Exchanges</div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Return or Sale invoice..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="to" class="form-control" value="<?= $dateTo ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-success"><i class="bi bi-filter me-1"></i>Filter</button>
                <a href="returns.php" class="btn btn-outline-secondary ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Returns Table -->
<div class="card">
    <div class="card-header justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>Return Records</span>
        <span class="badge bg-warning text-dark"><?= count($returns) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>Return Invoice</th><th>Sale Invoice</th><th>Customer</th><th>Type</th><th>Reason</th><th>Refund</th><th>Staff</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php foreach ($returns as $r): ?>
                <tr>
                    <td class="fw-600 text-warning"><?= htmlspecialchars($r['invoice_no']) ?></td>
                    <td><a href="invoice.php?id=<?= $r['sale_id'] ?>" class="text-success"><?= htmlspecialchars($r['sale_invoice'] ?? '—') ?></a></td>
                    <td><?= htmlspecialchars($r['customer_name'] ?? 'Walk-in') ?></td>
                    <td>
                        <?php if ($r['return_type'] === 'exchange'): ?>
                            <span class="badge bg-info">Exchange</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Refund</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted" style="max-width:150px; font-size:12px;"><?= htmlspecialchars($r['reason'] ?? '—') ?></td>
                    <td class="fw-700 text-danger">৳<?= number_format($r['refund_amount'], 2) ?></td>
                    <td class="text-muted" style="font-size:12px;"><?= htmlspecialchars($r['staff_name'] ?? '') ?></td>
                    <td class="text-muted" style="font-size:12px;"><?= date('d M Y h:i A', strtotime($r['return_date'])) ?></td>
                    <td>
                        <a href="returns.php?view=<?= $r['id'] ?>" class="btn btn-sm btn-outline-warning" title="View Details">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php if (hasRole('admin')): ?>
                        <a href="returns.php?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" data-confirm="Delete this return? Stock will be reversed.">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$returns): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No returns in this period</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($returns): ?>
            <tfoot>
                <tr class="table-light fw-700">
                    <td colspan="5" class="text-end">Total Refunds:</td>
                    <td class="text-danger">৳<?= number_format($totalRefund, 2) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- View Return Details -->
<?php
if (isset($_GET['view'])) {
    $vid = intval($_GET['view']);
    $vr = $conn->query("SELECT r.*, s.invoice_no sale_invoice, c.name customer_name, u.full_name staff_name FROM returns r LEFT JOIN sales s ON r.sale_id=s.id LEFT JOIN customers c ON s.customer_id=c.id LEFT JOIN users u ON r.user_id=u.id WHERE r.id=$vid")->fetch_assoc();
    $vitems = $conn->query("SELECT ri.*, m.name med_name FROM return_items ri JOIN medicines m ON ri.medicine_id=m.id WHERE ri.return_id=$vid")->fetch_all(MYSQLI_ASSOC);
    if ($vr):
?>
<div class="modal fade show d-block" style="background:rgba(0,0,0,0.5);" id="viewModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-arrow-return-left text-warning me-2"></i>Return Details — <?= htmlspecialchars($vr['invoice_no']) ?></h5>
        <a href="returns.php" class="btn-close"></a>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <p class="mb-1"><strong>Sale Invoice:</strong> <a href="invoice.php?id=<?= $vr['sale_id'] ?>" class="text-success"><?= htmlspecialchars($vr['sale_invoice']) ?></a></p>
                <p class="mb-1"><strong>Customer:</strong> <?= htmlspecialchars($vr['customer_name'] ?? 'Walk-in') ?></p>
                <p class="mb-1"><strong>Type:</strong> <span class="badge <?= $vr['return_type']==='exchange' ? 'bg-info':'bg-danger' ?>"><?= ucfirst($vr['return_type']) ?></span></p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>Staff:</strong> <?= htmlspecialchars($vr['staff_name'] ?? '') ?></p>
                <p class="mb-1"><strong>Date:</strong> <?= date('d M Y h:i A', strtotime($vr['return_date'])) ?></p>
                <p class="mb-1"><strong>Reason:</strong> <?= htmlspecialchars($vr['reason'] ?? 'N/A') ?></p>
            </div>
        </div>
        <table class="table table-bordered">
            <thead class="table-light"><tr><th>Medicine</th><th>Qty Returned</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody>
                <?php foreach ($vitems as $vi): ?>
                <tr>
                    <td><?= htmlspecialchars($vi['med_name']) ?></td>
                    <td><?= $vi['quantity'] ?></td>
                    <td>৳<?= number_format($vi['unit_price'], 2) ?></td>
                    <td>৳<?= number_format($vi['total_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-700 table-warning">
                    <td colspan="3" class="text-end">Total Refund:</td>
                    <td>৳<?= number_format($vr['refund_amount'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
      </div>
      <div class="modal-footer">
        <a href="returns.php" class="btn btn-secondary">Close</a>
      </div>
    </div>
  </div>
</div>
<?php endif; } ?>

<!-- Find Sale Modal -->
<div class="modal fade" id="findSaleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-search me-2"></i>Find Sale Invoice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="GET">
        <div class="modal-body">
          <label class="form-label fw-600">Enter Sale Invoice Number</label>
          <input type="text" name="find_invoice" class="form-control form-control-lg" 
                 placeholder="e.g. INV-20250503-001" value="<?= htmlspecialchars($searchInvoice) ?>" required>
          <div class="form-text">Enter the original sale invoice number to process return.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-search me-1"></i>Find Invoice</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($foundSale): ?>
<!-- Process Return Modal (auto-show) -->
<div class="modal fade show d-block" style="background:rgba(0,0,0,0.5);" id="returnModal">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-arrow-return-left text-warning me-2"></i>Process Return — <?= htmlspecialchars($foundSale['invoice_no']) ?></h5>
        <a href="returns.php" class="btn-close"></a>
      </div>
      <form method="POST" id="returnForm">
        <input type="hidden" name="action" value="process_return">
        <input type="hidden" name="sale_id" value="<?= $foundSale['id'] ?>">
        <input type="hidden" name="return_items" id="returnItemsInput">
        <div class="modal-body">
          <!-- Sale Info -->
          <div class="alert alert-info py-2 mb-3">
            <div class="row">
                <div class="col"><strong>Customer:</strong> <?= htmlspecialchars($foundSale['customer_name'] ?? 'Walk-in') ?></div>
                <div class="col"><strong>Sale Total:</strong> ৳<?= number_format($foundSale['total'], 2) ?></div>
                <div class="col"><strong>Date:</strong> <?= date('d M Y', strtotime($foundSale['sale_date'])) ?></div>
            </div>
          </div>

          <!-- Items -->
          <table class="table table-bordered mb-3">
            <thead class="table-light"><tr>
                <th><input type="checkbox" id="selectAll" class="form-check-input"> Select All</th>
                <th>Medicine</th><th>Sold Qty</th><th>Already Returned</th><th>Return Qty</th><th>Unit Price</th><th>Refund</th>
            </tr></thead>
            <tbody>
                <?php 
                $saleItemsQuery = $conn->query("
                    SELECT si.*, m.name med_name, m.purchase_price,
                           COALESCE((SELECT SUM(ri.quantity) FROM return_items ri 
                                      JOIN returns r ON ri.return_id=r.id 
                                      WHERE ri.sale_item_id=si.id AND r.status='completed'), 0) returned_qty
                    FROM sale_items si 
                    JOIN medicines m ON si.medicine_id=m.id 
                    WHERE si.sale_id=" . $foundSale['id']
                );
                $saleItemsData = $saleItemsQuery->fetch_all(MYSQLI_ASSOC);
                foreach ($saleItemsData as $si): 
                    $available = $si['quantity'] - $si['returned_qty'];
                ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input item-check" 
                               data-id="<?= $si['id'] ?>" data-med="<?= $si['medicine_id'] ?>"
                               data-price="<?= $si['unit_price'] ?>" data-max="<?= $available ?>"
                               <?= $available <= 0 ? 'disabled' : '' ?>></td>
                    <td class="fw-600"><?= htmlspecialchars($si['med_name']) ?></td>
                    <td><?= $si['quantity'] ?></td>
                    <td class="text-danger"><?= $si['returned_qty'] > 0 ? $si['returned_qty'] : '—' ?></td>
                    <td>
                        <?php if ($available > 0): ?>
                        <input type="number" class="form-control form-control-sm qty-input" 
                               min="1" max="<?= $available ?>" value="<?= $available ?>" 
                               style="width:80px;" data-row="<?= $si['id'] ?>">
                        <?php else: ?>
                        <span class="badge bg-secondary">Fully Returned</span>
                        <?php endif; ?>
                    </td>
                    <td>৳<?= number_format($si['unit_price'], 2) ?></td>
                    <td class="fw-700 text-danger refund-cell" id="ref-<?= $si['id'] ?>">৳0.00</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-warning fw-700">
                    <td colspan="6" class="text-end">Total Refund:</td>
                    <td id="totalRefundDisplay">৳0.00</td>
                </tr>
            </tfoot>
          </table>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-600">Return Type</label>
              <select name="return_type" class="form-select" required>
                <option value="refund">💰 Refund (Money back)</option>
                <option value="exchange">🔄 Exchange (Replace product)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-600">Reason for Return</label>
              <input type="text" name="reason" class="form-control" placeholder="e.g. Damaged, Wrong medicine, Expired..." required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <a href="returns.php" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-warning fw-600" id="processReturnBtn" disabled>
            <i class="bi bi-arrow-return-left me-1"></i>Process Return
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checks = document.querySelectorAll('.item-check:not([disabled])');
    const selectAll = document.getElementById('selectAll');
    const processBtn = document.getElementById('processReturnBtn');

    function calcRefund() {
        let total = 0;
        let selected = [];
        checks.forEach(cb => {
            const row = cb.dataset.id;
            const price = parseFloat(cb.dataset.price);
            const qtyInput = document.querySelector(`.qty-input[data-row="${row}"]`);
            const qty = qtyInput ? parseInt(qtyInput.value) || 0 : 0;
            const cell = document.getElementById('ref-' + row);
            if (cb.checked) {
                const refund = price * qty;
                total += refund;
                if (cell) cell.textContent = '৳' + refund.toFixed(2);
                selected.push({
                    sale_item_id: parseInt(cb.dataset.id),
                    medicine_id: parseInt(cb.dataset.med),
                    unit_price: price,
                    return_qty: qty
                });
            } else {
                if (cell) cell.textContent = '৳0.00';
            }
        });
        document.getElementById('totalRefundDisplay').textContent = '৳' + total.toFixed(2);
        document.getElementById('returnItemsInput').value = JSON.stringify(selected);
        processBtn.disabled = selected.length === 0;
    }

    checks.forEach(cb => {
        cb.addEventListener('change', calcRefund);
    });

    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', calcRefund);
    });

    selectAll.addEventListener('change', function() {
        checks.forEach(cb => cb.checked = this.checked);
        calcRefund();
    });
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
