<?php
/**
 * admin/reports/export.php
 * Script untuk mengekspor laporan ke Word (HTML-Word) atau CSV menggunakan PHP Native.
 * 🔥 PENYESUAIAN: Menambahkan CSS yang lebih detail untuk merapikan tampilan export Word.
 */

// Pastikan sesi dan autentikasi berjalan
session_start();
require_once '../../admin_auth.php'; 
require_once '../../koneksi.php'; 

global $koneksi; 

$format = $_GET['format'] ?? null;
$filename_base = "Laporan_Stok_" . date('Ymd_His');
$sql_query_template = null;
$report_title = "Laporan Stok Produk";
$data_laporan = []; // Inisialisasi array data laporan

// =======================================================
// 1. AMBIL DAN PROSES INPUT FILTER DARI URL & SIAPKAN PREPARED STATEMENT
// =======================================================
$filter_category_id = $_GET['category_id'] ?? '';
$filter_min_stock = $_GET['min_stock'] ?? '';
$filter_max_price = $_GET['max_price'] ?? '';
$filter_product_name = $_GET['product_name'] ?? '';

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
    $having_clauses[] = "Total_Stok >= " . (int)$filter_min_stock;
}

// Gabungkan klausa
$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";
$having_sql = count($having_clauses) > 0 ? " HAVING " . implode(" AND ", $having_clauses) : "";


// =======================================================
// 2. QUERY DATA DENGAN FILTER
// =======================================================

if ($format == 'word_stock' || $format == 'csv_stock') {
    $sql_query_template = "
        SELECT 
            p.id, 
            p.name AS Nama_Produk, 
            c.name AS Kategori, 
            p.price AS Harga, 
            COALESCE(SUM(pv.stock), 0) AS Total_Stok
        FROM 
            products p
        LEFT JOIN 
            categories c ON p.category_id = c.id
        LEFT JOIN
            product_variants pv ON p.id = pv.product_id
        " . $where_sql . "
        GROUP BY
            p.id, p.name, c.name, p.price 
        " . $having_sql . "
        ORDER BY 
            Total_Stok DESC";
} else {
    if (isset($koneksi)) $koneksi->close();
    $_SESSION['message'] = '<div class="alert alert-warning">Format ekspor tidak valid.</div>';
    header("Location: ../dashboard.php?page=reports/main");
    exit;
}

// Eksekusi Prepared Statement
$stmt = $koneksi->prepare($sql_query_template);
if ($stmt) {
    if (!empty($bind_types)) {
        $tmp_params = array_merge([$bind_types], $bind_params);
        $refs = [];
        foreach ($tmp_params as $key => $value) {
            $refs[$key] = &$tmp_params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data_laporan[] = $row;
        }
    }
    $stmt->close();
}
$koneksi->close();

if (empty($data_laporan)) {
    $_SESSION['message'] = '<div class="alert alert-warning">Gagal membuat laporan: Tidak ada data yang ditemukan berdasarkan filter.</div>';
    header("Location: ../dashboard.php?page=reports/main");
    exit;
}

// =======================================================
// 3. PROSES EKSPOR BERDASARKAN FORMAT
// =======================================================

// A. EKSPOR WORD (HTML-Word)
if ($format == 'word_stock') {
    
    $filename = $filename_base . ".doc"; 

    header("Content-type: application/vnd.ms-word");
    header("Content-Disposition: attachment; filename={$filename}");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html>';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    echo '<title>' . $report_title . '</title>';
    
    // 🔥 CSS UNTUK MERAPIKAN TAMPILAN DI WORD
    echo '<style>
        body { font-family: Arial, sans-serif; font-size: 11pt; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
        }
        table, th, td { 
            border: 1px solid #999; 
        }
        th {
            background-color: #4CAF50; 
            color: white;
            padding: 10px;
            text-align: center;
        }
        td {
            padding: 8px;
            text-align: left;
        }
        .text-center { text-align: center !important; }
        .bg-low-stock { background-color: #F8D7DA; font-weight: bold; } /* Merah Muda */
        .bg-medium-stock { background-color: #FFF3CD; } /* Kuning Muda */
    </style>';
    // 🔥 AKHIR CSS UNTUK MERAPIKAN TAMPILAN DI WORD
    
    echo '</head>';
    echo '<body>';
    echo '<h2>' . $report_title . '</h2>';
    echo '<p>Tanggal Laporan: ' . date('d F Y H:i:s') . '</p>';
    
    if (!empty($where_sql) || !empty($having_sql)) {
        echo '<p style="font-size:10pt;">**Laporan ini difilter**</p>';
    }

    echo '<table>';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>Nama Produk</th>';
    echo '<th>Kategori</th>';
    echo '<th class="text-center">Harga</th>';
    echo '<th class="text-center">Total Stok</th>'; 
    echo '</tr></thead>';
    echo '<tbody>';
    
    foreach ($data_laporan as $row) {
        $row_class = '';
        if ($row['Total_Stok'] < 10) {
            $row_class = ' class="bg-low-stock"'; 
        } elseif ($row['Total_Stok'] < 20) {
            $row_class = ' class="bg-medium-stock"';
        }
        
        echo '<tr' . $row_class . '>';
        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['Nama_Produk']) . '</td>';
        echo '<td>' . htmlspecialchars($row['Kategori']) . '</td>';
        echo '<td class="text-center">Rp ' . number_format($row['Harga'], 0, ',', '.') . '</td>';
        echo '<td class="text-center">' . htmlspecialchars($row['Total_Stok']) . '</td>'; 
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</body></html>';
    
    exit;

}

// B. EKSPOR CSV
elseif ($format == 'csv_stock') {
    
    $filename = $filename_base . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');

    if (!empty($data_laporan)) {
        $headers = array_keys($data_laporan[0]);
        fputcsv($output, $headers);

        foreach ($data_laporan as $row) {
            // Format harga agar sesuai untuk CSV (angka polos tanpa Rp atau koma)
            $row['Harga'] = number_format($row['Harga'], 0, '.', ''); 
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit;
}

// Fallback jika format tidak dikenali
$_SESSION['message'] = '<div class="alert alert-warning">Format ekspor tidak valid.</div>';
header("Location: ../dashboard.php?page=reports/main");
exit;
?>