<?php
/**
 * kontak.php
 * Halaman kontak sederhana dengan informasi kontak langsung dan animasi transisi,
 * tanpa navbar, hanya tombol kembali ke halaman utama.
 */

// 1. Mulai Sesi (diperlukan jika ada logic session di footer atau di masa depan)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Variabel Informasi Kontak Langsung
// *Harap ganti '#' dengan link/nomor yang sebenarnya*
$whatsapp_number = '#'; // Contoh nomor WA
// Membuat link WA yang benar
$whatsapp_link = '#' . preg_replace('/[^0-9]/', '', $whatsapp_number); 

$instagram_link = '#'; // Ganti dengan link Instagram Anda
$facebook_link = '#';
$store_email = 'rizalmahendra@gmail.com'; // Email yang Anda berikan
$store_address = 'Jl. Jenderal Soedirman No. 12, Jawa Timur, Indonesia'; // Alamat yang Anda berikan

// Ambil path assets (sesuaikan jika perlu)
$assets_path = 'assets/'; 

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontak Kami | TokoOnlineku</title> 
    <link rel="stylesheet" href="<?php echo $assets_path; ?>css/gaya.css?v=<?php echo time(); ?>"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> 
    <style>
        body { background-color: #f8f9fa; }
        /* Sesuaikan margin-top karena navbar dihapus */
        .contact-container { margin-top: 20px; margin-bottom: 50px; } 
        
        /* CSS UNTUK ANIMASI TRANSISI SAAT HALAMAN DIMUAT */
        .page-transition {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        .page-transition.loaded {
            opacity: 1;
            transform: translateY(0);
        }

        /* Gaya Khusus Tombol WA */
        .btn-whatsapp {
            background-color: #25D366;
            border-color: #25D366;
            color: white;
        }
        .btn-whatsapp:hover {
            background-color: #128C7E;
            border-color: #128C7E;
            color: white;
        }
    </style>
</head>
<body>

<div class="container pt-4">
    <a href="toko_sepatu.php" class="btn btn-outline-secondary mb-3">
        <i class="fas fa-arrow-left me-2"></i> Kembali ke Beranda
    </a>
</div>

<div class="container contact-container page-transition">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    
                    <h1 class="card-title text-center mb-4 fw-bold text-primary"><i class="fas fa-headset me-2"></i> Hubungi Kami Langsung</h1>
                    <p class="text-center text-muted mb-5">
                        Kami menyediakan layanan dukungan pelanggan yang cepat dan responsif melalui saluran di bawah ini.
                    </p>

                    <div class="row text-center mb-5">
                        <div class="col-md-6 mb-3">
                            <div class="p-3 border rounded shadow-sm">
                                <i class="fab fa-whatsapp fa-3x text-success mb-3"></i>
                                <h5 class="fw-bold">Chat WhatsApp (Paling Cepat! ⚡)</h5>
                                <p class="text-muted">Hubungi kami untuk pertanyaan produk, pesanan, atau dukungan instan.</p>
                                <a href="<?php echo $whatsapp_link; ?>" target="_blank" class="btn btn-whatsapp btn-lg w-75">
                                    <i class="fab fa-whatsapp me-2"></i> Chat Sekarang!
                                </a>
                                <p class="mt-2 small text-muted">Nomor: <?php echo $whatsapp_number; ?></p>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="p-3 border rounded shadow-sm">
                                <i class="fas fa-envelope fa-3x text-warning mb-3"></i>
                                <h5 class="fw-bold">Dukungan Email</h5>
                                <p class="text-muted">Untuk permintaan formal, kerjasama, atau keluhan detail. Respon 1x24 jam.</p>
                                <a href="mailto:<?php echo $store_email; ?>" class="btn btn-warning text-white btn-lg w-75">
                                    <i class="fas fa-envelope me-2"></i> Kirim Email
                                </a>
                                <p class="mt-2 small text-muted">Alamat: <?php echo $store_email; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-5 pt-3 border-top">
                        <h5 class="fw-bold">Ikuti Kami & Informasi Lokasi</h5>
                        <div class="d-flex justify-content-center mb-3">
                            <a href="<?php echo $instagram_link; ?>" target="_blank" class="btn btn-outline-danger mx-2"><i class="fab fa-instagram fa-2x"></i></a>
                            <a href="<?php echo $facebook_link; ?>" target="_blank" class="btn btn-outline-primary mx-2"><i class="fab fa-facebook-f fa-2x"></i></a>
                        </div>
                        
                        <p class="mb-1">
                            <i class="fas fa-map-marker-alt me-2 text-primary"></i> 
                            <?php echo $store_address; ?>
                        </p>
                        <p>
                            <i class="fas fa-phone-square-alt me-2 text-primary"></i> 
                            (021) 123-4567
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tambahkan class 'loaded' setelah DOM selesai dimuat
        document.querySelector('.page-transition').classList.add('loaded');
    });
</script>

<?php 
// PENTING: Include footer
if (file_exists('footer_user.php')) {
    include 'footer_user.php'; 
} else {
    // SESUAIKAN: Footer Branding
    echo '<div class="container text-center py-3 text-muted"><small>&copy; ' . date("Y") . ' TokoOnlineku. Semua Hak Dilindungi.</small></div>';
}
?>
</body>
</html>