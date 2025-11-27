<?php
/**
 * admin/sidebar.php
 * Struktur Sidebar Navigasi untuk Admin Panel dengan styling dan Dropdown (Collapse).
 * PERBAIKAN: Kode JS Scroll dan Tombol Scroll dipindahkan ke file parent (dashboard.php)
 * 🔥 TAMBAHAN: Kelola Laporan di bawah kategori baru "ANALITIK & LAPORAN"
 */

// Pastikan variabel $router_path dan $page tersedia dari dashboard.php
$router_path = $router_path ?? 'dashboard.php';
// Mengambil halaman aktif dari URL
$current_page = $page ?? ($_GET['page'] ?? 'dashboard/main'); 

// Helper function untuk menentukan kelas 'active' dan 'show' (untuk collapse)
function is_active($target_segment, $current) {
    // Menambahkan pemeriksaan untuk tautan laporan tunggal atau grup
    if (strpos($current, $target_segment) !== false) {
        return 'active-link';
    }
    return '';
}

function is_expanded($target_segments, $current) {
    foreach ($target_segments as $segment) {
        if (strpos($current, $segment) !== false) {
            return 'show'; 
        }
    }
    return '';
}

// Definisikan segmen untuk setiap grup collapse
$store_segments = ['products/', 'orders/', 'categories/'];
$user_segments = ['administrator_management/', 'user_management/'];

// Tentukan apakah grup Toko harus terbuka
$store_is_expanded = is_expanded($store_segments, $current_page);
// Tentukan apakah grup Pengguna harus terbuka
$user_is_expanded = is_expanded($user_segments, $current_page);
?>

<style>
    /* Styling Kustom untuk Sidebar */
    
    .feather {
          width: 1rem;
          height: 1rem;
          vertical-align: text-bottom;
    }
    
    #layoutSidenav_nav {
        background-color: #2c3e50 !important; 
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.15); 
        color: #ecf0f1; 
        transition: width 0.3s ease, transform 0.3s ease; 
        overflow-x: hidden !important; 
    }
    
    /* KOREKSI SCROLL: Jadikan <nav> flex container dengan arah kolom dan 100% tinggi */
    #layoutSidenav_nav .nav {
        height: 100%; 
        flex-direction: column;
        padding: 0 !important; 
    }

    .sidebar-header {
        text-align: center;
        padding: 15px; 
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .sidebar-header h5 {
        font-size: 1.1em;
        letter-spacing: 0.1em; 
    }

    /* KOREKSI SCROLL: Kontainer menu utama, berikan flex-grow dan scroll otomatis */
    .nav-container {
        flex-grow: 1; 
        overflow-y: auto; 
        padding: 15px 15px 5px 15px;
        scrollbar-width: thin;
        scrollbar-color: #34495e #2c3e50; 
    }
    .nav-container::-webkit-scrollbar {
        width: 6px;
    }
    .nav-container::-webkit-scrollbar-track {
        background: #2c3e50;
    }
    .nav-container::-webkit-scrollbar-thumb {
        background-color: #34495e;
        border-radius: 20px;
    }

    /* Gaya untuk link biasa dan collapse toggle (Parent link) */
    .nav-link {
        color: #bdc3c7; 
        padding: 10px 15px;
        border-radius: 5px;
        transition: background-color 0.3s ease, color 0.3s ease; 
        margin-bottom: 5px; 
        text-align: left;
        font-size: 0.9em; 
    }

    .nav-link:hover {
        background-color: #34495e; 
        color: #ecf0f1;
    }
    
    /* Active State (Soft Teal/Emerald Modern) */
    .nav-link.active-link {
        background-color: #00b894; 
        color: white;
        font-weight: 600; 
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); 
    }
    
    /* Small Category Text */
    .small.fw-bold.text-muted {
        color: #7f8c8d !important; 
        padding: 5px 0;
        font-size: 0.8em;
        margin-left: 5px;
        letter-spacing: 0.05em; 
        text-transform: uppercase; 
    }

    /* 🔥 PERBAIKAN TRANSISI DROPDOWN: Menambahkan transisi untuk tinggi (height) */
    .collapse {
        transition: height 0.35s ease !important;
    }

    /* Styling untuk sub-menu (dalam collapse) */
    .collapse-menu {
        padding-top: 0px; 
        padding-bottom: 0px; 
    }

    .collapse-menu .nav-link {
        padding-left: 35px;
        font-weight: normal;
        margin-bottom: 3px; 
        font-size: 0.85em; 
        background-color: transparent !important; 
    }
    .collapse-menu .nav-link:hover {
        background-color: #34495e !important; 
        color: #ecf0f1 !important;
    }
    /* Sub-menu Active State (Darker shade of primary color) */
    .collapse-menu .nav-link.active-link {
        background-color: #009975 !important; 
        color: white !important;
        font-weight: 600;
        border-left: 3px solid #feca57; 
        padding-left: 32px; 
    }

    /* Gaya untuk indikator dropdown (caret) */
    .dropdown-indicator {
        float: right;
        transition: transform 0.3s ease;
        width: 1em; 
        height: 1em;
        margin-left: 5px;
    }
    .nav-link[aria-expanded="true"] .dropdown-indicator {
        transform: rotate(180deg);
    }

    .sidebar-footer {
        padding: 15px; 
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        flex-shrink: 0; 
    }
    
    .sidebar-footer .btn {
        transition: background-color 0.3s ease, opacity 0.3s ease;
    }

    /* ===== CSS UNTUK FLOATING SCROLL BUTTON (di luar sidebar) ===== */
    .floating-scroll-btn {
        position: fixed; 
        right: 20px; 
        bottom: 20px; 
        z-index: 1050; 
        width: 45px;
        height: 45px;
        border-radius: 50%; 
        background-color: #00b894; 
        color: white;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        border: none;
        transition: background-color 0.3s, opacity 0.3s, transform 0.3s;
        display: none; 
        cursor: pointer;
        font-size: 1.2em;
        line-height: 45px;
        text-align: center;
        opacity: 0.8;
    }

    .floating-scroll-btn:hover {
        background-color: #009975;
        opacity: 1;
        transform: scale(1.05);
    }
