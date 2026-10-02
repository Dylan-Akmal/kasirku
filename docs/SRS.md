# Software Requirements Specification (SRS)

## Kasirku - Aplikasi Kasir (Point of Sale)

| Atribut | Keterangan |
|---|---|
| Versi dokumen | 1.0 |
| Tanggal | 2 Oktober 2026 |
| Penulis | Dylan Akmal Adi Mahendra |
| Status | Draft |

---

## 1. Pendahuluan

### 1.1 Tujuan
Dokumen ini mendeskripsikan kebutuhan perangkat lunak Kasirku, yaitu aplikasi kasir (Point of Sale) untuk usaha kecil seperti warung, toko kelontong, kedai kopi, dan toko sejenis. Dokumen ini menjadi acuan pengembangan backend, web, dan mobile, serta dasar penyusunan UAT.

### 1.2 Ruang Lingkup
Kasirku membantu pemilik usaha mencatat penjualan, mengelola produk dan stok, serta melihat laporan penjualan. Sistem terdiri dari tiga komponen: REST API (Laravel), aplikasi web (Vue) untuk admin dan kasir, dan aplikasi mobile (Flutter) untuk kasir.

Di luar ruang lingkup versi 1.0: multi-cabang, manajemen hutang/piutang pelanggan, integrasi payment gateway otomatis, integrasi printer thermal Bluetooth, dan pembukuan akuntansi lengkap.

### 1.3 Definisi dan Singkatan

| Istilah | Arti |
|---|---|
| POS | Point of Sale, sistem pencatatan transaksi penjualan |
| SRS | Software Requirements Specification |
| API | Application Programming Interface |
| SKU | Stock Keeping Unit, kode unik produk |
| Admin | Pengguna pemilik atau pengelola usaha |
| Kasir | Pengguna yang melayani transaksi penjualan |
| MVP | Minimum Viable Product |

### 1.4 Referensi
Dokumen ERD (docs/ERD.md) dan dokumen UAT (akan dibuat setelah fitur selesai).

---

## 2. Deskripsi Umum

### 2.1 Perspektif Produk
Kasirku adalah sistem mandiri berbasis klien-server. Aplikasi web dan mobile berkomunikasi dengan satu REST API yang terhubung ke basis data MySQL. Autentikasi memakai token (Laravel Sanctum).

### 2.2 Kelas Pengguna

| Pengguna | Deskripsi | Hak akses |
|---|---|---|
| Admin | Pemilik atau pengelola usaha | Seluruh fitur: produk, kategori, stok, pengguna, laporan, dan transaksi |
| Kasir | Karyawan yang melayani pembeli | Transaksi, riwayat transaksi miliknya, dan melihat daftar produk |

### 2.3 Lingkungan Operasi
Backend berjalan di PHP 8.2+ dengan MySQL. Web diakses lewat browser modern (Chrome, Edge, Firefox). Mobile berjalan di Android (Flutter).

### 2.4 Asumsi dan Ketergantungan
Pengguna memiliki koneksi internet saat bertransaksi. Satu usaha memakai satu basis data (single tenant). Harga disimpan dalam Rupiah tanpa pecahan desimal.

---

## 3. Kebutuhan Fungsional

### 3.1 Autentikasi dan Pengguna

| Kode | Kebutuhan | Prioritas |
|---|---|---|
| FR-01 | Pengguna dapat login dengan email dan kata sandi | Tinggi |
| FR-02 | Pengguna dapat logout dan token dicabut | Tinggi |
| FR-03 | Pengguna dapat melihat profil dan mengubah kata sandi sendiri | Sedang |
| FR-04 | Admin dapat menambah, mengubah, menonaktifkan akun kasir | Tinggi |
| FR-05 | Sistem membatasi akses fitur berdasarkan peran (admin atau kasir) | Tinggi |

### 3.2 Kategori dan Produk

| Kode | Kebutuhan | Prioritas |
|---|---|---|
| FR-06 | Admin dapat menambah, mengubah, dan menghapus kategori produk | Tinggi |
| FR-07 | Admin dapat menambah, mengubah, dan menghapus produk (nama, SKU/barcode, kategori, harga beli, harga jual, stok, foto opsional) | Tinggi |
| FR-08 | Pengguna dapat mencari produk berdasarkan nama atau SKU/barcode dan memfilter berdasarkan kategori | Tinggi |
| FR-09 | Produk yang dihapus tidak menghilangkan riwayat transaksi lama (soft delete) | Tinggi |
| FR-10 | Sistem menandai produk dengan stok menipis berdasarkan batas minimum yang ditentukan admin | Sedang |

### 3.3 Manajemen Stok

| Kode | Kebutuhan | Prioritas |
|---|---|---|
| FR-11 | Admin dapat mencatat stok masuk (restock) beserta jumlah dan catatan | Tinggi |
| FR-12 | Admin dapat melakukan penyesuaian stok (stock opname) dengan alasan | Sedang |
| FR-13 | Stok berkurang otomatis saat transaksi berhasil dan kembali bila transaksi dibatalkan | Tinggi |
| FR-14 | Sistem menyimpan riwayat pergerakan stok (masuk, keluar, penyesuaian, pembatalan) | Sedang |

### 3.4 Transaksi Penjualan

