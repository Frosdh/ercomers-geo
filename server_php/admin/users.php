<?php
// ============================================================
//  Geo-Ecomers | Admin — Gestión de Usuarios
// ============================================================
$pageTitle = 'Usuarios';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_status' && $uid && $uid !== $_SESSION['user_id']) {
        $cur = $db->prepare('SELECT status FROM users WHERE id = ?'); $cur->execute([$uid]);
        $s   = $cur->fetchColumn();
        $new = $s === 'active' ? 'inactive' : 'active';
        $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $uid]);
        setFlash('success', 'Estado del usuario actualizado.');
        header('Location: ' . BASE_URL . '/admin/users.php'); exit;
    }
    if ($action === 'change_role' && $uid && $uid !== $_SESSION['user_id']) {
        $role = sanitize($_POST['role'] ?? 'customer');
        $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $uid]);
        setFlash('success', 'Rol actualizado.'); header('Location: ' . BASE_URL . '/admin/users.php'); exit;
    }
}

$search = sanitize($_GET['q']    ?? '');
$role   = sanitize($_GET['role'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where  = ['1=1']; $params = [];
if ($search) { $where[] = '(name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($role)   { $where[] = 'role = ?'; $params[] = $role; }
$whereSQL = implode(' AND ', $where);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $whereSQL"); $cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pag   = paginate($total, $perPage, $page);

$stmt = $db->prepare(
    "SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count
     FROM users u WHERE $whereSQL ORDER BY u.created_at DESC
     LIMIT {$pag['perPage']} OFFSET {$pag['offset']}"
);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row g-0">
  <?php require_once __DIR__ . '/sidebar.php'; ?>
  <div class="col ps-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Usuarios</h3>
    </div>

    <form method="GET" class="row g-2 mb-4">
      <div class="col-md-5">
        <input type="text" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Buscar por nombre o email...">
      </div>
      <div class="col-md-3">
        <select class="form-select" name="role">
          <option value="">Todos los roles</option>
          <option value="customer" <?= $role==='customer'?'selected':'' ?>>Clientes</option>
          <option value="admin"    <?= $role==='admin'   ?'selected':'' ?>>Admins</option>
          <option value="seller"   <?= $role==='seller'  ?'selected':'' ?>>Vendedores</option>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary">Limpiar</a>
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
          <thead class="table-light">
            <tr><th>Usuario</th><th>Email</th><th>Rol</th><th>Pedidos</th><th>Estado</th><th>Registro</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary rounded-circle text-white d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:36px;height:36px">
                      <?= strtoupper(substr($u['name'], 0, 1)) ?>
                    </div>
                    <span class="fw-semibold"><?= e($u['name']) ?></span>
                  </div>
                </td>
                <td><?= e($u['email']) ?></td>
                <td>
                  <span class="badge bg-<?= $u['role']==='admin'?'danger':($u['role']==='seller'?'warning text-dark':'primary') ?>">
                    <?= ucfirst($u['role']) ?>
                  </span>
                </td>
                <td><?= $u['order_count'] ?></td>
                <td>
                  <span class="badge bg-<?= $u['status']==='active'?'success':($u['status']==='banned'?'danger':'secondary') ?>">
                    <?= ucfirst($u['status']) ?>
                  </span>
                </td>
                <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <!-- Cambiar estado -->
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action"  value="toggle_status">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-<?= $u['status']==='active'?'danger':'success' ?> me-1"
                              onclick="return confirm('¿Cambiar estado?')">
                        <i class="bi bi-<?= $u['status']==='active'?'person-x':'person-check' ?>"></i>
                      </button>
                    </form>
                    <!-- Cambiar rol -->
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action"  value="change_role">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <select name="role" class="form-select form-select-sm d-inline-block w-auto"
                              onchange="this.form.submit()">
                        <option value="customer" <?= $u['role']==='customer'?'selected':'' ?>>Cliente</option>
                        <option value="seller"   <?= $u['role']==='seller'  ?'selected':'' ?>>Vendedor</option>
                        <option value="admin"    <?= $u['role']==='admin'   ?'selected':'' ?>>Admin</option>
                      </select>
                    </form>
                  <?php else: ?>
                    <span class="text-muted small">Tú</span>
                  <?php endif; ?>
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
                <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&role=<?= e($role) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
          </ul></nav>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
