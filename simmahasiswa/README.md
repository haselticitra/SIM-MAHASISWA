# SIM Mahasiswa UMB

Aplikasi PHP native dan MySQL untuk pengelolaan data mahasiswa per program studi. Aplikasi menyediakan antarmuka CRUD, login berbasis peran, serta endpoint web service berbentuk JSON.

## Menjalankan dengan XAMPP

1. Salin folder `simmahasiswa` ke `C:\xampp\htdocs\`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP.
3. Buka phpMyAdmin, buat database `sim_mahasiswa` dengan collation `utf8mb4_unicode_ci`, lalu import [`sim_mahasiswa.sql`](./sim_mahasiswa.sql).
4. Konfigurasi bawaan di [`koneksi.php`](./koneksi.php) sesuai untuk XAMPP standar (`localhost`, `root`, tanpa password). Jika pengaturan MySQL Anda berbeda, salin `config.local.php.example` ke `config.local.php` dan sesuaikan nilainya.
5. Buka `http://localhost/simmahasiswa/`. Buat akun administrator pertama di `http://localhost/simmahasiswa/setupadmin.php`, lalu segera hapus `setupadmin.php` dari server setelah berhasil.

Dump `sim_mahasiswa.sql` adalah skema yang digunakan aplikasi PHP di folder ini. Jangan gunakan `dump_universitas_semantik.sql` dari folder induk sebagai penggantinya; dump tersebut memakai nama kolom/skema yang berbeda.
Jika Anda memperbarui instalasi lama yang sudah menyimpan data, cadangkan database lalu jalankan `migrasi_dosen_semantik.sql` sebelum membuka modul baru. Jangan impor ulang dump penuh ke database berisi data.

## Menjalankan di InfinityFree atau shared hosting

1. Buat database MySQL dari panel hosting dan import isi `sim_mahasiswa.sql` melalui phpMyAdmin hosting.
2. Salin `config.local.php.example` menjadi `config.local.php`. Isi `host`, `username`, `password`, `database`, dan `port` menggunakan nilai persis dari panel hosting. File konfigurasi ini dikecualikan dari Git agar kredensial tidak ikut terunggah ke repository.
3. Unggah isi folder `simmahasiswa` ke `htdocs`/`public_html` hosting menggunakan File Manager atau FTP. Pastikan `index.php` berada di folder publik yang benar.
4. Buka URL domain hosting, jalankan `setupadmin.php` satu kali, lalu hapus file tersebut dari server setelah akun berhasil dibuat.

Aturan `.htaccess` mencegah file dump SQL, arsip ZIP, README, dan konfigurasi lokal diunduh melalui browser. Jika hosting tidak mengizinkan aturan `.htaccess`, jangan simpan dump database atau arsip backup di folder publik.

URL hosting dan kredensial database harus dibuat dari akun InfinityFree pemiliknya. Aplikasi dan langkah konfigurasi ini belum membuat akun hosting atau domain secara otomatis.

## SIM Dosen

- Jalankan [`migrasi_dosen_semantik.sql`](./migrasi_dosen_semantik.sql) melalui phpMyAdmin sebelum membuka modul dosen pada database yang sudah berisi data. Migrasi menambah role `DOSEN` dan tabel dosen tanpa menghapus tabel atau data lama; aman dijalankan ulang.
- Dump [`sim_mahasiswa.sql`](./sim_mahasiswa.sql) yang baru juga sudah memuat role dan tabel dosen untuk instalasi baru.
- Admin dan operator Program Studi dapat membuat, melihat, mengubah, dan menghapus data dosen dalam scope masing-masing. Dekanat dan rektorat hanya dapat melihat dosen dalam scope mereka. Akun `DOSEN` hanya dapat melihat profilnya sendiri.
- Field dosen: NIDN 10 digit, nama lengkap, Program Studi, email, nomor telepon, dan status. Setiap profil memiliki akun role `DOSEN`; username adalah `dosen.` diikuti NIDN dan administrator menetapkan password awal (minimal 12 karakter).
- Endpoint `api/dosen.php` memerlukan sesi login dan mengembalikan informasi akademik sesuai scope; nomor telepon/email tidak diekspor.
- `api/login.php` membuat sesi PHP setelah autentikasi sukses; klien API harus menyimpan dan mengirim kembali cookie sesi untuk endpoint yang dilindungi.

## RDF, OWL, dan SPARQL

- `api/ontology.php?format=turtle` menyajikan ontology OWL dalam sintaks Turtle. Parameter format yang didukung: `turtle`, `jsonld`, dan `rdfxml`.
- `api/rdf.php?format=turtle` mengekspor data publik universitas, fakultas, dan program studi; format `jsonld` dan `rdfxml` juga tersedia.
- `api/sparql.php` adalah endpoint **baca-saja** yang memerlukan login. Kirim query melalui `POST` sebagai `application/sparql-query`, body JSON `{"query":"..."}`, atau parameter form `query`.
- Contoh query melalui `curl`: `curl -b cookies.txt -X POST -H "Content-Type: application/sparql-query" --data "PREFIX sim: <urn:simmahasiswa:vocab#> SELECT ?s ?nama WHERE { ?s sim:nama ?nama . } LIMIT 10" http://localhost/simmahasiswa/api/sparql.php`.
- Implementasi SPARQL berfokus pada subset SELECT pola triple dasar: `PREFIX`, variabel, `a`, pemisah titik/koma/titik-koma, `DISTINCT`, dan `LIMIT`. Batas query: 8 KB, 12 pola triple, 100 hasil, dan batas operasi untuk menolak query terlalu luas. `FILTER`, `OPTIONAL`, `UNION`, `ORDER BY`, agregasi, `SERVICE`, dan query update tidak didukung. Halaman `semantik.php` menyediakan contoh yang dapat dijalankan.
- Data RDF publik tidak memuat dosen, mahasiswa, akun, NIDN, alamat, tanggal lahir, email, atau nomor telepon. Query SPARQL terautentikasi hanya menambahkan nama/status dosen dan Program Studi sesuai scope: Admin/operator/dekanat/rektorat mengikuti scope masing-masing; dosen hanya dapat melihat entitas dirinya; mahasiswa hanya memperoleh graf institusi publik.

## Web service dan privasi

- Direktori `api/` berisi layanan JSON untuk mahasiswa, dosen, program studi, fakultas, universitas, dan login.
- `api/mahasiswa.php` dan alias lama `api/daftarmhs.php` sekarang memerlukan login dan membatasi hasil berdasarkan role. Respons tidak mencakup alamat atau tanggal lahir. Role mahasiswa hanya menerima datanya sendiri.
- Endpoint master institusi yang tidak berisi data personal tetap dapat dipakai secara publik.

## Catatan akun awal

Import database tidak menyediakan password administrator bawaan. Buat akun administrator baru melalui `setupadmin.php`; gunakan password kuat dan hapus file setup setelahnya. Akun contoh selain administrator memakai hash placeholder dan tidak dapat dipakai untuk login sampai password-nya diatur.

## Persyaratan

- PHP 8.0+ dengan ekstensi `mysqli` dan dukungan MySQL Native Driver (`mysqlnd`).
- MySQL atau MariaDB yang mendukung foreign key dan view.

## Pengujian

Jalankan regresi parser SPARQL, serialisasi RDF, dan scope role dari root repository dengan `php tests/semantic_graph_test.php`. Untuk uji CRUD dan ekspor RDF yang mengambil data aktual, jalankan MySQL lalu gunakan database hasil import/migrasi.
