<?php

namespace Playlist;

use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\Module\Network\Wrappers\CurlWrapper;

class Download
{
    private $wrapper;

    /**
     * @var int
     */
    private $segmentCount = 0;

    /**
     * @var string
     */
    private $manifest;

    /**
     * @var array Arrayed playlist.
     */
    private $manifestContent = [];

    /**
     * @var string Storage for mp4/m4a data.
     */
    private $storeDestination = __DIR__ . '/tmp';

    /**
     * @var string Root content url (for m3u8 without full urls).
     */
    private $baseUrl;

    /**
     * Download constructor.
     */
    public function __construct()
    {
        $this->wrapper = new NetWrapper();
        $config = new WrapperConfig();
        $this->wrapper->setConfig($config);
    }

    /**
     * @param $manifestUrl
     * @return $this
     */
    public function setManifest($manifestUrl)
    {
        $this->manifest = $manifestUrl;
        return $this;
    }

    /**
     * @return $this
     * @throws ExceptionHandler
     */
    public function exec()
    {
        $this->cleanupDestination();

        if (!empty($this->manifest)) {
            $this->manifestContent = $this->getPlaylistManifest($this->manifest);
            $this->segmentCount = $this->getSegmentCount($this->manifestContent);
            $this->baseUrl = $this->getBaseUrl($this->manifest);
            $this->getMergedSegments($this->manifestContent, 'merge', $this->baseUrl);
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
     * @param $basePath
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
            $partNum = str_pad($part, 2, '0', STR_PAD_LEFT);
            $destinationName = sprintf('%s%s.mp4', $destination, $partNum);

            switch ($row) {
                case preg_match('/^#/', $row) && preg_match('/MAP:URI/i', $row):
                    printf("Join map.\n");
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
                    printf("Join %s.\n", $row);
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
     */
    private function getMapData($mapDataString, $basePath)
    {
        $map = preg_replace('/(.*?)\"(.*?)\"(.*?)$/', '$2', $mapDataString);
        $getFrom = sprintf('%s/%s', $basePath, $map);
        $mapData = '';
        try {
            $mapData = $this->wrapper->request($getFrom)->getBody();
        } catch (ExceptionHandler $e) {
            printf("%s (%d): %s\n", $e->getMessage(), $e->getCode(), $getFrom);
        }
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
        if (!file_exists($destination)) {
            mkdir($destination);
        }
        if (!file_exists($destination)) {
            throw new \Exception('Destination directory not found - not able to create.');
        }
        $this->storeDestination = $destination;
        return $this;
    }
}
