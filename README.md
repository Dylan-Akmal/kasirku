# Kasirku

Aplikasi kasir (Point of Sale) untuk usaha kecil seperti warung, toko kelontong, dan kedai kopi. Terdiri dari REST API (Laravel), aplikasi web untuk admin dan kasir (Vue 3), dan aplikasi mobile untuk kasir (Flutter, dalam pengembangan).

## Fitur

**Kasir**
- Halaman kasir dengan pencarian produk, filter kategori, dan scan barcode (pemindai USB mengetik SKU lalu Enter)
- Keranjang dengan batas jumlah sesuai stok, diskon nominal atau persen, dan pembayaran tunai, QRIS, atau transfer
- Hitung kembalian otomatis dan struk yang bisa dicetak
- Riwayat transaksi milik sendiri beserta cetak ulang struk

**Admin**
- Semua fitur kasir, ditambah manajemen produk, kategori, dan foto produk
- Stok masuk, penyesuaian stok (stock opname), dan riwayat pergerakan stok
- Pembatalan (void) transaksi dengan alasan, dan stok otomatis dikembalikan
- Laporan penjualan: ringkasan, grafik omzet harian, per metode bayar, per kasir, produk terlaris, dan ekspor CSV
- Manajemen akun kasir (tambah, ubah, reset kata sandi, aktifkan atau nonaktifkan) dan pengaturan profil toko

## Teknologi

| Bagian | Teknologi |
|---|---|
| Backend | Laravel 12, Laravel Sanctum (token Bearer), MySQL |
| Web | Vue 3, Vite, Tailwind CSS, Pinia, Vue Router, Axios |
| Mobile | Flutter (dalam pengembangan) |

## Tangkapan layar

| Login | Dashboard |
|---|---|
| ![Login](docs/screenshots/01-login.png) | ![Dashboard](docs/screenshots/02-dashboard.png) |

| Kasir (POS) | Struk |
|---|---|
| ![Kasir](docs/screenshots/03-pos.png) | ![Struk](docs/screenshots/04-receipt.png) |

| Produk | Riwayat Transaksi |
|---|---|
| ![Produk](docs/screenshots/05-products.png) | ![Riwayat](docs/screenshots/06-history.png) |

| Riwayat Stok | Laporan |
|---|---|
| ![Stok](docs/screenshots/07-stock.png) | ![Laporan](docs/screenshots/08-reports.png) |

## Struktur repositori

```
kasirku/
├── backend/   REST API Laravel
├── web/       Aplikasi web Vue 3
└── docs/      SRS, ERD, dan tangkapan layar
```

## Menjalankan secara lokal

### Prasyarat

PHP 8.2 atau lebih baru, Composer, MySQL (misalnya lewat XAMPP), dan Node.js 20 atau lebih baru.

### Backend

Buat database kosong bernama `kasirku` di MySQL, lalu jalankan:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Ubah pengaturan database di `backend/.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kasirku
DB_USERNAME=root
DB_PASSWORD=
```

Lanjutkan dengan migrasi, data awal, tautan penyimpanan foto, dan server:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

API berjalan di `http://127.0.0.1:8000/api`.

### Web

```bash
cd web
npm install
npm run dev
```

Aplikasi web berjalan di `http://localhost:5173`. Alamat API dapat diubah lewat variabel `VITE_API_URL` di `web/.env` (bawaan: `http://127.0.0.1:8000/api`).

### Akun uji

Dibuat oleh seeder, khusus untuk pengembangan. Ganti kata sandinya sebelum dipakai di lingkungan sebenarnya.

| Peran | Email | Kata sandi |
|---|---|---|
| Admin | admin@kasirku.test | password |
| Kasir | kasir@kasirku.test | password |

## Ringkasan API

Semua rute berawalan `/api` dan memakai header `Authorization: Bearer <token>` kecuali login.

| Metode | Endpoint | Akses | Keterangan |
|---|---|---|---|
| POST | `/login` | Publik | Login dan mendapatkan token |
| POST | `/logout`, GET `/me`, PUT `/password` | Login | Sesi dan kata sandi sendiri |
| GET | `/store-settings` | Login | Profil toko |
| PUT | `/store-settings` | Admin | Ubah profil toko |
| GET | `/categories` | Login | Daftar kategori |
| POST, PUT, DELETE | `/categories` | Admin | Kelola kategori |
| GET | `/products`, `/products/{id}` | Login | Cari, filter, dan detail produk |
| POST, PUT, DELETE | `/products`, `/products/{id}/image` | Admin | Kelola produk dan foto |
| POST | `/products/{id}/stock-in`, `/products/{id}/stock-adjust` | Admin | Stok masuk dan penyesuaian |
| GET | `/stock-movements` | Admin | Riwayat pergerakan stok |
| GET, POST | `/transactions` | Login | Riwayat dan checkout |
| GET | `/transactions/{id}`, `/transactions/{id}/receipt` | Login | Detail dan struk |
| POST | `/transactions/{id}/void` | Admin | Batalkan transaksi |
| GET | `/reports/sales`, `/reports/top-products`, `/reports/export` | Admin | Laporan dan ekspor CSV |
| GET, POST, PUT | `/users` | Admin | Kelola akun |

## Aturan bisnis penting

- Checkout berjalan dalam satu transaksi basis data: pembuatan transaksi, pengurangan stok, dan pencatatan pergerakan stok berhasil atau gagal bersama-sama, dengan penguncian baris produk agar stok tidak minus.
- Harga beli dan harga jual disimpan sebagai snapshot di setiap item transaksi, sehingga perubahan harga tidak memengaruhi data lama.
- Transaksi yang sudah selesai tidak dapat diubah atau dihapus, hanya dibatalkan oleh admin.
- Produk yang dihapus memakai soft delete agar riwayat transaksi tetap utuh.
- Kasir hanya melihat transaksi miliknya dan tidak melihat harga beli.
- Nomor invoice berformat `INV-YYYYMMDD-NNNN` dan urutannya kembali ke 0001 setiap hari.

## Dokumentasi

- [Software Requirements Specification](docs/SRS.md)
- [Entity Relationship Diagram](docs/ERD.md)

## Rencana pengembangan

- Aplikasi mobile untuk kasir dengan Flutter
- Pengujian otomatis (feature test API)
- Cetak struk ke printer thermal Bluetooth
