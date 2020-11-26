#!/usr/bin/php
<?php

use M3U8\Download;
use M3U8\FileHandler;

require_once(__DIR__ . '/vendor/autoload.php');

$testVid = 'https://vod-akc-eu-north-1.media.dssott.com/ps01/disney/11b54ab5-ca04-4d2e-b58c-ce6641d36e72/r/composite_4250k_CENC_CTR_FHD_SDR_3c3b6f55-093e-4c84-8a81-24a784e895d1_8a6e127c-46ab-4ac4-b3ec-2cc765db8a8d.m3u8';
$testAud = 'https://vod-llc-eu-north-1.media.dssott.com/ps01/disney/11b54ab5-ca04-4d2e-b58c-ce6641d36e72/r/composite_128k_mp4a.40.2_en_PRIMARY_2ec019b5-0677-4df3-b26c-19c9f6457b34_8a6e127c-46ab-4ac4-b3ec-2cc765db8a8d.m3u8';
$argv[1] = $testVid;
$argv[2] = $testAud;
$argv[3] = "318e658a3fa2461ebf96bb7f2d5d21d9:df2eac0ce6f7f886e397cf03358d970d";

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
