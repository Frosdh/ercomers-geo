<?php
// ============================================================
//  Geo-Ecomers | Carrito de Compras
// ============================================================
$pageTitle = 'Mi carrito';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();

// ── Helper: ejecutar add al carrito ──────────────────────────
function doAddToCart(PDO $db, int $productId, int $qty): void {
    $cartId   = getOrCreateCart();
    $existing = $db->prepare('SELECT id FROM cart_items WHERE cart_id=? AND product_id=? AND variant_id IS NULL');
    $existing->execute([$cartId, $productId]);
    $row = $existing->fetch();
    if ($row) {
        $db->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?')
           ->execute([$qty, $row['id']]);
    } else {
        $priceStmt = $db->prepare('SELECT price FROM products WHERE id = ? AND status="active"');
        $priceStmt->execute([$productId]);
        $price = (float)$priceStmt->fetchColumn();
        if ($price > 0) {
            $db->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, unit_price) VALUES (?,?,?,?)')
               ->execute([$cartId, $productId, $qty, $price]);
        }
    }
}

// ── Si acaba de iniciar sesión y tenía un add pendiente ───────
if (isLoggedIn() && !empty($_SESSION['pending_cart_add'])) {
    $pending = $_SESSION['pending_cart_add'];
    unset($_SESSION['pending_cart_add']);
    doAddToCart($db, (int)$pending['product_id'], (int)$pending['qty']);
    setFlash('success', 'Producto agregado al carrito.');
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && !empty($_POST['product_id'])) {
        $productId = (int)$_POST['product_id'];
        $qty       = max(1, (int)($_POST['qty'] ?? 1));

        if (!isLoggedIn()) {
            // Guardar intent y redirigir al login
            $_SESSION['pending_cart_add']    = ['product_id' => $productId, 'qty' => $qty];
            $_SESSION['redirect_after_login'] = BASE_URL . '/cart.php';
            setFlash('info', 'Inicia sesión para agregar productos al carrito.');
            header('Location: ' . BASE_URL . '/auth/login.php');
            exit;
        }

        doAddToCart($db, $productId, $qty);
        setFlash('success', 'Producto agregado al carrito.');

        // Volver a donde estaba (product page o index)
        $back = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/index.php';
        header('Location: ' . $back);
        exit;
    }

    if ($action === 'update' && isLoggedIn()) {
        foreach ($_POST['quantities'] ?? [] as $itemId => $qty) {
            $qty = max(0, (int)$qty);
            if ($qty === 0) {
                $db->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$itemId]);
            } else {
                $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')->execute([$qty, $itemId]);
            }
        }
        setFlash('success', 'Carrito actualizado.');
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }

    if ($action === 'remove' && isLoggedIn()) {
        $db->prepare('DELETE FROM cart_items WHERE id = ?')->execute([(int)$_POST['item_id']]);
        setFlash('info', 'Producto eliminado del carrito.');
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }

    if ($action === 'clear' && isLoggedIn()) {
        $cartId = getOrCreateCart();
        $db->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cartId]);
        setFlash('info', 'Carrito vaciado.');
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }
}

// Si no está logueado, redirigir con mensaje
if (!isLoggedIn()) {
    setFlash('warning', 'Debes iniciar sesión para ver tu carrito.');
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Cargar items
$cartId  = getOrCreateCart();
$items   = $db->prepare(
    "SELECT ci.id, ci.quantity, ci.unit_price,
            p.id AS product_id, p.name, p.price AS current_price, p.slug,
            (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image,
            COALESCE((SELECT quantity FROM inventory WHERE product_id=p.id AND variant_id IS NULL LIMIT 1),0) AS stock
     FROM cart_items ci
     JOIN products p ON p.id = ci.product_id
     WHERE ci.cart_id = ?"
);
$items->execute([$cartId]);
$items = $items->fetchAll();

// Cupón
$coupon    = null;
$discount  = 0;
$couponMsg = '';

if (!empty($_GET['coupon']) || !empty($_POST['coupon_code'])) {
    $code      = strtoupper(sanitize($_POST['coupon_code'] ?? $_GET['coupon'] ?? ''));
    $cpnStmt   = $db->prepare("SELECT * FROM discounts WHERE code = ? AND status='active' AND (expires_at IS NULL OR expires_at > NOW())");
    $cpnStmt->execute([$code]);
    $coupon    = $cpnStmt->fetch();
    if (!$coupon) $couponMsg = 'Cupón no válido o expirado.';
}

// Totales
$subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['quantity'], $items));
if ($coupon) {
    $discount = match($coupon['type']) {
        'percentage'   => $subtotal * ($coupon['value'] / 100),
        'fixed_amount' => min($coupon['value'], $subtotal),
        default        => 0,
    };
}
$shipping = $subtotal >= 50 ? 0 : 3.50;
$total    = max(0, $subtotal - $discount + $shipping);
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<h2 class="section-title mb-4"><i class="bi bi-cart3 me-2"></i>Mi carrito</h2>

