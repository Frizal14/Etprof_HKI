<?php
/**
 * kontak.php
 * Halaman kontak dengan tampilan modern, layout rapi, dan data diambil dari admin_kontak_entri.
 * * PERBAIKAN: Tautan email diubah agar elemen <a> membungkus seluruh entri (selain tombol WA/Telpon) 
 * dan menggunakan skema "mailto:" agar langsung membuka aplikasi email.
 */

// 1. Mulai Sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Koneksi Database
include 'koneksi.php'; 
global $koneksi;

// --- LOGIKA FOOTER ---
$is_logged_in = isset($_SESSION['user_id']);
$cart_count = $is_logged_in ? count($_SESSION['cart'] ?? []) : 0;

$site_name = 'TokoOnlineku'; 
$site_tagline = 'Gaya Setiap Aksi';

if (isset($koneksi) && $koneksi instanceof mysqli) {
    $sql_settings = "SELECT website_name, tagline FROM website_settings WHERE id = 1"; 
    $result_settings = $koneksi->query($sql_settings);

    if ($result_settings && $result_settings->num_rows > 0) {
        $settings_row = $result_settings->fetch_assoc();
        $site_name = htmlspecialchars($settings_row['website_name']);
        $site_tagline = htmlspecialchars($settings_row['tagline']);
    }
}
// -----------------------------------------------------------------------

// 3. Ambil Data Kontak dari Database
$sql = "SELECT * FROM admin_kontak_entri WHERE is_active = 1 ORDER BY prioritas ASC";
$result = $koneksi->query($sql);

$grouped_contacts = [
    'WA' => [],
    'Email' => [],
    'Telp' => [],
    'Sosmed' => [],
    'Lainnya' => []
];

// Data fallback jika database kosong
$default_data = [
    'email_toko' => 'admin@tokoonline.com', // Default aman
    'telepon_toko' => '(021) 123-4567',
];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tipe = strtoupper($row['tipe_kontak']);
        // Kita ambil nilai mentah dulu, nanti dibersihkan saat mau ditampilkan
        $nilai = $row['nilai_kontak']; 
        $nama = htmlspecialchars($row['nama_kontak']);

        if ($tipe == 'WA') {
            $wa_clean = preg_replace('/[^0-9]/', '', $nilai);
            if (substr($wa_clean, 0, 1) === '0') {
                $wa_clean = '62' . substr($wa_clean, 1);
            }
            $row['link'] = 'https://wa.me/' . $wa_clean;
            $row['display_number'] = htmlspecialchars($nilai);
            $grouped_contacts['WA'][] = $row;

        } elseif ($tipe == 'EMAIL') {
            // Simpan nilai asli untuk ditampilkan teksnya
            $row['display_email'] = htmlspecialchars($nilai);
            // Bersihkan nilai untuk link href (Hapus spasi & karakter aneh)
            $email_bersih = filter_var($nilai, FILTER_SANITIZE_EMAIL);
            $email_bersih = str_replace(' ', '', $email_bersih); // Hapus spasi paksa
            $row['link_email'] = $email_bersih;
            
            $grouped_contacts['Email'][] = $row;

        } elseif ($tipe == 'TELP') {
            $grouped_contacts['Telp'][] = $row;

        } elseif (in_array($tipe, ['IG', 'FB', 'TIKTOK'])) {
            $grouped_contacts['Sosmed'][] = $row;
        } 
    }
}

