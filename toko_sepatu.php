<?php
// 1. Mulai Sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

//  MENONAKTIFKAN CACHE RIWAYAT PERAMBAN 🔥
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Tentukan status login
$is_logged_in = isset($_SESSION['user_id']);

require_once 'koneksi.php'; // Ambil koneksi database
global $koneksi; // Pastikan koneksi global di sini

// 🔥 START: AMBIL PENGATURAN WEBSITE (BRAND) 🔥
$website_settings = [];
$sql_settings = "SELECT website_name, tagline, logo_image_path FROM website_settings WHERE id = 1"; 
$result_settings = $koneksi->query($sql_settings);

if ($result_settings && $result_settings->num_rows > 0) {
    $website_settings = $result_settings->fetch_assoc();
} else {
    // Default jika tabel kosong
    $website_settings = [
        'website_name' => 'TokoOnlineku', 
        'tagline' => 'Gaya Setiap Aksi 🛍️',
        'logo_image_path' => null 
    ];
}

$site_name = htmlspecialchars($website_settings['website_name'] ?? 'TokoOnlineku');
$site_tagline = htmlspecialchars($website_settings['tagline'] ?? 'Gaya Setiap Aksi 🛍️');
$site_logo_file = htmlspecialchars($website_settings['logo_image_path'] ?? ''); // Nama file logo
// 🔥 END: AMBIL PENGATURAN WEBSITE (BRAND) 🔥

$uploads_url_path = 'uploads/product_images/'; 
$assets_path = 'assets/'; // Path baru untuk assets
$profile_upload_path = 'uploads/user_profiles/'; // Path untuk foto user
$logo_uploads_url_path = 'uploads/brand/'; // Path baru untuk logo brand

$products = [];
$categories = [];
$selected_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0; 

// --- AMBIL DATA PROFILE USER DARI SESSION (DIPERLUKAN UNTUK NAVBAR) ---
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Pengguna';
$user_profile_img = $_SESSION['user_profile_img'] ?? null; 
// ---------------------------------------------------------------------

// =========================================================================
// 🔥 LOGIKA BARU: PENGAMBILAN DATA LENGKAP USER UNTUK MODAL PROFIL 🔥
// =========================================================================
$user_data = [];
$profile_img_url_modal = 'https://via.placeholder.com/150/EEEEEE/AAAAAA?text=Default'; 
$current_profile_img = null;

if ($is_logged_in) {
    $sql_profile = "SELECT id, name, email, profile_image_path FROM users WHERE id = ?";
    $stmt_profile = $koneksi->prepare($sql_profile);

    if ($stmt_profile) {
        $stmt_profile->bind_param("i", $user_id);
        $stmt_profile->execute();
        $result_profile = $stmt_profile->get_result();
        
        if ($result_profile->num_rows === 1) {
            $user_data = $result_profile->fetch_assoc();
            $current_profile_img = $user_data['profile_image_path'];
            
            if (!empty($current_profile_img)) {
                $profile_img_url_modal = $profile_upload_path . htmlspecialchars($current_profile_img);
            }
        }
        $stmt_profile->close();
    }
}
// =========================================================================


// 3. LOGIKA PENGAMBILAN DATA (Kategori, Pagination, dan Produk)

// A. Ambil semua kategori
$sql_categories = "SELECT id, name FROM categories ORDER BY name ASC";
$result_categories = $koneksi->query($sql_categories);
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
}

// --- START: LOGIKA PAGINATION ---
$limit = 8;
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1; 
$offset = ($current_page - 1) * $limit; 

$count_params = [];
$count_types = '';

$final_count_sql = "
    SELECT 
        COUNT(*) AS total_count 
    FROM 
        (
            SELECT 
                p.id 
            FROM 
                products p
            LEFT JOIN 
                product_variants pv ON p.id = pv.product_id
            WHERE 
                1=1
"; 

if ($selected_category_id > 0) {
    $final_count_sql .= " AND p.category_id = ? ";
    $count_params[] = $selected_category_id;
    $count_types .= 'i';
}

$final_count_sql .= "
            GROUP BY 
                p.id 
            HAVING 
                COALESCE(SUM(pv.stock), 0) > 0 
        ) AS filtered_products
";

$total_products = 0; 

