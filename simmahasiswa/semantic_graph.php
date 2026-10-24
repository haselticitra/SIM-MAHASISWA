<?php

const SIM_RDF_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
const SIM_RDFS_NS = 'http://www.w3.org/2000/01/rdf-schema#';
const SIM_OWL_NS = 'http://www.w3.org/2002/07/owl#';
const SIM_XSD_NS = 'http://www.w3.org/2001/XMLSchema#';
const SIM_VOCAB_NS = 'urn:simmahasiswa:vocab#';

function semantic_uri(string $value): array
{
    return ['type' => 'uri', 'value' => $value];
}

function semantic_literal(string $value, ?string $datatype = null, ?string $language = null): array
{
    return [
        'type' => 'literal',
        'value' => $value,
        'datatype' => $datatype,
        'language' => $language,
    ];
}

function semantic_add_triple(array &$triples, string $subject, string $predicate, array $object): void
{
    $triples[] = [
        'subject' => semantic_uri($subject),
        'predicate' => semantic_uri($predicate),
        'object' => $object,
    ];
}

function semantic_resource(string $type, int $id): string
{
    return 'urn:simmahasiswa:' . $type . ':' . $id;
}

function semantic_ontology_triples(): array
{
    $vocab = SIM_VOCAB_NS;
    $ontology = 'urn:simmahasiswa:ontology';
    $triples = [];
    semantic_add_triple($triples, $ontology, SIM_RDF_NS . 'type', semantic_uri(SIM_OWL_NS . 'Ontology'));
    semantic_add_triple($triples, $ontology, SIM_RDFS_NS . 'label', semantic_literal('SIM Akademik Ontology', SIM_XSD_NS . 'string'));

    $classes = [
        'Universitas' => 'Universitas',
        'Fakultas' => 'Fakultas',
        'ProgramStudi' => 'Program Studi',
        'Dosen' => 'Dosen',
    ];

    foreach ($classes as $local => $label) {
        $class = $vocab . $local;
        semantic_add_triple($triples, $class, SIM_RDF_NS . 'type', semantic_uri(SIM_OWL_NS . 'Class'));
        semantic_add_triple($triples, $class, SIM_RDFS_NS . 'label', semantic_literal($label, SIM_XSD_NS . 'string'));
    }

    $object_properties = [
        'memilikiFakultas' => [$vocab . 'Universitas', $vocab . 'Fakultas'],
        'memilikiProgramStudi' => [$vocab . 'Fakultas', $vocab . 'ProgramStudi'],
        'terdaftarPada' => [$vocab . 'Dosen', $vocab . 'ProgramStudi'],
    ];

    foreach ($object_properties as $local => [$domain, $range]) {
        $property = $vocab . $local;
        semantic_add_triple($triples, $property, SIM_RDF_NS . 'type', semantic_uri(SIM_OWL_NS . 'ObjectProperty'));
        semantic_add_triple($triples, $property, SIM_RDFS_NS . 'domain', semantic_uri($domain));
        semantic_add_triple($triples, $property, SIM_RDFS_NS . 'range', semantic_uri($range));
    }

    $datatype_properties = [
        'kode' => SIM_VOCAB_NS . 'OrganisasiAkademik',
        'nama' => SIM_OWL_NS . 'Thing',
        'jenjang' => SIM_VOCAB_NS . 'ProgramStudi',
        'status' => SIM_OWL_NS . 'Thing',
    ];

    foreach ($datatype_properties as $local => $domain) {
        $property = $vocab . $local;
        semantic_add_triple($triples, $property, SIM_RDF_NS . 'type', semantic_uri(SIM_OWL_NS . 'DatatypeProperty'));
        semantic_add_triple($triples, $property, SIM_RDFS_NS . 'domain', semantic_uri($domain));
        semantic_add_triple($triples, $property, SIM_RDFS_NS . 'range', semantic_uri(SIM_XSD_NS . 'string'));
    }

    semantic_add_triple($triples, $vocab . 'Universitas', SIM_RDFS_NS . 'subClassOf', semantic_uri(SIM_VOCAB_NS . 'OrganisasiAkademik'));
    semantic_add_triple($triples, $vocab . 'Fakultas', SIM_RDFS_NS . 'subClassOf', semantic_uri(SIM_VOCAB_NS . 'OrganisasiAkademik'));
    semantic_add_triple($triples, $vocab . 'ProgramStudi', SIM_RDFS_NS . 'subClassOf', semantic_uri(SIM_VOCAB_NS . 'OrganisasiAkademik'));
    semantic_add_triple($triples, $vocab . 'OrganisasiAkademik', SIM_RDF_NS . 'type', semantic_uri(SIM_OWL_NS . 'Class'));
    semantic_add_triple($triples, $vocab . 'OrganisasiAkademik', SIM_RDFS_NS . 'label', semantic_literal('Organisasi akademik', SIM_XSD_NS . 'string'));

    return $triples;
}

