<?php 

?><?php

namespace WebPConvert\Loggers;

abstract class BaseLogger
{
    /*
    $msg: message to log
    $style: null | bold | italic
    */
    abstract public function log($msg, $style = '');

    abstract public function ln();

    public function logLn($msg, $style = '')
    {
        $this->log($msg, $style);
        $this->ln();
    }

    public function logLnLn($msg, $style = '')
    {
        $this->logLn($msg, $style);
        $this->ln();
    }
}

?><?php

namespace WebPConvert;

//use WebPConvert\Convert\Converters\ConverterHelper;
use WebPConvert\Convert\Converters\Stack;
use WebPConvert\Serve\ServeExistingOrHandOver;

class WebPConvert
{

    /*
      @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
      @param (string) $destination: Absolute path (no backslashes)
      @param (object) $options: Array of named options, such as 'quality' and 'metadata'
    */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        //return ConverterHelper::runConverterStack($source, $destination, $options, $logger);
        //return Convert::runConverterStack($source, $destination, $options, $logger);
        return Stack::convert($source, $destination, $options, $logger);
    }

    public static function convertAndServe($source, $destination, $options = [])
    {
        return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
    }
}

?><?php

namespace WebPConvert\Loggers;

use WebPConvert\Loggers\BaseLogger;

class BufferLogger extends BaseLogger
{
    public $entries = array();

    public function log($msg, $style = '')
    {
        $this->entries[] = [$msg, $style];
    }

    public function ln()
    {
        $this->entries[] = '';
    }

    public function getHtml()
    {
        $html = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {
                $html .= '<br>';
            } else {
                list($msg, $style) = $entry;
                $msg = htmlspecialchars($msg);
                if ($style == 'bold') {
                    $html .= '<b>' . $msg . '</b>';
                } elseif ($style == 'italic') {
                    $html .= '<i>' . $msg . '</i>';
                } else {
                    $html .= $msg;
                }
            }
        }
        return $html;
    }

    public function getText($newLineChar = ' ')
    {
        $text = '';
        foreach ($this->entries as $entry) {
            if ($entry == '') {
                if (substr($text, -2) != '. ') {
                    $text .= '. ';
                }
            } else {
                list($msg, $style) = $entry;
                $text .= $msg;
            }
        }

        return $text;
    }
}

?><?php

namespace WebPConvert\Loggers;

class EchoLogger extends BaseLogger
{
    public function log($msg, $style = '')
    {
        $msg = htmlspecialchars($msg);
        if ($style == 'bold') {
            echo '<b>' . $msg . '</b>';
        } elseif ($style == 'italic') {
            echo '<i>' . $msg . '</i>';
        } else {
            echo $msg;
        }
    }

    public function ln()
    {
        echo '<br>';
    }
}

?><?php

namespace WebPConvert\Loggers;

class VoidLogger extends BaseLogger
{
    public function log($msg, $style = '')
    {
    }

    public function ln()
    {
    }
}

?><?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;
use WebPConvert\Convert\Converters\ConverterHelper;
use WebPConvert\Loggers\EchoLogger;

//use WebPConvert\Loggers\EchoLogger;

class Report
{

    /**
     *   Input: We have a converter array where the options are defined
     *   Output:  the converter array is "flattened" to be just names.
     *            and the options have been moved to the "converter-options" option.
     */
    public static function flattenConvertersArray($options)
    {
        // TODO: If there are more of the same converters,
        // they should be added as ie 'wpc-2', 'wpc-3', etc

        $result = $options;
        $result['converters'] = [];
        foreach ($options['converters'] as $converter) {
            if (is_array($converter)) {
                $converterName = $converter['converter'];
                if (!isset($options['converter-options'][$converterName])) {
                    if (isset($converter['options'])) {
                        if (!isset($result['converter-options'])) {
                            $result['converter-options'] = [];
                        }
                        $result['converter-options'][$converterName] = $converter['options'];
                    }
                }
                $result['converters'][] = $converterName;
            } else {
                $result['converters'][] = $converter;
            }
        }
        return $result;
    }

    /* Hides sensitive options */
    public static function getPrintableOptions($options)
    {
        $printable_options = [];

        // (psst: the is_callable check is needed in order to work with WebPConvert v1.0)
        if (is_callable('ConverterHelper', 'getClassNameOfConverter')) {
            $printable_options = self::flattenConvertersArray($options);
            if (isset($printable_options['converter-options'])) {
                foreach ($printable_options['converter-options'] as $converterName => &$converterOptions) {
                    $className = ConverterHelper::getClassNameOfConverter($converterName);

                    // (pstt: the isset check is needed in order to work with WebPConvert v1.0)
                    if (isset($className::$extraOptions)) {
                        foreach ($className::$extraOptions as $extraOption) {
                            if ($extraOption['sensitive']) {
                                if (isset($converterOptions[$extraOption['name']])) {
                                    $converterOptions[$extraOption['name']] = '*******';
                                }
                            }
                        }
                    }
                }
            }
        }
        return $printable_options;
    }

