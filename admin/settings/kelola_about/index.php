<?php
/**
 * admin/settings/kelola_about/index.php
 * Versi perbaikan: Tampilan modern, tanpa gambar, toast notification, layout rapi.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($GLOBALS['koneksi'])) {
    die("Koneksi database tidak tersedia.");
}
$koneksi = $GLOBALS['koneksi'];
$router_path = $router_path ?? 'dashboard.php';

// Ambil data About
$query = "SELECT * FROM halaman_about WHERE id = 1";
$result = $koneksi->query($query);

$about_data = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;

$edit_route   = $router_path . "?page=settings/kelola_about/edit&id=1";
$create_route = $router_path . "?page=settings/kelola_about/edit";
$delete_route = $router_path . "?page=settings/kelola_about/edit";

// Format Misi → List
function format_misi($misi_text) {
    $items = array_filter(array_map('trim', explode("\n", $misi_text)));
    if (empty($items)) return '<span class="text-muted">Misi belum diisi.</span>';

    $html = '<ul class="ps-3">';
    foreach ($items as $i) $html .= "<li>" . htmlspecialchars($i) . "</li>";
    return $html . '</ul>';
}
?>

<!-- TOAST NOTIFICATION -->
<?php if (isset($_SESSION['status_message'])): ?>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
    <div class="toast align-items-center text-white bg-<?= $_SESSION['status_message']['type'] ?> border-0 show">
        <div class="d-flex">
            <div class="toast-body">
                <?= $_SESSION['status_message']['text'] ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php unset($_SESSION['status_message']); endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <h1 class="display-6 fw-bold text-primary"><i data-feather="info" class="me-2"></i> Kelola Halaman Tentang Kami</h1>

    <?php if ($about_data): ?>
        <div>
            <a href="<?= $edit_route ?>" class="btn btn-warning shadow-sm me-2">
                <i data-feather="edit-3" class="me-1"></i> Edit
            </a>
            <button class="btn btn-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                <i data-feather="trash-2" class="me-1"></i> Hapus
            </button>
        </div>
    <?php else: ?>
        <a href="<?= $create_route ?>" class="btn btn-success shadow-sm">
            <i data-feather="plus-circle" class="me-1"></i> Tambah Data
        </a>
    <?php endif; ?>
</div>

<?php if (!$about_data): ?>
    <div class="alert alert-warning">
        Belum ada data. Klik **Tambah Data** untuk membuat konten.
    </div>
<?php else: ?>

<!-- CARD PREVIEW -->
<div class="card shadow-lg border-0 mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="m-0 fw-bold"><i data-feather="eye" class="me-2"></i> Pratinjau Halaman</h5>
    </div>

    <div class="card-body p-4">

        <!-- Judul -->
        <h3 class="fw-bold text-primary">
            <?= htmlspecialchars($about_data['judul_halaman']) ?>
        </h3>

        <!-- Sub Judul (tanpa **) -->
        <p class="text-muted fs-5 mb-4"><?= htmlspecialchars($about_data['sub_judul_halaman']) ?></p>

        <div class="row">

            <!-- VISI -->
            <div class="col-md-6 mb-4">
                <h5 class="fw-bold text-warning"><i data-feather="target" class="me-1"></i> Visi Kami</h5>
                <p class="text-secondary">
                    <?= nl2br(htmlspecialchars($about_data['visi'])) ?: '<i class="text-muted">Belum diisi.</i>' ?>
                </p>
            </div>

            <!-- MISI -->
            <div class="col-md-6 mb-4">
                <h5 class="fw-bold text-success"><i data-feather="list" class="me-1"></i> Misi Kami</h5>
                <?= format_misi($about_data['misi']) ?>
            </div>
        </div>

        <!-- CTA -->
        <h5 class="fw-bold text-secondary mt-4"><i data-feather="message-circle" class="me-1"></i> Teks CTA</h5>
        <p>
            <span class="badge bg-info text-dark fs-6 px-3 py-2">
                <?= htmlspecialchars($about_data['teks_cta']) ?: 'Tidak ada.' ?>
            </span>
        </p>

        <!-- Google Maps -->
        <?php if (!empty($about_data['link_maps'])): ?>
        <div class="mt-4">
            <h5 class="fw-bold text-primary mb-2"><i data-feather="map" class="me-1"></i> Lokasi Google Maps</h5>
            <div class="ratio ratio-16x9 border rounded shadow-sm">
                <?= $about_data['link_maps'] ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="card-footer text-end text-muted small">
        Terakhir diperbarui: <?= $about_data['updated_at'] ?>
    </div>
</div>
<?php endif; ?>

<!-- MODAL DELETE -->
<div class="modal fade" id="confirmDeleteModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">
            <i data-feather="alert-triangle" class="me-2"></i> Konfirmasi Hapus
        </h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
          Apakah Anda yakin ingin menghapus seluruh data "Tentang Kami"?
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>

        <form action="<?= $delete_route ?>" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="1">
            <button type="submit" class="btn btn-danger">
                <i data-feather="trash"></i> Hapus
            </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
if (typeof feather !== 'undefined') { feather.replace(); }
</script>
