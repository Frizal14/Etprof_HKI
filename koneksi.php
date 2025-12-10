<?php
/**
 * koneksi.php
 * File untuk membuat koneksi database dan mendefinisikan path global.
 */

// ===================================
// 1. DATABASE CONFIG
// ===================================
$host = 'localhost';
$user = 'root'; 
$password = ''; 
$database = 'ecommerce_native_db'; 

// Buat koneksi
$koneksi = new mysqli($host, $user, $password, $database);

// Cek koneksi
if ($koneksi->connect_error) {
    // Hentikan eksekusi dan tampilkan error jika koneksi gagal
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

$koneksi->set_charset("utf8mb4");

// ===================================
// 2. PATHS GLOBAL (Asumsi Lokasi Proyek Anda)
// ===================================

// A. PATH SERVER (Path absolut di sistem file, e.g., E:\laragon\www\e-commerce_sederhana\)
// Kita gunakan $_SERVER['DOCUMENT_ROOT'] lalu hapus bagian yang tidak perlu
$root_path_server = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

// Karena proyek Anda di E:\laragon\www\e-commerce_sederhana\, dan $koneksi.php ada di root
// Kita harus pastikan $root_path_server menunjuk ke E:\laragon\www\e-commerce_sederhana/
// Dalam environment Laragon, $_SERVER['DOCUMENT_ROOT'] mungkin menunjuk ke E:\laragon\www\
// Jika e-commerce_sederhana adalah subdirectory:
$sub_dir = '/e-commerce_sederhana/'; // SESUAIKAN JIKA NAMA FOLDER BEDA
$root_path_server .= $sub_dir; 
$root_path_server = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $root_path_server);

// B. ROUTER PATH (URL base aplikasi, e.g., http://localhost/e-commerce_sederhana/)
// Digunakan untuk redirect dan path gambar di browser
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$router_path = $protocol . $_SERVER['HTTP_HOST'] . $sub_dir;

// ===================================
// 3. TEGASKAN VARIABEL INI GLOBAL
// ===================================
// Semua variabel yang didefinisikan di sini harus diulang menggunakan 'global' di file yang menggunakannya.
global $koneksi, $root_path_server, $router_path; 

?>