# Secure Document Signature

## 1. Deskripsi Aplikasi

Secure Document Signature adalah aplikasi web untuk finalisasi, penandatanganan digital, dan verifikasi dokumen PDF. Aplikasi ini dibuat sebagai proyek UTS Keamanan Informasi dan memisahkan proses pengelolaan dokumen, pembuatan signing key, penandatanganan berurutan, serta verifikasi publik.

PDF difinalisasi lebih dahulu dengan halaman pengesahan dan QR code. Setelah hash dibuat, byte PDF final tidak diubah lagi. Setiap signer kemudian membuat tanda tangan digital independen atas digest PDF final yang sama.

## 2. Fitur Utama

- Autentikasi dengan role `ADMIN` dan `SIGNER`.
- Upload dan finalisasi PDF dengan batas maksimum 10 MiB.
- Halaman pengesahan berisi metadata dokumen, daftar signer, QR code, dan URL verifikasi.
- Signing key RSA terpisah untuk setiap signer.
- Sequential multi-signer berdasarkan `sign_order`.
- Multiple independent digital signatures atas digest dokumen yang sama.
- Deteksi perubahan PDF melalui SHA-256.
- Verifikasi publik melalui token atau QR code tanpa login.
- Verifikasi dengan upload PDF final.
- Advanced Verification menggunakan custom RSA public key untuk satu signer target.
- Verification log untuk administrator.
- Benchmark performa signing dan verification.

## 3. Arsitektur Kriptografi

Konfigurasi kriptografi utama:

| Komponen | Implementasi |
| --- | --- |
| Document hash | SHA-256 |
| Signed data | Digest SHA-256 biner 32 byte |
| Asymmetric key | RSA 2048 bit |
| Signature | RSA-PSS dengan SHA-256 |
| MGF | MGF1-SHA-256 |
| PSS salt length | 32 byte |
| Private-key KDF | Argon2id |
| Private-key encryption | AES-256-GCM |
| Verification token | 32 random bytes, Base64URL tanpa padding, 43 karakter |

Setiap signer memiliki pasangan key sendiri. Private key dienkripsi menggunakan key yang diturunkan dari signing passphrase dan hanya didekripsi sementara saat signing. Signing passphrase berbeda dari password login dan tidak disimpan aplikasi.

Pada dokumen multi-signer, seluruh signer menandatangani digest PDF final yang sama secara independen. `sign_order` hanya mengatur urutan workflow aplikasi dan tidak membentuk cryptographic signature chain.

Detail teknis yang mengikat implementasi tersedia di [`docs/CONTRACT.md`](docs/CONTRACT.md).

## 4. Teknologi yang Digunakan

- PHP 8.3 dan Laravel 13
- React 19
- Inertia.js 3
- Tailwind CSS 4
- Vite 8
- PostgreSQL
- phpseclib 3 untuk RSA-PSS
- OpenSSL untuk AES-256-GCM
- Sodium untuk Argon2id dan penghapusan material sensitif dari memory
- FPDI dan FPDF untuk pemrosesan PDF
- Endroid QR Code untuk QR verification URL
- PHPUnit melalui Laravel test runner

## 5. Prasyarat

Pastikan perangkat memiliki:

- PHP `^8.3`.
- Composer.
- Node.js `^20.19.0` atau `>=22.12.0`, sesuai kebutuhan Vite 8.
- npm.
- PostgreSQL server.
- PHP extensions `pdo_pgsql`, `openssl`, `sodium`, `mbstring`, dan `fileinfo`.
- Ghostscript untuk finalisasi sumber PDF versi 1.5 atau lebih baru.

Implementasi mencari executable Ghostscript pada lokasi instalasi standar Windows atau melalui `PATH` dengan nama `gswin64c`, `gswin32c`, atau `gs`. Fungsi PHP `exec()` harus tersedia agar konversi PDF tersebut dapat dijalankan. PDF yang sudah kompatibel dengan versi 1.4 tidak memerlukan proses down-conversion.

## 6. Instalasi

Clone repository dan masuk ke direktori aplikasi:

```powershell
git clone https://github.com/mziccoalfarozi-source/UTS_KI_.git
cd secure-document-signature
```

Salin konfigurasi environment. Untuk PowerShell:

```powershell
Copy-Item .env.example .env
```

Untuk Linux atau macOS:

```bash
cp .env.example .env
```

Install dependency backend dan frontend, lalu buat application key:

```powershell
composer install
npm ci
php artisan key:generate
```

