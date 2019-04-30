<?php
namespace WebPConvert\Serve;

//use WebPConvert\Serve\Report;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Exceptions\ServeFailedException;

/**
 * Serve a file (send to standard output)
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeFile
{

    public static $defaultOptions = [
        'add-vary-accept-header' => true,
        'set-content-type-header' => true,
        'set-last-modified-header' => true,
        'set-cache-control-header' => true,
        'cache-control-header' => 'public, max-age=86400',
    ];

    /**
     * Serve existing file
     *
     * @throws ServeFailedException  if serving failed
     * @return  void
     */
    public static function serve($filename, $contentType, $options)
    {
        $options = array_merge(self::$defaultOptions, $options);

        if ($options['set-last-modified-header'] === true) {
            Header::setHeader("Last-Modified: " . gmdate("D, d M Y H:i:s", @filemtime($filename)) ." GMT");
        }

        if ($options['set-content-type-header'] === true) {
            Header::setHeader('Content-type: ' . $contentType);
        }

        if ($options['add-vary-accept-header'] === true) {
            Header::addHeader('Vary: Accept');
        }

        if ($options['set-cache-control-header'] === true) {
            if (!empty($options['cache-control-header'])) {
                Header::setHeader('Cache-Control: ' . $options['cache-control-header']);

                // Add exprires header too (#126)
                // Check string for something like this: max-age:86400
                if (preg_match('#max-age\\s*=\\s*(\\d*)#', $options['cache-control-header'], $matches)) {
                    $seconds = $matches[1];
                    Header::setHeader('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + intval($seconds)));
                }
            }
        }

        if (@readfile($filename) === false) {
            Header::addHeader('X-WebP-Convert-Error: Could not read file');
            throw new ServeFailedException('Could not read file');
        }
    }
}
