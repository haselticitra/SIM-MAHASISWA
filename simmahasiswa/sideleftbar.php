<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = $_SESSION['kode_role'] ?? '';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-title">MENU UTAMA</div>

    <a href="dashboard.php" class="sidebar-link">
        <span>▦</span> Dashboard
    </a>

    <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'REKTORAT', 'DOSEN'], true)): ?>
        <div class="sidebar-title">DATA AKADEMIK</div>
    <?php endif; ?>

    <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI'], true)): ?>
        <a href="mahasiswa.php" class="sidebar-link">
            <span>👨‍🎓</span> Data Mahasiswa
        </a>
    <?php endif; ?>

    <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'REKTORAT', 'DOSEN'], true)): ?>
        <a href="dosen.php" class="sidebar-link">
            <span>👩‍🏫</span> <?= $role === 'DOSEN' ? 'Profil Saya' : 'Data Dosen' ?>
        </a>
    <?php endif; ?>

    <?php if ($role === 'ADMIN'): ?>
        <a href="universitas.php" class="sidebar-link">
            <span>🏫</span> Universitas
        </a>
        <a href="fakultas.php" class="sidebar-link">
            <span>🏛</span> Fakultas
        </a>
        <a href="program_studi.php" class="sidebar-link">
            <span>📚</span> Program Studi
        </a>
        <a href="pengguna.php" class="sidebar-link">
            <span>👥</span> Pengguna
        </a>
        <a href="role.php" class="sidebar-link">
            <span>🔐</span> Role
        </a>
    <?php endif; ?>

    <?php if (in_array($role, ['ADMIN', 'DEKANAT', 'REKTORAT'], true)): ?>
        <div class="sidebar-title">LAPORAN</div>
        <a href="laporan_mahasiswa.php" class="sidebar-link">
            <span>📊</span> Rekap Mahasiswa
        </a>
    <?php endif; ?>

    <?php if ($role === 'MAHASISWA'): ?>
        <div class="sidebar-title">AKUN SAYA</div>
        <a href="mahasiswa_profil.php" class="sidebar-link">
            <span>👤</span> Profil Saya
        </a>
    <?php endif; ?>

    <?php if (in_array($role, ['ADMIN', 'OPERATOR_PRODI'], true)): ?>
        <div class="sidebar-title">SISTEM</div>
        <a href="audit_log.php" class="sidebar-link">
            <span>📝</span> Audit Log
        </a>
    <?php endif; ?>

    <?php if ($role !== ''): ?>
        <div class="sidebar-title">WEB SEMANTIK</div>
        <a href="semantik.php" class="sidebar-link">
            <span>🔗</span> RDF / OWL / SPARQL
        </a>
    <?php endif; ?>
</aside>
