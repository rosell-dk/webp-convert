<?php
namespace WebPConvert\Serve;

use WebPConvert\Serve\DecideWhatToServe;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Report;
use WebPConvert\Serve\ServeFile;

use WebPConvert\Serve\Exceptions\ServeFailedException;

use ImageMimeTypeGuesser\ImageMimeTypeGuesser;

/**
 * Serve a converted webp image.
 *
 * The webp that is served might end up being one of these:
 * - a fresh convertion
 * - the destionation
 * - the original
 *
 * Exactly which is a decision based upon options, file sizes and file modification dates (see DecideWhatToServe class)
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeConvertedWebP
{

/*
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
    ];*/

    /**
     * Serve original file (source).
     *
     * @param   string  $source              path to source file
     * @param   array   $options (optional)  options for serving
     * @throws  ServeFailedException  if source is not an image or mime type cannot be determined
     */
    public static function serveOriginal($source, $options)
    {
        $contentType = ImageMimeTypeGuesser::lenientGuess($source);
        if (is_null($contentType)) {
            throw new ServeFailedException('Rejecting to serve original (mime type cannot be determined)');
        } elseif ($contentType === false) {
            throw new ServeFailedException('Rejecting to serve original (it is not an image)');
        } else {
            ServeFile::serve($source, $contentType, $options);
        }
    }

    public static function serveDestination($destination, $options)
    {
        ServeFile::serve($destination, 'image/webp', $options);
    }

    /**
     * Serve converted webp.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting
     *
     * @throws  ServeFailedException  If an argument is invalid or source file does not exists
     * @return  void
     */
    public static function serve($source, $destination, $options = [])
    {
        if (empty($source)) {
            throw new ServeFailedException('Source argument missing');
        }
        if (empty($destination)) {
            throw new ServeFailedException('Destination argument missing');
        }
        if (@!file_exists($source)) {
            throw new ServeFailedException('Source file was not found');
        }

        list($whatToServe, $whyToServe, $msg) = DecideWhatToServe::decide($source, $destination, $options);

        Header::setHeader('X-WebP-Convert-Action: ' . $msg);

        switch ($whatToServe) {
            case 'destination':
                self::serveDestination($destination, $options);
                break;

            case 'source':
                self::serveOriginal($source, $options);
                break;

            case 'fresh-conversion':
                ServeFreshConversion::serve($source, $destination, $options);
                break;

            case 'report':
                Report::convertAndReport($source, $destination, $options);
                break;
        }
    }
}
