<?php
/**
 * admin/reports/main.php
 * Halaman utama untuk menampilkan laporan, ringkasan data, dan tabel detail.
 * PERBAIKAN: Memastikan semua ringkasan statistik diambil dari query database yang akurat.
 */

// Pastikan koneksi tersedia
if (!isset($GLOBALS['koneksi'])) {
    $content_output = '<div class="alert alert-danger mt-4">Koneksi database tidak tersedia. Mohon cek file koneksi.php Anda.</div>';
    echo $content_output;
    return;
}

// Ambil koneksi
$koneksi = $GLOBALS['koneksi'];

// Fungsi untuk format mata uang
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

// =======================================================
// 1. PENGAMBILAN INPUT FILTER
// =======================================================
$filter_category_id = $_GET['category_id'] ?? '';
$filter_min_stock = $_GET['min_stock'] ?? '';
$filter_max_price = $_GET['max_price'] ?? '';
$filter_product_name = $_GET['product_name'] ?? '';

// =======================================================
// 2. QUERY RINGKASAN DATA (TOTALS) - Mengambil Data Real
// =======================================================
$total_products = 0; // Jumlah Produk UNIK yang memiliki stok > 0
$total_customers = 0; 
$total_revenue = 0;
$total_order_month = 0;
$revenue_month = 0;

$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t 23:59:59');

// A. Total Produk Aktif (Gabungan Stok Varian > 0)
// Menghitung jumlah jenis produk yang memiliki stok.
$query_products_active = "
    SELECT COUNT(DISTINCT p.id) AS total
    FROM products p
    JOIN product_variants pv ON p.id = pv.product_id
    WHERE pv.stock > 0
";
$result_products = $koneksi->query($query_products_active);
if ($result_products) {
    $total_products = $result_products->fetch_assoc()['total'] ?? 0;
}

// B. Total Unit Stok (Total semua unit di semua varian)
$query_total_unit_stock = "
    SELECT COALESCE(SUM(stock), 0) AS total_units 
    FROM product_variants
";
$result_total_unit_stock = $koneksi->query($query_total_unit_stock);
$total_unit_stock = $result_total_unit_stock ? ($result_total_unit_stock->fetch_assoc()['total_units'] ?? 0) : 0;


// C. Total Pelanggan (Asumsi tabel users)
$query_customers = "SELECT COUNT(*) AS total FROM users"; 
$result_customers = $koneksi->query($query_customers);
if ($result_customers) {
    $total_customers = $result_customers->fetch_assoc()['total'] ?? 0;
}

// D. Total Pendapatan GLOBAL (Pesanan dengan status 'selesai')
$query_revenue_global = "SELECT SUM(total_amount) AS total FROM orders WHERE status = 'selesai'"; 
$result_revenue_global = $koneksi->query($query_revenue_global);

if ($result_revenue_global && $result_revenue_global->num_rows > 0) {
    $row = $result_revenue_global->fetch_assoc();
    $total_revenue = $row['total'] ?? 0;
}


// Query Ringkasan Pesanan Bulan Ini
$query_summary_month = "
    SELECT 
        status, 
        COUNT(*) AS total_count, 
        SUM(total_amount) AS total_amount_sum
    FROM orders
    WHERE order_date BETWEEN '{$current_month_start}' AND '{$current_month_end}'
    GROUP BY status
";
$result_summary_month = $koneksi->query($query_summary_month);
$summary_month = [];

if ($result_summary_month) {
    while($row = $result_summary_month->fetch_assoc()) {
        $summary_month[] = $row;
        if (strtolower($row['status']) == 'selesai') { 
            $revenue_month += $row['total_amount_sum'];
        }
        $total_order_month += $row['total_count'];
    }
}

// Query untuk mengambil daftar kategori (untuk filter dropdown)
$query_categories = "SELECT id, name FROM categories ORDER BY name ASC";
$result_categories = $koneksi->query($query_categories);
$categories = [];
if ($result_categories) {
    while($cat = $result_categories->fetch_assoc()){
        $categories[] = $cat;
    }
}


// =======================================================
// 3. QUERY LAPORAN STOK (PREPARED STATEMENT)
// =======================================================
$where_clauses = [];
$having_clauses = [];
$bind_types = '';
$bind_params = [];

// 1. Filter Nama Produk (WHERE)
if (!empty($filter_product_name)) {
    $where_clauses[] = "p.name LIKE ?";
    $bind_types .= 's'; 
    $bind_params[] = "%" . $filter_product_name . "%";
}

