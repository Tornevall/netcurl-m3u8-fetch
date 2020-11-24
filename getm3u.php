#!/usr/bin/php
<?php

use TorneLIB\Module\Network\Wrappers\CurlWrapper;

$mpDecryptBinary = '/usr/local/mp4decrypt/bin/mp4decrypt';
$ffmpeg = '/usr/bin/ffmpeg';
$manifest = isset($argv[1]) ? $argv[1] : null;
$key = isset($argv[2]) ? $argv[2] : null;
$extra = isset($argv[3]) ? $argv[3] : null;

/**
 * Scan for files to handle.
 * @param false $cleanUp
 * @param false $decode
 */
function scanFiles($cleanUp = false, $decode = false)
{
    global $keyData;
    $filelist = scandir(__DIR__);
    $decoded = [];
    $encoded = [];
    $finals = [];
    foreach ($filelist as $file) {
        if (preg_match('/mp4$/', $file)) {
            if (preg_match('/decode/i', $file)) {
                $decoded[] = $file;
            }
            if (preg_match('/encode/i', $file)) {
                $encoded[] = $file;
            }
            if (preg_match('/final/i', $file)) {
                $finals[] = $file;
            }
        }
    }
    if ($cleanUp) {
        $cleanArray = array_merge($decoded, $encoded, $finals);
        foreach ($cleanArray as $file) {
            echo "Clean up: $file\n";
            unlink($file);
        }
    }
    if ($decode) {
        foreach ($encoded as $file) {
            $decodeThis = preg_replace('/.mp4$/', '', $file) . ".mp4";
            $decodeAs = preg_replace('/encoded/', 'decoded', $decodeThis);
            $finalize = preg_replace('/decoded/', 'final', $decodeAs);
            printf("Decode %s as %s ...\n", $decodeThis, $decodeAs);
            decryptFile($decodeThis, $decodeAs, $finalize);
        }
    }
}

/**
 * mp4decrypt + finalize with ffmpeg.
 * @param $encodedFile
 * @param $decodedFile
 * @param $finalize
 */
function decryptFile($encodedFile, $decodedFile, $finalize)
{
    global $mpDecryptBinary, $keyData, $ffmpeg;
    $decodeApplication = sprintf("%s %s %s %s", $mpDecryptBinary, trim($keyData), $encodedFile, $decodedFile);
    echo $decodeApplication . "\n";
    system($decodeApplication);

    $repairApplication = sprintf("%s -i %s %s", $ffmpeg, $decodedFile, $finalize);
    echo $repairApplication . "\n";
    system($repairApplication);
}

// ./getm3u.php https://manifest.m3u8 KID:IV
if (empty($key)) {
    printf("Usage: %s <m3u8-manifest-or-url> <encryptionKID>:<encryptionIV>\n", $argv[0]);
    die;
}

scanFiles(true);

require_once(__DIR__ . '/vendor/autoload.php');
$nw = new CurlWrapper();
$keyArray = explode(':', $key);
$keyData = sprintf('--key %s', $key);
$splitKey = false;
if ($splitKey && is_array($keyArray)) {
    $keyData = '';
    foreach ($keyArray as $keyCount => $key) {
        $keyData .= sprintf('--key %d:%s ', ($keyCount + 1), $key);
    }
}

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

printf("File will be saved as '%s'. Key data is %s.\n", $saveAs, trim($keyData));

$count = 0;
$map = '';
$segmentCount = 0;
foreach ($content as $row) {
    if (!preg_match('/^#/', $row)) {
        $segmentCount++;
    }
}
$segmentCalc = str_pad($segmentCount, 6, '0', STR_PAD_LEFT);
$hasDisco = false;

$useEnc = "encoded0.mp4";
$part = 0;
foreach ($content as $row) {
    if (preg_match('/^#/', $row) && preg_match('/MAP:URI/i', $row)) {
        $map = preg_replace('/(.*?)\"(.*?)\"(.*?)$/', '$2', $row);
        $getFrom = sprintf('%s/%s', $basePath, $map);
        $mapData = $nw->request($getFrom)->getBody();
        echo "Fetched new mapdata...\n";
        file_put_contents($useEnc, $mapData, FILE_APPEND | FILE_BINARY);

        // Alternative.
        //system(sprintf("curl -sS %s >>encoded.mp4", $getFrom));
    } elseif (preg_match('/DISCONTINUITY/', $row) && !$hasDisco) {
        $hasDisco = true;
        $part++;
        echo "==== NEXT STREAM START ====\n";
        $useEnc = sprintf('encoded%d.mp4', $part);
    } elseif (!preg_match('/^#/', $row)) {
        $count++;
        $getFrom = sprintf('%s/%s', $basePath, $row);
        $getFromData = explode('/', $getFrom);
        $file = $getFromData[count($getFromData) - 1];
        $saveTo = str_pad($count, 6, '0', STR_PAD_LEFT);
        printf("%s/%s: %s (%s)...\n", $saveTo, $segmentCalc, $file, $useEnc);
        $mp4Content = $nw->request($getFrom)->getBody();
        file_put_contents($useEnc, $mp4Content, FILE_APPEND | FILE_BINARY);
        // Apply mapdata only once per found map-segment.
        $mapData = null;

        // Alternative.
        //system(sprintf("curl -sS %s >>encoded.mp4", $getFrom));
    }
}

scanFiles(false, true);
