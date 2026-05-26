<?php
// ============================================================
//  Geo-Ecomers | Admin — Inventario
// ============================================================
$pageTitle = 'Inventario';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

// ═══════════════════════════════════════════════════════════════
//  ACCIONES POST
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Ajuste de stock ──────────────────────────────────────
    if ($action === 'adjust') {
        $product_id    = (int)($_POST['product_id']    ?? 0);
        $adjust_type   = sanitize($_POST['adjust_type'] ?? 'set');  // set | add | subtract
        $amount        = (int)($_POST['amount']         ?? 0);
        $reorder_level = (int)($_POST['reorder_level']  ?? 5);
        $note          = sanitize($_POST['note']         ?? '');

        if ($product_id && $amount >= 0) {
            // Obtener stock actual
            $stmt = $db->prepare('SELECT quantity FROM inventory WHERE product_id = ?');
            $stmt->execute([$product_id]);
            $cur = (int)($stmt->fetchColumn() ?: 0);

            $new_qty = match($adjust_type) {
                'add'      => $cur + $amount,
                'subtract' => max(0, $cur - $amount),
                default    => $amount,  // 'set'
            };

            // Upsert inventory
            $db->prepare(
                'INSERT INTO inventory (product_id, quantity, reorder_level)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = ?, reorder_level = ?'
            )->execute([$product_id, $new_qty, $reorder_level, $new_qty, $reorder_level]);

            // Log the movement
            $db->prepare(
                'INSERT INTO inventory_movements (product_id, type, quantity, note, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            )->execute([$product_id, $adjust_type, ($adjust_type === 'subtract' ? -$amount : $amount), $note ?: null]);

            setFlash('success', 'Stock actualizado correctamente.');
        } else {
            setFlash('danger', 'Datos inválidos. Verifica el producto y la cantidad.');
        }
        header('Location: inventory.php'); exit;
    }

    // ── Actualizar nivel de reorden masivo ───────────────────
    if ($action === 'bulk_reorder') {
        foreach ($_POST['reorder'] ?? [] as $pid => $level) {
            $db->prepare('UPDATE inventory SET reorder_level = ? WHERE product_id = ?')
               ->execute([(int)$level, (int)$pid]);
        }
        setFlash('success', 'Niveles de reorden actualizados.');
        header('Location: inventory.php'); exit;
    }
}

// ═══════════════════════════════════════════════════════════════
//  FILTROS
// ═══════════════════════════════════════════════════════════════
$filter = $_GET['filter'] ?? 'all';   // all | low | out
$search = sanitize($_GET['q'] ?? '');

$where  = 'WHERE 1=1';
$params = [];

if ($filter === 'low')  { $where .= ' AND i.quantity > 0 AND i.quantity <= i.reorder_level'; }
if ($filter === 'out')  { $where .= ' AND i.quantity = 0'; }
if ($search)            { $where .= ' AND p.name LIKE ?'; $params[] = '%' . $search . '%'; }

$products = $db->prepare(
    "SELECT p.id, p.name, p.sku, p.price, p.status,
            c.name AS category,
            COALESCE(i.quantity, 0) AS quantity,
            COALESCE(i.reorder_level, 5) AS reorder_level
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN inventory i ON i.product_id = p.id
     {$where}
     ORDER BY i.quantity ASC, p.name ASC"
);
$products->execute($params);
$products = $products->fetchAll();

// Totales para stats
$allInventory = $db->query(
    'SELECT COALESCE(i.quantity,0) AS qty, i.reorder_level
     FROM products p LEFT JOIN inventory i ON i.product_id = p.id
     WHERE p.status = "active"'
)->fetchAll();

$totalItems    = count($allInventory);
$outOfStock    = count(array_filter($allInventory, fn($r) => (int)$r['qty'] === 0));
$lowStock      = count(array_filter($allInventory, fn($r) => (int)$r['qty'] > 0 && (int)$r['qty'] <= (int)$r['reorder_level']));
$totalUnits    = array_sum(array_column($allInventory, 'qty'));

