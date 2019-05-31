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

    /** @var array  Array of default options */
    public static $defaultOptions = [
        'set-cache-control-header' => false,
        'set-expires-header' => false,
        'cache-control-header' => 'public, max-age=31536000',
        'add-vary-accept-header' => false,
        'set-content-type-header' => true,
        'set-last-modified-header' => true,
        'set-content-length-header' => true,
    ];

/*
    public static $defaultOptions = [
        'header-switches' => [
            'cache-control' => false,
            'expires' => false,
            'vary-accept' => false,
            'content-type' => true,
            'last-modified' => true,
            'content-length' => true,
        ],
        'cache-control-header' => 'public, max-age=31536000',
    ];*/

    /**
     * Serve existing file.
     *
     * @param  string  $filename     File to serve (absolute path)
     * @param  string  $contentType  Content-type (used to set header).
     *                                    Only used when the "set-content-type-header" option is set.
     *                                    Set to ie "image/jpeg" for serving jpeg file.
     * @param  array   $options      Array of named options (optional).
     *       Supported options:
     *       'add-vary-accept-header'  => (boolean)   Whether to add *Vary: Accept* header or not. Default: true.
     *       'set-content-type-header' => (boolean)   Whether to set *Content-Type* header or not. Default: true.
     *       'set-last-modified-header' => (boolean)  Whether to set *Last-Modified* header or not. Default: true.
     *       'set-cache-control-header' => (boolean)  Whether to set *Cache-Control* header or not. Default: true.
     *       'cache-control-header' => string         Cache control header. Default: "public, max-age=86400"
     *
     * @throws ServeFailedException  if serving failed
     * @return  void
     */
    public static function serve($filename, $contentType, $options = [])
    {
        if (!file_exists($filename)) {
            Header::addHeader('X-WebP-Convert-Error: Could not read file');
            throw new ServeFailedException('Could not read file');
        }

        $options = array_merge(self::$defaultOptions, $options);

        if ($options['set-last-modified-header']) {
            Header::setHeader("Last-Modified: " . gmdate("D, d M Y H:i:s", @filemtime($filename)) ." GMT");
        }

        if ($options['set-content-type-header']) {
            Header::setHeader('Content-Type: ' . $contentType);
        }

        if ($options['add-vary-accept-header']) {
            Header::addHeader('Vary: Accept');
        }

        if (!empty($options['cache-control-header'])) {
            if ($options['set-cache-control-header']) {
                Header::setHeader('Cache-Control: ' . $options['cache-control-header']);
            }
            if ($options['set-expires-header']) {
                // Add exprires header too (#126)
                // Check string for something like this: max-age:86400
                if (preg_match('#max-age\\s*=\\s*(\\d*)#', $options['cache-control-header'], $matches)) {
                    $seconds = $matches[1];
                    Header::setHeader('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + intval($seconds)));
                }
            }
        }

        if ($options['set-content-length-header']) {
            Header::setHeader('Content-Length: ' . filesize($filename));
        }

        if (@readfile($filename) === false) {
            Header::addHeader('X-WebP-Convert-Error: Could not read file');
            throw new ServeFailedException('Could not read file');
        }
    }
}
