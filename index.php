<?php
declare(strict_types=1);
session_start();

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$outputDir = __DIR__ . DIRECTORY_SEPARATOR . 'outputs';
foreach ([$uploadDir, $outputDir] as $dir) {
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
}
function cleanName(string $name): string {
    $name = basename($name);
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'uploaded_file';
}
function cleanDownloadName(string $name, string $fallback): string {
    $name = trim($name);
    $name = preg_replace('/[\\\\\/\\:\\*\\?"<>|]+/', '_', $name) ?? '';
    $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?? '';
    $name = trim($name, " .\\t\\n\\r\\0\\x0B");
    return $name !== '' ? $name : $fallback;
}
function compileC(): array {
    $src = __DIR__ . DIRECTORY_SEPARATOR . 'huffman.c';
    $exe = __DIR__ . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'huffman.exe' : 'huffman');
    $cmd = 'gcc ' . escapeshellarg($src) . ' -O2 -std=c11 -o ' . escapeshellarg($exe) . ' 2>&1';
    exec($cmd, $out, $code);
    return [$exe, $code, implode("\n", $out)];
}
function runC(string $exe, array $args): array {
    $parts = [escapeshellarg($exe)];
    foreach ($args as $arg) { $parts[] = escapeshellarg($arg); }
    exec(implode(' ', $parts) . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}
function jsonResponse(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['ok'=>false, 'error'=>'Please choose a file and try again.'], 400);
    }
    $originalName = cleanName($_FILES['file']['name']);
    if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
        jsonResponse(['ok'=>false, 'error'=>'Maximum upload size is 20 MB for this demo.'], 413);
    }
    $token = bin2hex(random_bytes(8));
    $inputPath = $uploadDir . DIRECTORY_SEPARATOR . $token . '_' . $originalName;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $inputPath)) {
        jsonResponse(['ok'=>false, 'error'=>'Could not save uploaded file. Check folder permissions.'], 500);
    }
    [$exe, $compileCode, $compileOutput] = compileC();
    if ($compileCode !== 0) {
        @unlink($inputPath);
        jsonResponse(['ok'=>false, 'error'=>"Could not compile huffman.c. Ensure GCC is installed and available to PHP.\n".$compileOutput], 500);
    }
    $action = (string)$_GET['action'];
    if ($action === 'analyze') {
        [$code, $output] = runC($exe, ['analyze', $inputPath]);
        @unlink($inputPath);
        if ($code !== 0) jsonResponse(['ok'=>false, 'error'=>'Analysis failed: '.$output], 500);
        $data = json_decode($output, true);
        if (!is_array($data)) jsonResponse(['ok'=>false, 'error'=>'C program returned invalid analysis data. '.$output], 500);
        $data['ok'] = true;
        $data['originalName'] = $originalName;
        jsonResponse($data);
    } elseif ($action === 'compress') {
        $requestedName = cleanDownloadName((string)($_POST['downloadName'] ?? ''), pathinfo($originalName, PATHINFO_FILENAME) . '.huff');
        if (!preg_match('/\\.huff$/i', $requestedName)) $requestedName .= '.huff';
        $outName = $token . '_' . cleanName($requestedName);
        $outPath = $outputDir . DIRECTORY_SEPARATOR . cleanName($outName);
        [$code, $output] = runC($exe, ['compress', $inputPath, $outPath]);
        @unlink($inputPath);
        if ($code !== 0 || !is_file($outPath)) jsonResponse(['ok'=>false, 'error'=>'Compression failed: '.$output], 500);
        $size = filesize($outPath);
        jsonResponse(['ok'=>true, 'download'=>'download.php?file='.rawurlencode(basename($outPath)).'&name='.rawurlencode($requestedName), 'name'=>$requestedName, 'compressedSize'=>$size, 'message'=>'Compression complete.']);
    } elseif ($action === 'decompress') {
        $outName = $token . '_restored_' . preg_replace('/\.huff$/i', '', $originalName);
        if ($outName === $token . '_restored_' . $originalName) $outName = $token . '_restored_' . $originalName . '.bin';
        $outPath = $outputDir . DIRECTORY_SEPARATOR . cleanName($outName);
        [$code, $output] = runC($exe, ['decompress', $inputPath, $outPath]);
        @unlink($inputPath);
        if ($code !== 0 || !is_file($outPath)) jsonResponse(['ok'=>false, 'error'=>'Decompression failed. Make sure this is a valid HuffZip .huff file. '.$output], 400);
        jsonResponse(['ok'=>true, 'download'=>'download.php?file='.rawurlencode(basename($outPath)).'&name='.rawurlencode($requestedName), 'name'=>$requestedName, 'message'=>'Decompression complete.']);
    }
    @unlink($inputPath);
    jsonResponse(['ok'=>false, 'error'=>'Unknown action.'], 400);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HuffZip — Huffman File Compression</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
  <header class="topbar">
    <a class="brand" href="#"><span class="brand-mark">H</span><span>Huff<span class="brand-accent">Zip</span><small>HUFFMAN FILE COMPRESSION</small></span></a>
    <div class="top-tag"><span class="status-dot"></span> NLDS PROJECT · C LANGUAGE</div>
  </header>

  <main>
    <section class="hero">
      <div class="hero-copy">
        <div class="eyebrow"><span></span> LOSSLESS COMPRESSION LAB</div>
        <h1>Make compression<br><em>make sense.</em></h1>
        <p>Explore how Huffman Coding transforms character frequencies into a binary tree, generates compact codes, and compresses your file — one step at a time.</p>
        <div class="hero-points"><span>✓ Visual algorithm walkthrough</span><span>✓ C-powered compression</span><span>✓ Lossless decompression</span></div>
      </div>
      <div class="hero-art" aria-hidden="true">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        <div class="tree-art">
          <div class="art-node root">10</div><div class="art-lines"><i></i><i></i></div>
          <div class="art-children"><div class="art-node leaf">D<span>4</span></div><div class="art-node mid">6</div></div>
          <div class="art-sub-lines"><i></i><i></i></div>
          <div class="art-last"><div class="art-node leaf">C<span>3</span></div><div class="art-node leaf">N1<span>3</span></div></div>
        </div><div class="floating-code">01011001</div><div class="floating-frequency">A · B · C · D</div>
      </div>
    </section>

    <section class="workspace">
      <div class="section-heading"><div><div class="eyebrow">01 / GET STARTED</div><h2>Choose your file</h2></div><span class="step-count">UPLOAD & ANALYZE</span></div>
      <div class="upload-panel">
        <label class="dropzone" id="dropzone" for="fileInput">
          <input id="fileInput" type="file">
          <span class="upload-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0L7 9m5-5 5 5M4 16v4h16v-4"/></svg></span>
          <strong id="fileTitle">Drop a file here, or <u>browse</u></strong>
          <span id="fileSub">Any file type · Maximum 20 MB</span>
          <span class="file-chip" id="fileChip" hidden></span>
        </label>
        <div class="download-name-panel">
          <label for="downloadName" class="download-name-label">
            <span class="download-name-icon">↓</span>
            <span><strong>Download file name</strong><small>Choose the name your downloaded file should have</small></span>
          </label>
          <div class="download-name-input-wrap">
            <input id="downloadName" type="text" maxlength="120" autocomplete="off" placeholder="e.g. my_compressed_file">
            <span class="name-extension" id="nameExtension">.huff</span>
          </div>
          <p class="download-name-hint" id="downloadNameHint">The .huff extension is added automatically when you compress.</p>
        </div>
        <div class="upload-actions">
          <button class="btn btn-primary" id="analyzeBtn" disabled><span>Analyze & Build Tree</span><b>→</b></button>
          <button class="btn btn-secondary" id="compressBtn" disabled>Compress & Download <span>↓</span></button>
          <button class="btn btn-quiet" id="decompressBtn" disabled>Decompress .huff</button>
        </div>
        <p class="microcopy">Your file is processed by the C program on this server. Demo limit: 20 MB.</p>
      </div>
    </section>

    <section class="results" id="results" hidden>
      <div class="section-heading"><div><div class="eyebrow">02 / FREQUENCY ANALYSIS</div><h2>Understand your data</h2></div><span class="step-count" id="totalChars">—</span></div>
      <div class="stats-grid">
        <div class="stat-card"><span>Unique byte values</span><strong id="uniqueCount">—</strong><small>Distinct symbols found</small></div>
        <div class="stat-card"><span>Total input bytes</span><strong id="totalBytes">—</strong><small>Before compression</small></div>
        <div class="stat-card"><span>Tree merge operations</span><strong id="mergeCount">—</strong><small>Minimum nodes combined</small></div>
      </div>
      <div class="content-card">
        <div class="card-heading"><div><span class="number-pill">A</span><div><h3>Character frequency table</h3><p>Every byte is counted before the tree is built.</p></div></div><span class="mini-label">FREQUENCY</span></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Symbol</th><th>Byte value</th><th>Frequency</th><th>Relative frequency</th></tr></thead><tbody id="frequencyBody"></tbody></table></div>
      </div>
      <div class="section-heading tree-section-heading"><div><div class="eyebrow">03 / BUILD THE TREE</div><h2>Watch the tree take shape</h2><p class="section-description">Move through the algorithm at your own pace. Each step combines the two available nodes with the lowest frequencies.</p></div></div>
      <div class="stepper-card">
        <div class="stepper-top"><div><span class="mini-label">CURRENT OPERATION</span><h3 id="stepTitle">Initial nodes</h3></div><span class="step-badge" id="stepBadge">START</span></div>
        <p id="stepDescription" class="step-description">All symbols begin as separate leaf nodes.</p>
        <div class="merge-equation" id="mergeEquation"><span class="eq-node">A · 1</span><span class="eq-plus">+</span><span class="eq-node">B · 2</span><span class="eq-equals">=</span><span class="eq-node eq-result">N1 · 3</span></div>
        <div class="stepper-controls"><button class="btn btn-secondary" id="prevBtn" disabled>← Previous</button><div class="progress-wrap"><div class="progress-track"><div id="progressFill"></div></div><span id="progressText">Step 0 of 0</span></div><button class="btn btn-primary" id="nextBtn">Next Step →</button><button class="btn btn-quiet" id="showAllBtn">Show final tree</button></div>
      </div>
      <div class="content-card tree-card">
        <div class="card-heading"><div><span class="number-pill">B</span><div><h3 id="treeTitle">Huffman tree</h3><p id="treeSubtitle">Nodes are connected according to the merge history.</p></div></div><span class="mini-label">TREE VIEW</span></div>
        <div class="tree-legend"><span><i class="legend-dot leaf-dot"></i> Character / leaf</span><span><i class="legend-dot internal-dot"></i> Combined node</span><span><b>0</b> left branch</span><span><b>1</b> right branch</span></div>
        <div id="treeCanvas" class="tree-canvas"><div class="tree-empty">Analyze a file to generate its Huffman tree.</div></div>
      </div>
      <div class="content-card">
        <div class="card-heading"><div><span class="number-pill">C</span><div><h3>Generated Huffman codes</h3><p>Each symbol receives a unique prefix-free binary code.</p></div></div><span class="mini-label">ENCODING TABLE</span></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Symbol</th><th>Frequency</th><th>Huffman code</th><th>Code length</th></tr></thead><tbody id="codesBody"></tbody></table></div>
      </div>
      <div class="success-note" id="successNote"><span>✓</span><div><strong>Tree construction complete</strong><p>Your codes are ready. You can now compress the original file.</p></div></div>
    </section>
    <section class="how-it-works">
      <div><div class="eyebrow">THE IDEA</div><h2>Simple rule.<br><em>Smarter codes.</em></h2></div>
      <div class="idea-grid"><div><span>01</span><h3>Count</h3><p>Count how often each byte appears in the file.</p></div><div><span>02</span><h3>Combine</h3><p>Join the two least-frequent nodes into a parent node.</p></div><div><span>03</span><h3>Encode</h3><p>Read 0s and 1s along each root-to-leaf path.</p></div></div>
    </section>
    <footer><a class="brand footer-brand" href="#"><span class="brand-mark">H</span><span>Huff<span class="brand-accent">Zip</span><small>NLDS · HUFFMAN CODING</small></span></a><span>Built for learning. Powered by C.</span></footer>
  </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="app.js"></script>
</body>
</html>
