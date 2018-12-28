<?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;
use WebPConvert\Loggers\BufferLogger;
use WebPConvert\Converters\ConverterHelper;
use WebPConvert\Serve\Report;

/**
 * This class must serves a converted image (either a fresh convertion, the destionation, or
 * the original). Upon failure, the fail action given in the options will be exectuted
 */
class ServeConverted extends ServeBase
{

    private function addXOptionsHeader()
    {
        if ($this->options['add-x-header-options']) {
            $this->header('X-WebP-Convert-Options:' . Report::getPrintableOptionsAsString($this->options));
        }
    }

    private function addHeadersPreventingCaching()
    {
        $this->header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        $this->header("Cache-Control: post-check=0, pre-check=0", false);
        $this->header("Pragma: no-cache");
    }

    public function serve404()
    {
        $protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'HTTP/1.0';
        $this->header($protocol . " 404 Not Found");
    }

    public function serveOriginal()
    {
        if (!$this->callAboutToServeImageCallBack('source')) {
            return true;    // we shall not trigger the fail callback
        }

        if ($this->options['add-content-type-header']) {
            $arr = explode('.', $this->source);
            $ext = array_pop($arr);
            switch (strtolower($ext)) {
                case 'jpg':
                case 'jpeg':
                    $this->header('Content-type: image/jpeg');
                    break;
                case 'png':
                    $this->header('Content-type: image/png');
                    break;
            }
        }

        $this->addVaryHeader();

        switch ($this->whyServingThis) {
            case 'source-lighter':
            case 'explicitly-told-to':
                $this->addCacheControlHeader();
                $this->addLastModifiedHeader(@filemtime($this->source));
                break;
            default:
                $this->addHeadersPreventingCaching();
        }

        if (@readfile($this->source) === false) {
            $this->header('X-WebP-Convert: Could not read file');
            return false;
        }
        return true;
    }

    public function serveFreshlyConverted()
    {

        $criticalFail = false;
        $success = false;
        $bufferLogger = new BufferLogger();

        try {
            $success = WebPConvert::convert($this->source, $this->destination, $this->options, $bufferLogger);

            if ($success) {
                // Serve source if it is smaller than destination
                $filesizeDestination = @filesize($this->destination);
                $filesizeSource = @filesize($this->source);
                if (($filesizeSource !== false) &&
                    ($filesizeDestination !== false) &&
                    ($filesizeDestination > $filesizeSource)) {
                    $this->whatToServe = 'original';
                    $this->whyServingThis = 'source-lighter';
                    return $this->serveOriginal();
                }

                if (!$this->callAboutToServeImageCallBack('fresh-conversion')) {
                    return;
                }
                if ($this->options['add-content-type-header']) {
                    $this->header('Content-type: image/webp');
                }
                if ($this->whyServingThis == 'explicitly-told-to') {
                    $this->addXStatusHeader(
                        'Serving freshly converted image (was explicitly told to reconvert)'
                    );
                } elseif ($this->whyServingThis == 'source-modified') {
                    $this->addXStatusHeader(
                        'Serving freshly converted image (the original had changed)'
                    );
                } elseif ($this->whyServingThis == 'no-existing') {
                    $this->addXStatusHeader(
                        'Serving freshly converted image (there were no existing to serve)'
                    );
                } else {
                    $this->addXStatusHeader(
                        'Serving freshly converted image (dont know why!)'
                    );
                }

                if ($this->options['add-vary-header']) {
                    $this->header('Vary: Accept');
                }

                if ($this->whyServingThis == 'no-existing') {
                    $this->addCacheControlHeader();
                } else {
                    $this->addHeadersPreventingCaching();
                }
                $this->addLastModifiedHeader(time());

                // Should we add Content-Length header?
                // $this->header('Content-Length: ' . filesize($file));
                if (@readfile($this->destination)) {
                    return true;
                } else {
                    $this->fail('Error', 'could not read the freshly converted file');
                    return false;
                }
            } else {
                $description = 'No converters are operational';
                $msg = '';
            }
        } catch (\WebPConvert\Exceptions\InvalidFileExtensionException $e) {
            $criticalFail = true;
            $description = 'Invalid file extension';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\TargetNotFoundException $e) {
            $criticalFail = true;
            $description = 'Source file not found';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Converters\Exceptions\ConverterFailedException $e) {
            // No converters could convert the image. At least one converter failed, even though it appears to be
            // operational
            $description = 'No converters could convert the image';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Converters\Exceptions\ConversionDeclinedException $e) {
            // (no converters could convert the image. At least one converter declined
            $description = 'No converters could/wanted to convert the image';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\ConverterNotFoundException $e) {
            $description = 'A converter was not found!';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\CreateDestinationFileException $e) {
            $description = 'Cannot create destination file';
            $msg = $e->getMessage();
        } catch (\WebPConvert\Exceptions\CreateDestinationFolderException $e) {
            $description = 'Cannot create destination folder';
            $msg = $e->getMessage();
        } catch (\Exception $e) {
            $description = 'An unanticipated exception was thrown';
            $msg = $e->getMessage();
        }

        // Next line is commented out, because we need to be absolute sure that the details does not violate syntax
        // We could either try to filter it, or we could change WebPConvert, such that it only provides safe texts.
        // $this->header('X-WebP-Convert-And-Serve-Details: ' . $bufferLogger->getText());

        $this->fail('Conversion failed', $description, $criticalFail);
        return false;
        //echo '<p>This is how conversion process went:</p>' . $bufferLogger->getHtml();
    }

