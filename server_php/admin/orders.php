<?php
// ============================================================
//  Geo-Ecomers | Admin — Gestión de Pedidos
// ============================================================
$pageTitle = 'Pedidos';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

// Actualizar estado de pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['order_id'])) {
    $orderId   = (int)$_POST['order_id'];
    $newStatus = sanitize($_POST['status'] ?? '');
    $comment   = sanitize($_POST['comment'] ?? '');

    $db->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $orderId]);
    $db->prepare('INSERT INTO order_status_history (order_id, status, comment, created_by) VALUES (?,?,?,?)')
       ->execute([$orderId, $newStatus, $comment, $_SESSION['user_id']]);

    setFlash('success', 'Estado del pedido actualizado.');
    header('Location: ' . BASE_URL . '/admin/orders.php?id=' . $orderId);
    exit;
}

// Ver detalle
$orderId = (int)($_GET['id'] ?? 0);
$order   = null;
if ($orderId) {
    $stmt = $db->prepare(
        'SELECT o.*, u.name AS customer_name, u.email AS customer_email,
                a.full_name, a.address_line1, a.city, a.state, a.country
         FROM orders o
         LEFT JOIN users u     ON u.id = o.user_id
         LEFT JOIN addresses a ON a.id = o.address_id
         WHERE o.id = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if ($order) {
        $orderItems = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $orderItems->execute([$orderId]);
        $orderItems = $orderItems->fetchAll();

        $payment = $db->prepare(
            'SELECT p.*, pm.name AS method_name FROM payments p
             JOIN payment_methods_config pm ON pm.id = p.payment_method_id
             WHERE p.order_id = ? LIMIT 1'
        );
        $payment->execute([$orderId]);
        $payment = $payment->fetch();

        $history = $db->prepare('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC');
        $history->execute([$orderId]);
        $history = $history->fetchAll();
    }
}

// Listado
$statusFilter = sanitize($_GET['s']    ?? '');
$search       = sanitize($_GET['q']    ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;

$where = ['1=1']; $params = [];
if ($statusFilter) { $where[] = 'o.status = ?'; $params[] = $statusFilter; }
if ($search) { $where[] = '(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSQL = implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE $whereSQL");
$cntStmt->execute($params); $total = (int)$cntStmt->fetchColumn();
$pag = paginate($total, $perPage, $page);

$listStmt = $db->prepare(
    "SELECT o.id, o.order_number, o.status, o.total, o.created_at,
            u.name AS customer_name, u.email AS customer_email
     FROM orders o LEFT JOIN users u ON u.id = o.user_id
     WHERE $whereSQL ORDER BY o.created_at DESC
     LIMIT {$pag['perPage']} OFFSET {$pag['offset']}"
);
$listStmt->execute($params);
$ordersList = $listStmt->fetchAll();

$statusColors = ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger','refunded'=>'secondary'];
$statusLabels = ['pending'=>'Pendiente','confirmed'=>'Confirmado','processing'=>'En proceso','shipped'=>'Enviado','delivered'=>'Entregado','cancelled'=>'Cancelado','refunded'=>'Reembolsado'];
$allStatuses  = array_keys($statusLabels);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row g-0">
  <?php require_once __DIR__ . '/sidebar.php'; ?>
  <div class="col ps-4">

    <?php if ($order): ?>
    <!-- ===== DETALLE ===== -->
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Pedidos
      </a>
      <h3 class="fw-bold mb-0">Pedido <?= e($order['order_number']) ?></h3>
      <span class="badge bg-<?= $statusColors[$order['status']] ?? 'secondary' ?> ms-2">
        <?= $statusLabels[$order['status']] ?? $order['status'] ?>
      </span>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <!-- Items -->
        <div class="card p-4 mb-4">
          <h5 class="fw-bold mb-3">Productos del pedido</h5>
          <div class="table-responsive">
            <table class="table small mb-0">
              <thead class="table-light"><tr><th>Producto</th><th>SKU</th><th>Cant.</th><th>Precio unit.</th><th>Total</th></tr></thead>
              <tbody>
                <?php foreach ($orderItems as $item): ?>
                  <tr>
                    <td><?= e($item['product_name']) ?></td>
                    <td><?= e($item['product_sku'] ?? '—') ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= money($item['unit_price']) ?></td>
                    <td class="fw-bold"><?= money($item['total_price']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr><td colspan="4" class="text-end fw-bold">Total:</td><td class="fw-bold price-tag fs-5"><?= money($order['total']) ?></td></tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Cambiar estado -->
        <div class="card p-4">
          <h5 class="fw-bold mb-3"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Actualizar estado</h5>
          <form method="POST">
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
            <div class="row g-3">
              <div class="col-md-5">
                <select class="form-select" name="status">
                  <?php foreach ($allStatuses as $s): ?>
                    <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                      <?= $statusLabels[$s] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-5">
                <input type="text" class="form-control" name="comment" placeholder="Comentario (opcional)">
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Actualizar</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="col-lg-4">
        <!-- Cliente -->
        <div class="card p-4 mb-4">
          <h6 class="fw-bold mb-2"><i class="bi bi-person me-2 text-primary"></i>Cliente</h6>
          <p class="mb-1 fw-semibold"><?= e($order['customer_name'] ?? 'Invitado') ?></p>
          <p class="mb-0 small text-muted"><?= e($order['customer_email'] ?? '') ?></p>
        </div>
        <!-- Dirección -->
        <?php if ($order['full_name']): ?>
        <div class="card p-4 mb-4">
          <h6 class="fw-bold mb-2"><i class="bi bi-geo-alt me-2 text-primary"></i>Dirección de envío</h6>
          <p class="mb-1 small"><?= e($order['full_name']) ?></p>
          <p class="mb-1 small"><?= e($order['address_line1']) ?></p>
          <p class="mb-0 small text-muted"><?= e($order['city']) ?><?= $order['state'] ? ', '.e($order['state']) : '' ?>, <?= e($order['country']) ?></p>
        </div>
        <?php endif; ?>
        <!-- Pago -->
        <?php if ($payment): ?>
        <div class="card p-4 mb-4">
          <h6 class="fw-bold mb-2"><i class="bi bi-credit-card me-2 text-primary"></i>Pago</h6>
          <p class="mb-1 small"><?= e($payment['method_name']) ?></p>
          <p class="mb-1 small">Monto: <strong><?= money($payment['amount']) ?></strong></p>
          <span class="badge bg-<?= $payment['status']==='completed'?'success':'warning' ?> text-dark">
            <?= ucfirst($payment['status']) ?>
          </span>
        </div>
        <?php endif; ?>
        <!-- Historial -->
        <?php if ($history): ?>
        <div class="card p-4">
          <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Historial</h6>
          <?php foreach ($history as $h): ?>
            <div class="d-flex gap-2 mb-2 small">
              <span class="badge bg-<?= $statusColors[$h['status']] ?? 'secondary' ?> align-self-start">
                <?= $statusLabels[$h['status']] ?? $h['status'] ?>
              </span>
              <div>
                <?php if ($h['comment']): ?><p class="mb-0"><?= e($h['comment']) ?></p><?php endif; ?>
                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php else: ?>
    <!-- ===== LISTADO ===== -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0"><i class="bi bi-cart3 me-2"></i>Pedidos</h3>
    </div>

    <!-- Filtros -->
    <form method="GET" class="row g-2 mb-4">
      <div class="col-md-5">
        <input type="text" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Buscar por N° pedido o cliente...">
      </div>
      <div class="col-md-3">
        <select class="form-select" name="s">
          <option value="">Todos los estados</option>
          <?php foreach ($statusLabels as $val => $label): ?>
            <option value="<?= $val ?>" <?= $statusFilter===$val?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
        <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline-secondary">Limpiar</a>
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
          <thead class="table-light">
            <tr><th>N° Pedido</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Fecha</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($ordersList as $o): ?>
              <tr>
                <td class="fw-bold"><?= e($o['order_number']) ?></td>
                <td>
                  <?= e($o['customer_name'] ?? 'Invitado') ?>
                  <br><small class="text-muted"><?= e($o['customer_email'] ?? '') ?></small>
                </td>
                <td class="fw-bold price-tag"><?= money($o['total']) ?></td>
                <td><span class="badge bg-<?= $statusColors[$o['status']] ?? 'secondary' ?>"><?= $statusLabels[$o['status']] ?? $o['status'] ?></span></td>
                <td><?= date('d/m/Y', strtotime($o['created_at'])) ?></td>
                <td>
                  <a href="<?= BASE_URL ?>/admin/orders.php?id=<?= $o['id'] ?>"
                     class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye me-1"></i>Ver
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pag['pages'] > 1): ?>
        <div class="p-3">
          <nav><ul class="pagination pagination-sm justify-content-end mb-0">
            <?php for ($i=1;$i<=$pag['pages'];$i++): ?>
              <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&s=<?= e($statusFilter) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
          </ul></nav>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
