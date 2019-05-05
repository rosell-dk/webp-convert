<?php
namespace WebPConvert\Serve;

use WebPConvert\Serve\Header;
use WebPConvert\Serve\Report;
use WebPConvert\Serve\ServeConvertedWeb;
use WebPConvert\Serve\Exceptions\ServeFailedException;

/**
 * Serve a converted webp image and handle errors.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class ServeConvertedWebPWithErrorHandling
{

    public static $defaultOptions = [
        'fail' => 'original',
        'fail-when-original-unavailable' => '404',
    ];

    /**
     *  Add headers for preventing caching.
     *
     *  @return  void
     */
    private static function addHeadersPreventingCaching()
    {
        Header::setHeader("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        Header::addHeader("Cache-Control: post-check=0, pre-check=0");
        Header::setHeader("Pragma: no-cache");
    }

    /**
     * Perform fail action.
     *
     * @param  string  $fail                Action to perform (original | 404 | report)
     * @param  string  $failIfFailFails     Action to perform if $fail action fails
     * @param  string  $source              path to source file
     * @param  string  $destination         path to destination
     * @param  array   $options (optional)  options for serving/converting
     * @return void
     */
    public static function performFailAction($fail, $failIfFailFails, $source, $destination, $options)
    {
        self::addHeadersPreventingCaching();

        switch ($fail) {
            case 'original':
                try {
                    ServeConvertedWebP::serveOriginal($source, $options);
                } catch (\Exception $e) {
                    self::performFailAction($failIfFailFails, '404', $source, $destination, $options);
                }
                break;

            case '404':
                $protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : 'HTTP/1.0';
                Header::setHeader($protocol . " 404 Not Found");
                break;

            case 'report':
                $options['show-report'] = true;
                Report::convertAndReport($source, $destination, $options);
                break;

            case 'report-as-image':
                // TODO
                break;
        }
    }

    /**
     * Serve webp image and handle errors.
     *
     * @throws  ServeFailedException  If an argument is invalid or source file does not exists
     * @return  void
     */
    public static function serve($source, $destination, $options = [])
    {
        $options = array_merge(self::$defaultOptions, $options);

        try {
            ServeConvertedWebP::serve($source, $destination, $options);
        } catch (\Exception $e) {
            self::performFailAction(
                $options['fail'],
                $options['fail-when-original-unavailable'],
                $source,
                $destination,
                $options
            );
        }
    }
}
