<?php
// ============================================================
//  Geo-Ecomers | Admin — Gestión de Productos
// ============================================================
$pageTitle = 'Productos';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_product') {
        $pid    = (int)($_POST['product_id'] ?? 0);
        $name   = sanitize($_POST['name']   ?? '');
        $catId  = (int)($_POST['category_id'] ?? 0);
        $price  = (float)($_POST['price']  ?? 0);
        $cmpPrc = (float)($_POST['compare_price'] ?? 0) ?: null;
        $status = sanitize($_POST['status'] ?? 'draft');
        $desc   = sanitize($_POST['description'] ?? '');
        $short  = sanitize($_POST['short_description'] ?? '');
        $sku    = sanitize($_POST['sku'] ?? '') ?: 'GEO-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $featured = !empty($_POST['is_featured']) ? 1 : 0;
        $slug   = slugify($name) . '-' . time();
        $stock  = (int)($_POST['stock'] ?? 0);
        $imageUrl = sanitize($_POST['image_url'] ?? '');

        if ($pid) {
            $db->prepare(
                'UPDATE products SET name=?, category_id=?, price=?, compare_price=?, status=?,
                 description=?, short_description=?, is_featured=?, updated_at=NOW() WHERE id=?'
            )->execute([$name, $catId, $price, $cmpPrc, $status, $desc, $short, $featured, $pid]);
            // Actualizar stock
            $existInv = $db->prepare('SELECT id FROM inventory WHERE product_id = ? AND variant_id IS NULL');
            $existInv->execute([$pid]);
            if ($existInv->fetch()) {
                $db->prepare('UPDATE inventory SET quantity=? WHERE product_id=? AND variant_id IS NULL')->execute([$stock, $pid]);
            } else {
                $db->prepare('INSERT INTO inventory (product_id, quantity) VALUES (?,?)')->execute([$pid, $stock]);
            }
            // Imagen primaria
            if ($imageUrl) {
                $db->prepare('UPDATE product_images SET is_primary=0 WHERE product_id=?')->execute([$pid]);
                $exists = $db->prepare('SELECT id FROM product_images WHERE product_id=? AND image_url=? LIMIT 1');
                $exists->execute([$pid, $imageUrl]);
                if (!$exists->fetch()) {
                    $db->prepare('INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?,?,1)')->execute([$pid, $imageUrl]);
                } else {
                    $db->prepare('UPDATE product_images SET is_primary=1 WHERE product_id=? AND image_url=?')->execute([$pid, $imageUrl]);
                }
            }
            setFlash('success', 'Producto actualizado.');
        } else {
            $db->prepare(
                'INSERT INTO products (name, slug, sku, category_id, price, compare_price, status, description, short_description, is_featured)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([$name, $slug, $sku, $catId, $price, $cmpPrc, $status, $desc, $short, $featured]);
            $pid = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO inventory (product_id, quantity) VALUES (?,?)')->execute([$pid, $stock]);
            if ($imageUrl) {
                $db->prepare('INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?,?,1)')->execute([$pid, $imageUrl]);
            }
            setFlash('success', 'Producto creado exitosamente.');
        }
        header('Location: ' . BASE_URL . '/admin/products.php');
        exit;
    }

    if ($action === 'delete_product') {
        $db->prepare('UPDATE products SET status="inactive" WHERE id=?')->execute([(int)$_POST['product_id']]);
        setFlash('warning', 'Producto desactivado.');
        header('Location: ' . BASE_URL . '/admin/products.php');
        exit;
    }
}

