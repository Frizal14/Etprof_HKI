<?php
/**
 * admin/dashboard/main.php
 * Halaman utama dashboard admin: menampilkan ringkasan statistik dan KPI.
 * File ini di-include oleh admin/dashboard.php.
 */

// Pastikan koneksi tersedia (dari admin/dashboard.php)
if (!isset($GLOBALS['koneksi'])) {
    die("Koneksi database tidak tersedia.");
}

$koneksi = $GLOBALS['koneksi'];

// ----------------------------------------------------
// 1. PENGAMBILAN DATA STATISTIK UTAMA
// ----------------------------------------------------

$stats = [
    'total_products' => 0,
    'new_orders' => 0,
    'total_revenue' => 0,
    'pending_orders' => 0,
];

// A. Total Produk
$result_products = $koneksi->query("SELECT COUNT(id) AS total FROM products");
$stats['total_products'] = $result_products ? ($result_products->fetch_assoc()['total'] ?? 0) : 0;

// B. Pesanan Baru (Asumsi status 'Baru' atau 'pending')
$result_new_orders = $koneksi->query("SELECT COUNT(id) AS total FROM orders WHERE status = 'Baru'");
$stats['new_orders'] = $result_new_orders ? ($result_new_orders->fetch_assoc()['total'] ?? 0) : 0;

// C. Total Pendapatan (Pesanan dengan status 'selesai')
$result_revenue = $koneksi->query("SELECT SUM(total_amount) AS total FROM orders WHERE status = 'selesai'");
$stats['total_revenue'] = $result_revenue ? ($result_revenue->fetch_assoc()['total'] ?? 0) : 0;

// D. Pesanan Pending (Asumsi status 'diproses' atau 'payment_sent' atau 'pending')
$result_pending = $koneksi->query("SELECT COUNT(id) AS total FROM orders WHERE status IN ('diproses', 'payment_sent', 'pending', 'processing', 'Baru')");
$stats['pending_orders'] = $result_pending ? ($result_pending->fetch_assoc()['total'] ?? 0) : 0;

// ----------------------------------------------------
// 2. DATA UNTUK GRAFIK (Pendapatan Bulanan Sederhana)
// ----------------------------------------------------
$monthly_revenue = [];
// Mengambil data 6 bulan terakhir
$sql_monthly = "
    SELECT 
        DATE_FORMAT(order_date, '%Y-%m') AS month_year, 
        SUM(total_amount) AS total 
    FROM orders 
    WHERE status = 'selesai' 
    GROUP BY month_year 
    ORDER BY month_year DESC 
    LIMIT 6
";
$result_monthly = $koneksi->query($sql_monthly);
if ($result_monthly) {
    while ($row = $result_monthly->fetch_assoc()) {
        $monthly_revenue[$row['month_year']] = (float)$row['total'];
    }
}
$monthly_revenue = array_reverse($monthly_revenue); // Urutkan dari bulan terlama ke terbaru
$chart_labels = array_keys($monthly_revenue);
$chart_data = array_values($monthly_revenue);

// Perbaikan strftime(): Menggunakan date() untuk format yang aman
$formatted_labels = array_map(function($date) {
    // Menggunakan date() untuk format bulan/tahun (eg: Oct 2025)
    return date('M Y', strtotime($date . '-01'));
}, $chart_labels);

// ----------------------------------------------------
// 3. DATA BARU: PESANAN TERBARU (QUICK ACTION)
// ----------------------------------------------------
$latest_orders = [];
// 🔥 PERBAIKAN: Memasukkan status umum lainnya yang memerlukan aksi cepat admin
$sql_latest_orders = "
    SELECT 
        o.id, o.id AS order_code, o.total_amount, o.status, o.order_date,
        u.name AS user_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.status IN ('Baru', 'payment_sent', 'pending', 'processing')
    ORDER BY o.order_date DESC 
    LIMIT 5
";
$result_latest_orders = $koneksi->query($sql_latest_orders);
if ($result_latest_orders) {
    while ($row = $result_latest_orders->fetch_assoc()) {
        $latest_orders[] = $row;
    }
}

