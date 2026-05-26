<?php
// ============================================================
//  Geo-Ecomers | Registro de Usuario
// ============================================================
$pageTitle = 'Crear cuenta';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    $dest = isAdmin() ? BASE_URL . '/admin/index.php' : BASE_URL . '/index.php';
    header('Location: ' . $dest);
    exit;
}

$errors = [];
$data   = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['name']  = sanitize($_POST['name']  ?? '');
    $data['email'] = sanitize($_POST['email'] ?? '');
    $data['phone'] = sanitize($_POST['phone'] ?? '');
    $pass          = $_POST['password']        ?? '';
    $pass2         = $_POST['password_confirm'] ?? '';

    if (strlen($data['name']) < 2)  $errors[] = 'El nombre debe tener al menos 2 caracteres.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
    if (strlen($pass) < 8)          $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    if ($pass !== $pass2)           $errors[] = 'Las contraseñas no coinciden.';

    if (empty($errors)) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Ya existe una cuenta con ese email.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $db->prepare(
                'INSERT INTO users (name, email, password, phone, role, status, email_verified_at)
                 VALUES (?, ?, ?, ?, "customer", "active", NOW())'
            )->execute([$data['name'], $data['email'], $hash, $data['phone'] ?: null]);

            $userId = (int) $db->lastInsertId();
            $_SESSION['user_id']   = $userId;
            $_SESSION['user_role'] = 'customer';

            setFlash('success', '¡Bienvenido/a, ' . $data['name'] . '! Tu cuenta fue creada.');
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card p-4 p-md-5">
      <div class="text-center mb-4">
        <i class="bi bi-person-plus-fill text-primary" style="font-size:2.5rem"></i>
        <h3 class="fw-bold mt-2">Crear cuenta</h3>
        <p class="text-muted small">Regístrate gratis y empieza a comprar</p>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?>
              <li><?= e($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <div class="mb-3">
          <label class="form-label fw-semibold">Nombre completo <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="name"
                 value="<?= e($data['name']) ?>" required autofocus
                 placeholder="Ej: María García">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
          <input type="email" class="form-control" name="email"
                 value="<?= e($data['email']) ?>" required
                 placeholder="tu@email.com">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Teléfono <span class="text-muted small">(opcional)</span></label>
          <input type="tel" class="form-control" name="phone"
                 value="<?= e($data['phone']) ?>"
                 placeholder="+593 99 000 0000">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="password" class="form-control" name="password"
                   id="pass1" required placeholder="Mínimo 8 caracteres">
            <button class="btn btn-outline-secondary" type="button"
                    onclick="togglePass('pass1', this)">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Confirmar contraseña <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="password" class="form-control" name="password_confirm"
                   id="pass2" required placeholder="Repite la contraseña">
            <button class="btn btn-outline-secondary" type="button"
                    onclick="togglePass('pass2', this)">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="d-grid">
          <button type="submit" class="btn btn-accent btn-lg fw-semibold">
            <i class="bi bi-person-check me-2"></i>Crear mi cuenta
          </button>
        </div>
      </form>

      <hr class="my-4">
      <p class="text-center mb-0">¿Ya tienes cuenta?
        <a href="<?= BASE_URL ?>/auth/login.php" class="fw-semibold">Inicia sesión</a>
      </p>
    </div>
  </div>
</div>

<script>
function togglePass(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
