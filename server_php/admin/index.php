<?php
// ============================================================
//  Geo-Ecomers | Admin — Dashboard
// ============================================================
$pageTitle = 'Panel Admin';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

// KPIs
$stats = [
    'orders_today'    => $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'revenue_today'   => (float)$db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at)=CURDATE() AND status != 'cancelled'")->fetchColumn(),
    'total_users'     => $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),
    'total_products'  => $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(),
    'pending_orders'  => $db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
    'low_stock'       => $db->query("SELECT COUNT(*) FROM inventory WHERE quantity <= reorder_level")->fetchColumn(),
    'revenue_month'   => (float)$db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND status!='cancelled'")->fetchColumn(),
];

// Últimos 10 pedidos
$recentOrders = $db->query(
    "SELECT o.id, o.order_number, u.name AS customer, o.total, o.status, o.created_at
     FROM orders o LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC LIMIT 10"
)->fetchAll();

// Productos con stock bajo
$lowStockProducts = $db->query(
    "SELECT p.name, p.sku, i.quantity, i.reorder_level
     FROM inventory i JOIN products p ON p.id = i.product_id
     WHERE i.quantity <= i.reorder_level ORDER BY i.quantity LIMIT 8"
)->fetchAll();

$statusColors = ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'primary','delivered'=>'success','cancelled'=>'danger','refunded'=>'secondary'];
$statusLabels = ['pending'=>'Pendiente','confirmed'=>'Confirmado','processing'=>'En proceso','shipped'=>'Enviado','delivered'=>'Entregado','cancelled'=>'Cancelado','refunded'=>'Reembolsado'];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row g-0">
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Contenido -->
  <div class="col ps-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h3>
      <span class="text-muted small"><?= date('d/m/Y H:i') ?></span>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
      <?php $kpis = [
        ['icon'=>'bi-cart-check','label'=>'Pedidos hoy',     'value'=>$stats['orders_today'],              'color'=>'primary'],
        ['icon'=>'bi-cash-stack','label'=>'Ingresos hoy',    'value'=>money($stats['revenue_today']),      'color'=>'success'],
        ['icon'=>'bi-people',   'label'=>'Clientes',         'value'=>$stats['total_users'],               'color'=>'info'],
        ['icon'=>'bi-box-seam', 'label'=>'Productos activos','value'=>$stats['total_products'],            'color'=>'warning'],
        ['icon'=>'bi-hourglass','label'=>'Pedidos pendientes','value'=>$stats['pending_orders'],           'color'=>'danger'],
        ['icon'=>'bi-exclamation-triangle','label'=>'Stock bajo','value'=>$stats['low_stock'],             'color'=>'warning'],
        ['icon'=>'bi-graph-up', 'label'=>'Ingresos del mes', 'value'=>money($stats['revenue_month']),     'color'=>'success'],
      ]; foreach ($kpis as $kpi): ?>
        <div class="col-6 col-md-3">
          <div class="card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
              <div class="bg-<?= $kpi['color'] ?> bg-opacity-10 rounded-3 p-3">
                <i class="bi <?= $kpi['icon'] ?> text-<?= $kpi['color'] ?> fs-4"></i>
              </div>
              <div>
                <div class="fw-bold fs-4"><?= $kpi['value'] ?></div>
                <div class="text-muted small"><?= $kpi['label'] ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <!-- Últimos pedidos -->
      <div class="col-lg-7">
        <div class="card p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Últimos pedidos</h5>
            <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline-primary btn-sm">Ver todos</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle small">
              <thead class="table-light">
                <tr><th>N° Pedido</th><th>Cliente</th><th>Total</th><th>Estado</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($recentOrders as $o): ?>
                  <tr>
                    <td class="fw-semibold"><?= e($o['order_number']) ?></td>
                    <td><?= e($o['customer'] ?? 'Invitado') ?></td>
                    <td class="fw-bold text-danger"><?= money($o['total']) ?></td>
                    <td><span class="badge bg-<?= $statusColors[$o['status']] ?? 'secondary' ?>"><?= $statusLabels[$o['status']] ?? $o['status'] ?></span></td>
                    <td><a href="<?= BASE_URL ?>/admin/orders.php?id=<?= $o['id'] ?>" class="btn btn-xs btn-outline-secondary btn-sm py-0">Ver</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Stock bajo -->
      <div class="col-lg-5">
        <div class="card p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Stock bajo</h5>
            <a href="<?= BASE_URL ?>/admin/products.php" class="btn btn-outline-warning btn-sm">Gestionar</a>
          </div>
          <?php if (empty($lowStockProducts)): ?>
            <p class="text-success small"><i class="bi bi-check-circle me-1"></i>Todo el stock está bien.</p>
          <?php else: ?>
            <?php foreach ($lowStockProducts as $ps): ?>
              <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                  <p class="mb-0 small fw-semibold"><?= e(mb_strimwidth($ps['name'],0,35,'…')) ?></p>
                  <small class="text-muted">SKU: <?= e($ps['sku']) ?></small>
                </div>
                <span class="badge bg-<?= $ps['quantity'] == 0 ? 'danger' : 'warning' ?> text-dark">
                  <?= $ps['quantity'] ?> unid.
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
