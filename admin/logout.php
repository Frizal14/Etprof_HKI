<?php
/**
 * admin/logout.php
 * Logika untuk keluar dari sesi admin (Versi Final dan Aman untuk User).
 */

// 1. Pastikan sesi sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Tentukan pesan logout.
$logout_message_to_carry = 'Anda telah berhasil keluar dari akun Admin. Sampai jumpa lagi! 👋';

// 🔥 KUNCI PERBAIKAN: HANYA HAPUS VARIABEL SESI ADMIN 🔥
// JANGAN gunakan session_unset() atau session_destroy()
// Karena akan menghapus data login user (`$_SESSION['user_id']`).

unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);

// 3. Simpan pesan toast ke variabel sesi.
// Variabel sesi user (seperti user_id) TIDAK akan terpengaruh oleh penimpaan ini.
$_SESSION['logout_success_toast'] = $logout_message_to_carry;

// 4. Tulis sesi dan tutup file sesi SEBELUM REDIRECT.
// Ini adalah langkah penting untuk memastikan data toast tersimpan.
session_write_close(); 

// 5. Redirect ke halaman login admin
header("Location: login.php");
exit;
?>