// Filtros
$search  = sanitize($_GET['q']   ?? '');
$status  = sanitize($_GET['s']   ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
if ($catFilter) { $where[] = 'p.category_id = ?'; $params[] = $catFilter; }
$whereSQL = implode(' AND ', $where);

$total   = (int)$db->prepare("SELECT COUNT(*) FROM products p WHERE $whereSQL")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereSQL")->execute($params) : 0;
$cntStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE $whereSQL"); $cntStmt->execute($params); $total = (int)$cntStmt->fetchColumn();
$pag     = paginate($total, $perPage, $page);

$prodStmt = $db->prepare(
    "SELECT p.id, p.name, p.sku, p.price, p.status, p.is_featured, c.name AS category,
            (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image,
            COALESCE((SELECT quantity FROM inventory WHERE product_id=p.id AND variant_id IS NULL LIMIT 1),0) AS stock
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE $whereSQL ORDER BY p.created_at DESC LIMIT {$pag['perPage']} OFFSET {$pag['offset']}"
);
$prodStmt->execute($params);
$products = $prodStmt->fetchAll();

$categories = $db->query('SELECT id, name FROM categories WHERE status="active" ORDER BY name')->fetchAll();

// Producto a editar
$editProduct = null;
if (!empty($_GET['edit'])) {
    $editStmt = $db->prepare('SELECT * FROM products WHERE id = ?'); $editStmt->execute([(int)$_GET['edit']]);
    $editProduct = $editStmt->fetch();
    if ($editProduct) {
        $imgStmt = $db->prepare('SELECT image_url FROM product_images WHERE product_id=? AND is_primary=1 LIMIT 1'); $imgStmt->execute([$editProduct['id']]);
        $editProduct['image_url'] = $imgStmt->fetchColumn() ?: '';
        $stkStmt = $db->prepare('SELECT quantity FROM inventory WHERE product_id=? AND variant_id IS NULL LIMIT 1'); $stkStmt->execute([$editProduct['id']]);
        $editProduct['stock'] = (int)$stkStmt->fetchColumn();
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="row g-0">
  <?php require_once __DIR__ . '/sidebar.php'; ?>
  <div class="col ps-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0"><i class="bi bi-box-seam me-2"></i>Productos</h3>
      <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#productModal">
        <i class="bi bi-plus-lg me-1"></i>Nuevo producto
      </button>
    </div>

    <!-- Filtros -->
    <form method="GET" class="row g-2 mb-4">
      <div class="col-md-4">
        <input type="text" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Buscar por nombre o SKU...">
      </div>
      <div class="col-md-3">
        <select class="form-select" name="s">
          <option value="">Todos los estados</option>
          <option value="active"   <?= $status==='active'   ? 'selected':'' ?>>Activos</option>
          <option value="inactive" <?= $status==='inactive' ? 'selected':'' ?>>Inactivos</option>
          <option value="draft"    <?= $status==='draft'    ? 'selected':'' ?>>Borrador</option>
        </select>
      </div>
      <div class="col-md-3">
        <select class="form-select" name="cat">
          <option value="">Todas las categorías</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $catFilter===$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
        <a href="<?= BASE_URL ?>/admin/products.php" class="btn btn-outline-secondary">Limpiar</a>
      </div>
    </form>

    <!-- Tabla -->
    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Imagen</th><th>Nombre / SKU</th><th>Categoría</th>
              <th>Precio</th><th>Stock</th><th>Estado</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): ?>
              <tr>
                <td><img src="<?= $p['image'] ? e($p['image']) : BASE_URL.'/assets/img/no-image.svg' ?>"
                         style="width:50px;height:50px;object-fit:cover;border-radius:8px"></td>
                <td>
                  <span class="fw-semibold"><?= e(mb_strimwidth($p['name'],0,40,'…')) ?></span>
                  <?php if ($p['is_featured']): ?><span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i></span><?php endif; ?>
                  <br><small class="text-muted"><?= e($p['sku']) ?></small>
                </td>
                <td><?= e($p['category']) ?></td>
                <td class="fw-bold"><?= money($p['price']) ?></td>
                <td>
                  <span class="badge bg-<?= $p['stock'] == 0 ? 'danger' : ($p['stock'] <= 5 ? 'warning text-dark' : 'success') ?>">
                    <?= $p['stock'] ?>
                  </span>
                </td>
                <td>
                  <span class="badge bg-<?= $p['status']==='active'?'success':($p['status']==='draft'?'warning text-dark':'secondary') ?>">
                    <?= ucfirst($p['status']) ?>
                  </span>
                </td>
                <td>
                  <a href="<?= BASE_URL ?>/admin/products.php?edit=<?= $p['id'] ?>"
                     class="btn btn-sm btn-outline-primary me-1">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="POST" class="d-inline"
                        onsubmit="return confirm('¿Desactivar este producto?')">
                    <input type="hidden" name="action"     value="delete_product">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
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
                <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&s=<?= e($status) ?>&cat=<?= $catFilter ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
          </ul></nav>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal Nuevo / Editar Producto -->
<div class="modal fade" id="productModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action"     value="save_product">
        <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?? '' ?>">
        <div class="modal-header">
          <h5 class="modal-title"><?= $editProduct ? 'Editar producto' : 'Nuevo producto' ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Nombre *</label>
              <input type="text" class="form-control" name="name" required
                     value="<?= e($editProduct['name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">SKU</label>
              <input type="text" class="form-control" name="sku"
                     value="<?= e($editProduct['sku'] ?? '') ?>" placeholder="Auto">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Categoría *</label>
              <select class="form-select" name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= ($editProduct['category_id'] ?? 0)==$cat['id']?'selected':'' ?>>
                    <?= e($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Precio *</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0" class="form-control" name="price" required
                       value="<?= $editProduct['price'] ?? '' ?>">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Precio comparación</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0" class="form-control" name="compare_price"
                       value="<?= $editProduct['compare_price'] ?? '' ?>" placeholder="Tachado">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Stock</label>
              <input type="number" min="0" class="form-control" name="stock"
                     value="<?= $editProduct['stock'] ?? 0 ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Estado</label>
              <select class="form-select" name="status">
                <option value="active"   <?= ($editProduct['status']??'')=='active'  ?'selected':'' ?>>Activo</option>
                <option value="draft"    <?= ($editProduct['status']??'')=='draft'   ?'selected':'' ?>>Borrador</option>
                <option value="inactive" <?= ($editProduct['status']??'')=='inactive'?'selected':'' ?>>Inactivo</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">&nbsp;</label>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="is_featured" id="feat"
                       <?= !empty($editProduct['is_featured']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="feat">Producto destacado</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">URL de imagen principal</label>
              <input type="url" class="form-control" name="image_url"
                     value="<?= e($editProduct['image_url'] ?? '') ?>"
                     placeholder="https://…/imagen.jpg">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Descripción corta</label>
              <input type="text" class="form-control" name="short_description" maxlength="500"
                     value="<?= e($editProduct['short_description'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Descripción completa</label>
              <textarea class="form-control" name="description" rows="4"><?= e($editProduct['description'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-accent">
            <i class="bi bi-save me-1"></i><?= $editProduct ? 'Actualizar' : 'Crear producto' ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($editProduct): ?>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    new bootstrap.Modal(document.getElementById('productModal')).show();
  });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
