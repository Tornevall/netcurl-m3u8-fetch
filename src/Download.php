<?php

namespace Playlist;

use Exception;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Module\Config\WrapperConfig;
use TorneLIB\Module\Network\Wrappers\CurlWrapper;

class Download
{
    private $wrapper;

    /**
     * Manifest automation.
     * @var array
     */
    private $autoCollector = [];

    /**
     * List of abbreviated language names translated to their long names, in use with multilingual render.
     * @var array
     */
    private $audioNames = [];

    /**
     * Audio array in preferred order.
     * Regarding eac-3: Could not find tag for codec eac3 in stream #0, codec not currently supported in container.
     * @var array
     */
    private $allowedAudio = ['aac-128k', 'aac-64k', 'eac-3'];

    /**
     * User-Agent for 1080p.
     * @var string
     */
    private $browser1080 = 'Mozilla/5.0 (Web0S; Linux/SmartTV) AppleWebKit/538.2 (KHTML, like Gecko) Large Screen ' .
    'Safari/538.2 LG Browser/7.00.00(LGE; 24LF4820-BU; 03.20.14; 1; DTV_W15L); webOS.TV-2015; LG NetCast.TV-2013 ' .
    'Compatible (LGE, 24LF4820-BU, wireless)';

    private $languageList = [];

    private $audioGroup = '';

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
    /**
     * @var int Which row the subtitle is on, in count.
     */
    private $subTitleRow;
    /**
     * @var bool $isMultiAudio Multiple language import boolean.
     */
    private $isMultiAudio = false;

    /**
     * @var bool $includeDubbing Whether we want to include dub cards in the merge.
     */
    private $includeDubbing = false;

    /**
     * Download constructor.
     */
    public function __construct()
    {
        $this->wrapper = new CurlWrapper();
        $config = new WrapperConfig();
        $config->setUserAgent($this->browser1080);
        $this->wrapper->setConfig($config);
        $this->wrapper->setTimeout(30);
    }

    /**
     * Set other preferred audio codec.
     * @param $audioCodec
     * @return $this
     */
    public function setAudioCodec($audioCodec)
    {
        if (in_array($audioCodec, $this->allowedAudio)) {
            $this->allowedAudio = [$audioCodec];
        }

        return $this;
    }

    /**
     * @param $allow
     * @return $this
     */
    public function setDubCardAllow($allow)
    {
        $this->includeDubbing = $allow;

        return $this;
    }

    /**
     * @param $languageList
     */
    public function setLanguageList($languageList = '')
    {
        $languageArray = explode(',', $languageList);
        if (is_array($languageArray)) {
            foreach ($languageArray as $languageKey) {
                if (!empty($languageKey)) {
                    $this->languageList[] = $languageKey;
                }
            }
        } elseif (is_string($languageList)) {
            $this->languageList[] = $languageList;
        }
    }

    /**
     * Autodetect preferred audio and video from a manifest.
     *
     * @param $manifestUrl
     * @return $this
     * @throws ExceptionHandler
     */
    public function getDetectedManifest($manifestUrl)
    {
        $this->wrapper->request($manifestUrl);
        $manifestAutoContent = explode("\n", $this->wrapper->getBody());

        $languageList = ['en', 'da', 'no', 'fi', 'sv'];
        $audioArray = [];
        $videoArray = [];
        $subtitleArray = [];

        if (count($this->languageList)) {
            foreach ($this->languageList as $languageKey) {
                if (!in_array($languageKey, $languageList)) {
                    $languageList[] = $languageKey;
                }
            }
        }
        echo "Language list follows...\n";
        print_r($languageList);

        foreach ($manifestAutoContent as $index => $row) {
            $shortUri = $this->getDataFromM3u($row, 'URI');
            if (!empty($shortUri)) {
                $shortUri = $this->getBaseUrl($manifestUrl) . '/' . $shortUri;
            }
            $mediaType = $this->getDataFromM3u($row, 'EXT-X-MEDIA:TYPE');
            $mediaVideo = $this->getDataFromM3u($row, 'EXT-X-STREAM-INF:BANDWIDTH', false);
            $mediaBand = 0;
            if (!empty($mediaVideo)) {
                $mediaType = 'STREAM';
                $mediaBand = $mediaVideo;
            }
            switch ($mediaType) {
                case 'AUDIO':
                    $language = $this->getDataFromM3u($row, 'LANGUAGE');
                    $audioGroup = $this->getDataFromM3u($row, 'GROUP-ID');
                    $languageName = $this->getDataFromM3u($row, 'NAME');
                    if ((
                            in_array($language, $languageList, true) ||
                            in_array('all', $languageList, true)
                        ) &&
                        !preg_match('/description/i', $languageName) &&
                        !preg_match('/deskription/i', $languageName)
                    ) {
                        $this->audioNames[$language] = $languageName;
                        $audioArray[$audioGroup][$language] = $shortUri;
                    }
                    break;
                case 'SUBTITLES':
                    $language = $this->getDataFromM3u($row, 'LANGUAGE');
                    if (in_array($language, $languageList, true) ||
                        in_array('all', $languageList, true)
                    ) {
                        $subtitleArray[$language] = sprintf('%s:%s', $language, $shortUri);
                    }
                    break;
                case 'STREAM':
                    $forAudio = $this->getDataFromM3u($row, 'AUDIO');
                    $uriStream = $this->getBaseUrl($manifestUrl) . '/' . $manifestAutoContent[$index + 1];
                    $videoArray[$forAudio][$mediaBand] = $uriStream;
                    break;
                default:
                    break;
            }
        }

        try {
            $this->autoCollector = [
                'subtitle' => $subtitleArray,
                'video' => $videoArray,
                'audio' => $audioArray,
            ];

        } catch (Exception $e) {
            // Skip on failures.
        }

        return $this;
    }

