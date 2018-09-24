<?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;
use WebPConvert\Loggers\BufferLogger;
use WebPConvert\Converters\ConverterHelper;
use WebPConvert\ServeExistingOrConvert;
use WebPConvert\Serve\Report;

//use WebPConvert\Loggers\EchoLogger;

class ConvertAndServe
{
    public $source;
    public $destination;
    public $options;

    function __construct($source, $destination, $options) {
        $this->source = $source;
        $this->destination = $destination;
        $this->options = array_merge(self::$defaultOptions, $options);
        //print_r($this->options);
    }

    public static $defaultOptions = [
        'fail' => 'original',
        'fail-when-original-unavailable' => '404',

        'show-report' => false,
        'reconvert' => false,
        'serve-original' => false,
        'add-x-header-status' => true,
        'add-x-header-options' => false,
        'add-vary-header' => true,
        'add-content-type-header' => true,
        'converters' =>  ['cwebp', 'gd', 'imagick']
    ];

    private static function header($header, $replace = true)
    {
        header($header, $replace);
    }

    private static function addHeadersPreventingCaching()
    {
        // Prevent caching
        self::header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        self::header("Cache-Control: post-check=0, pre-check=0", false);
        self::header("Pragma: no-cache");
    }

    private static function addXStatusHeader($text, $options)
    {
        if ($options['add-x-header-status']) {
            self::header('X-WebP-Convert-Status: ' . $text, true);
        }
    }

    private static function addXOptionsHeader($options)
    {
        if (!$options['add-x-header-options']) {
            return;
        }
        self::header('X-WebP-Convert-Options:' . Report::getPrintableOptionsAsString($options));
    }

    protected static function serve404()
    {
        $protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'HTTP/1.0';
        self::header($protocol . " 404 Not Found");
    }

    protected static function serveOriginal($source, $options)
    {
        if ($options['add-content-type-header']) {
            $arr = explode('.', $source);
            $ext = array_pop($arr);
            switch (strtolower($ext)) {
                case 'jpg':
                case 'jpeg':
                    self::header('Content-type: image/jpeg');
                    break;
                case 'png':
                    self::header('Content-type: image/png');
                    break;
            }
        }

        if (@readfile($source) === false) {
            self::header('X-WebP-Convert: Could not read file');
            return false;
        }
        return true;
    }

