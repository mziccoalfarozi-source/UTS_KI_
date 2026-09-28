# Technical Contract: Secure Document Signature

File ini adalah single source of truth teknis untuk seluruh pengerjaan coding oleh Galang, Ziya, Zicco, dan AI agent.

Setiap implementasi wajib mengikuti seluruh nama tabel, kolom, enum, route, format data, algoritma, parameter kriptografi, service boundary, dan aturan PDF di dalam kontrak ini. Jangan mengubah arsitektur atau membuat alternatif sendiri. Jika menemukan konflik teknis atau bagian kontrak yang tidak dapat diimplementasikan, berhenti dan jelaskan masalahnya terlebih dahulu sebelum menulis kode.

## 1. Tujuan Sistem

Aplikasi adalah sistem web untuk penandatanganan dan verifikasi dokumen PDF secara digital.

Sistem harus membuktikan:

- Integrity: dokumen tidak berubah setelah ditandatangani.
- Authenticity: tanda tangan dapat diverifikasi menggunakan public key signer.
- Multi-signer verification: satu dokumen dapat ditandatangani beberapa signer secara berurutan.

Stack final:

- Laravel
- React
- Inertia.js
- Tailwind CSS
- PostgreSQL
- RSA-2048
- RSA-PSS
- SHA-256
- Argon2id
- AES-256-GCM
- QR Code

Sistem tidak menggunakan:

- Blockchain
- PKI/CA
- TSA
- PAdES
- Post-quantum cryptography
- Cryptographic multisignature

Multiple signer yang digunakan adalah **Multiple Independent Digital Signatures**.

Setiap signer memiliki pasangan key sendiri dan menandatangani digest PDF final yang sama. Urutan signer dikendalikan aplikasi melalui `sign_order`, bukan dengan membuat rantai signature secara kriptografis.

## 2. Aktor Sistem

### Administrator

Administrator dapat:

- Login.
- Membuat dokumen.
- Upload PDF.
- Menentukan judul dokumen.
- Menentukan institusi.
- Menentukan tanggal dokumen.
- Memilih signer.
- Menentukan jabatan signer.
- Menentukan urutan signer.
- Melihat status dokumen.
- Download dokumen final.
- Melihat verification log.

Administrator tidak memiliki akses terhadap private key signer.

### Signer

Signer dapat:

- Login.
- Membuat pasangan RSA key.
- Membuat signing passphrase.
- Melihat dokumen yang harus ditandatangani.
- Melakukan digital signing sesuai urutan.
- Melihat status signing.
- Download dokumen final.

### Public Verifier

Verifier tidak perlu login.

Verifier dapat:

- Scan QR Code.
- Membuka URL verifikasi.
- Memasukkan token secara manual.
- Upload dokumen untuk pengecekan.
- Melihat hasil validasi seluruh signer.
- Secara opsional memasukkan public key lain melalui Advanced Verification.

## 3. Algoritma dan Parameter Kriptografi Final

Bagian ini dikunci dan tidak boleh diubah antaranggota atau AI agent.

| Komponen | Keputusan Final |
| --- | --- |
| Asymmetric algorithm | RSA |
| RSA key size | 2048 bit |
| Signature scheme | RSA-PSS |
| Hash | SHA-256 |
| PSS hash | SHA-256 |
| MGF | MGF1-SHA-256 |
| PSS salt length | 32 byte |
| Private-key KDF | Argon2id |
| Argon2id algorithm | `SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13` |
| Argon2id salt | 16 byte random |
| KDF output | 32 byte |
| Private-key encryption | AES-256-GCM |
| AES nonce | 12 byte random |
| GCM authentication tag | 16 byte |
| Random generator | `random_bytes()` |
| QR payload | Verification URL + random token |

Review terakhir secara spesifik mengunci PSS, Argon2id, dan AES-GCM agar implementasi antarmodul konsisten.

## 4. Key Generation dan Private Key Protection

Setiap signer membuat key sendiri.

Alur:

```text
Signer
|
+-- Generate RSA-2048
|   |
|   +-- Public Key
|   |   +-- disimpan sebagai PEM
|   |
|   +-- Private Key
|       |
|       v
|       Signing Passphrase
|       |
|       v
|       Argon2id
|       |
|       v
|       32-byte AES Key
|       |
|       v
|       AES-256-GCM
|       |
|       v
|       Encrypted Private Key
|       |
|       v
|       Database
```

