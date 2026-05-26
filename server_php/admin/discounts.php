<?php
// ============================================================
//  Geo-Ecomers | Admin — Descuentos y Cupones
// ============================================================
$pageTitle = 'Descuentos';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();
$errors = [];

// ═══════════════════════════════════════════════════════════════
//  ACCIONES POST
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $db->prepare('DELETE FROM discounts WHERE id = ?')->execute([(int)$_POST['id']]);
        setFlash('success', 'Cupón eliminado.');
        header('Location: discounts.php'); exit;
    }

    if ($action === 'toggle') {
        $stmt = $db->prepare("SELECT COALESCE(is_active, IF(status='active',1,0)) FROM discounts WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $cur = (int)$stmt->fetchColumn();
        $newActive = $cur ? 0 : 1;
        $newStatus = $newActive ? 'active' : 'inactive';
        $db->prepare('UPDATE discounts SET is_active = ?, status = ? WHERE id = ?')
           ->execute([$newActive, $newStatus, (int)$_POST['id']]);
        setFlash('success', 'Estado actualizado.');
        header('Location: discounts.php'); exit;
    }

    if (in_array($action, ['create', 'edit'])) {
        $id           = (int)($_POST['id'] ?? 0);
        $code         = strtoupper(sanitize($_POST['code']          ?? ''));
        $description  = sanitize($_POST['description']  ?? '');
        $type         = sanitize($_POST['type']          ?? 'percentage');
        $value        = (float)($_POST['value']         ?? 0);
        $min_order    = $_POST['min_order'] !== '' ? (float)$_POST['min_order'] : null;
        $max_uses     = $_POST['max_uses']  !== '' ? (int)$_POST['max_uses']    : null;
        $per_user     = $_POST['per_user']  !== '' ? (int)$_POST['per_user']    : null;
        $starts_at    = $_POST['starts_at'] !== '' ? $_POST['starts_at']        : null;
        $expires_at   = $_POST['expires_at'] !== '' ? $_POST['expires_at']      : null;
        $is_active    = isset($_POST['is_active']) ? 1 : 0;
        $free_shipping= isset($_POST['free_shipping']) ? 1 : 0;

        if (!$code)                    $errors[] = 'El código del cupón es obligatorio.';
        if ($value <= 0)               $errors[] = 'El valor del descuento debe ser mayor que 0.';
        if ($type === 'percentage' && $value > 100) $errors[] = 'El porcentaje no puede superar 100%.';

        if (empty($errors)) {
            // Verificar código duplicado
            $chk = $db->prepare('SELECT id FROM discounts WHERE code = ? AND id != ?');
            $chk->execute([$code, $id]);
            if ($chk->fetch()) {
                $errors[] = "El código «{$code}» ya existe.";
            }
        }

        $statusStr = $is_active ? 'active' : 'inactive';
        if (empty($errors)) {
            if ($action === 'create') {
                $db->prepare(
                    'INSERT INTO discounts (code, name, description, type, value, min_order_amount, max_uses, uses_per_user, max_uses_per_user, free_shipping, starts_at, expires_at, is_active, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$code, $description ?: $code, $description ?: null, $type, $value, $min_order, $max_uses, $per_user, $per_user ?? 1, $free_shipping, $starts_at, $expires_at, $is_active, $statusStr]);
                setFlash('success', "Cupón «{$code}» creado.");
            } else {
                $db->prepare(
                    'UPDATE discounts SET code=?,name=?,description=?,type=?,value=?,min_order_amount=?,max_uses=?,uses_per_user=?,max_uses_per_user=?,free_shipping=?,starts_at=?,expires_at=?,is_active=?,status=? WHERE id=?'
                )->execute([$code, $description ?: $code, $description ?: null, $type, $value, $min_order, $max_uses, $per_user, $per_user ?? 1, $free_shipping, $starts_at, $expires_at, $is_active, $statusStr, $id]);
                setFlash('success', "Cupón «{$code}» actualizado.");
            }
            header('Location: discounts.php'); exit;
        }
    }
}

// ═══════════════════════════════════════════════════════════════
//  DATOS
// ═══════════════════════════════════════════════════════════════
$discounts = $db->query(
    "SELECT d.*,
       COALESCE(d.is_active, IF(d.status='active',1,0)) AS is_active,
       COALESCE(d.uses_per_user, d.max_uses_per_user)   AS uses_per_user,
       COALESCE(d.uses_count, 0)                         AS total_used
     FROM discounts d ORDER BY d.created_at DESC"
)->fetchAll();

$now = date('Y-m-d H:i:s');
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row g-0">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <div class="col ps-4 pt-2">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0"><i class="bi bi-tag me-2"></i>Descuentos y Cupones</h3>
      <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#modalDiscount" onclick="resetDiscount()">
        <i class="bi bi-plus-lg me-1"></i>Nuevo cupón
      </button>
    </div>

    <?= showFlash() ?>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <!-- Stats rápidas -->
    <div class="row g-3 mb-4">
      <?php
        $total   = count($discounts);
        $active  = count(array_filter($discounts, fn($d) => $d['is_active']));
        $expired = count(array_filter($discounts, fn($d) => $d['expires_at'] && $d['expires_at'] < $now));
        $totalUsed = array_sum(array_column($discounts, 'total_used'));
      ?>
      <div class="col-6 col-md-3">
        <div class="card p-3 text-center">
          <div class="fs-2 fw-bold text-primary"><?= $total ?></div>
          <div class="text-muted small">Total cupones</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card p-3 text-center">
          <div class="fs-2 fw-bold text-success"><?= $active ?></div>
          <div class="text-muted small">Activos</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card p-3 text-center">
          <div class="fs-2 fw-bold text-danger"><?= $expired ?></div>
          <div class="text-muted small">Vencidos</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card p-3 text-center">
          <div class="fs-2 fw-bold text-warning"><?= $totalUsed ?></div>
          <div class="text-muted small">Usos totales</div>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div class="card-body p-0">
        <?php if (empty($discounts)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-tag fs-1 d-block mb-2"></i>
            <p>No hay cupones registrados.</p>
            <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#modalDiscount" onclick="resetDiscount()">
              <i class="bi bi-plus-lg me-1"></i>Crear primer cupón
            </button>
          </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Código</th>
                <th>Tipo</th>
                <th>Valor</th>
                <th>Pedido mín.</th>
                <th>Usos</th>
                <th>Vigencia</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($discounts as $d):
                $isExpired   = $d['expires_at'] && $d['expires_at'] < $now;
                $notStarted  = $d['starts_at']  && $d['starts_at']  > $now;
                $usedUp      = $d['max_uses'] && $d['total_used'] >= $d['max_uses'];
              ?>
              <tr class="<?= (!$d['is_active'] || $isExpired || $usedUp) ? 'table-secondary' : '' ?>">
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <code class="fs-6 fw-bold"><?= e($d['code']) ?></code>
                    <button class="btn btn-sm btn-link p-0" title="Copiar"
                            onclick="navigator.clipboard.writeText('<?= e($d['code']) ?>')">
                      <i class="bi bi-clipboard"></i>
                    </button>
                  </div>
                  <?php if ($d['description']): ?>
                    <small class="text-muted"><?= e($d['description']) ?></small>
                  <?php endif; ?>
                  <?php if ($d['free_shipping']): ?>
                    <span class="badge bg-info ms-1"><i class="bi bi-truck me-1"></i>Envío gratis</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= $d['type'] === 'percentage' ? 'bg-primary' : 'bg-success' ?>">
                    <?= $d['type'] === 'percentage' ? 'Porcentaje' : 'Monto fijo' ?>
                  </span>
                </td>
                <td class="fw-semibold">
                  <?= $d['type'] === 'percentage' ? $d['value'] . '%' : money($d['value']) ?>
                </td>
                <td><?= $d['min_order_amount'] !== null ? money($d['min_order_amount']) : '—' ?></td>
                <td>
                  <?= $d['total_used'] ?>
                  <?php if ($d['max_uses']): ?>
                    / <?= $d['max_uses'] ?>
                    <?php if ($usedUp): ?>
                      <span class="badge bg-danger ms-1">Agotado</span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td class="small">
                  <?php if ($d['starts_at'] || $d['expires_at']): ?>
                    <?= $d['starts_at']  ? '<div>Desde: ' . date('d/m/Y', strtotime($d['starts_at']))  . '</div>' : '' ?>
                    <?= $d['expires_at'] ? '<div>Hasta: <span class="'.($isExpired ? 'text-danger fw-semibold' : '').'">' . date('d/m/Y', strtotime($d['expires_at'])) . '</span></div>' : '' ?>
                  <?php else: ?>
                    <span class="text-muted">Sin límite</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!$d['is_active']): ?>
                    <span class="badge bg-secondary">Inactivo</span>
                  <?php elseif ($isExpired): ?>
                    <span class="badge bg-danger">Vencido</span>
                  <?php elseif ($notStarted): ?>
                    <span class="badge bg-warning">Programado</span>
                  <?php elseif ($usedUp): ?>
                    <span class="badge bg-dark">Agotado</span>
                  <?php else: ?>
                    <span class="badge bg-success">Activo</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary"
                            onclick="editDiscount(<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= $d['id'] ?>">
                      <button class="btn btn-outline-secondary" title="<?= $d['is_active'] ? 'Desactivar' : 'Activar' ?>">
                        <i class="bi bi-<?= $d['is_active'] ? 'pause' : 'play' ?>-circle"></i>
                      </button>
                    </form>
                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este cupón?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $d['id'] ?>">
                      <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ══ Modal Crear / Editar ══ -->
<div class="modal fade" id="modalDiscount" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="formDiscount">
        <input type="hidden" name="action" id="dAction" value="create">
        <input type="hidden" name="id"     id="dId"     value="">

        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-tag me-2"></i><span id="dModalTitle">Nuevo cupón</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text" class="form-control text-uppercase fw-bold" name="code" id="dCode"
                       required placeholder="Ej: PROMO20" style="letter-spacing:.05em">
                <button type="button" class="btn btn-outline-secondary" onclick="genCode()">
                  <i class="bi bi-shuffle"></i>
                </button>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
              <select class="form-select" name="type" id="dType" onchange="toggleTypeLabel()">
                <option value="percentage">Porcentaje (%)</option>
                <option value="fixed">Monto fijo ($)</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Valor <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text" id="dTypeSymbol">%</span>
                <input type="number" class="form-control" name="value" id="dValue"
                       step="0.01" min="0.01" required value="10">
              </div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Descripción interna</label>
              <input type="text" class="form-control" name="description" id="dDesc"
                     placeholder="Ej: Descuento Black Friday 2024">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Pedido mínimo ($)</label>
              <input type="number" class="form-control" name="min_order" id="dMinOrder"
                     step="0.01" min="0" placeholder="Opcional">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Usos máximos totales</label>
              <input type="number" class="form-control" name="max_uses" id="dMaxUses"
                     min="1" placeholder="Ilimitado">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Usos por usuario</label>
              <input type="number" class="form-control" name="per_user" id="dPerUser"
                     min="1" placeholder="Ilimitado">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Válido desde</label>
              <input type="datetime-local" class="form-control" name="starts_at" id="dStartsAt">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Válido hasta</label>
              <input type="datetime-local" class="form-control" name="expires_at" id="dExpiresAt">
            </div>
            <div class="col-12 d-flex gap-4">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="dActive" value="1" checked>
                <label class="form-check-label" for="dActive">Cupón activo</label>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="free_shipping" id="dFreeShipping" value="1">
                <label class="form-check-label" for="dFreeShipping">Incluye envío gratis</label>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-accent"><i class="bi bi-save me-1"></i>Guardar cupón</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleTypeLabel() {
  const type = document.getElementById('dType').value;
  document.getElementById('dTypeSymbol').textContent = type === 'percentage' ? '%' : '$';
}