    public static function serveFreshlyConverted(
        $source,
        $destination,
        $options,
        $additionalInfo,
        $callbackBeforeServing = null
    ) {
        $failArgs = [$source, $destination, $options];

        $criticalFail = false;
        $success = false;
        $bufferLogger = new BufferLogger();

        try {
            $success = WebPConvert::convert($source, $destination, $options, $bufferLogger);

            if (!is_null($callbackBeforeServing)) {
                $continue = (call_user_func(
                    $callbackBeforeServing,
                    'fresh-conversion-' . ($success ? 'successful' : 'failed'),
                    $additionalInfo
                ) !== false);
                if (!$continue) {
                    return;
                }
            }


            if ($success) {
                if ($options['add-content-type-header']) {
                    self::header('Content-type: image/webp');
                }
                if ($additionalInfo == 'explicitly-told-to') {
                    self::addXStatusHeader(
                        'Serving freshly converted image (was explicitly told to reconvert)',
                        $options
                    );
                } elseif ($additionalInfo == 'source-modified') {
                    self::addXStatusHeader(
                        'Serving freshly converted image (the original had changed)',
                        $options
                    );
                } elseif ($additionalInfo == 'no-existing') {
                    self::addXStatusHeader(
                        'Serving freshly converted image (there were no existing to serve)',
                        $options
                    );
                } else {
                    self::addXStatusHeader(
                        'Serving freshly converted image (dont know why!)',
                        $options
                    );
                }

                if ($options['add-vary-header']) {
                    self::header('Vary: Accept');
                }

                // Should we add Content-Length header?
                // self::header('Content-Length: ' . filesize($file));
                if (@readfile($destination)) {
                    return true;
                } else {
                    self::fail('could not read the freshly converted file', $failArgs);
                    return false;
                }
            } else {
                $description = 'No converters are operational';
                $msg = '';
            }
        } catch (\WebPConvert\Exceptions\InvalidFileExtensionException $e) {
            $criticalFail = true;
            $description = 'Failed (invalid file extension)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\TargetNotFoundException $e) {
            $criticalFail = true;
            $description = 'Failed (source file not found)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
            // No converters could convert the image. At least one converter failed, even though it appears to be
            // operational
            $description = 'Failure (no converters could convert the image)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
            // (no converters could convert the image. At least one converter declined
            $description = 'Failure (no converters could/wanted to convert the image)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\ConverterNotFoundException $e) {
            $description = 'Failure (a converter was not found!)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\CreateDestinationFileException $e) {
            $description = 'Failure (cannot create destination file)';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\CreateDestinationFolderException $e) {
            $description = 'Failure (cannot create destination folder)';
            $msg = $e->getMessage();
        } catch (\Exception $e) {
            $description = 'Failure (an unanticipated exception was thrown)';
            $msg = $e->getMessage();
        }

        // Next line is commented out, because we need to be absolute sure that the details does not violate syntax
        // We could either try to filter it, or we could change WebPConvert, such that it only provides safe texts.
        // self::header('X-WebP-Convert-And-Serve-Details: ' . $bufferLogger->getText());

        if (!is_null($callbackBeforeServing)) {
            if (call_user_func($callbackBeforeServing,
                'fresh-conversion-failed',
                $additionalInfo,
                $description,
                $msg
            ) === false) {
                return;
            };
        }

        self::fail($description, $failArgs, $criticalFail);
        return false;
        //echo '<p>This is how conversion process went:</p>' . $bufferLogger->getHtml();
    }

    protected static function serveErrorMessageImage($msg, $options)
    {
        // Generate image containing error message
        if ($options['add-content-type-header']) {
            self::header('Content-type: image/gif');
        }

        // TODO: handle if this fails...
        $image = imagecreatetruecolor(620, 200);
        imagestring($image, 1, 5, 5, $msg, imagecolorallocate($image, 233, 214, 291));
        // echo imagewebp($image);
        echo imagegif($image);
        imagedestroy($image);
    }

    protected static function fail($description, $failArgs, $critical = false)
    {
        list ($source, $destination, $options) = $failArgs;

        //print_r($options);
        self::addXStatusHeader('Failed (' . $description . ')', $options);

        // Prevent caching
        self::addHeadersPreventingCaching();

        $action = $critical ? $options['fail-when-original-unavailable'] : $options['fail'];

        $title = 'Conversion failed';
        switch ($action) {
            case 'original':
                if (!self::serveOriginal($source, $options)) {
                    self::serve404();
                };
                break;
            case '404':
                self::serve404();
                break;
            case 'report-as-image':
                // todo: handle if this fails
                self::serveErrorMessageImage($title . '. ' . $description, $options);
                break;
            case 'report':
                echo '<h1>' . $title . '</h1>' . $description;
                break;
        }
    }

    protected static function criticalFail($description, $failArgs)
    {
        return self::fail($description, $failArgs, true);
    }

