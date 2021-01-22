<?php

namespace M3U8;

class M3U8_Detect
{
    private $currentManifestUrl;
    private $manifests = ['Disney'];

    public function __construct($url)
    {
        $this->currentManifestUrl = $url;
    }

    /**
     * @return mixed|null
     */
    public function getManifestClass() {
        $return = null;

        foreach ($this->manifests as $key) {
            if (preg_match(
                sprintf('/%s/i', $key),
                $this->currentManifestUrl
            )) {
                $className = sprintf('%s\M3U8_%s', 'M3U8', $key);
                if (class_exists($className)) {
                    return new $className();
                }
            }
        }

        return $return;
    }
}