Jangan gunakan `composer run setup` sebagai jalur setup utama proyek ini karena konfigurasi PostgreSQL perlu dipastikan sebelum migration dijalankan.

## 7. Konfigurasi Environment

Periksa nilai berikut pada `.env`:

```dotenv
APP_NAME="Secure Document Signature"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
VERIFY_BASE_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=secure_document_signature
DB_USERNAME=postgres
DB_PASSWORD=

FILESYSTEM_DISK=local
```

`php artisan key:generate` akan mengisi `APP_KEY`. Sesuaikan username dan password PostgreSQL dengan konfigurasi lokal. `VERIFY_BASE_URL` harus merupakan base URL yang dapat dibuka oleh pengguna QR code dan harus sesuai dengan alamat aplikasi yang dijalankan.

## 8. Konfigurasi PostgreSQL

Siapkan dua database terpisah:

```text
secure_document_signature
secure_document_signature_test
```

Database pertama digunakan aplikasi development. Database kedua digunakan automated test. `phpunit.xml` menetapkan `secure_document_signature_test` sebagai database test dan menggunakan konfigurasi host, port, username, serta password PostgreSQL dari environment.

Jangan menyimpan data penting pada database test karena test dapat mereset isi database tersebut.

## 9. Migration dan Seeder

Setelah database development tersedia dan `.env` telah dikonfigurasi, jalankan:

```powershell
php artisan migrate --seed
```

Migration membuat schema aplikasi. Seeder membuat akun demo secara idempotent hanya untuk environment non-production. Seeder tidak membuat signing key, dokumen, atau assignment signer.

Untuk menjalankan migration tanpa akun demo:

```powershell
php artisan migrate
```

## 10. Menjalankan Aplikasi

Jalankan backend pada terminal pertama:

```powershell
php artisan serve
```

Jalankan Vite development server pada terminal kedua:

```powershell
npm run dev
```

Aplikasi dapat dibuka melalui `http://127.0.0.1:8000` jika menggunakan konfigurasi contoh.

Sebagai alternatif, buat production frontend assets terlebih dahulu lalu jalankan backend:

```powershell
npm run build
php artisan serve
```

`php artisan storage:link` tidak diperlukan. Dokumen dan laporan benchmark disimpan pada storage privat dan dokumen diunduh melalui controller yang menerapkan authorization.

## 11. Akun Demo

Akun berikut dibuat oleh `DatabaseSeeder` untuk local, development, dan demonstrasi:

| Nama | Email | Password | Role |
| --- | --- | --- | --- |
| Demo Administrator | `admin@example.test` | `AdminDemo123!` | `ADMIN` |
| Demo Signer | `signer@example.test` | `SignerDemo123!` | `SIGNER` |
| Demo Signer 2 | `signer2@example.test` | `SignerDemo123!` | `SIGNER` |
| Demo Signer 3 | `signer3@example.test` | `SignerDemo123!` | `SIGNER` |

Seeder tidak membuat akun demo ketika `APP_ENV=production`. Jangan gunakan kredensial demo ini untuk deployment production.

Setiap signer tetap harus login dan membuat signing key sendiri melalui aplikasi. Hal ini memungkinkan alur key generation dan signing passphrase didemonstrasikan secara nyata.

## 12. Contoh Penggunaan

1. Login sebagai setiap Demo Signer dan buat signing key dengan signing passphrase masing-masing.
2. Login sebagai Demo Administrator.
3. Buat dokumen, upload PDF, lalu pilih tiga signer dengan `sign_order` 1, 2, dan 3.
4. Download atau lihat detail PDF final yang sudah memiliki halaman pengesahan dan QR code.
5. Login sebagai signer urutan pertama dan tandatangani dokumen.
6. Login sebagai signer berikutnya secara berurutan. Signer tidak dapat melompati signer sebelumnya.
7. Setelah seluruh signer selesai, status dokumen menjadi `COMPLETED`.
8. Buka QR atau URL verifikasi untuk menjalankan public verification.
9. Upload PDF final untuk memeriksa integritas dan seluruh signature secara kriptografis.
10. Gunakan Advanced Verification bila perlu menguji signature satu signer terhadap custom public key.

## 13. Role dan Hak Akses

### Admin

- Membuat dan memfinalisasi dokumen.
- Memilih signer serta menentukan jabatan dan urutannya.
- Melihat status dokumen dan signer.
- Mengunduh PDF final.
- Melihat verification log.
- Tidak memiliki akses ke private key atau signing passphrase signer.