<?php if (empty($items)): ?>
  <div class="text-center py-5">
    <i class="bi bi-cart-x text-muted" style="font-size:5rem"></i>
    <h4 class="mt-3">Tu carrito está vacío</h4>
    <p class="text-muted">Agrega productos para comenzar a comprar.</p>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-accent btn-lg mt-2 rounded-pill px-5">
      <i class="bi bi-bag me-2"></i>Ver productos
    </a>
  </div>
<?php else: ?>
<div class="row g-4">
  <!-- Lista de items -->
  <div class="col-lg-8">
    <form method="POST" id="cartForm">
      <input type="hidden" name="action" value="update">
      <div class="card p-0 overflow-hidden">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Producto</th>
              <th class="text-center">Precio</th>
              <th class="text-center" style="width:130px">Cantidad</th>
              <th class="text-center">Subtotal</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-3">
                  <img src="<?= $item['image'] ? e($item['image']) : BASE_URL.'/assets/img/no-image.svg' ?>"
                       style="width:64px;height:64px;object-fit:cover;border-radius:8px">
                  <a href="<?= BASE_URL ?>/product.php?id=<?= $item['product_id'] ?>"
                     class="text-decoration-none text-dark fw-semibold">
                    <?= e(mb_strimwidth($item['name'],0,50,'…')) ?>
                  </a>
                </div>
              </td>
              <td class="text-center"><?= money($item['unit_price']) ?></td>
              <td class="text-center">
                <input type="number" name="quantities[<?= $item['id'] ?>]"
                       value="<?= $item['quantity'] ?>"
                       min="0" max="<?= $item['stock'] ?>"
                       class="form-control form-control-sm text-center"
                       onchange="updateSubtotal()"
                       data-price="<?= $item['unit_price'] ?>"
                       data-id="<?= $item['id'] ?>">
              </td>
              <td class="text-center fw-bold price-tag" id="sub_<?= $item['id'] ?>">
                <?= money($item['unit_price'] * $item['quantity']) ?>
              </td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action"  value="remove">
                  <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-outline-primary">
          <i class="bi bi-arrow-clockwise me-1"></i>Actualizar carrito
        </button>
        <form method="POST" class="ms-auto">
          <input type="hidden" name="action" value="clear">
          <button type="submit" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-trash me-1"></i>Vaciar carrito
          </button>
        </form>
      </div>
    </form>
  </div>

  <!-- Resumen -->
  <div class="col-lg-4">
    <div class="card p-4">
      <h5 class="fw-bold mb-4">Resumen del pedido</h5>

      <!-- Cupón -->
      <form method="POST" class="mb-3">
        <input type="hidden" name="action" value="coupon">
        <label class="form-label small fw-semibold">¿Tienes un cupón?</label>
        <div class="input-group">
          <input type="text" name="coupon_code" class="form-control" placeholder="Ej: BIENVENIDO10">
          <button type="submit" class="btn btn-outline-primary">Aplicar</button>
        </div>
        <?php if ($couponMsg): ?>
          <small class="text-danger"><?= e($couponMsg) ?></small>
        <?php elseif ($coupon): ?>
          <small class="text-success"><i class="bi bi-check-circle me-1"></i>Cupón aplicado: <?= e($coupon['code']) ?></small>
        <?php endif; ?>
      </form>

      <hr>
      <div class="d-flex justify-content-between mb-2">
        <span>Subtotal</span><span><?= money($subtotal) ?></span>
      </div>
      <?php if ($discount > 0): ?>
      <div class="d-flex justify-content-between mb-2 text-success">
        <span>Descuento</span><span>-<?= money($discount) ?></span>
      </div>
      <?php endif; ?>
      <div class="d-flex justify-content-between mb-2">
        <span>Envío</span>
        <span><?= $shipping == 0 ? '<span class="text-success">Gratis</span>' : money($shipping) ?></span>
      </div>
      <?php if ($subtotal < 50 && $subtotal > 0): ?>
        <small class="text-muted d-block mb-2">
          <i class="bi bi-info-circle me-1"></i>Agrega <?= money(50 - $subtotal) ?> más para envío gratis
        </small>
      <?php endif; ?>
      <hr>
      <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
        <span>Total</span><span class="price-tag"><?= money($total) ?></span>
      </div>
      <a href="<?= BASE_URL ?>/checkout.php" class="btn btn-accent btn-lg w-100 fw-semibold">
        <i class="bi bi-bag-check me-2"></i>Proceder al pago
      </a>
      <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary w-100 mt-2">
        <i class="bi bi-arrow-left me-1"></i>Seguir comprando
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function updateSubtotal() {
  document.querySelectorAll('input[data-price]').forEach(input => {
    const sub = document.getElementById('sub_' + input.dataset.id);
    if (sub) {
      const total = (parseFloat(input.dataset.price) * parseInt(input.value || 0)).toFixed(2);
      sub.textContent = '$' + total;
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
