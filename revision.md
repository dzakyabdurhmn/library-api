# Fitur Pengembangan API

## 1.1 Modul Auth

- **Fitur Lupa Password**: Telah diimplementasikan pada modul Auth. [DONE]
- **Pengembalian Data Admin Setelah Login**: Semua data admin dikembalikan kecuali password. [DONE]

---

## 1.2 Penggunaan Filter & Validasi

- **Filter Menggunakan Array**: Kata kunci filter harus disusun dalam array `filter[0][string]`. Status: [PROGRESS]

---

## 1.3 Pengelolaan Data API

- **Modifikasi & Penghapusan Data (ID pada Body)**: ID dimasukkan melalui body request, bukan di URL. Implementasi belum selesai. [DONT]

  ```json
  {
    "id": 1,
    "username": "john_doe",
    "email": "john@example.com",
    "full_name": "John Doe",
    "nik": "1234567890123456",
    "phone": "081234567890",
    "gender": "male",
    "address": "123 Main St"
  }
  ```

- **Konsistensi Response**: Semua response data harus konsisten dalam format yang sama. [DONE]

  ```json
  {
    "data": [
      // Isi data
    ]
  }
  ```

- **Otorisasi via Environment Variables**: Jika akses API tidak berhasil, simpan informasi otorisasi di environment variables untuk autentikasi menggunakan Basic Auth (PHP). [DONE]

- **Request/Response Tidak Menampilkan Nama Tabel**: Pastikan bahwa nama tabel tidak diekspos dalam body request atau response. [DONE]

- **Panjang Kolom di Database**: Kolom varchar di database harus disesuaikan dengan kebutuhan kasus bisnis. [DONE]

- **Penyesuaian Format Request/Response Berdasarkan Negara**: Format data request/response disesuaikan dengan spesifikasi lokal negara yang relevan. [DONE]

---

## 2. Fitur Hari yang Berbeda

- **Reset Password dengan OTP**: OTP sudah terintegrasi, hanya tinggal styling antarmuka. [DONE]
- **URL untuk Modul Employee**: URL untuk modul data karyawan yang salah telah diganti menjadi `/admin`. [DONE]
- **Token berdasarkan Role**: Token disesuaikan dengan role masing-masing user dan dimasukkan sesuai module. [DONE]
- **Detail API melalui Parameter**: Untuk API `GET` detail, parameter ID harus dimasukkan melalui URL atau query string. [DONE]
- **Response Format**: Format response yang konsisten menggunakan `result - data`. [DONE]

---

## 3. Pencarian dan Pagination

- **Filter dan Search**: Fungsi pencarian harus berjalan bersamaan dengan filter yang diterapkan. [DONE]
- **Pagination Default**: Limit default untuk pagination adalah 10, dengan opsi untuk menyesuaikan. [DONE]
- **Pagination untuk Semua API List**: Semua API list harus mendukung pagination, dengan opsi untuk diaktifkan atau dinonaktifkan. [DONE]
- **Pencarian di Semua Kolom**: Fitur pencarian harus mendukung pencarian di seluruh kolom data, bukan hanya satu kolom. [DONE]
- **Filter di Semua Row**: Pastikan filter dapat diterapkan di setiap row hasil query. [DONE]

---

## 4. Validasi & Error Handling

- **Pesan API**: Semua pesan API harus jelas dan informatif, memberikan feedback yang berguna kepada pengguna. [DONE]
- **Validasi Konsisten dengan Struktur Database**: Validasi input harus selalu konsisten dengan skema database dan memastikan data yang diterima valid. [DELETE]
- **Penghapusan Menggunakan Body**: API `DELETE` harus menerima data ID di dalam body, bukan melalui parameter URL. [DONE]

---

## 5. Struktur API Modules

### 1.1 Modul Auth

- **OTP Folder**: Fitur OTP folder telah diimplementasikan. [DONE]
- **OTP Resend**: Implementasi resend OTP untuk verifikasi password. [DONE]
- **OTP Validate**: Validasi OTP untuk autentikasi. [DONE]
- **Email Body Reset Password**: Body request untuk reset password via email telah diatur. [DONE]

