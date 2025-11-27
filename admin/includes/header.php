<?php
// Pastikan sesi sudah dimulai di admin_auth.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Variabel untuk menentukan folder root dari file ini. Digunakan untuk link.
$root_path = '../../'; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Toko Online Native PHP</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <link href="<?php echo $root_path; ?>assets/css/style.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        /* Gaya dasar untuk sidebar sederhana */
        #sidebar {
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            background: #212529;
            padding-top: 56px; /* Sebesar tinggi navbar */
        }
        #content {
            margin-left: 250px;
            padding: 20px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $root_path; ?>admin/dashboard.php">Admin Panel</a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link">
                        <i class="fas fa-user-circle"></i> Selamat Datang, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-outline-danger btn-sm text-white" href="<?php echo $root_path; ?>admin/logout.php">
                        Logout <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div id="sidebar">
    <div class="list-group list-group-flush pt-3">
        <a href="<?php echo $root_path; ?>admin/dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="<?php echo $root_path; ?>admin/dashboard.php?page=products/index" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="fas fa-box"></i> Manajemen Produk
        </a>
        <a href="<?php echo $root_path; ?>admin/dashboard.php?page=orders/index" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="fas fa-clipboard-list"></i> Pesanan
        </a>
    </div>
</div>
<div id="content" class="mt-5"> 
    ```

