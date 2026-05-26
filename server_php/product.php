<?php
// ============================================================
//  Geo-Ecomers | Detalle de Producto
// ============================================================
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.id = ? AND p.status = 'active'"
);
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('warning', 'Producto no encontrado.');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Incrementar vistas
$db->prepare('UPDATE products SET views = views + 1 WHERE id = ?')->execute([$id]);

// Imágenes
$images = $db->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order');
$images->execute([$id]);
$images = $images->fetchAll();

// Stock
$stockStmt = $db->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND variant_id IS NULL LIMIT 1');
$stockStmt->execute([$id]);
$stock = (int)($stockStmt->fetchColumn() ?: 0);

// Variantes
$variants = $db->prepare('SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1');
$variants->execute([$id]);
$variants = $variants->fetchAll();

// Reseñas aprobadas
$reviewStmt = $db->prepare(
    'SELECT r.*, u.name AS user_name FROM reviews r JOIN users u ON u.id = r.user_id
     WHERE r.product_id = ? AND r.status = "approved" ORDER BY r.created_at DESC LIMIT 10'
);
$reviewStmt->execute([$id]);
$reviews = $reviewStmt->fetchAll();
$avgRating = $reviews ? array_sum(array_column($reviews,'rating')) / count($reviews) : 0;