Signing passphrase:

- Minimal 12 karakter.
- Berbeda konsep dari password login.
- Diketik dua kali saat pembuatan key.
- Tidak disimpan ke database.
- Tidak disimpan di session.
- Tidak ditulis ke log.

Private key plaintext:

- Hanya berada sementara di memory.
- Tidak disimpan sebagai file.
- Tidak masuk database.
- Tidak masuk source code.
- Tidak masuk Git.

Data key yang disimpan:

- `public_key`
- `encrypted_private_key`
- `salt`
- `nonce`
- `auth_tag`
- `kdf`
- `kdf_opslimit`
- `kdf_memlimit`
- `algorithm`

Argon2id harus menghasilkan 32 byte raw key dengan parameter:

- `algorithm = SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13`
- `salt = random_bytes(16)`
- `opslimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE`
- `memlimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE`

Nilai aktual dari konstanta yang digunakan saat key derivation harus disimpan pada `kdf_opslimit` dan `kdf_memlimit`. Proses dekripsi wajib menggunakan nilai yang tersimpan tersebut. Password hash Laravel tidak boleh digunakan sebagai AES key.

Representasi data kriptografi di database:

- `salt`, `nonce`, `auth_tag`, `encrypted_private_key`, dan `signature` disimpan sebagai Base64 string standar dengan padding.
- Seluruh nilai tersebut wajib di-Base64-decode menjadi raw binary sebelum operasi kriptografi.
- `salt` adalah 16 raw bytes sebelum Base64 encoding.
- `nonce` adalah 12 raw bytes sebelum Base64 encoding.
- `auth_tag` adalah 16 raw bytes sebelum Base64 encoding.
- `signature` RSA-2048 adalah 256 raw bytes sebelum Base64 encoding.
- `document_hash` bukan Base64 dan tetap lowercase hexadecimal sepanjang 64 karakter.

Key lifecycle untuk scope proyek:

- Key rotation tidak termasuk scope.
- `POST /keys` hanya boleh membuat key jika signer belum memiliki record `signing_keys`.
- Jika signer sudah memiliki signing key, request harus ditolak dan key lama tidak boleh di-overwrite.
- Tidak ada endpoint untuk delete, replace, atau rotate signing key.

## 5. Document Finalization

Ini adalah aturan inti sistem.

Urutan final:

```text
Admin Upload PDF
-> Isi Metadata
-> Pilih Signer
-> Tentukan sign_order
-> Generate Random Token
-> Generate Verification URL
-> Generate QR Code
-> Tambahkan Halaman Pengesahan
-> Simpan PDF FINAL
-> SHA-256(PDF FINAL)
-> document_hash
-> WAITING_SIGNATURE
```

Token dibuat dengan format:

```text
random_bytes(32)
-> Base64URL tanpa padding
-> 43 karakter
```

QR berisi:

```text
VERIFY_BASE_URL/verify/{token}
```

Base URL tidak boleh hard-coded ke `localhost`. Nilainya harus berasal dari environment aplikasi:

```text
VERIFY_BASE_URL
```

## 6. Halaman Pengesahan PDF

Halaman pengesahan ditambahkan sebelum hashing dan signing.

Isi halaman pengesahan:

```text
Judul Dokumen
Institusi:
Tanggal Dokumen:

Daftar Penandatangan:
1. Nama Signer
   Jabatan
2. Nama Signer
   Jabatan
3. Nama Signer
   Jabatan

[ QR CODE ]

Verifikasi:
https://.../verify/{token}
```

QR sendiri tetap hanya membawa URL + token. Metadata dicetak di samping QR agar dokumen tetap memberikan informasi signer tanpa membuat payload QR terlalu kompleks.

Dilarang dicetak pada PDF final:

- `SIGNED`
- `COMPLETED`
- `VALID`
- Tanggal signing
- Signature digital
- Status signer

Data tersebut baru muncul setelah PDF final dibuat.

## 7. Aturan Emas PDF

**PDF FINAL TIDAK BOLEH BERUBAH SETELAH HASH DIBUAT.**

Urutan yang benar:

```text
QR
-> Metadata
-> PDF FINAL
-> SHA-256
-> SIGN
```

Urutan yang salah:

```text
PDF
-> HASH
-> SIGN
-> tambahkan QR
```

Setelah `documents` dibuat:

