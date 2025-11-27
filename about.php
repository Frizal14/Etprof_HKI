<?php
/**
 * about.php
 * Halaman detail 'Tentang Kami' untuk frontend.
 * Konten Diperbarui: Fokus pada 'TokoOnlineku' (toko umum), mempertahankan styling modern.
 * Perbaikan: Menambahkan styling responsif untuk Mobile dan Tablet.
 */

// 1. Mulai Sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek status login (untuk fungsionalitas keranjang/navigasi lain jika diperlukan)
$is_logged_in = isset($_SESSION['user_id']);

// Asumsi 'koneksi.php' berisi koneksi database ($koneksi)
// require_once 'koneksi.php'; // Ambil koneksi database

// Hitung item di keranjang (hanya jika login)
$cart_count = $is_logged_in ? count($_SESSION['cart'] ?? []) : 0; 

// PENTING: Untuk keperluan demonstrasi, $koneksi diabaikan jika file koneksi tidak ada
// Jika 'koneksi.php' dihilangkan, pastikan variabel $koneksi tidak digunakan untuk menghindari error.

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - TokoOnlineku</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        /* ========================================= */
        /* CSS KHUSUS UNTUK ABOUT.PHP */
        /* ========================================= */

        /* Transisi dan Animasi Fade */
        body {
            overflow-x: hidden; 
            opacity: 0; 
            transition: opacity 0.8s ease-out; 
            background-color: #f5f7fa; 
        }
        
        .page-loaded {
            opacity: 1; 
        }

        /* Gradient untuk Header/Hero Section */
        .hero-gradient-bg {
            background: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%);
        }
        
        /* Ikon Fitur */
        .feature-icon-gradient {
            font-size: 3.5rem; 
            color: #0d6efd; 
        }

        /* Style untuk judul section yang lebih fokus */
        .text-section-title {
            font-size: 2.5rem;
            margin-bottom: 25px;
            position: relative;
            font-weight: 700;
        }
        .text-section-title::after {
            content: '';
            display: block;
            width: 70px;
            height: 4px;
            background: #0d6efd;
            margin: 8px auto 0;
            border-radius: 2px;
        }
        
        /* Placeholder Image */
        .about-image-placeholder {
            min-height: 350px; 
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #495057;
            border-radius: 10px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-align: center; /* Tambahkan ini agar teks di dalam placeholder tidak keluar baris */
        }
        /* Penyesuaian tinggi placeholder untuk Mobile */
        @media (max-width: 767.98px) {
             .about-image-placeholder {
                min-height: 200px; /* Kurangi tinggi di layar kecil */
                font-size: 1.2rem;
                margin-top: 20px; /* Tambahkan margin agar terpisah dari teks di atas */
            }
        }

        /* Divider lebih halus */
        .featurette-divider {
            margin: 4rem 0;
            border-top: 2px solid rgba(0, 0, 0, 0.05);
            opacity: 1;
        }

        /* CTA Gradient */
        .cta-gradient-bg {
            background: linear-gradient(90deg, #17a2b8, #007bff); /* Teal ke Biru */
            color: white;
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
        }
        
        /* ========================================= */
        /* MEDIA QUERIES UNTUK RESPONSIVITAS */
        /* ========================================= */

        /* LAYAR KECIL (MOBILE - di bawah 576px, atau penyesuaian untuk 768px ke bawah) */
        @media (max-width: 767.98px) {
            
            /* Header */
            header {
                padding: 3rem 1rem !important; /* Padding lebih kecil */
            }
            header .display-3 {
                font-size: 2rem; /* Ukuran font lebih kecil */
            }
            header .fs-5 {
                font-size: 1rem !important;
            }

            /* Judul Section */
            .text-section-title {
                font-size: 2rem;
            }
            
            /* Featurette (Visi & Misi) */
            .featurette {
                padding: 3rem 1.5rem !important; /* Padding lebih kecil pada konten */
            }
            .featurette h2 {
                font-size: 1.5rem; /* Judul featurette lebih kecil */
            }
            .featurette .lead {
                 font-size: 1rem;
            }
            .featurette .list-group-item {
                 padding-left: 0 !important;
            }
            
            /* Tata Letak Featurette (Visi & Misi) */
            /* Pastikan urutan gambar/teks di mobile sesuai dengan kebutuhan (biasanya teks duluan) */
            .col-md-7.order-md-1, .col-md-5.order-md-2, .col-md-7.order-md-2, .col-md-5.order-md-1 {
                order: initial !important; /* Hapus pengurutan di mobile agar block-nya stack secara normal */
            }
        
            /* Fitur 3 Kolom */
            .feature-icon-gradient {
                font-size: 2.5rem; /* Ukuran ikon fitur lebih kecil */
            }
            .card h3 {
                font-size: 1.25rem !important;
            }
            .card p {
                font-size: 0.9rem;
            }
            .card {
                height: auto !important; /* Biarkan tinggi card menyesuaikan konten */
            }

            /* CTA */
            .cta-gradient-bg {
                padding: 3rem 1rem !important;
            }
            .cta-gradient-bg h3 {
                font-size: 1.5rem;
            }
        }
        
        /* LAYAR TABLET (768px - 991.98px) */
        @media (min-width: 768px) and (max-width: 991.98px) {
            
            /* Header */
            header .display-3 {
                font-size: 2.5rem;
            }
            
            /* Featurette (Visi & Misi) */
             .featurette {
                padding: 4rem 2rem !important;
            }
            .featurette h2 {
                font-size: 1.8rem;
            }
            
            /* Placeholder Image */
            .about-image-placeholder {
                min-height: 280px;
                font-size: 1.5rem;
            }

            /* Fitur 3 Kolom */
            .feature-icon-gradient {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body class="bg-light"> 

<header class="p-5 text-center hero-gradient-bg border-bottom mb-5 shadow-sm">
    <div class="container">
        <h1 class="display-3 fw-bold text-dark">Mengenal TokoOnlineku</h1>
        <p class="fs-5 text-muted">Kisah di balik komitmen kami untuk memberikan produk terbaik bagi Anda.</p>
        <a href="toko_sepatu.php" class="btn btn-primary btn-lg mt-3 shadow-lg">
            <i class="bi bi-arrow-left-circle me-2"></i> Kembali ke Beranda
        </a>
    </div>
</header>
<div class="container my-5">
    
<div class="row featurette align-items-center bg-white p-5 rounded-4 shadow-lg border border-light">
    <div class="col-md-7 order-md-1">
        <h2 class="fw-bolder mb-3 text-primary">Visi Kami: Kualitas & Kepuasan Pelanggan.</h2>
        
        <p class="lead text-dark-emphasis text-justify">
            TokoOnlineku didirikan pada tahun 2020 dengan visi sederhana: menjadi platform andalan yang menyediakan produk berkualitas tinggi dari berbagai kategori kepada seluruh pelanggan di Indonesia.
        </p>
        
        <p class="text-muted text-justify">
            Kami percaya bahwa belanja online haruslah mudah, menyenangkan, dan terpercaya. Oleh karena itu, setiap produk yang kami tawarkan telah melewati proses kurasi yang ketat, memastikan Anda hanya mendapatkan yang terbaik.
        </p>
    </div>
    <div class="col-md-5 order-md-2">
        <div class="about-image-placeholder shadow-sm">
            <i class="bi bi-lightbulb-fill me-2"></i> Visi TokoOnlineku
        </div>
    </div>
</div>
    
    <hr class="featurette-divider">

    <div class="row featurette align-items-center bg-white p-5 rounded-4 shadow-lg border border-light">
        <div class="col-md-7 order-md-2">
            <h2 class="fw-bolder mb-3 text-primary">Misi Kami: Aksesibilitas & Keanekaragaman Produk.</h2>
            <p class="lead text-dark-emphasis">Kami memiliki tiga misi utama untuk melayani pelanggan setia kami:</p>
            <ul class="list-group list-group-flush">
                <li class="list-group-item border-0 bg-white"><i class="bi bi-check-circle-fill text-success me-2"></i>Menyediakan **pilihan produk yang luas dan beragam** dari berbagai kategori.</li>
                <li class="list-group-item border-0 bg-white"><i class="bi bi-check-circle-fill text-success me-2"></i>Menjamin **harga yang kompetitif** untuk semua produk berkualitas tinggi.</li>
                <li class="list-group-item border-0 bg-white"><i class="bi bi-check-circle-fill text-success me-2"></i>Menciptakan **pengalaman belanja online** yang transparan, mudah, dan aman.</li>
            </ul>
        </div>
        <div class="col-md-5 order-md-1">
            <div class="about-image-placeholder shadow-sm">
                <i class="bi bi-bullseye me-2"></i> Misi Utama Kami
            </div>
        </div>
    </div>

    <hr class="featurette-divider">

    <div class="text-center">
        <h2 class="text-section-title text-dark-emphasis">Mengapa Memilih TokoOnlineku?</h2>
        <div class="row row-cols-1 row-cols-md-3 g-4 mt-4">
            <div class="col">
                <div class="card h-100 p-4 shadow-sm border-0 rounded-4">
                    <i class="bi bi-patch-check-fill feature-icon-gradient mb-3"></i>
                    <h3 class="fw-bold fs-4 text-dark">Produk Terkurasi</h3>
                    <p class="text-muted">Kami hanya menjual produk asli yang teruji kualitasnya, memastikan investasi terbaik untuk kebutuhan Anda.</p>
                </div>
            </div>
            <div class="col">
                <div class="card h-100 p-4 shadow-sm border-0 rounded-4">
                    <i class="bi bi-truck feature-icon-gradient mb-3"></i>
                    <h3 class="fw-bold fs-4 text-dark">Pengiriman Terjamin</h3>
                    <p class="text-muted">Didukung oleh mitra logistik terpercaya, kami memastikan pesanan Anda tiba dengan cepat dan dalam kondisi prima.</p>
                </div>
            </div>
            <div class="col">
                <div class="card h-100 p-4 shadow-sm border-0 rounded-4">
                    <i class="bi bi-headset feature-icon-gradient mb-3"></i>
                    <h3 class="fw-bold fs-4 text-dark">Layanan Pelanggan</h3>
                    <p class="text-muted">Tim dukungan kami siap membantu Anda mulai dari pertanyaan produk, pemesanan, hingga penanganan retur yang mudah.</p>
                </div>
            </div>
        </div>
    </div>
    
    <hr class="featurette-divider">

    <div class="text-center cta-gradient-bg p-5 rounded-4">
        <h3 class="fw-bolder mb-3 text-white">Temukan Produk Kebutuhan Anda Sekarang!</h3>
        <p class="lead text-white-50">Lihat berbagai kategori produk kami dan dapatkan penawaran terbaik hari ini.</p>
        <a href="toko_sepatu.php" class="btn btn-warning btn-lg mt-2 fw-bold shadow-lg">
            <i class="bi bi-bag-check-fill me-2"></i> Belanja Sekarang
        </a>
    </div>

</div>

<footer class="mt-5 pt-4 border-top">
    <?php 
    // Pastikan footer_user.php di-include di sini
    if (file_exists('footer_user.php')) {
        // Asumsi footer_user.php mencantumkan nama TokoOnlineku
        include 'footer_user.php'; 
    } else {
        echo '<div class="container text-center py-3 text-muted"><small>&copy; ' . date("Y") . ' TokoOnlineku. Semua Hak Dilindungi.</small></div>';
    }
    ?> 
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Script untuk efek fade-in
    window.addEventListener("load", function() {
        document.body.classList.add('page-loaded');
    });
</script>

</body>
</html>
<?php 
// Koneksi ditutup di sini
if (isset($koneksi) && $koneksi instanceof mysqli) {
    // Pastikan $koneksi benar-benar didefinisikan dari 'koneksi.php' jika tidak ada error
    // $koneksi->close(); 
}
?>