function genCode() {
  const chars = 'ABCDEFGHIJKLMNPQRSTUVWXYZ23456789';
  let code = '';
  for (let i = 0; i < 8; i++) code += chars[Math.floor(Math.random() * chars.length)];
  document.getElementById('dCode').value = code;
}

function resetDiscount() {
  document.getElementById('dAction').value         = 'create';
  document.getElementById('dId').value             = '';
  document.getElementById('dModalTitle').textContent = 'Nuevo cupón';
  document.getElementById('formDiscount').reset();
  document.getElementById('dTypeSymbol').textContent = '%';
  document.getElementById('dActive').checked = true;
}

function editDiscount(d) {
  document.getElementById('dAction').value           = 'edit';
  document.getElementById('dId').value               = d.id;
  document.getElementById('dModalTitle').textContent = 'Editar cupón';
  document.getElementById('dCode').value             = d.code         || '';
  document.getElementById('dType').value             = d.type         || 'percentage';
  document.getElementById('dValue').value            = d.value        || '';
  document.getElementById('dDesc').value             = d.description  || '';
  document.getElementById('dMinOrder').value         = d.min_order_amount || '';
  document.getElementById('dMaxUses').value          = d.max_uses     || '';
  document.getElementById('dPerUser').value          = d.uses_per_user || '';
  document.getElementById('dStartsAt').value         = d.starts_at   ? d.starts_at.replace(' ','T').slice(0,16)  : '';
  document.getElementById('dExpiresAt').value        = d.expires_at  ? d.expires_at.replace(' ','T').slice(0,16) : '';
  document.getElementById('dActive').checked         = d.is_active == 1;
  document.getElementById('dFreeShipping').checked   = d.free_shipping == 1;
  toggleTypeLabel();
  new bootstrap.Modal(document.getElementById('modalDiscount')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
