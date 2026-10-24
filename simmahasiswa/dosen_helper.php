<?php
require_once __DIR__ . '/csrf.php';

function dosen_scope_clause(string $dosen_alias = 'd', string $fakultas_alias = 'f', string $universitas_alias = 'u'): array
{
    $role = $_SESSION['kode_role'] ?? '';

    return match ($role) {
        'ADMIN' => ['', '', []],
        'OPERATOR_PRODI' => [" AND {$dosen_alias}.id_program_studi = ?", 'i', [(int)($_SESSION['id_program_studi'] ?? 0)]],
        'DEKANAT' => [" AND {$fakultas_alias}.id_fakultas = ?", 'i', [(int)($_SESSION['id_fakultas'] ?? 0)]],
        'REKTORAT' => [" AND {$universitas_alias}.id_universitas = ?", 'i', [(int)($_SESSION['id_universitas'] ?? 0)]],
        'DOSEN' => [" AND {$dosen_alias}.id_pengguna = ?", 'i', [(int)($_SESSION['id_pengguna'] ?? 0)]],
        default => [' AND 1 = 0', '', []],
    };
}

function dosen_bind_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
}

function dosen_audit(
    mysqli $koneksi,
    int $actor_id,
    string $action,
    int $id_dosen,
    ?array $old_data,
    ?array $new_data
): void {
    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO audit_log
            (id_pengguna, tabel_nama, record_id, aksi, data_lama, data_baru, ip_address, user_agent)
         VALUES (?, 'dosen', ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan audit data dosen.');
    }

    $record_id = (string)$id_dosen;
    $old_json = $old_data === null ? null : json_encode($old_data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $new_json = $new_data === null ? null : json_encode($new_data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    mysqli_stmt_bind_param(
        $stmt,
        'issssss',
        $actor_id,
        $record_id,
        $action,
        $old_json,
        $new_json,
        $ip_address,
        $user_agent
    );

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Gagal mencatat audit data dosen: ' . $error);
    }

    mysqli_stmt_close($stmt);
}

function dosen_program_studi(mysqli $koneksi, int $id_program_studi): ?array
{
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT ps.id_program_studi, ps.id_fakultas, ps.status_aktif, f.id_universitas
         FROM program_studi ps
         INNER JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
         WHERE ps.id_program_studi = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException('Gagal memeriksa program studi: ' . mysqli_error($koneksi));
    }

    mysqli_stmt_bind_param($stmt, 'i', $id_program_studi);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);

    return $row;
}

function dosen_operator_scope_matches(int $id_program_studi): bool
{
    return ($_SESSION['kode_role'] ?? '') !== 'OPERATOR_PRODI'
        || $id_program_studi === (int)($_SESSION['id_program_studi'] ?? 0);
}

function dosen_audit_snapshot(array $data): array
{
    return [
        'nidn' => $data['nidn'],
        'id_program_studi' => (int)$data['id_program_studi'],
        'status_aktif' => $data['status_aktif'],
    ];
}
