<?php
// Tesseract OCR microservice: POST a PDF as "userfile", get the same PDF back
// with an invisible text layer added.
//
// The OCR is done by ocrmypdf, which keeps the original page images and only
// adds the recognised text. The earlier implementation rasterised every page
// to a 300 dpi TIFF and let tesseract embed that image again as JPEG, which
// made even blank pages several megabytes and threw the original images away.
//
// Optional form fields:
//   lang   tesseract language(s), e.g. "deu", "deu+eng" (default: OCR_LANGUAGE env or "deu")
//
// Responses: 200 with the PDF, 400 for a bad request, 500 when OCR failed
// (never 200 without a valid PDF, so callers can trust the status code).

ini_set('display_errors', 0);
error_reporting(E_ALL);

function fail($code, $message) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Error: POST a PDF as "userfile".');
}
if (!isset($_FILES['userfile']) || $_FILES['userfile']['error'] !== UPLOAD_ERR_OK) {
    $reason = isset($_FILES['userfile']) ? 'upload error ' . $_FILES['userfile']['error'] : 'no "userfile" field';
    fail(400, "Error: $reason.");
}

$language = getenv('OCR_LANGUAGE') ?: 'deu';
if (isset($_POST['lang']) && $_POST['lang'] !== '') {
    if (!preg_match('/^[a-z_]{3,}(\+[a-z_]{3,})*$/', $_POST['lang'])) {
        fail(400, 'Error: invalid "lang" (use tesseract codes such as "deu" or "deu+eng").');
    }
    $language = $_POST['lang'];
}

$workdir = '/var/www/html/uploads/';
$uuid = uniqid('', true);
$basename = basename($_FILES['userfile']['name']);
$input = $workdir . $uuid . '-input.pdf';
$output = $workdir . $uuid . '-output.pdf';

if (!move_uploaded_file($_FILES['userfile']['tmp_name'], $input)) {
    fail(500, 'Error: could not store the upload.');
}

$head = file_get_contents($input, false, null, 0, 5);
if ($head !== '%PDF-') {
    unlink($input);
    fail(400, 'Error: "userfile" is not a PDF.');
}

// --skip-text: pages that already carry text are passed through unchanged.
// --output-type pdf: keep the input's structure (no PDF/A conversion, which
// would re-encode the images).
$command = sprintf(
    'ocrmypdf --skip-text --output-type pdf --language %s --jobs %d %s %s 2>&1',
    escapeshellarg($language),
    max(1, (int)(getenv('OCR_JOBS') ?: 2)),
    escapeshellarg($input),
    escapeshellarg($output)
);
exec($command, $lines, $status);
unlink($input);

if ($status !== 0 || !is_file($output) || filesize($output) === 0) {
    if (is_file($output)) {
        unlink($output);
    }
    $log = implode("\n", array_slice($lines, -20));
    error_log("ocrmypdf failed (status $status) for $basename: $log");
    fail(500, "Error: OCR failed (ocrmypdf exit status $status).\n$log");
}

header('HTTP/1.1 200 OK');
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($output));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $basename) . '-ocr.pdf"');
readfile($output);
unlink($output);
?>
