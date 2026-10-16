<?php
declare(strict_types=1);
$name = isset($_GET['file']) ? basename((string)$_GET['file']) : '';
if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
    http_response_code(400); exit('Invalid download request.');
}
$path = __DIR__ . DIRECTORY_SEPARATOR . 'outputs' . DIRECTORY_SEPARATOR . $name;
if (!is_file($path)) { http_response_code(404); exit('File not found. Please run the operation again.'); }
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($path));
$downloadName = isset($_GET['name']) ? basename((string)$_GET['name']) : $name;
if ($downloadName === '' || !preg_match('/^[A-Za-z0-9._ -]+$/', $downloadName)) {
    $downloadName = $name;
}
header('Content-Disposition: attachment; filename="' . addcslashes($downloadName, '"\\') . '"');
readfile($path);
exit;