$count_stmt = $koneksi->prepare($final_count_sql);

if ($count_stmt) {
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params); 
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $total_products = $count_result['total_count'];
    $count_stmt->close(); 
} else {
    // Error handling
}

$total_pages = ceil($total_products / $limit); 

if ($total_pages > 0) {
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
        $offset = ($current_page - 1) * $limit;
    } else if ($current_page < 1) {
        $current_page = 1;
        $offset = 0;
    }
} else {
    $current_page = 1; 
    $offset = 0;
}
// --- END: LOGIKA PAGINATION ---


// B. Ambil data produk berdasarkan filter
$sql = "
    SELECT 
        p.id, p.name, p.price, p.image_path, p.description, p.category_id,
        COALESCE(SUM(pv.stock), 0) AS total_stock 
    FROM 
        products p
    LEFT JOIN
        product_variants pv ON p.id = pv.product_id
    WHERE 
        1=1
";
$params = [];
$types = '';

if ($selected_category_id > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $selected_category_id;
    $types .= 'i';
}

$sql .= " 
    GROUP BY 
        p.id, p.name, p.price, p.image_path, p.description, p.category_id
    HAVING 
        total_stock > 0
";

$sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?"; 

$params[] = $limit; 
$params[] = $offset; 
$types .= 'ii'; 

$stmt = $koneksi->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params); 
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    $stmt->close(); 
} else {
    // Error handling
}


// Hanya hitung keranjang jika sudah login
$cart_count = $is_logged_in ? count($_SESSION['cart'] ?? []) : 0;

// LOGIKA PENGAMBILAN FLASH MESSAGE (TOAST)
$toast_message = null;
$toast_type = null;

// 🔥 LOGIKA SWEET ALERT BARU 🔥
$session_swal_title = '';
$session_swal_text = '';
$session_swal_icon = '';

if (isset($_SESSION['swal_title']) && isset($_SESSION['swal_icon'])) {
    $session_swal_title = $_SESSION['swal_title'];
    $session_swal_text = isset($_SESSION['swal_text']) ? $_SESSION['swal_text'] : '';
    $session_swal_icon = $_SESSION['swal_icon'];
    
    // Hapus sesi agar Sweet Alert hanya muncul sekali
    unset($_SESSION['swal_title']);
    unset($_SESSION['swal_text']);
    unset($_SESSION['swal_icon']);
}
// ---------------------------------

// Logika Tambahan: Pemicu Modal Otomatis setelah Update Profil
$show_profile_modal = false;
if (isset($_SESSION['show_profile_modal'])) {
    $show_profile_modal = true;
    unset($_SESSION['show_profile_modal']);
}

