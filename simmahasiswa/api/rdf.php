<?php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../semantic_graph.php';

try {
    $format = strtolower(trim($_GET['format'] ?? 'turtle'));
    [$content_type, $body] = semantic_serialize(semantic_public_graph($koneksi), $format);
    header('Content-Type: ' . $content_type);
    header('Cache-Control: public, max-age=300');
    echo $body;
} catch (Throwable $e) {
    error_log('Public RDF export failed: ' . $e->getMessage());
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'Ekspor data RDF gagal diproses.',
    ], JSON_UNESCAPED_UNICODE);
}
