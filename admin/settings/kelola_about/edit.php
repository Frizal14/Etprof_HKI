<?php
/**
 * admin/settings/kelola_about/edit.php
 * Form edit About + tombol simpan perubahan.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$koneksi = $GLOBALS['koneksi'];
$router_path = $router_path ?? 'dashboard.php';

$id = $_GET['id'] ?? 1;

// Ambil data
$query = "SELECT * FROM halaman_about WHERE id = $id";
$result = $koneksi->query($query);
$data = $result->fetch_assoc();

// Jika tidak ada data → buat baru
if (!$data) {
    $data = [
        'judul_halaman' => '',
        'sub_judul_halaman' => '',
        'visi' => '',
        'misi' => '',
        'gambar_visi' => '',
        'gambar_misi' => '',
        'teks_cta' => '',
        'link_maps' => ''
    ];
}

// Path proses.php
$proses_route = $router_path . "?page=settings/kelola_about/proses";

?>

<div class="card shadow border-0">
    <div class="card-header bg-primary text-white p-3">
        <h4 class="mb-0"><i data-feather="edit" class="me-2"></i>Edit Halaman Tentang Kami</h4>
    </div>

    <form action="<?= $proses_route ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="card-body p-4">

            <div class="mb-3">
                <label class="form-label fw-bold">Judul Halaman</label>
                <input type="text" class="form-control" name="judul_halaman" value="<?= htmlspecialchars($data['judul_halaman']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Sub Judul</label>
                <input type="text" class="form-control" name="sub_judul_halaman" value="<?= htmlspecialchars($data['sub_judul_halaman']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Visi</label>
                <textarea class="form-control" name="visi" rows="4"><?= htmlspecialchars($data['visi']) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Upload Gambar Visi</label>
                <input type="file" name="gambar_visi" class="form-control">
                <?php if($data['gambar_visi']): ?>
                    <img src="uploads/<?= $data['gambar_visi'] ?>" class="img-fluid rounded mt-2" style="max-height:150px;">
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Misi (Pisahkan dengan enter)</label>
                <textarea class="form-control" name="misi" rows="5"><?= htmlspecialchars($data['misi']) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Upload Gambar Misi</label>
                <input type="file" name="gambar_misi" class="form-control">
                <?php if($data['gambar_misi']): ?>
                    <img src="uploads/<?= $data['gambar_misi'] ?>" class="img-fluid rounded mt-2" style="max-height:150px;">
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Teks CTA</label>
                <input type="text" class="form-control" name="teks_cta" value="<?= htmlspecialchars($data['teks_cta']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Embed Google Maps</label>
                <textarea name="link_maps" rows="4" class="form-control"><?= $data['link_maps'] ?></textarea>
            </div>

        </div>

        <div class="card-footer p-3 text-end">
            <a href="<?= $router_path ?>?page=settings/kelola_about/index" class="btn btn-secondary">Kembali</a>
            <button type="submit" class="btn btn-success">
                <i data-feather="save" class="me-1"></i> Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<script>
if (typeof feather !== 'undefined') feather.replace();
</script>
