<?php
// ============================================================
//  Geo-Ecomers | Perfil de Usuario
// ============================================================
$pageTitle = 'Mi perfil';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$db   = getDB();
$user = currentUser();

$errors  = [];
$success = '';

// Actualizar datos personales
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = sanitize($_POST['name']  ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        if (strlen($name) < 2) $errors[] = 'El nombre debe tener al menos 2 caracteres.';
        if (empty($errors)) {
            $db->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')
               ->execute([$name, $phone ?: null, $_SESSION['user_id']]);
            setFlash('success', 'Perfil actualizado correctamente.');
            header('Location: ' . BASE_URL . '/profile.php');
            exit;
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password'] ?? '')) {
            // Recargar user con password
            $uStmt = $db->prepare('SELECT password FROM users WHERE id = ?');
            $uStmt->execute([$_SESSION['user_id']]);
            $pw = $uStmt->fetchColumn();
            if (!password_verify($current, $pw)) $errors[] = 'La contraseña actual es incorrecta.';
        }
        if (strlen($new) < 8)  $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        if ($new !== $confirm) $errors[] = 'Las contraseñas no coinciden.';
        if (empty($errors)) {
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')
               ->execute([password_hash($new, PASSWORD_BCRYPT), $_SESSION['user_id']]);
            setFlash('success', 'Contraseña cambiada correctamente.');
            header('Location: ' . BASE_URL . '/profile.php');
            exit;
        }
    }

    if ($action === 'add_address') {
        $fullName = sanitize($_POST['full_name']     ?? '');
        $line1    = sanitize($_POST['address_line1'] ?? '');
        $city     = sanitize($_POST['city']          ?? '');
        $state    = sanitize($_POST['state']         ?? '');
        $postal   = sanitize($_POST['postal_code']   ?? '');
        $phone    = sanitize($_POST['addr_phone']    ?? '');
        $country  = sanitize($_POST['country']       ?? 'Ecuador');
        $isDefault = !empty($_POST['is_default']);

        if (!$fullName) $errors[] = 'El nombre es requerido.';
        if (!$line1)    $errors[] = 'La dirección es requerida.';
        if (!$city)     $errors[] = 'La ciudad es requerida.';

        if (empty($errors)) {
            if ($isDefault) {
                $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$_SESSION['user_id']]);
            }
            $db->prepare(
                'INSERT INTO addresses (user_id, full_name, phone, address_line1, city, state, postal_code, country, is_default)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$_SESSION['user_id'], $fullName, $phone, $line1, $city, $state, $postal, $country, $isDefault ? 1 : 0]);
            setFlash('success', 'Dirección guardada.');
            header('Location: ' . BASE_URL . '/profile.php#addresses');
            exit;
        }
    }

    if ($action === 'delete_address') {
        $db->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?')
           ->execute([(int)$_POST['address_id'], $_SESSION['user_id']]);
        setFlash('info', 'Dirección eliminada.');
        header('Location: ' . BASE_URL . '/profile.php#addresses');
        exit;
    }
}

// Recargar usuario actualizado
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Direcciones
$addresses = $db->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC');
$addresses->execute([$_SESSION['user_id']]);
$addresses = $addresses->fetchAll();

