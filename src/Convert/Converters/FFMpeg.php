<?php

namespace WebPConvert\Convert\Converters;

use WebPConvert\Convert\Converters\AbstractConverter;
use WebPConvert\Convert\Converters\ConverterTraits\ExecTrait;
use WebPConvert\Convert\Converters\ConverterTraits\EncodingAutoTrait;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

//use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

/**
 * Convert images to webp by calling imagemagick binary.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class FFMpeg extends AbstractConverter
{
    use ExecTrait;
    use EncodingAutoTrait;

    protected function getUnsupportedDefaultOptions()
    {
        return [
            'alpha-quality',
            'auto-filter',
            'encoding',
            'low-memory',
            'near-lossless',
            'preset',
            'sharp-yuv',
            'size-in-percentage',
            'use-nice'
        ];
    }

    private function getPath()
    {
        if (defined('WEBPCONVERT_FFMPEG_PATH')) {
            return constant('WEBPCONVERT_FFMPEG_PATH');
        }
        if (!empty(getenv('WEBPCONVERT_FFMPEG_PATH'))) {
            return getenv('WEBPCONVERT_FFMPEG_PATH');
        }
        return 'ffmpeg';
    }

    public function isInstalled()
    {
        exec($this->getPath() . ' -version 2>&1', $output, $returnCode);
        return ($returnCode == 0);
    }

    // Check if webp delegate is installed
    public function isWebPDelegateInstalled()
    {
        exec($this->getPath() . ' -version 2>&1', $output, $returnCode);
        foreach ($output as $line) {
            if (preg_match('# --enable-libwebp#i', $line)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check (general) operationality of imagack converter executable
     *
     * @throws SystemRequirementsNotMetException  if system requirements are not met
     */
    public function checkOperationality()
    {
        $this->checkOperationalityExecTrait();

        if (!$this->isInstalled()) {
            throw new SystemRequirementsNotMetException(
                'ffmpeg is not installed (cannot execute: "' . $this->getPath() . '")'
            );
        }
        if (!$this->isWebPDelegateInstalled()) {
            throw new SystemRequirementsNotMetException('ffmpeg was compiled without libwebp');
        }
    }

    /**
     * Build command line options
     *
     * @return string
     */
    private function createCommandLineOptions()
    {
        // PS: Available webp options for ffmpeg are documented here:
        // https://www.ffmpeg.org/ffmpeg-codecs.html#libwebp

        $commandArguments = [];

        $commandArguments[] = '-i';
        $commandArguments[] = escapeshellarg($this->source);

        // preset. Appears first in the list as recommended in the cwebp docs
        if (!is_null($this->options['preset'])) {
            if ($this->options['preset'] != 'none') {
                $commandArguments[] = '-preset ' . $this->options['preset'];
            }
        }

        // Overwrite existing files?, yes!
        $commandArguments[] = '-y';

        if ($this->isQualityDetectionRequiredButFailing()) {
            // quality:auto was specified, but could not be determined.
            // we cannot apply the max-quality logic, but we can provide auto quality
            // simply by not specifying the quality option.
        } else {
            $commandArguments[] = '-qscale ' . escapeshellarg($this->getCalculatedQuality());
        }
        if ($this->options['encoding'] == 'lossless') {
            $commandArguments[] = '-lossless 1';
        } else {
            $commandArguments[] = '-lossless 0';
        }

        if ($this->options['metadata'] == 'none') {
            // Unfortunately there seems to be no easy solution available for removing all metadata.
        }

        // compression_level maps to method, according to https://www.ffmpeg.org/ffmpeg-codecs.html#libwebp
        $commandArguments[] = '-compression_level ' . $this->options['method'];

        $commandArguments[] = escapeshellarg($this->destination);


        return implode(' ', $commandArguments);
    }

    protected function doActualConvert()
    {
        //$this->logLn($this->getVersion());

        $command = $this->getPath() . ' ' . $this->createCommandLineOptions() . ' 2>&1';

        $useNice = (($this->options['use-nice']) && self::hasNiceSupport()) ? true : false;
        if ($useNice) {
            $this->logLn('using nice');
            $command = 'nice ' . $command;
        }
        $this->logLn('Executing command: ' . $command);
        exec($command, $output, $returnCode);

        $this->logExecOutput($output);
        if ($returnCode == 0) {
            $this->logLn('success');
        } else {
            $this->logLn('return code: ' . $returnCode);
        }

        if ($returnCode == 127) {
            throw new SystemRequirementsNotMetException('ffmpeg is not installed');
        }
        if ($returnCode != 0) {
            throw new SystemRequirementsNotMetException('The exec call failed');
        }
    }
}