</style>

<div id="layoutSidenav_nav">
    <nav class="nav flex-column"> 
        
        <div class="sidebar-header">
            <h5 class="m-0 text-white"><i data-feather="command" class="me-2 feather"></i> ADMIN PANEL</h5>
        </div>

        <div class="nav-container">
            
            <div class="small fw-bold text-muted mt-2 mb-2">CORE</div>
            
            <a class="nav-link <?php echo is_active('dashboard/main', $current_page); ?>" 
                href="<?php echo $router_path; ?>?page=dashboard/main">
                <i data-feather="home" class="me-2 feather"></i>
                Dashboard Utama
            </a>
            
            
            <div class="small fw-bold text-muted mt-3 mb-2">ANALITIK & LAPORAN</div>
            
            <a class="nav-link <?php echo is_active('reports/main', $current_page); ?>" 
                href="<?php echo $router_path; ?>?page=reports/main">
                <i data-feather="bar-chart-2" class="me-2 feather"></i>
                Laporan & Analitik
            </a>
            <div class="small fw-bold text-muted mt-3 mb-2">MANAJEMEN TOKO</div>
            
            <a class="nav-link <?php echo $store_is_expanded ? 'active-link' : ''; ?>" 
                data-bs-toggle="collapse" 
                href="#collapseStore" 
                role="button" 
                aria-expanded="<?php echo $store_is_expanded ? 'true' : 'false'; ?>" 
                aria-controls="collapseStore">
                <i data-feather="shopping-cart" class="me-2 feather"></i>
                Toko & Produk
                <i data-feather="chevron-down" class="dropdown-indicator"></i>
            </a>
            
            <div class="collapse <?php echo $store_is_expanded; ?>" id="collapseStore">
                <div class="nav flex-column collapse-menu">
                    
                    <a class="nav-link <?php echo is_active('products/', $current_page); ?>" 
                        href="<?php echo $router_path; ?>?page=products/index">
                        <i data-feather="box" class="me-2 feather"></i>
                        Produk
                    </a>

                    <a class="nav-link <?php echo is_active('orders/', $current_page); ?>" 
                        href="<?php echo $router_path; ?>?page=orders/index">
                        <i data-feather="package" class="me-2 feather"></i>
                        Pesanan
                    </a>

                    <a class="nav-link <?php echo is_active('categories/', $current_page); ?>" 
                        href="<?php echo $router_path; ?>?page=categories/index">
                        <i data-feather="tag" class="me-2 feather"></i>
                        Kategori
                    </a>
                    
                </div>
            </div>
            
            
            <div class="small fw-bold text-muted mt-3 mb-2">MANAJEMEN PENGGUNA</div>

            <a class="nav-link <?php echo $user_is_expanded ? 'active-link' : ''; ?>" 
                data-bs-toggle="collapse" 
                href="#collapseUsers" 
                role="button" 
                aria-expanded="<?php echo $user_is_expanded ? 'true' : 'false'; ?>" 
                aria-controls="collapseUsers">
                <i data-feather="users" class="me-2 feather"></i>
                Pengguna & Admin
                <i data-feather="chevron-down" class="dropdown-indicator"></i>
            </a>

            <div class="collapse <?php echo $user_is_expanded; ?>" id="collapseUsers">
                <div class="nav flex-column collapse-menu">

                    <a class="nav-link <?php echo is_active('administrator_management/', $current_page); ?>" 
                        href="<?php echo $router_path; ?>?page=administrator_management/index">
                        <i data-feather="user-check" class="me-2 feather"></i>
                        Manajemen Admin
                    </a>

                    <a class="nav-link <?php echo is_active('user_management/', $current_page); ?>" 
                        href="<?php echo $router_path; ?>?page=user_management/index">
                        <i data-feather="user-minus" class="me-2 feather"></i>
                        Manajemen Pelanggan
                    </a>

                </div>
            </div>
        
        </div>
        
        <div class="sidebar-footer">
            <a class="btn btn-danger btn-sm w-100" href="logout.php"
                onclick="return confirm('Apakah Anda yakin ingin keluar (logout) dari sesi ini?');">
                <i data-feather="log-out" class="me-1 feather"></i> Logout
            </a>
            <div class="mt-2 pt-2 small fw-bold text-white text-center">
                Log in sebagai: <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
            </div>
        </div>

    </nav>
</div>