// 2. Filter Kategori (WHERE)
if (is_numeric($filter_category_id) && $filter_category_id > 0) {
    $where_clauses[] = "p.category_id = ?";
    $bind_types .= 'i'; 
    $bind_params[] = (int)$filter_category_id;
}

// 3. Filter Harga Maksimal (WHERE)
if (is_numeric($filter_max_price) && $filter_max_price >= 0) {
    $where_clauses[] = "p.price <= ?";
    $bind_types .= 'd'; 
    $bind_params[] = (float)$filter_max_price;
}

// 4. Filter Stok Minimal (HAVING)
if (is_numeric($filter_min_stock) && $filter_min_stock >= 0) {
    $having_clauses[] = "stock >= " . (int)$filter_min_stock;
}

// Gabungkan klausa WHERE
$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Gabungkan klausa HAVING
$having_sql = count($having_clauses) > 0 ? " HAVING " . implode(" AND ", $having_clauses) : "";


// Query utama laporan stok dengan placeholder (?)
$query_stock_report_template = "
    SELECT 
        p.id, 
        p.name, 
        p.price, 
        c.name AS category_name,
        COALESCE(SUM(pv.stock), 0) AS stock
    FROM 
        products p
    LEFT JOIN 
        categories c ON p.category_id = c.id
    LEFT JOIN
        product_variants pv ON p.id = pv.product_id 
    " . $where_sql . "
    GROUP BY
        p.id, p.name, p.price, c.name 
    " . $having_sql . "
    ORDER BY 
        stock DESC, p.name ASC";

// Siapkan Prepared Statement
$result_stock_report = null;
$stmt_stock = $koneksi->prepare($query_stock_report_template);

if ($stmt_stock) {
    // Bind parameter jika ada
    if (!empty($bind_types)) {
        $tmp_params = array_merge([$bind_types], $bind_params);
        $refs = [];
        foreach ($tmp_params as $key => $value) {
            $refs[$key] = &$tmp_params[$key];
        }
        call_user_func_array([$stmt_stock, 'bind_param'], $refs);
    }
    
    // Eksekusi dan ambil hasilnya
    $stmt_stock->execute();
    $result_stock_report = $stmt_stock->get_result();
    $stmt_stock->close();

} 


// =======================================================
// 4. QUERY LAPORAN PELANGGAN - (Tidak perlu Prepared Statement)
// =======================================================
$query_users = "SELECT id, name, email, created_at FROM users ORDER BY created_at DESC LIMIT 10"; 
$result_users = $koneksi->query($query_users);

?>

<style>
/* 🔥 CSS Internal untuk Styling Tampilan Modern */
:root {
    --primary-reports: #007bff; /* Biru terang untuk Primary */
    --success-reports: #28a745; /* Hijau */
    --warning-reports: #ffc107; /* Kuning */
    --info-reports: #17a2b8; /* Cyan */
    --dark-reports: #343a40; /* Dark */
    --danger-reports: #E74C3C;
}

/* Base Style */
.card {
    border-radius: 10px;
    transition: all 0.3s ease;
}

/* Card Statistik */
.stat-card {
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    color: white !important;
    /* 🔥 Tambahkan transisi untuk efek hover */
    transition: all 0.3s ease-in-out; 
}
/* 🔥 Efek Hover */
.stat-card:hover {
    transform: translateY(-5px); 
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2); 
}

.stat-card .h5 {
    font-size: 2rem;
    font-weight: 700;
}
.stat-card .text-xs {
    font-size: 0.8rem;
    font-weight: 600;
    opacity: 0.8;
}
.stat-card .col-auto i {
    opacity: 0.7;
}

/* Header dan Tabel */
.card-header-styled {
    background-color: var(--dark-reports) !important;
    color: white;
    font-weight: bold;
    border-bottom: none;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}
.table {
    font-size: 0.95rem;
}
.table-reports thead th {
    background-color: var(--dark-reports) !important;
    color: #fff;
    border-color: #454d55;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.table-reports tbody tr {
    transition: background-color 0.2s;
}
.table-reports tbody tr:hover {
    background-color: #f8f9fa;
}

