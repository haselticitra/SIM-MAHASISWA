<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/semantic_graph.php';

$ontology_url = 'api/ontology.php?format=turtle';
$rdf_url = 'api/rdf.php?format=turtle';
$example_query = 'PREFIX sim: <' . SIM_VOCAB_NS . ">
PREFIX rdf: <" . SIM_RDF_NS . ">
SELECT ?prodi ?nama
WHERE {
  ?prodi rdf:type sim:ProgramStudi ;
         sim:nama ?nama .
}
LIMIT 20";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Semantik - SIM Akademik</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .semantic-panel{padding:22px;margin-bottom:20px}.semantic-links{display:flex;flex-wrap:wrap;gap:10px;margin:14px 0}.semantic-query{width:100%;min-height:190px;box-sizing:border-box;padding:14px;border:1px solid #cbd5e1;border-radius:9px;font:13px/1.6 Consolas,monospace}.semantic-result{white-space:pre-wrap;overflow:auto;max-height:420px;background:#0f172a;color:#e2e8f0;padding:16px;border-radius:9px;font:12px/1.6 Consolas,monospace}.semantic-note{color:#64748b;line-height:1.6}.semantic-actions{display:flex;align-items:center;gap:12px;margin:14px 0}@media(max-width:600px){.semantic-panel{padding:16px}.semantic-actions{align-items:stretch;flex-direction:column}}
    </style>
</head>
<body class="app-body">
<?php include __DIR__ . '/topbar.php'; ?>
<?php include __DIR__ . '/sideleftbar.php'; ?>
<main class="main-content">
    <div class="page-heading">
        <span class="eyebrow">WEB SEMANTIK</span>
        <h1>RDF, OWL &amp; SPARQL</h1>
        <p>Data akademik disediakan sebagai graf RDF dengan ontology OWL dan query SELECT yang mengikuti hak akses akun.</p>
    </div>
    <section class="dashboard-panel semantic-panel">
        <h2>Dataset RDF publik</h2>
        <p class="semantic-note">Ekspor hanya berisi ontology serta data institusi, fakultas, dan program studi. Nama dosen, NIDN, akun, kontak, dan data pribadi mahasiswa tidak ikut ke ekspor publik.</p>
        <div class="semantic-links">
            <a class="btn btn-outline" href="<?= htmlspecialchars($ontology_url, ENT_QUOTES, 'UTF-8') ?>">Ontology OWL/Turtle</a>
            <a class="btn btn-outline" href="<?= htmlspecialchars($rdf_url, ENT_QUOTES, 'UTF-8') ?>">Data RDF/Turtle</a>
            <a class="btn btn-outline" href="api/rdf.php?format=jsonld">Data JSON-LD</a>
            <a class="btn btn-outline" href="api/rdf.php?format=rdfxml">Data RDF/XML</a>
        </div>
        <p class="semantic-note">Query SPARQL memerlukan login. Admin, operator program studi, dekanat, dan rektorat hanya mendapat data dosen sesuai scope. Akun dosen hanya mendapat profil semantiknya sendiri; role lain tidak mendapat data pribadi mahasiswa.</p>
    </section>
    <section class="dashboard-panel semantic-panel">
        <h2>Jalankan query SPARQL</h2>
        <p class="semantic-note">Endpoint mendukung SELECT atas pola triple dasar, prefix, variabel, predikat <code>a</code>, pemisah titik koma/koma, DISTINCT, dan LIMIT. Query dibatasi maksimal 12 pola, 8 KB, dan 100 hasil. Query update, FILTER, OPTIONAL, dan SERVICE tidak didukung.</p>
        <form id="sparql-form">
            <label for="sparql-query">Query</label>
            <textarea id="sparql-query" class="semantic-query" name="query" spellcheck="false" required><?= htmlspecialchars($example_query, ENT_QUOTES, 'UTF-8') ?></textarea>
            <div class="semantic-actions">
                <button id="sparql-submit" class="btn btn-primary" type="submit">Jalankan SELECT</button>
                <span id="sparql-status" class="semantic-note" role="status"></span>
            </div>
        </form>
        <pre id="sparql-result" class="semantic-result" aria-live="polite">Hasil query akan muncul di sini.</pre>
    </section>
</main>
<script>
document.getElementById('sparql-form').addEventListener('submit', async function (event) {
    event.preventDefault();
    const status = document.getElementById('sparql-status');
    const result = document.getElementById('sparql-result');
    const button = document.getElementById('sparql-submit');
    button.disabled = true;
    status.textContent = 'Memproses query...';
    try {
        const response = await fetch('api/sparql.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/sparql-query'},
            body: document.getElementById('sparql-query').value,
            credentials: 'same-origin'
        });
        const body = await response.json();
        result.textContent = JSON.stringify(body, null, 2);
        status.textContent = response.ok ? 'Query selesai.' : 'Query gagal (' + response.status + ').';
    } catch (error) {
        result.textContent = 'Respons endpoint tidak dapat dibaca.';
        status.textContent = 'Koneksi ke endpoint gagal.';
    } finally {
        button.disabled = false;
    }
});
</script>
</body>
</html>
