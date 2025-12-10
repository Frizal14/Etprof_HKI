<?php
/**
 * admin/settings/kontak/proses.php
 * Script pemrosesan Tambah (CREATE) dan Edit (UPDATE) data kontak.
 */

// 1. Cek dan mulai sesi hanya jika belum aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Tentukan BASE PATH Absolut (Mundur 3 level ke root project)
// Ini adalah untuk require_once file di server.
$root_path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR; 

// 3. Memuat Koneksi dan Autentikasi
require_once $root_path . 'koneksi.php'; 
require_once $root_path . 'admin_auth.php'; 

global $koneksi;

// Tentukan router_path
$router_path = 'dashboard.php'; 

// 🔥 PERBAIKAN REDIRECT UTAMA 🔥
// 4. Bangun URL ABSOLUT LENGKAP untuk redirect browser
// Ini mengatasi masalah redirect yang mengarahkan ke localhost/ tanpa folder project
$project_folder_name = 'e-commerce_sederhana';
$base_http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}";

// URL lengkap ke file dashboard.php di folder admin/
$redirect_url_full = $base_http . '/' . $project_folder_name . '/admin/' . $router_path . '?page=settings/kontak/index';

// ===============================================
// LOGIKA UTAMA (CRUD)
// ===============================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['crud_success_toast'] = "Akses tidak sah.";
    header('Location: ' . $redirect_url_full); // Menggunakan URL Full
    exit;
}

$action = $_POST['action_type'] ?? '';
$id_kontak = (int)($_POST['id_kontak'] ?? 0);
$nama_kontak = $koneksi->real_escape_string($_POST['nama_kontak']);
$tipe_kontak = $koneksi->real_escape_string($_POST['tipe_kontak']);
$nilai_kontak = $koneksi->real_escape_string($_POST['nilai_kontak']);
$prioritas = (int)($_POST['prioritas'] ?? 99);
$is_active = (int)($_POST['is_active'] ?? 1);

// Validasi dasar
if (empty($nama_kontak) || empty($tipe_kontak) || empty($nilai_kontak)) {
    $_SESSION['crud_success_toast'] = "Gagal! Semua field wajib diisi.";
    header('Location: ' . $redirect_url_full); // Menggunakan URL Full
    exit;
}

if ($action == 'tambah') {
    $sql = "INSERT INTO admin_kontak_entri (nama_kontak, tipe_kontak, nilai_kontak, prioritas, is_active) 
             VALUES ('$nama_kontak', '$tipe_kontak', '$nilai_kontak', $prioritas, $is_active)";
    
    if (@$koneksi->query($sql)) {
        $_SESSION['crud_success_toast'] = "Kontak baru **{$nama_kontak}** berhasil ditambahkan!";
    } else {
        if ($koneksi->errno == 1062) {
            $_SESSION['crud_success_toast'] = "Gagal menambah kontak: Nilai kontak/URL sudah ada. Harap gunakan nilai yang unik.";
        } else {
            $_SESSION['crud_success_toast'] = "Gagal menambah kontak: " . $koneksi->error;
        }
    }
} 
elseif ($action == 'edit' && $id_kontak > 0) {
    $sql = "UPDATE admin_kontak_entri SET
            nama_kontak = '$nama_kontak',
            tipe_kontak = '$tipe_kontak',
            nilai_kontak = '$nilai_kontak',
            prioritas = $prioritas,
            is_active = $is_active
            WHERE id = $id_kontak";

    if (@$koneksi->query($sql)) {
        $_SESSION['crud_success_toast'] = "Kontak **{$nama_kontak}** berhasil diperbarui!";
    } else {
         if ($koneksi->errno == 1062) {
            $_SESSION['crud_success_toast'] = "Gagal memperbarui kontak: Nilai kontak/URL sudah digunakan oleh entri lain.";
        } else {
            $_SESSION['crud_success_toast'] = "Gagal memperbarui kontak: " . $koneksi->error;
        }
    }
}

// Redirect ke halaman index menggunakan URL web absolut lengkap
header('Location: ' . $redirect_url_full);
exit;