    /**
     *  Decides what to serve.
     *  Returns array. First item is what to do, second is additional info.
     *  First item can be one of these:
     *  - "destination"  (serve existing converted image at the destination path)
     *  - "source"
     *       - "explicitly-told-to"
     *       - "source-lighter"
     *  - "fresh-conversion" (note: this may still fail)
     *       - "explicitly-told-to"
     *       - "source-modified"
     *       - "no-existing"
     *  - "fail"
     *        - missing-destination-argument
     *  - "critical-fail"   (a failure where the source file cannot be served)
     *        - "missing-source-argument"
     *        - "source-not-found"
     *  - "report"
     */
    public static function decideWhatToServe($source, $destination, $options)
    {
        $options = array_merge(self::$defaultOptions, $options);

        if (empty($source)) {
            return ['critical-fail', 'missing-source-argument'];
        }
        if (@!file_exists($source)) {
            return ['critical-fail', 'source-not-found'];
        }
        if (empty($destination)) {
            return ['fail', 'missing-destination-argument'];
        }
        if ($options['show-report']) {
            return ['report', ''];
        }
        if ($options['serve-original']) {
            return ['source', 'explicitly-told-to'];
        }
        if ($options['reconvert']) {
            return ['fresh-conversion', 'explicitly-told-to'];
        }

        if (@file_exists($destination)) {
            // Reconvert if source file is newer than destination
            $timestampSource = @filemtime($source);
            $timestampDestination = @filemtime($destination);
            if (($timestampSource !== false) &&
                ($timestampDestination !== false) &&
                ($timestampSource > $timestampDestination)) {
                return ['fresh-conversion', 'source-modified'];
            }

            // Serve source if it is smaller than destination
            $filesizeDestination = @filesize($destination);
            $filesizeSource = @filesize($source);
            if (($filesizeSource !== false) &&
                ($filesizeDestination !== false) &&
                ($filesizeDestination > $filesizeSource)) {
                return ['source', 'source-lighter'];
            }

            // Destination exists, and there is no reason left not to serve it
            return ['destination', ''];
        } else {
            return ['fresh-conversion', 'no-existing'];
        }
    }

    /**
     *  Serve the thing specified in $decisionArr
     *  You can get a valid $decisionArr by calling the decideWhatToServe() method
     *  You may for example do this in order to add your own headers based on
     *  decideWhatToServe, before proccessing
     */
    public static function serveThis($source, $destination, $options, $decisionArr, $callbackBeforeServing = null)
    {
        $options = array_merge(self::$defaultOptions, $options);
        $failArgs = [$source, $destination, $options];
        list($whatToServe, $additionalInfo) = $decisionArr;

        self::addXOptionsHeader($options);

        $continue = true;
        if ($whatToServe != 'fresh-conversion') {
            if (!is_null($callbackBeforeServing)) {
                $continue = (call_user_func($callbackBeforeServing, $whatToServe, $additionalInfo) !== false);
            }
        }
        if (!$continue) {
            return;
        }
        switch ($whatToServe) {
            case 'destination':
                return ServeExistingOrConvert::serveExisting($destination, $options);
            case 'source':
                if ($additionalInfo == 'explicitly-told-to') {
                    self::addXStatusHeader(
                        'Serving original image (was explicitly told to)',
                        $options
                    );
                } else {
                    self::addXStatusHeader(
                        'Serving original image (it is smaller than the already converted)',
                        $options
                    );
                }
                if (!self::serveOriginal($source, $options)) {
                    self::criticalFail('could not read source file', $failArgs);
                    return false;
                }
                return true;
            case 'fresh-conversion':
                return self::serveFreshlyConverted(
                    $source,
                    $destination,
                    $options,
                    $additionalInfo,
                    $callbackBeforeServing
                );
                break;
            case 'critical-fail':
                self::criticalFail($additionalInfo, $failArgs);
                return false;
            case 'fail':
                self::fail($additionalInfo, $failArgs);
                return false;
            case 'report':
                self::addXStatusHeader('Reporting...', $options);
                Report::convertAndReport($source, $destination, $options);
                return true;  // yeah, lets say that a report is always a success, even if conversion is a failure
        }
    }

    //self::decideWhatToServe($source, $destination, $options)
    /**
     * Main method
     */
    public static function convertAndServe($source, $destination, $options, $callbackBeforeServing = null)
    {
        ServeExistingOrConvert::setErrorReporting($options);

        // For backward compatability:
        if (isset($options['critical-fail']) && !isset($options['fail-when-original-unavailable'])) {
            $options['fail-when-original-unavailable'] = $options['critical-fail'];
        }

        $decisionArr = self::decideWhatToServe($source, $destination, $options);
        return self::serveThis($source, $destination, $options, $decisionArr, $callbackBeforeServing);
    }
}