// ----------------------------------------------------
// 4. DATA BARU: PRODUK TERLARIS (TOP 5)
// ----------------------------------------------------
$top_products = [];
$sql_top_products = "
    SELECT 
        p.name AS product_name, 
        SUM(oi.quantity) AS total_sold,
        SUM(oi.price_at_order * oi.quantity) AS total_revenue_product
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC 
    LIMIT 5
";
$result_top_products = $koneksi->query($sql_top_products);
if ($result_top_products) {
    while ($row = $result_top_products->fetch_assoc()) {
        $top_products[] = $row;
    }
}

// Helper function untuk menampilkan badge status
function get_status_badge($status) {
    // Menormalkan status untuk badge yang konsisten
    $normalized_status = strtolower($status);
    
    switch ($normalized_status) {
        case 'baru': 
        case 'pending': 
            return '<span class="badge bg-danger">Baru/Bayar</span>';
        case 'payment_sent': 
            return '<span class="badge bg-warning text-dark">Verifikasi Bayar</span>';
        case 'diproses': 
        case 'processing': 
            return '<span class="badge bg-info">Diproses</span>';
        case 'selesai': 
            return '<span class="badge bg-success">Selesai</span>';
        default: 
            return '<span class="badge bg-secondary">' . htmlspecialchars(ucwords($status)) . '</span>';
    }
}

?>
<style>
/* 🔥 PERBAIKAN STYLING KESELURUHAN & PALET WARNA BARU */
:root {
    --primary-modern: #2C3E50; /* Dark Blue/Charcoal - Primary Focus */
    --info-modern: #3498DB;    /* Bright Blue - Info */
    --warning-modern: #F39C12; /* Orange/Yellow - Warning */
    --success-modern: #27AE60; /* Emerald Green - Success */
    --danger-modern: #E74C3C;  /* Red - Danger */
    /* Warna latar belakang sangat ringan untuk Card */
    --light-bg-info: #EBF5FB; 
    --light-bg-warning: #FEF9E7;
    --light-bg-success: #E8F8F5;
    --light-bg-primary: #EAEDED; 
}

.text-primary { color: var(--primary-modern) !important; }

/* 1. Card Statistik Modern */
.stat-card {
    transition: all 0.3s ease-in-out;
    border-radius: 12px; 
    border: 1px solid #f0f0f0; 
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); 
    overflow: hidden; /* Penting untuk efek border-left */
}

.stat-card:hover {
    transform: translateY(-5px); 
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.15); 
}

/* Penyesuaian Warna Background dan Border Kiri */
.stat-card.info-bg { background-color: var(--light-bg-info); border-left: 5px solid var(--info-modern) !important; }
.stat-card.warning-bg { background-color: var(--light-bg-warning); border-left: 5px solid var(--warning-modern) !important; }
.stat-card.success-bg { background-color: var(--light-bg-success); border-left: 5px solid var(--success-modern) !important; }
.stat-card.primary-bg { background-color: var(--light-bg-primary); border-left: 5px solid var(--primary-modern) !important; }

.stat-icon {
    opacity: 0.6;
    font-size: 3rem; 
}

.stat-value {
    font-size: 2.5rem; 
    font-weight: 800;
    color: var(--primary-modern);
}

.stat-label {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}
.stat-card .card-footer {
    background-color: transparent !important;
    border-top: none;
    padding-top: 0 !important;
}

/* 2. Card Grafik & Tabel */
.chart-card, .table-card {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
}

.chart-card .card-header, .table-card .card-header {
    background-color: var(--primary-modern); /* Warna header tabel/chart diubah ke primary */
    color: #ffffff;
    border-bottom: 1px solid #e9ecef;
    font-size: 1.1rem;
    font-weight: 700;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}
.chart-card .card-header i, .table-card .card-header i {
    color: #ffffff !important; /* Pastikan ikon tetap putih di header primary */
}

/* 🔥 3. Perbaikan Styling Tabel */
.table-card .table {
    border-collapse: separate;
    border-spacing: 0 5px; /* Memberikan sedikit ruang antar baris */
}
.table-card .table thead {
    /* Menggunakan Light Gray untuk header di dalam card primary */
    background-color: #f8f9fa;
    color: var(--primary-modern);
}

