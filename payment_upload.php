<?php
/**
 * payment_upload.php
 * Form dan Logika untuk mengunggah bukti pembayaran.
 * 🚀 PERBAIKAN STYLING MODERN & RESPONSIVITAS MOBILE 🚀
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Pastikan file koneksi ada
if (!file_exists('koneksi.php')) {
    die("Error: File koneksi.php tidak ditemukan.");
}
require_once 'koneksi.php'; // Koneksi Database
global $koneksi;

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login_user.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;
$error = [];
$message = '';
$order_data = null;

// Konfigurasi Path
$target_dir_php = 'uploads/payments/'; 
// Pastikan folder ini ada dan memiliki izin tulis (chmod 777)
if (!is_dir($target_dir_php)) {
    // Suppress error if already exists, try to create recursively
    @mkdir($target_dir_php, 0777, true); 
}


// 1. Ambil Detail Pesanan
if (is_numeric($order_id) && $order_id > 0) {
    $order_id = (int) $order_id; // Sanitize input
    $stmt = $koneksi->prepare("SELECT id, status, total_amount FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error[] = "Pesanan tidak ditemukan atau bukan milik Anda.";
    } else {
        $order_data = $result->fetch_assoc();
        if (strtolower($order_data['status']) !== 'pending' && strtolower($order_data['status']) !== 'payment_sent') { // Izinkan upload ulang jika payment_sent
            $error[] = "Bukti pembayaran tidak dapat diunggah untuk pesanan berstatus: " . htmlspecialchars($order_data['status']);
        }
    }
    $stmt->close();
} else {
    $error[] = "ID Pesanan tidak valid.";
}


// 2. Proses Upload (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
        
        $image_file = $_FILES['proof_image'];
        $image_name = basename($image_file["name"]);
        $image_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        // Nama file unik: proof_[ID]_[WAKTU].ext
        $unique_filename = 'proof_' . $order_id . '_' . time() . '.' . $image_extension;
        $target_file = $target_dir_php . $unique_filename; 
        
        $allowed_types = ['jpg', 'jpeg', 'png'];
        if (!in_array($image_extension, $allowed_types)) {
            $error[] = "Hanya file JPG, JPEG, dan PNG yang diizinkan.";
        }
        if ($image_file["size"] > 2000000) { // Maks 2MB
            $error[] = "Ukuran file terlalu besar. Maksimal 2MB.";
        }
        
        if (empty($error)) {
            if (move_uploaded_file($image_file["tmp_name"], $target_file)) {
                
                // 3. Update Status Pesanan di Database
                $new_status = 'payment_sent'; // Status baru: Bukti Pembayaran Terkirim
                
                $stmt_update = $koneksi->prepare("UPDATE orders SET payment_proof_path = ?, status = ? WHERE id = ?");
                
                if ($stmt_update) {
                    // Hanya simpan nama file, bukan full path
                    $stmt_update->bind_param("ssi", $unique_filename, $new_status, $order_id); 
                    
                    if ($stmt_update->execute()) {
                        // SUKSES
                        $stmt_update->close();
                        
                        // Set Toast Message
                        $_SESSION['toast_message'] = "✅ Bukti pembayaran berhasil diunggah. Pesanan Anda akan segera kami verifikasi.";
                        $_SESSION['toast_type'] = 'success';
                        header('Location: detail_checkout.php?order_id=' . $order_id);
                        exit;
                    } else {
                        $error[] = "Gagal menyimpan data ke database: " . $stmt_update->error; 
                        $stmt_update->close();
                    }
                } else {
                    $error[] = "Gagal menyiapkan query update: " . $koneksi->error;
                }

            } else {
                $error[] = "❌ Gagal mengunggah file. Cek izin folder 'uploads/payments/' (chmod 777)."; 
            }
        }

    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Cek jika ada error upload selain UPLOAD_ERR_OK
        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
             $error[] = "Gagal upload file (Error Code: " . $_FILES['proof_image']['error'] . "). Cek konfigurasi PHP Anda.";
        } else {
             $error[] = "Pilih file bukti pembayaran.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Upload Bukti Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Modern Styling Base */
        :root {
            --primary-color: #007bff; /* Bright Blue */
            --success-color: #28a745; 
            --light-bg: #f8f9fa;
        }

        body {
            background-color: var(--light-bg);
        }

        /* Container responsif penuh */
        .upload-container { 
            max-width: 600px; 
            margin-top: 20px; /* Lebih responsif di atas */
            padding-left: 15px;
            padding-right: 15px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        .btn-success:hover {
             background-color: #1e7e34;
             border-color: #1e7e34;
        }
        
        /* Styling Preview Gambar */
        .preview-img-wrapper {
            border: 2px dashed #007bff50;
            border-radius: 8px;
            padding: 10px;
            background-color: #f0f8ff;
            text-align: center;
        }
        .preview-img { 
            width: auto; /* Width auto untuk menyesuaikan tinggi */
            max-width: 100%;
            max-height: 200px; /* Batasi tinggi untuk mobile */
            object-fit: contain; 
            margin-bottom: 0;
            border-radius: 4px;
        }

        /* Penyesuaian Mobile */
        @media (max-width: 576px) {
            .upload-container {
                margin-top: 10px;
                padding-left: 5px;
                padding-right: 5px;
            }
            h1 {
                font-size: 1.8rem;
                margin-bottom: 1rem !important;
            }
            .preview-img {
                max-height: 150px; /* Lebih kecil di mobile */
            }
        }
    </style>
</head>
<body>

<div class="container upload-container">
    <h1 class="mb-4 text-center text-primary">Kirim Bukti Pembayaran</h1>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <strong>Gagal Upload:</strong>
            <ul><?php foreach ($error as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul>
        </div>
        <a href="detail_checkout.php?order_id=<?php echo $order_id; ?>" class="btn btn-outline-secondary mt-3 w-100"><i class="fas fa-arrow-left me-1"></i> Kembali</a>

    <?php elseif ($order_data): 
        $formatted_order_id = str_pad($order_id, 6, '0', STR_PAD_LEFT);
        $formatted_amount = number_format($order_data['total_amount'], 0, ',', '.');
    ?>

        <div class="alert alert-info text-center fw-bold p-3">
            Upload bukti transfer untuk Pesanan <span class="text-danger">#ORD-<?php echo $formatted_order_id; ?></span><br>
            Total Bayar: <span class="text-success fs-5">Rp <?php echo $formatted_amount; ?></span>
        </div>

        <div class="card shadow-lg p-4 p-sm-5">
            <form action="payment_upload.php?order_id=<?php echo $order_id; ?>" method="POST" enctype="multipart/form-data">
                
                <div class="mb-4">
                    <label class="form-label fw-bold d-block text-center">Preview Bukti Pembayaran</label>
                    <div class="preview-img-wrapper">
                         <img id="imagePreview" src="https://via.placeholder.com/600x200?text=Bukti+Bayar+(Max+2MB)" class="preview-img" alt="Preview Bukti">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="proof_image" class="form-label fw-bold">Pilih File Bukti (JPG/PNG)</label>
                    <input class="form-control form-control-lg" type="file" id="proof_image" name="proof_image" accept="image/jpeg,image/png" required>
                    <div class="form-text">Ukuran maksimal file adalah 2MB.</div>
                </div>
                
                <button type="submit" class="btn btn-success btn-lg w-100 mb-3">
                    <i class="fas fa-upload me-2"></i> Kirim Bukti
                </button>
                <a href="detail_checkout.php?order_id=<?php echo $order_id; ?>" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-times me-1"></i> Batal
                </a>
            </form>
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Script untuk menampilkan preview gambar
    document.getElementById('proof_image')?.addEventListener('change', function(event) {
        var file = event.target.files[0];
        var preview = document.getElementById('imagePreview');
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        } else {
             // Placeholder jika file dibatalkan
             preview.src = "https://via.placeholder.com/600x200?text=Bukti+Bayar+(Max+2MB)";
        }
    });
</script>
</body>
</html>
<?php 
// Koneksi ditutup di sini (akhir file)
if (isset($koneksi) && $koneksi instanceof mysqli) {
    $koneksi->close(); 
}
?>