- PDF tidak boleh ditulis ulang.
- QR tidak boleh diganti.
- Signature visual tidak boleh ditambahkan.
- Tanggal signing tidak boleh ditambahkan.
- Tidak ada endpoint replace PDF.
- Download harus mengirim byte file apa adanya.

Pada proses signing dan verification, file server selalu di-hash ulang.

## 8. SHA-256 dan Signed Data

PDF final dihitung:

```text
SHA-256(PDF FINAL)
```

Hasil hash adalah:

```text
32-byte digest
```

Database menyimpan representasi:

```text
document_hash = lowercase hexadecimal, 64 karakter
```

Contoh:

```text
a53e...9bc1
```

Tetapi input ke `sign()` dan `verify()` **bukan** string hex tersebut.

Input ke `sign()` dan `verify()` harus:

```text
32-byte binary digest
```

Contoh implementasi konseptual:

```php
hash_file('sha256', $file, true)
```

atau:

```php
hex2bin($documentHash)
```

Jangan menggunakan input berikut untuk signing atau verification:

- 64-character hex
- Base64 digest

Format ini harus sama di semua modul.

## 9. RSA-PSS Signing

Setiap signer menandatangani:

```text
digest_32_byte
```

Dengan parameter:

- RSA-2048
- RSA-PSS
- SHA-256
- MGF1-SHA-256
- `saltLength = 32`

Alur konseptual:

```text
PDF FINAL
-> SHA-256
-> Digest 32 byte
-> RSA-PSS Sign
-> Digital Signature
```

Karena RSA-PSS melakukan hashing internal terhadap input, implementasi ini berarti ada hashing internal tambahan. Ini disengaja dan harus konsisten antara sign dan verify.

## 10. Multiple Independent Digital Signatures

Semua signer menandatangani digest `H` yang sama:

```text
PDF FINAL
-> SHA-256
-> DIGEST H

H -> S1 -> Sig1
H -> S2 -> Sig2
H -> S3 -> Sig3
```

Bukan chain signature kriptografis:

```text
Signer 1 -> Signer 2 -> Signer 3
```

Urutan hanya merupakan workflow aplikasi.

Contoh workflow:

```text
sign_order 1 -> harus SIGNED
sign_order 2 -> boleh sign
sign_order 3 -> boleh sign
```

Jika signer 3 mencoba sign ketika signer 2 belum selesai:

```text
SIGNING REJECTED
```

Keputusan ini dipertahankan karena lebih sederhana dan sesuai scope proyek.

## 11. Signing Flow

Saat signer menekan Sign:

```text
Signer
-> Input Signing Passphrase
-> Cek sign_order
-> Hash ulang PDF server
-> Bandingkan dengan document_hash
-> Argon2id(passphrase)
-> AES-256-GCM decrypt private key
-> RSA-PSS Sign(digest)
-> Self Verify
-> Simpan Signature
-> Update Signer Status
-> Update Document Status
```

Jika passphrase salah atau ciphertext rusak, pesan error:

```text
Passphrase salah atau data kunci rusak
```

Tidak perlu memberi informasi lebih detail.

Setelah signature dibuat, aplikasi melakukan sanity verification menggunakan public key signer sebelum signature disimpan.

## 12. Status Dokumen

Hanya ada status dokumen berikut:

- `WAITING_SIGNATURE`
- `PARTIALLY_SIGNED`
- `COMPLETED`

Aturan status:

| Kondisi | Status |
| --- | --- |
| 0 signer selesai | `WAITING_SIGNATURE` |
| Sebagian signer selesai | `PARTIALLY_SIGNED` |
| Semua signer selesai | `COMPLETED` |

`COMPLETED != VALID`.

`COMPLETED` hanya berarti workflow signing selesai. `VALID` hanya dapat diperoleh setelah verifikasi kriptografi aktual.

## 13. Verification Flow

Endpoint utama:

```text
/verify/{token}
```

Input:

| Jenis | Data |
| --- | --- |
| Wajib | `token` |
| Opsional | PDF upload |
| Advanced | `document_signer_id` + `custom_public_key` PEM |

Flow:

```text
Token
-> Cari Document
-> Ambil PDF server / PDF upload
-> Hitung SHA-256
-> Bandingkan document_hash
-> Ambil signatures
-> Abaikan signer berstatus PENDING dari cryptographic verification
-> Untuk signer SIGNED, ambil official public key melalui document_signers.key_id
-> Jika Advanced Verification, ganti key hanya untuk signer target dengan custom public key
-> RSA-PSS Verify seluruh signer SIGNED
-> Hitung hasil akhir
```

