<?php

namespace M3U8;

use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Module\Network\Wrappers\CurlWrapper;

class Download
{
    private $wrapper;

    /**
     * User-Agent for 1080p.
     * @var string
     */
    private $browser1080 = 'Mozilla/5.0 (Web0S; Linux/SmartTV) AppleWebKit/538.2 (KHTML, like Gecko) Large Screen ' .
    'Safari/538.2 LG Browser/7.00.00(LGE; 24LF4820-BU; 03.20.14; 1; DTV_W15L); webOS.TV-2015; LG NetCast.TV-2013 ' .
    'Compatible (LGE, 24LF4820-BU, wireless)';

    /**
     * @var int
     */
    private $videoSegmentCount = 0;

    /**
     * @var int
     */
    private $audioSegmentCount = 0;

    /**
     * @var string
     */
    private $videoManifest;

    /**
     * @var string
     */
    private $audioManifest;

    /**
     * @var array Arrayed playlist for video.
     */
    private $videoManifestContent = [];

    /**
     * @var array Arrayed playlist for audio.
     */
    private $audioManifestContent = [];

    /**
     * @var string Storage for mp4/m4a data.
     */
    private $storeDestination = __DIR__ . '/tmp';

    /**
     * @var string Root content url (for m3u8 without full urls) - audio.
     */
    private $audioBaseUrl;

    /**
     * @var string Root content url (for m3u8 without full urls) - video.
     */
    private $videoBaseUrl;

    /**
     * Download constructor.
     */
    public function __construct()
    {
        $this->wrapper = new CurlWrapper();
        $config = new WrapperConfig();
        $config->setUserAgent($this->browser1080);
        $this->wrapper->setConfig($config);
    }

    /**
     * @param $manifestUrl
     * @return $this
     */
    public function setVideoManifest($manifestUrl)
    {
        $this->videoManifest = $manifestUrl;
        return $this;
    }

    /**
     * @param $manifestUrl
     * @return $this
     */
    public function setAudioManifest($manifestUrl)
    {
        $this->audioManifest = $manifestUrl;
        return $this;
    }

    /**
     * @return $this
     * @throws ExceptionHandler
     */
    public function exec()
    {
        $this->cleanupDestination();

        if (!empty($this->videoManifest)) {
            $this->videoManifestContent = $this->getPlaylistManifest($this->videoManifest);
            $this->videoSegmentCount = $this->getSegmentCount($this->videoManifestContent);
            $this->videoBaseUrl = $this->getBaseUrl($this->videoManifest);
            echo "=== VIDEO SEGMENT REQUEST ===\n";
            $this->getMergedSegments($this->videoManifestContent, 'encvid', $this->videoBaseUrl);
        }
        if (!empty($this->audioManifest)) {
            $this->audioManifestContent = $this->getPlaylistManifest($this->audioManifest);
            $this->audioSegmentCount = $this->getSegmentCount($this->audioManifestContent);
            $this->audioBaseUrl = $this->getBaseUrl($this->audioManifest);
            echo "=== AUDIO SEGMENT REQUEST ===\n";
            $this->getMergedSegments($this->audioManifestContent, 'audio', $this->audioBaseUrl);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function cleanupDestination()
    {
        $filelist = scandir($this->storeDestination);
        foreach ($filelist as $file) {
            if (preg_match('/.mp/', $file)) {
                printf("Unlink %s.\n", $file);
                unlink(
                    sprintf('%s/%s', $this->storeDestination, $file)
                );
            }
        }
        return $this;
    }

    /**
     * Extract manifest as array.
     * @param $manifestUrl
     * @return array|mixed
     * @throws ExceptionHandler
     */
    private function getPlaylistManifest($manifestUrl)
    {
        return explode("\n", $this->wrapper->request($manifestUrl)->getBody());
    }

    /**
     * @param array $content
     * @return int
     */
    private function getSegmentCount($content)
    {
        $segmentCount = 0;

        foreach ($content as $row) {
            if (!preg_match('/^#/', $row)) {
                $segmentCount++;
            }
        }

        return $segmentCount;
    }

    /**
     * @param $manifest
     * @return string
     */
    private function getBaseUrl($manifest)
    {
        $uData = explode("/", $manifest);
        $uData = array_reverse($uData);
        array_shift($uData);
        return implode("/", array_reverse($uData));
    }

    /**
     * @param $manifestContent
     * @param $typeName
     * @throws ExceptionHandler
     */
    private function getMergedSegments($manifestContent, $typeName, $basePath)
    {
        $destination = sprintf(
            '%s/%s',
            preg_replace('/\/$/', '/', $this->storeDestination),
            $typeName
        );
        $part = 1;
        foreach ($manifestContent as $row) {
            $partNum = str_pad($part, 6, '0', STR_PAD_LEFT);
            $destinationName = sprintf('%s%d.mp4', $destination, $partNum);

            switch ($row) {
                case preg_match('/^#/', $row) && preg_match('/MAP:URI/i', $row):
                    printf("Merge map.\n");
                    file_put_contents(
                        $destinationName,
                        $this->getMapData($row, $basePath),
                        FILE_APPEND | FILE_BINARY
                    );
                    break;
                case (bool)preg_match('/DISCONTINUITY/', $row):
                    $part++;
                    break;
                case (bool)preg_match('/^#/', $row):
                    break;
                default:
                    printf("Merge %s.\n", $row);
                    file_put_contents(
                        $destinationName,
                        $this->getContentData($row, $basePath),
                        FILE_APPEND | FILE_BINARY
                    );
                    break;
            }
        }
    }

    /**
     * Get mpeg map.
     * @param $mapDataString
     * @param $basePath
     * @return mixed
     * @throws ExceptionHandler
     */
    private function getMapData($mapDataString, $basePath)
    {
        $map = preg_replace('/(.*?)\"(.*?)\"(.*?)$/', '$2', $mapDataString);
        $getFrom = sprintf('%s/%s', $basePath, $map);
        $mapData = $this->wrapper->request($getFrom)->getBody();
        return $mapData;
    }

    /**
     * @param $contentUri
     * @param $basePath
     * @return array|mixed
     * @throws ExceptionHandler
     */
    private function getContentData($contentUri, $basePath)
    {
        return $this->wrapper->request(
            sprintf('%s/%s', $basePath, $contentUri)
        )->getBody();
    }

    /**
     * @param $destination
     * @return Download
     */
    public function setStoreDestination($destination)
    {
        $this->storeDestination = $destination;
        return $this;
    }
}
