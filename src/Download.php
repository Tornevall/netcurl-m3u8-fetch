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
     * @todo Need another user-agent since LG 24LF4820-BU is blacklisted.
     */
    private $browser1080 = 'Mozilla/5.0 (Web0S; Linux/SmartTV) AppleWebKit/538.2 (KHTML, like Gecko) Large Screen ' .
    'Safari/538.2 LG Browser/7.00.00(LGE; 24LF4820-BU; 03.20.14; 1; DTV_W15L); webOS.TV-2015; LG NetCast.TV-2013 ' .
    'Compatible (LGE, 24LF4820-BU, wireless)';

    /**
     * Download constructor.
     */
    public function __construct()
    {
        $this->wrapper = new CurlWrapper();
        $config = new WrapperConfig();
        $this->wrapper->setConfig($config);
    }

    /**
     * Extract manifest as array.
     * @param $manifestUrl
     * @return array|mixed
     * @throws ExceptionHandler
     */
    public function getPlaylistManifest($manifestUrl)
    {
        return explode("\n", $this->wrapper->request($manifestUrl)->getBody());
    }

    /**
     * @param $manifestUrl
     */
    public function getVideoParts($manifestUrl) {

    }
}