| Kode | Kebutuhan | Prioritas |
|---|---|---|
| FR-15 | Kasir dapat menambahkan produk ke keranjang lewat pencarian atau scan barcode | Tinggi |
| FR-16 | Kasir dapat mengubah jumlah dan menghapus item di keranjang | Tinggi |
| FR-17 | Kasir dapat memberi diskon (nominal atau persen) pada transaksi | Sedang |
| FR-18 | Sistem menghitung subtotal, diskon, dan total secara otomatis | Tinggi |
| FR-19 | Kasir dapat memilih metode pembayaran: tunai, QRIS (manual), atau transfer | Tinggi |
| FR-20 | Untuk pembayaran tunai, sistem menghitung kembalian dari uang yang diterima | Tinggi |
| FR-21 | Sistem menolak transaksi bila jumlah melebihi stok yang tersedia | Tinggi |
| FR-22 | Setiap transaksi memiliki nomor invoice unik | Tinggi |
| FR-23 | Harga produk pada transaksi disimpan sebagai snapshot sehingga perubahan harga berikutnya tidak memengaruhi data lama | Tinggi |
| FR-24 | Admin dapat membatalkan (void) transaksi dengan alasan, dan stok dikembalikan | Sedang |

### 3.5 Struk dan Riwayat

| Kode | Kebutuhan | Prioritas |
|---|---|---|
| FR-25 | Sistem menampilkan struk setelah transaksi berhasil (nama toko, invoice, waktu, kasir, item, total, bayar, kembalian) | Tinggi |
| FR-26 | Struk dapat dicetak dari browser atau dibagikan sebagai teks/PDF dari mobile | Sedang |
| FR-27 | Pengguna dapat melihat riwayat transaksi dengan filter tanggal dan pencarian invoice; kasir hanya melihat transaksi miliknya | Tinggi |
| FR-28 | Pengguna dapat membuka detail transaksi | Tinggi |

### 3.6 Laporan

| Kode | Kebutuhan | Prioritas |
|---|---|---|
| FR-29 | Admin dapat melihat ringkasan penjualan harian (jumlah transaksi, omzet, laba kotor) | Tinggi |
| FR-30 | Admin dapat melihat laporan penjualan per rentang tanggal | Tinggi |
| FR-31 | Admin dapat melihat daftar produk terlaris | Sedang |
| FR-32 | Admin dapat melihat penjualan per metode pembayaran dan per kasir | Sedang |
| FR-33 | Admin dapat mengekspor laporan penjualan ke CSV | Rendah |

### 3.7 Pengaturan Toko

| Kode | Kebutuhan | Prioritas |
|---|---|---|
| FR-34 | Admin dapat mengatur profil toko (nama, alamat, nomor telepon, pesan kaki struk) | Sedang |

---

## 4. Kebutuhan Non-Fungsional

| Kode | Kategori | Kebutuhan |
|---|---|---|
| NFR-01 | Keamanan | Kata sandi disimpan dalam bentuk hash; seluruh endpoint selain login memerlukan token yang valid |
| NFR-02 | Keamanan | Otorisasi berbasis peran diterapkan di sisi server, bukan hanya di antarmuka |
| NFR-03 | Kinerja | Pencarian produk dan penambahan item ke keranjang merespons kurang dari 1 detik pada kondisi normal |
| NFR-04 | Integritas data | Pembuatan transaksi, pengurangan stok, dan pencatatan pergerakan stok berjalan dalam satu transaksi basis data (atomic) |
| NFR-05 | Kegunaan | Alur transaksi dari memilih produk sampai struk dapat diselesaikan dalam maksimal 5 langkah |
| NFR-06 | Kompatibilitas | Web responsif untuk layar desktop dan tablet; mobile mendukung Android 8.0 ke atas |
| NFR-07 | Keterpeliharaan | Kode mengikuti konvensi Laravel, Vue, dan Flutter; dokumentasi API tersedia di README |
| NFR-08 | Auditabilitas | Setiap transaksi, void, dan perubahan stok mencatat pengguna dan waktu kejadian |

---

## 5. Aturan Bisnis

| Kode | Aturan |
|---|---|
| BR-01 | Hanya admin yang dapat mengubah harga, stok manual, dan membatalkan transaksi |
| BR-02 | Transaksi yang sudah selesai tidak dapat diubah, hanya dapat dibatalkan (void) |
| BR-03 | Total = jumlah (harga jual x kuantitas) dikurangi diskon; total tidak boleh negatif |
| BR-04 | Uang yang diterima pada pembayaran tunai tidak boleh kurang dari total |
| BR-05 | Akun kasir yang dinonaktifkan tidak dapat login, tetapi riwayat transaksinya tetap tersimpan |
| BR-06 | Nomor invoice berformat INV-YYYYMMDD-NNNN dengan urutan yang di-reset setiap hari |

---

## 6. Antarmuka Eksternal

### 6.1 Antarmuka Pengguna
Web memiliki halaman: login, dashboard, kasir (POS), produk, kategori, stok, riwayat transaksi, laporan, pengguna, dan pengaturan toko. Mobile memiliki halaman: login, kasir, keranjang, pembayaran, struk, dan riwayat transaksi.

### 6.2 Antarmuka Perangkat Lunak
Komunikasi antara klien dan server memakai REST API dengan format JSON dan autentikasi Bearer token.

### 6.3 Antarmuka Perangkat Keras
Kamera perangkat mobile dipakai untuk memindai barcode produk. Pembaca barcode USB (mode keyboard) didukung di web.

---

## 7. Teknologi

| Komponen | Teknologi |
|---|---|
| Backend | Laravel 12, Laravel Sanctum, MySQL |
| Web | Vue 3, Vite, Tailwind CSS, Pinia, Vue Router, Axios |
| Mobile | Flutter |
| Kontrol versi | Git dan GitHub |

---

## 8. Rencana Pengembangan

Tahap 1: dokumentasi (SRS, ERD). Tahap 2: backend (autentikasi, kategori, produk, stok, transaksi, laporan). Tahap 3: web (admin dan kasir). Tahap 4: mobile (kasir). Tahap 5: pengujian (UAT), perapian, dan README.
