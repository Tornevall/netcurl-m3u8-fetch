<?php

namespace M3U8;

class FileHandler
{
    /**
     * @var string
     */
    private $mpDecryptBinary = '/usr/local/mp4decrypt/bin/mp4decrypt';

    /**
     * @var string
     */
    private $ffmpeg = '/usr/bin/ffmpeg';

    /**
     * @var
     */
    private $wideVineKeys;

    /**
     * @var
     */
    private $storeDestination;

    /**
     * @var
     */
    private $files = [];

    /**
     * @var string
     */
    private $concatVideoFile = '';
    /**
     * @var string
     */
    private $concatAudioFile = '';

    /**
     * @var string
     */
    private $mergeVideoName = '';
    /**
     * @var string
     */
    private $mergeAudioName = '';

    /**
     * Set new binary for mp4decrypt.
     * @param $binaryFile
     * @return FileHandler
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
     * @param $destination
     * @return FileHandler
     */
    public function setStoreDestination($destination)
    {
        $this->storeDestination = $destination;
        return $this;
    }

    /**
     * @return $this
     */
    public function exec()
    {
        $this->getFileList();
        $this->getDecrypted();
        $this->setConcatenatedData();

        return $this;
    }

    private function getFileList()
    {
        $scanFiles = scandir($this->storeDestination);

        $fileListArray = [
            'video' => [],
            'audio' => [],
        ];
        foreach ($scanFiles as $file) {
            if (preg_match('/.m(p4|4a|mp4a|mpa)/i', $file)) {
                $fullName = sprintf('%s/%s', $this->storeDestination, $file);

                switch ($file) {
                    case (bool)preg_match('/vid(.\d+)/i', $file):
                        $fileListArray['video'][] = $fullName;
                        break;
                    case (bool)preg_match('/audio(.\d+)/i', $file):
                        $fileListArray['audio'][] = $fullName;
                        break;
                    default:
                }
            }
        }
        $this->files = $fileListArray;
    }

    /**
     * @return $this
     */
    private function setConcatenatedData()
    {
        $this->concatVideoFile = $this->getFullFileName('concat_video.txt');
        $this->concatAudioFile = $this->getFullFileName('concat_audio.txt');
        $this->mergeVideoName = $this->getFullFileName('merge.mp4');
        $this->mergeAudioName = $this->getFullFileName('merge.m4a');
        $this->cleanConcatFiles();

        $this->setConcatFile($this->files['video'], $this->concatVideoFile);
        $this->setConcatFile($this->files['audio'], $this->concatAudioFile);
        $this->ffConcatenate($this->concatVideoFile, $this->mergeVideoName, 'video');
        $this->ffConcatenate($this->concatAudioFile, $this->mergeAudioName, 'audio');

        $this->ffMerge($this->mergeVideoName, $this->mergeAudioName);

        return $this;
    }

    /**
     * @param $name
     * @return string
     */
    private function getFullFileName($name)
    {
        return sprintf('%s/%s', $this->storeDestination, $name);
    }

    /**
     * @return $this
     */
    private function cleanConcatFiles()
    {
        $this->cleanFiles(
            [
                $this->concatVideoFile,
                $this->concatAudioFile,
                $this->mergeVideoName,
                $this->mergeAudioName,
            ]
        );

        return $this;
    }

    /**
     * @param $fileArray
     * @return $this
     */
    private function cleanFiles($fileArray)
    {
        foreach ($fileArray as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        return $this;
    }

    /**
     * @param $array
     * @param $concatFile
     * @return FileHandler
     */
    private function setConcatFile($array, $concatFile)
    {
        foreach ($array as $file) {
            if ((bool)preg_match('/dec/i', $file)) {
                file_put_contents($concatFile, sprintf("file '%s'\n", $file), FILE_APPEND);
            }
            if ((bool)preg_match('/audio/i', $file)) {
                file_put_contents($concatFile, sprintf("file '%s'\n", $file), FILE_APPEND);
            }
        }
        return $this;
    }

    /**
     * @param $concatFile
     * @param $mergeName
     * @param $type
     * @return null
     */
    private function ffConcatenate($concatFile, $mergeName, $type)
    {
        $return = null;
        $concatString = '';
        if (!empty($concatFile) && file_exists($concatFile)) {
            switch ($type) {
                case 'video':
                    $concatString = "-f concat -safe 0 -i %s -c:v copy %s";
                    break;
                case 'audio':
                    $concatString = "-f concat -safe 0 -i %s -c:a copy %s";
                    break;
                default:
            }
            if (!empty($concatString)) {
                $this->ffExec(sprintf(
                    $concatString,
                    $concatFile,
                    $mergeName
                ));
            }
        }
        return $return;
    }

    /**
     * @param $cmd
     * @return mixed
     */
    private function ffExec($cmd)
    {
        $ffmpegCmd = sprintf("%s %s", $this->ffmpeg, $cmd);
        exec($ffmpegCmd, $output, $return);

        return $return;
    }

    /**
     * @param $concatVideoFile
     * @param $concatAudioFile
     * @return $this
     */
    private function ffMerge($concatVideoFile, $concatAudioFile)
    {
        $mergedVideoName = $this->getFullFileName('merged_video.mp4');
        $this->cleanFiles([$mergedVideoName]);

        if (file_exists($concatVideoFile) && file_exists($concatAudioFile)) {
            $this->ffExec(
                sprintf(
                    '-i %s -i %s -c:v copy -c:a aac %s',
                    $concatVideoFile,
                    $concatAudioFile,
                    $mergedVideoName
                )
            );
        }
        return $this;
    }

    /**
     * @param $source
     * @param $destination
     * @return mixed
     */
    private function ffWrite($source, $destination)
    {
        $ffmpegCmd = sprintf("%s -i %s %s", $this->ffmpeg, $source, $destination);
        exec($ffmpegCmd, $output, $return);

        return $return;
    }

    /**
     * @return $this
     */
    private function getDecrypted()
    {
        foreach ($this->files['video'] as $file) {
            $getName = basename($file);
            if ((bool)preg_match('/^enc/i', $getName)) {
                $getFileNum = preg_replace('/\D./', '$1', $getName);
                $storeAs = sprintf(
                    '%s/decvid%s.mp4',
                    $this->storeDestination,
                    $getFileNum
                );

                printf("Decrypting %s with keys %s.\n", $getName, $this->wideVineKeys);
                $this->getDecryptedVideo(
                    $file,
                    $storeAs
                );
            }
        }
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
            "%s --key %s %s %s",
            $this->mpDecryptBinary,
            $this->wideVineKeys,
            $encryptedVideoFile,
            $destinationVideoFile
        );

        exec($decodeApplication, $output, $return);

        return $return;
    }
}
