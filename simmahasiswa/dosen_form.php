<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/dosen_helper.php';

wajib_role(['ADMIN', 'OPERATOR_PRODI']);

$mode = $dosen_form_mode ?? '';
if (!in_array($mode, ['create', 'edit'], true)) {
    header('Location: dosen.php');
    exit;
}

$is_edit = $mode === 'edit';
$id_dosen = $is_edit ? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) : null;

if ($is_edit && !$id_dosen) {
    $_SESSION['error'] = 'ID dosen tidak valid.';
    header('Location: dosen.php');
    exit;
}

$data = [
    'nidn' => '',
    'nama_lengkap' => '',
    'email' => '',
    'no_hp' => '',
    'id_program_studi' => (int)($_SESSION['id_program_studi'] ?? 0),
    'status_aktif' => 'Aktif',
];
$id_pengguna = 0;
$existing_program_studi_id = 0;

if ($is_edit) {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT d.id_dosen, d.id_pengguna, d.id_program_studi, d.nidn, d.status_aktif,
                p.nama_lengkap, p.email, p.no_hp
         FROM dosen d
         INNER JOIN pengguna p ON p.id_pengguna = d.id_pengguna
         WHERE d.id_dosen = ?
         LIMIT 1"
    );

    if (!$stmt) {
        error_log('Dosen edit load failed: ' . mysqli_error($koneksi));
        http_response_code(500);
        exit('Data dosen gagal dimuat.');
    }

    mysqli_stmt_bind_param($stmt, 'i', $id_dosen);
    mysqli_stmt_execute($stmt);
    $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$current || !dosen_operator_scope_matches((int)$current['id_program_studi'])) {
        $_SESSION['error'] = 'Data dosen tidak ditemukan atau di luar scope Anda.';
        header('Location: dosen.php');
        exit;
    }

    $data = $current;
    $id_pengguna = (int)$current['id_pengguna'];
    $existing_program_studi_id = (int)$current['id_program_studi'];
}

$program_studi = mysqli_query(
    $koneksi,
    "SELECT ps.id_program_studi, ps.kode_program_studi, ps.nama_program_studi,
            ps.status_aktif, f.nama_fakultas
     FROM program_studi ps
     INNER JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
     ORDER BY f.nama_fakultas, ps.nama_program_studi"
);

if (!$program_studi) {
    error_log('Dosen program list failed: ' . mysqli_error($koneksi));
    http_response_code(500);
    exit('Daftar program studi gagal dimuat.');
}