// Estadísticas
$stats = $db->prepare(
    "SELECT
       COUNT(*) AS total_orders,
       COALESCE(SUM(total), 0) AS total_spent,
       SUM(status = 'delivered') AS delivered
     FROM orders WHERE user_id = ?"
);
$stats->execute([$_SESSION['user_id']]);
$stats = $stats->fetch();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <!-- Sidebar de perfil -->
  <div class="col-md-4 col-lg-3">
    <div class="card p-4 text-center">
      <div class="bg-primary rounded-circle mx-auto d-flex align-items-center justify-content-center text-white mb-3"
           style="width:80px;height:80px;font-size:2rem">
        <?= strtoupper(substr($user['name'], 0, 1)) ?>
      </div>
      <h5 class="fw-bold mb-0"><?= e($user['name']) ?></h5>
      <p class="text-muted small mb-3"><?= e($user['email']) ?></p>
      <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?> mb-4">
        <?= ucfirst($user['role']) ?>
      </span>
      <div class="row text-center g-2">
        <div class="col-4">
          <div class="bg-light rounded p-2">
            <div class="fw-bold text-primary"><?= $stats['total_orders'] ?></div>
            <small class="text-muted" style="font-size:.7rem">Pedidos</small>
          </div>
        </div>
        <div class="col-4">
          <div class="bg-light rounded p-2">
            <div class="fw-bold text-success"><?= $stats['delivered'] ?></div>
            <small class="text-muted" style="font-size:.7rem">Entregados</small>
          </div>
        </div>
        <div class="col-4">
          <div class="bg-light rounded p-2">
            <div class="fw-bold text-warning" style="font-size:.85rem"><?= money($stats['total_spent']) ?></div>
            <small class="text-muted" style="font-size:.7rem">Gastado</small>
          </div>
        </div>
      </div>
    </div>

    <div class="list-group mt-3">
      <a href="#datos"      class="list-group-item list-group-item-action"><i class="bi bi-person me-2"></i>Mis datos</a>
      <a href="#password"   class="list-group-item list-group-item-action"><i class="bi bi-lock me-2"></i>Contraseña</a>
      <a href="#addresses"  class="list-group-item list-group-item-action"><i class="bi bi-geo-alt me-2"></i>Direcciones</a>
      <a href="<?= BASE_URL ?>/orders.php" class="list-group-item list-group-item-action"><i class="bi bi-box-seam me-2"></i>Mis pedidos</a>
    </div>
  </div>

  <!-- Contenido principal -->
  <div class="col-md-8 col-lg-9">

    <!-- Datos personales -->
    <div class="card p-4 mb-4" id="datos">
      <h5 class="fw-bold mb-4"><i class="bi bi-person me-2 text-primary"></i>Mis datos personales</h5>
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre completo</label>
            <input type="text" class="form-control" name="name" value="<?= e($user['name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Teléfono</label>
            <input type="tel" class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
            <small class="text-muted">El email no se puede cambiar.</small>
          </div>
        </div>
        <button type="submit" class="btn btn-primary mt-3">
          <i class="bi bi-check-lg me-1"></i>Guardar cambios
        </button>
      </form>
    </div>

    <!-- Cambiar contraseña -->
    <div class="card p-4 mb-4" id="password">
      <h5 class="fw-bold mb-4"><i class="bi bi-lock me-2 text-primary"></i>Cambiar contraseña</h5>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Contraseña actual</label>
            <input type="password" class="form-control" name="current_password" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" class="form-control" name="new_password" required minlength="8">
          </div>
          <div class="col-md-4">
            <label class="form-label">Confirmar nueva</label>
            <input type="password" class="form-control" name="confirm_password" required>
          </div>
        </div>
        <button type="submit" class="btn btn-warning mt-3">
          <i class="bi bi-shield-lock me-1"></i>Cambiar contraseña
        </button>
      </form>
    </div>

    <!-- Direcciones -->
    <div class="card p-4" id="addresses">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0"><i class="bi bi-geo-alt me-2 text-primary"></i>Mis direcciones</h5>
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newAddressForm">
          <i class="bi bi-plus me-1"></i>Nueva
        </button>
      </div>

      <!-- Formulario nueva dirección (colapsable) -->
      <div class="collapse mb-4" id="newAddressForm">
        <div class="border rounded p-3">
          <h6 class="fw-semibold mb-3">Agregar nueva dirección</h6>
          <form method="POST">
            <input type="hidden" name="action" value="add_address">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nombre completo *</label>
                <input type="text" class="form-control" name="full_name" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Teléfono</label>
                <input type="tel" class="form-control" name="addr_phone">
              </div>
              <div class="col-12">
                <label class="form-label">Dirección *</label>
                <input type="text" class="form-control" name="address_line1" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Ciudad *</label>
                <input type="text" class="form-control" name="city" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Provincia</label>
                <input type="text" class="form-control" name="state">
              </div>
              <div class="col-md-4">
                <label class="form-label">Código postal</label>
                <input type="text" class="form-control" name="postal_code">
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" name="is_default" id="isDefault">
                  <label class="form-check-label" for="isDefault">Establecer como predeterminada</label>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">
              <i class="bi bi-save me-1"></i>Guardar dirección
            </button>
          </form>
        </div>
      </div>

      <?php if (empty($addresses)): ?>
        <p class="text-muted">No tienes direcciones guardadas.</p>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($addresses as $addr): ?>
            <div class="col-md-6">
              <div class="border rounded p-3 h-100 <?= $addr['is_default'] ? 'border-primary' : '' ?>">
                <?php if ($addr['is_default']): ?>
                  <span class="badge bg-primary mb-2">Predeterminada</span>
                <?php endif; ?>
                <p class="mb-1 fw-semibold"><?= e($addr['full_name']) ?></p>
                <p class="mb-1 small"><?= e($addr['address_line1']) ?></p>
                <p class="mb-2 small text-muted"><?= e($addr['city']) ?><?= $addr['state'] ? ', ' . e($addr['state']) : '' ?>, <?= e($addr['country']) ?></p>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action"     value="delete_address">
                  <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm"
                          onclick="return confirm('¿Eliminar esta dirección?')">
                    <i class="bi bi-trash me-1"></i>Eliminar
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