Jika PDF upload diberikan, aplikasi harus melakukan hash terhadap raw bytes file tersebut. Jangan menolak file tampered hanya karena parser PDF menganggap file tersebut rusak.

Aturan Advanced Verification:

- Pada dokumen multi-signer, verifier wajib memilih tepat satu signer target.
- Input terdiri dari `document_signer_id` bertipe BIGINT dan `custom_public_key` bertipe string PEM.
- `document_signer_id` wajib menunjuk record `document_signers` milik dokumen yang ditemukan dari token dan record tersebut wajib berstatus `SIGNED`.
- Custom public key hanya digunakan untuk memverifikasi signature signer target.
- Signer lain tetap diverifikasi menggunakan official public key yang direferensikan oleh `document_signers.key_id`.
- Custom PEM yang tidak dapat diparse, bukan RSA public key yang valid, atau valid tetapi gagal memverifikasi signature signer target menghasilkan `INVALID_PUBLIC_KEY`.

## 14. Verification Truth Table

Urutan evaluasi:

| Kondisi | Hasil |
| --- | --- |
| Token tidak ditemukan / format salah | `REJECTED_TOKEN_NOT_FOUND` |
| Hash file != `document_hash` | `INVALID_DOCUMENT_MODIFIED` |
| Signature signer `SIGNED` gagal diverifikasi menggunakan official public key yang direferensikan oleh `document_signers.key_id` | `INVALID_SIGNATURE` |
| Custom PEM tidak dapat diparse, bukan RSA public key yang valid, atau gagal memverifikasi signature signer target | `INVALID_PUBLIC_KEY` |
| Seluruh signature signer `SIGNED` valid tetapi masih terdapat signer `PENDING` | `INCOMPLETE` |
| Semua signer selesai + hash cocok + semua signature valid | `VALID` |

Aturan penting:

- `documents.status == COMPLETED` tidak cukup untuk menghasilkan `VALID`.
- Verifikasi harus dihitung ulang setiap request.
- Jangan melakukan cryptographic verification terhadap signer berstatus `PENDING`. Hanya signer berstatus `SIGNED` yang diverifikasi.
- Setelah seluruh signature signer `SIGNED` valid, keberadaan satu atau lebih signer `PENDING` menghasilkan `INCOMPLETE`.
- `INVALID_PUBLIC_KEY` hanya digunakan pada Advanced Verification untuk kegagalan parse, tipe key yang bukan RSA public key valid, atau kegagalan verifikasi signature signer target menggunakan custom public key.
- Jika public key resmi signer dari database digunakan dan signature gagal diverifikasi, hasilnya harus `INVALID_SIGNATURE`.
- Canonical `VerificationCode` untuk hasil belum lengkap adalah `INCOMPLETE`.
- Hasil verification memuat `signed_count` dan `total_signers` sebagai field integer terpisah.
- UI boleh menampilkan `INCOMPLETE (n/m)`, tetapi nilai tersebut hanya presentation string dan bukan `VerificationCode`.
- `verification_logs.result_code` hanya menyimpan `INCOMPLETE`, bukan presentation string.

## 15. Database Final

Gunakan tepat 5 tabel:

- `users`
- `signing_keys`
- `documents`
- `document_signers`
- `verification_logs`

Skema final ini mengikuti simplifikasi review dari enam tabel menjadi lima agar tidak ada duplikasi signature antara dua tabel.

Konvensi database yang dikunci:

- Jangan menggunakan PostgreSQL native ENUM.
- Seluruh enum disimpan sebagai `VARCHAR`, dipetakan ke PHP string-backed enum, divalidasi pada application layer, dan diberi database `CHECK` constraint sesuai nilai enum final.
- UUID dibuat oleh aplikasi dan tidak memiliki database default.
- `BIGINT auto-increment` berarti primary key BIGINT yang nilainya dihasilkan database.
- Seluruh timestamp menggunakan `TIMESTAMP(0) WITHOUT TIME ZONE`.
- Semua kolom adalah `NOT NULL` kecuali secara eksplisit ditandai nullable.
- Foreign key diberi index non-unique kecuali sudah menjadi leading column pada unique index yang sesuai.
- Tanda `-` pada kolom Default / Constraint berarti kolom tidak memiliki database default atau constraint tambahan selain `NOT NULL`.
- Selain primary-key index, unique index, dan index yang dicantumkan secara eksplisit, migration tidak membuat index tambahan.

