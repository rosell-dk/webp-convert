<?php

namespace WebPConvert\Convert;

use WebPConvert\Exceptions\ConverterNotFoundException;
use WebPConvert\Exceptions\CreateDestinationFileException;
use WebPConvert\Exceptions\CreateDestinationFolderException;
use WebPConvert\Exceptions\InvalidFileExtensionException;
use WebPConvert\Exceptions\TargetNotFoundException;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;
use WebPConvert\Converters\Exceptions\ConversionDeclinedException;

class BaseConverter
{
    public $source;
    public $destination;
    public $options;
    public $logger;
    public $beginTime;

    public static $allowedExtensions = ['jpg', 'jpeg', 'png'];

    public static $defaultOptions = [
        'quality' => 'auto',
        'max-quality' => 85,
        'default-quality' => 75,
        'metadata' => 'none',
        'method' => 6,
        'low-memory' => false,
        'lossless' => false,
    ];

    function __construct($source, $destination, $options = [], $logger = null) {
        if (!isset($logger)) {
            $logger = new \WebPConvert\Loggers\VoidLogger();
        }
        $this->source = $source;
        $this->destination = $destination;
        $this->options = $options;
        $this->logger = $logger;
    }

    public static function createInstance($source, $destination, $options, $logger)
    {
        return new static($source, $destination, $options, $logger);
    }

    public static function convert($source, $destination, $options = [], $logger = null)
    {
        $instance = self::createInstance($source, $destination, $options, $logger);

        $instance->prepareConvert();
        $instance->doConvert();
        $instance->finalizeConvert();

        //echo $instance->id;
    }

    public function logLn($msg, $style = '')
    {
        $this->logger->logLn($msg, $style);
    }

    public function logLnLn($msg)
    {
        $this->logger->logLnLn($msg);
    }

    public function ln()
    {
        $this->logger->ln();
    }

    public function log($msg)
    {
        $this->logger->log($msg);
    }

