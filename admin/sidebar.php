<?php
/**
 * admin/sidebar.php – Sidebar versi FIXED (Dropdown, Caret, Scroll, Feather)
 */

$router_path = $router_path ?? 'dashboard.php';
$current_page = $page ?? ($_GET['page'] ?? 'dashboard/main');

function is_active($target_segment, $current) {
    // 🔥 PERBAIKAN: Gunakan preg_quote() untuk meng-escape karakter khusus regex, seperti '/' atau karakter yang dapat disalahartikan sebagai modifier (seperti 'k').
    $escaped_segment = preg_quote($target_segment, '/');
    
    // Baris 10 (sudah diperbaiki):
    if (preg_match("/{$escaped_segment}(\/|$)/", $current)) { 
        return 'active-link';
    }
    return '';
}

function is_expanded($segments, $current) {
    foreach ($segments as $seg) {
        if (strpos($current, $seg) !== false) {
            return 'show';
        }
    }
    return '';
}

$store_segments = ['products/', 'orders/', 'categories/'];
$user_segments = ['administrator_management/', 'user_management/'];
// 🔥 Tambahkan segment untuk Kelola About
$about_segment = 'settings/kelola_about/'; 
// 🔥 BARU: Tambahkan segment untuk Kelola Brand
$brand_segment = 'settings/kelola_brand/'; 


$store_is_expanded = is_expanded($store_segments, $current_page);
$user_is_expanded = is_expanded($user_segments, $current_page);

// 🔥 Definisikan status aktif untuk Kelola About
$about_is_active = is_active($about_segment, $current_page);
// 🔥 BARU: Definisikan status aktif untuk Kelola Brand
$brand_is_active = is_active($brand_segment, $current_page); 
?>

