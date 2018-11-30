<?php
namespace WebPConvert\Serve;

//use WebPConvert\Serve\Report;

class ServeBase
{
    public $source;
    public $destination;
    public $options;

    // These two fellows are first set when decideWhatToServe is called
    // However, if it is decided to serve a fresh conversion, they might get modified.
    // If that for example results in a file larger than source, $whatToServe will change
    // from 'fresh-conversion' to 'original', and $whyServingThis will change to 'source-lighter'
    public $whatToServe = '';
    public $whyServingThis = '';

    public function __construct($source, $destination, $options)
    {

        $this->source = $source;
        $this->destination = $destination;
        $this->options = array_merge(self::$defaultOptions, $options);

        $this->setErrorReporting();
    }

    public static $defaultOptions = [
        'add-content-type-header' => true,
        'add-last-modified-header' => true,
        'add-vary-header' => true,
        'add-x-header-status' => true,
        'add-x-header-options' => false,
        'aboutToServeImageCallBack' => null,
        'aboutToPerformFailAction' => null,
        'cache-control-header' => 'public, max-age=86400',
        'converters' =>  ['cwebp', 'gd', 'imagick'],
        'error-reporting' => 'auto',
        'fail' => 'original',
        'fail-when-original-unavailable' => '404',
        'reconvert' => false,
        'serve-original' => false,
        'show-report' => false,
    ];

    protected function setErrorReporting()
    {
        if (($this->options['error-reporting'] === true) ||
            (($this->options['error-reporting'] === 'auto') && ($this->options['show-report'] === true))
        ) {
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        } elseif (($this->options['error-reporting'] === false) ||
            (($this->options['error-reporting'] === 'auto') && ($this->options['show-report'] === false))
        ) {
            error_reporting(0);
            ini_set('display_errors', 'Off');
        }
    }

    protected function header($header, $replace = true)
    {
        header($header, $replace);
    }

    public function addXStatusHeader($text)
    {
        if ($this->options['add-x-header-status']) {
            $this->header('X-WebP-Convert-Status: ' . $text, true);
        }
    }

    public function addVaryHeader()
    {
        if ($this->options['add-vary-header']) {
            $this->header('Vary: Accept');
        }
    }

    public function addContentTypeHeader($cType)
    {
        if ($this->options['add-content-type-header']) {
            $this->header('Content-type: ' . $cType);
        }
    }

    /* $timestamp  Unix timestamp */
    public function addLastModifiedHeader($timestamp)
    {
        if ($this->options['add-last-modified-header']) {
            $this->header("Last-Modified: " . gmdate("D, d M Y H:i:s", $timestamp) ." GMT", true);
        }
    }

    public function addCacheControlHeader()
    {
        if (!empty($this->options['cache-control-header'])) {
            $this->header('Cache-Control: ' . $this->options['cache-control-header'], true);
        }
    }

    public function serveExisting()
    {
        if (!$this->callAboutToServeImageCallBack('destination')) {
            return;
        }

        $this->addXStatusHeader('Serving existing converted image');
        $this->addVaryHeader();
        $this->addContentTypeHeader('image/webp');
        $this->addCacheControlHeader();
        $this->addLastModifiedHeader(@filemtime($this->destination));

        if (@readfile($this->destination) === false) {
            $this->header('X-WebP-Convert-Error: Could not read file');
            return false;
        }
        return true;
    }

    /**
     *   Called immidiately before serving image (either original, already converted or fresh)
     *   $whatToServe can be 'source' | 'destination' | 'fresh-conversion'
     *   $whyServingThis can be:
     *   for 'source':
     *       - "explicitly-told-to"     (when the "original" option is set)
     *       - "source-lighter"         (when original image is actually smaller than the converted)
     *   for 'fresh-conversion':
     *       - "explicitly-told-to"     (when the "reconvert" option is set)
     *       - "source-modified"        (when source is newer than existing)
     *       - "no-existing"            (when there is no existing at the destination)
     *   for 'destination':
     *       - "no-reason-not-to"       (it is lighter than source, its not older,
     *                                   and we were not told to do otherwise)
     */
    protected function callAboutToServeImageCallBack($whatToServe)
    {
        if (!isset($this->options['aboutToServeImageCallBack'])) {
            return true;
        }
        $result = call_user_func(
            $this->options['aboutToServeImageCallBack'],
            $whatToServe,
            $this->whyServingThis,
            $this
        );
        return ($result !== false);
    }

    /**
     *  Decides what to serve.
     *  Returns array. First item is what to do, second is additional info.
     *  First item can be one of these:
     *  - "destination"  (serve existing converted image at the destination path)
     *       - "no-reason-not-to"
     *  - "source"
     *       - "explicitly-told-to"
     *       - "source-lighter"
     *  - "fresh-conversion" (note: this may still fail)
     *       - "explicitly-told-to"
     *       - "source-modified"
     *       - "no-existing"
     *  - "fail"
     *        - "Missing destination argument"
     *  - "critical-fail"   (a failure where the source file cannot be served)
     *        - "Missing source argument"
     *        - "Source file was not found!"
     *  - "report"
     */
    public function decideWhatToServe()
    {
        $decisionArr = $this->doDecideWhatToServe();
        $this->whatToServe = $decisionArr[0];
        $this->whyServingThis = $decisionArr[1];
    }

    private function doDecideWhatToServe()
    {
        if (empty($this->source)) {
            return ['critical-fail', 'Missing source argument'];
        }
        if (@!file_exists($this->source)) {
            return ['critical-fail', 'Source file was not found!'];
        }
        if (empty($this->destination)) {
            return ['fail', 'Missing destination argument'];
        }
        if ($this->options['show-report']) {
            return ['report', ''];
        }
        if ($this->options['serve-original']) {
            return ['source', 'explicitly-told-to'];
        }
        if ($this->options['reconvert']) {
            return ['fresh-conversion', 'explicitly-told-to'];
        }

        if (@file_exists($this->destination)) {
            // Reconvert if source file is newer than destination
            $timestampSource = @filemtime($this->source);
            $timestampDestination = @filemtime($this->destination);
            if (($timestampSource !== false) &&
                ($timestampDestination !== false) &&
                ($timestampSource > $timestampDestination)) {
                return ['fresh-conversion', 'source-modified'];
            }

            // Serve source if it is smaller than destination
            $filesizeDestination = @filesize($this->destination);
            $filesizeSource = @filesize($this->source);
            if (($filesizeSource !== false) &&
                ($filesizeDestination !== false) &&
                ($filesizeDestination > $filesizeSource)) {
                return ['source', 'source-lighter'];
            }

            // Destination exists, and there is no reason left not to serve it
            return ['destination', 'no-reason-not-to'];
        } else {
            return ['fresh-conversion', 'no-existing'];
        }
    }
}