### 15.1 `users`

Kolom:

| Kolom | Tipe | Null | Default / Constraint |
| --- | --- | --- | --- |
| `id` | BIGINT auto-increment | Tidak | Primary key |
| `name` | VARCHAR(255) | Tidak | - |
| `email` | VARCHAR(255) | Tidak | `UNIQUE` |
| `password` | VARCHAR(255) | Tidak | - |
| `role` | VARCHAR(20) | Tidak | `CHECK (role IN ('ADMIN', 'SIGNER'))` |
| `position_title` | VARCHAR(255) | Ya | `NULL` |
| `institution` | VARCHAR(255) | Ya | `NULL` |
| `created_at` | TIMESTAMP(0) WITHOUT TIME ZONE | Tidak | `CURRENT_TIMESTAMP` |
| `updated_at` | TIMESTAMP(0) WITHOUT TIME ZONE | Tidak | `CURRENT_TIMESTAMP`; dipelihara aplikasi |

Nilai `role`:

- `ADMIN`
- `SIGNER`

Index:

- Unique index pada `email`.

### 15.2 `signing_keys`

Kolom:

| Kolom | Tipe | Null | Default / Constraint |
| --- | --- | --- | --- |
| `id` | UUID | Tidak | Primary key; dibuat aplikasi |
| `user_id` | BIGINT | Tidak | Foreign key ke `users.id` `ON DELETE RESTRICT`; `UNIQUE` |
| `algorithm` | VARCHAR(32) | Tidak | Default `RSA-2048-PSS-SHA256`; `CHECK` sesuai enum `Algorithm` |
| `public_key` | TEXT | Tidak | RSA public key PEM |
| `encrypted_private_key` | TEXT | Tidak | Base64 string |
| `salt` | VARCHAR(24) | Tidak | Base64 dari 16 raw bytes |
| `nonce` | VARCHAR(16) | Tidak | Base64 dari 12 raw bytes |
| `auth_tag` | VARCHAR(24) | Tidak | Base64 dari 16 raw bytes |
| `kdf` | VARCHAR(32) | Tidak | Default `ARGON2ID13`; `CHECK (kdf = 'ARGON2ID13')` |
| `kdf_opslimit` | BIGINT | Tidak | Nilai aktual opslimit yang digunakan; `CHECK (kdf_opslimit > 0)` |
| `kdf_memlimit` | BIGINT | Tidak | Nilai aktual memlimit yang digunakan; `CHECK (kdf_memlimit > 0)` |
| `created_at` | TIMESTAMP(0) WITHOUT TIME ZONE | Tidak | `CURRENT_TIMESTAMP` |

Index dan foreign key:

- `UNIQUE(user_id)` sekaligus menjadi index untuk foreign key `user_id`.
- `user_id -> users.id ON DELETE RESTRICT`.

Nilai `kdf_opslimit` dan `kdf_memlimit` menyimpan nilai aktual dari `SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE` dan `SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE` yang digunakan saat key derivation.

Satu signer memiliki satu signing key untuk scope proyek ini. Record tidak dapat di-overwrite, dihapus, diganti, atau dirotasi melalui aplikasi.

### 15.3 `documents`

Kolom:

| Kolom | Tipe | Null | Default / Constraint |
| --- | --- | --- | --- |
| `id` | UUID | Tidak | Primary key; dibuat aplikasi |
| `title` | VARCHAR(255) | Tidak | - |
| `institution` | VARCHAR(255) | Tidak | - |
| `document_date` | DATE | Tidak | - |
| `original_filename` | VARCHAR(255) | Tidak | - |
| `file_path` | TEXT | Tidak | - |
| `file_size` | BIGINT | Tidak | `CHECK (file_size >= 0)`; satuan byte |
| `document_hash` | CHAR(64) | Tidak | `CHECK (document_hash ~ '^[0-9a-f]{64}$')` |
| `verification_token` | CHAR(43) | Tidak | `UNIQUE`; `CHECK (verification_token ~ '^[A-Za-z0-9_-]{43}$')` |
| `status` | VARCHAR(32) | Tidak | Default `WAITING_SIGNATURE`; `CHECK` sesuai enum `DocumentStatus` |
| `created_by` | BIGINT | Tidak | Foreign key ke `users.id` `ON DELETE RESTRICT` |
| `created_at` | TIMESTAMP(0) WITHOUT TIME ZONE | Tidak | `CURRENT_TIMESTAMP` |

