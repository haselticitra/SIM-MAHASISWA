-- Upgrade database SIM Mahasiswa yang sudah berisi data.
-- Aman dijalankan ulang: tidak menghapus/mengganti data aplikasi yang ada.
-- Jalankan setelah memilih database sim_mahasiswa di phpMyAdmin.

INSERT INTO roles (kode_role, nama_role, keterangan)
VALUES (
    'DOSEN',
    'Dosen',
    'Mengakses profil dosen sendiri dan data semantik dalam scope yang diizinkan'
)
ON DUPLICATE KEY UPDATE
    nama_role = VALUES(nama_role),
    keterangan = VALUES(keterangan);

CREATE TABLE IF NOT EXISTS dosen (
    id_dosen BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pengguna BIGINT UNSIGNED NOT NULL,
    id_program_studi BIGINT UNSIGNED NOT NULL,
    nidn VARCHAR(20) NOT NULL,
    status_aktif ENUM('Aktif', 'Tidak Aktif') NOT NULL DEFAULT 'Aktif',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_dosen),
    UNIQUE KEY uk_dosen_pengguna (id_pengguna),
    UNIQUE KEY uk_dosen_nidn (nidn),
    KEY idx_dosen_prodi_status (id_program_studi, status_aktif),
    CONSTRAINT fk_dosen_pengguna
        FOREIGN KEY (id_pengguna) REFERENCES pengguna (id_pengguna)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_dosen_prodi
        FOREIGN KEY (id_program_studi) REFERENCES program_studi (id_program_studi)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
