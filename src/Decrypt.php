<?php

namespace M3U8;

class Decrypt
{
    private $mpDecryptBinary = '/usr/local/mp4decrypt/bin/mp4decrypt';
    private $ffmpeg = '/usr/bin/ffmpeg';
    private $wideVineKeys;

    /**
     * Set new binary for mp4decrypt.
     * @param $binaryFile
     * @return Decrypt
     */
    public function setDecryptBinary($binaryFile)
    {
        if (file_exists($binaryFile)) {
            $this->mpDecryptBinary = $binaryFile;
        }
        return $this;
    }

    /**
     * @param $binaryFile
     * @return $this
     */
    public function setMpegLibrary($binaryFile)
    {
        if (file_exists($binaryFile)) {
            $this->ffmpeg = $binaryFile;
        }
        return $this;
    }

    /**
     * @param $keyString
     * @return $this
     */
    public function setWideVineKeys($keyString)
    {
        $this->wideVineKeys = trim($keyString);

        return $this;
    }

    /**
     * @param $encryptedVideoFile
     * @param $destinationVideoFile
     * @return mixed
     */
    public function getDecryptedVideo($encryptedVideoFile, $destinationVideoFile)
    {
        $decodeApplication = sprintf(
            "%s %s %s %s",
            $this->mpDecryptBinary,
            $this->wideVineKeys,
            $encryptedVideoFile,
            $destinationVideoFile
        );

        exec($decodeApplication, $output, $return);

        return $return;
    }
}