    public static function getExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension);
    }

    public function getSourceExtension() {
        return self::getExtension($this->source);
    }

    public function prepareConvert()
    {
        $this->beginTime = microtime(true);

        if (!isset($this->options['_skip_basic_validations'])) {
            // Run basic validations (if source exists and if file extension is valid)
            $this->runBasicValidations();

            // Prepare destination folder (may throw exception)
            $this->createWritableDestinationFolder();
        }

        // Prepare options
        $this->prepareOptions();
    }

    public function runBasicValidations()
    {
        // Check if source exists
        if (!@file_exists($this->source)) {
            throw new TargetNotFoundException('File or directory not found: ' . $this->source);
        }

        // Check if the provided file's extension is valid
        $fileExtension = $this->getSourceExtension();
        if (!in_array(strtolower($fileExtension), self::$allowedExtensions)) {
            throw new InvalidFileExtensionException('Unsupported file extension: ' . $fileExtension);
        }
    }

    public function prepareOptions()
    {
        $defaultOptions = self::$defaultOptions;

        // -  Merge defaults of the converters extra options into the standard default options.
        $defaultOptions = array_merge($defaultOptions, array_column(static::$extraOptions, 'default', 'name'));

        // -  Merge $defaultOptions into provided options
        $this->options = array_merge($defaultOptions, $this->options);

        // Prepare quality option (sets "_calculated_quality" option)
        $this->processQualityOption();

        $fileExtension = $this->getSourceExtension();
        if ($fileExtension == 'png') {

            // skip png's ?
            if ($this->options['skip-pngs']) {
                throw new ConversionDeclinedException(
                    'PNG file skipped (configured to do so)'
                );
            }

            // Force lossless option to true for PNG images
            $this->options['lossless'] = true;
        }


    }

    // Creates folder in provided path & sets correct permissions
    // also deletes the file at filePath (if it already exists)
    public function createWritableDestinationFolder()
    {
        $filePath = $this->destination;

        $folder = dirname($filePath);
        if (!@file_exists($folder)) {
            // TODO: what if this is outside open basedir?
            // see http://php.net/manual/en/ini.core.php#ini.open-basedir

            // First, we have to figure out which permissions to set.
            // We want same permissions as parent folder
            // But which parent? - the parent to the first missing folder

            $parentFolders = explode('/', $folder);
            $poppedFolders = [];

            while (!(@file_exists(implode('/', $parentFolders))) && count($parentFolders) > 0) {
                array_unshift($poppedFolders, array_pop($parentFolders));
            }

            // Retrieving permissions of closest existing folder
            $closestExistingFolder = implode('/', $parentFolders);
            $permissions = @fileperms($closestExistingFolder) & 000777;
            $stat = @stat($closestExistingFolder);

            // Trying to create the given folder (recursively)
            if (!@mkdir($folder, $permissions, true)) {
                throw new CreateDestinationFolderException('Failed creating folder: ' . $folder);
            }

            // `mkdir` doesn't always respect permissions, so we have to `chmod` each created subfolder
            foreach ($poppedFolders as $subfolder) {
                $closestExistingFolder .= '/' . $subfolder;
                // Setting directory permissions
                if ($permissions !== false) {
                    @chmod($folder, $permissions);
                }
                if ($stat !== false) {
                    if (isset($stat['uid'])) {
                        @chown($folder, $stat['uid']);
                    }
                    if (isset($stat['gid'])) {
                        @chgrp($folder, $stat['gid']);
                    }
                }
            }
        }

        if (@file_exists($filePath)) {
            // A file already exists in this folder...
            // We delete it, to make way for a new webp
            if (!@unlink($filePath)) {
                throw new CreateDestinationFileException(
                    'Existing file cannot be removed: ' . basename($filePath)
                );
            }
        }

        return true;
    }

    /* Try to detect quality of jpeg.
       If not possible, nothing is returned (null). Otherwise quality is returned (int)
        */
    public static function detectQualityOfJpg($filename)
    {
        // Try Imagick extension
        if (extension_loaded('imagick') && class_exists('\\Imagick')) {
            $img = new \Imagick($filename);

            // The required function is available as from PECL imagick v2.2.2
            if (method_exists($img, 'getImageCompressionQuality')) {
                return $img->getImageCompressionQuality();
            }
        }

        // Gmagick extension doesn't seem to support this (yet):
        // https://bugs.php.net/bug.php?id=63939

        if (function_exists('shell_exec')) {
            // Try Imagick
            $quality = shell_exec("identify -format '%Q' " . $filename);
            if ($quality) {
                return intval($quality);
            }

            // Try GraphicsMagick
            $quality = shell_exec("gm identify -format '%Q' " . $filename);
            if ($quality) {
                return intval($quality);
            }
        }
    }

    /**
     *  Returns quality, as a number.
     *  If quality was set to auto, you get the detected quality / fallback quality, otherwise
     *  you get whatever it was set to.
     *  Use this, if you simply want quality as a number, and have no handling of "auto" quality
     */
    public function getCalculatedQuality()
    {
        return $this->options['_calculated_quality'];
    }

    public function isQualitySetToAutoAndDidQualityDetectionFail()
    {
        return isset($this->options['_quality_could_not_be_detected']);
    }

    public function processQualityOption()
    {
        if (isset($this->options['_calculated_quality'])) {
            return;
        }
        if ($this->options['quality'] == 'auto') {
            $q = self::detectQualityOfJpg($this->source);
            //$this->log('Quality set to auto... Quality of source: ');
            if (!$q) {
                $q = $this->options['default-quality'];
                $this->logLn(
                    'Quality of source could not be established (Imagick or GraphicsMagick is required)' .
                    ' - Using default instead (' . $this->options['default-quality'] . ').'
                );

                // this allows the converter to know (by calling isQualitySetToAutoAndDidQualityDetectionFail())
                // that feature is btw used by wpc and imagick
                $this->options['_quality_could_not_be_detected'] = true;
            } else {
                if ($q > $this->options['max-quality']) {
                    $this->logLn(
                        'Quality of source is ' . $q . '. ' .
                        'This is higher than max-quality, so using that instead (' . $this->options['max-quality'] . ')'
                    );
                } else {
                    $this->logLn('Quality set to same as source: ' . $q);
                }
            }
            //$this->ln();
            $q = min($q, $this->options['max-quality']);

            $this->options['_calculated_quality'] = $q;
        //$this->logLn('Using quality: ' . $this->options['quality']);
        } else {
            $this->logLn(
                'Quality: ' . $this->options['quality'] . '. ' .
                'Consider setting quality to "auto" instead. It is generally a better idea'
            );
            $this->options['_calculated_quality'] = $this->options['quality'];
        }
        //$this->ln();
    }

    public function finalizeConvert()
    {
        $source = $this->source;
        $destination = $this->destination;

        if (!@file_exists($this->destination)) {
            throw new ConverterFailedException('Destination file is not there');
        } else {
            if (!isset($this->options['_suppress_success_message'])) {
                $this->logLn(
                    'Successfully converted image in ' .
                    round((microtime(true) - $this->beginTime) * 1000) . ' ms'
                );

                $sourceSize = @filesize($source);
                if ($sourceSize !== false) {
                    $msg = 'Reduced file size with ' .
                        round((filesize($source) - filesize($destination))/filesize($source) * 100) . '% ';

                    if ($sourceSize < 10000) {
                        $msg .= '(went from ' . round(filesize($source)) . ' bytes to ';
                        $msg .= round(filesize($destination)) . ' bytes)';
                    } else {
                        $msg .= '(went from ' . round(filesize($source)/1024) . ' kb to ';
                        $msg .= round(filesize($destination)/1024) . ' kb)';
                    }
                    $this->logLn($msg);
                }
            }
        }

    }

}
