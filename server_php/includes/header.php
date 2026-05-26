<?php
// ============================================================
//  Geo-Ecomers | Header / Navbar
// ============================================================
require_once __DIR__ . '/functions.php';

$pageTitle  = $pageTitle  ?? 'Geo-Ecomers';
$cartItems  = isLoggedIn() ? cartCount() : 0;
$user       = currentUser();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | Geo-Ecomers</title>
  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --geo-primary: #1a73e8;
      --geo-dark:    #0d47a1;
      --geo-accent:  #ff6f00;
    }
    body { background: #f5f6fa; }
    .navbar-brand span { color: var(--geo-accent); font-weight: 800; }
    .navbar { background: var(--geo-dark) !important; }
    .navbar .nav-link, .navbar-brand { color: #fff !important; }
    .navbar .nav-link:hover { color: #ffd54f !important; }
    .btn-accent { background: var(--geo-accent); color: #fff; border: none; }
    .btn-accent:hover { background: #e65100; color: #fff; }
    .badge-cart { position: relative; top: -8px; left: -5px; font-size: 0.65rem; }
    .card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.07); }
    .product-card img { height: 200px; object-fit: cover; border-radius: 12px 12px 0 0; }
    .price-tag { color: var(--geo-accent); font-weight: 700; font-size: 1.1rem; }
    .compare-price { text-decoration: line-through; color: #999; font-size: .9rem; }
    footer { background: var(--geo-dark); color: #ccc; }
    .section-title { font-weight: 700; border-left: 4px solid var(--geo-accent); padding-left: 12px; }
    .admin-sidebar { min-height: 100vh; background: #1e293b; }
    .admin-sidebar .nav-link { color: #94a3b8; }
    .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.1); border-radius: 8px; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/index.php">
      <i class="bi bi-globe2 me-1"></i>Geo<span>Ecomers</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <!-- Search -->
      <form class="d-flex mx-auto my-2 my-lg-0" style="max-width:400px;width:100%"
            action="<?= BASE_URL ?>/index.php" method="GET">
        <input class="form-control me-2 rounded-pill" type="search" name="q"
               placeholder="Buscar productos..." value="<?= e($_GET['q'] ?? '') ?>">
        <button class="btn btn-accent rounded-pill px-3" type="submit">
          <i class="bi bi-search"></i>
        </button>
      </form>

      <ul class="navbar-nav ms-auto align-items-center gap-1">

        <!-- Productos (siempre visible) -->
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/index.php">
            <i class="bi bi-grid me-1"></i>Productos
          </a>
        </li>

        <!-- Cart -->
        <li class="nav-item">
          <a class="nav-link position-relative" href="<?= BASE_URL ?>/cart.php">
            <i class="bi bi-cart3 fs-5"></i>
            <?php if ($cartItems > 0): ?>
              <span class="badge bg-warning text-dark badge-cart"><?= $cartItems ?></span>
            <?php endif; ?>
          </a>
        </li>

        <?php if (isLoggedIn()): ?>
          <?php if (isAdmin()): ?>
            <!-- Acceso directo al panel admin -->
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/admin/index.php" title="Panel Admin">
                <i class="bi bi-speedometer2 fs-5"></i>
              </a>
            </li>
          <?php endif; ?>
          <!-- User dropdown -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              <?php if (!empty($user['avatar'])): ?>
                <img src="<?= e($user['avatar']) ?>" class="rounded-circle me-1" width="28" height="28">
              <?php else: ?>
                <i class="bi bi-person-circle fs-5 me-1"></i>
              <?php endif; ?>
              <?= e(explode(' ', $user['name'])[0]) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/index.php"><i class="bi bi-house me-2"></i>Inicio / Productos</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>/orders.php"><i class="bi bi-box-seam me-2"></i>Mis pedidos</a></li>
              <?php if (isAdmin()): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-primary fw-semibold" href="<?= BASE_URL ?>/admin/index.php"><i class="bi bi-speedometer2 me-2"></i>Panel Admin</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/auth/login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Ingresar</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-accent btn-sm rounded-pill px-3" href="<?= BASE_URL ?>/auth/register.php">Registrarse</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="py-4">
<div class="container">
<?= showFlash() ?>
