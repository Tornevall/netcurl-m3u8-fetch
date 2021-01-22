<?php

namespace M3U8;

use Exception;

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
     * @var string
     */
    private $outputName;

    /**
     * @var string
     */
    private $finalVideoName;

    /**
     * @var string
     */
    private $finalRenameName;
    /**
     * @var bool
     */
    private $useMetaTitles = false;

    /**
     * FileHandler constructor.
     */
    public function __construct()
    {
        if (file_exists(__DIR__ . '/../mp4decrypt/bin/mp4decrypt')) {
            // Using local instead of remote-
            $this->setDecryptBinary(__DIR__ . '/../mp4decrypt/bin/mp4decrypt');
        }
    }

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
        if ($this->getWideVineKeys()) {
            $this->getDecrypted();
        }
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
                    case (bool)preg_match('/audio_(.*?)_(.\d+)/i', $file):
                        $splitAudioKeys = explode('_', $file);
                        if (is_array($splitAudioKeys) && count($splitAudioKeys)) {
                            if (!isset($fileListArray['audio'][$splitAudioKeys[1]])) {
                                $fileListArray['audio'][$splitAudioKeys[1]] = [];
                            }
                            $fileListArray['audio'][$splitAudioKeys[1]][] = $fullName;
                        }
                        break;
                    default:
                }
            }
        }
        $this->files = $fileListArray;
    }

    /**
     * @return mixed
     */
    public function getWideVineKeys()
    {
        return $this->wideVineKeys;
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
     * @return $this
     */
    private function getDecrypted()
    {
        if (!$this->wideVineKeys) {
            echo "Keys not included in this session. Ignoring\n";
            return $this;
        }

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

        try {
            //exec($decodeApplication, $output, $return);
            system($decodeApplication, $return);
        } catch (Exception $e) {
            // Ignore errors.
        }

        return $return;
    }

    /**
     * @return $this
     */
    private function setConcatenatedData()
    {
        $this->concatAudioFile = $this->getFullFileName('concat_audio.txt');
        $this->concatVideoFile = $this->getFullFileName('concat_video.txt');
        $this->mergeVideoName = $this->getFullFileName('merge.mp4');
        $this->mergeAudioName = $this->getFullFileName('merge.m4a');
        $this->finalVideoName = $this->getFullFileName('merged_video.mp4');
        $this->cleanConcatFiles();

        // Rescan files.
        $this->getFileList();
        $this->setConcatFile($this->files['audio'], $this->concatAudioFile, 'audio');
        $this->setConcatFile($this->files['video'], $this->concatVideoFile, 'video');
        foreach ($this->concatAudioFile as $audioKey => $audioFile) {
            $this->ffConcatenate($audioFile, $this->mergeAudioName[$audioKey], 'audio');
        }
        $this->ffConcatenate($this->concatVideoFile, $this->mergeVideoName, 'video');
        $this->ffMerge($this->mergeVideoName, $this->mergeAudioName);

        if (!empty($this->outputName) && file_exists($this->finalVideoName)) {
            $this->finalRenameName = $this->getFullFileName($this->outputName);
            $this->cleanConcatFiles();
            $this->cleanFiles($this->files['audio']);
            $this->cleanFiles($this->files['video']);
            rename($this->finalVideoName, $this->finalRenameName);
        }

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
     * @param $type
     * @return FileHandler
     */
    private function setConcatFile($array, $concatFile, $type)
    {
        if ($type === 'video') {
            $this->setConcatFileArray($array, $concatFile);
        } elseif ($type === 'audio') {
            $defaultAudioName = $this->mergeAudioName;
            $this->concatAudioFile = [];
            $this->mergeAudioName = [];
            foreach ($array as $track => $fileList) {
                $newConcatFile = preg_replace(
                    '/concat_audio.txt$/i',
                    sprintf(
                        'concat_audio_%s.txt',
                        $track
                    ),
                    $concatFile
                );
                $this->concatAudioFile[$track] = $newConcatFile;
                $this->mergeAudioName[$track] = preg_replace('/\/merge\./i', sprintf('/merge_%s.', $track),
                    $defaultAudioName);
                $this->setConcatFileArray($fileList, $newConcatFile);
            }
            if (is_array($this->concatAudioFile) && count($this->concatAudioFile) > 1) {
                $this->useMetaTitles = true;
            }
        }
        return $this;
    }

    /**
     * @param $array
     * @param $concatFile
     */
    private function setConcatFileArray($array, $concatFile)
    {
        foreach ($array as $file) {
            $setFileName = sprintf("file '%s'\n", $file);
            if ((bool)preg_match('/dec/i', $file)) {
                file_put_contents($concatFile, $setFileName, FILE_APPEND);
            }
            if ((bool)preg_match('/audio/i', $file)) {
                file_put_contents($concatFile, $setFileName, FILE_APPEND);
            }
        }
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
                    $concatString = "-y -f concat -safe 0 -i %s -c:v copy %s";
                    break;
                case 'audio':
                    $concatString = "-y -f concat -safe 0 -i %s -c:a copy %s";
                    break;
                default:
            }
            if (!empty($concatString)) {
                $this->ffExec(
                    sprintf(
                        $concatString,
                        $concatFile,
                        $mergeName
                    )
                );
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
        if (file_exists($concatVideoFile)) {
            $mergeString = sprintf('-y -i %s ', $concatVideoFile);
            $mapString = '-map 0:v ';
            $metaString = '';
            $mapCounter = 0;
            $metaCounter = 0;
            foreach ($concatAudioFile as $concatMap => $concatAudioFile) {
                if (file_exists($concatAudioFile)) {
                    $mapCounter++;
                    if ($this->useMetaTitles) {
                        // Define each track with metadata if there are more than one.
                        $metaString .= sprintf('-metadata:s:a:%d title="%s" ', $metaCounter, $concatMap);
                        // Counting in this case is a bit different to the mapping. So we're in lazy mode.
                        $metaCounter++;
                    }
                    $mergeString .= sprintf('-i %s ', $concatAudioFile);
                    $mapString .= sprintf('-map %s:a ', $mapCounter);
                }
            }
            $mergeString .= $mapString;
            $mergeString .= $metaString;
            $mergeString .= sprintf('-c:v copy -c:a aac %s', $mergedVideoName);

            $this->ffExec($mergeString);
        }

        return $this;
    }

    /**
     * @param $outputName
     * @return FileHandler
     */
    public function setOutPutName($outputName)
    {
        $this->outputName = $outputName;

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
}
