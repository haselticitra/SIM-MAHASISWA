<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/dosen_helper.php';

wajib_role(['ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'REKTORAT', 'DOSEN']);

[$scope_sql, $scope_types, $scope_params] = dosen_scope_clause();
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT d.id_dosen, d.id_pengguna, d.id_program_studi, d.nidn, d.status_aktif,
            p.nama_lengkap, p.email, p.no_hp,
            ps.nama_program_studi, f.nama_fakultas
     FROM dosen d
     INNER JOIN pengguna p ON p.id_pengguna = d.id_pengguna
     INNER JOIN program_studi ps ON ps.id_program_studi = d.id_program_studi
     INNER JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
     INNER JOIN universitas u ON u.id_universitas = f.id_universitas
     WHERE 1 = 1 {$scope_sql}
     ORDER BY p.nama_lengkap"
);

if (!$stmt) {
    error_log('Dosen list query failed: ' . mysqli_error($koneksi));
    http_response_code(500);
    exit('Daftar dosen gagal dimuat.');
}

dosen_bind_params($stmt, $scope_types, $scope_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$can_manage = in_array($_SESSION['kode_role'] ?? '', ['ADMIN', 'OPERATOR_PRODI'], true);
$is_dosen = ($_SESSION['kode_role'] ?? '') === 'DOSEN';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dosen - SIM Akademik</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="app-body">
<?php include __DIR__ . '/topbar.php'; ?>
<?php include __DIR__ . '/sideleftbar.php'; ?>
<main class="main-content">
    <div class="page-heading page-heading-flex">
        <div>
            <span class="eyebrow">DATA AKADEMIK</span>
            <h1><?= $is_dosen ? 'Profil Saya' : 'Data Dosen' ?></h1>
            <p><?= $is_dosen ? 'Profil dosen Anda yang terdaftar.' : 'Kelola data dosen sesuai kewenangan program studi.' ?></p>
        </div>
        <?php if ($can_manage): ?><a class="btn btn-primary" href="dosen_tambah.php">+ Tambah Dosen</a><?php endif; ?>
    </div>
    <?php foreach (['success' => 'alert-success', 'error' => 'alert-danger'] as $key => $class): ?>
        <?php if (!empty($_SESSION[$key])): ?>
            <div class="alert <?= $class ?>"><?= htmlspecialchars($_SESSION[$key], ENT_QUOTES, 'UTF-8') ?></div>
            <?php unset($_SESSION[$key]); ?>
        <?php endif; ?>
    <?php endforeach; ?>
    <section class="dashboard-panel">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>No.</th><th>NIDN</th><th>Nama</th><th>Program Studi</th><th>Fakultas</th><th>Status</th><?php if ($can_manage): ?><th>Aksi</th><?php endif; ?></tr></thead>
                <tbody>
                <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nidn'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($can_manage && !empty($row['email'])): ?><div class="scope-text"><?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['nama_program_studi'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['nama_fakultas'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['status_aktif'], ENT_QUOTES, 'UTF-8') ?></td>
                        <?php if ($can_manage): ?>
                            <td>
                                <div class="action-group">
                                    <a class="btn btn-sm btn-outline" href="dosen_edit.php?id=<?= (int)$row['id_dosen'] ?>">Edit</a>
                                    <form action="dosen_hapus.php" method="post" onsubmit="return confirm('Hapus data dan akun dosen ini?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_dosen" value="<?= (int)$row['id_dosen'] ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
                <?php if ($no === 1): ?><tr><td class="empty-state" colspan="<?= $can_manage ? '7' : '6' ?>">Belum ada data dosen pada scope ini.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
<?php mysqli_stmt_close($stmt); ?>