    /**
     * @param $string
     * @param $key
     * @param bool $exact
     * @return $this
     */
    private function getDataFromM3u($string, $key, $exact = true)
    {
        $string = preg_replace('/^#+/', '', $string);
        $dataStr = explode(",", $string);
        $return = null;
        if (is_array($dataStr) && count($dataStr)) {
            foreach ($dataStr as $dataPart) {
                $dataEx = explode("=", $dataPart);
                if (is_array($dataEx) && count($dataEx) >= 2) {
                    if (strtolower($dataEx[0]) === strtolower($key)) {
                        $return = $dataEx[1];
                        break;
                    }
                    if (!$exact && preg_match(sprintf('/%s/i', $key), $dataEx[0])) {
                        $return = $dataEx[1];
                        break;
                    }
                }
            }
        }

        return preg_replace('/^"|"$/', '', $return);
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
     * @return string
     */
    public function getAudioGroup()
    {
        return $this->audioGroup;
    }

    /**
     * @return $this
     * @throws ExceptionHandler
     */
    public function exec()
    {
        $this->cleanupDestination();
        $this->getGeneratedRequest();

        echo "Checking subtitleManifest (array)...\n";
        if (!empty($this->subtitleManifest)) {
            foreach ($this->subtitleManifest as $manifestLanguage => $manifestUrl) {
                $this->subTitleRow = 0;
                $this->subtitleBaseUrl = $this->getBaseUrl($manifestUrl);
                $this->subtitleManifestContent = $this->getPlaylistManifest($manifestUrl);
                $this->getMergedSegments(
                    $this->subtitleManifestContent,
                    sprintf('%s', $manifestLanguage),
                    $this->subtitleBaseUrl
                );
            }
            // Clean up the array.
            $this->subtitleManifestContent = [];
        }

        printf("Checking audioManifest (handled as array)...\n", $this->audioManifest);
        if (!empty($this->audioManifest)) {
            $this->getCheckedAudioManifest();
        }

        printf("Checking videoManifest (%s)...\n", $this->videoManifest);
        if (!empty($this->videoManifest)) {
            $this->videoManifestContent = $this->getPlaylistManifest($this->videoManifest);
            $this->videoSegmentCount = $this->getSegmentCount($this->videoManifestContent);
            $this->videoBaseUrl = $this->getBaseUrl($this->videoManifest);
            echo "=== VIDEO SEGMENT REQUEST ===\n";
            $this->getMergedSegments($this->videoManifestContent, 'encvid', $this->videoBaseUrl);
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
            if (preg_match('/.mp|.vtt|.srt|.txt/', $file)) {
                printf("Unlink %s.\n", $file);
                unlink(
                    sprintf('%s/%s', $this->storeDestination, $file)
                );
            }
        }
        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     */
    private function getGeneratedRequest()
    {
        if (isset($this->autoCollector['subtitle']) && is_array($this->autoCollector['subtitle'])) {
            $this->setSubtitleManifest($this->autoCollector['subtitle']);
        }
        if (isset($this->autoCollector['audio'])) {
            foreach ($this->allowedAudio as $audioGroup) {
                if (isset($this->autoCollector['audio'][$audioGroup])) {
                    $audioBandArray = $this->autoCollector['audio'][$audioGroup];
                    $videoBandArray = $this->autoCollector['video'][$audioGroup];
                    ksort($videoBandArray);
                    if (count($audioBandArray) === 1) {
                        $this->setAudioManifest(array_pop($audioBandArray));
                    } elseif (count($audioBandArray) > 1) {
                        $audioBandArrayRender = [];
                        foreach ($audioBandArray as $languageKey => $languageUrl) {
                            $audioBandArrayRender[] = sprintf(
                                '%s:%s',
                                $this->audioNames[$languageKey],
                                $languageUrl
                            );
                        }
                        $this->setAudioManifest(implode(",", $audioBandArrayRender));
                    }
                    if (is_array($videoBandArray) && count($videoBandArray)) {
                        $preferredVideoUrl = array_pop($videoBandArray);
                        $this->setVideoManifest($preferredVideoUrl);
                        $this->audioGroup = $audioGroup;
                        printf("Audio Group of Choice: %s\n", $audioGroup);
                        break;
                    } else {
                        throw new Exception("No video array found. Aborting.\n");
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @param $manifestUrl
     * @return $this
     * @throws Exception
     */
    public function setAudioManifest($manifestUrl)
    {
        $this->audioManifest = $manifestUrl;

        $newArray = [];
        if (is_string($manifestUrl)) {
            if (preg_match('/,/', $manifestUrl)) {
                $this->audioManifest = explode(',', $manifestUrl);
                foreach ($this->audioManifest as $audManifest) {
                    if (!(bool)preg_match('/:http/', $audManifest)) {
                        throw new Exception('Subtitle manifest must be defined with a language like -s<lang>:<url>');
                    }
                    $explodeManifest = explode(':', $audManifest, 2);
                    $newArray[$explodeManifest[0]] = $explodeManifest[1];
                }

                if (!empty($newArray)) {
                    $this->audioManifest = $newArray;
                }
            }
        }

        return $this;
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
        $rows = 0;
        $rowCount = 0;
        // Count rows before start to estimate number of merged segments.
        foreach ($manifestContent as $row) {
            if (!preg_match('/^#/', $row)) {
                $rows++;
            }
        }
        foreach ($manifestContent as $row) {
            $partNum = str_pad($part, 2, '0', STR_PAD_LEFT);
            if ($this->isSubtitle()) {
                $part = 0;
                $partNum = null;
            }
            $destinationName = sprintf('%s%s.%s', $destination, $partNum, $this->storeExtension);

            if (preg_match('/DUB_CARD/', $row)) {
                echo "DUB_CARD discovered. Skipping!\n";
                continue;
            }
            if (preg_match('/BUMPER/', $row)) {
                echo "BUMPER discovered. Skipping!\n";
                continue;
            }

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
                        continue 2;
                    }

                    $rowCount++;
                    printf("Merge [Segment %d of %d] %s (%s).\n", $rowCount, $rows, $row, $destinationName);
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

        foreach ((array)$this->subtitleManifestContent as $row) {
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
     * @return $this
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
            if (count($content) <= 1) {
                return "";
            }
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
            $return = $output . PHP_EOL;
        }

        return $return;
    }

    /**
     * Handle multiple audio manifests.
     * @throws ExceptionHandler
     */
    private function getCheckedAudioManifest()
    {
        if (is_string($this->audioManifest)) {
            $this->audioManifest = ['normal' => $this->audioManifest];
        }
        foreach ($this->audioManifest as $audioKey => $manifestUrl) {
            $this->audioManifestContent = $this->getPlaylistManifest($manifestUrl);
            $this->audioSegmentCount = $this->getSegmentCount($this->audioManifestContent);
            $this->audioBaseUrl = $this->getBaseUrl($manifestUrl);
            echo "=== AUDIO SEGMENT REQUEST ===\n";
            $this->getMergedSegments(
                $this->audioManifestContent,
                sprintf('audio_%s_', $audioKey),
                $this->audioBaseUrl
            );
        }
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
     * @param $destination
     * @return Download
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
     * @return $this
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
