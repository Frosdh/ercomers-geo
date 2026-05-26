<?php
$pageTitle = 'Finalizar compra';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$db     = getDB();
$cartId = getOrCreateCart();

$items = $db->prepare(
    "SELECT ci.*, p.name, p.price AS current_price,
            (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
     FROM cart_items ci JOIN products p ON p.id = ci.product_id
     WHERE ci.cart_id = ?"
);
$items->execute([$cartId]);
$items = $items->fetchAll();

if (empty($items)) {
    setFlash('warning', 'Tu carrito está vacío.');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$addresses     = $db->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC');
$addresses->execute([$_SESSION['user_id']]);
$addresses     = $addresses->fetchAll();
$payMethods    = $db->query("SELECT * FROM payment_methods_config WHERE is_active=1 ORDER BY sort_order")->fetchAll();
$shippingRates = $db->query("SELECT sr.*,sz.name AS zone_name FROM shipping_rates sr JOIN shipping_zones sz ON sz.id=sr.zone_id WHERE sr.is_active=1 ORDER BY sr.base_cost")->fetchAll();
$bankAccounts  = $db->query("SELECT * FROM bank_accounts WHERE is_active=1")->fetchAll();

$subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['quantity'], $items));
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payMethodId  = (int)($_POST['payment_method'] ?? 0);
    $shippingRate = (int)($_POST['shipping_rate']  ?? 0);
    $notes        = sanitize($_POST['notes'] ?? '');
    $addrId       = (int)($_POST['address_id'] ?? 0);

    if (!$addrId) {
        $fullName = sanitize($_POST['full_name']     ?? '');
        $phone    = sanitize($_POST['addr_phone']    ?? '');
        $line1    = sanitize($_POST['address_line1'] ?? '');
        $city     = sanitize($_POST['city']          ?? '');
        $state    = sanitize($_POST['state']         ?? '');
        $postal   = sanitize($_POST['postal_code']   ?? '');
        $country  = sanitize($_POST['country']       ?? 'Ecuador');
        $saveAddr = !empty($_POST['save_address']);

        if (!$fullName) $errors[] = 'El nombre completo de envío es requerido.';
        if (!$line1)    $errors[] = 'La dirección es requerida.';
        if (!$city)     $errors[] = 'La ciudad es requerida.';

        if (empty($errors)) {
            $db->prepare('INSERT INTO addresses (user_id,full_name,phone,address_line1,city,state,postal_code,country,is_default) VALUES (?,?,?,?,?,?,?,?,?)')
               ->execute([$_SESSION['user_id'],$fullName,$phone,$line1,$city,$state,$postal,$country,$saveAddr?1:0]);
            $addrId = (int)$db->lastInsertId();
        }
    }

    if (!$payMethodId) $errors[] = 'Selecciona un método de pago.';

    if (empty($errors)) {
        $rateStmt = $db->prepare('SELECT base_cost FROM shipping_rates WHERE id=?');
        $rateStmt->execute([$shippingRate]);
        $shippingCost = (float)($rateStmt->fetchColumn() ?: 3.50);
        $finalTotal   = $subtotal + $shippingCost;
        $orderNum     = 'GEO-' . date('Y') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);

        $db->prepare('INSERT INTO orders (order_number,user_id,address_id,status,subtotal,shipping_cost,total,notes,ip_address) VALUES (?,?,?,?,?,?,?,?,?)')
           ->execute([$orderNum,$_SESSION['user_id'],$addrId?:null,'pending',$subtotal,$shippingCost,$finalTotal,$notes,$_SERVER['REMOTE_ADDR']??null]);
        $orderId = (int)$db->lastInsertId();

        foreach ($items as $item) {
            $db->prepare('INSERT INTO order_items (order_id,product_id,product_name,product_sku,quantity,unit_price,total_price) VALUES (?,?,?,?,?,?,?)')
               ->execute([$orderId,$item['product_id'],$item['name'],$item['product_sku']??'',$item['quantity'],$item['unit_price'],$item['unit_price']*$item['quantity']]);
            $db->prepare('UPDATE inventory SET quantity=quantity-? WHERE product_id=? AND variant_id IS NULL')
               ->execute([$item['quantity'],$item['product_id']]);
        }

        $pmStmt = $db->prepare('SELECT type FROM payment_methods_config WHERE id=?');
        $pmStmt->execute([$payMethodId]);
        $pmType = $pmStmt->fetchColumn();

        $db->prepare('INSERT INTO payments (order_id,payment_method_id,amount,currency,status,payment_type) VALUES (?,?,?,?,?,?)')
           ->execute([$orderId,$payMethodId,$finalTotal,'USD','pending',$pmType]);

        $db->prepare('INSERT INTO order_status_history (order_id,status,comment,created_by) VALUES (?,?,?,?)')
           ->execute([$orderId,'pending','Pedido creado',$_SESSION['user_id']]);

        $db->prepare('DELETE FROM cart_items WHERE cart_id=?')->execute([$cartId]);

        $db->prepare('INSERT INTO notifications (user_id,type,title,message) VALUES (?,?,?,?)')
           ->execute([$_SESSION['user_id'],'order_created','¡Pedido recibido!',"Tu pedido $orderNum está pendiente de pago."]);

        if ($pmType === 'bank_transfer') {
            header('Location: ' . BASE_URL . '/upload_proof.php?order=' . $orderId);
        } else {
            setFlash('success', "¡Pedido $orderNum creado!");
            header('Location: ' . BASE_URL . '/orders.php?order=' . $orderId);
        }
        exit;
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<style>
.pay-card,.ship-card{cursor:pointer;transition:all .2s;border:2px solid #dee2e6!important}
.pay-card:hover,.ship-card:hover{border-color:#1a73e8!important}
.pay-card.selected,.ship-card.selected{border-color:#1a73e8!important;background:#f0f6ff}
.bank-card{background:linear-gradient(135deg,#1e3a5f,#2d5a8e);color:#fff;border-radius:14px}
.bank-num{font-size:1.1rem;letter-spacing:2px;font-weight:600}
.step-dot{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem}
</style>

<!-- Steps -->
<div class="d-flex align-items-center gap-2 mb-4">
  <div class="step-dot bg-success text-white"><i class="bi bi-check-lg small"></i></div>
  <span class="fw-semibold text-success small">Carrito</span>
  <div style="height:2px;width:40px;background:#dee2e6"></div>
  <div class="step-dot bg-primary text-white">2</div>
  <span class="fw-bold small">Pago</span>
  <div style="height:2px;width:40px;background:#dee2e6"></div>
  <div class="step-dot border text-muted">3</div>
  <span class="text-muted small">Confirmación</span>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger border-0 rounded-3">
  <?php foreach($errors as $e): ?><div><i class="bi bi-exclamation-circle me-2"></i><?= e($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" id="checkoutForm">
<div class="row g-4">
<div class="col-lg-7">

  <!-- DIRECCIÓN -->
  <div class="card p-4 mb-3 border-0 shadow-sm">
    <h5 class="fw-bold mb-3"><span class="badge bg-primary me-2">1</span>Dirección de envío</h5>
    <?php if ($addresses): foreach ($addresses as $addr): ?>
      <label class="border rounded-3 p-3 mb-2 d-flex gap-3 ship-card <?= $addr['is_default']?'selected':'' ?>">
        <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="address_id"
               value="<?= $addr['id'] ?>" <?= $addr['is_default']?'checked':'' ?>>
        <div class="flex-grow-1">
          <p class="mb-0 fw-semibold"><?= e($addr['full_name']) ?></p>
          <p class="mb-0 small text-muted"><?= e($addr['address_line1']) ?>, <?= e($addr['city']) ?></p>
        </div>
        <?php if($addr['is_default']): ?><span class="badge bg-success align-self-start">Principal</span><?php endif; ?>
      </label>
    <?php endforeach; endif; ?>
    <label class="border rounded-3 p-3 mb-2 d-flex gap-3 ship-card" id="newAddrLabel">
      <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="address_id" value="0" id="newAddrRadio" <?= !$addresses?'checked':'' ?>>
      <span class="fw-semibold text-primary"><i class="bi bi-plus-circle me-2"></i>Nueva dirección</span>
    </label>
    <div id="newAddrForm" class="pt-3 mt-1" <?= $addresses?'style="display:none"':'' ?>>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label small fw-semibold">Nombre *</label>
          <input type="text" class="form-control" name="full_name" placeholder="Nombre completo"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Teléfono</label>
          <input type="tel" class="form-control" name="addr_phone" placeholder="+593 99 000 0000"></div>
        <div class="col-12"><label class="form-label small fw-semibold">Dirección *</label>
          <input type="text" class="form-control" name="address_line1" placeholder="Calle, número, barrio"></div>
        <div class="col-5"><label class="form-label small fw-semibold">Ciudad *</label>
          <input type="text" class="form-control" name="city" placeholder="Quito"></div>
        <div class="col-4"><label class="form-label small fw-semibold">Provincia</label>
          <input type="text" class="form-control" name="state" placeholder="Pichincha"></div>
        <div class="col-3"><label class="form-label small fw-semibold">Código postal</label>
          <input type="text" class="form-control" name="postal_code"></div>
        <div class="col-12">
          <div class="form-check"><input class="form-check-input" type="checkbox" name="save_address" id="saveAddr">
          <label class="form-check-label small" for="saveAddr">Guardar dirección</label></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ENVÍO -->
  <div class="card p-4 mb-3 border-0 shadow-sm">
    <h5 class="fw-bold mb-3"><span class="badge bg-primary me-2">2</span>Método de envío</h5>
    <?php foreach ($shippingRates as $i => $rate): ?>
    <label class="border rounded-3 p-3 mb-2 d-flex align-items-center gap-3 ship-card <?= $i==0?'selected':'' ?>">
      <input class="form-check-input flex-shrink-0" type="radio" name="shipping_rate"
             value="<?= $rate['id'] ?>" data-cost="<?= $rate['base_cost'] ?>" <?= $i==0?'checked':'' ?>>
      <div class="flex-grow-1">
        <p class="mb-0 fw-semibold"><?= e($rate['name']) ?></p>
        <small class="text-muted"><?= $rate['estimated_days_min'] ?>–<?= $rate['estimated_days_max'] ?> días · <?= e($rate['zone_name']) ?></small>
      </div>
      <span class="fw-bold <?= $rate['base_cost']==0?'text-success':'' ?>">
        <?= $rate['base_cost']==0?'<i class="bi bi-gift-fill text-success me-1"></i>Gratis':money($rate['base_cost']) ?>
      </span>
    </label>
    <?php endforeach; ?>
  </div>

  <!-- PAGO -->
  <div class="card p-4 mb-3 border-0 shadow-sm">
    <h5 class="fw-bold mb-3"><span class="badge bg-primary me-2">3</span>Método de pago</h5>
    <?php
    $pmIcons  = ['credit_card'=>'bi-credit-card-2-front text-primary','bank_transfer'=>'bi-bank2 text-success','paypal'=>'bi-paypal text-primary','cash_on_delivery'=>'bi-cash-coin text-warning'];
    foreach ($payMethods as $i => $pm): ?>
    <label class="border rounded-3 p-3 mb-2 d-flex align-items-center gap-3 pay-card <?= $i==0?'selected':'' ?>"
           data-type="<?= $pm['type'] ?>">
      <input class="form-check-input flex-shrink-0" type="radio" name="payment_method"
             value="<?= $pm['id'] ?>" data-type="<?= $pm['type'] ?>" <?= $i==0?'checked':'' ?>>
      <div>
        <p class="mb-0 fw-semibold">
          <i class="bi <?= $pmIcons[$pm['type']] ?? 'bi-wallet2' ?> fs-5 me-2"></i><?= e($pm['name']) ?>
        </p>
        <?php if($pm['instructions']): ?>
          <small class="text-muted"><?= e($pm['instructions']) ?></small>
        <?php endif; ?>
      </div>
    </label>
    <?php endforeach; ?>

    <!-- Cuentas bancarias -->
    <?php if ($bankAccounts): ?>
    <div id="bankPanel" class="mt-3 p-3 rounded-3 bg-light" style="display:none">
      <p class="fw-semibold mb-3 text-success"><i class="bi bi-info-circle-fill me-2"></i>Transfiere a una de estas cuentas y luego sube tu comprobante:</p>
      <div class="row g-3">
        <?php foreach ($bankAccounts as $ba): ?>
        <div class="col-md-6">
          <div class="bank-card p-3">
            <div class="d-flex justify-content-between mb-2">
              <span class="badge bg-white bg-opacity-25 text-white small"><?= e($ba['account_type']) ?></span>
              <button type="button" class="btn btn-sm btn-outline-light btn-copy py-0" data-copy="<?= e($ba['account_number']) ?>">
                <i class="bi bi-copy"></i> Copiar
              </button>
            </div>
            <p class="fw-bold mb-1"><?= e($ba['bank_name']) ?></p>
            <p class="bank-num mb-1"><?= e($ba['account_number']) ?></p>
            <p class="small mb-0 opacity-75"><?= e($ba['account_name']) ?></p>
            <?php if($ba['identification']): ?><p class="small mb-0 opacity-75">RUC: <?= e($ba['identification']) ?></p><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="alert alert-warning border-0 rounded-3 mt-3 small mb-0">
        <i class="bi bi-clock-fill me-2"></i>Tienes <strong>24 horas</strong> para transferir y subir el comprobante.
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- NOTAS -->
  <div class="card p-4 border-0 shadow-sm">
    <h6 class="fw-semibold mb-2 text-muted"><i class="bi bi-chat-left-text me-2"></i>Notas <small class="fw-normal">(opcional)</small></h6>
    <textarea class="form-control" name="notes" rows="2" placeholder="Instrucciones de entrega, horario, etc."></textarea>
  </div>

</div>

<!-- RESUMEN -->
<div class="col-lg-5">
  <div class="card p-4 border-0 shadow-sm sticky-top" style="top:80px">
    <h5 class="fw-bold mb-3">Tu pedido</h5>
    <div style="max-height:240px;overflow-y:auto;margin-right:-4px;padding-right:4px">
      <?php foreach ($items as $item): ?>
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="position-relative flex-shrink-0">
          <img src="<?= $item['image']?e($item['image']):BASE_URL.'/assets/img/no-image.svg' ?>"
               style="width:52px;height:52px;object-fit:cover;border-radius:10px;border:2px solid #f0f0f0">
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark"
                style="font-size:.6rem"><?= $item['quantity'] ?></span>
        </div>
        <div class="flex-grow-1 min-w-0">
          <p class="mb-0 small fw-semibold text-truncate"><?= e($item['name']) ?></p>
          <small class="text-muted"><?= money($item['unit_price']) ?> × <?= $item['quantity'] ?></small>
        </div>
        <span class="fw-bold small flex-shrink-0"><?= money($item['unit_price']*$item['quantity']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <hr>
    <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Subtotal</span><span><?= money($subtotal) ?></span></div>
    <div class="d-flex justify-content-between small mb-1">
      <span class="text-muted">Envío</span>
      <span id="shipCostDisplay" class="text-muted">—</span>
    </div>
    <hr>
    <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
      <span>Total</span>
      <span class="price-tag" id="totalDisplay">—</span>
    </div>
    <button type="submit" class="btn btn-accent btn-lg w-100 fw-semibold rounded-3 py-3">
      <i class="bi bi-lock-fill me-2"></i>Confirmar pedido
    </button>
    <p class="text-center text-muted small mt-2 mb-0">
      <i class="bi bi-shield-check text-success me-1"></i>Compra segura y protegida
    </p>
  </div>
</div>
</div>
</form>

<script>
const subtotal = <?= $subtotal ?>;

// Shipping
document.querySelectorAll('input[name="shipping_rate"]').forEach(r => {
  r.addEventListener('change', () => updateTotal());
  r.closest('label')?.addEventListener('click', () => {
    document.querySelectorAll('.ship-card').forEach(c => c.classList.remove('selected'));
    r.closest('label').classList.add('selected');
    r.checked = true; updateTotal();
  });
});

// Payment
document.querySelectorAll('input[name="payment_method"]').forEach(r => {
  r.closest('label')?.addEventListener('click', () => {
    document.querySelectorAll('.pay-card').forEach(c => c.classList.remove('selected'));
    r.closest('label').classList.add('selected');
    r.checked = true;
    const panel = document.getElementById('bankPanel');
    if (panel) panel.style.display = r.dataset.type === 'bank_transfer' ? 'block' : 'none';
  });
});

// Address
document.querySelectorAll('input[name="address_id"]').forEach(r => {
  r.addEventListener('change', () => {
    document.getElementById('newAddrForm').style.display = r.value === '0' ? 'block' : 'none';
  });
});

function updateTotal() {
  const sel = document.querySelector('input[name="shipping_rate"]:checked');
  if (!sel) return;
  const cost = parseFloat(sel.dataset.cost);
  document.getElementById('shipCostDisplay').textContent = cost === 0 ? 'Gratis' : '$' + cost.toFixed(2);
  document.getElementById('totalDisplay').textContent = '$' + (subtotal + cost).toFixed(2);
}

// Copiar
document.querySelectorAll('.btn-copy').forEach(btn => {
  btn.addEventListener('click', e => {
    e.preventDefault(); e.stopPropagation();
    navigator.clipboard.writeText(btn.dataset.copy).then(() => {
      const orig = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check-lg"></i> ¡Copiado!';
      setTimeout(() => btn.innerHTML = orig, 1800);
    });
  });
});

updateTotal();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
