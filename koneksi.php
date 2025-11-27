<?php
/**
 * koneksi.php
 * File untuk membuat koneksi database.
 */

$host = 'localhost';
$user = 'root'; // Ganti dengan user database Anda
$password = ''; // Ganti dengan password database Anda
$database = 'ecommerce_native_db'; // Pastikan sesuai dengan nama database yang Anda buat

// Buat koneksi
$koneksi = new mysqli($host, $user, $password, $database);
// PENTING: Tegaskan variabel ini sebagai global
global $koneksi;

// Cek koneksi
if ($koneksi->connect_error) {
    // Hentikan eksekusi dan tampilkan error jika koneksi gagal
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Opsional: Atur character set ke utf8mb4
$koneksi->set_charset("utf8mb4");

// Catatan: Variabel $koneksi akan digunakan di semua file lain yang membutuhkan akses database.
?>