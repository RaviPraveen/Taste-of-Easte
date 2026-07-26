<?php
$page_title = 'POS Billing';
$active = 'pos';
require_once __DIR__ . '/header.php';

$categories = db()->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();
$items = db()->query(
    'SELECT mi.id, mi.name, mi.price, mi.image, mi.category_id, c.name AS category
     FROM menu_items mi JOIN categories c ON c.id = mi.category_id
     WHERE mi.available = 1 ORDER BY c.sort_order, mi.name'
)->fetchAll();
$servicePct = (float) setting('service_charge_pct', '10');
$currency = setting('currency', 'Rs.');
?>
<div class="row g-3">
  <!-- Menu side -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3 pos-toolbar">
          <div class="d-flex flex-wrap gap-1">
            <button class="btn btn-sm btn-brand cat-btn active" data-cat="all">All</button>
            <?php foreach ($categories as $cat): ?>
              <button class="btn btn-sm btn-outline-brand cat-btn" data-cat="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></button>
            <?php endforeach; ?>
          </div>
          <div class="ms-auto" style="min-width:230px;">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
              <input type="text" id="itemSearch" class="form-control border-start-0" placeholder="Search items..." aria-label="Search menu items">
              <span class="input-group-text bg-white"><span class="kbd">/</span></span>
            </div>
          </div>
        </div>
        <div class="pos-scroll">
          <div class="row g-2" id="itemGrid">
            <?php foreach ($items as $it): ?>
            <div class="col-6 col-md-4 col-xl-3 item-cell" data-cat="<?= (int) $it['category_id'] ?>" data-name="<?= e(strtolower($it['name'])) ?>">
              <button class="item-card" onclick='addToCart(<?= (int) $it['id'] ?>)'>
                <div class="item-img">
                  <?php if ($it['image']): ?>
                    <img src="<?= e($it['image']) ?>" alt="<?= e($it['name']) ?>" loading="lazy">
                  <?php else: ?>
                    <span><?= food_emoji($it['name'], $it['category']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="item-body">
                  <div class="item-name"><?= e($it['name']) ?></div>
                  <div class="d-flex justify-content-between align-items-end">
                    <span class="item-cat"><?= e($it['category']) ?></span>
                    <span class="item-price"><?= e($currency) ?> <?= number_format((float) $it['price'], 2) ?></span>
                  </div>
                </div>
              </button>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Cart side -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm cart-panel">
      <div class="card-header bg-white fw-bold d-flex align-items-center">
        <i class="bi bi-cart3 me-2 text-brand"></i> Current Order
      </div>
      <div class="card-body pt-2">
        <div class="row g-2 mb-2">
          <div class="col-7">
            <select id="orderType" class="form-select form-select-sm" onchange="renderCart()">
              <option value="takeaway">Takeaway</option>
              <option value="dine_in">Dine-In</option>
            </select>
          </div>
          <div class="col-5">
            <input type="text" id="tableNo" class="form-control form-control-sm" placeholder="Table #" style="display:none;">
          </div>
        </div>
        <div id="cartRows" class="cart-rows mb-2">
          <div class="empty-state" id="cartEmpty"><i class="bi bi-basket"></i>No items yet.<br>Tap menu items to add.</div>
        </div>
        <div class="border-top pt-2 small">
          <div class="d-flex justify-content-between"><span>Subtotal</span><span id="tSubtotal"><?= e($currency) ?> 0.00</span></div>
          <div class="d-flex justify-content-between align-items-center" id="serviceRow" <?= $servicePct <= 0 ? 'style="display:none!important;"' : '' ?>>
            <span>
              <input class="form-check-input me-1" type="checkbox" id="serviceChk" checked onchange="renderCart()">
              Service charge (<?= e($servicePct) ?>%)
            </span>
            <span id="tService"><?= e($currency) ?> 0.00</span>
          </div>
          <div class="d-flex justify-content-between align-items-center my-1">
            <span>Discount</span>
            <input type="number" id="discount" class="form-control form-control-sm text-end" style="width:110px" min="0" step="0.01" value="0" oninput="renderCart()">
          </div>
          <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
            <span class="fw-medium">Total</span><span id="tTotal" class="cart-total"><?= e($currency) ?> 0.00</span>
          </div>
        </div>
        <div class="d-grid gap-2 mt-3">
          <button class="btn btn-accent btn-lg fw-semibold" id="payBtn" onclick="openPay()" disabled>
            <i class="bi bi-credit-card me-1"></i> Charge / Pay <span class="ms-1 kbd" style="background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.3);color:#fff">F9</span>
          </button>
          <button class="btn btn-outline-danger btn-sm" onclick="clearCart()"><i class="bi bi-trash"></i> Clear</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Payment modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cash-coin"></i> Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-3">
          <div class="text-muted small">Amount due</div>
          <div class="display-6 fw-bold text-brand" id="payTotal"><?= e($currency) ?> 0.00</div>
        </div>
        <div class="btn-group w-100 mb-3">
          <input type="radio" class="btn-check" name="payMethod" id="pmCash" value="cash" checked onchange="payMethodChanged()">
          <label class="btn btn-outline-brand" for="pmCash"><i class="bi bi-cash"></i> Cash</label>
          <input type="radio" class="btn-check" name="payMethod" id="pmCard" value="card" onchange="payMethodChanged()">
          <label class="btn btn-outline-brand" for="pmCard"><i class="bi bi-credit-card"></i> Card</label>
        </div>
        <div id="cashFields">
          <label class="form-label small fw-semibold">Cash received</label>
          <input type="number" id="paidAmount" class="form-control form-control-lg text-end mb-2" min="0" step="0.01" oninput="updateChange()">
          <div class="d-flex flex-wrap gap-1 mb-2" id="quickCash"></div>
          <div class="d-flex justify-content-between fw-bold">
            <span>Change</span><span id="changeDue" class="text-success"><?= e($currency) ?> 0.00</span>
          </div>
        </div>
        <div id="payError" class="alert alert-danger py-2 small mt-2" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-brand fw-semibold" id="confirmPayBtn" onclick="submitOrder()">
          <i class="bi bi-check2-circle"></i> Confirm &amp; Print
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const MENU = <?= json_encode(array_map(fn ($i) => [
    'id' => (int) $i['id'],
    'name' => $i['name'],
    'price' => (float) $i['price'],
], $items)) ?>;
const CURRENCY = <?= json_encode($currency) ?>;
const SERVICE_PCT = <?= json_encode($servicePct) ?>;
</script>
<script src="assets/pos.js?v=4"></script>
<?php require_once __DIR__ . '/footer.php'; ?>
