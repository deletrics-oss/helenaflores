<?php
$file = 'modeloplanilhasshopeemercadolivre/Shopee_mass_upload_2026-02-05_basic_template.xlsx';
$zip = new ZipArchive();
if ($zip->open($file) === TRUE) {
    echo "Workbook XML:\n";
    $wbXml = $zip->getFromName('xl/workbook.xml');
    echo $wbXml . "\n\n";

    echo "Relationships:\n";
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    echo $relsXml . "\n";
    $zip->close();
} else {
    echo "Failed to open zip.";
}
?>