$error = '';
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $data['nidn'] = trim($_POST['nidn'] ?? '');
    $data['nama_lengkap'] = trim($_POST['nama_lengkap'] ?? '');
    $data['email'] = trim($_POST['email'] ?? '');
    $data['no_hp'] = trim($_POST['no_hp'] ?? '');
    $data['id_program_studi'] = filter_input(INPUT_POST, 'id_program_studi', FILTER_VALIDATE_INT) ?: 0;
    $data['status_aktif'] = $_POST['status_aktif'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (!preg_match('/^[0-9]{10}$/', $data['nidn'])) {
        $error = 'NIDN harus terdiri dari 10 angka.';
    } elseif ($data['nama_lengkap'] === '' || mb_strlen($data['nama_lengkap']) > 200) {
        $error = 'Nama dosen wajib diisi dan maksimal 200 karakter.';
    } elseif ($data['email'] !== '' && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 150)) {
        $error = 'Email tidak valid atau melebihi 150 karakter.';
    } elseif (mb_strlen($data['no_hp']) > 30) {
        $error = 'Nomor telepon maksimal 30 karakter.';
    } elseif (!in_array($data['status_aktif'], ['Aktif', 'Tidak Aktif'], true)) {
        $error = 'Status dosen tidak valid.';
    } elseif (!$is_edit && mb_strlen($password) < 12) {
        $error = 'Password akun dosen minimal 12 karakter.';
    } elseif ($is_edit && $password !== '' && mb_strlen($password) < 12) {
        $error = 'Password baru minimal 12 karakter.';
    } elseif (($password !== '' || $password_confirm !== '') && ($password === '' || $password !== $password_confirm)) {
        $error = 'Konfirmasi password tidak sama.';
    } elseif (!$data['id_program_studi']) {
        $error = 'Program studi wajib dipilih.';
    } elseif (!dosen_operator_scope_matches((int)$data['id_program_studi'])) {
        $error = 'Operator hanya dapat mengelola dosen pada program studinya.';
    }

    $prodi = null;
    if ($error === '') {
        try {
            $prodi = dosen_program_studi($koneksi, (int)$data['id_program_studi']);
            if (!$prodi) {
                $error = 'Program studi yang dipilih tidak ditemukan.';
            } elseif (
                $prodi['status_aktif'] !== 'Aktif'
                && (!$is_edit || (int)$data['id_program_studi'] !== $existing_program_studi_id)
            ) {
                $error = 'Dosen hanya dapat ditempatkan pada program studi aktif.';
            }
        } catch (Throwable $e) {
            error_log('Dosen program validation failed: ' . $e->getMessage());
            $error = 'Validasi program studi gagal.';
        }
    }

    if ($error === '') {
        $sql = "SELECT id_dosen FROM dosen WHERE nidn = ?" . ($is_edit ? " AND id_dosen <> ?" : '') . " LIMIT 1";
        $stmt = mysqli_prepare($koneksi, $sql);

        if (!$stmt) {
            $error = 'Pemeriksaan NIDN gagal.';
        } else {
            if ($is_edit) {
                mysqli_stmt_bind_param($stmt, 'si', $data['nidn'], $id_dosen);
            } else {
                mysqli_stmt_bind_param($stmt, 's', $data['nidn']);
            }

            mysqli_stmt_execute($stmt);
            $duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($duplicate) {
                $error = 'NIDN tersebut sudah terdaftar.';
            }
        }
    }

    $username = 'dosen.' . $data['nidn'];
    if ($error === '') {
        $sql = "SELECT id_pengguna FROM pengguna WHERE username = ?" . ($is_edit ? " AND id_pengguna <> ?" : '') . " LIMIT 1";
        $stmt = mysqli_prepare($koneksi, $sql);

        if (!$stmt) {
            $error = 'Pemeriksaan username gagal.';
        } else {
            if ($is_edit) {
                mysqli_stmt_bind_param($stmt, 'si', $username, $id_pengguna);
            } else {
                mysqli_stmt_bind_param($stmt, 's', $username);
            }

            mysqli_stmt_execute($stmt);
            $duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($duplicate) {
                $error = 'Username dosen tersebut sudah digunakan.';
            }
        }
    }

    if ($error === '') {
        try {
            mysqli_begin_transaction($koneksi);
            $transaction_started = true;

            if ($is_edit) {
                $stmt = mysqli_prepare($koneksi, "SELECT * FROM dosen WHERE id_dosen = ? LIMIT 1");
                if (!$stmt) {
                    throw new RuntimeException('Gagal mengambil data dosen lama.');
                }
                mysqli_stmt_bind_param($stmt, 'i', $id_dosen);
                mysqli_stmt_execute($stmt);
                $old_record = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);

                $password_hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
                if ($password_hash !== null) {
                    $stmt = mysqli_prepare(
                        $koneksi,
                        "UPDATE pengguna
                         SET id_universitas = ?, id_fakultas = ?, id_program_studi = ?,
                             username = ?, nama_lengkap = ?, email = NULLIF(?, ''),
                             no_hp = NULLIF(?, ''), status_aktif = ?, password_hash = ?
                         WHERE id_pengguna = ?"
                    );
                    if (!$stmt) {
                        throw new RuntimeException('Gagal menyiapkan pembaruan akun dosen.');
                    }
                    $id_universitas = (int)$prodi['id_universitas'];
                    $id_fakultas = (int)$prodi['id_fakultas'];
                    mysqli_stmt_bind_param($stmt, 'iiissssssi', $id_universitas, $id_fakultas, $data['id_program_studi'], $username, $data['nama_lengkap'], $data['email'], $data['no_hp'], $data['status_aktif'], $password_hash, $id_pengguna);
                } else {
                    $stmt = mysqli_prepare(
                        $koneksi,
                        "UPDATE pengguna
                         SET id_universitas = ?, id_fakultas = ?, id_program_studi = ?,
                             username = ?, nama_lengkap = ?, email = NULLIF(?, ''),
                             no_hp = NULLIF(?, ''), status_aktif = ?
                         WHERE id_pengguna = ?"
                    );
                    if (!$stmt) {
                        throw new RuntimeException('Gagal menyiapkan pembaruan akun dosen.');
                    }
                    $id_universitas = (int)$prodi['id_universitas'];
                    $id_fakultas = (int)$prodi['id_fakultas'];
                    mysqli_stmt_bind_param($stmt, 'iiisssssi', $id_universitas, $id_fakultas, $data['id_program_studi'], $username, $data['nama_lengkap'], $data['email'], $data['no_hp'], $data['status_aktif'], $id_pengguna);
                }

                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('Gagal memperbarui akun dosen: ' . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare(
                    $koneksi,
                    "UPDATE dosen SET nidn = ?, id_program_studi = ?, status_aktif = ? WHERE id_dosen = ?"
                );
                if (!$stmt) {
                    throw new RuntimeException('Gagal menyiapkan pembaruan data dosen.');
                }
                mysqli_stmt_bind_param($stmt, 'sisi', $data['nidn'], $data['id_program_studi'], $data['status_aktif'], $id_dosen);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('Gagal memperbarui data dosen: ' . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);

                dosen_audit($koneksi, (int)$_SESSION['id_pengguna'], 'UPDATE', (int)$id_dosen, dosen_audit_snapshot($old_record), dosen_audit_snapshot($data));
                $success = 'Data dosen berhasil diperbarui.';
            } else {
                $role_stmt = mysqli_prepare($koneksi, "SELECT id_role FROM roles WHERE kode_role = 'DOSEN' LIMIT 1");
                if (!$role_stmt) {
                    throw new RuntimeException('Gagal memeriksa role dosen.');
                }
                mysqli_stmt_execute($role_stmt);
                $role_record = mysqli_fetch_assoc(mysqli_stmt_get_result($role_stmt));
                mysqli_stmt_close($role_stmt);
                if (!$role_record) {
                    throw new RuntimeException('Role DOSEN belum tersedia. Jalankan migrasi_dosen_semantik.sql.');
                }

                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO pengguna
                        (id_role, id_universitas, id_fakultas, id_program_studi,
                         username, password_hash, nama_lengkap, email, no_hp, status_aktif)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)"
                );
                if (!$stmt) {
                    throw new RuntimeException('Gagal menyiapkan akun dosen.');
                }
                $id_role = (int)$role_record['id_role'];
                $id_universitas = (int)$prodi['id_universitas'];
                $id_fakultas = (int)$prodi['id_fakultas'];
                mysqli_stmt_bind_param($stmt, 'iiiissssss', $id_role, $id_universitas, $id_fakultas, $data['id_program_studi'], $username, $password_hash, $data['nama_lengkap'], $data['email'], $data['no_hp'], $data['status_aktif']);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('Gagal membuat akun dosen: ' . mysqli_stmt_error($stmt));
                }
                $id_pengguna = (int)mysqli_insert_id($koneksi);
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare(
                    $koneksi,
                    "INSERT INTO dosen (id_pengguna, id_program_studi, nidn, status_aktif)
                     VALUES (?, ?, ?, ?)"
                );
                if (!$stmt) {
                    throw new RuntimeException('Gagal menyiapkan data dosen.');
                }
                mysqli_stmt_bind_param($stmt, 'iiss', $id_pengguna, $data['id_program_studi'], $data['nidn'], $data['status_aktif']);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException('Gagal menyimpan data dosen: ' . mysqli_stmt_error($stmt));
                }
                $id_dosen = (int)mysqli_insert_id($koneksi);
                mysqli_stmt_close($stmt);

                dosen_audit($koneksi, (int)$_SESSION['id_pengguna'], 'INSERT', $id_dosen, null, dosen_audit_snapshot($data));
                $success = 'Data dosen dan akun login berhasil dibuat. Username: ' . $username . '.';
            }

            mysqli_commit($koneksi);
            $transaction_started = false;
            $_SESSION['success'] = $success;
            header('Location: dosen.php');
            exit;
        } catch (Throwable $e) {
            if (!empty($transaction_started)) {
                mysqli_rollback($koneksi);
            }
            error_log('Dosen save failed: ' . $e->getMessage());
            $error = 'Data dosen tidak dapat disimpan. Periksa NIDN dan database, lalu coba lagi.';
        }
    }
}