// Productos relacionados
$related = $db->prepare(
    "SELECT p.id, p.name, p.price,
            (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
     FROM products p WHERE p.category_id = ? AND p.id != ? AND p.status='active' LIMIT 4"
);
$related->execute([$product['category_id'], $id]);
$related = $related->fetchAll();

// Manejo del carrito (POST add)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_cart') {
    requireLogin();
    $qty       = max(1, (int)($_POST['qty'] ?? 1));
    $variantId = (int)($_POST['variant_id'] ?? 0) ?: null;
    $cartId    = getOrCreateCart();

    // ¿Ya existe en el carrito?
    $existing = $db->prepare('SELECT id, quantity FROM cart_items WHERE cart_id=? AND product_id=? AND variant_id IS ?');
    $existing->execute([$cartId, $id, $variantId]);
    $row = $existing->fetch();

    if ($row) {
        $db->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?')
           ->execute([$qty, $row['id']]);
    } else {
        $db->prepare('INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, unit_price) VALUES (?,?,?,?,?)')
           ->execute([$cartId, $id, $variantId, $qty, $product['price']]);
    }
    setFlash('success', '¡Producto agregado al carrito!');
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

$pageTitle = $product['name'];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Inicio</a></li>
    <li class="breadcrumb-item">
      <a href="<?= BASE_URL ?>/index.php?cat=<?= $product['category_id'] ?>"><?= e($product['category_name']) ?></a>
    </li>
    <li class="breadcrumb-item active"><?= e(mb_strimwidth($product['name'],0,40,'…')) ?></li>
  </ol>
</nav>

<div class="row g-5">
  <!-- Imágenes -->
  <div class="col-md-5">
    <?php $mainImg = $images[0]['image_url'] ?? BASE_URL . '/assets/img/no-image.svg'; ?>
    <img id="mainImage" src="<?= e($mainImg) ?>" class="img-fluid rounded-4 w-100 mb-3"
         style="max-height:400px;object-fit:cover" alt="<?= e($product['name']) ?>">
    <?php if (count($images) > 1): ?>
      <div class="d-flex gap-2 flex-wrap">
        <?php foreach ($images as $img): ?>
          <img src="<?= e($img['image_url']) ?>"
               class="rounded-3 border border-2 <?= $img['is_primary'] ? 'border-primary' : 'border-light' ?>"
               style="width:70px;height:70px;object-fit:cover;cursor:pointer"
               onclick="document.getElementById('mainImage').src=this.src">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Info -->
  <div class="col-md-7">
    <span class="badge bg-primary mb-2"><?= e($product['category_name']) ?></span>
    <h1 class="fw-bold h2"><?= e($product['name']) ?></h1>
    <p class="text-muted small">SKU: <?= e($product['sku']) ?></p>

    <!-- Rating -->
    <?php if ($avgRating > 0): ?>
      <div class="d-flex align-items-center gap-2 mb-3">
        <?php for ($s = 1; $s <= 5; $s++): ?>
          <i class="bi bi-star<?= $s <= round($avgRating) ? '-fill text-warning' : ' text-muted' ?>"></i>
        <?php endfor; ?>
        <span class="text-muted small">(<?= count($reviews) ?> reseñas)</span>
      </div>
    <?php endif; ?>

    <!-- Precio -->
    <div class="mb-3">
      <span class="price-tag fs-3"><?= money($product['price']) ?></span>
      <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
        <span class="compare-price ms-2 fs-5"><?= money($product['compare_price']) ?></span>
        <span class="badge bg-danger ms-2">
          -<?= round((1 - $product['price']/$product['compare_price'])*100) ?>%
        </span>
      <?php endif; ?>
    </div>

    <!-- Stock -->
    <div class="mb-3">
      <?php if ($stock > 10): ?>
        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>En stock (<?= $stock ?> disponibles)</span>
      <?php elseif ($stock > 0): ?>
        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>Últimas <?= $stock ?> unidades</span>
      <?php else: ?>
        <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Sin stock</span>
      <?php endif; ?>
    </div>

    <?php if ($product['short_description']): ?>
      <p class="text-muted"><?= e($product['short_description']) ?></p>
    <?php endif; ?>

    <?php if ($stock > 0): ?>
    <form method="POST">
      <input type="hidden" name="action" value="add_cart">
      <?php if ($variants): ?>
        <div class="mb-3">
          <label class="form-label fw-semibold">Selecciona variante</label>
          <select name="variant_id" class="form-select">
            <?php foreach ($variants as $v): ?>
              <option value="<?= $v['id'] ?>">
                <?= e($v['attribute_name']) ?>: <?= e($v['attribute_value']) ?>
                <?= $v['price_modifier'] != 0 ? ' ('.($v['price_modifier']>0?'+':'').money($v['price_modifier']).')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="d-flex align-items-center gap-3 mb-4">
        <label class="fw-semibold">Cantidad:</label>
        <div class="input-group" style="width:130px">
          <button class="btn btn-outline-secondary" type="button"
                  onclick="let q=document.getElementById('qty');if(q.value>1)q.value--">−</button>
          <input type="number" id="qty" name="qty" value="1" min="1" max="<?= $stock ?>"
                 class="form-control text-center">
          <button class="btn btn-outline-secondary" type="button"
                  onclick="let q=document.getElementById('qty');if(q.value<<?= $stock ?>)q.value++">+</button>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-accent btn-lg px-5 fw-semibold">
          <i class="bi bi-cart-plus me-2"></i>Agregar al carrito
        </button>
        <?php if (isLoggedIn()): ?>
          <a href="<?= BASE_URL ?>/cart.php?wishlist=<?= $id ?>" class="btn btn-outline-secondary btn-lg">
            <i class="bi bi-heart"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
    <?php endif; ?>

    <!-- Detalles de envío -->
    <div class="mt-4 p-3 bg-light rounded-3">
      <div class="d-flex gap-4 flex-wrap">
        <span class="small"><i class="bi bi-truck text-primary me-2"></i>Envío a todo Ecuador</span>
        <span class="small"><i class="bi bi-shield-check text-success me-2"></i>Compra segura</span>
        <span class="small"><i class="bi bi-arrow-return-left text-warning me-2"></i>Devoluciones fáciles</span>
      </div>
    </div>
  </div>
</div>

<!-- Descripción completa -->
<?php if ($product['description']): ?>
<div class="mt-5">
  <h4 class="section-title mb-3">Descripción</h4>
  <div class="card p-4">
    <p class="mb-0"><?= nl2br(e($product['description'])) ?></p>
  </div>
</div>
<?php endif; ?>

<!-- Reseñas -->
<div class="mt-5">
  <h4 class="section-title mb-3">Reseñas de clientes (<?= count($reviews) ?>)</h4>
  <?php if ($reviews): ?>
    <div class="row g-3">
      <?php foreach ($reviews as $r): ?>
        <div class="col-md-6">
          <div class="card p-3">
            <div class="d-flex justify-content-between">
              <strong><?= e($r['user_name']) ?></strong>
              <span class="text-warning">
                <?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?>
              </span>
            </div>
            <?php if ($r['title']): ?><p class="fw-semibold mb-1 mt-1"><?= e($r['title']) ?></p><?php endif; ?>
            <?php if ($r['comment']): ?><p class="text-muted small mb-0"><?= e($r['comment']) ?></p><?php endif; ?>
            <small class="text-muted mt-2"><?= date('d/m/Y', strtotime($r['created_at'])) ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-muted">Este producto aún no tiene reseñas.</p>
  <?php endif; ?>
</div>

<!-- Relacionados -->
<?php if ($related): ?>
<div class="mt-5">
  <h4 class="section-title mb-3">Productos relacionados</h4>
  <div class="row row-cols-2 row-cols-md-4 g-3">
    <?php foreach ($related as $r): ?>
      <div class="col">
        <a href="<?= BASE_URL ?>/product.php?id=<?= $r['id'] ?>" class="text-decoration-none text-dark">
          <div class="card h-100">
            <img src="<?= $r['image'] ? e($r['image']) : BASE_URL.'/assets/img/no-image.svg' ?>"
                 class="card-img-top" style="height:140px;object-fit:cover">
            <div class="card-body p-2">
              <p class="small fw-semibold mb-1"><?= e(mb_strimwidth($r['name'],0,45,'…')) ?></p>
              <p class="price-tag mb-0 small"><?= money($r['price']) ?></p>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
