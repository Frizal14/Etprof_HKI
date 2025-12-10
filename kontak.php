<?php
/**
 * kontak.php
 * Halaman kontak dengan tampilan modern, layout rapi, dan data diambil dari admin_kontak_entri.
 */

// 1. Mulai Sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Koneksi Database
include 'koneksi.php'; 
global $koneksi;

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
    'email_toko' => 'rizalmahendra@gmail.com',
    'telepon_toko' => '(021) 123-4567',
    'instagram_url' => '#',
    'facebook_url' => '#',
    'tiktok_url' => '#',
];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tipe = strtoupper($row['tipe_kontak']);
        $nilai = htmlspecialchars($row['nilai_kontak']);
        $nama = htmlspecialchars($row['nama_kontak']);

        if ($tipe == 'WA') {
            $wa_clean = preg_replace('/[^0-9]/', '', $nilai);
            if (substr($wa_clean, 0, 1) === '0') {
                $wa_clean = '62' . substr($wa_clean, 1);
            }
            $row['link'] = 'https://wa.me/' . $wa_clean;
            $row['display_number'] = $nilai;

            $grouped_contacts['WA'][] = $row;

        } elseif ($tipe == 'EMAIL') {
            $default_data['email_toko'] = $nilai;
            $grouped_contacts['Email'][] = $row;

        } elseif ($tipe == 'TELP') {
            $default_data['telepon_toko'] = $nilai;
            $grouped_contacts['Telp'][] = $row;

        } elseif (in_array($tipe, ['IG', 'FB', 'TIKTOK'])) {
            $grouped_contacts['Sosmed'][] = $row;

            if ($tipe == 'IG') $default_data['instagram_url'] = $nilai;
            if ($tipe == 'FB') $default_data['facebook_url'] = $nilai;
            if ($tipe == 'TIKTOK') $default_data['tiktok_url'] = $nilai;
        } 
    }
}

$contact_data = $default_data;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* Modern Base */
        body { background-color: #f7f9fc; } 

        .contact-card {
            border-radius: 20px;
            overflow: hidden;
            border: none;
        }

        .section-title {
            font-weight: 800;
            color: #2c3e50; /* Darker primary color for title focus */
        }

        /* Styling untuk Setiap Entry Kontak */
        .contact-entry {
            padding: 12px 18px;
            background: #ffffff;
            border-radius: 12px;
            margin-bottom: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); /* Soft shadow */
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e9ecef;
        }
        .contact-entry:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-whatsapp {
            background: #25D366;
            color: white;
            font-weight: 600;
        }

        .btn-whatsapp:hover {
            background: #128C7E;
            color: white;
        }
        
        .btn-warning {
             background-color: #f39c12 !important;
             border-color: #f39c12 !important;
             color: white !important;
        }
        .btn-info {
             background-color: #3498db !important;
             border-color: #3498db !important;
             color: white !important;
        }


        /* Styling untuk Tombol Sosmed */
        .socmed-btn {
            width: 55px;
            height: 55px;
            border-radius: 50%; /* Membuat tombol bulat */
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s, box-shadow 0.3s;
            font-size: 1.25rem;
        }
        .socmed-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* Hilangkan CSS Transisi (karena tag HTML sudah dihapus) */
        /* .page-transition.loaded { opacity: 1; transform: translateY(0); } */

        /* RESPONSIVE */
        @media(max-width:768px){
            .contact-container { padding: 10px; }
            .contact-card { border-radius: 10px; }
        }
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
                                        class="btn <?php echo $btn_class; ?> btn-sm px-3">
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
                                <div class="contact-entry d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="d-block"><?php echo $email['nama_kontak']; ?></strong>
                                        <small class="text-muted"><?php echo $email['nilai_kontak']; ?></small>
                                    </div>
                                    <a href="mailto:<?php echo $email['nilai_kontak']; ?>" 
                                        class="btn btn-warning text-white btn-sm px-3">
                                        <i class="fas fa-envelope"></i> Kirim
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="fw-semibold text-muted mb-3">Dukungan Email:</p>
                            <div class="alert alert-info small">Alamat Email formal belum diatur.</div>
                        <?php endif; ?>

                        <div class="mt-4">
                            <p class="fw-semibold text-muted mb-3">Ikuti Insagram Kami :</p>
                            <div class="d-flex justify-content-start gap-3">
                                <?php 
                                $socmed_icons = [
                                    'IG' => ['icon' => 'fab fa-instagram', 'class' => 'btn-danger'],
                                    'FB' => ['icon' => 'fab fa-facebook-f', 'class' => 'btn-primary'],
                                    'TIKTOK' => ['icon' => 'fab fa-tiktok', 'class' => 'btn-dark'],
                                ];
                                $displayed = [];
                                ?>

                                <?php foreach ($grouped_contacts['Sosmed'] as $s): 
                                    $t = strtoupper($s['tipe_kontak']);
                                    if(isset($socmed_icons[$t]) && !in_array($t, $displayed)):
                                        $displayed[] = $t;
                                        $icon_info = $socmed_icons[$t];
                                ?>
                                    <a href="<?php echo $s['nilai_kontak']; ?>" target="_blank"
                                       class="btn socmed-btn <?php echo $icon_info['class']; ?>"
                                       title="<?php echo $s['nama_kontak']; ?>">
                                        <i class="<?php echo $icon_info['icon']; ?>"></i>
                                    </a>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>

                                <?php if(empty($grouped_contacts['Sosmed'])): ?>
                                    <span class="text-muted small align-self-center">Tidak ada link sosmed aktif.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    
                </div>
                </div></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Logika sederhana untuk animasi (jika diperlukan)
        const contactContainer = document.querySelector('.contact-container');
        if (contactContainer) {
             contactContainer.style.opacity = 1;
             contactContainer.style.transform = 'translateY(0)';
        }
    });
</script>

<?php 
if (file_exists('footer_user.php')) {
    include 'footer_user.php'; 
} else {
    echo '<div class="container text-center py-3 text-muted"><small>&copy; ' . date("Y") . ' TokoOnlineku. Semua Hak Dilindungi.</small></div>';
}
?>
</body>
</html>