Index dan foreign key:

- Unique index pada `verification_token`.
- Non-unique index pada `created_by`.
- `created_by -> users.id ON DELETE RESTRICT`.
- `document_hash` tidak unique karena byte-identical PDF final boleh tersimpan sebagai dokumen berbeda.

### 15.4 `document_signers`

Kolom:

| Kolom | Tipe | Null | Default / Constraint |
| --- | --- | --- | --- |
| `id` | BIGINT auto-increment | Tidak | Primary key |
| `document_id` | UUID | Tidak | Foreign key ke `documents.id` `ON DELETE CASCADE` |
| `user_id` | BIGINT | Tidak | Foreign key ke `users.id` `ON DELETE RESTRICT` |
| `sign_order` | INTEGER | Tidak | `CHECK (sign_order > 0)` |
| `position_title` | VARCHAR(255) | Tidak | Snapshot jabatan untuk dokumen |
| `status` | VARCHAR(20) | Tidak | Default `PENDING`; `CHECK` sesuai enum `SignerStatus` |
| `key_id` | UUID | Ya | Default `NULL`; foreign key ke `signing_keys.id` `ON DELETE RESTRICT` |
| `signature` | VARCHAR(344) | Ya | Default `NULL`; Base64 dari signature RSA-2048 sepanjang 256 raw bytes |
| `signed_at` | TIMESTAMP(0) WITHOUT TIME ZONE | Ya | Default `NULL` |
| `created_at` | TIMESTAMP(0) WITHOUT TIME ZONE | Tidak | `CURRENT_TIMESTAMP` |

Constraint, index, dan foreign key:

- `UNIQUE(document_id, user_id)`.
- `UNIQUE(document_id, sign_order)`.
- Kedua unique constraint tersebut menyediakan index dengan leading column `document_id`.
- Non-unique index pada `user_id`.
- Non-unique index pada `key_id`.
- `document_id -> documents.id ON DELETE CASCADE`.
- `user_id -> users.id ON DELETE RESTRICT`.
- `key_id -> signing_keys.id ON DELETE RESTRICT`.
- `CHECK` state: jika `status = 'SIGNED'`, maka `signature`, `key_id`, dan `signed_at` semuanya `NOT NULL`; jika `status = 'PENDING'`, ketiganya harus `NULL`.

### 15.5 `verification_logs`

Kolom:

| Kolom | Tipe | Null | Default / Constraint |
| --- | --- | --- | --- |
| `id` | BIGINT auto-increment | Tidak | Primary key |
| `document_id` | UUID | Ya | Default `NULL`; foreign key ke `documents.id` `ON DELETE SET NULL` |
| `method` | VARCHAR(32) | Tidak | `CHECK` sesuai enum `VerificationMethod` |
| `result_code` | VARCHAR(40) | Tidak | `CHECK` sesuai enum `VerificationCode`; hanya canonical enum value |
| `token_prefix` | VARCHAR(8) | Tidak | Delapan karakter pertama token, atau seluruh input jika panjangnya kurang dari delapan karakter |
| `created_at` | TIMESTAMP(0) WITHOUT TIME ZONE | Tidak | `CURRENT_TIMESTAMP` |

Index dan foreign key:

- Non-unique index pada `document_id`.
- Non-unique index pada `created_at` untuk daftar log kronologis.
- `document_id -> documents.id ON DELETE SET NULL`.
- `result_code` menyimpan `INCOMPLETE`, bukan presentation string `INCOMPLETE (n/m)`.

Log tidak menyimpan:

- Token lengkap
- PDF
- Private key
- Passphrase

### 15.6 Eloquent Model Mapping untuk Foundation

Aturan model minimum:

- Model `User`, `DocumentSigner`, dan `VerificationLog` menggunakan primary key integer auto-increment.
- Model `SigningKey` dan `Document` menggunakan primary key UUID string, non-incrementing.
- Hanya model `User` yang menggunakan `created_at` dan `updated_at`.
- Model `SigningKey`, `Document`, `DocumentSigner`, dan `VerificationLog` menggunakan `created_at` tanpa `updated_at`; konfigurasi Eloquent wajib menonaktifkan hanya `updated_at`, bukan `created_at`.
- Field UUID dan foreign key UUID diperlakukan sebagai string.
- Field Base64 tetap berupa string pada model dan hanya di-decode di service kriptografi yang berwenang.

