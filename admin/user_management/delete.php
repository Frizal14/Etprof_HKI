<?php
/**
 * admin/user_management/delete.php
 * Logika penghapusan pelanggan (DELETE), sekarang termasuk penghapusan file foto profil.
 * Di-include oleh admin/dashboard.php.
 */

$db_koneksi = $GLOBALS['koneksi'] ?? null;
// Tentukan folder upload profil (Relatif ke dashboard.php)
$profile_upload_dir = '../uploads/user_profiles/'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $delete_id = (int)$_POST['id'];

    if ($delete_id > 0) {
        // 1. Ambil path foto profil pengguna sebelum dihapus dari database
        $image_to_delete = null;
        $stmt_select = $db_koneksi->prepare("SELECT profile_image_path FROM users WHERE id = ?");
        $stmt_select->bind_param("i", $delete_id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        
        if ($row = $result_select->fetch_assoc()) {
            $image_to_delete = $row['profile_image_path'];
        }
        $stmt_select->close();

        // 2. Hapus data pengguna dari database
        $stmt_delete = $db_koneksi->prepare("DELETE FROM users WHERE id = ?");
        $stmt_delete->bind_param("i", $delete_id);
        
        if ($stmt_delete->execute()) {
            
            // 3. Jika penghapusan database sukses, hapus file foto profil dari server
            if (!empty($image_to_delete)) {
                $file_path = $profile_upload_dir . $image_to_delete;
                
                // Pastikan file tersebut ada sebelum mencoba menghapus
                if (file_exists($file_path)) {
                    // Gunakan @unlink untuk menekan error jika gagal hapus file
                    // Walaupun gagal, kita tetap anggap penghapusan user berhasil (data DB sudah hilang)
                    @unlink($file_path); 
                    // Optional: Tambahkan pengecekan apakah unlink berhasil untuk logging/debug
                }
            }
            
            $_SESSION['crud_success_toast'] = 'Pelanggan ID **' . $delete_id . '** dan fotonya (jika ada) berhasil dihapus.';
            
        } else {
            $_SESSION['crud_success_toast'] = '<div class="alert alert-danger">Gagal menghapus pelanggan.</div>';
        }
        $stmt_delete->close();
    } else {
        $_SESSION['crud_success_toast'] = '<div class="alert alert-danger">ID pelanggan tidak valid.</div>';
    }
} else {
    // Akses langsung ke file delete tanpa POST
    $_SESSION['crud_success_toast'] = '<div class="alert alert-warning">Akses tidak valid.</div>';
}

// Redirect selalu kembali ke halaman daftar
header('Location: ' . $router_path . '?page=user_management/index');
exit;