<?php
$pageTitle = 'Subir comprobante de pago';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$db      = getDB();
$orderId = (int)($_GET['order'] ?? 0);

// Verificar que el pedido pertenece al usuario
$order = $db->prepare("SELECT o.*, pm.name AS pay_method_name FROM orders o JOIN payments p ON p.order_id=o.id JOIN payment_methods_config pm ON pm.id=p.payment_method_id WHERE o.id=? AND o.user_id=? LIMIT 1");
$order->execute([$orderId, $_SESSION['user_id']]);
$order = $order->fetch();

if (!$order) {
    setFlash('danger','Pedido no encontrado.');
    header('Location: ' . BASE_URL . '/orders.php'); exit;
}

$bankAccounts = $db->query("SELECT * FROM bank_accounts WHERE is_active=1")->fetchAll();

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference = sanitize($_POST['transfer_reference'] ?? '');
    if (!$reference) $errors[] = 'El número de referencia es requerido.';

    $proofUrl = null;

    // Manejar subida de imagen
    if (!empty($_FILES['proof_image']['tmp_name'])) {
        $file     = $_FILES['proof_image'];
        $allowed  = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
        $maxSize  = 5 * 1024 * 1024; // 5 MB

        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'El archivo debe ser imagen (JPG, PNG, GIF, WEBP) o PDF.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'El archivo no puede superar 5 MB.';
        } else {
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'proof_' . $orderId . '_' . time() . '.' . strtolower($ext);
            $dir      = __DIR__ . '/uploads/transfers/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $proofUrl = BASE_URL . '/uploads/transfers/' . $filename;
            } else {
                $errors[] = 'Error al subir el archivo. Intenta nuevamente.';
            }
        }
    } else {
        $errors[] = 'Debes subir el comprobante de transferencia.';
    }

    if (empty($errors)) {
        $db->prepare("UPDATE payments SET transfer_reference=?, transfer_proof_url=?, status='processing', updated_at=NOW() WHERE order_id=?")
           ->execute([$reference, $proofUrl, $orderId]);

        $db->prepare("INSERT INTO order_status_history (order_id,status,comment) VALUES (?,?,?)")
           ->execute([$orderId,'pending','Comprobante de transferencia subido por el cliente. En revisión.']);

        $db->prepare("INSERT INTO notifications (user_id,type,title,message) VALUES (?,?,?,?)")
           ->execute([$_SESSION['user_id'],'payment_uploaded','Comprobante recibido','Tu comprobante del pedido '.$order['order_number'].' fue recibido. Lo verificaremos pronto.']);

        $success = true;
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
.bank-card{background:linear-gradient(135deg,#1e3a5f,#2d5a8e);color:#fff;border-radius:14px}
.bank-num{font-size:1.05rem;letter-spacing:2px;font-weight:600}
.upload-area{border:2px dashed #dee2e6;border-radius:14px;padding:2rem;text-align:center;cursor:pointer;transition:all .2s}
.upload-area:hover,.upload-area.dragging{border-color:#1a73e8;background:#f0f6ff}
</style>

<div class="row justify-content-center">
<div class="col-lg-7">

<?php if ($success): ?>
  <!-- ÉXITO -->
  <div class="card border-0 shadow-sm p-5 text-center">
    <div class="mb-4">
      <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle" style="width:80px;height:80px">
        <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem"></i>
      </div>
    </div>
    <h3 class="fw-bold mb-2">¡Comprobante enviado!</h3>
    <p class="text-muted mb-1">Tu pedido <strong><?= e($order['order_number']) ?></strong> está en revisión.</p>
    <p class="text-muted mb-4">Te notificaremos cuando confirmemos tu pago (generalmente en menos de 2 horas hábiles).</p>

    <div class="bg-light rounded-3 p-3 mb-4 text-start small">
      <p class="mb-1"><i class="bi bi-1-circle-fill text-primary me-2"></i><strong>Comprobante recibido</strong> ✓</p>
      <p class="mb-1 text-muted"><i class="bi bi-2-circle text-muted me-2"></i>Verificación del pago <em>(en proceso)</em></p>
      <p class="mb-0 text-muted"><i class="bi bi-3-circle text-muted me-2"></i>Preparación y despacho del pedido</p>
    </div>

    <div class="d-flex gap-3 justify-content-center">
      <a href="<?= BASE_URL ?>/orders.php?order=<?= $orderId ?>" class="btn btn-accent px-4">
        <i class="bi bi-box-seam me-2"></i>Ver mi pedido
      </a>
      <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary px-4">
        <i class="bi bi-bag me-2"></i>Seguir comprando
      </a>
    </div>
  </div>

<?php else: ?>

  <!-- Steps -->
  <div class="d-flex align-items-center gap-2 mb-4">
    <div style="width:28px;height:28px;border-radius:50%;background:#198754;display:flex;align-items:center;justify-content:center">
      <i class="bi bi-check-lg text-white small"></i></div>
    <span class="fw-semibold text-success small">Pedido creado</span>
    <div style="height:2px;width:40px;background:#dee2e6"></div>
    <div style="width:28px;height:28px;border-radius:50%;background:#0d6efd;display:flex;align-items:center;justify-content:center">
      <span class="text-white fw-bold" style="font-size:.8rem">2</span></div>
    <span class="fw-bold small">Comprobante</span>
    <div style="height:2px;width:40px;background:#dee2e6"></div>
    <div style="width:28px;height:28px;border-radius:50%;border:2px solid #dee2e6;display:flex;align-items:center;justify-content:center">
      <span class="text-muted fw-bold" style="font-size:.8rem">3</span></div>
    <span class="text-muted small">Confirmación</span>
  </div>

  <!-- Info pedido -->
  <div class="card border-0 shadow-sm p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-receipt me-2 text-primary"></i>Pedido <?= e($order['order_number']) ?></h5>
        <p class="text-muted small mb-0">Total a transferir: <strong class="price-tag fs-5"><?= money($order['total']) ?></strong></p>
      </div>
      <span class="badge bg-warning text-dark">Pendiente de pago</span>
    </div>
  </div>

  <!-- Cuentas bancarias -->
  <div class="card border-0 shadow-sm p-4 mb-4">
    <h5 class="fw-bold mb-3"><i class="bi bi-bank2 me-2 text-success"></i>Realiza la transferencia a:</h5>
    <div class="row g-3">
      <?php foreach ($bankAccounts as $ba): ?>
      <div class="col-md-6">
        <div class="bank-card p-3 h-100">
          <div class="d-flex justify-content-between mb-2">
            <span class="badge bg-white bg-opacity-20 text-white"><?= ucfirst($ba['account_type']) ?></span>
            <button type="button" class="btn btn-sm btn-outline-light py-0 btn-copy" data-copy="<?= e($ba['account_number']) ?>">
              <i class="bi bi-copy me-1"></i>Copiar
            </button>
          </div>
          <p class="fw-bold mb-1"><?= e($ba['bank_name']) ?></p>
          <p class="bank-num mb-1"><?= e($ba['account_number']) ?></p>
          <p class="small mb-0 opacity-75">Titular: <?= e($ba['account_name']) ?></p>
          <?php if ($ba['identification']): ?>
            <p class="small mb-0 opacity-75">RUC: <?= e($ba['identification']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="alert alert-info border-0 rounded-3 mt-3 small mb-0">
      <i class="bi bi-info-circle-fill me-2"></i>
      Transfiere exactamente <strong><?= money($order['total']) ?></strong> e incluye el número de pedido <strong><?= e($order['order_number']) ?></strong> en el concepto.
    </div>
  </div>

  <!-- Formulario comprobante -->
  <div class="card border-0 shadow-sm p-4">
    <h5 class="fw-bold mb-3"><i class="bi bi-upload me-2 text-primary"></i>Sube tu comprobante</h5>

    <?php if ($errors): ?>
      <div class="alert alert-danger border-0 rounded-3">
        <?php foreach($errors as $e): ?><div><i class="bi bi-x-circle me-2"></i><?= e($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <div class="mb-4">
        <label class="form-label fw-semibold">Número de referencia / comprobante *</label>
        <input type="text" class="form-control form-control-lg" name="transfer_reference"
               placeholder="Ej: 00245789 o número que aparece en tu transferencia" required>
        <div class="form-text">Número que aparece en el comprobante de la transferencia bancaria.</div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold">Imagen o PDF del comprobante *</label>
        <div class="upload-area" id="dropZone" onclick="document.getElementById('proofFile').click()">
          <input type="file" name="proof_image" id="proofFile" accept="image/*,.pdf" style="display:none" required onchange="showPreview(this)">
          <div id="uploadPrompt">
            <i class="bi bi-cloud-upload text-primary" style="font-size:2.5rem"></i>
            <p class="fw-semibold mt-2 mb-1">Haz clic o arrastra tu archivo aquí</p>
            <p class="text-muted small mb-0">JPG, PNG, PDF · Máximo 5 MB</p>
          </div>
          <div id="filePreview" style="display:none">
            <img id="previewImg" src="" style="max-height:180px;max-width:100%;border-radius:10px;object-fit:contain">
            <p id="fileName" class="text-muted small mt-2 mb-0"></p>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-accent btn-lg w-100 fw-semibold rounded-3 py-3">
        <i class="bi bi-send-fill me-2"></i>Enviar comprobante
      </button>
      <p class="text-center text-muted small mt-2 mb-0">
        <i class="bi bi-shield-check text-success me-1"></i>Tu información está segura con nosotros
      </p>
    </form>
  </div>

<?php endif; ?>
</div>
</div>

<script>
// Copiar número de cuenta
document.querySelectorAll('.btn-copy').forEach(btn => {
  btn.addEventListener('click', () => {
    navigator.clipboard.writeText(btn.dataset.copy).then(() => {
      const orig = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>¡Copiado!';
      setTimeout(() => btn.innerHTML = orig, 1800);
    });
  });
});

// Preview imagen
function showPreview(input) {
  if (!input.files.length) return;
  const file = input.files[0];
  document.getElementById('fileName').textContent = file.name + ' (' + (file.size/1024).toFixed(0) + ' KB)';
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('previewImg').src = e.target.result;
      document.getElementById('previewImg').style.display = 'block';
    };
    reader.readAsDataURL(file);
  } else {
    document.getElementById('previewImg').style.display = 'none';
  }
  document.getElementById('uploadPrompt').style.display = 'none';
  document.getElementById('filePreview').style.display  = 'block';
}

// Drag & drop
const dz = document.getElementById('dropZone');
if (dz) {
  dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragging'); });
  dz.addEventListener('dragleave', () => dz.classList.remove('dragging'));
  dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('dragging');
    const f = document.getElementById('proofFile');
    f.files = e.dataTransfer.files;
    showPreview(f);
  });
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
