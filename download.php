#!/usr/bin/php
<?php

require_once(__DIR__ . '/vendor/autoload.php');

if (!isset($argv[1])) {
    printf("Usage: %s <playlist>\n", $argv[0]);
    exit(1);
}

if (!file_exists('tmp')) {
    mkdir('tmp');
}

$download = new Playlist\Download();
$download->setStoreDestination(__DIR__ . '/tmp');
// Instead of injecting separate manifests, we now push the major collector instead.
$download->getDetectedManifest($argv[1]);
//$download->setManifest($argv[1]);
$download->exec();