// Logika Toast (Dipertahankan untuk error)
if (isset($_SESSION['toast_message'])) {
    $toast_message = htmlspecialchars($_SESSION['toast_message']);
    $toast_type = $_SESSION['toast_type'] ?? 'success'; 
    
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

// Periksa path foto profil untuk Navbar
$full_profile_path = (!empty($user_profile_img) && file_exists($profile_upload_path . $user_profile_img)) ? $profile_upload_path . htmlspecialchars($user_profile_img) : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?></title> 
    <link rel="stylesheet" href="<?php echo $assets_path; ?>css/gaya.css?v=<?php echo time(); ?>"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* ================================================= */
        /* VARIABEL DAN STYLING DASAR */
        /* ================================================= */
        :root {
            --primary-brand: #ff6b6b; 
            --primary-dark: #cc5656;
            --success-brand: #00b894; 
        }
        .text-primary { color: var(--primary-brand) !important; }
        .btn-primary { 
            background-color: var(--primary-brand);
            border-color: var(--primary-brand);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .btn-success {
            background-color: var(--success-brand);
            border-color: var(--success-brand);
        }

        body { 
            opacity: 0; 
            transition: opacity 0.8s ease-out; 
            overflow-x: hidden;
            background-color: #f8f9fa; 
        }
        .page-loaded { opacity: 1; }
        
        /* Navbar Auto-Hide */
        .navbar-autohide {
            transition: transform 0.3s ease-in-out !important; 
            transform: translateY(0);
            z-index: 1050;
        }
        .navbar-autohide.hidden {
            transform: translateY(-100%) !important; 
        }

        /* Dropdown Profil Styling Kustom (Desktop) */
        .custom-dropdown { position: relative; display: inline-block; }
        .custom-dropdown-toggle { cursor: pointer; padding: 0.5rem 1rem; display: block; } 
        .custom-dropdown-menu { 
            position: absolute; top: 100%; right: 0; 
            z-index: 1000; display: none; min-width: 12rem;
            padding: 0.5rem 0; margin: 0.125rem 0 0; 
            background-color: #fff; background-clip: padding-box; 
            border: 1px solid rgba(0, 0, 0, 0.15); border-radius: 0.375rem; 
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175); 
        }
        .custom-dropdown-menu.show { display: block; }

        /* ================================================= */
        /* STYLING MODAL PROFIL (BARU) */
        /* ================================================= */
        .modal-header.bg-primary-custom {
            background-color: var(--primary-brand) !important; 
            color: white; 
            border-bottom: none;
            padding-top: 1.5rem;
            padding-bottom: 1.5rem;
        }
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 7px solid var(--primary-brand); 
            border-radius: 50%;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.2); 
            transition: transform 0.3s ease-in-out;
        }
        .file-upload-box {
            background-color: #f8f9fa;
            border: 1px dashed #ced4da;
            transition: border-color 0.3s;
        }
        .file-upload-box:hover {
            border-color: var(--primary-brand);
        }
        /* ================================================= */

        /* ================================================= */
        /* STYLING PRODUK (CARD IMAGE ASPECT RATIO) */
        /* ================================================= */
        .product-image-container {
            width: 100%;
            /* Mempertahankan rasio aspek 4:3 (Tinggi 75% dari lebar) */
            padding-top: 75%; 
            position: relative;
            overflow: hidden;
            background-color: #f5f5f5;
        }
        .product-image-container img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover; /* Memastikan gambar mengisi container tanpa terdistorsi */
            transition: transform 0.3s ease;
        }
        .product-card:hover .product-image-container img {
            transform: scale(1.05);
        }

        /* ================================================= */
        /* STYLING FOOTER (DIPINDAHKAN DARI INLINE) */
        /* ================================================= */
        .footer-link, .social-icon {
            transition: color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }
        .footer-link:hover {
            color: var(--primary-brand) !important; 
        }
        .social-icon:hover {
            color: #fff !important; 
            transform: scale(1.1);
            text-shadow: 0 0 10px rgba(13, 110, 253, 0.5); 
        }


        /* ================================================= */
        /* RESPONSIVITAS MOBILE (<= 991.98px) */
        /* ================================================= */
        @media (max-width: 991.98px) { 
            /* 1. CONTAINER NAVBAR - Pastikan logo dan menu mobile sejajar */
            .navbar > .container {
                display: flex; 
                flex-wrap: nowrap; 
                justify-content: space-between; 
                align-items: center;
            }
            
            /* 2. LOGO BRAND - Membuat nama brand lebih kecil agar muat */
            .brand-logo-container .brand-name {
                font-size: 0.9rem; /* Sedikit diperbesar karena slogan hilang */
            }
            
            /* Sembunyikan tagline di mobile (redundant sekarang karena di php sudah dihapus, tapi untuk jaga-jaga) */
            .brand-logo-container .brand-tagline {
                display: none !important; 
            }
            
            .brand-logo-container > div {
                max-width: 150px; 
                overflow: hidden; 
                text-overflow: clip; 
                white-space: nowrap; 
            }
            
            /* 3. GRUP KANAN - Memastikan elemen di kanan (Profil + Titik Tiga) tidak wrap */
            .navbar .d-lg-none {
                flex-shrink: 0; 
                margin-left: auto; 
            }

            /* 4. FILTER KATEGORI - Rata kiri untuk menghemat ruang jika terlalu banyak kategori */
            .category-filter {
                text-align: left !important;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            .category-filter .btn {
                font-size: 0.85rem;
                padding: 0.3rem 0.6rem;
                margin: 0.2rem !important;
            }
        }
    </style>
</head>
<body>

<?php 
// HTML TOAST CONTAINER (Dipertahankan untuk Error/Stok)
if ($toast_message): 
    $toast_class = ($toast_type === 'error' || $toast_type === 'stock_limit') ? 'bg-danger' : 'bg-success';
?>
<div class="toast-container position-fixed end-0 p-3" style="z-index: 1090;">
    <div id="liveToast" class="toast fade align-items-center text-white <?php echo $toast_class; ?>" 
        role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex p-2">
            <div class="toast-body fw-bold">
                <?php echo $toast_message; ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top navbar-autohide" id="mainNavbar"> 
    <div class="container">
        <a class="navbar-brand brand-logo-container d-flex align-items-center" href="toko_sepatu.php">
            <?php 
            // Cek apakah logo dari DB ada di uploads/brand
            $logo_url = (!empty($site_logo_file) && file_exists($logo_uploads_url_path . $site_logo_file)) 
                ? $logo_uploads_url_path . $site_logo_file
                : $assets_path . 'images/logo.png'; // Fallback ke logo default di assets
            ?>
            <img src="<?php echo $logo_url; ?>" alt="<?php echo $site_name; ?> Logo" class="brand-logo" style="height: 35px;">
            <div>
                <span class="brand-name fw-bold ms-2 text-primary"><?php echo $site_name; ?></span>
            </div>
        </a>
        <div class="d-flex align-items-center d-lg-none">

            <?php if ($is_logged_in): ?>
                <a href="#" class="me-3" style="text-decoration: none;" data-bs-toggle="modal" data-bs-target="#userProfileModal">
                    <?php 
                        $profile_size_nav_mobile = '35px';
                        if (!empty($full_profile_path)): 
                        ?>
                            <img src="<?php echo $full_profile_path; ?>" 
                                alt="Profil" 
                                class="rounded-circle" 
                                style="width: <?php echo $profile_size_nav_mobile; ?>; height: <?php echo $profile_size_nav_mobile; ?>; object-fit: cover; border: 2px solid var(--primary-brand);">
                        <?php else: ?>
                            <i class="fas fa-user-circle fs-4 text-primary"></i> 
                        <?php endif; ?>
                </a>
            <?php endif; ?>

            <div class="dropdown" id="mobileMenuDropdownContainer">
                <button class="btn btn-link dropdown-toggle p-0" type="button" id="mobileMenuDropdown" 
                        data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none; border: none;">
                    <i class="fas fa-ellipsis-v fs-4 text-dark"></i>
                </button>
                
                <ul class="dropdown-menu dropdown-menu-end p-0 shadow-lg" aria-labelledby="mobileMenuDropdown" style="border: none; overflow: hidden;">
                    <?php if ($is_logged_in): ?>
                        
                        <li class="bg-light p-3 border-bottom">
                            <span class="d-block fw-bold text-dark"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="small text-muted">Selamat Datang!</span>
                        </li>
                        
                        <li><h6 class="dropdown-header mt-2 text-uppercase" style="font-size: 0.75rem;">Belanja</h6></li>
                        <li>
                            <a class="dropdown-item py-2 d-flex justify-content-between align-items-center" href="cart.php">
                                <span><i class="fas fa-shopping-cart me-2 text-success"></i> Keranjang</span>
                                <?php if ($cart_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="orders_user.php">
                                <i class="fas fa-list-alt me-2 text-primary"></i> Pesanan Saya
                            </a>
                        </li>

                        <li><hr class="dropdown-divider my-2"></li>

                        <li><h6 class="dropdown-header text-uppercase" style="font-size: 0.75rem;">Akun</h6></li>
                        <li>
                            <a class="dropdown-item py-2" href="#" data-bs-toggle="modal" data-bs-target="#userProfileModal">
                                <i class="fas fa-user-edit me-2 text-info"></i> Edit Profil
                            </a>
                        </li>

                        <li><hr class="dropdown-divider my-2"></li>
                        
                        <li><h6 class="dropdown-header text-uppercase" style="font-size: 0.75rem;">Info</h6></li>
                        <li><a class="dropdown-item py-2" href="kontak.php"><i class="fas fa-phone-alt me-2 text-secondary"></i> Kontak</a></li>
                        <li><a class="dropdown-item py-2" href="about.php"><i class="fas fa-info-circle me-2 text-secondary"></i> Tentang Kami</a></li>
                        
                        <li><hr class="dropdown-divider my-2"></li>
                        
                        <li>
                            <a class="dropdown-item py-3 text-danger fw-bold bg-light" href="logout_user.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');">
                                <i class="fas fa-sign-out-alt me-2"></i> Keluar
                            </a>
                        </li>

                    <?php else: ?>
                        <li><a class="dropdown-item py-3 text-primary fw-bold" href="login_user.php">
                            <i class="fas fa-sign-in-alt me-2"></i> Login / Daftar
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2" href="kontak.php"><i class="fas fa-phone-alt me-2"></i> Kontak</a></li>
                        <li><a class="dropdown-item py-2" href="about.php"><i class="fas fa-info-circle me-2"></i> Tentang Kami</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="collapse navbar-collapse d-none d-lg-block" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                
                <?php if ($is_logged_in): ?>
                
                <li class="nav-item me-lg-2">
                    <a class="btn btn-outline-secondary d-lg-block" href="kontak.php">
                        <i class="fas fa-phone-alt me-1"></i> Kontak
                    </a>
                </li>
                
                <li class="nav-item me-lg-2">
                    <a class="btn btn-outline-primary d-lg-block" href="orders_user.php">
                        <i class="fas fa-list-alt me-1"></i> Pesanan
                    </a>
                </li>
                
                <li class="nav-item me-lg-2">
                    <a class="btn btn-success text-white position-relative d-lg-block" href="cart.php">
                        <i class="fas fa-shopping-cart me-1"></i> Keranjang
                        <?php if ($cart_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $cart_count; ?>
                            <span class="visually-hidden">items in cart</span>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-item custom-dropdown me-lg-0" id="userDropdownContainer">
                    <a class="nav-link text-dark fw-bold custom-dropdown-toggle d-flex align-items-center justify-content-lg-start" id="userDropdownToggle">
                        
                        <?php 
                        $profile_size = '40px'; 
                        if (!empty($full_profile_path)): 
                        ?>
                            <img src="<?php echo $full_profile_path; ?>" 
                                alt="Profil" 
                                class="rounded-circle me-1" 
                                style="width: <?php echo $profile_size; ?>; height: <?php echo $profile_size; ?>; object-fit: cover; border: 2px solid var(--primary-brand);">
                        <?php else: ?>
                            <i class="fas fa-user-circle me-1 fs-4 text-primary"></i> 
                        <?php endif; ?>
                        
                        Halo, <?php echo htmlspecialchars($user_name); ?>! <i class="fas fa-caret-down ms-1"></i>
                    </a>
                    <div class="custom-dropdown-menu" id="userDropdownMenu">
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#userProfileModal"><i class="fas fa-user-edit me-1"></i> Edit Profil</a>
                        <a class="dropdown-item" href="about.php"><i class="fas fa-info-circle me-1"></i> Tentang Kami</a>
                        <hr class="dropdown-divider">
                        <a class="dropdown-item text-danger fw-bold" 
                            href="logout_user.php"
                            onclick="return confirm('Apakah Anda yakin ingin keluar dari akun ini?');"> 
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </div>
                </li>
                <?php else: ?>

                <li class="nav-item me-lg-2">
                    <a class="btn btn-outline-secondary d-lg-block" href="kontak.php">
                        <i class="fas fa-phone-alt me-1"></i> Kontak
                    </a>
                </li>
                
                <li class="nav-item me-lg-2">
                    <a class="btn btn-outline-secondary d-lg-block" href="about.php">
                        <i class="fas fa-info-circle me-1"></i> Tentang Kami
                    </a>
                </li>

                <li class="nav-item me-lg-0">
                    <a class="btn btn-primary text-white d-lg-block" href="login_user.php">
                        <i class="fas fa-sign-in-alt me-1"></i> Login / Daftar
                    </a>
                </li>

                <?php endif; ?>
            </ul>
        </div>
        </div>
</nav>

<div class="container mt-5 pt-4" id="produk-list">
    <h2 class="mb-4 text-center fw-bold text-dark-emphasis">Koleksi Produk 
        <?php 
        $current_category_name = "Terbaru";
        if ($selected_category_id > 0) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $selected_category_id) {
                    $current_category_name = htmlspecialchars($cat['name']);
                    break;
                }
            }
        }
        echo $current_category_name;
        ?>
    </h2>
    
    <div class="text-center mb-5 category-filter" id="produk-filter">
        <h4 class="mb-3 text-secondary">Filter Berdasarkan Kategori:</h4>
        
        <a href="toko_sepatu.php" class="btn <?php echo ($selected_category_id == 0) ? 'btn-primary' : 'btn-outline-secondary'; ?> rounded-pill m-1">
            Semua Produk
        </a>
        
        <?php foreach ($categories as $cat): 
            $is_active = ($selected_category_id == $cat['id']);
        ?>
            <a href="toko_sepatu.php?category_id=<?php echo $cat['id']; ?>" 
                class="btn <?php echo $is_active ? 'btn-primary' : 'btn-outline-secondary'; ?> rounded-pill m-1">
                <?php echo htmlspecialchars($cat['name']); ?>
            </a>
        <?php endforeach; ?>
        
        <?php if (empty($categories)): ?>
             <div class="alert alert-info mt-3" role="alert">Belum ada kategori yang terdaftar.</div>
        <?php endif; ?>

    </div>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 mb-5">
        
        <?php if (!empty($products)): ?>
            <?php foreach($products as $row): ?>
            
                <div class="col">
                    <div class="card product-card shadow-sm">
                        <?php 
                        $image_filename = htmlspecialchars($row['image_path']);
                        
                        $image_path_check = $uploads_url_path . $image_filename;
                        if (empty($image_filename) || (!file_exists($image_path_check) && !is_readable($image_path_check))) {
                            $image_url = 'https://via.placeholder.com/300x220?text=Produk'; 
                        } else {
                            $image_url = $image_path_check; 
                        }
                        ?>
                        
                        <div class="product-image-container">
                            <img src="<?php echo $image_url; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row['name']); ?>" loading="lazy">
                        </div>
                        
                        <div class="card-body p-3">
                            <h5 class="card-title text-truncate fw-bold"><?php echo htmlspecialchars($row['name']); ?></h5>
                            <?php 
                            $card_category_name = 'Lain-lain';
                            foreach ($categories as $cat) {
                                if (($row['category_id'] ?? 0) == $cat['id']) {
                                    $card_category_name = htmlspecialchars($cat['name']);
                                    break;
                                }
                            }
                            ?>
                            <span class="badge bg-secondary mb-2"><?php echo $card_category_name; ?></span>
                            
                            <p class="card-text text-danger fs-5 fw-bold mt-2 mb-3">
                                Rp <?php echo number_format($row['price'], 0, ',', '.'); ?>
                            </p>
                            
                            <div class="card-actions">
                                <button 
                                    data-product-id="<?php echo $row['id']; ?>" 
                                    class="btn btn-sm btn-outline-info w-100 mb-2 quick-view-btn"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#productDetailModal">
                                    <i class="fas fa-search me-1"></i> Tinjauan Cepat
                                </button>
                                
                                <?php if ($is_logged_in): ?>
                                <a href="add_to_cart.php?id=<?php echo $row['id']; ?>" class="btn btn-success w-100">
                                    <i class="fas fa-cart-plus"></i> Tambah ke Keranjang
                                </a>
                                <?php else: ?>
                                <a href="login_user.php" class="btn btn-primary w-100" onclick="alert('Silakan login terlebih dahulu untuk menambahkan produk ke keranjang!');">
                                    <i class="fas fa-sign-in-alt"></i> Login untuk Membeli
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> 
                    <?php if ($selected_category_id > 0): ?>
                        Tidak ada produk tersedia dalam kategori ini.
                    <?php else: ?>
                        Belum ada produk tersedia.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
    
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Penomoran Halaman Produk" class="my-5">
        <ul class="pagination justify-content-center">
            
            <?php 
            $base_url = 'toko_sepatu.php';
            $category_param = ($selected_category_id > 0) ? '&category_id=' . $selected_category_id : '';
            ?>

            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $base_url . '?p=' . max(1, $current_page - 1) . $category_param; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo; Sebelumnya</span>
                </a>
            </li>

            <?php 
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);
            
            if ($start_page > 1) {
                echo '<li class="page-item"><a class="page-link" href="' . $base_url . '?p=1' . $category_param . '">1</a></li>';
                if ($start_page > 2) {
                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                }
            }

            for ($i = $start_page; $i <= $end_page; $i++): 
                $is_active = ($i == $current_page) ? 'active' : '';
            ?>
            
            <li class="page-item <?php echo $is_active; ?>">
                <a class="page-link" href="<?php echo $base_url . '?p=' . $i . $category_param; ?>">
                    <?php echo $i; ?>
                </a>
            </li>

            <?php endfor; 
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                }
                echo '<li class="page-item"><a class="page-link" href="' . $base_url . '?p=' . $total_pages . $category_param . '">' . $total_pages . '</a></li>';
            }
            ?>
            
            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $base_url . '?p=' . min($total_pages, $current_page + 1) . $category_param; ?>" aria-label="Next">
                    <span aria-hidden="true">Selanjutnya &raquo;</span>
                </a>
            </li>
            
        </ul>
    </nav>
    <?php endif; ?>
    
    <div class="about-footer-link border-top mt-4 pt-4">
        <a href="about.php" class="btn btn-outline-dark btn-lg">
            <i class="fas fa-info-circle me-2"></i> Mengenal Kami Lebih Dekat
        </a>
    </div>

