<?php
// ============================================================
//  Geo-Ecomers | Login
// ============================================================
$pageTitle = 'Iniciar sesión';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $error = 'Por favor completa todos los campos.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name, password, role, status FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password'])) {
            $error = 'Email o contraseña incorrectos.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Tu cuenta está desactivada. Contacta al soporte.';
        } else {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            // Log activity
            $db->prepare(
                'INSERT INTO activity_log (user_id, action, ip_address, user_agent) VALUES (?,?,?,?)'
            )->execute([$user['id'], 'login', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);

            $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-md-5 col-lg-4">
    <div class="card p-4 p-md-5">
      <div class="text-center mb-4">
        <i class="bi bi-shield-lock-fill text-primary" style="font-size:2.5rem"></i>
        <h3 class="fw-bold mt-2">Iniciar sesión</h3>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <div class="mb-3">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" class="form-control" name="email"
                 value="<?= e($email) ?>" required autofocus
                 placeholder="tu@email.com">
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Contraseña</label>
          <div class="input-group">
            <input type="password" class="form-control" name="password"
                   id="passField" required>
            <button class="btn btn-outline-secondary" type="button"
                    onclick="togglePass()">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <div class="d-grid">
          <button type="submit" class="btn btn-accent btn-lg fw-semibold">
            <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
          </button>
        </div>
      </form>

      <hr class="my-4">
      <p class="text-center mb-0">¿No tienes cuenta?
        <a href="<?= BASE_URL ?>/auth/register.php" class="fw-semibold">Regístrate gratis</a>
      </p>
    </div>
  </div>
</div>

<script>
function togglePass() {
  const f = document.getElementById('passField');
  const i = document.getElementById('eyeIcon');
  if (f.type === 'password') {
    f.type = 'text'; i.className = 'bi bi-eye-slash';
  } else {
    f.type = 'password'; i.className = 'bi bi-eye';
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
