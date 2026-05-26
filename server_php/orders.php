<?php
// ============================================================
//  Geo-Ecomers | Mis Pedidos
// ============================================================
$pageTitle = 'Mis pedidos';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$db = getDB();

// Ver detalle de un pedido específico
$orderId = (int)($_GET['order'] ?? 0);
if ($orderId) {
    $stmt = $db->prepare(
        'SELECT o.*, a.full_name, a.address_line1, a.city, a.state, a.country, a.phone AS addr_phone
         FROM orders o
         LEFT JOIN addresses a ON a.id = o.address_id
         WHERE o.id = ? AND o.user_id = ?'
    );
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if ($order) {
        $orderItems = $db->prepare(
            'SELECT oi.*, (SELECT image_url FROM product_images WHERE product_id=oi.product_id AND is_primary=1 LIMIT 1) AS image
             FROM order_items oi WHERE oi.order_id = ?'
        );
        $orderItems->execute([$orderId]);
        $orderItems = $orderItems->fetchAll();

        $payment = $db->prepare('SELECT p.*, pm.name AS method_name FROM payments p JOIN payment_methods_config pm ON pm.id = p.payment_method_id WHERE p.order_id = ? LIMIT 1');
        $payment->execute([$orderId]);
        $payment = $payment->fetch();

        $shipment = $db->prepare('SELECT s.*, sc.name AS carrier_name FROM shipments s LEFT JOIN shipping_carriers sc ON sc.id = s.carrier_id WHERE s.order_id = ? LIMIT 1');
        $shipment->execute([$orderId]);
        $shipment = $shipment->fetch();

        $history = $db->prepare('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at');
        $history->execute([$orderId]);
        $history = $history->fetchAll();
    }
}

