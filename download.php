#!/usr/bin/php
<?php

require_once(__DIR__ . '/vendor/autoload.php');

if (!isset($argv[1])) {
    printf("Usage: %s <playlist>\n", $argv[0]);
    exit(1);
}
$download = new Playlist\Download();
$download->setStoreDestination(__DIR__ . '/tmp');
$download->setManifest($argv[1]);
$download->exec();
