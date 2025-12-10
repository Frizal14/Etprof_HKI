<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$koneksi = $GLOBALS['koneksi'];

$id = $_POST['id'];

// Ambil Data
$judul = $_POST['judul_halaman'];
$sub_judul = $_POST['sub_judul_halaman'];
$visi = $_POST['visi'];
$misi = $_POST['misi'];
$cta = $_POST['teks_cta'];
$maps = $koneksi->real_escape_string($_POST['link_maps']);

// Upload function
function uploadFile($field, $folder = "uploads/") {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;

    $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
    $nama = uniqid() . "." . $ext;

    move_uploaded_file($_FILES[$field]['tmp_name'], $folder . $nama);
    return $nama;
}

$gambar_visi = uploadFile("gambar_visi");
$gambar_misi = uploadFile("gambar_misi");

// Update Query
$query = "UPDATE halaman_about SET 
    judul_halaman = '$judul',
    sub_judul_halaman = '$sub_judul',
    visi = '$visi',
    misi = '$misi',
    teks_cta = '$cta',
    link_maps = '$maps'
";

if ($gambar_visi) $query .= ", gambar_visi = '$gambar_visi'";
if ($gambar_misi) $query .= ", gambar_misi = '$gambar_misi'";

$query .= " WHERE id = $id";

// Eksekusi
if ($koneksi->query($query)) {
    $_SESSION['status_message'] = [
        'type' => 'success',
        'text' => 'Perubahan berhasil disimpan!'
    ];
} else {
    $_SESSION['status_message'] = [
        'type' => 'danger',
        'text' => 'Gagal menyimpan perubahan: ' . $koneksi->error
    ];
}

// Redirect kembali ke index
header("Location: dashboard.php?page=settings/kelola_about/index");
exit;
