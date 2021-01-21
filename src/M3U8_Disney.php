<?php

namespace M3U8;

use Exception;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Module\Network\Wrappers\CurlWrapper;

class M3U8_Disney
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
     * @var array List of urls with subtitles.
     */
    private $subtitleManifest = [];

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
     * @var string
     */
    private $subtitleManifestContent = '';
    /**
     * @var string
     */
    private $subtitleBaseUrl;
    /**
     * @var string
     */
    private $storeExtension;
    /**
     * @var bool
     */
    private $hasByteOrder = false;
    private $subTitleRow;

    /**
     * Download constructor.
     */
    public function __construct()
    {
        $this->wrapper = new CurlWrapper();
        $this->wrapper->setTimeout(30);
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

        printf("Checking videoManifest (%s)...\n", $this->videoManifest);
        if (!empty($this->videoManifest)) {
            $this->videoManifestContent = $this->getPlaylistManifest($this->videoManifest);
            $this->videoSegmentCount = $this->getSegmentCount($this->videoManifestContent);
            $this->videoBaseUrl = $this->getBaseUrl($this->videoManifest);
            echo "=== VIDEO SEGMENT REQUEST ===\n";
            $this->getMergedSegments($this->videoManifestContent, 'encvid', $this->videoBaseUrl);
        }

        printf("Checking audioManifest (%s)...\n", $this->audioManifest);
        if (!empty($this->audioManifest)) {
            $this->audioManifestContent = $this->getPlaylistManifest($this->audioManifest);
            $this->audioSegmentCount = $this->getSegmentCount($this->audioManifestContent);
            $this->audioBaseUrl = $this->getBaseUrl($this->audioManifest);
            echo "=== AUDIO SEGMENT REQUEST ===\n";
            $this->getMergedSegments($this->audioManifestContent, 'audio', $this->audioBaseUrl);
        }

        echo "Checking subtitleManifest (is array)...\n";
        if (!empty($this->subtitleManifest)) {
            foreach ($this->subtitleManifest as $manifestLanguage => $manifestUrl) {
                $this->subTitleRow = 0;
                $this->subtitleBaseUrl = $this->getBaseUrl($manifestUrl);
                $this->subtitleManifestContent = $this->getPlaylistManifest($manifestUrl);
                $this->getMergedSegments(
                    $this->subtitleManifestContent,
                    sprintf('sub_%s', $manifestLanguage),
                    $this->subtitleBaseUrl
                );
            }
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
            if (preg_match('/.mp|.vtt|.srt/', $file)) {
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
     */
    private function getMergedSegments($manifestContent, $typeName, $basePath)
    {
        $destination = sprintf(
            '%s/%s',
            preg_replace('/\/$/', '/', $this->storeDestination),
            $typeName
        );

        $this->storeExtension = $this->getExtension($typeName);

        $part = 1;
        foreach ($manifestContent as $row) {
            $partNum = str_pad($part, 2, '0', STR_PAD_LEFT);
            if ($this->isSubtitle()) {
                $part = 0;
                $partNum = null;
            }
            $destinationName = sprintf('%s%s.%s', $destination, $partNum, $this->storeExtension);

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
                    if (preg_match('/DUB(.*?)vtt$/', $row)) {
                        continue;
                    }

                    printf("Merge %s.\n", $row);
                    try {
                        $this->setContentData($destinationName, $row, $basePath);
                    } catch (ExceptionHandler $e) {
                        printf("Skipped segment due to error %d: %s\n", $e->getCode(), $e->getMessage());
                    }
                    break;
            }
        }
    }

    /**
     * @param $typeName
     * @return string
     */
    private function getExtension($typeName = '')
    {
        $return = 'mp4';

        foreach ($this->subtitleManifestContent as $row) {
            if (substr($row, 0, 1) !== '#') {
                preg_match_all('/\.(.*?)$/', $row, $resultExt);
                if (isset($resultExt[1], $resultExt[1][0]) && strlen($resultExt[1][0]) > 1) {
                    $return = $resultExt[1][0];
                    break;
                }
            }
        }

        if ($return === 'vtt') {
            $return = 'srt'; // Trying to convert here.
        }

        /*        if (preg_match('_', $typeName)) {
                    $typeEx = explode('_', $typeName);
                    switch ($typeEx[0]) {
                        case 'sub':
                            $return = 'srt';
                            break;
                        default:
                            break;
                    }
                }*/

        return $return;
    }

    /**
     * @return bool
     */
    private function isSubtitle()
    {
        $return = false;
        if (in_array($this->storeExtension, ['vtt', 'srt'])) {
            $return = true;
        }

        return $return;
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
     * @param $destinationName
     * @param $row
     * @param $basePath
     * @return M3U8_Disney
     * @throws ExceptionHandler
     */
    private function setContentData($destinationName, $row, $basePath)
    {
        $content = $this->getContentData($row, $basePath);

        if ($this->isSubtitle()) {
            $content = $this->getSrtByteOrder() . $this->getSrt($content);
        }

        file_put_contents(
            $destinationName,
            $content,
            FILE_APPEND | FILE_BINARY
        );

        return $this;
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
     * Simplified byte order.
     */
    private function getSrtByteOrder()
    {
        if (!$this->hasByteOrder) {
            $this->hasByteOrder = true;
            return chr(hexdec('EF')) .
                chr(hexdec('BB')) .
                chr(hexdec('BF'));
        }

        return null;
    }

    /**
     * @param $content
     * @return mixed
     */
    private function getSrt($content)
    {
        $return = $content;
        $rowState = '';
        if (preg_match('/WEBVTT/', $content)) {
            $stripVTT = trim(preg_replace('/(.*?)}(.*)/s', '$2', $content));
            $stripVTT = trim(preg_replace('/WEBVTT$/', '', $stripVTT));
            $content = explode("\n", $stripVTT);
            $output = '';
            foreach ($content as $row) {
                $pattern1 = '#(\d{2}):(\d{2}):(\d{2})\.(\d{3})#'; // '00:00:00.000'
                $pattern2 = '#(\d{2}):(\d{2})\.(\d{3})#'; // '00:00.000'
                $m1 = preg_match($pattern1, $row);
                if (is_numeric($m1) && $m1 > 0) {
                    $this->subTitleRow++;
                    $output .= $this->subTitleRow;
                    $output .= PHP_EOL;
                    $row = preg_replace($pattern1, '$1:$2:$3,$4', $row);
                    $rowEx = explode(' ', $row);
                    if (isset($rowEx[3])) {
                        $rowInfo = $rowEx[3];
                        unset($rowEx[3]);
                        if (preg_match('/start/i', $rowInfo)) {
                            $rowState = '{\an8}';
                        }
                        $row = implode(' ', $rowEx);
                    }
                } else {
                    $m2 = preg_match($pattern2, $row);
                    if (is_numeric($m2) && $m2 > 0) {
                        $output .= $this->subTitleRow;
                        $output .= PHP_EOL;
                        $row = preg_replace($pattern2, '00:$1:$2,$3', $row);
                    } else {
                        $row = $rowState . $row;
                    }
                    $rowState = '';
                }
                $output .= $row . PHP_EOL;

                if (empty($row)) {
                    //$output .= "\n";
                    continue;
                }
            }
            $return = $output;
        }

        return $return;
    }

    /**
     * @param $destination
     * @return M3U8_Disney
     */
    public function setStoreDestination($destination)
    {
        $this->storeDestination = $destination;
        return $this;
    }

    /**
     * @return array
     */
    public function getSubtitleManifest()
    {
        return $this->subtitleManifest;
    }

    /**
     * @param array $subtitleManifest
     * @return M3U8_Disney
     * @throws Exception
     */
    public function setSubtitleManifest($subtitleManifest = [])
    {
        $newArray = [];
        $this->subtitleManifest = $subtitleManifest;
        if (is_string($subtitleManifest)) {
            $this->subtitleManifest = (array)$subtitleManifest;
            if (preg_match('/,/', $subtitleManifest)) {
                $this->subtitleManifest = explode(',', $subtitleManifest);
            }
        }
        foreach ($this->subtitleManifest as $subManifest) {
            if (!(bool)preg_match('/:http/', $subManifest)) {
                throw new Exception('Subtitle manifest must be defined with a language like -s<lang>:<url>');
            }
            $explodeManifest = explode(':', $subManifest, 2);
            $newArray[$explodeManifest[0]] = $explodeManifest[1];
        }

        if (!empty($newArray)) {
            $this->subtitleManifest = $newArray;
        }

        return $this;
    }
}
