<?php
// ============================================================
//  Geo-Ecomers | Checkout
// ============================================================
$pageTitle = 'Finalizar compra';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$db     = getDB();
$cartId = getOrCreateCart();

// Verificar que el carrito tenga items
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

// Direcciones guardadas
$addresses = $db->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC');
$addresses->execute([$_SESSION['user_id']]);
$addresses = $addresses->fetchAll();

// Métodos de pago activos
$payMethods = $db->query("SELECT * FROM payment_methods_config WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Zonas y tarifas de envío
$shippingRates = $db->query("SELECT sr.*, sz.name AS zone_name FROM shipping_rates sr JOIN shipping_zones sz ON sz.id = sr.zone_id WHERE sr.is_active = 1 ORDER BY sr.base_cost")->fetchAll();

// Cuentas bancarias
$bankAccounts = $db->query("SELECT * FROM bank_accounts WHERE is_active = 1")->fetchAll();

// Totales
$subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['quantity'], $items));
$shipping = 3.50;
$total    = $subtotal + $shipping;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payMethodId  = (int)($_POST['payment_method'] ?? 0);
    $shippingRate = (int)($_POST['shipping_rate']  ?? 0);
    $notes        = sanitize($_POST['notes'] ?? '');

    // Dirección: nueva o existente
    $addrId = (int)($_POST['address_id'] ?? 0);
    if (!$addrId) {
        $fullName  = sanitize($_POST['full_name']     ?? '');
        $phone     = sanitize($_POST['addr_phone']    ?? '');
        $line1     = sanitize($_POST['address_line1'] ?? '');
        $city      = sanitize($_POST['city']          ?? '');
        $state     = sanitize($_POST['state']         ?? '');
        $postal    = sanitize($_POST['postal_code']   ?? '');
        $country   = sanitize($_POST['country']       ?? 'Ecuador');
        $saveAddr  = !empty($_POST['save_address']);

        if (!$fullName) $errors[] = 'El nombre completo de envío es requerido.';
        if (!$line1)    $errors[] = 'La dirección es requerida.';
        if (!$city)     $errors[] = 'La ciudad es requerida.';

        if (empty($errors)) {
            $db->prepare(
                'INSERT INTO addresses (user_id, full_name, phone, address_line1, city, state, postal_code, country, is_default)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$_SESSION['user_id'], $fullName, $phone, $line1, $city, $state, $postal, $country, $saveAddr ? 1 : 0]);
            $addrId = (int)$db->lastInsertId();
        }
    }

    if (!$payMethodId) $errors[] = 'Selecciona un método de pago.';

    if (empty($errors)) {
        // Obtener tarifa de envío
        $rateRow = null;
        if ($shippingRate) {
            $rateStmt = $db->prepare('SELECT base_cost FROM shipping_rates WHERE id = ?');
            $rateStmt->execute([$shippingRate]);
            $rateRow = $rateStmt->fetch();
        }
        $shippingCost = $rateRow ? (float)$rateRow['base_cost'] : 3.50;
        $finalTotal   = $subtotal + $shippingCost;

        // Número de orden
        $orderNum = 'GEO-' . date('Y') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);

        // Crear orden
        $db->prepare(
            'INSERT INTO orders (order_number, user_id, address_id, status, subtotal, shipping_cost, total, notes, ip_address)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $orderNum, $_SESSION['user_id'], $addrId ?: null,
            'pending', $subtotal, $shippingCost, $finalTotal,
            $notes, $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        $orderId = (int)$db->lastInsertId();

        // Items del pedido
        foreach ($items as $item) {
            $db->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price)
                 VALUES (?,?,?,?,?,?)'
            )->execute([
                $orderId, $item['product_id'], $item['name'],
                $item['quantity'], $item['unit_price'],
                $item['unit_price'] * $item['quantity']
            ]);
            // Reducir stock
            $db->prepare('UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND variant_id IS NULL')
               ->execute([$item['quantity'], $item['product_id']]);
        }

        // Registrar pago pendiente
        $db->prepare(
            'INSERT INTO payments (order_id, payment_method_id, amount, currency, status) VALUES (?,?,?,?,?)'
        )->execute([$orderId, $payMethodId, $finalTotal, 'USD', 'pending']);

        // Historial de estado
        $db->prepare(
            'INSERT INTO order_status_history (order_id, status, comment, created_by) VALUES (?,?,?,?)'
        )->execute([$orderId, 'pending', 'Pedido creado', $_SESSION['user_id']]);

        // Vaciar carrito
        $db->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cartId]);

        // Notificación
        $db->prepare(
            'INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)'
        )->execute([
            $_SESSION['user_id'], 'order_created',
            '¡Pedido recibido!',
            "Tu pedido $orderNum ha sido recibido y está pendiente de confirmación."
        ]);

        setFlash('success', "¡Pedido $orderNum creado con éxito! Revisa tu área de pedidos.");
        header('Location: ' . BASE_URL . '/orders.php?order=' . $orderId);
        exit;
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<h2 class="section-title mb-4"><i class="bi bi-bag-check me-2"></i>Finalizar compra</h2>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="POST">
<div class="row g-4">
  <!-- Columna izquierda -->
  <div class="col-lg-7">

    <!-- Dirección de envío -->
    <div class="card p-4 mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-geo-alt me-2 text-primary"></i>Dirección de envío</h5>
      <?php if ($addresses): ?>
        <div class="mb-3">
          <label class="form-label fw-semibold">Usar dirección guardada</label>
          <?php foreach ($addresses as $addr): ?>
            <div class="form-check border rounded p-3 mb-2">
              <input class="form-check-input" type="radio" name="address_id"
                     value="<?= $addr['id'] ?>" <?= $addr['is_default'] ? 'checked' : '' ?>>
              <label class="form-check-label">
                <strong><?= e($addr['full_name']) ?></strong><br>
                <small class="text-muted"><?= e($addr['address_line1']) ?>, <?= e($addr['city']) ?>, <?= e($addr['country']) ?></small>
              </label>
            </div>
          <?php endforeach; ?>
          <div class="form-check border rounded p-3">
            <input class="form-check-input" type="radio" name="address_id" value="0" id="newAddr">
            <label class="form-check-label" for="newAddr">+ Nueva dirección</label>
          </div>
        </div>
      <?php endif; ?>

      <div id="newAddrForm" <?= $addresses ? 'style="display:none"' : '' ?>>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre completo *</label>
            <input type="text" class="form-control" name="full_name" placeholder="Tu nombre">
          </div>
          <div class="col-md-6">
            <label class="form-label">Teléfono</label>
            <input type="tel" class="form-control" name="addr_phone" placeholder="+593 99 000 0000">
          </div>
          <div class="col-12">
            <label class="form-label">Dirección *</label>
            <input type="text" class="form-control" name="address_line1" placeholder="Calle, número, barrio">
          </div>
          <div class="col-md-4">
            <label class="form-label">Ciudad *</label>
            <input type="text" class="form-control" name="city" placeholder="Quito">
          </div>
          <div class="col-md-4">
            <label class="form-label">Provincia</label>
            <input type="text" class="form-control" name="state" placeholder="Pichincha">
          </div>
          <div class="col-md-4">
            <label class="form-label">Código postal</label>
            <input type="text" class="form-control" name="postal_code">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="save_address" id="saveAddr">
              <label class="form-check-label" for="saveAddr">Guardar esta dirección para futuras compras</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Envío -->
    <div class="card p-4 mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-truck me-2 text-primary"></i>Método de envío</h5>
      <?php foreach ($shippingRates as $rate): ?>
        <div class="form-check border rounded p-3 mb-2">
          <input class="form-check-input" type="radio" name="shipping_rate"
                 value="<?= $rate['id'] ?>" <?= $rate['base_cost'] == 3.50 ? 'checked' : '' ?>>
          <label class="form-check-label d-flex justify-content-between">
            <span>
              <strong><?= e($rate['name']) ?></strong>
              <small class="text-muted d-block"><?= e($rate['zone_name']) ?> · <?= $rate['estimated_days_min'] ?>–<?= $rate['estimated_days_max'] ?> días</small>
            </span>
            <span class="fw-bold"><?= $rate['base_cost'] == 0 ? '<span class="text-success">Gratis</span>' : money($rate['base_cost']) ?></span>
          </label>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pago -->
    <div class="card p-4 mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-credit-card me-2 text-primary"></i>Método de pago</h5>
      <?php foreach ($payMethods as $pm): ?>
        <div class="form-check border rounded p-3 mb-2">
          <input class="form-check-input" type="radio" name="payment_method"
                 value="<?= $pm['id'] ?>" id="pm<?= $pm['id'] ?>">
          <label class="form-check-label" for="pm<?= $pm['id'] ?>">
            <strong><?= e($pm['name']) ?></strong>
            <?php if ($pm['instructions']): ?>
              <small class="text-muted d-block"><?= e($pm['instructions']) ?></small>
            <?php endif; ?>
          </label>
        </div>
      <?php endforeach; ?>

      <!-- Cuentas bancarias (si aplica) -->
      <?php if ($bankAccounts): ?>
        <div class="alert alert-info mt-3 small" id="bankInfo" style="display:none">
          <strong><i class="bi bi-bank me-1"></i>Cuentas bancarias disponibles:</strong>
          <?php foreach ($bankAccounts as $ba): ?>
            <div class="mt-2">
              <strong><?= e($ba['bank_name']) ?></strong> — <?= e($ba['account_type']) ?><br>
              Titular: <?= e($ba['account_name']) ?> | N°: <?= e($ba['account_number']) ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Notas -->
    <div class="card p-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-chat-left-text me-2 text-primary"></i>Notas del pedido</h5>
      <textarea class="form-control" name="notes" rows="3"
                placeholder="Instrucciones especiales, horario de entrega, etc."></textarea>
    </div>
  </div>

  <!-- Resumen -->
  <div class="col-lg-5">
    <div class="card p-4 sticky-top" style="top:80px">
      <h5 class="fw-bold mb-3">Tu pedido</h5>
      <?php foreach ($items as $item): ?>
        <div class="d-flex align-items-center gap-3 mb-3">
          <img src="<?= $item['image'] ? e($item['image']) : BASE_URL.'/assets/img/no-image.svg' ?>"
               style="width:50px;height:50px;object-fit:cover;border-radius:8px">
          <div class="flex-grow-1">
            <p class="mb-0 fw-semibold small"><?= e(mb_strimwidth($item['name'],0,40,'…')) ?></p>
            <small class="text-muted">x<?= $item['quantity'] ?></small>
          </div>
          <span class="fw-bold"><?= money($item['unit_price'] * $item['quantity']) ?></span>
        </div>
      <?php endforeach; ?>
      <hr>
      <div class="d-flex justify-content-between mb-1"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
      <div class="d-flex justify-content-between mb-1"><span>Envío</span><span>Calculado arriba</span></div>
      <hr>
      <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
        <span>Total estimado</span><span class="price-tag"><?= money($total) ?>+</span>
      </div>
      <button type="submit" class="btn btn-accent btn-lg w-100 fw-semibold">
        <i class="bi bi-lock-fill me-2"></i>Confirmar pedido
      </button>
      <p class="text-center text-muted small mt-2">
        <i class="bi bi-shield-check me-1 text-success"></i>Pago 100% seguro
      </p>
    </div>
  </div>
</div>
</form>

<script>
// Mostrar/ocultar formulario nueva dirección
document.querySelectorAll('input[name="address_id"]').forEach(radio => {
  radio.addEventListener('change', function() {
    document.getElementById('newAddrForm').style.display =
      this.value === '0' ? 'block' : 'none';
  });
});
// Mostrar cuentas bancarias si selecciona transferencia
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
  radio.addEventListener('change', function() {
    const bankInfo = document.getElementById('bankInfo');
    if (bankInfo) bankInfo.style.display = 'none';
    const label = this.nextElementSibling.textContent.toLowerCase();
    if (label.includes('transferencia') && bankInfo) bankInfo.style.display = 'block';
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
