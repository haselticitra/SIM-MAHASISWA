<?php
header('Vary: Cookie');
header('Content-Type: application/sparql-results+json; charset=utf-8');

require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../semantic_graph.php';

function sparql_error(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['login']) || $_SESSION['login'] !== true) {
    header('WWW-Authenticate: Session');
    sparql_error(401, 'Login diperlukan untuk menjalankan SPARQL.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    sparql_error(405, 'Gunakan POST dengan parameter query SPARQL.');
}

$query = trim($_POST['query'] ?? '');
if ($query === '') {
    $raw_body = file_get_contents('php://input');
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/sparql-query')) {
        $query = trim($raw_body);
    } elseif (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $payload = json_decode($raw_body, true);
        if (is_array($payload) && is_string($payload['query'] ?? null)) {
            $query = trim($payload['query']);
        }
    }
}
if ($query === '') {
    sparql_error(400, 'Parameter query SPARQL wajib diisi.');
}

require_once __DIR__ . '/../koneksi.php';

try {
    $result = semantic_execute_sparql($query, semantic_authorized_graph($koneksi));
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    sparql_error(400, $e->getMessage());
} catch (LengthException $e) {
    sparql_error(413, $e->getMessage());
} catch (Throwable $e) {
    error_log('SPARQL query failed: ' . $e->getMessage());
    sparql_error(500, 'Query semantik gagal diproses.');
}
