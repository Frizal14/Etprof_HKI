<?php
/**
 * admin/dashboard.php
 * File Router Utama Admin. Mengatur Sesi, Koneksi, dan Memuat Konten Halaman.
 */

// 1. SESI DAN AUTENTIKASI
// 🔥 PERBAIKAN: Pastikan session_start() dipanggil paling awal
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 🔄 PENYESUAIAN: Mengambil pesan sukses dari sesi.
$success_toast_message = null;

// Prioritas 1: Pesan Sukses Login (dari login.php)
if (isset($_SESSION['login_success_toast'])) {
    $success_toast_message = $_SESSION['login_success_toast'];
    unset($_SESSION['login_success_toast']);
} 
// Prioritas 2: Pesan Sukses CRUD umum (untuk aksi Tambah/Ubah/Hapus)
else if (isset($_SESSION['crud_success_toast'])) {
    $success_toast_message = $_SESSION['crud_success_toast'];
    unset($_SESSION['crud_success_toast']);
}


// Periksa status login
require_once '../admin_auth.php'; 

// 2. KONEKSI DATABASE
// Pastikan path ke koneksi.php benar relatif terhadap dashboard.php
require_once '../koneksi.php'; 
global $koneksi; 

$GLOBALS['koneksi'] = $koneksi; 

// 3. LOGIKA ROUTER
$page = $_GET['page'] ?? 'dashboard/main'; 
// Menghapus titik-titik ('..') untuk mencegah directory traversal di router yang sederhana
$page = str_replace(['../', '..\\'], '', $page); 
$content_file = __DIR__ . '/' . $page . '.php'; 

$router_path = 'dashboard.php'; 
$admin_page = $page; 
$admin_username = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); 

// PATH MANAGEMENT (Tidak ada perubahan di sini)
$root_path_server = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR; 
$uploads_path_php_file_op = $root_path_server . 'uploads' . DIRECTORY_SEPARATOR . 'product_images' . DIRECTORY_SEPARATOR;
$GLOBALS['uploads_path_php_file_op'] = $uploads_path_php_file_op; 
$uploads_path_payments_op = $root_path_server . 'uploads' . DIRECTORY_SEPARATOR . 'payments' . DIRECTORY_SEPARATOR;
$GLOBALS['uploads_path_payments_op'] = $uploads_path_payments_op; 

// Browser URL Paths
$base_url_path = '/e-commerce_sederhana/'; 
$uploads_path = $base_url_path . 'uploads/product_images/'; 
$GLOBALS['uploads_path'] = $uploads_path; 
$uploads_path_payments = $base_url_path . 'uploads/payments/';
$GLOBALS['uploads_path_payments'] = $uploads_path_payments; 

$content_output = ''; 

// OUTPUT BUFFERING
if (file_exists($content_file)) {
    ob_start(); 
    include $content_file; 
    $content_output = ob_get_clean(); 
} else {
    // 404 Error Display
    $content_output = '<div class="alert alert-danger mt-4">Halaman **' . htmlspecialchars($page) . '** tidak ditemukan. Pastikan file `admin/' . htmlspecialchars($page) . '.php` tersedia.</div>';
    http_response_code(404);
}

