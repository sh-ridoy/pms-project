<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT s.*, c.name customer_name, c.phone customer_phone, u.full_name staff FROM sales s LEFT JOIN customers c ON s.customer_id=c.id LEFT JOIN users u ON s.user_id=u.id WHERE s.id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
if (!$sale) { echo 'Invoice not found.'; exit; }

$stmt2 = $conn->prepare("SELECT si.*, m.name med_name, m.unit FROM sale_items si JOIN medicines m ON si.medicine_id=m.id WHERE si.sale_id=?");
$stmt2->bind_param('i', $id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="page-header no-print">
    <div>
        <div class="page-title"><i class="bi bi-receipt me-2 text-success"></i>Invoice</div>
        <div class="page-subtitle"><?= htmlspecialchars($sale['invoice_no']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn-primary-custom" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <a href="sales.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="invoice-box">
    <div class="invoice-header">
        <div class="row align-items-center">
            <div class="col-7">
                <div class="invoice-title">PharmaCare</div>
                <div style="font-size:13px; color:#555;">Pharmacy Management System</div>
                <div style="font-size:12px; color:#777; margin-top:4px;">
                    <i class="bi bi-telephone me-1"></i>+880-XXXX-XXXX &nbsp;
                    <i class="bi bi-envelope me-1"></i>info@pharmacare.com
                </div>
            </div>
            <div class="col-5 text-end">
                <div style="font-size:22px; font-weight:800; color:#1a6b3c;">INVOICE</div>
                <div style="font-family:'Space Mono',monospace; font-size:14px; color:#333;"><?= htmlspecialchars($sale['invoice_no']) ?></div>
                <div style="font-size:12px; color:#777;"><?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?></div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <div style="font-size:11px; font-weight:700; color:#999; letter-spacing:1px; text-transform:uppercase; margin-bottom:6px;">Bill To</div>
            <div style="font-weight:600;"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
            <?php if ($sale['customer_phone']): ?>
            <div style="font-size:13px; color:#555;"><?= htmlspecialchars($sale['customer_phone']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-6 text-end">
            <div style="font-size:11px; font-weight:700; color:#999; letter-spacing:1px; text-transform:uppercase; margin-bottom:6px;">Served By</div>
            <div style="font-weight:600;"><?= htmlspecialchars($sale['staff'] ?? '') ?></div>
            <div style="font-size:13px; color:#555;">Payment: <?= ucfirst($sale['payment_method']) ?></div>
        </div>
    </div>

    <table class="table table-bordered mb-0" style="font-size:13px;">
        <thead style="background:#f0f4f8;">
            <tr>
                <th style="width:40px;">#</th>
                <th>Medicine</th>
                <th class="text-center" style="width:70px;">Qty</th>
                <th class="text-end" style="width:100px;">Unit Price</th>
                <th class="text-end" style="width:110px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $item): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($item['med_name']) ?> <small class="text-muted">(<?= htmlspecialchars($item['unit']) ?>)</small></td>
                <td class="text-center"><?= $item['quantity'] ?></td>
                <td class="text-end">৳<?= number_format($item['unit_price'], 2) ?></td>
                <td class="text-end fw-600">৳<?= number_format($item['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row mt-3">
        <div class="col-6">
            <?php if ($sale['notes']): ?>
            <div style="font-size:12px; color:#777;"><strong>Notes:</strong> <?= htmlspecialchars($sale['notes']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-6">
            <table class="table table-sm mb-0" style="font-size:13px;">
                <tr><td class="text-muted">Subtotal</td><td class="text-end">৳<?= number_format($sale['subtotal'], 2) ?></td></tr>
                <?php if ($sale['discount'] > 0): ?>
                <tr><td class="text-danger">Discount</td><td class="text-end text-danger">-৳<?= number_format($sale['discount'], 2) ?></td></tr>
                <?php endif; ?>
                <?php if ($sale['tax'] > 0): ?>
                <tr><td class="text-muted">Tax</td><td class="text-end">৳<?= number_format($sale['tax'], 2) ?></td></tr>
                <?php endif; ?>
                <tr style="border-top:2px solid #1a6b3c;">
                    <td class="fw-800" style="font-size:15px;">TOTAL</td>
                    <td class="text-end fw-800" style="font-size:15px; color:#1a6b3c;">৳<?= number_format($sale['total'], 2) ?></td>
                </tr>
                <tr class="text-muted"><td>Paid</td><td class="text-end">৳<?= number_format($sale['paid_amount'], 2) ?></td></tr>
                <tr class="text-muted"><td>Change</td><td class="text-end">৳<?= number_format($sale['change_amount'], 2) ?></td></tr>
            </table>
        </div>
    </div>

    <div style="text-align:center; margin-top:28px; padding-top:16px; border-top:1px dashed #ddd; font-size:12px; color:#777;">
        <div style="font-weight:600; color:#1a6b3c; margin-bottom:4px;">Thank you for your purchase!</div>
        <div>This is a computer-generated invoice. No signature required.</div>
        <div style="margin-top:8px; font-size:11px;">Developed by <strong>Md Shamim Hossain Ridoy</strong> | Developer Portfolio &copy; <?= date('Y') ?></div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
