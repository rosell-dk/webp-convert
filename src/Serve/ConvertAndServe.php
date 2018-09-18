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
    public static $defaultOptions = [
        'fail' => 'original',
        'fail-when-original-unavailable' => '404',

        'show-report' => false,
        'reconvert' => false,
        'original' => false,
        'add-x-header-status' => true,
        'add-x-header-options' => false,
        'add-vary-header' => true,
        'converters' =>  ['cwebp', 'gd', 'imagick']
    ];

    private static function addHeadersPreventingCaching()
    {
        // Prevent caching
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    private static function addXStatusHeader($text, $options)
    {
        if ($options['add-x-header-status']) {
            header('X-WebP-Convert-Status: ' . $text);
        }
    }

    private static function addXOptionsHeader($options)
    {
        if (!$options['add-x-header-options']) {
            return;
        }
        header('X-WebP-Convert-Options:' . Report::getPrintableOptionsAsString($options));
    }

    protected static function serve404()
    {
        $protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'HTTP/1.0';
        header($protocol . " 404 Not Found");
    }

    protected static function serveOriginal($source)
    {
        $arr = explode('.', $source);
        $ext = array_pop($arr);
        switch (strtolower($ext)) {
            case 'jpg':
            case 'jpeg':
                header('Content-type: image/jpeg');
                break;
            case 'png':
                header('Content-type: image/png');
                break;
        }

        if (@readfile($source) === false) {
            header('X-WebP-Convert: Could not read file');
            return false;
        }
        return true;
    }

    protected static function serveErrorMessageImage($msg)
    {
        // Generate image containing error message
        header('Content-type: image/gif');

        // Prevent caching image
        self::addHeadersPreventingCaching();

        // TODO: handle if this fails...
        $image = imagecreatetruecolor(620, 200);
        imagestring($image, 1, 5, 5, $msg, imagecolorallocate($image, 233, 214, 291));
        // echo imagewebp($image);
        echo imagegif($image);
        imagedestroy($image);
    }

    protected static function fail($description, $failArgs, $critical = false)
    {
        $options = $failArgs['options'];
        //print_r($options);
        self::addXStatusHeader('Failed (' . $description . ')', $options);

        $action = $critical ? $options['fail-when-original-unavailable'] : $options['fail'];

        $title = 'Conversion failed';
        switch ($action) {
            case 'original':
                if (!self::serveOriginal($source)) {
                    self::serve404();
                };
                break;
            case '404':
                self::serve404();
                break;
            case 'report-as-image':
                // todo: handle if this fails
                self::serveErrorMessageImage($title . '. ' . $description);
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
     * Main method
     */
    public static function convertAndServe($source, $destination, $options)
    {
        // For backward compatability:
        if (isset($options['critical-fail']) && !isset($options['fail-when-original-unavailable'])) {
            $options['fail-when-original-unavailable'] = $options['critical-fail'];
        }

        $options = array_merge(self::$defaultOptions, $options);

        $failArgs = [
            'source' => $source,
            'destination' => $destination,
            'options' => $options,
        ];

        self::addXOptionsHeader($options);

        if (empty($source)) {
            self::criticalFail('missing source argument', $failArgs);
            return false;
        }
        if (@!file_exists($source)) {
            self::criticalFail('source not found', $failArgs);
            return false;
        }
        if (empty($destination)) {
            self::fail('missing destination argument', $failArgs);
            return false;
        }
//Report::convertAndReport($source, $destination, $options);
        if ($options['show-report']) {
            self::addXStatusHeader('Reporting...', $options);
            Report::convertAndReport($source, $destination, $options);
            return true;  // yeah, lets say that a report is always a success, even if conversion is a failure
        }

        if ($options['original']) {
            self::addXStatusHeader('Serving original image (was explicitly told to)', $options);
            if (!self::serveOriginal($source)) {
                self::criticalFail('could not read source file', $failArgs);
                return false;
            }
            return true;
        }


        if (@file_exists($destination)) {

            if (ServeExistingOrConvert::shouldWeServeExisting($source, $destination, $options)) {
                return ServeExistingOrConvert::serveExisting($destination, $options);
            }

            // Serve source file if it is lighter than destination
            $filesizeDestination = @filesize($destination);
            $filesizeSource = @filesize($source);
            if (($filesizeSource !== false) &&
                ($filesizeDestination !== false) &&
                ($filesizeDestination > $filesizeSource)) {
                self::addXStatusHeader('Serving original image - because it is smaller than the converted!', $options);
                return self::serveOriginal($source);
            }
        }

        // Check if source file if it has been modified (we must check before doing the conversion...)
        $originalHasChanged = false;
        if (@file_exists($destination)) {
            $timestampSource = @filemtime($source);
            $timestampDestination = @filemtime($destination);
            if (($timestampSource !== false) &&
                ($timestampDestination !== false) &&
                ($timestampSource > $timestampDestination)) {
                $originalHasChanged = true;
            }
        }


        $failAction = $options['fail'];
        $criticalFailAction = $options['fail-when-original-unavailable'];

        $criticalFail = false;
        $success = false;
        $bufferLogger = new BufferLogger();

        try {
            $success = WebPConvert::convert($source, $destination, $options, $bufferLogger);

            if ($success) {
                header('Content-type: image/webp');
                if ($originalHasChanged) {
                    self::addXStatusHeader('Serving freshly converted image (the original had changed)', $options);
                } else {
                    self::addXStatusHeader('Serving freshly converted image', $options);
                }
                ServeExistingOrConvert::addVaryHeader($options);

                // Should we add Content-Length header?
                // header('Content-Length: ' . filesize($file));
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
        // header('X-WebP-Convert-And-Serve-Details: ' . $bufferLogger->getText());

        self::fail($description, $failArgs);
        return false;
        //echo '<p>This is how conversion process went:</p>' . $bufferLogger->getHtml();

    }

}