function semantic_institution_triples(mysqli $koneksi): array
{
    $triples = [];
    $result = mysqli_query($koneksi, "SELECT id_universitas, kode_universitas, nama_universitas FROM universitas ORDER BY id_universitas");
    if (!$result) {
        throw new RuntimeException('Gagal mengambil data universitas: ' . mysqli_error($koneksi));
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $subject = semantic_resource('universitas', (int)$row['id_universitas']);
        semantic_add_triple($triples, $subject, SIM_RDF_NS . 'type', semantic_uri(SIM_VOCAB_NS . 'Universitas'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'kode', semantic_literal($row['kode_universitas'], SIM_XSD_NS . 'string'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'nama', semantic_literal($row['nama_universitas'], SIM_XSD_NS . 'string'));
    }

    $result = mysqli_query(
        $koneksi,
        "SELECT f.id_fakultas, f.kode_fakultas, f.nama_fakultas, f.id_universitas
         FROM fakultas f ORDER BY f.id_fakultas"
    );
    if (!$result) {
        throw new RuntimeException('Gagal mengambil data fakultas: ' . mysqli_error($koneksi));
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $subject = semantic_resource('fakultas', (int)$row['id_fakultas']);
        $university = semantic_resource('universitas', (int)$row['id_universitas']);
        semantic_add_triple($triples, $subject, SIM_RDF_NS . 'type', semantic_uri(SIM_VOCAB_NS . 'Fakultas'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'kode', semantic_literal($row['kode_fakultas'], SIM_XSD_NS . 'string'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'nama', semantic_literal($row['nama_fakultas'], SIM_XSD_NS . 'string'));
        semantic_add_triple($triples, $university, SIM_VOCAB_NS . 'memilikiFakultas', semantic_uri($subject));
    }

    $result = mysqli_query(
        $koneksi,
        "SELECT ps.id_program_studi, ps.kode_program_studi, ps.nama_program_studi,
                ps.jenjang, ps.status_aktif, f.id_fakultas
         FROM program_studi ps
         INNER JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
         ORDER BY ps.id_program_studi"
    );
    if (!$result) {
        throw new RuntimeException('Gagal mengambil data program studi: ' . mysqli_error($koneksi));
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $subject = semantic_resource('program-studi', (int)$row['id_program_studi']);
        $faculty = semantic_resource('fakultas', (int)$row['id_fakultas']);
        semantic_add_triple($triples, $subject, SIM_RDF_NS . 'type', semantic_uri(SIM_VOCAB_NS . 'ProgramStudi'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'kode', semantic_literal($row['kode_program_studi'], SIM_XSD_NS . 'string'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'nama', semantic_literal($row['nama_program_studi'], SIM_XSD_NS . 'string'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'jenjang', semantic_literal($row['jenjang'], SIM_XSD_NS . 'string'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'status', semantic_literal($row['status_aktif'], SIM_XSD_NS . 'string'));
        semantic_add_triple($triples, $faculty, SIM_VOCAB_NS . 'memilikiProgramStudi', semantic_uri($subject));
    }

    return $triples;
}

function semantic_dosen_triples(mysqli $koneksi): array
{
    $role = $_SESSION['kode_role'] ?? '';
    if (!in_array($role, ['ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'REKTORAT', 'DOSEN'], true)) {
        return [];
    }

    require_once __DIR__ . '/dosen_helper.php';
    [$scope_sql, $scope_types, $scope_params] = dosen_scope_clause();
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT d.id_dosen, d.id_program_studi, d.status_aktif, p.nama_lengkap
         FROM dosen d
         INNER JOIN pengguna p ON p.id_pengguna = d.id_pengguna
         INNER JOIN program_studi ps ON ps.id_program_studi = d.id_program_studi
         INNER JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
         INNER JOIN universitas u ON u.id_universitas = f.id_universitas
         WHERE 1 = 1 {$scope_sql}
         ORDER BY d.id_dosen"
    );
    if (!$stmt) {
        throw new RuntimeException('Gagal mengambil data dosen: ' . mysqli_error($koneksi));
    }
    dosen_bind_params($stmt, $scope_types, $scope_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $triples = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $subject = semantic_resource('dosen', (int)$row['id_dosen']);
        $program_studi = semantic_resource('program-studi', (int)$row['id_program_studi']);
        semantic_add_triple($triples, $subject, SIM_RDF_NS . 'type', semantic_uri(SIM_VOCAB_NS . 'Dosen'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'nama', semantic_literal($row['nama_lengkap'], SIM_XSD_NS . 'string'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'status', semantic_literal($row['status_aktif'], SIM_XSD_NS . 'string'));
        semantic_add_triple($triples, $subject, SIM_VOCAB_NS . 'terdaftarPada', semantic_uri($program_studi));
    }

    mysqli_stmt_close($stmt);
    return $triples;
}

function semantic_public_graph(mysqli $koneksi): array
{
    return array_merge(semantic_ontology_triples(), semantic_institution_triples($koneksi));
}

function semantic_authorized_graph(mysqli $koneksi): array
{
    return array_merge(semantic_public_graph($koneksi), semantic_dosen_triples($koneksi));
}

function semantic_escape_literal(string $value): string
{
    return '"' . addcslashes($value, "\\\"\n\r\t") . '"';
}

function semantic_serialize_turtle(array $triples): string
{
    $output = "@prefix rdf: <" . SIM_RDF_NS . "> .\n";
    $output .= "@prefix rdfs: <" . SIM_RDFS_NS . "> .\n";
    $output .= "@prefix owl: <" . SIM_OWL_NS . "> .\n";
    $output .= "@prefix xsd: <" . SIM_XSD_NS . "> .\n";
    $output .= "@prefix sim: <" . SIM_VOCAB_NS . "> .\n\n";

    foreach ($triples as $triple) {
        $subject = '<' . $triple['subject']['value'] . '>';
        $predicate = '<' . $triple['predicate']['value'] . '>';
        $object = $triple['object'];
        if ($object['type'] === 'uri') {
            $formatted = '<' . $object['value'] . '>';
        } else {
            $formatted = semantic_escape_literal($object['value']);
            if (!empty($object['language'])) {
                $formatted .= '@' . $object['language'];
            } elseif (!empty($object['datatype'])) {
                $formatted .= '^^<' . $object['datatype'] . '>';
            }
        }
        $output .= "{$subject} {$predicate} {$formatted} .\n";
    }

    return $output;
}

function semantic_serialize_jsonld(array $triples): string
{
    $graph = [];
    foreach ($triples as $triple) {
        $subject = $triple['subject']['value'];
        $predicate = $triple['predicate']['value'];
        $object = $triple['object'];
        $value = $object['type'] === 'uri'
            ? ['@id' => $object['value']]
            : ['@value' => $object['value']];

        if ($object['type'] === 'literal' && !empty($object['language'])) {
            $value['@language'] = $object['language'];
        } elseif ($object['type'] === 'literal' && !empty($object['datatype'])) {
            $value['@type'] = $object['datatype'];
        }

        $graph[$subject]['@id'] = $subject;
        $graph[$subject][$predicate][] = $value;
    }

    return json_encode(
        ['@context' => ['rdf' => SIM_RDF_NS, 'rdfs' => SIM_RDFS_NS, 'owl' => SIM_OWL_NS, 'sim' => SIM_VOCAB_NS], '@graph' => array_values($graph)],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
}

function semantic_serialize_rdfxml(array $triples): string
{
    $namespaces = [
        'rdf' => SIM_RDF_NS,
        'rdfs' => SIM_RDFS_NS,
        'owl' => SIM_OWL_NS,
        'xsd' => SIM_XSD_NS,
        'sim' => SIM_VOCAB_NS,
    ];
    $subjects = [];
    foreach ($triples as $triple) {
        $subjects[$triple['subject']['value']][] = $triple;
    }

    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $output .= '<rdf:RDF';
    foreach ($namespaces as $prefix => $namespace) {
        $output .= ' xmlns:' . $prefix . '="' . $escape($namespace) . '"';
    }
    $output .= ">\n";

    foreach ($subjects as $subject => $subject_triples) {
        $output .= '  <rdf:Description rdf:about="' . $escape($subject) . '">' . "\n";
        foreach ($subject_triples as $triple) {
            $predicate = $triple['predicate']['value'];
            $qualified_name = null;
            foreach ($namespaces as $prefix => $namespace) {
                if (str_starts_with($predicate, $namespace)) {
                    $local = substr($predicate, strlen($namespace));
                    if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $local)) {
                        $qualified_name = $prefix . ':' . $local;
                        break;
                    }
                }
            }
            if ($qualified_name === null) {
                throw new RuntimeException('Namespace predicate RDF/XML tidak didukung.');
            }

            $object = $triple['object'];
            if ($object['type'] === 'uri') {
                $output .= '    <' . $qualified_name . ' rdf:resource="' . $escape($object['value']) . '" />' . "\n";
            } else {
                $attributes = '';
                if (!empty($object['language'])) {
                    $attributes = ' xml:lang="' . $escape($object['language']) . '"';
                } elseif (!empty($object['datatype'])) {
                    $attributes = ' rdf:datatype="' . $escape($object['datatype']) . '"';
                }
                $output .= '    <' . $qualified_name . $attributes . '>' . $escape($object['value']) . '</' . $qualified_name . '>' . "\n";
            }
        }
        $output .= "  </rdf:Description>\n";
    }

    return $output . "</rdf:RDF>\n";
}

function semantic_serialize(array $triples, string $format): array
{
    return match ($format) {
        'turtle', 'ttl' => ['text/turtle; charset=utf-8', semantic_serialize_turtle($triples)],
        'jsonld', 'json-ld' => ['application/ld+json; charset=utf-8', semantic_serialize_jsonld($triples)],
        'rdfxml', 'rdf', 'xml' => ['application/rdf+xml; charset=utf-8', semantic_serialize_rdfxml($triples)],
        default => throw new InvalidArgumentException('Format RDF tidak didukung. Gunakan turtle, jsonld, atau rdfxml.'),
    };
}

function semantic_tokenize_sparql(string $source): array
{
    $pattern = '/\s+|#[^\r\n]*|<[^>]*>|"(?:\\\\.|[^"\\\\])*"(?:@[A-Za-z]+(?:-[A-Za-z0-9]+)*|\^\^(?:<[^>]*>|[A-Za-z_][A-Za-z0-9_-]*:[A-Za-z_][A-Za-z0-9_.-]*))?|[?$][A-Za-z_][A-Za-z0-9_]*|[{}.;,]|[A-Za-z_][A-Za-z0-9_-]*:[A-Za-z_][A-Za-z0-9_.~-]*|[A-Za-z_][A-Za-z0-9_-]*|[+-]?[0-9]+(?:\.[0-9]+)?/';
    if (!preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
        throw new InvalidArgumentException('Sintaks SPARQL tidak dikenali.');
    }

    $tokens = [];
    $offset = 0;
    foreach ($matches[0] as [$token, $position]) {
        if (trim(substr($source, $offset, $position - $offset)) !== '') {
            throw new InvalidArgumentException('Ada token SPARQL yang tidak didukung.');
        }
        $offset = $position + strlen($token);
        if ($token !== '' && !preg_match('/^(?:\s+|#[^\r\n]*)$/', $token)) {
            $tokens[] = $token;
        }
    }
    if (trim(substr($source, $offset)) !== '') {
        throw new InvalidArgumentException('Ada token SPARQL yang tidak didukung.');
    }

    return $tokens;
}

function semantic_parse_sparql_term(string $token, array $prefixes): array
{
    if (preg_match('/^[?$]([A-Za-z_][A-Za-z0-9_]*)$/', $token, $match)) {
        return ['variable' => $match[1]];
    }
    if ($token === 'a') {
        return semantic_uri(SIM_RDF_NS . 'type');
    }
    if (preg_match('/^<([^>]*)>$/', $token, $match)) {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $match[1])) {
            throw new InvalidArgumentException('IRI relatif tidak didukung tanpa klausa BASE.');
        }
        return semantic_uri($match[1]);
    }
    if (str_starts_with($token, '"')) {
        if (!preg_match('/^("(?:\\\\.|[^"\\\\])*")(?:@([A-Za-z]+(?:-[A-Za-z0-9]+)*)|\^\^(.+))?$/s', $token, $match)) {
            throw new InvalidArgumentException('Literal SPARQL tidak valid.');
        }
        try {
            $value = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Literal SPARQL tidak valid.');
        }
        $language = $match[2] ?? null;
        $datatype = isset($match[3]) ? semantic_parse_sparql_term($match[3], $prefixes)['value'] : SIM_XSD_NS . 'string';
        return semantic_literal($value, $datatype, $language);
    }
    if (preg_match('/^(true|false)$/i', $token)) {
        return semantic_literal(strtolower($token), SIM_XSD_NS . 'boolean');
    }
    if (preg_match('/^[+-]?[0-9]+$/', $token)) {
        return semantic_literal($token, SIM_XSD_NS . 'integer');
    }
    if (preg_match('/^[+-]?[0-9]+\.[0-9]+$/', $token)) {
        return semantic_literal($token, SIM_XSD_NS . 'decimal');
    }
    if (preg_match('/^([A-Za-z_][A-Za-z0-9_-]*):([A-Za-z_][A-Za-z0-9_.~-]*)$/', $token, $match)) {
        if (!array_key_exists($match[1], $prefixes)) {
            throw new InvalidArgumentException('Prefix SPARQL tidak didefinisikan: ' . $match[1]);
        }
        return semantic_uri($prefixes[$match[1]] . $match[2]);
    }

    throw new InvalidArgumentException('Term SPARQL tidak didukung: ' . $token);
}

function semantic_parse_sparql(string $query): array
{
    if (strlen($query) > 8192) {
        throw new InvalidArgumentException('Query SPARQL maksimal 8 KB.');
    }

    $prefixes = [
        'rdf' => SIM_RDF_NS,
        'rdfs' => SIM_RDFS_NS,
        'owl' => SIM_OWL_NS,
        'xsd' => SIM_XSD_NS,
        'sim' => SIM_VOCAB_NS,
    ];
    $remaining = trim($query);
    while (preg_match('/^#[^\r\n]*(?:\R|$)\s*/', $remaining, $comment)) {
        $remaining = substr($remaining, strlen($comment[0]));
    }
    while (preg_match('/^PREFIX\s+([A-Za-z][A-Za-z0-9_-]*):\s*<([^>]*)>\s*/i', $remaining, $match)) {
        $prefixes[$match[1]] = $match[2];
        $remaining = substr($remaining, strlen($match[0]));
        $remaining = preg_replace('/^(?:\s*#[^\r\n]*(?:\R|$)\s*)+/', '', $remaining) ?? $remaining;
    }

    if (!preg_match('/^SELECT\s+(?:(DISTINCT)\s+)?(.+?)\s+WHERE\s*\{(.*)\}\s*(?:LIMIT\s+([0-9]+)\s*)?$/is', $remaining, $match)) {
        throw new InvalidArgumentException('Endpoint ini hanya menerima SELECT dengan pola triple dasar (WHERE {}).');
    }

    $distinct = !empty($match[1]);
    $selection = trim($match[2]);
    $where = $match[3];
    $requested_limit = isset($match[4]) ? (int)$match[4] : 100;
    if ($requested_limit < 1) {
        throw new InvalidArgumentException('LIMIT harus minimal 1.');
    }
    $limit = min(100, $requested_limit);

    $tokens = semantic_tokenize_sparql($where);
    $patterns = [];
    $position = 0;
    $variable_order = [];
    $take = static function () use (&$tokens, &$position): string {
        if (!isset($tokens[$position])) {
            throw new InvalidArgumentException('Pola triple SPARQL belum lengkap.');
        }
        return $tokens[$position++];
    };

    while ($position < count($tokens)) {
        if ($tokens[$position] === '.') {
            $position++;
            continue;
        }
        if (in_array($tokens[$position], ['{', '}', ';', ','], true)) {
            throw new InvalidArgumentException('Pola triple SPARQL tidak valid.');
        }

        $subject = semantic_parse_sparql_term($take(), $prefixes);
        $predicate = semantic_parse_sparql_term($take(), $prefixes);
        $same_subject = true;
        while ($same_subject) {
            $object = semantic_parse_sparql_term($take(), $prefixes);
            if (($subject['type'] ?? null) === 'literal' || ($predicate['type'] ?? null) === 'literal') {
                throw new InvalidArgumentException('Subjek dan predikat pola triple harus berupa resource atau variabel.');
            }
            $patterns[] = [$subject, $predicate, $object];
            foreach ([$subject, $predicate, $object] as $term) {
                if (isset($term['variable']) && !in_array($term['variable'], $variable_order, true)) {
                    $variable_order[] = $term['variable'];
                }
            }
            if (count($patterns) > 12) {
                throw new InvalidArgumentException('Maksimal 12 pola triple dalam satu query.');
            }

            if (($tokens[$position] ?? null) === ',') {
                $position++;
                continue;
            }

            if (($tokens[$position] ?? null) === ';') {
                $position++;
                if (in_array($tokens[$position] ?? null, [null, '.', '}'], true)) {
                    $same_subject = false;
                    continue;
                }
                $predicate = semantic_parse_sparql_term($take(), $prefixes);
                continue;
            }

            $same_subject = false;
        }

        if (($tokens[$position] ?? null) === '.') {
            $position++;
        } elseif ($position < count($tokens) && in_array($tokens[$position], [';', ','], true)) {
            throw new InvalidArgumentException('Pola separator SPARQL tidak valid.');
        }
    }

    if ($patterns === []) {
        throw new InvalidArgumentException('WHERE harus memiliki minimal satu pola triple.');
    }

    if ($selection === '*') {
        $selected = $variable_order;
    } else {
        $selection_tokens = preg_split('/\s+/', $selection) ?: [];
        $selected = [];
        foreach ($selection_tokens as $token) {
            if (!preg_match('/^[?$]([A-Za-z_][A-Za-z0-9_]*)$/', $token, $variable)) {
                throw new InvalidArgumentException('SELECT hanya menerima nama variabel atau *.');
            }
            if (!in_array($variable[1], $variable_order, true) || in_array($variable[1], $selected, true)) {
                throw new InvalidArgumentException('Variabel SELECT tidak valid atau tidak digunakan dalam WHERE.');
            }
            $selected[] = $variable[1];
        }
    }

    if ($selected === []) {
        throw new InvalidArgumentException('SELECT harus memilih minimal satu variabel.');
    }

    return ['distinct' => $distinct, 'variables' => $selected, 'patterns' => $patterns, 'limit' => $limit];
}

function semantic_term_matches(array $expected, array $actual, array &$bindings, string &$budget): bool
{
    if (isset($expected['variable'])) {
        $name = $expected['variable'];
        if (isset($bindings[$name])) {
            return semantic_term_matches($bindings[$name], $actual, $bindings, $budget);
        }
        $bindings[$name] = $actual;
        return true;
    }

    return ($expected['type'] ?? '') === ($actual['type'] ?? '')
        && ($expected['value'] ?? null) === ($actual['value'] ?? null)
        && ($expected['datatype'] ?? null) === ($actual['datatype'] ?? null)
        && ($expected['language'] ?? null) === ($actual['language'] ?? null);
}

function semantic_execute_sparql(string $query, array $triples): array
{
    $parsed = semantic_parse_sparql($query);
    $by_predicate = [];
    foreach ($triples as $triple) {
        $by_predicate[$triple['predicate']['value']][] = $triple;
    }

    $solutions = [[]];
    $operations = 0;
    foreach ($parsed['patterns'] as [$subject, $predicate, $object]) {
        $next_solutions = [];
        foreach ($solutions as $solution) {
            $candidate_triples = isset($predicate['value'])
                ? ($by_predicate[$predicate['value']] ?? [])
                : $triples;
            foreach ($candidate_triples as $triple) {
                if (++$operations > 500000) {
                    throw new LengthException('Query terlalu luas; batasi pola triple agar hasil dapat dihitung.');
                }
                $bindings = $solution;
                $budget = '';
                if (
                    semantic_term_matches($subject, $triple['subject'], $bindings, $budget)
                    && semantic_term_matches($predicate, $triple['predicate'], $bindings, $budget)
                    && semantic_term_matches($object, $triple['object'], $bindings, $budget)
                ) {
                    $next_solutions[] = $bindings;
                }
            }
        }
        $solutions = $next_solutions;
        if ($solutions === []) {
            break;
        }
    }

    $rows = [];
    $seen = [];
    foreach ($solutions as $solution) {
        $row = [];
        foreach ($parsed['variables'] as $variable) {
            if (isset($solution[$variable])) {
                $row[$variable] = $solution[$variable];
            }
        }
        if ($parsed['distinct']) {
            $key = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
        }
        $rows[] = $row;
        if (count($rows) >= $parsed['limit']) {
            break;
        }
    }

    return ['head' => ['vars' => $parsed['variables']], 'results' => ['bindings' => $rows]];
}