</div> <?php if ($is_logged_in): ?>
<div class="modal fade" id="userProfileModal" tabindex="-1" aria-labelledby="userProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content shadow-lg">
            
            <div class="modal-header bg-primary-custom text-white"> 
                <h5 class="modal-title fw-bold" id="userProfileModalLabel"><i class="fas fa-user-edit me-2"></i> Edit Profil Saya</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 p-sm-5">
                
                <div class="text-center mb-4">
                    <img src="<?php echo $profile_img_url_modal; ?>" class="rounded-circle profile-img" alt="Foto Profil">
                    <h4 class="mt-3 fw-bold text-primary"><?php echo htmlspecialchars($user_data['name'] ?? ''); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($user_data['email'] ?? ''); ?></p>
                </div>

                <form action="user_profile_handler.php" method="POST" enctype="multipart/form-data">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">Nama Lengkap</label>
                        <input type="text" class="form-control form-control-lg" id="name" name="name" 
                            value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-bold">Alamat Email</label>
                        <input type="email" class="form-control form-control-lg bg-light" id="email" name="email" 
                            value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required readonly disabled>
                        <small class="text-info"><i class="fas fa-info-circle me-1"></i> Email adalah ID unik, tidak dapat diubah dari sini.</small>
                    </div>

                    <div class="mb-4 p-3 rounded file-upload-box"> 
                        <label for="profile_image" class="form-label fw-bold text-primary"><i class="fas fa-camera me-1"></i> Ganti Foto Profil (Opsional)</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg, image/png">
                        <small class="text-muted">Maksimal ukuran file 2MB. Format: JPG atau PNG.</small>
                    </div>
                    
                    <?php if (!empty($current_profile_img)): ?>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" value="1" id="delete_photo" name="delete_photo">
                        <label class="form-check-label text-danger fw-bold" for="delete_photo">
                            <i class="fas fa-trash me-1"></i> Hapus Foto Profil Saat Ini
                        </label>
                    </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" name="update_profile" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="productDetailModal" tabindex="-1" aria-labelledby="productDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productDetailModalLabel">Detail Produk Cepat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modal-detail-content">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2">Memuat detail...</p>
                </div>
            </div>
            <div class="modal-footer">
                <a id="modal-link-full-detail" href="#" class="btn btn-outline-secondary">Lihat Halaman Detail Penuh</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<footer class="bg-dark text-white pt-5 pb-3 mt-5"> 
    <div class="container">
        <div class="row">
            
            <div class="col-md-4 mb-4">
                <h5 class="fw-bold text-uppercase mb-3" style="color: #0d6efd;"><?php echo $site_name; ?></h5>
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
                
                <a href="kontak.php" class="btn text-white fw-bold" style="background-color: #0d6efd; border-color: #0d6efd;">
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
// ==========================================================
// 🔥 FUNGSI SWEET ALERT (BARU) 🔥
// ==========================================================
function showSweetAlert(icon, title, text, timer = 4000) {
    Swal.fire({
        icon: icon,
        title: title,
        text: text,
        showConfirmButton: false,
        timer: timer,
    });
}