Cast minimum:

| Model | Field | Cast |
| --- | --- | --- |
| `User` | `role` | `Role` backed enum |
| `SigningKey` | `algorithm` | `Algorithm` backed enum |
| `SigningKey` | `kdf_opslimit`, `kdf_memlimit` | integer |
| `Document` | `document_date` | date |
| `Document` | `file_size` | integer |
| `Document` | `status` | `DocumentStatus` backed enum |
| `DocumentSigner` | `sign_order` | integer |
| `DocumentSigner` | `status` | `SignerStatus` backed enum |
| `DocumentSigner` | `signed_at` | datetime |
| `VerificationLog` | `method` | `VerificationMethod` backed enum |
| `VerificationLog` | `result_code` | `VerificationCode` backed enum |
| Seluruh model | Kolom timestamp yang dimiliki | datetime |

## 16. Enum Final

Nama-nama enum ini tidak boleh diganti anggota atau AI agent.

### Role

- `ADMIN`
- `SIGNER`

### DocumentStatus

- `WAITING_SIGNATURE`
- `PARTIALLY_SIGNED`
- `COMPLETED`

### SignerStatus

- `PENDING`
- `SIGNED`

### VerificationCode

- `VALID`
- `INCOMPLETE`
- `INVALID_DOCUMENT_MODIFIED`
- `INVALID_SIGNATURE`
- `INVALID_PUBLIC_KEY`
- `REJECTED_TOKEN_NOT_FOUND`

### VerificationMethod

- `TOKEN`
- `UPLOAD`
- `UPLOAD_CUSTOM_KEY`

### Algorithm

- `RSA-2048-PSS-SHA256`

## 17. Service Architecture

Crypto hanya boleh berada di service tertentu.

```text
App\Services\
|
+-- Crypto\
|   +-- KeyPairService
|   +-- PrivateKeyVault
|   +-- SignatureService
|
+-- Document\
|   +-- DocumentFinalizer
|   +-- SigningWorkflowService
|
+-- Verification\
    +-- VerificationService
```

Controller tidak boleh langsung melakukan operasi kriptografi.

### KeyPairService

Tanggung jawab:

- Generate RSA key pair.
- Export public key.
- Export private key.
- Reject pembuatan jika user sudah memiliki signing key; jangan overwrite record lama.

### PrivateKeyVault

Tanggung jawab:

- Argon2id.
- AES-256-GCM encrypt.
- AES-256-GCM decrypt.
- Base64 encode sebelum persistence dan Base64 decode sebelum operasi kriptografi untuk seluruh field biner yang dikunci kontrak.

### SignatureService

Tanggung jawab:

- `sign()`
- `verify()`

### DocumentFinalizer

Tanggung jawab:

- Generate token.
- Generate QR.
- Stamp PDF.
- Save final PDF.
- Calculate SHA-256.

### SigningWorkflowService

Tanggung jawab:

- Check signer.
- Check `sign_order`.
- Verify document hash.
- Decrypt private key.
- Call `SignatureService`.
- Update signing status.

### VerificationService

Tanggung jawab:

- Calculate document hash.
- Verify hanya signer berstatus `SIGNED`.
- Untuk Advanced Verification, gunakan `document_signer_id` dan custom public key hanya pada signer target.
- Apply verification truth table.
- Return canonical `VerificationCode`, integer `signed_count`, dan integer `total_signers` sebagai field terpisah.

## 18. Routes Final

Routes:

```text
POST /keys
GET  /keys

GET  /documents
POST /documents
GET  /documents/{document}
GET  /documents/{document}/download
POST /documents/{document}/sign

GET  /verify
GET  /verify/{token}
POST /verify/{token}

GET  /admin/logs
```

Public routes:

```text
GET  /verify
GET  /verify/{token}
POST /verify/{token}
```

Admin route:

```text
GET /admin/logs
```

## 19. Library

### RSA-PSS

Preferred:

- `phpseclib3`

### AES

- `ext-openssl`

### Argon2id

- `ext-sodium`

### QR

Preferred:

- `endroid/qr-code`

### PDF

Preferred:

- `setasign/fpdi`
- `setasign/fpdf`