    public static function getPrintableOptionsAsString($options, $glue = '. ')
    {
        $optionsForPrint = [];
        foreach (self::getPrintableOptions($options) as $optionName => $optionValue) {
            $printValue = '';
            if ($optionName == 'converter-options') {
                $converterNames = [];
                $extraConvertOptions = $optionValue;
                //print_r($extraConvertOptions);
                /*
                foreach ($optionValue as $converterName => $converterOptions) {

                    if (is_array($converter)) {
                        $converterName = $converter['converter'];
                        if (isset($converter['options'])) {
                            $extraConvertOptions[$converter['converter']] = $converter['options'];
                        }
                    } else {
                        $converterName = $converter;
                    }
                    $converterNames[] = $converterName;
                }*/
                $glueMe = [];
                foreach ($extraConvertOptions as $converter => $extraOptions) {
                    $opt = [];
                    foreach ($extraOptions as $oName => $oValue) {
                        $opt[] = $oName . ':"' . $oValue . '"';
                    }
                    $glueMe[] = '(' . $converter . ': (' . implode($opt, ', ') . '))';
                }
                $printValue = implode(',', $glueMe);
            } elseif ($optionName == 'web-service') {
                $printValue = 'sensitive, so not displaying here...';
            } else {
                switch (gettype($optionValue)) {
                    case 'boolean':
                        if ($optionValue === true) {
                            $printValue = 'true';
                        } elseif ($optionValue === false) {
                            $printValue = 'false';
                        }
                        break;
                    case 'string':
                        $printValue = '"' . $optionValue . '"';
                        break;
                    case 'array':
                        $printValue = implode(', ', $optionValue);
                        break;
                    case 'integer':
                        $printValue = $optionValue;
                        break;
                    default:
                        $printValue = $optionValue;
                }
            }
            $optionsForPrint[] = $optionName . ': ' . $printValue;
        }
        return implode($glue, $optionsForPrint);
    }

    public static function convertAndReport($source, $destination, $options)
    {
        ?>
<html>
    <head>
        <style>td {vertical-align: top} table {color: #666}</style>
        <script>
            function showOptions(elToHide) {
                document.getElementById('options').style.display='block';
                elToHide.style.display='none';
            }
        </script>
    </head>
    <body>
        <table>
            <tr><td><i>source:</i></td><td><?php echo $source ?></td></tr>
            <tr><td><i>destination:</i></td><td><?php echo $destination ?><td></tr>
            <tr>
                <td><i>options:</i></td>
                <td>
                    <i style="text-decoration:underline;cursor:pointer" onclick="showOptions(this)">click to see</i>
                    <pre id="options" style="display:none"><?php
                        echo print_r(self::getPrintableOptionsAsString($options, '<br>'), true);
                    ?></pre>
                    <?php //echo json_encode(self::getPrintableOptions($options)); ?>
                    <?php //echo print_r(self::getPrintableOptions($options), true); ?>
                </td>
            </tr>
        </table>
        <br>
        <?php
        // TODO:
        // We could display warning if unknown options are set
        // but that requires that WebPConvert also describes its general options

        try {
            $echoLogger = new EchoLogger();
            $success = WebPConvert::convert($source, $destination, $options, $echoLogger);
        } catch (\Exception $e) {
            $success = false;

            $msg = $e->getMessage();

            echo '<b>' . $msg . '</b>';
            exit;
        }

        if ($success) {
            //echo 'ok';
        } else {
            echo '<b>Conversion failed. None of the tried converters are operational</b>';
        }
        ?>
    </body>
    </html>
        <?php
    }
}

?><?php
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

?><?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;
use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionDeclinedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\InvalidFileExtensionException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;

use WebPConvert\Loggers\BufferLogger;
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
        } catch (InvalidFileExtensionException $e) {
            $criticalFail = true;
            $description = 'Invalid file extension';
            $msg = $e->getMessage();
        } catch (TargetNotFoundException $e) {
            $criticalFail = true;
            $description = 'Source file not found';
            $msg = $e->getMessage();
        } catch (ConversionFailedException $e) {
            // No converters could convert the image. At least one converter failed, even though it appears to be
            // operational
            $description = 'No converters could convert the image';
            $msg = $e->getMessage();
        } catch (ConversionDeclinedException $e) {
            // (no converters could convert the image. At least one converter declined
            $description = 'No converters could/wanted to convert the image';
            $msg = $e->getMessage();
        } catch (ConverterNotFoundException $e) {
            $description = 'A converter was not found!';
            $msg = $e->getMessage();
        } catch (CreateDestinationFileException $e) {
            $description = 'Cannot create destination file';
            $msg = $e->getMessage();
        } catch (CreateDestinationFolderException $e) {
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

?><?php
namespace WebPConvert\Serve;

use WebPConvert\Serve\ServeBase;
use WebPConvert\Serve\ServeConverted;

/**
 * This class must determine if an existing converted image can and should be served.
 * If so, it must serve it.
 * If not, it must hand the task over to ConvertAndServe
 *
 * The reason for doing it like this is that we want existing images to be served as fast as
 * possible, because that is the thing that will happen most of the time.
 *
 * Anything else, such as error handling and creating new conversion is handed off
 * (and only autoloaded when needed)
 */

class ServeExistingOrHandOver extends ServeBase
{

    /**
     * Main method
     */
    public static function serveConverted($source, $destination, $options)
    {
        $server = new ServeExistingOrHandOver($source, $destination, $options);

        $server->decideWhatToServe();
        if ($server->whatToServe == 'destination') {
            return $server->serveExisting();
        } else {
            // Load extra php classes, if told to
            if (isset($options['require-for-conversion'])) {
                require($options['require-for-conversion']);
            }
            ServeConverted::serveConverted($source, $destination, $options);
        }
    }
}

