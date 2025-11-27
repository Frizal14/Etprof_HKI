<?php
// header_user.php

// Pastikan session sudah dimulai sebelum header di-include
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definisikan variabel path dan data yang diperlukan
$cart_count = count($_SESSION['cart'] ?? []);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Tamu');

// Asumsi: $page_title di-set sebelum header di-include
$title = $page_title ?? "Toko SepatuKu"; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    
    <!-- Tautan Bootstrap dan Font Awesome CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- KONFIRMASI: Tautan ke style.css sudah BENAR di sini -->
    <link rel="stylesheet" href="style.css"> 
    
</head>
<body>

<?php 
// ... (Logika Notifikasi Global) ...
if (isset($_GET['status'])): 
    $alert_class = match($_GET['status']) {
        'added', 'removed', 'updated' => 'alert-success',
        default => 'alert-danger',
    };
    $message = match($_GET['status']) {
        'added' => 'Produk **' . htmlspecialchars($_GET['product'] ?? '') . '** berhasil ditambahkan ke keranjang!',
        'removed' => 'Item berhasil dihapus dari keranjang.',
        'updated' => 'Keranjang berhasil diperbarui.',
        default => htmlspecialchars($_GET['message'] ?? 'Terjadi kesalahan.')
    };
?>
    <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show mb-0" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="toko_sepatu.php">Toko SepatuKu</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                
                <li class="nav-item me-3">
                    <a class="nav-link btn btn-outline-primary position-relative" href="cart.php">
                        <i class="fas fa-shopping-cart"></i> Keranjang
                        <?php if ($cart_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $cart_count; ?>
                            <span class="visually-hidden">items in cart</span>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-item">
                    <span class="nav-link text-dark me-2">Halo, <?php echo $user_name; ?>!</span>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-outline-danger btn-sm" 
                       href="logout_user.php"
                       onclick="return confirm('Apakah Anda yakin ingin keluar dari akun ini?');">
                        Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Div #page-content yang di-set 'flex: 1 0 auto;' di style.css untuk mendorong footer ke bawah -->
<div id="page-content">
