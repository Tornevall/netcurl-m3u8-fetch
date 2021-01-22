#!/usr/bin/php
<?php

use M3U8\FileHandler;
use M3U8\M3U8_Detect;

require_once(__DIR__ . '/vendor/autoload.php');

// Debugging.
//$mergeOnly = true;
$videoManifest = isset($argv[1]) ? $argv[1] : null;
$audioManifest = isset($argv[2]) ? $argv[2] : null;
$keys = isset($argv[3]) ? $argv[3] : '';
$outputName = isset($argv[4]) ? $argv[4] : '';
$subTitleManifest = isset($argv[4]) ? $argv[4] : null;

$downloader = (new M3U8_Detect($videoManifest))->getManifestClass();
if (get_class($downloader) === null) {
    die("Unsupported manifest name.\n");
}
$generalStoreDestination = __DIR__ . '/tmp';
if (!file_exists($generalStoreDestination)) {
    printf("%s not found. Creating.\n", $generalStoreDestination);
    mkdir($generalStoreDestination);
    if (!file_exists($generalStoreDestination)) {
        die(sprintf("Could not create %s.", $generalStoreDestination));
    }
}
$downloader->setStoreDestination($generalStoreDestination);

$hasArgs = false;
try {
    if (isset($argv) && is_array($argv) && count($argv)) {
        foreach ($argv as $arg) {
            if (substr($arg, 0, 1) === '-') {
                $hasArgs = true;
                $argKey = strtolower(substr($arg, 1, 1));
                $rest = preg_replace('/^=+|^-+/', '', substr($arg, 2));
                switch ($argKey) {
                    case 'v':
                        $downloader->setVideoManifest($rest);
                        break;
                    case 'k':
                        $keys = $rest;
                        break;
                    case 'a':
                        $downloader->setAudioManifest($rest);
                        break;
                    case 's':
                        $downloader->setSubtitleManifest($rest);
                        break;
                    case 'o':
                        $outputName = $rest;
                    default:
                }
            }
        }
    }
} catch (Exception $e) {
    die($e->getMessage() . "\n");
}

if (!$hasArgs) {
    if (!isset($argv[3])) {
        printf("Usage: %s <video-playlist-url> <audio-playlist-url> <KID:IV> <subtitleManifestsInCommas>\n", $argv[0]);
        printf("Usage: %s merge (= retry merging when the first download failed).\n", $argv[0]);
        echo "KID:IV = Widevine decoded AES key (kidhash:ivhash).\n";
        echo "\n";
        exit;
    }
}

/*
 * Subtitle EF BB BF - Manifest https://en.wikipedia.org/wiki/Byte_order_mark
 */

// We download and merge everything at once now.
if (!$mergeOnly) {
    try {
        if (!$hasArgs) {
            $downloader->setVideoManifest($videoManifest);
            $downloader->setAudioManifest($audioManifest);
            if (!empty($subTitleManifest)) {
                $downloader->setSubtitleManifest($subTitleManifest);
            }
        }
        $downloader->exec();
    } catch (Exception $e) {
        die($e->getMessage() . "\n");
    }
}

$fileHandler = new FileHandler();
$fileHandler->setStoreDestination($generalStoreDestination);
$fileHandler->setOutPutName($outputName);
$fileHandler->setWideVineKeys($keys);
$fileHandler->exec();
