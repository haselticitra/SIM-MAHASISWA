<?php
session_start();

if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: dashboard.php");
    exit;
}

$database = null;
$programs = [];
$overview = null;
$programsLoaded = false;
$overviewLoaded = false;
$programsUnavailable = false;
$overviewUnavailable = false;
$databaseConfig = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'sim_mahasiswa',
    'port' => 3306,
];

try {
    $localConfigPath = __DIR__ . '/config.local.php';
    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;
        if (!is_array($localConfig)) {
            throw new RuntimeException('config.local.php must return an array.');
        }
        $databaseConfig = array_replace($databaseConfig, $localConfig);
    }

    foreach (['host', 'username', 'database'] as $setting) {
        if (!isset($databaseConfig[$setting]) || !is_string($databaseConfig[$setting])
            || trim($databaseConfig[$setting]) === '') {
            throw new RuntimeException('Invalid database setting: ' . $setting);
        }
    }
    if (!isset($databaseConfig['password']) || !is_string($databaseConfig['password'])) {
        throw new RuntimeException('Invalid database setting: password');
    }

    $port = filter_var($databaseConfig['port'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($port === false) {
        throw new RuntimeException('Database port must be between 1 and 65535.');
    }

    set_error_handler(static function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
        mysqli_report(MYSQLI_REPORT_OFF);
        $database = mysqli_connect(
            $databaseConfig['host'],
            $databaseConfig['username'],
            $databaseConfig['password'],
            $databaseConfig['database'],
            $port
        );
        if (!$database) {
            throw new RuntimeException(mysqli_connect_error() ?: 'Unable to connect to database.');
        }
        if (!mysqli_set_charset($database, 'utf8mb4')) {
            throw new RuntimeException(mysqli_error($database) ?: 'Unable to set database character set.');
        }
    } finally {
        restore_error_handler();
    }
} catch (Throwable $exception) {
    error_log('SIM Mahasiswa landing database setup failed: ' . $exception->getMessage());
    $programsUnavailable = true;
    $overviewUnavailable = true;
}

if ($database) {
    try {
        $statement = mysqli_prepare(
            $database,
            'SELECT
                (SELECT COUNT(*) FROM mahasiswa) AS jumlah_mahasiswa,
                (SELECT COUNT(*) FROM program_studi) AS jumlah_program_studi,
                (SELECT COUNT(*) FROM fakultas) AS jumlah_fakultas'
        );
        if (!$statement) {
            throw new RuntimeException(mysqli_error($database) ?: 'Unable to prepare overview query.');
        }
        if (!mysqli_stmt_execute($statement)
            || !mysqli_stmt_bind_result($statement, $studentsCount, $programsCount, $facultiesCount)
            || mysqli_stmt_fetch($statement) !== true) {
            throw new RuntimeException(mysqli_stmt_error($statement) ?: 'Unable to retrieve overview data.');
        }
        $overview = [
            'mahasiswa' => (int)$studentsCount,
            'program_studi' => (int)$programsCount,
            'fakultas' => (int)$facultiesCount,
        ];
        $overviewLoaded = true;
        mysqli_stmt_close($statement);
    } catch (Throwable $exception) {
        error_log('SIM Mahasiswa landing overview query failed: ' . $exception->getMessage());
        $overviewUnavailable = true;
        if (isset($statement) && $statement instanceof mysqli_stmt) {
            mysqli_stmt_close($statement);
        }
    }

    try {
        $statement = mysqli_prepare(
            $database,
            'SELECT
                ps.nama_program_studi,
                ps.jenjang,
                f.nama_fakultas,
                u.nama_universitas,
                COUNT(m.id_mahasiswa) AS jumlah_mahasiswa
            FROM program_studi ps
            INNER JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
            INNER JOIN universitas u ON u.id_universitas = f.id_universitas
            LEFT JOIN mahasiswa m ON m.id_program_studi = ps.id_program_studi
            GROUP BY
                ps.id_program_studi,
                ps.nama_program_studi,
                ps.jenjang,
                f.id_fakultas,
                f.nama_fakultas,
                u.id_universitas,
                u.nama_universitas
            ORDER BY u.nama_universitas ASC, f.nama_fakultas ASC, ps.nama_program_studi ASC'
        );
        if (!$statement) {
            throw new RuntimeException(mysqli_error($database) ?: 'Unable to prepare program query.');
        }
        if (!mysqli_stmt_execute($statement)
            || !mysqli_stmt_bind_result($statement, $programName, $level, $facultyName, $universityName, $programStudentCount)) {
            throw new RuntimeException(mysqli_stmt_error($statement) ?: 'Unable to retrieve program data.');
        }
        while (($fetchStatus = mysqli_stmt_fetch($statement)) === true) {
            $programs[] = [
                'nama_program_studi' => $programName,
                'jenjang' => $level,
                'nama_fakultas' => $facultyName,
                'nama_universitas' => $universityName,
                'jumlah_mahasiswa' => (int)$programStudentCount,
            ];
        }
        if ($fetchStatus === false) {
            throw new RuntimeException(mysqli_stmt_error($statement) ?: 'Unable to finish reading program data.');
        }
        $programsLoaded = true;
        mysqli_stmt_close($statement);
    } catch (Throwable $exception) {
        error_log('SIM Mahasiswa landing program query failed: ' . $exception->getMessage());
        $programsUnavailable = true;
        if (isset($statement) && $statement instanceof mysqli_stmt) {
            mysqli_stmt_close($statement);
        }
    }

    mysqli_close($database);
}

function landing_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function landing_number($value)
{
    return number_format((int)$value, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f3f8ff">
    <title>SIM Mahasiswa — Universitas Muhammadiyah Bengkulu</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="landing-page">
    <header class="portal-header">
        <nav class="portal-nav" aria-label="Navigasi utama">
            <a class="portal-brand" href="#beranda" aria-label="SIM Mahasiswa, beranda">
                <span class="portal-brand__mark" aria-hidden="true">UMB</span>
                <span class="portal-brand__name">
                    <strong>Universitas Muhammadiyah Bengkulu</strong>
                    <small>Sistem Informasi Mahasiswa</small>
                </span>
            </a>
            <div class="portal-nav__links">
                <a href="#beranda">Beranda</a>
                <a href="#program-studi">Program Studi</a>
                <a href="#tentang">Tentang</a>
            </div>
            <a class="portal-button portal-button--nav" href="login.php">Masuk <span aria-hidden="true">↗</span></a>
        </nav>
    </header>

    <main>
        <section id="beranda" class="portal-hero" aria-labelledby="hero-title">
            <div class="portal-hero__copy">
                <p class="portal-eyebrow"><span></span> PORTAL DATA AKADEMIK</p>
                <h1 id="hero-title">Data Mahasiswa<br><em>Per Program Studi</em></h1>
                <p class="portal-hero__lead">
                    Kenali susunan data akademik Universitas Muhammadiyah Bengkulu—dari universitas hingga program studi—dalam satu portal yang tertata.
                </p>
                <div class="portal-hero__actions">
                    <a class="portal-button portal-button--primary" href="login.php">Masuk ke sistem <span aria-hidden="true">→</span></a>
                    <a class="portal-text-link" href="#tentang">Kenali Web Semantik <span aria-hidden="true">↓</span></a>
                </div>
                <div class="portal-hero__caption"><span class="portal-caption-line"></span> INFORMASI AKADEMIK, TERSUSUN DENGAN KONTEKS</div>
            </div>

            <div class="portal-illustration" role="img" aria-label="Ilustrasi kampus dan laptop dengan susunan Universitas, Fakultas, Program Studi, dan Mahasiswa">
                <div class="portal-sun"></div>
                <div class="portal-campus">
                    <div class="portal-campus__roof"></div>
                    <div class="portal-campus__building">
                        <i></i><i></i><i></i><i></i><i></i><i></i>
                        <b></b>
                    </div>
                    <div class="portal-campus__ground"></div>
                    <div class="portal-campus__tree portal-campus__tree--one"></div>
                    <div class="portal-campus__tree portal-campus__tree--two"></div>
                </div>
                <div class="portal-laptop">
                    <div class="portal-laptop__screen">
                        <div class="portal-laptop__top"><span>STRUKTUR DATA</span><b>● ● ●</b></div>
                        <div class="portal-tree">
                            <div class="portal-tree__node portal-tree__node--university"><span aria-hidden="true">◈</span> Universitas</div>
                            <div class="portal-tree__connector"></div>
                            <div class="portal-tree__node"><span aria-hidden="true">▦</span> Fakultas</div>
                            <div class="portal-tree__connector"></div>
                            <div class="portal-tree__node"><span aria-hidden="true">⌘</span> Program Studi</div>
                            <div class="portal-tree__connector"></div>
                            <div class="portal-tree__node portal-tree__node--student"><span aria-hidden="true">◉</span> Mahasiswa</div>
                        </div>
                    </div>
                    <div class="portal-laptop__base"></div>
                </div>
                <div class="portal-illustration__label"><span></span> SATU HIRARKI, KONTEKS YANG JELAS</div>
                <span class="portal-illustration__spark portal-illustration__spark--one" aria-hidden="true">✳</span>
                <span class="portal-illustration__spark portal-illustration__spark--two" aria-hidden="true">✦</span>
            </div>
        </section>

        <section id="fitur" class="portal-features" aria-label="Manfaat sistem">
            <article class="portal-feature">
                <span class="portal-feature__icon portal-feature__icon--blue" aria-hidden="true">⌘</span>
                <div><h2>Data berjenjang</h2><p>Program studi terhubung dengan fakultas dan universitasnya.</p></div>
            </article>
            <article class="portal-feature">
                <span class="portal-feature__icon portal-feature__icon--gold" aria-hidden="true">◈</span>
                <div><h2>Akses sesuai peran</h2><p>Pengelolaan data mengikuti peran dan cakupan pengguna.</p></div>
            </article>
            <article class="portal-feature">
                <span class="portal-feature__icon portal-feature__icon--green" aria-hidden="true">⌕</span>
                <div><h2>Pencarian terarah</h2><p>Data mahasiswa dapat dicari melalui halaman sistem setelah masuk.</p></div>
            </article>
            <article class="portal-feature">
                <span class="portal-feature__icon portal-feature__icon--violet" aria-hidden="true">⇄</span>
                <div><h2>Siap terhubung</h2><p>Endpoint tersedia untuk pertukaran data berformat JSON.</p></div>
            </article>
        </section>

        <section id="program-studi" class="portal-programs" aria-labelledby="programs-title">
            <div class="portal-section-heading">
                <div>
                    <p class="portal-eyebrow">JELAJAHI DATA AKADEMIK</p>
                    <h2 id="programs-title">Daftar Program Studi</h2>
                    <p class="portal-section-heading__copy">Program dan fakultas ditampilkan berdasarkan data yang tersedia di sistem.</p>
                </div>
                <span class="portal-section-heading__index">01 <i>/</i> AKADEMIK</span>
            </div>

            <?php if ($programsUnavailable || $overviewUnavailable): ?>
                <p class="portal-data-note" role="status">
                    <?php if ($programsUnavailable && $overviewUnavailable): ?>
                        Data program dan ringkasan belum dapat dimuat.
                    <?php elseif ($programsUnavailable): ?>
                        Data program studi belum dapat dimuat.
                    <?php else: ?>
                        Ringkasan data institusi belum dapat dimuat.
                    <?php endif; ?>
                    Silakan coba lagi nanti atau hubungi pengelola sistem.
                </p>
            <?php endif; ?>

            <?php if ($programsLoaded && count($programs) > 0): ?>
                <div class="portal-program-grid">
                    <?php foreach ($programs as $index => $program): ?>
                        <article class="portal-program-card">
                            <div class="portal-program-card__top">
                                <span class="portal-program-card__number"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                <span class="portal-program-card__level"><?= landing_escape($program['jenjang']) ?></span>
                            </div>
                            <h3><?= landing_escape($program['nama_program_studi']) ?></h3>
                            <p class="portal-program-card__faculty"><?= landing_escape($program['nama_fakultas']) ?></p>
                            <p class="portal-program-card__university"><?= landing_escape($program['nama_universitas']) ?></p>
                            <div class="portal-program-card__bottom">
                                <span><?= landing_number($program['jumlah_mahasiswa']) ?> mahasiswa terdata</span>
                                <a href="login.php" aria-label="Masuk untuk mengakses data di program studi <?= landing_escape($program['nama_program_studi']) ?>">Masuk <span aria-hidden="true">↗</span></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($programsLoaded): ?>
                <div class="portal-empty">
                    <span class="portal-empty__mark" aria-hidden="true">⌂</span>
                    <div><h3>Belum ada program studi</h3><p>Program studi akan tampil di sini setelah data institusi tersedia.</p></div>
                </div>
            <?php else: ?>
                <div class="portal-empty">
                    <span class="portal-empty__mark" aria-hidden="true">⌂</span>
                    <div><h3>Program studi belum tersedia</h3><p>Informasi ini akan ditampilkan setelah data dapat dimuat dari sistem.</p></div>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($overviewLoaded): ?>
            <section class="portal-stats" aria-label="Ringkasan data institusi">
                <div class="portal-stats__intro">
                    <p class="portal-eyebrow">SEKILAS DATA</p>
                    <h2>Gambaran<br>institusi.</h2>
                    <p>Ringkasan dihitung langsung dari basis data.</p>
                </div>
                <div class="portal-stat"><span>01</span><strong><?= landing_number($overview['mahasiswa']) ?></strong><p>Mahasiswa terdata</p></div>
                <div class="portal-stat"><span>02</span><strong><?= landing_number($overview['program_studi']) ?></strong><p>Program studi</p></div>
                <div class="portal-stat"><span>03</span><strong><?= landing_number($overview['fakultas']) ?></strong><p>Fakultas</p></div>
            </section>
        <?php endif; ?>

        <section id="tentang" class="portal-about" aria-labelledby="about-title">
            <div class="portal-about__heading">
                <p class="portal-eyebrow">MEMAHAMI WEB SEMANTIK</p>
                <h2 id="about-title">Data mendapat arti dari hubungan yang menyertainya.</h2>
            </div>
            <div class="portal-about__body">
                <p>
                    Web Semantik menghubungkan data akademik melalui RDF dan ontology OWL. Data institusi tersedia sebagai Turtle, RDF/XML, dan JSON-LD; pengguna terautentikasi dapat menjalankan query SPARQL baca yang dibatasi menurut hak akses.
                </p>
                <div class="portal-about__note">
                    <span class="portal-about__note-icon" aria-hidden="true">i</span>
                    <p><strong>Catatan implementasi</strong>Ekspor RDF/OWL tersedia sebagai Turtle, RDF/XML, dan JSON-LD; SPARQL menyediakan query baca-saja yang dibatasi oleh hak akses.</p>
                </div>
            </div>
        </section>

        <section class="portal-cta" aria-labelledby="cta-title">
            <div><p class="portal-eyebrow">PORTAL AKADEMIK UMB</p><h2 id="cta-title">Kelola data dalam konteks yang tepat.</h2></div>
            <a class="portal-button portal-button--light" href="login.php">Masuk ke SIM Mahasiswa <span aria-hidden="true">→</span></a>
        </section>
    </main>

    <footer class="portal-footer">
        <a class="portal-brand portal-brand--footer" href="#beranda">
            <span class="portal-brand__mark" aria-hidden="true">UMB</span>
            <span class="portal-brand__name"><strong>Universitas Muhammadiyah Bengkulu</strong><small>Sistem Informasi Mahasiswa</small></span>
        </a>
        <span class="portal-footer__copy">© <?= date('Y') ?> Universitas Muhammadiyah Bengkulu</span>
        <a class="portal-footer__top" href="#beranda">Kembali ke atas ↑</a>
    </footer>
</body>
</html>
