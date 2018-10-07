<?php
// In PHP kleiner als 4.1.0 sollten Sie $HTTP_POST_FILES anstatt
// $_FILES verwenden.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$uploaddir = '/var/www/html/uploads/';
$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
#    echo "Datei ist valide und wurde erfolgreich hochgeladen.\n";
} else {
#    echo "Möglicherweise eine Dateiupload-Attacke!\n";
}

#echo 'Weitere Debugging Informationen:';
#print_r($_FILES);

#print "</pre>";

#convert -density 300 Mietvertrag.pdf -depth 8 file.tiff
#tesseract file.tiff output -l deu pdf hocr

$output = shell_exec('convert -density 300 '.$uploadfile.' -depth 8 '.$uploaddir.'/file.tiff');
$output = shell_exec('cd '.$uploaddir.' && tesseract file.tiff output -l deu pdf hocr');

$attachment_location = $uploaddir.'output.pdf';
       if (file_exists($attachment_location)) {
           header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
           header("Cache-Control: public"); // needed for internet explorer
           header("Content-Type: application/zip");
           header("Content-Transfer-Encoding: Binary");
           header("Content-Length:".filesize($attachment_location));
           header("Content-Disposition: attachment; filename=output.pdf");
           readfile($attachment_location);
           die();
       } else {
           die("Error: File not found.");
       }

?>
