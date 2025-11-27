<?php
/**
 * user_profile_handler.php
 * Menangani logika pemrosesan formulir update profil.
 * DIUBAH: Menggunakan Sweet Alert (SWAL) untuk notifikasi sukses.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Pastikan user sudah login dan request menggunakan method POST
    header("Location: login_user.php");
    exit();
}

require_once 'koneksi.php'; // Ambil koneksi database

$user_id = $_SESSION['user_id'];
$new_name = trim($_POST['name'] ?? '');
$delete_photo = (isset($_POST['delete_photo']) && $_POST['delete_photo'] == 1);
$profile_upload_dir = 'uploads/user_profiles/';

// Pastikan nama tidak kosong
if (empty($new_name)) {
    $_SESSION['toast_message'] = "Nama tidak boleh kosong.";
    $_SESSION['toast_type'] = "error";
    header("Location: user_profile.php");
    exit();
}


// --- 1. Ambil Path Foto Lama (diperlukan untuk hapus file lama) ---
$old_image_path = null;
$sql_old = "SELECT profile_image_path FROM users WHERE id = ?";
$stmt_old = $koneksi->prepare($sql_old);
$stmt_old->bind_param("i", $user_id);
$stmt_old->execute();
$result_old = $stmt_old->get_result();
if ($row_old = $result_old->fetch_assoc()) {
    $old_image_path = $row_old['profile_image_path'];
}
$stmt_old->close();


// --- 2. Logika Update Foto Profil ---
$new_image_path = $old_image_path; // Default: pertahankan foto lama

$is_image_uploaded = (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK);

if ($is_image_uploaded) {
    // Proses Upload Foto Baru
    $file_info = $_FILES['profile_image'];
    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 2 * 1024 * 1024; // 2MB

    // Validasi Tipe dan Ukuran File
    if (!in_array($file_info['type'], $allowed_types) || $file_info['size'] > $max_size) {
        $_SESSION['toast_message'] = "Gagal. Foto harus JPG/PNG dan maksimal 2MB.";
        $_SESSION['toast_type'] = "error";
        header("Location: user_profile.php");
        exit();
    }
    
    // Generate nama file unik
    $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
    $unique_filename = uniqid('user_') . '.' . $extension;
    $target_file = $profile_upload_dir . $unique_filename;

    // Pindahkan file ke folder tujuan
    if (move_uploaded_file($file_info['tmp_name'], $target_file)) {
        $new_image_path = $unique_filename; // Simpan nama file ke database
        
        // Hapus foto lama di server (jika ada)
        if (!empty($old_image_path) && file_exists($profile_upload_dir . $old_image_path)) {
            unlink($profile_upload_dir . $old_image_path);
        }
    } else {
        $_SESSION['toast_message'] = "Gagal memindahkan file upload.";
        $_SESSION['toast_type'] = "error";
        header("Location: user_profile.php");
        exit();
    }
} elseif ($delete_photo) {
    // Logika Hapus Foto (jika checkbox dicentang)
    $new_image_path = null; // Set path di database menjadi NULL

    // Hapus file lama di server (jika ada)
    if (!empty($old_image_path) && file_exists($profile_upload_dir . $old_image_path)) {
        unlink($profile_upload_dir . $old_image_path);
    }
}


// --- 3. Update Database (Nama & Path Foto) ---
$sql_update = "UPDATE users SET name = ?, profile_image_path = ? WHERE id = ?";
$stmt_update = $koneksi->prepare($sql_update);

if ($stmt_update) {
    // Binding parameters: $new_image_path akan di-bind sebagai string,
    // yang akan disimpan sebagai NULL di MySQL jika nilainya NULL di PHP.
    $stmt_update->bind_param("ssi", $new_name, $new_image_path, $user_id);
    
    if ($stmt_update->execute()) {
        // 🔥 Perbaikan Foto Profil: Update data di SESSION agar langsung terbarui di navbar 🔥
        $_SESSION['user_name'] = $new_name;
        $_SESSION['user_profile_img'] = $new_image_path; 

        // 🔥 GANTI DARI TOAST KE SWEET ALERT (SWAL) UNTUK SUKSES 🔥
        $_SESSION['swal_icon'] = "success";
        $_SESSION['swal_title'] = "Perubahan Disimpan!";
        $_SESSION['swal_text'] = "Profil Anda telah berhasil diperbarui.";
        
        // Tambahkan flag untuk menampilkan modal secara otomatis di halaman user_profile.php (opsional)
        // Jika form ini dipanggil dari modal di toko_sepatu.php, Anda mungkin ingin mengarahkan kembali ke toko_sepatu.php
        // Dan menggunakan flag untuk membuka kembali modal
        // $_SESSION['show_profile_modal'] = true; 
        
    } else {
        // PERTAHANKAN TOAST UNTUK ERROR DATABASE
        $_SESSION['toast_message'] = "Gagal memperbarui database: " . $stmt_update->error;
        $_SESSION['toast_type'] = "error";
    }
    $stmt_update->close();
} else {
    // PERTAHANKAN TOAST UNTUK ERROR QUERY
    $_SESSION['toast_message'] = "Gagal mempersiapkan query update.";
    $_SESSION['toast_type'] = "error";
}

// Tutup koneksi dan redirect kembali ke halaman profil
$koneksi->close();
header("Location: user_profile.php");
exit();
?>