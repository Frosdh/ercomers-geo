<?php
// ============================================================
//  Geo-Ecomers | Página Principal — Catálogo de Productos
// ============================================================
$pageTitle = 'Inicio';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();

// Parámetros de filtro
$search   = sanitize($_GET['q']        ?? '');
$catId    = (int)($_GET['cat']         ?? 0);
$sort     = sanitize($_GET['sort']     ?? 'recent');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;

// Categorías para el menú
$categories = $db->query('SELECT id, name, slug FROM categories WHERE status="active" ORDER BY sort_order')->fetchAll();

// Construir query de productos
$where  = ['p.status = "active"'];
$params = [];

if ($search) {
    $where[]  = '(p.name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($catId) {
    $where[]  = 'p.category_id = ?';
    $params[] = $catId;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orderSQL = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular'    => 'p.views DESC',
    default      => 'p.created_at DESC',
};

// Total
$totalStmt = $db->prepare("SELECT COUNT(*) FROM products p $whereSQL");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();
$pag   = paginate($total, $perPage, $page);

// Productos
$stmt = $db->prepare(
    "SELECT p.id, p.name, p.slug, p.price, p.compare_price, p.short_description, p.is_featured,
            c.name AS category_name,
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image,
            COALESCE((SELECT quantity FROM inventory WHERE product_id = p.id AND variant_id IS NULL LIMIT 1), 0) AS stock
     FROM products p
     JOIN categories c ON c.id = p.category_id
     $whereSQL
     ORDER BY $orderSQL
     LIMIT {$pag['perPage']} OFFSET {$pag['offset']}"
);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Productos destacados (solo para home sin filtros)
$featured = [];
if (!$search && !$catId) {
    $featStmt = $db->query(
        "SELECT p.id, p.name, p.slug, p.price, p.compare_price,
                (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image
         FROM products p WHERE p.status='active' AND p.is_featured=1 LIMIT 4"
    );
    $featured = $featStmt->fetchAll();
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<?php if ($featured): ?>
<!-- ===== HERO / DESTACADOS ===== -->
<div class="p-5 mb-4 rounded-4 text-white" style="background:linear-gradient(135deg,#0d47a1,#1565c0)">
  <div class="row align-items-center">
    <div class="col-md-6">
      <h1 class="display-5 fw-bold">¡Bienvenido a <span style="color:#ffd54f">GeoEcomers</span>!</h1>
      <p class="lead">Encuentra los mejores productos con entrega a todo el Ecuador.</p>
      <a href="#productos" class="btn btn-warning btn-lg fw-semibold rounded-pill px-4">
        <i class="bi bi-bag-fill me-2"></i>Ver productos
      </a>
    </div>
    <div class="col-md-6 d-none d-md-flex gap-3 flex-wrap justify-content-center mt-3 mt-md-0">
      <?php foreach (array_slice($featured, 0, 4) as $fp): ?>
        <a href="<?= BASE_URL ?>/product.php?id=<?= $fp['id'] ?>"
           class="card text-dark text-decoration-none" style="width:140px">
          <img src="<?= $fp['image'] ? e($fp['image']) : BASE_URL . '/assets/img/no-image.svg' ?>"
               class="card-img-top" style="height:100px;object-fit:cover">
          <div class="card-body p-2">
            <p class="small mb-0 fw-semibold" style="font-size:.75rem"><?= e(mb_strimwidth($fp['name'],0,30,'…')) ?></p>
            <p class="mb-0 text-danger fw-bold small"><?= money($fp['price']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ===== FILTROS ===== -->
<div class="row g-3 mb-4 align-items-end" id="productos">
  <div class="col-12">
    <h2 class="section-title mb-3">
      <?= $search ? 'Resultados para: <em>' . e($search) . '</em>' : ($catId ? 'Categoría' : 'Todos los productos') ?>
      <small class="text-muted fs-6 fw-normal ms-2">(<?= $total ?> productos)</small>
    </h2>
  </div>

  <!-- Categorías pill -->
  <div class="col-12">
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= BASE_URL ?>/index.php<?= $search ? '?q='.urlencode($search) : '' ?>"
         class="btn btn-sm <?= !$catId ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill">
        Todas
      </a>
      <?php foreach ($categories as $cat): ?>
        <a href="<?= BASE_URL ?>/index.php?cat=<?= $cat['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?>"
           class="btn btn-sm <?= $catId === $cat['id'] ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill">
          <?= e($cat['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Sort -->
  <div class="col-auto ms-auto">
    <form method="GET" class="d-flex gap-2">
      <?php if ($search): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
      <?php if ($catId): ?><input type="hidden" name="cat" value="<?= $catId ?>"><?php endif; ?>
      <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="recent"     <?= $sort==='recent'     ? 'selected' : '' ?>>Más recientes</option>
        <option value="popular"    <?= $sort==='popular'    ? 'selected' : '' ?>>Más populares</option>
        <option value="price_asc"  <?= $sort==='price_asc'  ? 'selected' : '' ?>>Precio: menor a mayor</option>
        <option value="price_desc" <?= $sort==='price_desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
      </select>
    </form>
  </div>
</div>

<!-- ===== GRID DE PRODUCTOS ===== -->
<?php if ($products): ?>
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-4">
  <?php foreach ($products as $p): ?>
  <div class="col">
    <div class="card h-100">
      <a href="<?= BASE_URL ?>/product.php?id=<?= $p['id'] ?>">
        <img src="<?= $p['image'] ? e($p['image']) : BASE_URL . '/assets/img/no-image.svg' ?>"
             class="card-img-top product-card" alt="<?= e($p['name']) ?>">
      </a>
      <?php if ($p['is_featured']): ?>
        <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">
          <i class="bi bi-star-fill"></i> Destacado
        </span>
      <?php endif; ?>
      <div class="card-body d-flex flex-column">
        <small class="text-muted"><?= e($p['category_name']) ?></small>
        <a href="<?= BASE_URL ?>/product.php?id=<?= $p['id'] ?>"
           class="text-decoration-none text-dark">
          <h6 class="fw-semibold mt-1 mb-2"><?= e(mb_strimwidth($p['name'],0,55,'…')) ?></h6>
        </a>
        <div class="mt-auto">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="price-tag"><?= money($p['price']) ?></span>
            <?php if ($p['compare_price'] && $p['compare_price'] > $p['price']): ?>
              <span class="compare-price"><?= money($p['compare_price']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($p['stock'] <= 0): ?>
            <span class="badge bg-secondary w-100">Sin stock</span>
          <?php else: ?>
            <form method="POST" action="<?= BASE_URL ?>/cart.php">
              <input type="hidden" name="action"     value="add">
              <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-accent btn-sm w-100">
                <i class="bi bi-cart-plus me-1"></i>Agregar
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Paginación -->
<?php if ($pag['pages'] > 1): ?>
<nav class="mt-5">
  <ul class="pagination justify-content-center">
    <?php for ($i = 1; $i <= $pag['pages']; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $catId ? '&cat='.$catId : '' ?>&sort=<?= e($sort) ?>">
          <?= $i ?>
        </a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php else: ?>
<div class="text-center py-5">
  <i class="bi bi-search text-muted" style="font-size:4rem"></i>
  <h4 class="mt-3 text-muted">No se encontraron productos</h4>
  <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary mt-3">Ver todos los productos</a>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