// 🔥 PERBAIKAN: HAPUS PENUTUPAN KONEKSI DI SINI. 
// Biarkan PHP menutup koneksi secara otomatis di akhir skrip.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Toko SepatuKu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        /* (CSS Styles) */
        
        #layoutSidenav {
            display: flex;
            min-height: 100vh;
        }

        /* 1. CSS SIDEBAR SCROLLABLE & POSITIONING */
        #layoutSidenav_nav {
            flex-shrink: 0;
            width: 250px; /* Lebar default sidebar */
            transition: margin-left 0.3s ease;
            position: fixed; 
            top: 0;
            left: 0;
            bottom: 0; /* Mengambil 100% tinggi viewport */
            z-index: 1030; 
            
            /* 🔥 KOREKSI UTAMA UNTUK SCROLL */
            overflow-y: auto !important; /* Memastikan scroll vertikal aktif */
            overflow-x: hidden !important; /* Mencegah scroll horizontal yang mengganggu */
        }
        
        /* 2. CSS CONTENT UNTUK MENGGESER SAAT SIDEBAR MUNCUL/HILANG */
        #layoutSidenav_content {
            flex-grow: 1;
            padding: 0;
            background-color: #f8f9fa;
            margin-left: 250px; /* Jarak default konten dari sidebar */
            transition: margin-left 0.3s ease;
        }

        /* 3. CSS UNTUK MENYEMBUNYIKAN SIDEBAR (Kelas yang ditambahkan JS) */
        body.sb-sidenav-toggled #layoutSidenav_nav {
            margin-left: -250px; /* Geser ke kiri untuk menyembunyikan */
        }
        body.sb-sidenav-toggled #layoutSidenav_content {
            margin-left: 0; /* Konten kembali ke posisi kiri penuh */
        }

        /* Responsive adjustments for mobile */
        @media (max-width: 991.98px) {
            #layoutSidenav_nav {
                margin-left: -250px; /* Sembunyikan default di mobile */
            }
            #layoutSidenav_content {
                margin-left: 0;
            }
            body.sb-sidenav-toggled #layoutSidenav_nav {
                margin-left: 0; /* Tampilkan di mobile saat toggle */
            }
        }
        
        /* Styling Navbar Atas */
        .admin-navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1020; 
        }
        
        /* Gaya Tautan Logout */
        .logout-link {
            color: #dc3545 !important; 
            font-weight: bold;
            transition: all 0.3s ease;
            border: 1px solid #dc3545; 
        }
        .logout-link:hover {
            background-color: #e35a68; 
            color: white !important; 
            border-color: #e35a68; 
        }
        
        /* Gaya Hover untuk Nama Admin */
        .nav-link.admin-username-display {
            transition: color 0.3s ease;
            cursor: default; 
        }
        .nav-link.admin-username-display:hover {
            color: #adb5bd !important; 
        }

        /* Styling Main Content */
        main {
            padding: 1.5rem;
        }
        
        /* Styling Toast Container */
        .toast-container-dashboard {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 2000; 
        }
        .btn-close-white {
            filter: invert(1);
        }
        /* Style Feather Icons */
        .feather {
             width: 1rem;
             height: 1rem;
             vertical-align: text-bottom;
        }

        /* 🔥 TAMBAHAN: CSS untuk Tombol Scroll-to-Bottom */
        .floating-scroll-btn {
            position: fixed; 
            right: 20px; 
            bottom: 20px; 
            z-index: 1050; 
            width: 45px;
            height: 45px;
            border-radius: 50%; 
            background-color: #00b894; /* Menggunakan warna yang sama dengan sidebar active link */
            color: white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            border: none;
            transition: background-color 0.3s, opacity 0.3s, transform 0.3s;
            display: none; 
            cursor: pointer;
            font-size: 1.2em;
            line-height: 45px;
            text-align: center;
            opacity: 0.8;
        }

        .floating-scroll-btn:hover {
            background-color: #009975;
            opacity: 1;
            transform: scale(1.05);
        }

        /* ------------------------------------------------------------------ */
        /* 🔥 MULAI: CSS KHUSUS UNTUK CETAK/PRINT PDF (HANYA TABEL) 🔥 */
        @media print {
            /* 1. Sembunyikan semua elemen navigasi dan layout */
            #layoutSidenav_nav,           /* Sidebar */
            .admin-navbar,               /* Navbar Atas */
            .sidebar-footer,             /* Footer Sidebar */
            .floating-scroll-btn,        /* Tombol Scroll */
            .toast-container-dashboard,  /* Toast */
            /* Judul Halaman dan Deskripsi */
            main > .container-fluid > h1, 
            main > .container-fluid > p.lead,
            .alert-danger,               /* Pesan Error Umum */
            /* Menyembunyikan baris KARTU RINGKASAN (4 Kartu) */
            .row:has(> .col-xl-3)        
            {
                display: none !important;
            }

            /* 2. Sembunyikan semua tombol dan link di dalam card report */
            .card-header .dropdown,      /* Tombol Unduh/Dropdown di Header Laporan */
            .card-header .btn-outline-secondary, /* Tombol Cetak */
            .card-body a.btn,            /* Tombol Lihat Semua Pesanan/Riwayat */
            .card-body p                 /* Hapus paragraf deskriptif */
            {
                display: none !important;
            }


            /* 3. Pastikan konten utama mengambil lebar penuh */
            #layoutSidenav_content {
                margin-left: 0 !important;
                padding: 0 !important;
                background-color: white !important; /* Latar belakang putih */
            }

            /* 4. Atur Margin dan style untuk Halaman Cetak */
            main {
                padding: 0.5cm !important; /* Margin kecil untuk kertas */
            }
            
            /* Tampilkan hanya laporan yang diminta */
            .card {
                box-shadow: none !important; /* Hapus shadow pada kartu */
                border: 1px solid #ccc;     /* Tambahkan border ringan pada kartu laporan */
                page-break-inside: auto;    /* Izinkan pemotongan halaman di dalam kartu */
                margin-top: 0;
            }

            /* Pastikan header tercetak dengan warna latar */
            .card-header {
                font-size: 1.1em;
                color: #333 !important;
                background-color: #f0f0f0 !important;  
            }

            /* Hapus padding dari container-fluid yang mungkin tersisa */
            .container-fluid {
                padding: 0 !important;
                margin: 0 !important;
            }
        }
        /* 🔥 AKHIR: CSS KHUSUS UNTUK CETAK/PRINT PDF 🔥 */
        /* ------------------------------------------------------------------ */
        
    </style>
