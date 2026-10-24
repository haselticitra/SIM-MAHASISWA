<?php
require_once __DIR__ . '/../semantic_graph.php';

try {
    $format = strtolower(trim($_GET['format'] ?? 'turtle'));
    [$content_type, $body] = semantic_serialize(semantic_ontology_triples(), $format);
    header('Content-Type: ' . $content_type);
    header('Cache-Control: public, max-age=86400');
    echo $body;
} catch (Throwable $e) {
    error_log('OWL ontology serialization failed: ' . $e->getMessage());
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'Ontology tidak dapat diserialisasi.',
    ], JSON_UNESCAPED_UNICODE);
}
