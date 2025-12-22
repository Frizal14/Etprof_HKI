<?php
/**
 * about.php (FRONTEND)
 * Mengambil data dari tabel: halaman_about DAN website_settings
 */

// Mulai sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Koneksi database
require_once 'koneksi.php';

// 1. DEFINISIKAN STATUS LOGIN
$is_logged_in = isset($_SESSION['user_id']);
$cart_count = $is_logged_in ? count($_SESSION['cart'] ?? []) : 0;

// 2. AMBIL DATA PENGATURAN WEBSITE (Dinamis: Nama Toko & Tagline)
// -----------------------------------------------------------
$site_name = 'TokoOnlineku'; // Default jika database belum disetting
$site_tagline = 'Gaya Setiap Aksi'; // Default

if (isset($koneksi) && $koneksi instanceof mysqli) {
    // Ambil settings dari tabel website_settings
    $sql_settings = "SELECT website_name, tagline FROM website_settings WHERE id = 1"; 
    $result_settings = $koneksi->query($sql_settings);

    if ($result_settings && $result_settings->num_rows > 0) {
        $settings_row = $result_settings->fetch_assoc();
        // Timpa variabel default dengan data dari database
        $site_name = htmlspecialchars($settings_row['website_name']);
        $site_tagline = htmlspecialchars($settings_row['tagline']);
    }

    // 3. AMBIL DATA KONTEN HALAMAN ABOUT
    $about_data = [
        'judul_halaman'      => 'Tentang Kami',
        'sub_judul_halaman'  => 'Informasi tentang toko kami.',
        'visi'               => 'Visi kami akan tampil di sini.',
        'misi'               => ['Misi belum diatur.'],
        'teks_cta'           => 'Belanja Sekarang',
        'link_maps'          => ''
    ];

    $query = "SELECT * FROM halaman_about WHERE id = 1";
    $result = $koneksi->query($query);

    if ($result && $result->num_rows > 0) {
        $db = $result->fetch_assoc();

        $about_data['judul_halaman']     = htmlspecialchars($db['judul_halaman']);
        $about_data['sub_judul_halaman'] = htmlspecialchars($db['sub_judul_halaman']);
        $about_data['visi']              = htmlspecialchars($db['visi']);
        $about_data['teks_cta']          = htmlspecialchars($db['teks_cta']);
        $about_data['link_maps']         = $db['link_maps'];

        if (!empty($db['misi'])) {
            $misi_list = explode("\n", trim($db['misi']));
            $about_data['misi'] = array_filter(array_map('trim', $misi_list));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $about_data['judul_halaman']; ?> - <?= $site_name ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        body {
            background: #f8f9fc;
            opacity: 0;
            transition: opacity 0.8s ease-out;
        }
        .page-loaded { opacity: 1; }

        .hero-bg {
            background: linear-gradient(120deg, #0d6efd, #00b894);
            color: white;
        }
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            position: relative;
        }
        .section-title::after {
            content: "";
            width: 70px;
            height: 4px;
            background: #0d6efd;
            display: block;
            margin: 10px auto;
            border-radius: 3px;
        }

        .feature-card {
            transition: .3s;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 25px rgba(0,0,0,.15);
        }

        /* --- UKURAN MAPS --- */
        .maps-container {
            width: 100%;
            height: 350px; 
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
            background-color: #eee;
        }
        
        .maps-container iframe {
            width: 100% !important;
            height: 100% !important;
            border: 0;
            display: block;
        }
        
        /* Footer Links Hover Effect */
        .footer-link {
            transition: color 0.2s ease-in-out;
        }
        .footer-link:hover {
            color: #0d6efd !important; /* Warna primary saat hover */
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="toko_sepatu.php">
            <i class="bi bi-arrow-left me-2"></i> Kembali ke Toko
        </a>
    </div>
</nav>

<header class="hero-bg p-5 text-center shadow-sm mb-5">
    <h1 class="display-4 fw-bold"><?= $about_data['judul_halaman']; ?></h1>
    <p class="fs-5 text-light"><?= $about_data['sub_judul_halaman']; ?></p>
</header>

<div class="container">

    <div class="bg-white p-5 rounded-4 shadow-sm mb-5">
        <h2 class="text-primary fw-bold mb-3">Visi Kami</h2>
        <p class="lead text-dark"><?= nl2br($about_data['visi']); ?></p>
    </div>

    <div class="bg-white p-5 rounded-4 shadow-sm mb-5">
        <h2 class="text-primary fw-bold mb-3">Misi Kami</h2>

        <ul class="list-group list-group-flush">
            <?php foreach ($about_data['misi'] as $m): ?>
                <li class="list-group-item bg-white border-0">
                    <i class="bi bi-check-circle-fill text-success me-2"></i> 
                    <?= htmlspecialchars($m); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if (!empty($about_data['link_maps'])): ?>
        <div class="mb-5 text-center">
            <h2 class="section-title text-dark">Lokasi Kami</h2>
            <p class="text-muted mb-4">Kunjungi toko fisik kami di lokasi berikut</p>
            
            <div class="maps-container mx-auto">
                <?= $about_data['link_maps']; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="text-center my-5">
        <h2 class="section-title">Mengapa Memilih Kami?</h2>

        <div class="row row-cols-1 row-cols-md-3 g-4 mt-4">

            <div class="col">
                <div class="card feature-card p-4 rounded-4 border-0 shadow-sm">
                    <i class="bi bi-patch-check-fill text-primary fs-1 mb-3"></i>
                    <h4 class="fw-bold">Produk Berkualitas</h4>
                    <p class="text-muted">Kami menjamin hanya produk terbaik untuk pelanggan.</p>
                </div>
            </div>

            <div class="col">
                <div class="card feature-card p-4 rounded-4 border-0 shadow-sm">
                    <i class="bi bi-truck fs-1 text-primary mb-3"></i>
                    <h4 class="fw-bold">Pengiriman Cepat</h4>
                    <p class="text-muted">Pesanan Anda akan tiba tepat waktu dan aman.</p>
                </div>
            </div>

            <div class="col">
                <div class="card feature-card p-4 rounded-4 border-0 shadow-sm">
                    <i class="bi bi-headset fs-1 text-primary mb-3"></i>
                    <h4 class="fw-bold">Layanan Pelanggan</h4>
                    <p class="text-muted">Kami siap membantu kapan saja.</p>
                </div>
            </div>

        </div>
    </div>

    <div class="text-center bg-primary p-5 text-white rounded-4 shadow mt-5">
        <h3 class="fw-bold"><?= $about_data['teks_cta']; ?></h3>
        <a href="toko_sepatu.php" class="btn btn-warning btn-lg mt-3 fw-bold">
            <i class="bi bi-bag-check-fill me-2"></i> Belanja Sekarang
        </a>
    </div>

</div>

<footer class="bg-dark text-white pt-5 pb-3 mt-5"> 
    <div class="container">
        <div class="row">
            
            <div class="col-md-4 mb-4">
                <h5 class="fw-bold text-uppercase text-primary mb-3"><?php echo $site_name; ?></h5>
                <p class="small text-white-50"><?php echo $site_tagline; ?>. Kami berkomitmen menyediakan produk dan perlengkapan terbaik dengan kualitas yang terjamin.</p>
                <a href="about.php" class="btn btn-sm btn-outline-light mt-2">Tentang Kami</a>
            </div>

            <div class="col-md-4 mb-4">
                <h5 class="fw-bold text-uppercase mb-3">Tautan Cepat</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="toko_sepatu.php" class="text-white-50 text-decoration-none footer-link">Beranda</a></li>
                    
                    <?php if ($is_logged_in): ?>
                    <li class="mb-2"><a href="orders_user.php" class="text-white-50 text-decoration-none footer-link">Pesanan Saya</a></li>
                    <li class="mb-2"><a href="cart.php" class="text-white-50 text-decoration-none footer-link">Keranjang Belanja</a></li>
                    <?php else: ?>
                    <li class="mb-2"><a href="login_user.php" class="text-white-50 text-decoration-none footer-link">Login/Daftar</a></li>
                    <?php endif; ?>
                    
                    <li class="mb-2"><a href="about.php" class="text-white-50 text-decoration-none footer-link">Pusat Bantuan</a></li>
                </ul>
            </div>

            <div class="col-md-4 mb-4">
                <h5 class="fw-bold text-uppercase mb-3">Layanan Pelanggan</h5>
                <p class="small text-white-50 mb-3">Punya pertanyaan seputar produk atau pesanan Anda? Tim kami siap membantu.</p>
                
                <a href="kontak.php" class="btn btn-primary text-white fw-bold">
                    <i class="fas fa-envelope me-2"></i> Hubungi Kami
                </a>
            </div>

        </div>
        
        <hr class="my-4 border-secondary">

        <div class="text-center">
            <p class="mb-1 text-white-50">&copy; <?php echo date('Y'); ?> <?php echo $site_name; ?>. Hak Cipta Dilindungi.</p>
            <p class="small text-muted">Dibuat dengan semangat untuk gaya dan kenyamanan Anda.</p>
        </div>
        
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.addEventListener("load", () => document.body.classList.add('page-loaded'));
</script>

</body>
</html>