### 1.2 Pesan API di Semua Modul

- Pesan API yang konsisten dan jelas diterapkan di semua modul. [DONE]

### 1.3 Pagination: Fleksibilitas True/False

- Pagination harus dapat diaktifkan atau dinonaktifkan tergantung pada kebutuhan kasus tertentu. Fleksibel dalam penerapannya. [FLEXIBLE]

### 1.4 Pembaruan Filter API

- Pembaruan filter API untuk memastikan penggunaan filter yang lebih efisien dan terstruktur. [NOTED]

---

## 6. Fitur Lainnya

### 1. Registrasi di Superadmin

- Registrasi user dengan role superadmin berjalan dengan baik. [DONE]

### 2. Penyortiran Data pada API Get All

- Penyortiran data berdasarkan parameter yang diberikan untuk `GET all` list. [DONE]

### 3. Response Format Result - Data

- Semua response API menggunakan format `result - data`. [DONE]

### 4. Pagination Object & Konsistensi

- Pagination di seluruh API sudah konsisten dan sesuai dengan objek yang dibutuhkan. [DONE]

### 5. Pembaruan Profil Pengguna

- Pembaruan data di profil sudah diterapkan sesuai dengan role yang dimiliki. [DONE]

### 6. Otorisasi Token

- Apabila token tidak ditemukan atau tidak valid, API akan merespons dengan status unauthorized. [DONE]

---

## 7. Data Transaksi & Validasi

### 1. Penambahan Stock

- Penambahan stock untuk API add stock sudah berhasil diterapkan. [DONE]

### 2. Validasi Kustom: Format Indonesia

- Validasi kustom disesuaikan dengan format Indonesia, misalnya untuk nomor telepon, email, dll. [DONE]

### 3. Response 200 Walaupun Data Tidak Ditemukan

- API tetap mengembalikan response dengan status 200 meskipun tidak ada data yang ditemukan. [DONE]

### 4. Pesan Error yang Informatif

- Jika terjadi error, API akan memberikan pesan error yang informatif seperti `error_validation` dan jenis error lainnya. [DONE]

### 5. Penghapusan Status Author

- Penghapusan status author dilakukan dengan benar di API. [DONE]

### 6. Token Logout

- Logout menggunakan token sudah sesuai, dan token logout tetap ada di parameter URL. [DONE]

---

## 8. Pelajari HTTP Status Codes

- Penggunaan HTTP status codes sudah diterapkan dengan benar untuk menandakan status request. [DONE]

### 1. Endpoint `/delete`

- Meskipun metode sudah ada, endpoint `/delete` tetap digunakan untuk operasi penghapusan. [DONE]

### 2. Penggunaan Try-Catch

- Penggunaan blok `try-catch` untuk error handling wajib diterapkan di seluruh API. [DONE]

---

## 9. Laporan Transaksi & Data

### 1. Urutan Transaksi Berdasarkan Terbaru

- List transaksi diurutkan berdasarkan tanggal transaksi yang terbaru. [DONE]

### 2. Detail Transaksi Lengkap Berdasarkan `loan_id`

- Detail transaksi harus lengkap, dengan query berdasarkan ID (`loan_id`). [DONE]

### 3. Laporan Data Mati

- Laporan untuk data mati tidak boleh menggunakan query join, memastikan setiap query hanya mengambil data yang relevan. [DONE]

---

## 10. Fitur Tambahan & Peningkatan

- **Pengurutan Semua Field**: Semua field, termasuk ID, dapat diurutkan sesuai permintaan. [DONE]
- **Error Handling untuk Kesuksesan**: Meskipun operasi berhasil, jika terjadi error, pesan error tetap ada namun dalam bentuk string kosong. [DONE]
- **Unique Validation Saat Update**: Validasi untuk field yang unique hanya diterapkan saat data benar-benar berubah. [DONE]
- **Fitur Stock dengan Tipe**: API add stock mendukung tipe "keluar" atau "masuk" untuk pengelolaan stock. [DONE]
- **Detail Data Bisa Dicopy untuk Update**: Detail data pada objek bisa disalin untuk mempermudah proses update. [DONE]
- **Reset Password Dikirim ke Email**: Saat reset password, email konfirmasi dikirim kepada pengguna. [DONE]
- **Perbaikan Struktur Database Transaksi**: Struktur database untuk transaksi telah diperbaiki untuk efisiensi. [DONE]

