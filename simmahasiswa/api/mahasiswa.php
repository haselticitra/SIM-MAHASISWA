<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../csrf.php';

function api_mahasiswa_reply(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['login']) || $_SESSION['login'] !== true) {
    api_mahasiswa_reply(401, ['success' => false, 'message' => 'Login diperlukan untuk mengakses data mahasiswa.', 'data' => []]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    api_mahasiswa_reply(405, ['success' => false, 'message' => 'Method tidak diizinkan. Gunakan GET.', 'data' => []]);
}

$role = $_SESSION['kode_role'] ?? '';
$where = '';
$params = [];
$types = '';

switch ($role) {
    case 'ADMIN':
        break;
    case 'OPERATOR_PRODI':
        $where = ' WHERE ps.id_program_studi = ?';
        $params[] = (int)($_SESSION['id_program_studi'] ?? 0);
        $types = 'i';
        break;
    case 'DEKANAT':
        $where = ' WHERE f.id_fakultas = ?';
        $params[] = (int)($_SESSION['id_fakultas'] ?? 0);
        $types = 'i';
        break;
    case 'REKTORAT':
        $where = ' WHERE u.id_universitas = ?';
        $params[] = (int)($_SESSION['id_universitas'] ?? 0);
        $types = 'i';
        break;
    case 'MAHASISWA':
        $where = ' WHERE m.id_pengguna = ?';
        $params[] = (int)($_SESSION['id_pengguna'] ?? 0);
        $types = 'i';
        break;
    default:
        api_mahasiswa_reply(403, ['success' => false, 'message' => 'Role tidak memiliki akses ke data mahasiswa.', 'data' => []]);
}

require_once __DIR__ . '/../koneksi.php';

$npm = trim($_GET['npm'] ?? '');
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

if ($npm !== '') {
    $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'm.npm = ?';
    $params[] = $npm;
    $types .= 's';
} elseif ($search !== '') {
    $where .= ($where === '' ? ' WHERE ' : ' AND ') . '(m.npm LIKE ? OR m.nama_mahasiswa LIKE ?)';
    $keyword = '%' . $search . '%';
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= 'ss';
}

if ($status !== '') {
    $allowed_statuses = ['Aktif', 'Cuti', 'Lulus', 'Mengundurkan Diri', 'Drop Out', 'Tidak Aktif'];
    if (!in_array($status, $allowed_statuses, true)) {
        api_mahasiswa_reply(400, ['success' => false, 'message' => 'Filter status tidak valid.', 'data' => []]);
    }
    $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'm.status_mahasiswa = ?';
    $params[] = $status;
    $types .= 's';
}

$from = " FROM mahasiswa m
          INNER JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
          INNER JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
          INNER JOIN universitas u ON u.id_universitas = f.id_universitas";

try {
$count_stmt = mysqli_prepare($koneksi, 'SELECT COUNT(*) AS total' . $from . $where);
if (!$count_stmt) {
    error_log('Student API count prepare failed: ' . mysqli_error($koneksi));
    api_mahasiswa_reply(500, ['success' => false, 'message' => 'Permintaan data gagal diproses.', 'data' => []]);
}
if ($types !== '') {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$total = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'] ?? 0);
mysqli_stmt_close($count_stmt);

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT m.id_mahasiswa, m.npm, m.nama_mahasiswa, m.status_mahasiswa,
            ps.kode_program_studi, ps.nama_program_studi, ps.jenjang,
            f.kode_fakultas, f.nama_fakultas, u.kode_universitas, u.nama_universitas
     {$from} {$where}
     ORDER BY m.nama_mahasiswa ASC
     LIMIT ? OFFSET ?"
);

if (!$stmt) {
    error_log('Student API data prepare failed: ' . mysqli_error($koneksi));
    api_mahasiswa_reply(500, ['success' => false, 'message' => 'Permintaan data gagal diproses.', 'data' => []]);
}

$query_params = $params;
$query_params[] = $limit;
$query_params[] = $offset;
$query_types = $types . 'ii';
mysqli_stmt_bind_param($stmt, $query_types, ...$query_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}
mysqli_stmt_close($stmt);

if ($npm !== '' && $total === 0) {
    api_mahasiswa_reply(404, ['success' => false, 'message' => 'Mahasiswa tidak ditemukan dalam scope Anda.', 'data' => []]);
}

api_mahasiswa_reply(200, [
    'success' => true,
    'message' => 'Data mahasiswa berhasil diambil sesuai scope akun.',
    'data' => $data,
    'meta' => [
        'page' => $page,
        'limit' => $limit,
        'total_data' => $total,
        'total_pages' => (int)ceil($total / $limit),
    ],
]);
} catch (Throwable $e) {
    error_log('Student API request failed: ' . $e->getMessage());
    api_mahasiswa_reply(500, ['success' => false, 'message' => 'Permintaan data mahasiswa gagal diproses.', 'data' => []]);
}
