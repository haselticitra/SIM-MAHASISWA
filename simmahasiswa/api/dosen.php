<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../dosen_helper.php';

function api_dosen_reply(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['login']) || $_SESSION['login'] !== true) {
    api_dosen_reply(401, ['success' => false, 'message' => 'Login diperlukan untuk mengakses data dosen.', 'data' => []]);
}

if (!in_array($_SESSION['kode_role'] ?? '', ['ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'REKTORAT', 'DOSEN'], true)) {
    api_dosen_reply(403, ['success' => false, 'message' => 'Role tidak memiliki akses ke data dosen.', 'data' => []]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    api_dosen_reply(405, ['success' => false, 'message' => 'Method tidak diizinkan. Gunakan GET.', 'data' => []]);
}

require_once __DIR__ . '/../koneksi.php';

[$scope_sql, $scope_types, $scope_params] = dosen_scope_clause();
$nidn = trim($_GET['nidn'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;
if ($nidn !== '') {
    $scope_sql .= ' AND d.nidn = ?';
    $scope_types .= 's';
    $scope_params[] = $nidn;
}

try {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT COUNT(*) AS total
         FROM dosen d
         INNER JOIN pengguna p ON p.id_pengguna = d.id_pengguna
         INNER JOIN program_studi ps ON ps.id_program_studi = d.id_program_studi
         INNER JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
         INNER JOIN universitas u ON u.id_universitas = f.id_universitas
         WHERE 1 = 1 {$scope_sql}"
    );

    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan hitung data dosen: ' . mysqli_error($koneksi));
    }
    dosen_bind_params($stmt, $scope_types, $scope_params);
    mysqli_stmt_execute($stmt);
    $total = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT d.id_dosen, d.nidn, p.nama_lengkap, d.status_aktif,
            ps.kode_program_studi, ps.nama_program_studi, ps.jenjang,
            f.kode_fakultas, f.nama_fakultas, u.kode_universitas, u.nama_universitas
     FROM dosen d
     INNER JOIN pengguna p ON p.id_pengguna = d.id_pengguna
     INNER JOIN program_studi ps ON ps.id_program_studi = d.id_program_studi
     INNER JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
     INNER JOIN universitas u ON u.id_universitas = f.id_universitas
     WHERE 1 = 1 {$scope_sql}
     ORDER BY p.nama_lengkap
     LIMIT ? OFFSET ?"
    );

    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan data dosen: ' . mysqli_error($koneksi));
    }

    $query_params = $scope_params;
    $query_params[] = $limit;
    $query_params[] = $offset;
    dosen_bind_params($stmt, $scope_types . 'ii', $query_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    mysqli_stmt_close($stmt);

    api_dosen_reply(200, [
        'success' => true,
        'message' => 'Data dosen berhasil diambil sesuai scope akun.',
        'data' => $data,
        'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total_data' => $total,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
} catch (Throwable $e) {
    error_log('Lecturer API request failed: ' . $e->getMessage());
    api_dosen_reply(500, ['success' => false, 'message' => 'Permintaan data dosen gagal diproses.', 'data' => []]);
}
