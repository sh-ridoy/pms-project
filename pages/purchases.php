<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

// Handle new purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    $cartData   = json_decode($_POST['cart_data'], true);
    $supplierId = intval($_POST['supplier_id'] ?? 0) ?: null;
    $paidAmt    = floatval($_POST['paid_amount'] ?? 0);
    $status     = $_POST['status'] ?? 'received';
    $notes      = trim($_POST['notes'] ?? '');

    if (!empty($cartData)) {
        $total = 0;
        foreach ($cartData as $item) $total += $item['price'] * $item['qty'];
        $invoice = generateInvoice('PUR');
        $userId  = $_SESSION['user_id'];

        $stmt = $conn->prepare("INSERT INTO purchases (invoice_no,supplier_id,user_id,total,paid_amount,status,notes) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('siiddss', $invoice, $supplierId, $userId, $total, $paidAmt, $status, $notes);
        $stmt->execute();
        $purchaseId = $conn->insert_id;

        $pi  = $conn->prepare("INSERT INTO purchase_items (purchase_id,medicine_id,quantity,unit_price,total_price) VALUES (?,?,?,?,?)");
        $upd = $conn->prepare("UPDATE medicines SET stock_qty = stock_qty + ? WHERE id = ?");
        foreach ($cartData as $item) {
            $tp = $item['price'] * $item['qty'];
            $pi->bind_param('iiidd', $purchaseId, $item['id'], $item['qty'], $item['price'], $tp);
            $pi->execute();
            if ($status === 'received') {
                $upd->bind_param('ii', $item['qty'], $item['id']);
                $upd->execute();
            }
        }
        flashMessage('success', "Purchase recorded! Invoice: $invoice");
        header('Location: purchases.php'); exit;
    }
}