/* Warna Row Status */
.row-success-bg { background-color: rgba(40, 167, 69, 0.1); font-weight: 600; }
.row-danger-bg { background-color: rgba(231, 76, 60, 0.1); color: var(--danger-reports); }
.row-secondary-bg { background-color: #f1f1f1; font-weight: 700; }

/* Warna Row Stok */
.table-stock .table-danger {
    background-color: #fddde0 !important; /* Merah muda sangat lembut */
    font-weight: 600;
    color: var(--danger-reports);
}
.table-stock .table-warning {
    background-color: #fff8e1 !important; /* Kuning muda sangat lembut */
}

/* Styling filter */
.filter-form-row {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}
.filter-form-row .form-label {
    margin-bottom: 0.2rem;
    font-weight: 600;
}
@media print {
    .no-print {
        display: none !important;
    }
}
</style>

<h1 class="mt-4 mb-4 text-primary"><i data-feather="bar-chart-2" class="me-2"></i> Laporan & Analitik</h1>
<p class="lead text-secondary">Ringkasan data utama dan laporan stok terperinci toko Anda.</p>

<div class="row">
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100 py-3" style="background-color: var(--success-reports);">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col me-2">
                        <div class="text-xs fw-bold text-uppercase mb-1">Total Pendapatan (Selesai)</div>
                        <div class="h5 mb-0"><?php echo format_rupiah($total_revenue); ?></div>
                    </div>
                    <div class="col-auto">
                        <i data-feather="dollar-sign" style="width: 3rem; height: 3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100 py-3" style="background-color: var(--primary-reports);">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col me-2">
                        <div class="text-xs fw-bold text-uppercase mb-1">Pesanan Bulan Ini (<?php echo date('M Y'); ?>)</div>
                        <div class="h5 mb-0"><?php echo number_format($total_order_month); ?></div>
                    </div>
                    <div class="col-auto">
                        <i data-feather="shopping-bag" style="width: 3rem; height: 3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100 py-3" style="background-color: var(--info-reports);">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col me-2">
                        <div class="text-xs fw-bold text-uppercase mb-1">Total Unit Stok (Semua Varian)</div>
                        <div class="h5 mb-0"><?php echo number_format($total_unit_stock); ?></div>
                    </div>
                    <div class="col-auto">
                        <i data-feather="box" style="width: 3rem; height: 3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100 py-3" style="background-color: var(--warning-reports);">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col me-2">
                        <div class="text-xs fw-bold text-uppercase mb-1">Total Produk Aktif (Jenis)</div>
                        <div class="h5 mb-0"><?php echo number_format($total_products); ?></div>
                    </div>
                    <div class="col-auto">
                        <i data-feather="package" style="width: 3rem; height: 3rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>

---

<div class="row mt-4">
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header card-header-styled">
                <h5 class="m-0"><i data-feather="clipboard" class="me-2"></i> Ringkasan Pesanan Bulan Ini</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-reports align-middle mb-0">
                    <thead class="table-reports">
                        <tr>
                            <th>Status</th>
                            <th class="text-center">Jumlah Pesanan</th>
                            <th class="text-end">Total Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($summary_month): ?>
                            <?php foreach ($summary_month as $row): ?>
                                <?php 
                                    $row_class = '';
                                    if (strtolower($row['status']) == 'selesai') {
                                        $row_class = 'row-success-bg';
                                    } elseif (strtolower($row['status']) == 'dibatalkan') {
                                        $row_class = 'row-danger-bg';
                                    }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo htmlspecialchars(ucwords($row['status'])); ?></td>
                                    <td class="text-center"><?php echo number_format($row['total_count']); ?></td>
                                    <td class="text-end"><?php echo format_rupiah($row['total_amount_sum']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="row-secondary-bg">
                                <td>PENDAPATAN SUKSES BULAN INI</td>
                                <td class="text-center">-</td>
                                <td class="text-end text-success fw-bold"><?php echo format_rupiah($revenue_month); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center py-3">Tidak ada data pesanan bulan ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header card-header-styled">
                <h5 class="m-0"><i data-feather="user-plus" class="me-2"></i> 10 Pelanggan Terbaru</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-reports align-middle mb-0">
                    <thead class="table-reports">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 35%;">Nama</th>
                            <th style="width: 35%;">Email</th>
                            <th style="width: 25%;">Tanggal Daftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_users && $result_users->num_rows > 0) {
                            while($user = $result_users->fetch_assoc()):
                        ?>
                        <tr>
                            <td class="small text-muted"><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php 
                            endwhile;
                        } else {
                            echo '<tr><td colspan="4" class="text-center py-3">Tidak ada data pelanggan terbaru.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

---

<div class="row mt-4">
    <div class="col-12">
        <div class="card mb-4 shadow-sm">
            <div class="card-header card-header-styled d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i data-feather="package" class="me-2"></i> Laporan Detail Stok Produk</h5>
                
                <div class="d-flex gap-2 no-print"> 
                    <a href="reports/export.php?format=word_stock&category_id=<?php echo htmlspecialchars($filter_category_id); ?>&min_stock=<?php echo htmlspecialchars($filter_min_stock); ?>&max_price=<?php echo htmlspecialchars($filter_max_price); ?>&product_name=<?php echo htmlspecialchars($filter_product_name); ?>" class="btn btn-light text-danger btn-sm">
                        <i data-feather="file-text" class="me-1" style="width:1rem; height:1rem;"></i> Unduh DOCX
                    </a>
                    <a href="reports/export.php?format=csv_stock&category_id=<?php echo htmlspecialchars($filter_category_id); ?>&min_stock=<?php echo htmlspecialchars($filter_min_stock); ?>&max_price=<?php echo htmlspecialchars($filter_max_price); ?>&product_name=<?php echo htmlspecialchars($filter_product_name); ?>" class="btn btn-success btn-sm">
                        <i data-feather="download" class="me-1" style="width:1rem; height:1rem;"></i> Unduh CSV
                    </a>
                </div>
            </div>
            <div class="card-body">
                
                <form method="GET" class="mb-4 no-print filter-form-row">
                    <input type="hidden" name="page" value="reports/main"> 
                    
                    <div class="row g-3 align-items-end">
                        
                        <div class="col-lg-3 col-md-6">
                            <label for="product_name" class="form-label small text-muted">Nama Produk</label>
                            <input type="text" name="product_name" id="product_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_product_name); ?>" placeholder="Cari nama produk...">
                        </div>
                        
                        <div class="col-lg-3 col-md-6">
                            <label for="category_id" class="form-label small text-muted">Filter Kategori</label>
                            <select name="category_id" id="category_id" class="form-select form-select-sm">
                                <option value="">-- Semua Kategori --</option>
                                <?php
                                    foreach($categories as $cat){
                                        $selected = ((string)$filter_category_id === (string)$cat['id']) ? 'selected' : '';
                                        echo '<option value="'.$cat['id'].'" '.$selected.'>'.htmlspecialchars($cat['name']).'</option>';
                                    }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-lg-2 col-md-4">
                            <label for="min_stock" class="form-label small text-muted">Stok Min.</label>
                            <input type="number" name="min_stock" id="min_stock" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_min_stock); ?>" placeholder="Min">
                        </div>

                        <div class="col-lg-2 col-md-4">
                            <label for="max_price" class="form-label small text-muted">Harga Maks.</label>
                            <input type="number" name="max_price" id="max_price" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_max_price); ?>" placeholder="Maks">
                        </div>

                        <div class="col-lg-2 col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                            <a href="?page=reports/main" class="btn btn-outline-secondary btn-sm" title="Reset Filter">Reset</a>
                        </div>
                    </div>
                </form>

                <hr class="no-print"> 
                
                <p class="text-muted no-print small">Menampilkan status stok saat ini (<?php echo $result_stock_report ? $result_stock_report->num_rows : 0; ?> produk).</p>
                
                <table class="table table-bordered table-reports table-stock align-middle">
                    <thead class="table-reports">
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 35%;">Nama Produk</th>
                            <th style="width: 20%;">Kategori</th>
                            <th style="width: 15%;">Harga Satuan</th>
                            <th style="width: 25%;" class="text-center">Total Stok Tersedia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_stock_report && $result_stock_report->num_rows > 0) {
                            $result_stock_report->data_seek(0);
                            while($product = $result_stock_report->fetch_assoc()):
                                $row_class = '';
                                $stock_badge = '';
                                if ($product['stock'] < 10) {
                                    $row_class = 'table-danger fw-bold';
                                    $stock_badge = '<span class="badge bg-danger ms-2">Stok Rendah</span>';
                                } elseif ($product['stock'] < 20) {
                                    $row_class = 'table-warning';
                                }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td class="small text-muted"><?php echo htmlspecialchars($product['id']); ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                            <td><?php echo format_rupiah($product['price']); ?></td>
                            <td class="text-center">
                                <?php echo htmlspecialchars($product['stock']); ?>
                                <?php echo $stock_badge; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        } else {
                            echo '<tr><td colspan="5" class="text-center py-3">Tidak ada data produk yang ditemukan dengan filter ini.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/feather-icons"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
</script>