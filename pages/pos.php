<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

// Process sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    $cartData   = json_decode($_POST['cart_data'], true);
    $customerId = intval($_POST['customer_id'] ?? 0) ?: null;
    $discount   = floatval($_POST['discount'] ?? 0);
    $taxPct     = floatval($_POST['tax_pct'] ?? 0);
    $paidAmt    = floatval($_POST['paid_amount'] ?? 0);
    $payMethod  = $_POST['payment_method'] ?? 'cash';
    $notes      = trim($_POST['notes'] ?? '');

    if (!empty($cartData)) {
        $subtotal = 0;
        foreach ($cartData as $item) $subtotal += $item['price'] * $item['qty'];
        $tax   = ($subtotal - $discount) * $taxPct / 100;
        $total = $subtotal - $discount + $tax;
        $change= max(0, $paidAmt - $total);
        $invoice = generateInvoice('INV');

        $stmt = $conn->prepare("INSERT INTO sales (invoice_no,customer_id,user_id,subtotal,discount,tax,total,paid_amount,change_amount,payment_method,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $userId = $_SESSION['user_id'];
        $stmt->bind_param('siiddddddss', $invoice, $customerId, $userId, $subtotal, $discount, $tax, $total, $paidAmt, $change, $payMethod, $notes);
        $stmt->execute();
        $saleId = $conn->insert_id;

        $si = $conn->prepare("INSERT INTO sale_items (sale_id,medicine_id,quantity,unit_price,total_price) VALUES (?,?,?,?,?)");
        $upd= $conn->prepare("UPDATE medicines SET stock_qty = stock_qty - ? WHERE id = ?");
        foreach ($cartData as $item) {
            $tp = $item['price'] * $item['qty'];
            $si->bind_param('iiidd', $saleId, $item['id'], $item['qty'], $item['price'], $tp);
            $si->execute();
            $upd->bind_param('ii', $item['qty'], $item['id']);
            $upd->execute();
        }

        flashMessage('success', "Sale recorded! Invoice: $invoice");
        header("Location: invoice.php?id=$saleId");
        exit;
    }
}

$customers = $conn->query("SELECT id, name, phone FROM customers ORDER BY name")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="bi bi-cart3 me-2 text-success"></i>Point of Sale</div>
        <div class="page-subtitle">Create new sale transaction</div>
    </div>
</div>

<div class="row g-3">
    <!-- Medicine Search & Cart -->
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-search me-2"></i>Search Medicine</div>
            <div class="card-body">
                <div class="position-relative">
                    <input type="text" id="medicineSearch" class="form-control form-control-lg"
                        placeholder="Type medicine name to search..." autocomplete="off"
                        oninput="searchMedicine(this.value)" style="font-size:15px;">
                    <div id="searchResults" class="medicine-search-results"></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-bag me-2"></i>Cart Items</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Medicine</th><th>Qty</th><th>Total</th><th></th></tr></thead>
                    <tbody id="cartBody">
                        <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-cart-x fs-2 d-block mb-2"></i>Cart is empty</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Summary -->
    <div class="col-lg-5">
        <div class="card sticky-top" style="top:70px;">
            <div class="card-header"><i class="bi bi-receipt-cutoff me-2"></i>Order Summary</div>
            <div class="card-body">
                <form id="saleForm" method="POST">
                    <input type="hidden" name="cart_data" id="cartDataInput">

                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-select">
                            <option value="">Walk-in Customer</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> - <?= htmlspecialchars($c['phone'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Discount (৳)</label>
                            <input type="number" name="discount" id="discount" class="form-control" value="0" min="0" oninput="calculateTotals()">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tax (%)</label>
                            <input type="number" name="tax_pct" id="taxPct" class="form-control" value="0" min="0" max="100" oninput="calculateTotals()">
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 mb-3" style="background:#f8fafc;">
                        <div class="d-flex justify-content-between cart-total-row mb-2">
                            <span>Subtotal</span><span id="subtotal">৳ 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between cart-total-row mb-2 text-muted">
                            <span>Tax</span><span id="taxAmt">৳ 0.00</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-700">TOTAL</span>
                            <span class="cart-grand-total" id="totalAmount">৳ 0.00</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach (['cash','card','mobile'] as $pm): ?>
                            <label class="flex-fill">
                                <input type="radio" name="payment_method" value="<?= $pm ?>" <?= $pm === 'cash' ? 'checked' : '' ?> class="d-none" onchange="this.closest('.d-flex').querySelectorAll('label').forEach(l=>l.classList.remove('active-pay'));this.parentElement.classList.add('active-pay')">
                                <div class="border rounded-3 text-center py-2 px-3 pay-opt <?= $pm === 'cash' ? 'active-pay' : '' ?>" style="cursor:pointer;font-size:13px;font-weight:600;">
                                    <i class="bi bi-<?= $pm === 'cash' ? 'cash' : ($pm === 'card' ? 'credit-card' : 'phone') ?> d-block mb-1 fs-5"></i>
                                    <?= ucfirst($pm) ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Paid Amount (৳)</label>
                            <input type="number" name="paid_amount" id="paidAmount" class="form-control" value="0" min="0" step="0.01" oninput="calculateChange()">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Change</label>
                            <div class="form-control text-center fw-700" id="changeAmount" style="background:#f0f4f8;">৳ 0.00</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>

                    <button type="button" class="btn-primary-custom w-100 justify-content-center py-3" onclick="submitSale()" style="font-size:16px;">
                        <i class="bi bi-check-circle-fill"></i> Complete Sale
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.active-pay { background: var(--primary-light); border-color: var(--primary) !important; color: var(--primary); }
</style>

<?php include '../includes/footer.php'; ?>
