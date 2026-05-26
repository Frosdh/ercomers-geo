<?php
// ============================================================
//  Geo-Ecomers | Admin — Cuentas Bancarias
// ============================================================
$pageTitle = 'Cuentas Bancarias';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db     = getDB();
$errors = [];
$success = '';

// ── Acciones POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Eliminar ──────────────────────────────────────────────
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM bank_accounts WHERE id = ?')->execute([$id]);
        setFlash('success', 'Cuenta eliminada correctamente.');
        header('Location: bank_accounts.php');
        exit;
    }

    // ── Crear / Editar ────────────────────────────────────────
    if (in_array($action, ['create', 'edit'])) {
        $id            = (int)($_POST['id'] ?? 0);
        $bank_name     = sanitize($_POST['bank_name']     ?? '');
        $account_name  = sanitize($_POST['account_name']  ?? '');
        $account_number= sanitize($_POST['account_number'] ?? '');
        $account_type  = sanitize($_POST['account_type']  ?? '');
        $routing_number= sanitize($_POST['routing_number'] ?? '');
        $swift_code    = sanitize($_POST['swift_code']    ?? '');
        $currency      = sanitize($_POST['currency']      ?? 'USD');
        $notes         = sanitize($_POST['notes']         ?? '');
        $is_active     = isset($_POST['is_active']) ? 1 : 0;

        if (!$bank_name)      $errors[] = 'El nombre del banco es obligatorio.';
        if (!$account_name)   $errors[] = 'El titular de la cuenta es obligatorio.';
        if (!$account_number) $errors[] = 'El número de cuenta es obligatorio.';

        if (empty($errors)) {
            if ($action === 'create') {
                $db->prepare(
                    'INSERT INTO bank_accounts (bank_name, account_name, account_number, account_type, routing_number, swift_code, currency, notes, is_active)
                     VALUES (?,?,?,?,?,?,?,?,?)'
                )->execute([$bank_name, $account_name, $account_number, $account_type, $routing_number ?: null, $swift_code ?: null, $currency, $notes ?: null, $is_active]);
                setFlash('success', 'Cuenta bancaria creada correctamente.');
            } else {
                $db->prepare(
                    'UPDATE bank_accounts SET bank_name=?, account_name=?, account_number=?, account_type=?, routing_number=?, swift_code=?, currency=?, notes=?, is_active=? WHERE id=?'
                )->execute([$bank_name, $account_name, $account_number, $account_type, $routing_number ?: null, $swift_code ?: null, $currency, $notes ?: null, $is_active, $id]);
                setFlash('success', 'Cuenta bancaria actualizada correctamente.');
            }
            header('Location: bank_accounts.php');
            exit;
        }
    }
}

// ── Cargar cuentas ────────────────────────────────────────────
$accounts = $db->query('SELECT * FROM bank_accounts ORDER BY is_active DESC, id DESC')->fetchAll();

