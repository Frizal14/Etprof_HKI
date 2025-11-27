<?php
/**
 * logout_user.php
 * Logika untuk keluar dari sesi pengguna (user).
 * DIUBAH untuk menggunakan Sweet Alert (SWAL) untuk pesan sukses.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Definisikan pesan Sweet Alert (SWAL) untuk sukses logout
$swal_title_to_carry = "Sampai Jumpa!";
$swal_text_to_carry = "👋 Anda berhasil keluar. Silakan login kembali untuk melanjutkan transaksi!";
$swal_icon_to_carry = "info"; // Menggunakan 'info' untuk logout, bukan 'success'

// Hapus variabel sesi pengguna spesifik
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_profile_img']); 
unset($_SESSION['user_email']); 
unset($_SESSION['is_logged_in']);
unset($_SESSION['cart']); 

// 2. Ambil nilai SWAL yang akan dibawa sebelum menghancurkan sesi
// Kita menggunakan variabel lokal untuk menampung pesan.
$swal_data = [
    'swal_title' => $swal_title_to_carry,
    'swal_text' => $swal_text_to_carry,
    'swal_icon' => $swal_icon_to_carry,
];

// Menghapus seluruh sesi
session_unset(); // Membersihkan semua variabel sesi
session_destroy();

// 3. Mulai sesi baru dan setel variabel SWAL untuk halaman berikutnya
session_start();

// Setel variabel sesi SWAL yang baru
$_SESSION['swal_title'] = $swal_data['swal_title'];
$_SESSION['swal_text'] = $swal_data['swal_text'];
$_SESSION['swal_icon'] = $swal_data['swal_icon'];

// 4. Redirect ke halaman utama toko
// Halaman toko_sepatu.php akan mendeteksi variabel sesi SWAL ini dan menampilkannya.
header("Location: toko_sepatu.php");
exit;
?>