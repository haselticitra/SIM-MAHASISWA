<?php
require_once __DIR__ . '/../simmahasiswa/semantic_graph.php';
require_once __DIR__ . '/../simmahasiswa/dosen_helper.php';

function test_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$triples = [];
$program = 'urn:simmahasiswa:program-studi:1';
$faculty = 'urn:simmahasiswa:fakultas:1';
$faculty2 = 'urn:simmahasiswa:fakultas:2';

semantic_add_triple($triples, $program, SIM_RDF_NS . 'type', semantic_uri(SIM_VOCAB_NS . 'ProgramStudi'));
semantic_add_triple($triples, $program, SIM_VOCAB_NS . 'nama', semantic_literal('Teknik Informatika', SIM_XSD_NS . 'string'));
semantic_add_triple($triples, $program, SIM_VOCAB_NS . 'jenjang', semantic_literal('S1', SIM_XSD_NS . 'string'));
semantic_add_triple($triples, $program, SIM_VOCAB_NS . 'kode', semantic_literal('TI', SIM_XSD_NS . 'string'));
semantic_add_triple($triples, $faculty, SIM_VOCAB_NS . 'memilikiProgramStudi', semantic_uri($program));
semantic_add_triple($triples, $faculty2, SIM_VOCAB_NS . 'memilikiProgramStudi', semantic_uri($program));

$query = 'PREFIX sim: <' . SIM_VOCAB_NS . '> PREFIX rdf: <' . SIM_RDF_NS . ">
SELECT DISTINCT ?program ?nama
WHERE {
    ?program rdf:type sim:ProgramStudi ;
             sim:nama ?nama ;
             sim:jenjang \"S1\" .
}
LIMIT 10";
$result = semantic_execute_sparql($query, $triples);
test_expect($result['head']['vars'] === ['program', 'nama'], 'SELECT variables are preserved.');
test_expect(count($result['results']['bindings']) === 1, 'Triple joins and DISTINCT return one result.');
test_expect($result['results']['bindings'][0]['program']['value'] === $program, 'The subject URI matches.');
test_expect($result['results']['bindings'][0]['nama']['value'] === 'Teknik Informatika', 'Literal values match.');

$join_query = 'PREFIX sim: <' . SIM_VOCAB_NS . "> SELECT ?faculty ?program WHERE {
    ?faculty sim:memilikiProgramStudi ?program .
    ?program sim:kode \"TI\" .
}";
$join_result = semantic_execute_sparql($join_query, $triples);
test_expect(count($join_result['results']['bindings']) === 2, 'Multiple basic graph patterns join correctly.');

$comma_query = 'PREFIX sim: <' . SIM_VOCAB_NS . '> SELECT ?value WHERE {
    <' . $program . '> sim:kode ?value, "TI" .
}';
test_expect(count(semantic_execute_sparql($comma_query, $triples)['results']['bindings']) === 1, 'Comma-separated objects are parsed.');

$all_triples = $triples;
for ($i = 1; $i <= 105; $i++) {
    semantic_add_triple($all_triples, 'urn:test:subject:' . $i, SIM_RDF_NS . 'type', semantic_uri(SIM_VOCAB_NS . 'ProgramStudi'));
}
$limited = semantic_execute_sparql(
    'PREFIX sim: <' . SIM_VOCAB_NS . '> PREFIX rdf: <' . SIM_RDF_NS . '> SELECT ?s WHERE { ?s rdf:type sim:ProgramStudi . } LIMIT 1000',
    $all_triples
);
test_expect(count($limited['results']['bindings']) === 100, 'The endpoint caps result sets at 100 rows.');

foreach ([
    'ASK { ?s ?p ?o }',
    'SELECT ?s WHERE { ?s ?p ?o FILTER(?s = ?o) }',
    'SELECT ?s WHERE { ?s x:missing ?o . }',
    'SELECT ?s WHERE { ?s <urn:test:p> "unterminated . }',
] as $invalid_query) {
    try {
        semantic_execute_sparql($invalid_query, $triples);
        throw new RuntimeException('Unsupported query should be rejected: ' . $invalid_query);
    } catch (InvalidArgumentException) {
    }
}

$ontology = semantic_ontology_triples();
$jsonld = json_decode(semantic_serialize_jsonld($ontology), true, 512, JSON_THROW_ON_ERROR);
test_expect(count($jsonld['@graph']) > 0, 'Ontology JSON-LD serializes to a graph.');
$xml = semantic_serialize_rdfxml($ontology);
$document = new DOMDocument();
test_expect($document->loadXML($xml), 'Ontology RDF/XML is well-formed.');
test_expect(str_contains(semantic_serialize_turtle($ontology), '@prefix owl:'), 'Ontology Turtle declares OWL.');

$_SESSION['kode_role'] = 'DOSEN';
$_SESSION['id_pengguna'] = 42;
[$scope, $types, $params] = dosen_scope_clause();
test_expect($scope === ' AND d.id_pengguna = ?' && $types === 'i' && $params === [42], 'DOSEN scope is limited to the signed-in account.');

$_SESSION['kode_role'] = 'OPERATOR_PRODI';
$_SESSION['id_program_studi'] = 7;
[$scope, $types, $params] = dosen_scope_clause();
test_expect($scope === ' AND d.id_program_studi = ?' && $params === [7], 'Operator scope is limited to their program.');

$_SESSION['kode_role'] = 'DEKANAT';
$_SESSION['id_fakultas'] = 4;
[$scope, $types, $params] = dosen_scope_clause();
test_expect($scope === ' AND f.id_fakultas = ?' && $params === [4], 'Faculty scope is limited to their faculty.');

$_SESSION['kode_role'] = 'REKTORAT';
$_SESSION['id_universitas'] = 3;
[$scope, $types, $params] = dosen_scope_clause();
test_expect($scope === ' AND u.id_universitas = ?' && $params === [3], 'Rectorate scope is limited to their university.');

echo "Semantic graph tests passed.\n";