// ── Cuenta a editar (si viene ?edit=ID) ───────────────────────
$editAccount = null;
if (!empty($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM bank_accounts WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editAccount = $stmt->fetch() ?: null;
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row g-0">
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <div class="col ps-4 pt-2">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0"><i class="bi bi-bank me-2"></i>Cuentas Bancarias</h3>
      <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#modalBankAccount"
              onclick="resetForm()">
        <i class="bi bi-plus-lg me-1"></i>Nueva cuenta
      </button>
    </div>

    <?= showFlash() ?>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <!-- ── Tabla de cuentas ──────────────────────────────────── -->
    <div class="card">
      <div class="card-body p-0">
        <?php if (empty($accounts)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-bank fs-1 d-block mb-2"></i>
            <p>No hay cuentas bancarias registradas.</p>
            <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#modalBankAccount" onclick="resetForm()">
              <i class="bi bi-plus-lg me-1"></i>Agregar primera cuenta
            </button>
          </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Banco</th>
                <th>Titular</th>
                <th>N° Cuenta</th>
                <th>Tipo</th>
                <th>Moneda</th>
                <th>SWIFT / Routing</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($accounts as $acc): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= e($acc['bank_name']) ?></div>
                  <?php if ($acc['notes']): ?>
                    <small class="text-muted"><?= e($acc['notes']) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= e($acc['account_name']) ?></td>
                <td>
                  <code><?= e($acc['account_number']) ?></code>
                  <button class="btn btn-sm btn-link p-0 ms-1" title="Copiar"
                          onclick="navigator.clipboard.writeText('<?= e($acc['account_number']) ?>')">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </td>
                <td><span class="badge bg-secondary"><?= e($acc['account_type'] ?: '—') ?></span></td>
                <td><?= e($acc['currency']) ?></td>
                <td class="small text-muted">
                  <?= $acc['swift_code']    ? '<div>SWIFT: ' . e($acc['swift_code'])    . '</div>' : '' ?>
                  <?= $acc['routing_number'] ? '<div>Routing: ' . e($acc['routing_number']) . '</div>' : '' ?>
                  <?= (!$acc['swift_code'] && !$acc['routing_number']) ? '—' : '' ?>
                </td>
                <td>
                  <?php if ($acc['is_active']): ?>
                    <span class="badge bg-success">Activa</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Inactiva</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary me-1"
                          onclick="editAccount(<?= htmlspecialchars(json_encode($acc), ENT_QUOTES) ?>)">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <form method="POST" class="d-inline"
                        onsubmit="return confirm('¿Eliminar esta cuenta bancaria?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $acc['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Info box ───────────────────────────────────────────── -->
    <div class="alert alert-info mt-4">
      <i class="bi bi-info-circle me-2"></i>
      Las cuentas activas se muestran automáticamente a los clientes cuando eligen <strong>transferencia bancaria</strong> durante el pago.
    </div>
  </div>
</div>

<!-- ── Modal Crear / Editar ───────────────────────────────────── -->
<div class="modal fade" id="modalBankAccount" tabindex="-1" aria-labelledby="modalBankAccountLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="formBankAccount">
        <input type="hidden" name="action" id="formAction" value="create">
        <input type="hidden" name="id"     id="formId"     value="">

        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="modalBankAccountLabel">
            <i class="bi bi-bank me-2"></i><span id="modalTitle">Nueva cuenta bancaria</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nombre del banco <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="bank_name" id="fBankName" required
                     placeholder="Ej: Banco Pichincha">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Titular de la cuenta <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="account_name" id="fAccountName" required
                     placeholder="Ej: Corporativo GeoEcomers S.A.">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Número de cuenta <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="account_number" id="fAccountNumber" required
                     placeholder="Ej: 2200123456789">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tipo de cuenta</label>
              <select class="form-select" name="account_type" id="fAccountType">
                <option value="">— Seleccionar —</option>
                <option value="Corriente">Corriente</option>
                <option value="Ahorros">Ahorros</option>
                <option value="Checking">Checking</option>
                <option value="Savings">Savings</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Moneda</label>
              <select class="form-select" name="currency" id="fCurrency">
                <option value="USD">USD — Dólar</option>
                <option value="EUR">EUR — Euro</option>
                <option value="COP">COP — Peso colombiano</option>
                <option value="PEN">PEN — Sol peruano</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Código SWIFT</label>
              <input type="text" class="form-control" name="swift_code" id="fSwift"
                     placeholder="Ej: PICHECEQ">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Routing / CCI</label>
              <input type="text" class="form-control" name="routing_number" id="fRouting"
                     placeholder="Ej: 021000021">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Notas internas</label>
              <textarea class="form-control" name="notes" id="fNotes" rows="2"
                        placeholder="Ej: Cuenta principal para transferencias nacionales"></textarea>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="fIsActive" value="1" checked>
                <label class="form-check-label" for="fIsActive">Cuenta activa (visible para clientes)</label>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-accent"><i class="bi bi-save me-1"></i>Guardar cuenta</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
  document.getElementById('formAction').value     = 'create';
  document.getElementById('formId').value         = '';
  document.getElementById('modalTitle').textContent = 'Nueva cuenta bancaria';
  document.getElementById('formBankAccount').reset();
  document.getElementById('fIsActive').checked = true;
}

function editAccount(acc) {
  document.getElementById('formAction').value      = 'edit';
  document.getElementById('formId').value          = acc.id;
  document.getElementById('modalTitle').textContent = 'Editar cuenta bancaria';
  document.getElementById('fBankName').value       = acc.bank_name     || '';
  document.getElementById('fAccountName').value    = acc.account_name  || '';
  document.getElementById('fAccountNumber').value  = acc.account_number|| '';
  document.getElementById('fAccountType').value    = acc.account_type  || '';
  document.getElementById('fCurrency').value       = acc.currency      || 'USD';
  document.getElementById('fSwift').value          = acc.swift_code    || '';
  document.getElementById('fRouting').value        = acc.routing_number|| '';
  document.getElementById('fNotes').value          = acc.notes         || '';
  document.getElementById('fIsActive').checked     = acc.is_active == 1;

  const modal = new bootstrap.Modal(document.getElementById('modalBankAccount'));
  modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
