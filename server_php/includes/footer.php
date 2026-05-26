<?php
// ============================================================
//  Geo-Ecomers | Footer
// ============================================================
?>
</div><!-- /container -->
</main>

<footer class="py-5 mt-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <h5 class="text-white fw-bold"><i class="bi bi-globe2 me-2"></i>Geo<span style="color:#ff6f00">Ecomers</span></h5>
        <p class="small">Tu tienda online de confianza. Productos de calidad con envío a todo el Ecuador.</p>
      </div>
      <div class="col-md-2">
        <h6 class="text-white">Tienda</h6>
        <ul class="list-unstyled small">
          <li><a href="<?= BASE_URL ?>/index.php" class="text-decoration-none text-secondary">Productos</a></li>
          <li><a href="<?= BASE_URL ?>/cart.php" class="text-decoration-none text-secondary">Carrito</a></li>
          <li><a href="<?= BASE_URL ?>/orders.php" class="text-decoration-none text-secondary">Mis pedidos</a></li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="text-white">Contacto</h6>
        <ul class="list-unstyled small">
          <li><i class="bi bi-envelope me-2"></i>info@geo-ecomers.com</li>
          <li><i class="bi bi-telephone me-2"></i>+593 99 000 0000</li>
          <li><i class="bi bi-geo-alt me-2"></i>Ecuador</li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="text-white">Pagos seguros</h6>
        <div class="d-flex gap-2 flex-wrap mt-2">
          <span class="badge bg-secondary py-2 px-3"><i class="bi bi-credit-card me-1"></i>Tarjeta</span>
          <span class="badge bg-secondary py-2 px-3"><i class="bi bi-bank me-1"></i>Transferencia</span>
          <span class="badge bg-secondary py-2 px-3"><i class="bi bi-paypal me-1"></i>PayPal</span>
        </div>
      </div>
    </div>
    <hr class="border-secondary mt-4">
    <p class="text-center small mb-0">&copy; <?= date('Y') ?> Geo-Ecomers. Todos los derechos reservados.</p>
  </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