Sebelum coding besar dilakukan, FPDI wajib dites karena kompatibilitas PDF merupakan risiko teknis paling besar. Review mencatat FPDI gratis dapat bermasalah dengan tipe PDF tertentu, sehingga spike harus dilakukan terhadap beberapa sumber PDF.

## 20. Testing Wajib

Minimal demo/test harus membuktikan:

| No | Skenario | Ekspektasi |
| --- | --- | --- |
| 1 | Dokumen asli | `VALID` |
| 2 | PDF diubah 1 byte | `INVALID_DOCUMENT_MODIFIED` |
| 3 | Wrong custom public key pada Advanced Verification | `INVALID_PUBLIC_KEY` |
| 4 | Token palsu | `REJECTED_TOKEN_NOT_FOUND` |
| 5 | QR asli digunakan pada PDF palsu | `INVALID_DOCUMENT_MODIFIED` |
| 6 | Wrong signing passphrase | Signing ditolak |
| 7 | Signer melompati urutan | Signing ditolak |
| 8 | Signature dimodifikasi | `INVALID_SIGNATURE` |
| 9 | Semua signer selesai | `COMPLETED` |
| 10 | Salah satu signature invalid saat menggunakan public key resmi signer dari database | `INVALID_SIGNATURE` |

Benchmark wajib:

- 30x signing
- 30x verification

Ukur:

- mean
- median
- minimum
- maximum
- standard deviation

Catat juga:

- signature size
- public key size

Fuzzing, penetration testing penuh, coverage 100%, dan browser E2E tidak diperlukan untuk scope ini.

## 21. Coding Contract Usage

Setiap kali Galang, Ziya, atau Zicco melakukan prompt AI untuk coding, lampirkan contract ini.

Prompt pembuka wajib:

```text
Gunakan docs/CONTRACT.md sebagai sumber kebenaran teknis proyek. Ikuti seluruh nama tabel, kolom, enum, route, format data, algoritma, parameter kriptografi, service boundary, dan aturan PDF di dalam contract. Jangan mengubah arsitektur atau membuat alternatif sendiri. Jika menemukan konflik teknis atau bagian contract yang tidak dapat diimplementasikan, berhenti dan jelaskan masalahnya terlebih dahulu sebelum menulis kode.
```

## 22. Aturan Implementasi

- Jangan mengubah stack final.
- Jangan mengubah algoritma dan parameter kriptografi.
- Jangan mengubah nama tabel, kolom, enum, route, format data, service boundary, atau aturan PDF.
- Jangan membuat endpoint replace PDF.
- Jangan menulis ulang PDF final setelah `document_hash` dibuat.
- Jangan menambahkan QR, visual signature, tanggal signing, status signer, `SIGNED`, `COMPLETED`, atau `VALID` ke PDF final setelah hashing.
- Jangan menggunakan password hash Laravel sebagai AES key.
- Jangan menyimpan signing passphrase dalam database, session, log, source code, file, atau Git.
- Jangan menyimpan private key plaintext dalam database, file, source code, atau Git.
- Jangan melakukan operasi kriptografi langsung di controller.
- Signing dan verification harus selalu hash ulang raw bytes PDF.
- Verification harus dihitung ulang setiap request.
- `COMPLETED` tidak boleh dianggap sama dengan `VALID`.
- Signature harus di-self-verify dengan public key signer sebelum disimpan.
- Jika technical spike membuktikan ada bagian yang benar-benar tidak jalan, hentikan implementasi dan jelaskan konflik teknis sebelum mengubah contract.

## 23. Final Scope Lock

Keputusan berikut dianggap final:

| Area | Keputusan Final |
| --- | --- |
| Stack | Laravel + React + Inertia + Tailwind + PostgreSQL |
| Signature | RSA-2048 + RSA-PSS |
| Hash | SHA-256 |
| Private key | Argon2id + AES-256-GCM |
| QR | URL + random verification token |
| Document | PDF final immutable |
| Multi signer | Multiple Independent Digital Signatures |
| Signing | Sequential workflow via `sign_order` |
| Verification | Recalculate hash + cryptographic verification |
| Database | 5 tables |
| Crypto library | phpseclib3 + OpenSSL + Sodium |
| No | Blockchain, PQC, PKI/CA, TSA, PAdES, cryptographic multisignature |

Arsitektur tidak diubah lagi kecuali technical spike membuktikan ada bagian yang benar-benar tidak jalan.