.table-card .table thead th {
    border-bottom: 2px solid #e9ecef;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.85rem;
    padding: 1rem 0.75rem;
}

.table-card .table tbody tr {
    border-radius: 8px; /* Sudut membulat pada baris */
    margin-bottom: 8px;
    background-color: #ffffff;
    transition: background-color 0.2s;
}

.table-card .table tbody tr:hover {
    background-color: #f6f6f6;
}

.table-card .table tbody td {
    vertical-align: middle;
    padding: 0.75rem;
}

.table-card .card-footer a {
    color: var(--primary-modern) !important;
    font-weight: 600;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <h1 class="display-6 fw-bold text-primary"><i class="fas fa-tachometer-alt me-2"></i> Dashboard Utama</h1>
    <p class="text-muted mb-0 d-none d-md-block">Ringkasan cepat performa toko Online Anda.</p>
</div>

<div class="row g-4 mb-5">
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card h-100 info-bg">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label mb-1" style="color: var(--info-modern)">TOTAL PRODUK</div>
                        <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
                    </div>
                    <i class="fas fa-boxes stat-icon" style="color: var(--info-modern);"></i>
                </div>
            </div>
            <div class="card-footer">
                <a href="dashboard.php?page=products/index" class="small text-decoration-none" style="color: var(--info-modern)">
                    Lihat Detail Produk <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card h-100 warning-bg">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label mb-1" style="color: var(--warning-modern)">PESANAN BARU</div>
                        <div class="stat-value"><?php echo number_format($stats['new_orders']); ?></div>
                    </div>
                    <i class="fas fa-bell stat-icon" style="color: var(--warning-modern);"></i>
                </div>
            </div>
            <div class="card-footer">
                <a href="dashboard.php?page=orders/index&status=Baru" class="small text-decoration-none" style="color: var(--warning-modern)">
                    Proses Sekarang <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card h-100 success-bg">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label mb-1" style="color: var(--success-modern)">TOTAL PENDAPATAN</div>
                        <div class="stat-value" style="font-size: 1.8rem; color: var(--success-modern);">Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></div>
                    </div>
                    <i class="fas fa-money-bill-wave stat-icon" style="color: var(--success-modern);"></i>
                </div>
            </div>
            <div class="card-footer">
                <span class="small text-muted">Berdasarkan pesanan 'selesai'</span>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card stat-card h-100 primary-bg">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label mb-1" style="color: var(--primary-modern)">PESANAN DIPROSES/PENDING</div>
                        <div class="stat-value"><?php echo number_format($stats['pending_orders']); ?></div>
                    </div>
                    <i class="fas fa-hourglass-half stat-icon" style="color: var(--primary-modern);"></i>
                </div>
            </div>
            <div class="card-footer">
                <a href="dashboard.php?page=orders/index" class="small text-decoration-none" style="color: var(--primary-modern)">
                    Kelola Pesanan <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-xl-6">
        <div class="card chart-card h-100">
            <div class="card-header py-3">
                <i class="fas fa-chart-bar me-1"></i>
                Pendapatan Bulanan (Bar Chart)
            </div>
            <div class="card-body p-4">
                <canvas id="revenueBarChart" style="max-height: 350px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card chart-card h-100">
            <div class="card-header py-3">
                <i class="fas fa-chart-line me-1"></i>
                Tren Pendapatan (Line Chart)
            </div>
            <div class="card-body p-4">
                <canvas id="revenueLineChart" style="max-height: 350px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="card table-card h-100">
            <div class="card-header py-3">
                <i class="fas fa-receipt me-1"></i>
                5 Pesanan Terbaru (Perlu Aksi Cepat)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Pelanggan</th>
                                <th>Total</th>
                                <th colspan="2">Status</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($latest_orders)): ?>
                                <?php foreach ($latest_orders as $order): ?>
                                    <tr>
                                        <td><span class="fw-bold text-primary"><?php echo htmlspecialchars($order['id']); ?></span></td> 
                                        
                                        <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                                        
                                        <td>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                        
                                        <td colspan="2">
                                            <?php echo get_status_badge($order['status']); ?>
                                            
                                            <a href="dashboard.php?page=orders/detail&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-info ms-2" title="Lihat Detail Admin">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Tidak ada pesanan baru yang perlu diproses.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light border-0">
                 <a href="dashboard.php?page=orders/index" class="small text-muted text-decoration-none">
                    Lihat Semua Pesanan <i class="fas fa-angle-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-xl-6">
        <div class="card table-card h-100">
            <div class="card-header py-3">
                <i class="fas fa-medal me-1"></i>
                Top 5 Produk Terlaris
            </div>
            <div class="card-body p-0">
                 <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Produk</th>
                                <th class="text-center">Terjual</th>
                                <th class="text-end">Pendapatan Produk</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($top_products)): $i = 1; ?>
                                <?php foreach ($top_products as $product): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td class="text-center fw-bold text-info"><?php echo number_format($product['total_sold']); ?></td>
                                        <td class="text-end text-success fw-bold">Rp <?php echo number_format($product['total_revenue_product'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Belum ada data penjualan produk.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light border-0">
                 <a href="dashboard.php?page=products/index" class="small text-muted text-decoration-none">
                    Lihat Semua Produk <i class="fas fa-angle-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Warna CSS yang didefinisikan di PHP/Style Block
    const PRIMARY_COLOR = getComputedStyle(document.documentElement).getPropertyValue('--primary-modern').trim() || '#2C3E50';
    const INFO_COLOR = getComputedStyle(document.documentElement).getPropertyValue('--info-modern').trim() || '#3498DB';
    const SUCCESS_COLOR = getComputedStyle(document.documentElement).getPropertyValue('--success-modern').trim() || '#27AE60';

    // Data PHP yang di-encode ke JavaScript
    const chartLabels = <?php echo json_encode($formatted_labels); ?>;
    const chartData = <?php echo json_encode($chart_data); ?>;

    // Cek data
    if (chartData.length === 0) {
        document.querySelectorAll('.card-body canvas').forEach(canvas => {
            canvas.style.display = 'none';
            canvas.parentElement.innerHTML = '<div class="alert alert-info text-center mt-3">Belum ada data pendapatan selesai untuk ditampilkan.</div>';
        });
        return;
    }
    
    // Helper untuk format Rupiah
    const rupiahFormatter = function(value) {
        if (value === null) return '';
        // Menggunakan Intl.NumberFormat untuk format Rupiah yang tepat
        return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value);
    };

    // Opsi dasar untuk kedua grafik
    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
                },
                ticks: {
                    callback: rupiahFormatter,
                    padding: 10
                }
            },
            x: {
                grid: {
                    display: false,
                    drawBorder: false
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                titleFont: { weight: 'bold' },
                bodyFont: { weight: 'normal' },
                padding: 10,
                callbacks: {
                    label: function(context) {
                        return ' Pendapatan: ' + rupiahFormatter(context.parsed.y);
                    },
                    title: function(context) {
                        return context[0].label;
                    }
                }
            }
        }
    };


    // ------------------------------------
    // 1. BAR CHART (Menggunakan warna info modern)
    // ------------------------------------
    const barCtx = document.getElementById('revenueBarChart');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Pendapatan Bulanan',
                data: chartData,
                backgroundColor: INFO_COLOR + 'd0', 
                borderColor: INFO_COLOR,
                borderRadius: 6, /* Bar lebih membulat */
                borderWidth: 0
            }]
        },
        options: baseOptions
    });


    // ------------------------------------
    // 2. LINE CHART (Menggunakan warna success modern)
    // ------------------------------------
    const lineCtx = document.getElementById('revenueLineChart');
    new Chart(lineCtx, {
        type: 'line', 
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Tren Pendapatan',
                data: chartData,
                backgroundColor: SUCCESS_COLOR + '33', 
                borderColor: SUCCESS_COLOR,      
                borderWidth: 3,
                tension: 0.4, 
                fill: 'origin', 
                pointRadius: 5, /* Titik lebih besar */
                pointBackgroundColor: '#ffffff', 
                pointBorderColor: SUCCESS_COLOR,
                pointBorderWidth: 3,
                pointHoverRadius: 8
            }]
        },
        options: baseOptions
    });

});
</script>