### Signer

- Membuat satu signing key untuk akunnya.
- Melihat dokumen yang ditugaskan kepadanya.
- Menandatangani dokumen ketika urutannya tersedia.
- Melihat status signing dan mengunduh PDF final.

### Public Verifier

- Tidak memerlukan login.
- Memverifikasi dokumen melalui token atau QR code.
- Mengupload PDF untuk pemeriksaan integritas dan signature.
- Menjalankan Advanced Verification menggunakan custom public key.

## 14. Sequential Multi-Signer Workflow

Contoh dokumen dengan tiga signer:

```text
WAITING_SIGNATURE
  -> Signer urutan 1 SIGNED
PARTIALLY_SIGNED
  -> Signer urutan 2 SIGNED
PARTIALLY_SIGNED
  -> Signer urutan 3 SIGNED
COMPLETED
```

Signer urutan berikutnya ditolak apabila signer sebelumnya belum `SIGNED`. Setelah signing, PDF final tidak dimodifikasi; signature disimpan sebagai data aplikasi. Status `COMPLETED` hanya berarti workflow signing selesai dan tidak otomatis berarti dokumen `VALID`. Status `VALID` hanya diperoleh dari verifikasi kriptografi aktual.

## 15. Automated Testing

Pastikan database PostgreSQL `secure_document_signature_test` tersedia, kosong dari data penting, dan dapat diakses menggunakan kredensial PostgreSQL pada environment. Kemudian jalankan:

```powershell
php artisan test --compact
```

Test mencakup authentication dan authorization, key protection, finalisasi PDF, immutable document hash, digital signing, urutan signer, public verification, verification log, serta benchmark command.

## 16. Signature Performance Benchmark

Jalankan benchmark dengan jumlah default 30 signing dan 30 verification:

```powershell
php artisan benchmark:signature
```

Jumlah iterasi dapat diubah, misalnya menjadi 50:

```powershell
php artisan benchmark:signature --runs=50
```

Command mengukur operasi `SignatureService` production dan menampilkan mean, median, minimum, maksimum, sample standard deviation, ukuran signature, dan ukuran public key. RSA key generation, database query, file PDF, dan penulisan CSV tidak masuk ke waktu operasi kriptografi yang dilaporkan.

Raw sample dan summary CSV disimpan di:

```text
storage/app/private/benchmarks/
```

Hasil benchmark bergantung pada hardware, sistem operasi, versi runtime, dan beban perangkat. Hasil lokal bukan jaminan performa universal.

## 17. Storage dan File Lokal

- PDF final disimpan di `storage/app/private/documents/`.
- CSV benchmark disimpan di `storage/app/private/benchmarks/`.
- Output PDF technical spike disimpan di `storage/app/private/spikes/pdf/` jika command spike digunakan tanpa custom output.
- Isi storage privat diabaikan Git, kecuali file `.gitignore` penjaga direktori.
- Direktori `storage/` dan `bootstrap/cache/` harus dapat ditulis oleh proses PHP.

Dokumen final tidak disajikan langsung sebagai public static file. Download dilakukan melalui route aplikasi dengan pemeriksaan role dan assignment.

## 18. Catatan Keamanan

- Jangan commit `.env`, `APP_KEY`, password database, atau kredensial deployment.
- Jangan commit private key plaintext maupun encrypted private-key material dari database.
- Jangan membagikan atau mencatat signing passphrase.
- Signing passphrase berbeda dari password login dan tidak disimpan aplikasi.
- Gunakan `APP_DEBUG=true` hanya pada local development; gunakan `false` pada production.
- Gunakan database test terpisah yang tidak menyimpan data penting.
- Dokumen dan laporan benchmark berada pada storage privat.
- Akun demo dan passwordnya bukan untuk production.
- Gunakan instalasi Ghostscript yang mutakhir dan proses hanya PDF dari sumber yang dipercaya. Down-conversion PDF 1.5+ saat ini menggunakan opsi `-dNOSAFER`; pertimbangkan batas trust dan hardening tambahan sebelum deployment production.
- Jangan mengubah PDF final setelah hash dibuat karena perubahan satu byte akan menyebabkan verifikasi gagal.

## 19. Anggota Kelompok

| Nama | NPM |
| --- | --- |
| Galang Maulid Nugraha | 247006111109 |
| Muhammad Ziya Pasya Alghifari | 247006111111 |
| Muhammad Zicco Alfarozi | 247006111113 |