    protected function serveErrorMessageImage($msg)
    {
        // Generate image containing error message
        if ($this->options['add-content-type-header']) {
            $this->header('Content-type: image/gif');
        }

        // TODO: handle if this fails...
        $image = imagecreatetruecolor(620, 200);
        imagestring($image, 1, 5, 5, $msg, imagecolorallocate($image, 233, 214, 291));
        // echo imagewebp($image);
        echo imagegif($image);
        imagedestroy($image);
    }

    protected function fail($title, $description, $critical = false)
    {
        $action = $critical ? $this->options['fail-when-original-unavailable'] : $this->options['fail'];

        if (isset($this->options['aboutToPerformFailActionCallback'])) {
            if (call_user_func(
                $this->options['aboutToPerformFailActionCallback'],
                $title,
                $description,
                $action,
                $this
            ) === false) {
                return;
            }
        }

        $this->addXStatusHeader('Failed (' . $description . ')');

        $this->addHeadersPreventingCaching();


        $title = 'Conversion failed';
        switch ($action) {
            case 'serve-original':
                if (!$this->serveOriginal()) {
                    $this->serve404();
                };
                break;
            case '404':
                $this->serve404();
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

    protected function criticalFail($title, $description)
    {
        return $this->fail($title, $description, true);
    }

    /**
     *  Serve the thing specified in $whatToServe and $whyServingThis
     *  These are first set my the decideWhatToServe() method, but may later change, if a fresh
     *  conversion is made
     */
    public function serve()
    {

        //$this->addXOptionsHeader();

        switch ($this->whatToServe) {
            case 'destination':
                return $this->serveExisting();
            case 'source':
                if ($this->whyServingThis == 'explicitly-told-to') {
                    $this->addXStatusHeader(
                        'Serving original image (was explicitly told to)'
                    );
                } else {
                    $this->addXStatusHeader(
                        'Serving original image (it is smaller than the already converted)'
                    );
                }
                if (!$this->serveOriginal()) {
                    $this->criticalFail('Error', 'could not serve original');
                    return false;
                }
                return true;
            case 'fresh-conversion':
                return $this->serveFreshlyConverted();
                break;
            case 'critical-fail':
                $this->criticalFail('Error', $this->whyServingThis);
                return false;
            case 'fail':
                $this->fail('Error', $this->whyServingThis);
                return false;
            case 'report':
                $this->addXStatusHeader('Reporting...');
                Report::convertAndReport($this->source, $this->destination, $this->options);
                return true;  // yeah, lets say that a report is always a success, even if conversion is a failure
        }
    }

    public function decideWhatToServeAndServeIt()
    {
        $this->decideWhatToServe();
        return $this->serve();
    }

    /**
     * Main method
     */
    public static function serveConverted($source, $destination, $options)
    {
        if (isset($options['fail']) && ($options['fail'] == 'original')) {
            $options['fail'] = 'serve-original';
        }
        // For backward compatability:
        if (isset($options['critical-fail']) && !isset($options['fail-when-original-unavailable'])) {
            $options['fail-when-original-unavailable'] = $options['critical-fail'];
        }

        $cs = new static($source, $destination, $options);

        return $cs->decideWhatToServeAndServeIt();
    }
}
