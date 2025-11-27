<?php
/**
 * footer_user.php
 * Footer universal untuk frontend (user), diperbarui dengan struktur Bootstrap 5
 * yang lebih modern, fungsional, dan penambahan ikon WhatsApp.
 */

// *************************************************************
// Catatan: Tentukan nomor WhatsApp di sini untuk kemudahan update
// *************************************************************
$whatsapp_number = '+6281234567890'; 
$whatsapp_link = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsapp_number);
?>

</div> 

<footer class="bg-dark text-white pt-5 pb-3 mt-5"> 
    <div class="container">
        <div class="row">
            
            <div class="col-md-4 mb-4">
                <h5 class="fw-bold text-uppercase text-primary mb-3">TokoOnlineku</h5>
                <p class="small text-white-50">Gaya Setiap Aksi. Kami berkomitmen menyediakan koleksi sepatu dan perlengkapan terbaik dengan kualitas yang terjamin.</p>
                <a href="about.php" class="btn btn-sm btn-outline-light mt-2">Tentang Kami</a>
            </div>

            <div class="col-md-4 mb-4">
                <h5 class="fw-bold text-uppercase mb-3">Tautan Cepat</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="toko_sepatu.php" class="text-white-50 text-decoration-none footer-link">Beranda</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="mb-2"><a href="orders_user.php" class="text-white-50 text-decoration-none footer-link">Pesanan Saya</a></li>
                    <li class="mb-2"><a href="cart.php" class="text-white-50 text-decoration-none footer-link">Keranjang Belanja</a></li>
                    <?php else: ?>
                    <li class="mb-2"><a href="login_user.php" class="text-white-50 text-decoration-none footer-link">Login/Daftar</a></li>
                    <?php endif; ?>
                    <li class="mb-2"><a href="about.php" class="text-white-50 text-decoration-none footer-link">Pusat Bantuan</a></li>
                </ul>
            </div>

            <div class="col-md-4 mb-4">
                <h5 class="fw-bold text-uppercase mb-3">Ikuti Kami & Kontak</h5>
                <div class="social-links mb-4"> 
                    
                    <a href="<?php echo $whatsapp_link; ?>" class="text-white-50 me-3 social-icon whatsapp-icon" target="_blank" title="WhatsApp">
                        <i class="fab fa-whatsapp fa-lg"></i>
                    </a>
                    
                    <a href="#" class="text-white-50 me-3 social-icon" target="_blank" title="Facebook">
                        <i class="fab fa-facebook-f fa-lg"></i>
                    </a>
                    <a href="#" class="text-white-50 me-3 social-icon" target="_blank" title="Instagram">
                        <i class="fab fa-instagram fa-lg"></i>
                    </a>
                    <a href="#" class="text-white-50 social-icon" target="_blank" title="Twitter/X">
                        <i class="fab fa-twitter fa-lg"></i>
                    </a>
                </div>
                <p class="small text-primary mt-4">Hubungi kami: support@toko-onlineku.com</p>
                <p class="small text-white-50">WA: <?php echo $whatsapp_number; ?></p>
            </div>
        </div>
        
        <hr class="my-4 border-secondary">

        <div class="text-center">
            <p class="mb-1 text-white-50">&copy; <?php echo date('Y'); ?> TokoOnlineku. Hak Cipta Dilindungi.</p>
            <p class="small text-muted">Dibuat dengan semangat untuk gaya dan kenyamanan Anda.</p>
        </div>
        
    </div>
</footer>

<style>
    /* Styling modern untuk Footer */
    .footer-link, .social-icon {
        transition: color 0.2s ease-in-out, transform 0.2s ease-in-out;
    }
    .footer-link:hover {
        color: var(--bs-primary) !important; /* Warna Primary Bootstrap */
    }
    .social-icon:hover {
        color: #fff !important; 
        transform: scale(1.1);
        text-shadow: 0 0 10px rgba(13, 110, 253, 0.5); /* Efek glow ringan */
    }
    /* Styling khusus untuk WhatsApp */
    .whatsapp-icon:hover {
        color: #25D366 !important; /* Warna hijau WA saat hover */
        text-shadow: 0 0 10px rgba(37, 211, 102, 0.8);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>