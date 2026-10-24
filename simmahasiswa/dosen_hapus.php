<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/dosen_helper.php';

wajib_role(['ADMIN', 'OPERATOR_PRODI']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dosen.php');
    exit;
}

csrf_verify();
$id_dosen = filter_input(INPUT_POST, 'id_dosen', FILTER_VALIDATE_INT);
if (!$id_dosen) {
    $_SESSION['error'] = 'ID dosen tidak valid.';
    header('Location: dosen.php');
    exit;
}

try {
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT d.id_dosen, d.id_pengguna, d.id_program_studi, d.nidn, d.status_aktif
         FROM dosen d WHERE d.id_dosen = ? LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan pemeriksaan dosen.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $id_dosen);
    mysqli_stmt_execute($stmt);
    $dosen = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$dosen || !dosen_operator_scope_matches((int)$dosen['id_program_studi'])) {
        $_SESSION['error'] = 'Dosen tidak ditemukan atau di luar scope Anda.';
        header('Location: dosen.php');
        exit;
    }
    if ((int)$dosen['id_pengguna'] === (int)($_SESSION['id_pengguna'] ?? 0)) {
        $_SESSION['error'] = 'Akun dosen yang sedang digunakan tidak dapat dihapus.';
        header('Location: dosen.php');
        exit;
    }

    mysqli_begin_transaction($koneksi);
    $transaction_started = true;
    dosen_audit($koneksi, (int)$_SESSION['id_pengguna'], 'DELETE', (int)$id_dosen, dosen_audit_snapshot($dosen), null);

    $stmt = mysqli_prepare($koneksi, "DELETE FROM dosen WHERE id_dosen = ?");
    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan penghapusan dosen.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $id_dosen);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Gagal menghapus profil dosen: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    $id_pengguna = (int)$dosen['id_pengguna'];
    $stmt = mysqli_prepare($koneksi, "DELETE FROM pengguna WHERE id_pengguna = ?");
    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan penghapusan akun dosen.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $id_pengguna);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Gagal menghapus akun dosen: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    mysqli_commit($koneksi);
    $transaction_started = false;
    $_SESSION['success'] = 'Data dosen dan akun loginnya berhasil dihapus.';
} catch (Throwable $e) {
    if (!empty($transaction_started)) {
        mysqli_rollback($koneksi);
    }
    error_log('Dosen delete failed: ' . $e->getMessage());
    $_SESSION['error'] = 'Data dosen tidak dapat dihapus.';
}

header('Location: dosen.php');
exit;
