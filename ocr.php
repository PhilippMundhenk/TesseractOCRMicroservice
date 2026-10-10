<?php
// Tesseract OCR microservice: POST a PDF as "userfile", get the same PDF back
// with an invisible text layer added.
//
// Pipeline:
//   1. optional re-render: pages are rendered to JPEG at OCR_RENDER_DPI (default
//      300, 0 = keep the original images) and wrapped back into a PDF. This is
//      what keeps OCR'd scans small: a lossless 300 dpi colour page is about
//      4 MB, the same page as JPEG a few hundred KB. Pages that are already
//      1-bit black and white are kept as they are (JPEG would only make them
//      bigger), and the render never uses more pixels than the page has.
//   2. ocrmypdf adds the text layer and keeps the page images.
//
// Optional form fields:
//   lang   tesseract language(s), e.g. "deu", "deu+eng" (default: OCR_LANGUAGE env or "deu")
//
// Responses: 200 with the PDF, 400 for a bad request, 500 when OCR failed
// (never 200 without a valid PDF, so callers can trust the status code).

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Settings come from the container environment via /etc/ocr-env.php, written
// by start.sh (lighttpd does not pass the environment to php-cgi). A real
// environment variable, if present, still wins.
$OCR_SETTINGS = @include '/etc/ocr-env.php';
if (!is_array($OCR_SETTINGS)) {
    $OCR_SETTINGS = array();
}
function setting($name, $default) {
    global $OCR_SETTINGS;
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = isset($OCR_SETTINGS[$name]) ? $OCR_SETTINGS[$name] : '';
    }
    return $value === '' ? $default : $value;
}

function fail($code, $message) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function cleanup($paths) {
    foreach ($paths as $path) {
        foreach (glob($path) ?: array() as $file) {
            @unlink($file);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Error: POST a PDF as "userfile".');
}
if (!isset($_FILES['userfile']) || $_FILES['userfile']['error'] !== UPLOAD_ERR_OK) {
    $reason = isset($_FILES['userfile']) ? 'upload error ' . $_FILES['userfile']['error'] : 'no "userfile" field';
    fail(400, "Error: $reason.");
}

$language = setting('OCR_LANGUAGE', 'deu');
if (isset($_POST['lang']) && $_POST['lang'] !== '') {
    if (!preg_match('/^[a-z_]{3,}(\+[a-z_]{3,})*$/', $_POST['lang'])) {
        fail(400, 'Error: invalid "lang" (use tesseract codes such as "deu" or "deu+eng").');
    }
    $language = $_POST['lang'];
}
$renderDpi = max(0, (int)setting('OCR_RENDER_DPI', '300'));
$jpegQuality = min(100, max(1, (int)setting('OCR_JPEG_QUALITY', '85')));
$jobs = max(1, (int)setting('OCR_JOBS', '2'));

$workdir = '/var/www/html/uploads/';
$uuid = uniqid('', true);
$basename = basename($_FILES['userfile']['name']);
$input = $workdir . $uuid . '-input.pdf';
$rendered = $workdir . $uuid . '-rendered.pdf';
$output = $workdir . $uuid . '-output.pdf';
$pageGlob = $workdir . $uuid . '-page-*.jpg';
$temp = array($input, $rendered, $output, $pageGlob);

if (!move_uploaded_file($_FILES['userfile']['tmp_name'], $input)) {
    fail(500, 'Error: could not store the upload.');
}

$head = file_get_contents($input, false, null, 0, 5);
if ($head !== '%PDF-') {
    cleanup($temp);
    fail(400, 'Error: "userfile" is not a PDF.');
}

$ocrInput = $input;
$renderNote = 'original images kept';

if ($renderDpi > 0) {
    // Look at the page images: colour or gray decides the JPEG device, 1-bit
    // pages are left alone, and the native resolution caps the render dpi so a
    // page that is mis-sized (72 dpi metadata) is not blown up 4x.
    exec('pdfimages -list ' . escapeshellarg($input) . ' 2>/dev/null', $imageLines);
    $color = false;
    $mono = false;
    $nativeDpi = $renderDpi;
    $seen = 0;
    foreach (array_slice($imageLines, 2) as $line) {
        $cols = preg_split('/\s+/', trim($line));
        if (count($cols) < 13 || !is_numeric($cols[0])) {
            continue;
        }
        $seen++;
        if ($cols[5] === 'rgb' || $cols[5] === 'cmyk') {
            $color = true;
        }
        if ($cols[8] === '1' || $cols[5] === 'mono') {
            $mono = true;
        }
        if (is_numeric($cols[12]) && (int)$cols[12] > 0) {
            $nativeDpi = min($nativeDpi, (int)$cols[12]);
        }
    }

    if ($seen > 0 && !$mono) {
        $device = $color ? 'jpeg' : 'jpeggray';
        $dpi = max(50, $nativeDpi);
        $pagePattern = $workdir . $uuid . '-page-%04d.jpg';
        $render = sprintf('gs -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=%s -r%d -dJPEGQ=%d -o %s %s 2>&1',
            $device, $dpi, $jpegQuality, escapeshellarg($pagePattern), escapeshellarg($input));
        exec($render, $renderLines, $renderStatus);
        $pages = glob($pageGlob);
        sort($pages);
        if ($renderStatus === 0 && count($pages) > 0) {
            $wrap = 'img2pdf -o ' . escapeshellarg($rendered);
            foreach ($pages as $page) {
                $wrap .= ' ' . escapeshellarg($page);
            }
            exec($wrap . ' 2>&1', $wrapLines, $wrapStatus);
            cleanup(array($pageGlob));
            if ($wrapStatus === 0 && is_file($rendered) && filesize($rendered) > 0) {
                $ocrInput = $rendered;
                $renderNote = "rendered as $device at $dpi dpi, JPEG quality $jpegQuality";
            } else {
                error_log("img2pdf failed for $basename, keeping original images: " . implode("\n", $wrapLines));
            }
        } else {
            error_log("gs render failed for $basename (status $renderStatus), keeping original images: " . implode("\n", $renderLines));
            cleanup(array($pageGlob));
        }
    } elseif ($mono) {
        $renderNote = '1-bit pages kept as they are';
    }
}

// --skip-text: pages that already carry text are passed through unchanged.
// --output-type pdf: keep the input's structure (no PDF/A conversion).
$command = sprintf(
    'ocrmypdf --skip-text --output-type pdf --language %s --jobs %d %s %s 2>&1',
    escapeshellarg($language),
    $jobs,
    escapeshellarg($ocrInput),
    escapeshellarg($output)
);
exec($command, $lines, $status);

if ($status !== 0 || !is_file($output) || filesize($output) === 0) {
    $log = implode("\n", array_slice($lines, -20));
    error_log("ocrmypdf failed (status $status) for $basename: $log");
    cleanup($temp);
    fail(500, "Error: OCR failed (ocrmypdf exit status $status).\n$log");
}

error_log("OCR done for $basename: $renderNote, " . filesize($input) . ' -> ' . filesize($output) . ' bytes');

header('HTTP/1.1 200 OK');
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($output));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $basename) . '-ocr.pdf"');
header('X-OCR-Render: ' . $renderNote);
readfile($output);
cleanup($temp);
?>
