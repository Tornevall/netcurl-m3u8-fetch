#!/usr/bin/php
<?php

use M3U8\FileHandler;
use M3U8\M3U8_Detect;

require_once(__DIR__ . '/vendor/autoload.php');

/*$argv[1] = '-vhttps://vod-akc-eu-north-1.media.dssott.com/ps01/disney/e92289f6-9625-4ea4-8d00-387e85c6006a/r/composite_4250k_CENC_CTR_FHD_SDR_6edf4b92-e075-4186-a8da-f40c6e426dc4_628a926b-a632-49ab-8447-fd608fa39549.m3u8';
$argv[2] = '-ahttps://vod-akc-eu-north-1.media.dssott.com/ps01/disney/e92289f6-9625-4ea4-8d00-387e85c6006a/r/composite_128k_mp4a.40.2_en_PRIMARY_56f3b816-1286-4666-b555-9994ef5fc6b7_628a926b-a632-49ab-8447-fd608fa39549.m3u8';
$argv[3] = '-k1655ce57ede64f64a2c9093fc15c7030:39c44c18aa83ea3261595b3021b4480f';
$argv[4] = '-sda:https://vod-akc-eu-north-1.media.dssott.com/ps01/disney/e92289f6-9625-4ea4-8d00-387e85c6006a/r/composite_da_NORMAL_69b29e9a-bb8f-46b6-9384-a5bc514f5c4c_628a926b-a632-49ab-8447-fd608fa39549.m3u8,en:https://vod-akc-eu-north-1.media.dssott.com/ps01/disney/e92289f6-9625-4ea4-8d00-387e85c6006a/r/composite_en_SDH_118baf67-c5c4-4604-ad04-d12d65c868f2_628a926b-a632-49ab-8447-fd608fa39549.m3u8,no:https://vod-akc-eu-north-1.media.dssott.com/ps01/disney/e92289f6-9625-4ea4-8d00-387e85c6006a/r/composite_no_NORMAL_044a1adc-4e59-4be1-ba9b-0806f0d95fa5_628a926b-a632-49ab-8447-fd608fa39549.m3u8,fi:https://vod-akc-eu-north-1.media.dssott.com/ps01/disney/e92289f6-9625-4ea4-8d00-387e85c6006a/r/composite_fi_NORMAL_54b8ddfa-596d-40a6-8283-74bd355bfe5b_628a926b-a632-49ab-8447-fd608fa39549.m3u8,sv:https://vod-akc-eu-north-1.media.dssott.com/ps01/disney/e92289f6-9625-4ea4-8d00-387e85c6006a/r/composite_sv_NORMAL_154542dd-8492-4f8d-a844-bafa8365beb4_628a926b-a632-49ab-8447-fd608fa39549.m3u8';
$argv[5] = '-ooutput.mp4';*/

//$argv[1] = '-ssv:https://vod-l3c-eu-north-1.media.dssott.com/ps01/disney/94c895e3-ef7f-4318-97b8-ae144c5b09d0/r/composite_sv_NORMAL_77ced72e-1769-4477-b58b-b82ac6898a13_b3cb20f4-3c0f-40b5-8ca4-3ac2dbff1ed1.m3u8,fi:https://vod-l3c-eu-north-1.media.dssott.com/ps01/disney/94c895e3-ef7f-4318-97b8-ae144c5b09d0/r/composite_fi_NORMAL_1524ebba-0b8d-4445-82f8-671a9a98937d_b3cb20f4-3c0f-40b5-8ca4-3ac2dbff1ed1.m3u8,no:https://vod-l3c-eu-north-1.media.dssott.com/ps01/disney/94c895e3-ef7f-4318-97b8-ae144c5b09d0/r/composite_no_NORMAL_75c4c284-8943-4024-89ec-f092642731ca_b3cb20f4-3c0f-40b5-8ca4-3ac2dbff1ed1.m3u8,en:https://vod-l3c-eu-north-1.media.dssott.com/ps01/disney/94c895e3-ef7f-4318-97b8-ae144c5b09d0/r/composite_en_SDH_0c9d9a47-cf45-47d8-991b-e3132541c517_b3cb20f4-3c0f-40b5-8ca4-3ac2dbff1ed1.m3u8,da:https://vod-l3c-eu-north-1.media.dssott.com/ps01/disney/94c895e3-ef7f-4318-97b8-ae144c5b09d0/r/composite_da_NORMAL_0bb1f9d5-e778-4174-bcd5-db589b74db64_b3cb20f4-3c0f-40b5-8ca4-3ac2dbff1ed1.m3u8';

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
try {
    //$downloader = (new M3U8_Detect($videoManifest))->getManifestClass();
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

$fileHandler = new FileHandler();
$fileHandler->setStoreDestination($generalStoreDestination);
$fileHandler->setOutPutName($outputName);
$fileHandler->setWideVineKeys($keys);
$fileHandler->exec();
