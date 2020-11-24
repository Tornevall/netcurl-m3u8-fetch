#!/usr/bin/php
<?php

use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Module\Network\NetWrapper;

$mpDecryptBinary = '/usr/local/mp4decrypt/bin/mp4decrypt';
$ffmpeg = '/usr/bin/ffmpeg';

require_once(__DIR__ . '/vendor/autoload.php');
$nw = new NetWrapper();
if (!isset($argv[2])) {
    printf("Usage: %s <m3u8-manifest-or-url> <encryptionKID>:<encryptionIV>\n", $argv[0]);
    die;
}
$manifest = $argv[1];
$key = $argv[2];
$keyData = sprintf('--key %s', $key);
if (preg_match('/^http/i', $manifest)) {
    $uData = explode("/", $manifest);
    $uData = array_reverse($uData);
    $urlPart = preg_replace('/.m3u8$/', '', array_shift($uData));
    $basePath = implode("/", array_reverse($uData));
    $saveAs = 'final';
    $content = explode("\n", $nw->request($manifest)->getBody());
} elseif (file_exists($manifest)) {
    $saveAs = 'merge_m3u8';
    $content = explode(
        "\n",
        file_get_contents($manifest)
    );
}

/*$keyData = sprintf('--key 1:%s', $key);
$moreKeys = explode(":", $key);
if (count($moreKeys)) {
    $keyCount = 0;
    $keyData = '';
    foreach ($moreKeys as $key) {
        $keyCount++;
        $keyData .= sprintf('--key %s:%s ', $keyCount, $key);
    }
}*/

$cleanFirst = [
    sprintf('%s.mp4', $saveAs),
    'decoded.mp4',
    'encoded.mp4',
];

printf("File will be saved as '%s'. Key data is %s.\n", $saveAs, $keyData);

foreach ($cleanFirst as $file) {
    if (file_exists($file)) {
        printf("Cleanup: %s\n", $file);
        unlink($file);
    }
}

$count = 0;
$map = '';
$segmentCount = 0;
foreach ($content as $row) {
    if (!preg_match('/^#/', $row)) {
        $segmentCount++;
    }
}
$segmentCalc = str_pad($segmentCount, 6, '0', STR_PAD_LEFT);
foreach ($content as $row) {
    if (preg_match('/^#/', $row) && preg_match('/MAP:URI/i', $row)) {
        $map = preg_replace('/(.*?)\"(.*?)\"(.*?)$/', '$2', $row);
        $getFrom = sprintf('%s/%s', $basePath, $map);
        $mapData = $nw->request($getFrom, null, requestMethod::METHOD_GET, dataType::NORMAL)->getBody();
        echo "Fetched new mapdata...\n";
    }
    if (!preg_match('/^#/', $row)) {
        $count++;
        $getFrom = sprintf('%s/%s', $basePath, $row);
        $getFromData = explode('/', $getFrom);
        $file = $getFromData[count($getFromData) - 1];
        $saveTo = str_pad($count, 6, '0', STR_PAD_LEFT);
        printf("%s/%s: %s ...\n", $saveTo, $segmentCalc, $file);
        $mp4Content = $nw->request($getFrom, null, requestMethod::METHOD_GET, dataType::NORMAL)->getBody();
        file_put_contents("encoded.mp4", $mapData . $mp4Content, FILE_APPEND);
        $mapData = '';
    }
}

$decodeApplication = sprintf("%s encoded.mp4 decoded.mp4 %s", $mpDecryptBinary, trim($keyData));
echo $decodeApplication . "\n";
system($decodeApplication);

$resultFile = sprintf('%s.mp4', $saveAs);
if (file_exists($resultFile)) {
    // Prepare to write a new.
    unlink($resultFile);
}
//$repairApplication = sprintf("%s -err_detect ignore_err -vcodec mpeg4 -i decoded.mp4 -c copy %s.mp4", $ffmpeg, $saveAs, $resultFile);
$repairApplication = sprintf("%s -i decoded.mp4 -c copy %s.mp4", $ffmpeg, $saveAs, $resultFile);
echo $repairApplication . "\n";
system($repairApplication);

foreach (['encoded.mp4', 'decoded.mp4'] as $file) {
    if (file_exists($file)) {
        printf("Cleanup: %s\n", $file);
        unlink($file);
    }
}