document.addEventListener('DOMContentLoaded', function() {
    // SCRIPT FADE-IN
    document.body.classList.add('page-loaded');

    // Fungsionalitas Toast (Dipertahankan untuk error/info non-fatal)
    <?php if ($toast_message): ?>
    var toastEl = document.getElementById('liveToast');
    if (toastEl) {
        var toast = new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 4000 
        });
        toast.show();
    }
    <?php endif; ?>

    // ==========================================================
    // 🔥 LOGIKA PEMICU SWEET ALERT DARI SESI PHP 🔥
    // ==========================================================
    const sessionSwalTitle = "<?= htmlspecialchars($session_swal_title) ?>";
    const sessionSwalText = "<?= htmlspecialchars($session_swal_text) ?>";
    const sessionSwalIcon = "<?= htmlspecialchars($session_swal_icon) ?>"; 

    if (sessionSwalTitle && sessionSwalIcon) {
          showSweetAlert(
              sessionSwalIcon, 
              sessionSwalTitle, 
              sessionSwalText,
              4000 
          );
    }
    // ==========================================================


    // 🔥 FIX UTAMA: Memaksa Re-render di Navigasi History (Mobile) 🔥
    if (window.performance && window.performance.navigation.type === 2) {
        setTimeout(function() {
            window.dispatchEvent(new Event('resize'));
        }, 100); 
    }

    // LOGIKA JAVASCRIPT UNTUK MODAL PROFIL (Auto-show jika ada redirect)
    <?php if ($is_logged_in && $show_profile_modal): ?>
    var profileModalEl = document.getElementById('userProfileModal');
    if (profileModalEl) {
        var profileModal = new bootstrap.Modal(profileModalEl);
        profileModal.show();
    }
    <?php endif; ?>

    // LOGIKA JAVASCRIPT AUTO-HIDE NAVBAR
    const mainNavbar = document.getElementById('mainNavbar');
    let lastScrollTop = 0;
    const scrollThreshold = 10; 
    
    const navbarHeight = mainNavbar.offsetHeight; 

    window.addEventListener('scroll', function() {
        let currentScroll = window.pageYOffset || document.documentElement.scrollTop;

        if (currentScroll > lastScrollTop && currentScroll > navbarHeight) {
            mainNavbar.classList.add('hidden');
        } 
        else if (currentScroll < lastScrollTop) {
            mainNavbar.classList.remove('hidden');
        }
        
        if (currentScroll <= scrollThreshold) {
            mainNavbar.classList.remove('hidden');
        }

        lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;

    }, { passive: true }); 


    // Logika Dropdown Profil KUSTOM (Untuk Desktop)
    const userDropdownToggle = document.getElementById('userDropdownToggle');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    const userDropdownContainer = document.getElementById('userDropdownContainer'); 

    if (userDropdownToggle && userDropdownMenu) {
        userDropdownToggle.addEventListener('click', function(e) {
            e.preventDefault();
            userDropdownMenu.classList.toggle('show');
            
            if (window.innerWidth >= 992) {
                 e.stopPropagation(); 
            }
        });

        document.addEventListener('click', function(e) {
            if (window.innerWidth >= 992 && userDropdownContainer && !userDropdownContainer.contains(e.target)) {
                 userDropdownMenu.classList.remove('show');
            }
        });
        
        userDropdownMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // LOGIKA JAVASCRIPT AJAX QUICK VIEW
    const detailModal = document.getElementById('productDetailModal');
    const modalContent = document.getElementById('modal-detail-content');
    const modalLinkFullDetail = document.getElementById('modal-link-full-detail');

    if (detailModal) {
        
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.quick-view-btn');
            
            if (btn) {
                e.preventDefault();
                const productId = btn.getAttribute('data-product-id');
                
                modalContent.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-3x text-primary"></i>
                        <p class="mt-3">Memuat detail produk...</p>
                    </div>`;
                
                modalLinkFullDetail.href = 'detail.php?id=' + productId;
                
                fetch('detail_ajax.php?id=' + productId)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Gagal memuat detail produk. Status: ' + response.status);
                        }
                        return response.text();
                    })
                    .then(html => {
                        modalContent.innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        modalContent.innerHTML = `
                            <div class="alert alert-danger" role="alert">
                                Gagal memuat detail produk: ${error.message}
                            </div>`;
                    });
            }
        });
    }
});
</script>
</body>
</html>