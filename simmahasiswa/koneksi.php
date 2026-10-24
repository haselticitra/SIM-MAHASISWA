<?php
/**
 * Koneksi MySQL/MariaDB untuk XAMPP dan shared hosting.
 * Salin config.local.php.example menjadi config.local.php untuk mengisi
 * kredensial hosting. File lokal tersebut tidak ikut disimpan di Git.
 */

$databaseConfig = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'sim_mahasiswa',
    'port' => 3306,
];

$localConfigPath = __DIR__ . '/config.local.php';

if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;

    if (!is_array($localConfig)) {
        error_log('SIM Mahasiswa: config.local.php must return an array.');
        http_response_code(500);
        exit('Konfigurasi database tidak valid. Periksa config.local.php.');
    }

    $databaseConfig = array_replace($databaseConfig, $localConfig);
}

foreach (['host', 'username', 'database'] as $requiredSetting) {
    if (!isset($databaseConfig[$requiredSetting])
        || !is_string($databaseConfig[$requiredSetting])
        || trim($databaseConfig[$requiredSetting]) === '') {
        error_log('SIM Mahasiswa: invalid database setting: ' . $requiredSetting);
        http_response_code(500);
        exit('Konfigurasi database tidak lengkap. Periksa config.local.php.');
    }
}

if (!isset($databaseConfig['password']) || !is_string($databaseConfig['password'])) {
    error_log('SIM Mahasiswa: invalid database setting: password');
    http_response_code(500);
    exit('Konfigurasi database tidak valid. Periksa config.local.php.');
}

$port = filter_var($databaseConfig['port'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);

if ($port === false) {
    error_log('SIM Mahasiswa: database port must be between 1 and 65535.');
    http_response_code(500);
    exit('Port database tidak valid. Periksa config.local.php.');
}

try {
    $koneksi = mysqli_connect(
        $databaseConfig['host'],
        $databaseConfig['username'],
        $databaseConfig['password'],
        $databaseConfig['database'],
        $port
    );

    if (!$koneksi) {
        throw new RuntimeException(mysqli_connect_error());
    }

    if (!mysqli_set_charset($koneksi, 'utf8mb4')) {
        throw new RuntimeException(mysqli_error($koneksi));
    }
} catch (mysqli_sql_exception | RuntimeException $exception) {
    error_log('SIM Mahasiswa database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Periksa konfigurasi dan pastikan layanan MySQL aktif.');
}
