<?php

use TorneLIB\Model\Type\dataType;
use TorneLIB\Model\Type\requestMethod;
use TorneLIB\Module\Network\NetWrapper;

require_once(__DIR__ . '/vendor/autoload.php');
$nw = new NetWrapper();
$enc = new TorneLIB\Data\Aes();

$url = 'https://vod-akc-eu-north-1.media.dssott.com/ps01/disney/11b54ab5-ca04-4d2e-b58c-ce6641d36e72/r/composite_4250k_CENC_CTR_FHD_SDR_3c3b6f55-093e-4c84-8a81-24a784e895d1_8a6e127c-46ab-4ac4-b3ec-2cc765db8a8d.m3u8';

$uData = explode("/", $url);
$uData = array_reverse($uData);
array_shift($uData);
$basePath = implode("/", array_reverse($uData));

$content = explode(
    "\n",
    file_get_contents('composite_4250k_CENC_CTR_FHD_SDR_3c3b6f55-093e-4c84-8a81-24a784e895d1_8a6e127c-46ab-4ac4-b3ec-2cc765db8a8d.m3u8')
);

$enc->setAesKeys(
    'df2eac0ce6f7f886e397cf03358d970d',
    '318e658a3fa2461ebf96bb7f2d5d21d9'
);

$enc->setCipher('aes-128-ctr');

$count = 0;
$map = '';
foreach ($content as $row) {
    if (preg_match('/^#/', $row) && preg_match('/MAP:URI/i', $row)) {
        $map = preg_replace('/(.*?)\"(.*?)\"(.*?)$/', '$2', $row);
        $getFrom = sprintf('%s/%s', $basePath, $map);
        $mapData = $nw->request($getFrom, null, requestMethod::METHOD_GET, dataType::NORMAL)->getBody();
    }
    if (!preg_match('/^#/', $row)) {
        $count++;
        $getFrom = sprintf('%s/%s', $basePath, $row);
        $getFromData = explode('/', $getFrom);
        $file = $getFromData[count($getFromData) - 1];
        $saveTo = str_pad($count, 6, '0', STR_PAD_LEFT);
        printf("%s: %s\n", $saveTo, $file);
        $mp4 = $nw->request($getFrom, null, requestMethod::METHOD_GET, dataType::NORMAL)->getBody();
        $data = $enc->aesDecrypt($mp4, false);
        $currentName = sprintf('%s/%s', 'mp4', $file);
        // Not decrypted.
        //file_put_contents($currentName, $mp4);
        // Decrypted
        file_put_contents($currentName, $mapData . $data);

        // Saving parts as they arrive.
        //file_put_contents(sprintf('%s/%s.mp4', 'mp4', $saveTo), $mp4);
        // Merging parts as they arrive.
        //file_put_contents('mp4/merge.mp4', $data, FILE_APPEND);

        // Saving parts with proper names.
        /*
        $exec = sprintf(
            '%s --key %s:%s %s %s.out',
            '/home/thorne/viktigt/Dropbox/mkv/mp4dec/bin/mp4decrypt',
            '318e658a3fa2461ebf96bb7f2d5d21d9',
            'df2eac0ce6f7f886e397cf03358d970d',
            $currentName,
            $currentName
        );
        echo $exec . "\n";
        system($exec);*/
    }
}

//print_r($basePath);