</head>
<body>

    <div id="layoutSidenav">
        
        <?php 
        // Asumsi file sidebar.php tersedia dan sudah diperbaiki
        include 'sidebar.php'; 
        ?>

        <div id="layoutSidenav_content">
            
            <nav class="admin-navbar navbar navbar-expand-lg navbar-light bg-white border-bottom">
                <div class="container-fluid">
                    
                    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#">
                        <i data-feather="menu" style="width: 24px; height: 24px;"></i>
                    </button>
                    
                    <a class="navbar-brand text-dark fw-bold" href="dashboard.php">
                        <i data-feather="command" class="me-2 text-primary feather"></i> Administrator
                    </a> 
                    
                    <div class="collapse navbar-collapse" id="adminNavbarContent">
                        <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                             <li class="nav-item me-3">
                                 <span class="nav-link text-muted admin-username-display"><i data-feather="user" class="me-1 feather"></i> Selamat Datang, <?php echo $admin_username; ?>!</span>
                             </li>
                            
                            <li class="nav-item">
                                <a class="nav-link logout-link btn btn-outline-danger btn-sm" 
                                    href="logout.php"
                                    onclick="return confirm('Apakah Anda yakin ingin keluar dari Admin Panel?');">
                                    <i data-feather="log-out" class="me-1 feather"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <main>
                <div class="container-fluid">
                    <?php echo $content_output; ?>
                </div>
            </main>
            
        </div>
    </div>

    <div class="toast-container-dashboard">
        <div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" id="successToast" data-bs-delay="4000">
            <div class="d-flex">
                <div class="toast-body">
                    <i data-feather="check-circle" class="me-2 feather"></i> 
                    <span id="toastMessageContent"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    
    <button id="scrollToBottomBtn" class="floating-scroll-btn" title="Scroll ke Bawah">
        <i data-feather="arrow-down"></i>
    </button>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/feather-icons"></script> 
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // INISIALISASI FEATHER ICONS (untuk semua ikon, termasuk yang baru di tombol scroll)
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // LOGIKA JAVASCRIPT UNTUK SIDEBAR TOGGLE
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            // Memuat status toggle dari localStorage
            if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
                document.body.classList.add('sb-sidenav-toggled');
            }
            
            sidebarToggle.addEventListener('click', function (event) {
                event.preventDefault();
                document.body.classList.toggle('sb-sidenav-toggled');
                // Menyimpan status toggle ke localStorage
                localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
            });
        }

        // 🔄 LOGIKA JAVASCRIPT UNTUK MENAMPILKAN TOAST SUKSES
        const successMessage = <?php echo json_encode($success_toast_message); ?>;

        if (successMessage && successMessage.trim() !== "") {
            const toastElement = document.getElementById('successToast');
            const messageContent = document.getElementById('toastMessageContent');
            
            messageContent.textContent = successMessage;
            
            const successToast = new bootstrap.Toast(toastElement);
            successToast.show();
        }

        // 🔥 LOGIKA JAVASCRIPT UNTUK TOMBOL SCROLL-TO-BOTTOM
        const scrollBtn = document.getElementById('scrollToBottomBtn');
        
        function toggleScrollButton() {
            // Sembunyikan jika halaman terlalu pendek atau jika sudah berada di bawah
            const buffer = 50; // Jarak buffer dari bawah
            const totalHeight = document.body.offsetHeight;
            const scrolledHeight = window.scrollY + window.innerHeight;

            if (totalHeight > window.innerHeight && scrolledHeight < totalHeight - buffer) {
                scrollBtn.style.display = "block";
            } else {
                scrollBtn.style.display = "none";
            }
        }

        window.addEventListener('scroll', toggleScrollButton);
        window.addEventListener('resize', toggleScrollButton);
        toggleScrollButton(); // Jalankan sekali saat load

        scrollBtn.addEventListener('click', function() {
            window.scrollTo({
                top: document.body.scrollHeight,
                behavior: 'smooth'
            });
        });

    });
    </script>
</body>
</html>