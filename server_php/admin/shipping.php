<?php
// ============================================================
//  Geo-Ecomers | Admin — Gestión de Envíos
// ============================================================
$pageTitle = 'Envíos';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db  = getDB();
$tab = $_GET['tab'] ?? 'orders'; // orders | carriers | zones | rates

// ═══════════════════════════════════════════════════════════════
//  ACCIONES POST
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Actualizar seguimiento de un pedido ──────────────────
    if ($action === 'update_tracking') {
        $order_id       = (int)($_POST['order_id']       ?? 0);
        $tracking_number= sanitize($_POST['tracking_number'] ?? '');
        $carrier_id     = (int)($_POST['carrier_id']     ?? 0);
        $status         = sanitize($_POST['status']       ?? '');
        $note           = sanitize($_POST['note']         ?? '');

        // Obtener o crear shipment
        $stmt = $db->prepare('SELECT id FROM shipments WHERE order_id = ?');
        $stmt->execute([$order_id]);
        $shipment = $stmt->fetch();

        if ($shipment) {
            $db->prepare('UPDATE shipments SET tracking_number=?, carrier_id=?, status=? WHERE order_id=?')
               ->execute([$tracking_number ?: null, $carrier_id ?: null, $status, $order_id]);
            $shipment_id = $shipment['id'];
        } else {
            $db->prepare('INSERT INTO shipments (order_id, carrier_id, tracking_number, status) VALUES (?,?,?,?)')
               ->execute([$order_id, $carrier_id ?: null, $tracking_number ?: null, $status]);
            $shipment_id = (int)$db->lastInsertId();
        }

        // Agregar tracking event
        if ($note || $status) {
            $db->prepare('INSERT INTO shipment_tracking (shipment_id, status, note, created_at) VALUES (?,?,?,NOW())')
               ->execute([$shipment_id, $status, $note ?: null]);
        }

        // Actualizar estado del pedido si corresponde
        $orderStatus = match($status) {
            'shipped'   => 'shipped',
            'delivered' => 'delivered',
            'returned'  => 'refunded',
            default     => null,
        };
        if ($orderStatus) {
            $db->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$orderStatus, $order_id]);
        }

        setFlash('success', 'Seguimiento actualizado correctamente.');
        header('Location: shipping.php?tab=orders');
        exit;
    }

    // ── Carrier CRUD ─────────────────────────────────────────
    if ($action === 'save_carrier') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = sanitize($_POST['name']    ?? '');
        $code    = sanitize($_POST['code']    ?? '');
        $phone   = sanitize($_POST['phone']   ?? '');
        $website = sanitize($_POST['website'] ?? '');
        $active  = isset($_POST['is_active']) ? 1 : 0;

        if (!$name) { setFlash('danger','El nombre es obligatorio.'); header('Location: shipping.php?tab=carriers'); exit; }

        if ($id) {
            $db->prepare('UPDATE shipping_carriers SET name=?,code=?,phone=?,website=?,is_active=? WHERE id=?')
               ->execute([$name, $code ?: null, $phone ?: null, $website ?: null, $active, $id]);
        } else {
            $db->prepare('INSERT INTO shipping_carriers (name,code,phone,website,is_active) VALUES (?,?,?,?,?)')
               ->execute([$name, $code ?: null, $phone ?: null, $website ?: null, $active]);
        }
        setFlash('success','Transportista guardado.');
        header('Location: shipping.php?tab=carriers'); exit;
    }

    if ($action === 'delete_carrier') {
        $db->prepare('DELETE FROM shipping_carriers WHERE id=?')->execute([(int)$_POST['id']]);
        setFlash('success','Transportista eliminado.');
        header('Location: shipping.php?tab=carriers'); exit;
    }

    // ── Zone CRUD ────────────────────────────────────────────
    if ($action === 'save_zone') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = sanitize($_POST['name']        ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $countries   = sanitize($_POST['countries']   ?? '');

        if (!$name) { setFlash('danger','El nombre es obligatorio.'); header('Location: shipping.php?tab=zones'); exit; }

        if ($id) {
            $db->prepare('UPDATE shipping_zones SET name=?,description=?,countries=?,locations=? WHERE id=?')
               ->execute([$name, $description ?: null, $countries ?: null, $countries ?: null, $id]);
        } else {
            $db->prepare('INSERT INTO shipping_zones (name,description,countries,locations) VALUES (?,?,?,?)')
               ->execute([$name, $description ?: null, $countries ?: null, $countries ?: null]);
        }
        setFlash('success','Zona guardada.');
        header('Location: shipping.php?tab=zones'); exit;
    }

    if ($action === 'delete_zone') {
        $db->prepare('DELETE FROM shipping_zones WHERE id=?')->execute([(int)$_POST['id']]);
        setFlash('success','Zona eliminada.');
        header('Location: shipping.php?tab=zones'); exit;
    }

    // ── Rate CRUD ────────────────────────────────────────────
    if ($action === 'save_rate') {
        $id          = (int)($_POST['id'] ?? 0);
        $zone_id     = (int)($_POST['zone_id']     ?? 0);
        $carrier_id  = (int)($_POST['carrier_id']  ?? 0);
        $name        = sanitize($_POST['name']      ?? '');
        $price       = (float)($_POST['price']      ?? 0);
        $min_days    = (int)($_POST['min_days']     ?? 1);
        $max_days    = (int)($_POST['max_days']     ?? 7);
        $free_above  = ($_POST['free_above'] ?? '') !== '' ? (float)$_POST['free_above'] : null;
        $active      = isset($_POST['is_active']) ? 1 : 0;

        if (!$zone_id || !$name) { setFlash('danger','Zona y nombre son obligatorios.'); header('Location: shipping.php?tab=rates'); exit; }

        if ($id) {
            $db->prepare('UPDATE shipping_rates SET zone_id=?,carrier_id=?,name=?,base_cost=?,min_days=?,max_days=?,free_above=?,min_order_free_shipping=?,is_active=? WHERE id=?')
               ->execute([$zone_id, $carrier_id ?: null, $name, $price, $min_days, $max_days, $free_above, $free_above, $active, $id]);
        } else {
            $db->prepare('INSERT INTO shipping_rates (zone_id,carrier_id,name,base_cost,min_days,max_days,free_above,min_order_free_shipping,is_active) VALUES (?,?,?,?,?,?,?,?,?)')
               ->execute([$zone_id, $carrier_id ?: null, $name, $price, $min_days, $max_days, $free_above, $free_above, $active]);
        }
        setFlash('success','Tarifa guardada.');
        header('Location: shipping.php?tab=rates'); exit;
    }

    if ($action === 'delete_rate') {
        $db->prepare('DELETE FROM shipping_rates WHERE id=?')->execute([(int)$_POST['id']]);
        setFlash('success','Tarifa eliminada.');
        header('Location: shipping.php?tab=rates'); exit;
    }
}

