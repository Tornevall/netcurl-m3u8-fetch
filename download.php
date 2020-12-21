#!/usr/bin/php
<?php

use M3U8\Download;
use M3U8\FileHandler;

require_once(__DIR__ . '/vendor/autoload.php');

$outputName = isset($argv[4]) ? $argv[4] : '';
$mergeOnly = false;
if ((isset($argv[1]) && $argv[1] === 'merge')) {
    $mergeOnly = true;
    $outputName = isset($argv[2]) ? $argv[2] : '';
}
$keys = isset($argv[3]) ? $argv[3] : '';

if (!isset($argv[3]) && !$mergeOnly) {
    printf("Usage: %s <video-playlist-url> <audio-playlist-url> <KID:IV>\n", $argv[0]);
    printf("Usage: %s merge (= retry merging when the first download failed).\n", $argv[0]);
    echo "KID:IV = Widevine decoded AES key (kidhash:ivhash).\n";
    echo "\n";
    exit;
}
$generalStoreDestination = __DIR__ . '/tmp';

// Skip download again with arg4.
if (!$mergeOnly) {
    $downloader = new Download();
    $downloader
        ->setVideoManifest($argv[1])
        ->setAudioManifest($argv[2])
        ->setStoreDestination($generalStoreDestination)
        ->exec();
    sleep(2);
}

$fileHandler = new FileHandler();
$fileHandler->setStoreDestination($generalStoreDestination);
$fileHandler->setOutPutName($outputName);

if ($mergeOnly) {
    $fileHandler->exec();
} else {
    $fileHandler->setWideVineKeys($keys);
    $fileHandler->exec();
}
