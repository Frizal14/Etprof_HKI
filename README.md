🛒 TokoOnlineku — Aplikasi Web E-Commerce Sederhana



TokoOnlineku adalah aplikasi web E-Commerce yang dirancang untuk memfasilitasi transaksi jual beli produk secara daring dengan sistem manajemen toko yang terpadu. Proyek ini menyediakan fitur lengkap baik untuk Pengguna (Users) maupun Administrator (Admin), dengan fokus pada kemudahan penggunaan, keamanan, dan efisiensi operasional.



🚀 Fitur Utama



⭐ Sisi Pengguna (Client-Side)



Katalog Produk Publik

Pengguna dapat melihat daftar produk, memfilter berdasarkan kategori, serta mengakses halaman detail produk (termasuk Quick View untuk varian dan deskripsi), tanpa perlu login.



Manajemen Akun

Sistem Registrasi \& Login yang aman, termasuk fitur edit data akun dan foto profil.



Proses Transaksi



Manajemen Keranjang Belanja



Checkout terpandu (pilihan kurir \& input alamat)



3 Metode Pembayaran: Bayar di Tempat (COD), E-Wallet, M-Banking



Pelacakan Pesanan

Pelacakan status pesanan: Pending $\\rightarrow$ Payment Sent $\\rightarrow$ Diproses $\\rightarrow$ Dikirim $\\rightarrow$ Selesai. Dilengkapi fitur Upload Bukti Bayar dan Pembatalan Pesanan (selama status masih Pending).



⭐ Sisi Administrator (Admin Panel)



Dashboard Utama

Menampilkan ringkasan performa toko seperti total produk, jumlah pesanan pending, dan total pendapatan menggunakan Bar Chart dan Line Chart.



Manajemen Toko



Manajemen Produk: Tambah, edit, hapus produk, varian, dan stok.



Manajemen Kategori Produk: Kelola daftar kategori.



Manajemen Pesanan: Verifikasi pembayaran \& update status pengiriman.



Laporan \& Analitik

Menyediakan Ringkasan Penjualan, Laporan Detail Stok Produk, dan fitur filter data.



Manajemen Pengguna

Mengelola akun Administrator dan Pengguna (Users), termasuk pembaruan kredensial.



🛠 Instalasi Proyek



🔧 Prasyarat



Pastikan Anda memiliki perangkat lunak berikut terinstal di komputer Anda:



Web Server Lokal (Contoh: XAMPP, Laragon, WAMP)



PHP (Versi yang disarankan: PHP 8.2)



MySQL/MariaDB



📥 Langkah-langkah Instalasi



Clone Repositori atau Download ZIP:


```text
git clone https://github.com/Frizal14/Etprof_HKI.git
```





Letakkan folder proyek di direktori web server lokal (misalnya, htdocs pada XAMPP) dan ganti namanya menjadi: e-commerce\_sederhana.



Konfigurasi Database:



Buat database baru dengan nama: tokoonlineku\_db.



Import file SQL dari link berikut:

https://drive.google.com/drive/folders/1SvlY3xVnfTz-ZqkKosWexl8uytm8003K?usp=sharing



Konfigurasi Koneksi Aplikasi:



Edit file koneksi di proyek Anda (misalnya: koneksi.php) dan sesuaikan kredensialnya:



DB\_HOST = "localhost"

DB\_USER = "root"

DB\_PASS = "" // Kosongkan jika tidak ada password

DB\_NAME = "e-commerce\_native\_db" // Pastikan sesuai dengan nama database Anda





PERHATIAN: Pastikan nama database yang Anda buat di langkah 2 sesuai dengan yang ada di file konfigurasi.



Jalankan Aplikasi:



Pastikan layanan Apache/Nginx dan MySQL sudah aktif.



Akses melalui browser: http://localhost/e-commerce\_sederhana



📘 Panduan Akses



Halaman



URL Akses



Halaman Utama (Pengguna)



http://localhost/e-commerce\_sederhana/toko\_sepatru.php



Login Admin



http://localhost/e-commerce\_sederhana/admin/login.php



🔑 Akun Default (Untuk Pengujian)



Peran



Username



Password



User



cobacoba12



12345678



Admin



admin\_toko



12345678



📄 Lisensi



Proyek ini dilindungi oleh Hak Cipta. Penggunaan, modifikasi, atau distribusi harus sesuai dengan ketentuan yang telah ditetapkan.



✨ Kontribusi



Pull request dan saran pengembangan sangat terbuka dan diterima.