// ═══════════════════════════════════════════════════════════════
//  DATOS
// ═══════════════════════════════════════════════════════════════

// Pedidos con envío
$orders = $db->query(
    "SELECT o.id, o.order_number, u.name AS customer, o.total, o.status, o.created_at,
            s.id AS shipment_id, s.tracking_number, s.status AS ship_status,
            sc.name AS carrier_name, sc.id AS carrier_id
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     LEFT JOIN shipments s ON s.order_id = o.id
     LEFT JOIN shipping_carriers sc ON sc.id = s.carrier_id
     WHERE o.status NOT IN ('cancelled','refunded')
     ORDER BY o.created_at DESC
     LIMIT 50"
)->fetchAll();

$carriers = $db->query('SELECT * FROM shipping_carriers ORDER BY name')->fetchAll();
$zones    = $db->query('SELECT *, COALESCE(countries, locations) AS countries FROM shipping_zones ORDER BY name')->fetchAll();
$rates    = $db->query(
    'SELECT r.*, r.base_cost AS price,
            COALESCE(r.min_days, r.estimated_days_min) AS min_days,
            COALESCE(r.max_days, r.estimated_days_max) AS max_days,
            COALESCE(r.free_above, r.min_order_free_shipping) AS free_above,
            z.name AS zone_name, c.name AS carrier_name
     FROM shipping_rates r
     LEFT JOIN shipping_zones z ON z.id = r.zone_id
     LEFT JOIN shipping_carriers c ON c.id = r.carrier_id
     ORDER BY z.name, r.base_cost'
)->fetchAll();