<style>
    /* EXACT SAME STYLE, HANYA DIBENARKAN STRUKTUR & RESPONSIVE FIX */
    .feather{width:1rem;height:1rem;vertical-align:text-bottom;}

    #layoutSidenav_nav{
        background:#2c3e50!important;
        color:#ecf0f1;
        box-shadow:2px 0 5px rgba(0,0,0,0.15);
        overflow:hidden;
        transition:all .3s ease;
    }

    .sb-sidenav-menu{
        display:flex;
        flex-direction:column;
        height:100%;
    }

    .sidebar-header{
        padding:15px;
        text-align:center;
        border-bottom:1px solid rgba(255,255,255,.1);
    }

    .nav-container{
        flex-grow:1;
        overflow-y:auto;
        padding:15px;
        scrollbar-width:thin;
        scrollbar-color:#34495e #2c3e50;
    }
    .nav-container::-webkit-scrollbar{width:6px;}
    .nav-container::-webkit-scrollbar-thumb{background:#34495e;border-radius:20px;}

    .nav-link{color:#bdc3c7;padding:10px 15px;border-radius:5px;margin-bottom:5px;transition:.3s;}
    .nav-link:hover{background:#34495e;color:#ecf0f1;}
    .active-link{background:#00b894;color:#fff;font-weight:600;}

    .small.fw-bold.text-muted{color:#7f8c8d!important;text-transform:uppercase;font-size:.8em;}

    .collapse-menu .nav-link{padding-left:35px;margin-bottom:4px;font-size:.85em;}
    .collapse-menu .nav-link.active-link{
        background:#009975!important;color:#fff!important;
        border-left:3px solid #feca57;padding-left:32px;
    }

    .dropdown-indicator{
        float:right;
        transition:transform .3s ease;
    }
    .dropdown-indicator.rotate{transform:rotate(180deg);}
</style>

<div id="layoutSidenav_nav">
    <nav class="sb-sidenav-menu">

        <div class="sidebar-header">
            <h5 class="m-0 text-white">
                <i data-feather="command" class="me-2 feather"></i> ADMIN PANEL
            </h5>
        </div>

        <div class="nav-container">

            <div class="small fw-bold text-muted mt-2 mb-2">CORE</div>

            <a class="nav-link <?= is_active('dashboard/main',$current_page); ?>"
                href="<?= $router_path; ?>?page=dashboard/main">
                <i data-feather="home" class="me-2 feather"></i> Dashboard Utama
            </a>

            <div class="small fw-bold text-muted mt-3 mb-2">ANALITIK & LAPORAN</div>

            <a class="nav-link <?= is_active('reports/main',$current_page); ?>"
                href="<?= $router_path; ?>?page=reports/main">
                <i data-feather="bar-chart-2" class="me-2 feather"></i> Laporan & Analitik
            </a>

            <div class="small fw-bold text-muted mt-3 mb-2">MANAJEMEN TOKO</div>

            <a class="nav-link <?= $store_is_expanded ? 'active-link' : ''; ?>"
                data-bs-toggle="collapse"
                href="#collapseStore"
                role="button"
                aria-expanded="<?= $store_is_expanded ? 'true' : 'false'; ?>">
                <i data-feather="shopping-cart" class="me-2 feather"></i>
                Toko & Produk
                <i class="dropdown-indicator feather" data-feather="chevron-down"></i>
            </a>

            <div class="collapse <?= $store_is_expanded; ?>" id="collapseStore">
                <div class="collapse-menu">
                    <a class="nav-link <?= is_active('products/',$current_page); ?>"
                        href="<?= $router_path; ?>?page=products/index">
                        <i data-feather="box" class="me-2 feather"></i> Produk
                    </a>

                    <a class="nav-link <?= is_active('orders/',$current_page); ?>"
                        href="<?= $router_path; ?>?page=orders/index">
                        <i data-feather="package" class="me-2 feather"></i> Pesanan
                    </a>

                    <a class="nav-link <?= is_active('categories/',$current_page); ?>"
                        href="<?= $router_path; ?>?page=categories/index">
                        <i data-feather="tag" class="me-2 feather"></i> Kategori
                    </a>
                </div>
            </div>

            <div class="small fw-bold text-muted mt-3 mb-2">MANAJEMEN PENGGUNA</div>

            <a class="nav-link <?= $user_is_expanded ? 'active-link' : ''; ?>"
                data-bs-toggle="collapse"
                href="#collapseUsers"
                aria-expanded="<?= $user_is_expanded ? 'true' : 'false'; ?>">
                <i data-feather="users" class="me-2 feather"></i>
                Pengguna & Admin
                <i class="dropdown-indicator feather" data-feather="chevron-down"></i>
            </a>

            <div class="collapse <?= $user_is_expanded; ?>" id="collapseUsers">
                <div class="collapse-menu">

                    <a class="nav-link <?= is_active('administrator_management/',$current_page); ?>"
                        href="<?= $router_path; ?>?page=administrator_management/index">
                        <i data-feather="user-check" class="me-2 feather"></i> Manajemen Admin
                    </a>

                    <a class="nav-link <?= is_active('user_management/',$current_page); ?>"
                        href="<?= $router_path; ?>?page=user_management/index">
                        <i data-feather="user-minus" class="me-2 feather"></i> Manajemen Pelanggan
                    </a>

                </div>
            </div>

            <div class="small fw-bold text-muted mt-3 mb-2">PENGATURAN TOKO</div>

            <a class="nav-link <?= is_active('settings/kontak/',$current_page); ?>"
                href="<?= $router_path; ?>?page=settings/kontak/index">
                <i data-feather="phone-call" class="me-2 feather"></i> Kelola Info Kontak
            </a>

            <a class="nav-link <?= $about_is_active; ?>"
                href="<?= $router_path; ?>?page=settings/kelola_about/index">
                <i data-feather="book-open" class="me-2 feather"></i> Kelola Halaman About
            </a>

            <a class="nav-link <?= $brand_is_active; ?>"
                href="<?= $router_path; ?>?page=settings/kelola_brand/index">
                <i data-feather="gift" class="me-2 feather"></i> Kelola Brand & Logo
            </a>
            </div>

        <div class="sidebar-footer p-3 border-top text-center">
            <a class="btn btn-danger btn-sm w-100" href="logout.php"
                onclick="return confirm('Apakah Anda yakin ingin keluar?');">
                <i data-feather="log-out" class="me-1 feather"></i> Logout
            </a>
            <div class="mt-2 small text-white">
                Login sebagai: <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
            </div>
        </div>

    </nav>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Memastikan feather.replace() hanya dipanggil sekali
    if (typeof feather !== 'undefined') {
        feather.replace();
    } else {
        console.error("Feather Icons library not loaded.");
    }


    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (el) {
        el.addEventListener("click", function () {
            let indicator = el.querySelector(".dropdown-indicator");
            // Menggunakan event Bootstrap yang lebih tepat
            let targetId = el.getAttribute('href');
            let targetCollapse = document.querySelector(targetId);

            // Handle collapse logic manually if needed, or rely on Bootstrap's collapse.
            // Timeout untuk memastikan ikon berputar setelah Bootstrap menyelesaikan transisi
            setTimeout(() => {
                if (targetCollapse.classList.contains('show')) {
                    indicator.classList.add("rotate");
                } else {
                    indicator.classList.remove("rotate");
                }
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            }, 150);
        });
    });
});
</script>