// Últimos movimientos
$movements = $db->query(
    'SELECT m.*, p.name AS product_name, p.sku
     FROM inventory_movements m
     JOIN products p ON p.id = m.product_id
     ORDER BY m.created_at DESC LIMIT 20'
)->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row g-0">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <div class="col ps-4 pt-2">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0"><i class="bi bi-boxes me-2"></i>Inventario</h3>
    </div>

    <?= showFlash() ?>

    <!-- ── Stats ─────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card p-3 text-center">
          <div class="fs-2 fw-bold text-primary"><?= $totalItems ?></div>
          <div class="text-muted small">Productos</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card p-3 text-center">
          <div class="fs-2 fw-bold text-success"><?= number_format($totalUnits) ?></div>
          <div class="text-muted small">Unidades en stock</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card p-3 text-center border-warning">
          <div class="fs-2 fw-bold text-warning"><?= $lowStock ?></div>
          <div class="text-muted small">Stock bajo</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card p-3 text-center border-danger">
          <div class="fs-2 fw-bold text-danger"><?= $outOfStock ?></div>
          <div class="text-muted small">Sin stock</div>
        </div>
      </div>
    </div>

    <!-- ── Filtros ────────────────────────────────────────────── -->
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
      <a href="?filter=all"  class="btn btn-sm <?= $filter==='all'  ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
      <a href="?filter=low"  class="btn btn-sm <?= $filter==='low'  ? 'btn-warning' : 'btn-outline-warning' ?>">Stock bajo</a>
      <a href="?filter=out"  class="btn btn-sm <?= $filter==='out'  ? 'btn-danger'  : 'btn-outline-danger'  ?>">Sin stock</a>
      <form method="GET" class="ms-auto d-flex gap-2">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <input type="search" class="form-control form-control-sm" name="q"
               value="<?= e($search) ?>" placeholder="Buscar producto…" style="width:200px">
        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
      </form>
    </div>

    <!-- ── Tabla de productos ─────────────────────────────────── -->
    <div class="card mb-4">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Producto</th>
                <th>SKU</th>
                <th>Categoría</th>
                <th>Precio</th>
                <th class="text-center">Stock actual</th>
                <th class="text-center">Nivel reorden</th>
                <th class="text-center">Estado</th>
                <th class="text-end">Ajustar</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p):
                $qty = (int)$p['quantity'];
                $reorder = (int)$p['reorder_level'];
                $stockClass = $qty === 0 ? 'danger' : ($qty <= $reorder ? 'warning' : 'success');
              ?>
              <tr class="<?= $qty === 0 ? 'table-danger' : ($qty <= $reorder ? 'table-warning' : '') ?>">
                <td>
                  <div class="fw-semibold"><?= e($p['name']) ?></div>
                  <?php if ($p['status'] !== 'active'): ?>
                    <span class="badge bg-secondary small"><?= e($p['status']) ?></span>
                  <?php endif; ?>
                </td>
                <td><code class="small"><?= e($p['sku'] ?? '—') ?></code></td>
                <td class="text-muted small"><?= e($p['category'] ?? '—') ?></td>
                <td><?= money($p['price']) ?></td>
                <td class="text-center">
                  <span class="badge bg-<?= $stockClass ?> fs-6 px-3"><?= $qty ?></span>
                </td>
                <td class="text-center text-muted"><?= $reorder ?></td>
                <td class="text-center">
                  <?php if ($qty === 0): ?>
                    <span class="badge bg-danger">Sin stock</span>
                  <?php elseif ($qty <= $reorder): ?>
                    <span class="badge bg-warning">Stock bajo</span>
                  <?php else: ?>
                    <span class="badge bg-success">OK</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary"
                          onclick="openAdjust(<?= $p['id'] ?>, <?= htmlspecialchars(json_encode($p['name']), ENT_QUOTES) ?>, <?= $qty ?>, <?= $reorder ?>)">
                    <i class="bi bi-sliders me-1"></i>Ajustar
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($products)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No se encontraron productos.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── Últimos movimientos ───────────────────────────────── -->
    <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Últimos movimientos</h5>
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Fecha</th>
                <th>Producto</th>
                <th>SKU</th>
                <th>Tipo</th>
                <th class="text-center">Cantidad</th>
                <th>Nota</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($movements as $mv): ?>
              <tr>
                <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($mv['created_at'])) ?></td>
                <td><?= e($mv['product_name']) ?></td>
                <td><code class="small"><?= e($mv['sku'] ?? '—') ?></code></td>
                <td>
                  <?php $tc = match($mv['type']) {
                    'add'      => 'success',
                    'subtract' => 'danger',
                    'set'      => 'info',
                    'sale'     => 'warning',
                    default    => 'secondary',
                  }; ?>
                  <span class="badge bg-<?= $tc ?>">
                    <?= match($mv['type']) {
                      'add'      => 'Entrada',
                      'subtract' => 'Salida',
                      'set'      => 'Ajuste',
                      'sale'     => 'Venta',
                      default    => $mv['type'],
                    } ?>
                  </span>
                </td>
                <td class="text-center fw-semibold <?= $mv['quantity'] < 0 ? 'text-danger' : 'text-success' ?>">
                  <?= $mv['quantity'] > 0 ? '+' : '' ?><?= $mv['quantity'] ?>
                </td>
                <td class="text-muted small"><?= e($mv['note'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($movements)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Sin movimientos registrados.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /col -->
</div><!-- /row -->

<!-- ── Modal Ajustar Stock ───────────────────────────────────── -->
<div class="modal fade" id="modalAdjust" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action"     value="adjust">
        <input type="hidden" name="product_id" id="aProductId">

        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Ajustar stock</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-info py-2 small mb-3">
            <strong id="aProductName"></strong> — Stock actual: <strong id="aCurrentStock"></strong> unidades
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Tipo de ajuste</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="adjust_type" id="aSet" value="set" checked>
              <label class="btn btn-outline-primary" for="aSet">Establecer</label>
              <input type="radio" class="btn-check" name="adjust_type" id="aAdd" value="add">
              <label class="btn btn-outline-success" for="aAdd">Agregar (+)</label>
              <input type="radio" class="btn-check" name="adjust_type" id="aSub" value="subtract">
              <label class="btn btn-outline-danger" for="aSub">Restar (−)</label>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Cantidad</label>
            <input type="number" class="form-control form-control-lg text-center fw-bold"
                   name="amount" id="aAmount" min="0" value="0" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Nivel de reorden</label>
            <input type="number" class="form-control" name="reorder_level" id="aReorder" min="0" value="5">
            <div class="form-text">Se generará alerta cuando el stock caiga por debajo de este valor.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Motivo / Nota</label>
            <input type="text" class="form-control" name="note"
                   placeholder="Ej: Recepción de mercancía, Ajuste de inventario…">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-accent"><i class="bi bi-save me-1"></i>Aplicar ajuste</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAdjust(id, name, current, reorder) {
  document.getElementById('aProductId').value          = id;
  document.getElementById('aProductName').textContent  = name;
  document.getElementById('aCurrentStock').textContent = current;
  document.getElementById('aAmount').value             = current;
  document.getElementById('aReorder').value            = reorder;
  document.getElementById('aSet').checked              = true;
  new bootstrap.Modal(document.getElementById('modalAdjust')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