---

## 11. Loan Detail & Pengelolaan Error

### 1. Loan Detail dengan ID Lengkap

- Detail pinjaman (`loan_id`) sekarang mencakup seluruh ID terkait untuk kemudahan tracking. [DONE]

### 2. Pengindeksan ID di `loan_detail`

- ID pada tabel `loan_detail` sudah diindeks untuk mempercepat pencarian. [DONE]

### 3. Penghapusan Username di Member

- Username di tabel member telah dihapus untuk memastikan data lebih aman dan terstruktur. [DONE]

### 4. Percentage Value dalam Bentuk Object

- Nilai persentase untuk pinjaman disimpan dalam bentuk objek yang mudah diproses. [DONE]

### 5. Pembagian Pesan Error untuk Email

- Pesan error terkait email sekarang dibedakan dengan jelas. [DONE]

### 6. Penggunaan `loan_id` pada Beberapa Proses

- Beberapa proses sekarang mengandalkan ` [DONE]

## 12

### 1. Untuk denda/aturan menggunakan Object [DONE SELASA] db

```json
[
  { "percentage": "20", "status": "broken" },
  { "percentage": "25", "status": "missing" }
]
```

### 2. Get all transction di kasi id loan

### 3. Masih ada kesalahan di get loan detail

```json
{
  "status": 200,
  "message": "Loan details retrieved successfully.",
  "error": "",
  "result": {
    "loan_id": 72,
    "member_id": 306,
    "transaction_code": "",
    "loan_date": "2024-11-18 14:46:31",
    "institution": "SMK MUH 1",
    "email": "fulan@example.com",
    "full_name": "John Doe",
    "address": "123 Main St",
    "book_array": [
      {
        "book_id": 100,
        "title": "Kisah Kesuksesan Abadi",
        "publisher_name": "Penerbit Buku Kompas",
        "publisher_address": "Jl. Palmerah No. 33, Jakarta",
        "publisher_phone": "021-01234567",
        "publisher_email": "info@bukukompas.com",
        "publication_year": "2023",
        "isbn": "978-1-234568-00-0",
        "author_name": "Rika Saputra",
        "author_biography": "Rika Saputra adalah penulis yang banyak menulis tentang pengalaman pribadi."
      },
      {
        "book_id": 99,
        "title": "Pendidikan untuk Masa Depan",
        "publisher_name": "Penerbit Kecil",
        "publisher_address": "Jl. Kalibata No. 35, Jakarta",
        "publisher_phone": "021-90123456",
        "publisher_email": "contact@penerbitkecil.com",
        "publication_year": "2023",
        "isbn": "978-1-234567-99-9",
        "author_name": "Vicky Kurniawan",
        "author_biography": "Vicky Kurniawan adalah penulis yang aktif di media sosial."
      },
      {
        "book_id": 100,
        "title": "Kisah Kesuksesan Abadi",
        "publisher_name": "Penerbit Buku Kompas",
        "publisher_address": "Jl. Palmerah No. 33, Jakarta",
        "publisher_phone": "021-01234567",
        "publisher_email": "info@bukukompas.com",
        "publication_year": "2023",
        "isbn": "978-1-234568-00-0",
        "author_name": "Rika Saputra",
        "author_biography": "Rika Saputra adalah penulis yang banyak menulis tentang pengalaman pribadi."
      }
    ]
  }
}
```

### 4. Masih belum bisa update return date dan status di table_loan detail [PROSESS]

### 5. Report yang berupa list cukup di beri parameter limit saja dan bisa di filter tanggal

### 6. detail stock activity dapat di filter by books
