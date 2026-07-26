let cart = {}; // id -> qty

const byId = (id) => document.getElementById(id);
const money = (n) => CURRENCY + ' ' + n.toFixed(2);
const menuItem = (id) => MENU.find((m) => m.id === id);

function addToCart(id) {
  cart[id] = (cart[id] || 0) + 1;
  renderCart();
}

function changeQty(id, delta) {
  cart[id] = (cart[id] || 0) + delta;
  if (cart[id] <= 0) delete cart[id];
  renderCart();
}

function removeItem(id) {
  delete cart[id];
  renderCart();
}

function clearCart() {
  cart = {};
  byId('discount').value = 0;
  renderCart();
}

function totals() {
  let subtotal = 0;
  for (const [id, qty] of Object.entries(cart)) {
    subtotal += menuItem(Number(id)).price * qty;
  }
  const service = SERVICE_PCT > 0 && byId('serviceChk').checked ? subtotal * SERVICE_PCT / 100 : 0;
  let discount = parseFloat(byId('discount').value) || 0;
  discount = Math.min(Math.max(discount, 0), subtotal + service);
  const total = subtotal + service - discount;
  return { subtotal, service, discount, total };
}

function renderCart() {
  const rows = byId('cartRows');
  const ids = Object.keys(cart);
  const dineIn = byId('orderType').value === 'dine_in';
  byId('tableNo').style.display = dineIn ? '' : 'none';

  if (ids.length === 0) {
    rows.innerHTML = '<div class="empty-state"><i class="bi bi-basket"></i>No items yet.<br>Tap menu items to add.</div>';
  } else {
    rows.innerHTML = ids.map((id) => {
      const it = menuItem(Number(id));
      const qty = cart[id];
      return `<div class="cart-row d-flex align-items-center gap-2">
        <div class="flex-grow-1">
          <div class="fw-semibold small">${it.name}</div>
          <div class="text-muted" style="font-size:.75rem">${money(it.price)} each</div>
        </div>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" onclick="changeQty(${id},-1)">&minus;</button>
          <span class="btn btn-light disabled px-2">${qty}</span>
          <button class="btn btn-outline-secondary" onclick="changeQty(${id},1)">+</button>
        </div>
        <div class="text-end fw-semibold small" style="width:80px">${money(it.price * qty)}</div>
        <button class="btn btn-sm btn-link text-danger p-0" onclick="removeItem(${id})"><i class="bi bi-x-circle"></i></button>
      </div>`;
    }).join('');
  }

  const t = totals();
  byId('tSubtotal').textContent = money(t.subtotal);
  byId('tService').textContent = money(t.service);
  byId('tTotal').textContent = money(t.total);
  byId('payBtn').disabled = ids.length === 0;
}

// ---- Menu filtering ----
document.querySelectorAll('.cat-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.cat-btn').forEach((b) => {
      b.classList.remove('active', 'btn-brand');
      b.classList.add('btn-outline-brand');
    });
    btn.classList.add('active', 'btn-brand');
    btn.classList.remove('btn-outline-brand');
    filterItems();
  });
});
byId('itemSearch').addEventListener('input', filterItems);

function filterItems() {
  const cat = document.querySelector('.cat-btn.active').dataset.cat;
  const q = byId('itemSearch').value.trim().toLowerCase();
  document.querySelectorAll('.item-cell').forEach((cell) => {
    const okCat = cat === 'all' || cell.dataset.cat === cat;
    const okName = !q || cell.dataset.name.includes(q);
    cell.style.display = okCat && okName ? '' : 'none';
  });
}

// ---- Payment ----
let payModal;
function openPay() {
  const t = totals();
  byId('payTotal').textContent = money(t.total);
  byId('payError').style.display = 'none';
  payModal = payModal || new bootstrap.Modal(byId('payModal'));
  payModal.show();
}

async function submitOrder() {
  const t = totals();
  const method = byId('pmCash').checked ? 'cash' : 'card';
  const errBox = byId('payError');

  const payload = {
    order_type: byId('orderType').value,
    table_no: byId('tableNo').value.trim(),
    items: Object.entries(cart).map(([id, qty]) => ({ id: Number(id), qty })),
    discount: t.discount,
    service: byId('serviceChk').checked,
    payment_method: method,
  };

  const btn = byId('confirmPayBtn');
  btn.disabled = true;
  const prevHtml = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
  try {
    const res = await fetch('api/save_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Failed to save order.');
    payModal.hide();
    clearCart();
    window.open('receipt.php?id=' + data.id + '&autoprint=1', '_blank');
  } catch (err) {
    errBox.textContent = err.message;
    errBox.style.display = '';
  } finally {
    btn.disabled = false;
    btn.innerHTML = prevHtml;
  }
}

// Keyboard shortcuts: "/" focuses search, F9 opens payment, Esc clears search.
document.addEventListener('keydown', (ev) => {
  const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName);
  if (ev.key === '/' && !typing) {
    ev.preventDefault();
    byId('itemSearch').focus();
  } else if (ev.key === 'F9' && !byId('payBtn').disabled) {
    ev.preventDefault();
    openPay();
  } else if (ev.key === 'Escape' && document.activeElement === byId('itemSearch')) {
    byId('itemSearch').value = '';
    filterItems();
    byId('itemSearch').blur();
  }
});

renderCart();
