<?php
namespace WebPConvert\Serve;

use WebPConvert\WebPConvert;

use WebPConvert\Serve\DecideWhatToServe;
use WebPConvert\Serve\Header;
use WebPConvert\Serve\Exceptions\ServeFailedException;

/**
 * Serve a freshly converted image.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeFreshConversion
{

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
