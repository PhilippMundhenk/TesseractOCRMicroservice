<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$uploaddir = '/var/www/html/uploads/';
$basename = basename($_FILES['userfile']['name']);
$uuid = uniqid();
$uploadfile = $uploaddir . $basename . $uuid;

if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
} else {
	http_response_code(500);
	die("Error! Internal Server Error");
}

$output = shell_exec('convert -density 300 '.$uploadfile.' -depth 8 '.$uploaddir.'/'.$uuid.'.tiff');
$output = shell_exec('cd '.$uploaddir.' && tesseract '.$uuid.'.tiff '.$uuid.'output -l deu pdf hocr');

unlink($uploadfile);
$attachment_location = $uploaddir.$uuid.'output.pdf';
if (file_exists($attachment_location)) {
        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
        header("Cache-Control: public"); // needed for internet explorer
        header("Content-Type: application/zip");
        header("Content-Transfer-Encoding: Binary");
        header("Content-Length:".filesize($attachment_location));
	header("Content-Disposition: attachment; filename=".$basename."-ocr.pdf");
        readfile($attachment_location);
        die();
} else {
        die("Error: File not found.");
}
?>
