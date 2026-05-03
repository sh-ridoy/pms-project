/* PharmaCare PMS - JS | Md Shamim Hossain Ridoy | Developer Portfolio */

// Live Clock
function updateClock() {
    const el = document.getElementById('liveClock');
    if (!el) return;
    const now = new Date();
    el.textContent = now.toLocaleTimeString('en-US', { hour12: true });
}
setInterval(updateClock, 1000);
updateClock();

// Sidebar Toggle
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const toggleBtn = document.getElementById('toggleSidebar');

if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('open');
        } else {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    });
}

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.custom-alert').forEach(a => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(a);
        bsAlert.close();
    });
}, 4000);

// Confirm delete
function confirmDelete(url, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        window.location.href = url;
    }
}

// POS System
let cart = [];

function searchMedicine(query) {
    if (query.length < 2) {
        document.getElementById('searchResults').innerHTML = '';
        return;
    }
    fetch(`../api/search_medicine.php?q=${encodeURIComponent(query)}`)
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById('searchResults');
            if (!data.length) {
                box.innerHTML = '<div class="med-result-item"><div class="med-name text-muted">No results found</div></div>';
                return;
            }
            box.innerHTML = data.map(m => `
                <div class="med-result-item" onclick="addToCart(${m.id},'${escapeHtml(m.name)}',${m.sale_price},${m.stock_qty})">
                    <div class="med-name">${escapeHtml(m.name)} <small class="text-muted">(${escapeHtml(m.generic_name || '')})</small></div>
                    <div class="med-detail">
                        <span class="me-3">Price: ৳${parseFloat(m.sale_price).toFixed(2)}</span>
                        <span>Stock: ${m.stock_qty}</span>
                    </div>
                </div>
            `).join('');
        });
}

function escapeHtml(str) {
    return str.replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
}

function addToCart(id, name, price, stock) {
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('medicineSearch').value = '';

    const existing = cart.find(i => i.id === id);
    if (existing) {
        if (existing.qty >= stock) { alert('Insufficient stock!'); return; }
        existing.qty++;
    } else {
        if (stock < 1) { alert('Out of stock!'); return; }
        cart.push({ id, name, price: parseFloat(price), qty: 1, stock });
    }
    renderCart();
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) { removeFromCart(id); return; }
    if (item.qty > item.stock) { item.qty = item.stock; alert('Cannot exceed stock!'); }
    renderCart();
}

function renderCart() {
    const tbody = document.getElementById('cartBody');
    if (!tbody) return;

    if (!cart.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-cart-x fs-2 d-block mb-2"></i>Cart is empty</td></tr>';
        document.getElementById('subtotal').textContent = '৳ 0.00';
        document.getElementById('totalAmount').textContent = '৳ 0.00';
        return;
    }

    tbody.innerHTML = cart.map(item => `
        <tr class="pos-item-row">
            <td><div class="fw-600">${escapeHtml(item.name)}</div><small class="text-muted">৳${item.price.toFixed(2)} each</small></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <button class="qty-btn" onclick="updateQty(${item.id},-1)">−</button>
                    <span class="fw-700">${item.qty}</span>
                    <button class="qty-btn" onclick="updateQty(${item.id},1)">+</button>
                </div>
            </td>
            <td class="fw-600">৳${(item.price * item.qty).toFixed(2)}</td>
            <td><button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${item.id})"><i class="bi bi-trash"></i></button></td>
        </tr>
    `).join('');

    calculateTotals();
}

function calculateTotals() {
    const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
    const discount = parseFloat(document.getElementById('discount')?.value || 0);
    const taxPct = parseFloat(document.getElementById('taxPct')?.value || 0);
    const tax = (subtotal - discount) * taxPct / 100;
    const total = subtotal - discount + tax;

    if (document.getElementById('subtotal')) document.getElementById('subtotal').textContent = '৳ ' + subtotal.toFixed(2);
    if (document.getElementById('taxAmt')) document.getElementById('taxAmt').textContent = '৳ ' + tax.toFixed(2);
    if (document.getElementById('totalAmount')) document.getElementById('totalAmount').textContent = '৳ ' + total.toFixed(2);

    calculateChange();
}

function calculateChange() {
    const totalEl = document.getElementById('totalAmount');
    const paid = parseFloat(document.getElementById('paidAmount')?.value || 0);
    const total = parseFloat(totalEl?.textContent.replace('৳ ', '') || 0);
    const change = paid - total;
    const changeEl = document.getElementById('changeAmount');
    if (changeEl) {
        changeEl.textContent = '৳ ' + Math.max(0, change).toFixed(2);
        changeEl.style.color = change < 0 ? '#e63946' : '#16a34a';
    }
}

function submitSale() {
    if (!cart.length) { alert('Cart is empty!'); return; }
    const paid = parseFloat(document.getElementById('paidAmount')?.value || 0);
    const total = parseFloat(document.getElementById('totalAmount')?.textContent.replace('৳ ', '') || 0);
    if (paid < total) { alert('Paid amount is less than total!'); return; }

    document.getElementById('cartDataInput').value = JSON.stringify(cart);
    document.getElementById('saleForm').submit();
}

// Confirm before delete
document.addEventListener('click', e => {
    if (e.target.closest('[data-confirm]')) {
        const msg = e.target.closest('[data-confirm]').dataset.confirm;
        if (!confirm(msg || 'Are you sure?')) e.preventDefault();
    }
});