// Listado de pedidos
$orders = $db->prepare(
    'SELECT o.id, o.order_number, o.status, o.total, o.created_at,
            COUNT(oi.id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.user_id = ?
     GROUP BY o.id ORDER BY o.created_at DESC'
);
$orders->execute([$_SESSION['user_id']]);
$orders = $orders->fetchAll();

$statusColors = [
    'pending'    => 'warning',
    'confirmed'  => 'info',
    'processing' => 'primary',
    'shipped'    => 'primary',
    'delivered'  => 'success',
    'cancelled'  => 'danger',
    'refunded'   => 'secondary',
];
$statusLabels = [
    'pending'    => 'Pendiente',
    'confirmed'  => 'Confirmado',
    'processing' => 'En proceso',
    'shipped'    => 'Enviado',
    'delivered'  => 'Entregado',
    'cancelled'  => 'Cancelado',
    'refunded'   => 'Reembolsado',
];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<?php if ($orderId && !empty($order)): ?>
<!-- ===== DETALLE DE PEDIDO ===== -->
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?= BASE_URL ?>/orders.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Mis pedidos
  </a>
  <h2 class="section-title mb-0">Pedido <?= e($order['order_number']) ?></h2>
  <span class="badge bg-<?= $statusColors[$order['status']] ?? 'secondary' ?> ms-auto">
    <?= $statusLabels[$order['status']] ?? $order['status'] ?>
  </span>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <!-- Items -->
    <div class="card p-4 mb-4">
      <h5 class="fw-bold mb-3">Productos</h5>
      <?php foreach ($orderItems as $item): ?>
        <div class="d-flex align-items-center gap-3 py-3 border-bottom">
          <img src="<?= $item['image'] ? e($item['image']) : BASE_URL.'/assets/img/no-image.svg' ?>"
               style="width:60px;height:60px;object-fit:cover;border-radius:8px">
          <div class="flex-grow-1">
            <p class="mb-0 fw-semibold"><?= e($item['product_name']) ?></p>
            <small class="text-muted">SKU: <?= e($item['product_sku'] ?? '—') ?> | Cant: <?= $item['quantity'] ?></small>
          </div>
          <span class="fw-bold"><?= money($item['total_price']) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="mt-3 text-end">
        <span class="fw-bold fs-5">Total: <span class="price-tag"><?= money($order['total']) ?></span></span>
      </div>
    </div>

    <!-- Historial de estados -->
    <?php if ($history): ?>
    <div class="card p-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Historial del pedido</h5>
      <div class="position-relative ms-3">
        <?php foreach ($history as $h): ?>
          <div class="d-flex gap-3 mb-3">
            <div class="bg-primary rounded-circle text-white d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:32px;height:32px;font-size:.75rem">
              <i class="bi bi-check-lg"></i>
            </div>
            <div>
              <span class="badge bg-<?= $statusColors[$h['status']] ?? 'secondary' ?> mb-1">
                <?= $statusLabels[$h['status']] ?? $h['status'] ?>
              </span>
              <?php if ($h['comment']): ?>
                <p class="mb-0 small"><?= e($h['comment']) ?></p>
              <?php endif; ?>
              <small class="text-muted"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <!-- Dirección -->
    <?php if ($order['full_name']): ?>
    <div class="card p-4 mb-4">
      <h6 class="fw-bold mb-2"><i class="bi bi-geo-alt text-primary me-2"></i>Dirección de envío</h6>
      <p class="mb-1 fw-semibold"><?= e($order['full_name']) ?></p>
      <p class="mb-1 small"><?= e($order['address_line1']) ?></p>
      <p class="mb-0 small text-muted"><?= e($order['city']) ?><?= $order['state'] ? ', ' . e($order['state']) : '' ?>, <?= e($order['country']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Pago -->
    <?php if ($payment): ?>
    <div class="card p-4 mb-4">
      <h6 class="fw-bold mb-2"><i class="bi bi-credit-card text-primary me-2"></i>Pago</h6>
      <p class="mb-1 small"><?= e($payment['method_name']) ?></p>
      <span class="badge bg-<?= $payment['status'] === 'completed' ? 'success' : 'warning' ?> text-dark">
        <?= ucfirst($payment['status']) ?>
      </span>
    </div>
    <?php endif; ?>

    <!-- Envío -->
    <?php if ($shipment): ?>
    <div class="card p-4">
      <h6 class="fw-bold mb-2"><i class="bi bi-truck text-primary me-2"></i>Envío</h6>
      <?php if ($shipment['carrier_name']): ?>
        <p class="mb-1 small"><?= e($shipment['carrier_name']) ?></p>
      <?php endif; ?>
      <?php if ($shipment['tracking_number']): ?>
        <p class="mb-1 small">Guía: <strong><?= e($shipment['tracking_number']) ?></strong></p>
      <?php endif; ?>
      <span class="badge bg-info text-dark"><?= ucfirst(str_replace('_',' ',$shipment['status'])) ?></span>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ===== LISTADO DE PEDIDOS ===== -->
<h2 class="section-title mb-4"><i class="bi bi-box-seam me-2"></i>Mis pedidos</h2>

<?php if (empty($orders)): ?>
  <div class="text-center py-5">
    <i class="bi bi-bag-x text-muted" style="font-size:5rem"></i>
    <h4 class="mt-3">Aún no tienes pedidos</h4>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-accent mt-3 rounded-pill px-5">
      <i class="bi bi-bag me-2"></i>Ir a comprar
    </a>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($orders as $o): ?>
      <div class="col-12">
        <div class="card p-4">
          <div class="row align-items-center">
            <div class="col-md-3">
              <p class="mb-0 fw-bold"><?= e($o['order_number']) ?></p>
              <small class="text-muted"><?= date('d/m/Y', strtotime($o['created_at'])) ?></small>
            </div>
            <div class="col-md-2">
              <small class="text-muted">Productos</small>
              <p class="mb-0 fw-semibold"><?= $o['item_count'] ?> artículo<?= $o['item_count'] != 1 ? 's' : '' ?></p>
            </div>
            <div class="col-md-2">
              <small class="text-muted">Total</small>
              <p class="mb-0 fw-bold price-tag"><?= money($o['total']) ?></p>
            </div>
            <div class="col-md-3">
              <span class="badge bg-<?= $statusColors[$o['status']] ?? 'secondary' ?> py-2 px-3">
                <?= $statusLabels[$o['status']] ?? $o['status'] ?>
              </span>
            </div>
            <div class="col-md-2 text-end">
              <a href="<?= BASE_URL ?>/orders.php?order=<?= $o['id'] ?>"
                 class="btn btn-outline-primary btn-sm">
                <i class="bi bi-eye me-1"></i>Ver detalle
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
