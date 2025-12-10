<?php
/**
 * about.php (FRONTEND)
 * Mengambil data dari tabel: halaman_about
 */

// Mulai sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
$cart_count = $is_logged_in ? count($_SESSION['cart'] ?? []) : 0;

// Koneksi database
require_once 'koneksi.php';

// Data default (jika database kosong)
$about_data = [
    'judul_halaman'      => 'Tentang Kami',
    'sub_judul_halaman'  => 'Informasi tentang toko kami.',
    'visi'               => 'Visi kami akan tampil di sini.',
    'misi'               => ['Misi belum diatur.'],
    'teks_cta'           => 'Belanja Sekarang',
    'link_maps'          => ''
];

if ($koneksi instanceof mysqli) {
    $query = "SELECT * FROM halaman_about WHERE id = 1";
    $result = $koneksi->query($query);

    if ($result && $result->num_rows > 0) {
        $db = $result->fetch_assoc();

        $about_data['judul_halaman']     = htmlspecialchars($db['judul_halaman']);
        $about_data['sub_judul_halaman'] = htmlspecialchars($db['sub_judul_halaman']);
        $about_data['visi']              = htmlspecialchars($db['visi']);
        $about_data['teks_cta']          = htmlspecialchars($db['teks_cta']);
        $about_data['link_maps']         = $db['link_maps'];

        // Misi → pecah baris
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
    <title><?= $about_data['judul_halaman']; ?> - TokoOnlineku</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

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
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="toko_sepatu.php">TokoOnlineku</a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="toko_sepatu.php">Beranda</a></li>
                <li class="nav-item"><a class="nav-link active" href="about.php">Tentang Kami</a></li>
                <li class="nav-item">
                    <a class="nav-link"><i class="bi bi-cart3"></i> Keranjang (<?= $cart_count ?>)</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- HEADER -->
<header class="hero-bg p-5 text-center shadow-sm mb-5">
    <h1 class="display-4 fw-bold"><?= $about_data['judul_halaman']; ?></h1>
    <p class="fs-5 text-light"><?= $about_data['sub_judul_halaman']; ?></p>
</header>

<!-- KONTEN UTAMA -->
<div class="container">

    <!-- Visi -->
    <div class="bg-white p-5 rounded-4 shadow-sm mb-5">
        <h2 class="text-primary fw-bold mb-3">Visi Kami</h2>
        <p class="lead text-dark"><?= nl2br($about_data['visi']); ?></p>
    </div>

    <!-- Misi -->
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

    <!-- Maps -->
    <?php if (!empty($about_data['link_maps'])): ?>
        <div class="mb-5">
            <h2 class="section-title text-dark">Lokasi Kami</h2>
            <div class="ratio ratio-16x9 shadow rounded-4">
                <?= $about_data['link_maps']; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Fitur -->
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

    <!-- CTA -->
    <div class="text-center bg-primary p-5 text-white rounded-4 shadow mt-5">
        <h3 class="fw-bold"><?= $about_data['teks_cta']; ?></h3>
        <a href="toko_sepatu.php" class="btn btn-warning btn-lg mt-3 fw-bold">
            <i class="bi bi-bag-check-fill me-2"></i> Belanja Sekarang
        </a>
    </div>

</div>

<!-- FOOTER -->
<footer class="mt-5">
    <?php 
    if (file_exists('footer_user.php')) {
        include 'footer_user.php';
    } else {
        echo '<div class="container text-center py-3 text-muted"><small>&copy; '.date('Y').' TokoOnlineku.</small></div>';
    }
    ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.addEventListener("load", () => document.body.classList.add('page-loaded'));
</script>

</body>
</html>
