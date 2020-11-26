#!/usr/bin/php
<?php

use M3U8\Download;
use M3U8\FileHandler;

require_once(__DIR__ . '/vendor/autoload.php');

if (!isset($argv[3])) {
    printf("Usage: %s <video-playlist-url> <audio-playlist-url> <KID:IV>\n", $argv[0]);
    echo "KID:IV = Widevine decoded AES key (kidhash:ivhash).\n";
    echo "\n";
    exit;
}

$generalStoreDestination = __DIR__ . '/tmp';

$downloader = new Download();
$downloader
    ->setVideoManifest($argv[1])
    ->setAudioManifest($argv[2])
    ->setStoreDestination($generalStoreDestination)
    ->exec();

$fileHandler = new FileHandler();
$fileHandler
    ->setStoreDestination($generalStoreDestination)
    ->setWideVineKeys($argv[3])
    ->exec();
