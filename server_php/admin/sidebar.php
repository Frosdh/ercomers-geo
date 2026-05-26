<?php
// Admin Sidebar
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="col-auto admin-sidebar p-0" style="width:220px;min-width:220px">
  <div class="p-3 border-bottom border-dark">
    <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none">
      <span class="text-white fw-bold fs-5"><i class="bi bi-globe2 me-2"></i>Geo<span style="color:#ff6f00">Ecomers</span></span>
    </a>
    <div class="text-secondary small mt-1">Panel Admin</div>
  </div>
  <nav class="p-3">
    <ul class="nav flex-column gap-1">

      <li class="nav-section-label text-uppercase text-secondary small px-2 mb-1 mt-1" style="font-size:.65rem;letter-spacing:.08em">Principal</li>
      <li><a href="<?= BASE_URL ?>/admin/index.php"         class="nav-link <?= $current==='index.php'         ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
      <li><a href="<?= BASE_URL ?>/admin/orders.php"        class="nav-link <?= $current==='orders.php'        ? 'active' : '' ?>"><i class="bi bi-cart3 me-2"></i>Pedidos</a></li>

      <li class="nav-section-label text-uppercase text-secondary small px-2 mb-1 mt-3" style="font-size:.65rem;letter-spacing:.08em">Catálogo</li>
      <li><a href="<?= BASE_URL ?>/admin/products.php"      class="nav-link <?= $current==='products.php'      ? 'active' : '' ?>"><i class="bi bi-box-seam me-2"></i>Productos</a></li>
      <li><a href="<?= BASE_URL ?>/admin/inventory.php"     class="nav-link <?= $current==='inventory.php'     ? 'active' : '' ?>"><i class="bi bi-boxes me-2"></i>Inventario</a></li>
      <li><a href="<?= BASE_URL ?>/admin/discounts.php"     class="nav-link <?= $current==='discounts.php'     ? 'active' : '' ?>"><i class="bi bi-tag me-2"></i>Descuentos</a></li>

      <li class="nav-section-label text-uppercase text-secondary small px-2 mb-1 mt-3" style="font-size:.65rem;letter-spacing:.08em">Logística</li>
      <li><a href="<?= BASE_URL ?>/admin/shipping.php"      class="nav-link <?= $current==='shipping.php'      ? 'active' : '' ?>"><i class="bi bi-truck me-2"></i>Envíos</a></li>
      <li><a href="<?= BASE_URL ?>/admin/bank_accounts.php" class="nav-link <?= $current==='bank_accounts.php' ? 'active' : '' ?>"><i class="bi bi-bank me-2"></i>Cuentas bancarias</a></li>

      <li class="nav-section-label text-uppercase text-secondary small px-2 mb-1 mt-3" style="font-size:.65rem;letter-spacing:.08em">Configuración</li>
      <li><a href="<?= BASE_URL ?>/admin/users.php"         class="nav-link <?= $current==='users.php'         ? 'active' : '' ?>"><i class="bi bi-people me-2"></i>Usuarios</a></li>

      <li class="mt-3 pt-3 border-top border-dark">
        <a href="<?= BASE_URL ?>/index.php" class="nav-link"><i class="bi bi-shop me-2"></i>Ver tienda</a>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a>
      </li>
    </ul>
  </nav>
</div>