$assets_path = 'assets/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontak Kami | <?= $site_name ?></title> 
    <link rel="stylesheet" href="<?php echo $assets_path; ?>css/gaya.css?v=<?php echo time(); ?>"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body { background-color: #f7f9fc; } 
        .contact-card { border-radius: 20px; overflow: hidden; border: none; }
        .section-title { font-weight: 800; color: #2c3e50; }
        .contact-entry {
            padding: 12px 18px; background: #ffffff; border-radius: 12px; margin-bottom: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e9ecef;
            display: flex; /* Tambahkan ini agar layout tidak rusak saat diubah jadi <a> */
            justify-content: space-between; /* Tambahkan ini */
            align-items: center; /* Tambahkan ini */
            color: inherit; /* Penting untuk tag <a> */
            text-decoration: none; /* Penting untuk tag <a> */
        }
        .contact-entry:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1); }
        .btn-whatsapp { background: #25D366; color: white; font-weight: 600; }
        .btn-whatsapp:hover { background: #128C7E; color: white; }
        .btn-warning { background-color: #f39c12 !important; border-color: #f39c12 !important; color: white !important; }
        .btn-info { background-color: #3498db !important; border-color: #3498db !important; color: white !important; }
        .socmed-btn {
            width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            transition: transform 0.3s, box-shadow 0.3s; font-size: 1.25rem; flex-shrink: 0;
        }
        .socmed-btn:hover { transform: scale(1.1); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); }
        .footer-link { transition: color 0.2s ease-in-out; }
        .footer-link:hover { color: #0d6efd !important; }
        @media(max-width:768px){ .contact-container { padding: 10px; } .contact-card { border-radius: 10px; } }
    </style>
</head>

<body>

<div class="container pt-4">
    <a href="toko_sepatu.php" class="btn btn-outline-secondary mb-4">
        <i class="fas fa-arrow-left me-2"></i> Kembali ke Beranda
    </a>
</div>

<div class="container contact-container mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">

            <div class="card contact-card shadow-lg p-md-5 p-4">

                <h2 class="text-center section-title mb-2">
                    <i class="fas fa-handshake me-2"></i> Pusat Bantuan
                </h2>
                <p class="text-center text-muted mb-5">
                    Layanan pelanggan kami siap membantu Anda dengan cepat dan efisien.
                </p>

                <div class="row g-4">

                    <div class="col-md-6">
                        <h4 class="fw-bold mb-3"><i class="fab fa-whatsapp text-success me-2"></i> Layanan Cepat</h4>
                        
                        <?php 
                        $quick_contacts = array_merge($grouped_contacts['WA'], $grouped_contacts['Telp']);
                        if (!empty($quick_contacts)): 
                        ?>
                            <?php foreach ($quick_contacts as $c): 
                                $is_wa = $c['tipe_kontak'] == 'WA';
                                $icon = $is_wa ? 'fab fa-whatsapp' : 'fas fa-phone';
                                $btn_class = $is_wa ? 'btn-whatsapp' : 'btn-info';
                                $link = $is_wa ? $c['link'] : 'tel:' . $c['nilai_kontak'];
                                $action_text = $is_wa ? 'Chat' : 'Telpon';
                            ?>
                                <div class="contact-entry d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="d-block"><?php echo $c['nama_kontak']; ?></strong>
                                        <small class="text-muted"><?php echo $c['display_number'] ?? $c['nilai_kontak']; ?></small>
                                    </div>
                                    <a href="<?php echo $link; ?>" 
                                        target="_blank" 
                                        class="btn <?php echo $btn_class; ?> btn-sm px-3 flex-shrink-0">
                                       <i class="<?php echo $icon; ?> me-1"></i> <?php echo $action_text; ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <div class="alert alert-warning small">Kontak WhatsApp dan Telepon belum tersedia.</div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <h4 class="fw-bold mb-3"><i class="fas fa-envelope text-warning me-2"></i> Formal & Komunitas</h4>

                        <?php if (!empty($grouped_contacts['Email'])): ?>
                            <p class="fw-semibold text-muted mb-1">Dukungan Email:</p>
                            <?php foreach ($grouped_contacts['Email'] as $email): ?>
                                <a href="mailto:<?php echo $email['link_email']; ?>"
                                   class="contact-entry text-dark"> 
                                    <div style="overflow: hidden;">
                                        <strong class="d-block text-truncate"><?php echo $email['nama_kontak']; ?></strong>
                                        <small class="text-muted text-truncate d-block"><?php echo $email['display_email']; ?></small>
                                    </div>
                                    <span class="btn btn-warning text-white btn-sm px-3 ms-2 flex-shrink-0">
                                       <i class="fas fa-envelope"></i>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="fw-semibold text-muted mb-1">Dukungan Email:</p>
                            <a href="mailto:<?php echo $default_data['email_toko']; ?>" 
                               class="contact-entry text-dark">
                                <div>
                                    <strong class="d-block">Email Toko</strong>
                                    <small class="text-muted"><?php echo $default_data['email_toko']; ?></small>
                                </div>
                                <span class="btn btn-warning text-white btn-sm px-3 ms-2 flex-shrink-0">
                                   <i class="fas fa-envelope"></i>
                                </span>
                            </a>
                        <?php endif; ?>
                        <div class="mt-4">
                            <p class="fw-semibold text-muted mb-3">Ikuti Media Sosial Kami :</p>
                            <div class="d-flex flex-column gap-2">
                                <?php 
                                $socmed_icons = [
                                    'IG' => ['icon' => 'fab fa-instagram', 'class' => 'btn-danger', 'label' => 'Instagram'],
                                    'FB' => ['icon' => 'fab fa-facebook-f', 'class' => 'btn-primary', 'label' => 'Facebook'],
                                    'TIKTOK' => ['icon' => 'fab fa-tiktok', 'class' => 'btn-dark', 'label' => 'TikTok'],
                                ];
                                ?>

                                <?php foreach ($grouped_contacts['Sosmed'] as $s): 
                                    $t = strtoupper($s['tipe_kontak']);
                                    if(isset($socmed_icons[$t])):
                                        $icon_info = $socmed_icons[$t];
                                ?>
                                    <div class="contact-entry d-flex align-items-center p-2">
                                        <a href="<?php echo $s['nilai_kontak']; ?>" target="_blank"
                                            class="btn socmed-btn <?php echo $icon_info['class']; ?> me-3 text-white">
                                            <i class="<?php echo $icon_info['icon']; ?>"></i>
                                        </a>
                                        <div style="flex-grow: 1;">
                                            <h6 class="fw-bold mb-0 text-dark"><?php echo $s['nama_kontak']; ?></h6>
                                            <a href="<?php echo $s['nilai_kontak']; ?>" target="_blank" class="text-decoration-none small text-muted">
                                                Kunjungi <?php echo $icon_info['label']; ?> <i class="fas fa-external-link-alt ms-1" style="font-size: 10px;"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>

                                <?php if(empty($grouped_contacts['Sosmed'])): ?>
                                    <span class="text-muted small">Tidak ada link sosmed aktif.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const contactContainer = document.querySelector('.contact-container');
        if (contactContainer) {
             contactContainer.style.opacity = 1;
             contactContainer.style.transform = 'translateY(0)';
        }
    });
</script>

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

</body>
</html>