function dosen_form_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Tambah' ?> Dosen - SIM Akademik</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dosen-form-panel{max-width:850px;padding:24px}.dosen-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.dosen-form-group{display:flex;flex-direction:column;gap:7px}.dosen-form-group label{font-weight:700;font-size:14px}.dosen-form-group input,.dosen-form-group select{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;background:white}.dosen-form-help{font-size:12px;color:#64748b;line-height:1.5}.dosen-form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px;padding-top:18px;border-top:1px solid #e5e7eb}.dosen-form-full{grid-column:1/-1}@media(max-width:640px){.dosen-form-grid{grid-template-columns:1fr}.dosen-form-full{grid-column:auto}.dosen-form-panel{padding:16px}}
    </style>
</head>
<body class="app-body">
<?php include __DIR__ . '/topbar.php'; ?>
<?php include __DIR__ . '/sideleftbar.php'; ?>
<main class="main-content">
    <div class="page-heading">
        <span class="eyebrow">DATA AKADEMIK</span>
        <h1><?= $is_edit ? 'Edit Dosen' : 'Tambah Dosen' ?></h1>
        <p>Akun dosen terhubung dengan Program Studi. Username login menggunakan awalan <code>dosen.</code> diikuti NIDN.</p>
    </div>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= dosen_form_escape($error) ?></div><?php endif; ?>
    <section class="dashboard-panel dosen-form-panel">
        <form method="post">
            <?= csrf_field() ?>
            <div class="dosen-form-grid">
                <div class="dosen-form-group">
                    <label for="nidn">NIDN (10 angka)</label>
                    <input id="nidn" name="nidn" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required value="<?= dosen_form_escape($data['nidn']) ?>">
                </div>
                <div class="dosen-form-group">
                    <label for="nama_lengkap">Nama lengkap</label>
                    <input id="nama_lengkap" name="nama_lengkap" maxlength="200" required value="<?= dosen_form_escape($data['nama_lengkap']) ?>">
                </div>
                <div class="dosen-form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="150" value="<?= dosen_form_escape($data['email']) ?>">
                </div>
                <div class="dosen-form-group">
                    <label for="no_hp">Nomor telepon</label>
                    <input id="no_hp" name="no_hp" type="tel" maxlength="30" value="<?= dosen_form_escape($data['no_hp']) ?>">
                </div>
                <div class="dosen-form-group">
                    <label for="id_program_studi">Program Studi</label>
                    <select id="id_program_studi" name="id_program_studi" required>
                        <option value="">Pilih Program Studi</option>
                        <?php while ($ps = mysqli_fetch_assoc($program_studi)): ?>
                            <?php if (dosen_operator_scope_matches((int)$ps['id_program_studi']) && ($ps['status_aktif'] === 'Aktif' || (int)$ps['id_program_studi'] === (int)$data['id_program_studi'])): ?>
                                <option value="<?= (int)$ps['id_program_studi'] ?>" <?= (int)$ps['id_program_studi'] === (int)$data['id_program_studi'] ? 'selected' : '' ?>>
                                    <?= dosen_form_escape($ps['nama_program_studi'] . ' — ' . $ps['nama_fakultas']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="dosen-form-group">
                    <label for="status_aktif">Status</label>
                    <select id="status_aktif" name="status_aktif" required>
                        <option value="Aktif" <?= $data['status_aktif'] === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="Tidak Aktif" <?= $data['status_aktif'] === 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                    </select>
                </div>
                <div class="dosen-form-group dosen-form-full">
                    <label for="password"><?= $is_edit ? 'Password baru (opsional)' : 'Password akun dosen' ?></label>
                    <input id="password" name="password" type="password" minlength="12" autocomplete="<?= $is_edit ? 'new-password' : 'new-password' ?>" <?= $is_edit ? '' : 'required' ?>>
                    <span class="dosen-form-help"><?= $is_edit ? 'Kosongkan jika password tidak ingin diubah.' : 'Minimal 12 karakter; tentukan password awal yang disampaikan secara aman kepada dosen.' ?></span>
                </div>
                <div class="dosen-form-group dosen-form-full">
                    <label for="password_confirm">Konfirmasi password</label>
                    <input id="password_confirm" name="password_confirm" type="password" minlength="12" autocomplete="new-password" <?= $is_edit ? '' : 'required' ?>>
                    <?php if ($is_edit): ?><span class="dosen-form-help">Isi bersama password baru; biarkan kedua kolom password kosong jika tidak ingin menggantinya.</span><?php endif; ?>
                </div>
            </div>
            <div class="dosen-form-actions">
                <a class="btn btn-outline" href="dosen.php">Batal</a>
                <button class="btn btn-primary" type="submit"><?= $is_edit ? 'Simpan Perubahan' : 'Buat Dosen' ?></button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