$orderStatusLabels = ['pending'=>'Pendiente','confirmed'=>'Confirmado','processing'=>'En proceso','shipped'=>'Enviado','delivered'=>'Entregado','cancelled'=>'Cancelado','refunded'=>'Reembolsado'];
$shipStatusLabels  = ['pending'=>'Pendiente','picked_up'=>'Recogido','in_transit'=>'En tránsito','out_for_delivery'=>'En reparto','delivered'=>'Entregado','failed'=>'Fallido','returned'=>'Devuelto'];
$shipStatusColors  = ['pending'=>'secondary','picked_up'=>'info','in_transit'=>'primary','out_for_delivery'=>'warning','delivered'=>'success','failed'=>'danger','returned'=>'dark'];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row g-0">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <div class="col ps-4 pt-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="fw-bold mb-0"><i class="bi bi-truck me-2"></i>Gestión de Envíos</h3>
    </div>

    <?= showFlash() ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
      <li class="nav-item"><a class="nav-link <?= $tab==='orders'   ? 'active' : '' ?>" href="?tab=orders"><i class="bi bi-cart3 me-1"></i>Pedidos</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab==='carriers' ? 'active' : '' ?>" href="?tab=carriers"><i class="bi bi-truck me-1"></i>Transportistas</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab==='zones'    ? 'active' : '' ?>" href="?tab=zones"><i class="bi bi-geo-alt me-1"></i>Zonas</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab==='rates'    ? 'active' : '' ?>" href="?tab=rates"><i class="bi bi-cash me-1"></i>Tarifas</a></li>
    </ul>

    <!-- ══════════════ TAB: PEDIDOS ══════════════ -->
    <?php if ($tab === 'orders'): ?>
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Total</th>
                <th>Estado pedido</th>
                <th>Transportista</th>
                <th>N° Seguimiento</th>
                <th>Estado envío</th>
                <th class="text-end">Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $ord): ?>
              <tr>
                <td><a href="orders.php?view=<?= $ord['id'] ?>" class="fw-semibold text-decoration-none"><?= e($ord['order_number']) ?></a></td>
                <td><?= e($ord['customer'] ?? '—') ?></td>
                <td><?= money($ord['total']) ?></td>
                <td><span class="badge bg-secondary"><?= $orderStatusLabels[$ord['status']] ?? $ord['status'] ?></span></td>
                <td><?= e($ord['carrier_name'] ?? '—') ?></td>
                <td>
                  <?php if ($ord['tracking_number']): ?>
                    <code><?= e($ord['tracking_number']) ?></code>
                  <?php else: ?>
                    <span class="text-muted small">Sin tracking</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php $ss = $ord['ship_status'] ?? 'pending'; ?>
                  <span class="badge bg-<?= $shipStatusColors[$ss] ?? 'secondary' ?>">
                    <?= $shipStatusLabels[$ss] ?? $ss ?>
                  </span>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary"
                          onclick="openTracking(<?= htmlspecialchars(json_encode($ord), ENT_QUOTES) ?>)">
                    <i class="bi bi-pencil-square me-1"></i>Actualizar
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal actualizar tracking -->
    <div class="modal fade" id="modalTracking" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="action"   value="update_tracking">
            <input type="hidden" name="order_id" id="tOrderId">
            <div class="modal-header">
              <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>Actualizar envío — <span id="tOrderNumber"></span></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label fw-semibold">Transportista</label>
                <select class="form-select" name="carrier_id" id="tCarrierId">
                  <option value="">— Sin asignar —</option>
                  <?php foreach ($carriers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Número de seguimiento</label>
                <input type="text" class="form-control" name="tracking_number" id="tTracking"
                       placeholder="Ej: EC123456789EC">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Estado del envío</label>
                <select class="form-select" name="status" id="tStatus">
                  <?php foreach ($shipStatusLabels as $val => $label): ?>
                    <option value="<?= $val ?>"><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Nota de seguimiento</label>
                <textarea class="form-control" name="note" rows="2"
                          placeholder="Ej: Paquete entregado al portero del edificio"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-accent"><i class="bi bi-save me-1"></i>Guardar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ══════════════ TAB: TRANSPORTISTAS ══════════════ -->
    <?php elseif ($tab === 'carriers'): ?>
    <div class="d-flex justify-content-end mb-3">
      <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#modalCarrier" onclick="resetCarrier()">
        <i class="bi bi-plus-lg me-1"></i>Nuevo transportista
      </button>
    </div>
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>Nombre</th><th>Código</th><th>Teléfono</th><th>Sitio web</th><th>Estado</th><th class="text-end">Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($carriers as $c): ?>
            <tr>
              <td class="fw-semibold"><?= e($c['name']) ?></td>
              <td><code><?= e($c['code'] ?? '—') ?></code></td>
              <td><?= e($c['phone'] ?? '—') ?></td>
              <td><?= $c['website'] ? '<a href="'.e($c['website']).'" target="_blank" class="small">'.e($c['website']).'</a>' : '—' ?></td>
              <td><span class="badge bg-<?= $c['is_active'] ? 'success' : 'secondary' ?>"><?= $c['is_active'] ? 'Activo' : 'Inactivo' ?></span></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary me-1"
                        onclick="editCarrier(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar transportista?')">
                  <input type="hidden" name="action" value="delete_carrier">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($carriers)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No hay transportistas registrados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal transportista -->
    <div class="modal fade" id="modalCarrier" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="action" value="save_carrier">
            <input type="hidden" name="id"     id="cId">
            <div class="modal-header">
              <h5 class="modal-title fw-bold" id="cModalTitle">Nuevo transportista</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-8">
                  <label class="form-label fw-semibold">Nombre *</label>
                  <input type="text" class="form-control" name="name" id="cName" required>
                </div>
                <div class="col-4">
                  <label class="form-label fw-semibold">Código</label>
                  <input type="text" class="form-control" name="code" id="cCode" placeholder="Ej: DHL">
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold">Teléfono</label>
                  <input type="text" class="form-control" name="phone" id="cPhone">
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold">Sitio web</label>
                  <input type="url" class="form-control" name="website" id="cWebsite" placeholder="https://">
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="cActive" value="1" checked>
                    <label class="form-check-label" for="cActive">Activo</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-accent"><i class="bi bi-save me-1"></i>Guardar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ══════════════ TAB: ZONAS ══════════════ -->
    <?php elseif ($tab === 'zones'): ?>
    <div class="d-flex justify-content-end mb-3">
      <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#modalZone" onclick="resetZone()">
        <i class="bi bi-plus-lg me-1"></i>Nueva zona
      </button>
    </div>
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>Nombre</th><th>Descripción</th><th>Países / regiones</th><th class="text-end">Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($zones as $z): ?>
            <tr>
              <td class="fw-semibold"><?= e($z['name']) ?></td>
              <td class="text-muted small"><?= e($z['description'] ?? '—') ?></td>
              <td class="small"><?= e($z['countries'] ?? '—') ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary me-1"
                        onclick="editZone(<?= htmlspecialchars(json_encode($z), ENT_QUOTES) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar zona?')">
                  <input type="hidden" name="action" value="delete_zone">
                  <input type="hidden" name="id" value="<?= $z['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($zones)): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">No hay zonas registradas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal zona -->
    <div class="modal fade" id="modalZone" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="action" value="save_zone">
            <input type="hidden" name="id"     id="zId">
            <div class="modal-header">
              <h5 class="modal-title fw-bold" id="zModalTitle">Nueva zona de envío</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label fw-semibold">Nombre *</label>
                  <input type="text" class="form-control" name="name" id="zName" required placeholder="Ej: Ecuador Costa">
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Descripción</label>
                  <input type="text" class="form-control" name="description" id="zDesc" placeholder="Breve descripción">
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Países / Regiones cubiertas</label>
                  <textarea class="form-control" name="countries" id="zCountries" rows="2"
                            placeholder="Ej: Ecuador, Colombia, Perú"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-accent"><i class="bi bi-save me-1"></i>Guardar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ══════════════ TAB: TARIFAS ══════════════ -->
    <?php elseif ($tab === 'rates'): ?>
    <div class="d-flex justify-content-end mb-3">
      <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#modalRate" onclick="resetRate()">
        <i class="bi bi-plus-lg me-1"></i>Nueva tarifa
      </button>
    </div>
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>Zona</th><th>Transportista</th><th>Nombre</th><th>Precio</th><th>Días</th><th>Gratis desde</th><th>Estado</th><th class="text-end">Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($rates as $r): ?>
            <tr>
              <td><?= e($r['zone_name'] ?? '—') ?></td>
              <td><?= e($r['carrier_name'] ?? '—') ?></td>
              <td class="fw-semibold"><?= e($r['name']) ?></td>
              <td><?= money($r['price']) ?></td>
              <td><?= $r['min_days'] ?>–<?= $r['max_days'] ?> días</td>
              <td><?= $r['free_above'] !== null ? money($r['free_above']) : '—' ?></td>
              <td><span class="badge bg-<?= $r['is_active'] ? 'success' : 'secondary' ?>"><?= $r['is_active'] ? 'Activa' : 'Inactiva' ?></span></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary me-1"
                        onclick="editRate(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar tarifa?')">
                  <input type="hidden" name="action" value="delete_rate">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rates)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No hay tarifas registradas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal tarifa -->
    <div class="modal fade" id="modalRate" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST">
            <input type="hidden" name="action" value="save_rate">
            <input type="hidden" name="id"     id="rId">
            <div class="modal-header">
              <h5 class="modal-title fw-bold" id="rModalTitle">Nueva tarifa de envío</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-6">
                  <label class="form-label fw-semibold">Zona *</label>
                  <select class="form-select" name="zone_id" id="rZoneId" required>
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($zones as $z): ?>
                      <option value="<?= $z['id'] ?>"><?= e($z['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold">Transportista</label>
                  <select class="form-select" name="carrier_id" id="rCarrierId">
                    <option value="">— Cualquiera —</option>
                    <?php foreach ($carriers as $c): ?>
                      <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Nombre de la tarifa *</label>
                  <input type="text" class="form-control" name="name" id="rName" required placeholder="Ej: Envío estándar">
                </div>
                <div class="col-4">
                  <label class="form-label fw-semibold">Precio</label>
                  <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" name="price" id="rPrice" step="0.01" min="0" value="0">
                  </div>
                </div>
                <div class="col-4">
                  <label class="form-label fw-semibold">Mín. días</label>
                  <input type="number" class="form-control" name="min_days" id="rMinDays" value="1" min="0">
                </div>
                <div class="col-4">
                  <label class="form-label fw-semibold">Máx. días</label>
                  <input type="number" class="form-control" name="max_days" id="rMaxDays" value="5" min="0">
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Envío gratis a partir de ($)</label>
                  <input type="number" class="form-control" name="free_above" id="rFreeAbove" step="0.01" min="0"
                         placeholder="Dejar vacío para no aplicar">
                </div>
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="rActive" value="1" checked>
                    <label class="form-check-label" for="rActive">Tarifa activa</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-accent"><i class="bi bi-save me-1"></i>Guardar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /col -->
</div><!-- /row -->

<script>
// ── Tracking ──────────────────────────────────────────────────
function openTracking(ord) {
  document.getElementById('tOrderId').value        = ord.id;
  document.getElementById('tOrderNumber').textContent = ord.order_number;
  document.getElementById('tTracking').value       = ord.tracking_number || '';
  document.getElementById('tStatus').value         = ord.ship_status    || 'pending';
  const carrierSel = document.getElementById('tCarrierId');
  if (carrierSel) carrierSel.value = ord.carrier_id || '';
  new bootstrap.Modal(document.getElementById('modalTracking')).show();
}

// ── Carrier ───────────────────────────────────────────────────
function resetCarrier() {
  document.getElementById('cId').value = '';
  document.getElementById('cModalTitle').textContent = 'Nuevo transportista';
  ['cName','cCode','cPhone','cWebsite'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('cActive').checked = true;
}
function editCarrier(c) {
  document.getElementById('cId').value               = c.id;
  document.getElementById('cModalTitle').textContent = 'Editar transportista';
  document.getElementById('cName').value    = c.name    || '';
  document.getElementById('cCode').value    = c.code    || '';
  document.getElementById('cPhone').value   = c.phone   || '';
  document.getElementById('cWebsite').value = c.website || '';
  document.getElementById('cActive').checked= c.is_active == 1;
  new bootstrap.Modal(document.getElementById('modalCarrier')).show();
}

// ── Zone ──────────────────────────────────────────────────────
function resetZone() {
  document.getElementById('zId').value = '';
  document.getElementById('zModalTitle').textContent = 'Nueva zona de envío';
  ['zName','zDesc','zCountries'].forEach(id => document.getElementById(id).value = '');
}
function editZone(z) {
  document.getElementById('zId').value               = z.id;
  document.getElementById('zModalTitle').textContent = 'Editar zona';
  document.getElementById('zName').value      = z.name        || '';
  document.getElementById('zDesc').value      = z.description || '';
  document.getElementById('zCountries').value = z.countries   || '';
  new bootstrap.Modal(document.getElementById('modalZone')).show();
}

// ── Rate ──────────────────────────────────────────────────────
function resetRate() {
  document.getElementById('rId').value = '';
  document.getElementById('rModalTitle').textContent = 'Nueva tarifa de envío';
  document.getElementById('rZoneId').value    = '';
  document.getElementById('rCarrierId').value = '';
  document.getElementById('rName').value      = '';
  document.getElementById('rPrice').value     = '0';
  document.getElementById('rMinDays').value   = '1';
  document.getElementById('rMaxDays').value   = '5';
  document.getElementById('rFreeAbove').value = '';
  document.getElementById('rActive').checked  = true;
}
function editRate(r) {
  document.getElementById('rId').value               = r.id;
  document.getElementById('rModalTitle').textContent = 'Editar tarifa';
  document.getElementById('rZoneId').value    = r.zone_id    || '';
  document.getElementById('rCarrierId').value = r.carrier_id || '';
  document.getElementById('rName').value      = r.name       || '';
  document.getElementById('rPrice').value     = r.price      || '0';
  document.getElementById('rMinDays').value   = r.min_days   || '1';
  document.getElementById('rMaxDays').value   = r.max_days   || '5';
  document.getElementById('rFreeAbove').value = r.free_above || '';
  document.getElementById('rActive').checked  = r.is_active == 1;
  new bootstrap.Modal(document.getElementById('modalRate')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
