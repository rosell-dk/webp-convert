<?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;

use WebPConvert\Serve\DecideWhatToServe;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Exceptions\ServeFailedException;

/**
 * Convert image to webp and serve it or the original (whichever is smallest).
 *
 * Converts and serves the conversion, unless:
 * - If the converted file turns out to be larger than the original, the original will be served.
 * - If the "serve-original" option is set, the original image even when it is larger than the converted
 * - If conversion fails, an exception is thrown.
 *
 * The conversion will always be fresh. If an image already exists at the destination, it will be removed.
 * If you would like to serve existing conversion when available, use the ServeConvertedWebP class.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 *
 * @see        WebPConvert::convert()   For the available conversion options
 * @see        ServeFile::serve()       For the available serve options (headers)
 */
class ConvertAndServeSmallest
{

    /**
     * Convert image to webp and serve it or the original (whichever is smallest).
     *
     * Converts and serves the conversion, unless:
     * - If the converted file turns out to be larger than the original, the original will be served.
     * - If the "serve-original" option is set, the original image will be served rather than the converted.
     * - If conversion fails, an exception is thrown.
     *
     * The conversion will always be fresh. If an image already exists at the destination, it will be removed.
     * If you would like to serve existing conversion when available, use the ServeConvertedWebP class.
     *
     * @param  string              $source        The path to the file to convert
     * @param  string              $destination   The path to save the converted file to
     * @param  array[string]mixed  $options       (optional)
     *       Supported options:
     *       - All options supported by WebPConvert::convert()
     *       - All options supported by ServeFile::serve()
     *       - The "serve-original" option, effected by DecideWhatToServe::decide
     *
     * @see        WebPConvert::convert()   For the available conversion options
     * @see        ServeFile::serve()       For the available serve options (headers)
     *
     * @throws ServeFailedException  if serving failed
     * @throws ConversionFailedException  if conversion failed
     * @return  void
     */
    public static function serve($source, $destination, $options = [])
    {

        try {
            WebPConvert::convert($source, $destination, $options);

            // We are here, so it was successful :)
            // If destination is smaller than source, we should serve destination. Otherwise we should serve source.
            // We can use DecideWhatToServe for that purpose.
            // However, we must make sure it does not answer "fresh-conversion" or "report"

            // Unset "reconvert", so we do not get "fresh-conversion"
            unset($options['reconvert']);

            // Unset "show-report", so we do not get "report"
            unset($options['show-report']);

            list($whatToServe, $whyToServe, $msg) = DecideWhatToServe::decide($source, $destination, $options);

            switch ($whatToServe) {
                case 'source':
                    Header::addHeader('X-WebP-Convert-Action: ' . $msg);
                    ServeConvertedWebP::serveOriginal($source, $options);
                    break;
                case 'destination':
                    ServeConvertedWebP::serveDestination($destination, $options);
                    break;

                case 'fresh-conversion':
                    // intentional fall through
                case 'report':
                    // intentional fall through
                default:
                    throw new ServeFailedException(
                        'DecideWhatToServe was supposed to return either "source" or "destination". ' .
                        'However, it returned: "' . $whatToServe . '"'
                    );
                    break;
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