$purchases = $conn->query("SELECT p.*, s.name supplier_name, u.full_name staff FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id LEFT JOIN users u ON p.user_id=u.id ORDER BY p.purchase_date DESC")->fetch_all(MYSQLI_ASSOC);
$suppliers = $conn->query("SELECT * FROM suppliers WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$medicines = $conn->query("SELECT id, name, unit, purchase_price FROM medicines WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-bag-check-fill me-2 text-success"></i>Purchases</div>
        <div class="page-subtitle">Record stock purchases from suppliers</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#purchaseModal">
        <i class="bi bi-plus-circle"></i> New Purchase
    </button>
</div>

<div class="card">
    <div class="card-header justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>Purchase Records</span>
        <span class="badge bg-success"><?= count($purchases) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>Invoice</th><th>Supplier</th><th>Total</th><th>Paid</th><th>Status</th><th>Staff</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php foreach ($purchases as $p): ?>
                <tr>
                    <td><span class="fw-600 text-success"><?= htmlspecialchars($p['invoice_no']) ?></span></td>
                    <td><?= htmlspecialchars($p['supplier_name'] ?? '—') ?></td>
                    <td class="fw-700">৳<?= number_format($p['total'], 2) ?></td>
                    <td>৳<?= number_format($p['paid_amount'], 2) ?></td>
                    <td>
                        <?php
                        $sc = ['received'=>'badge-active','pending'=>'badge-warning','partial'=>'badge-info'];
                        ?>
                        <span class="badge <?= $sc[$p['status']] ?? 'badge-info' ?>"><?= ucfirst($p['status']) ?></span>
                    </td>
                    <td class="text-muted" style="font-size:12px;"><?= htmlspecialchars($p['staff'] ?? '') ?></td>
                    <td class="text-muted" style="font-size:12px;"><?= date('d M Y', strtotime($p['purchase_date'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info" onclick="viewPurchaseItems(<?= $p['id'] ?>)" title="View Items"><i class="bi bi-eye"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$purchases): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No purchases yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Purchase Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-bag-plus me-2"></i>New Purchase Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="purchaseForm">
                <input type="hidden" name="cart_data" id="purchaseCartData">
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Supplier</label>
                            <select name="supplier_id" class="form-select">
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Paid Amount (৳)</label>
                            <input type="number" name="paid_amount" class="form-control" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="received">Received</option>
                                <option value="pending">Pending</option>
                                <option value="partial">Partial</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="Optional">
                        </div>
                    </div>

                    <h6 class="fw-700 mb-3"><i class="bi bi-plus-circle me-2 text-success"></i>Add Medicines</h6>
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-5">
                            <label class="form-label">Medicine</label>
                            <select id="pMedSelect" class="form-select">
                                <option value="">Select Medicine</option>
                                <?php foreach ($medicines as $m): ?>
                                <option value="<?= $m['id'] ?>" data-price="<?= $m['purchase_price'] ?>" data-unit="<?= htmlspecialchars($m['unit']) ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['unit']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit Price (৳)</label>
                            <input type="number" id="pPrice" class="form-control" value="0" step="0.01" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quantity</label>
                            <input type="number" id="pQty" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-success w-100" onclick="addToPurchaseCart()"><i class="bi bi-plus-circle me-1"></i>Add</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered" id="purchaseTable">
                            <thead class="table-light"><tr><th>Medicine</th><th>Qty</th><th>Unit Price</th><th>Total</th><th></th></tr></thead>
                            <tbody id="purchaseCartBody">
                                <tr id="emptyRow"><td colspan="5" class="text-center text-muted py-3">No items added</td></tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-700"><td colspan="3" class="text-end">Grand Total:</td><td id="purTotal">৳ 0.00</td><td></td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-primary-custom" onclick="submitPurchase()"><i class="bi bi-check-circle me-1"></i>Save Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Items Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-list-ul me-2"></i>Purchase Items</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody">Loading...</div>
        </div>
    </div>
</div>

<script>
let purchaseCart = [];

document.getElementById('pMedSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('pPrice').value = opt.dataset.price || 0;
});

function addToPurchaseCart() {
    const sel = document.getElementById('pMedSelect');
    const id  = parseInt(sel.value);
    if (!id) { alert('Select a medicine!'); return; }
    const name  = sel.options[sel.selectedIndex].text;
    const price = parseFloat(document.getElementById('pPrice').value) || 0;
    const qty   = parseInt(document.getElementById('pQty').value) || 1;

    const ex = purchaseCart.find(i => i.id === id);
    if (ex) { ex.qty += qty; ex.price = price; }
    else purchaseCart.push({ id, name, price, qty });
    renderPurchaseCart();
}

function removePurchaseItem(id) {
    purchaseCart = purchaseCart.filter(i => i.id !== id);
    renderPurchaseCart();
}

function renderPurchaseCart() {
    const tbody = document.getElementById('purchaseCartBody');
    const empty = document.getElementById('emptyRow');
    if (!purchaseCart.length) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="5" class="text-center text-muted py-3">No items added</td></tr>';
        document.getElementById('purTotal').textContent = '৳ 0.00';
        return;
    }
    tbody.innerHTML = purchaseCart.map(i => `
        <tr>
            <td>${i.name.replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}</td>
            <td>${i.qty}</td>
            <td>৳${i.price.toFixed(2)}</td>
            <td class="fw-600">৳${(i.price * i.qty).toFixed(2)}</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removePurchaseItem(${i.id})"><i class="bi bi-trash"></i></button></td>
        </tr>
    `).join('');
    const total = purchaseCart.reduce((s,i) => s + i.price * i.qty, 0);
    document.getElementById('purTotal').textContent = '৳ ' + total.toFixed(2);
}

function submitPurchase() {
    if (!purchaseCart.length) { alert('Add at least one medicine!'); return; }
    document.getElementById('purchaseCartData').value = JSON.stringify(purchaseCart);
    document.getElementById('purchaseForm').submit();
}

function viewPurchaseItems(id) {
    document.getElementById('viewModalBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success"></div></div>';
    new bootstrap.Modal(document.getElementById('viewModal')).show();
    fetch(`../api/purchase_items.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            const esc = s => String(s).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
            let html = '<div class="table-responsive"><table class="table table-bordered mb-0"><thead class="table-light"><tr><th>Medicine</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead><tbody>';
            data.forEach(i => {
                html += `<tr><td>${esc(i.med_name)}</td><td>${esc(i.quantity)}</td><td>৳${parseFloat(i.unit_price).toFixed(2)}</td><td class="fw-600">৳${parseFloat(i.total_price).toFixed(2)}</td></tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('viewModalBody').innerHTML = data.length ? html : '<p class="text-muted text-center py-3">No items found</p>';
        });
}
</script>

<?php include '../includes/footer.php'; ?>
