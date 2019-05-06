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
     * @param  \Exception  $e               exception that was thrown when trying to serve
     * @return void
     */
    public static function performFailAction($fail, $failIfFailFails, $source, $destination, $options, $e)
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

            case 'throw':
                throw $e;
                break;

            case 'report-as-image':
                // TODO: Implement or discard ?
                break;
        }
    }

    /**
     * Serve webp image and handle errors as specified in the 'fail' option.
     *
     * This method basically wraps ServeConvertedWebP:serve in order to provide exception handling.
     * The error handling is set with the 'fail' option and can be either '404', 'original' or 'report'.
     * If set to '404', errors results in 404 Not Found headers being issued. If set to 'original', an
     * error results in the original being served.
     * Look up the ServeConvertedWebP:serve method to learn more.
     *
     * @param   string  $source              path to source file
     * @param   string  $destination         path to destination
     * @param   array   $options (optional)  options for serving/converting
     *       Supported options:
     *       - 'fail' => (string)    Action to take on failure (404 | original | report | throw).
     *               "404" or "throw" is recommended for development and "original" is recommended for production.
     *               Default: 'original'.
     *       - 'fail-when-original-unavailable'  => (string) Action to take if fail action also fails. Default: '404'.
     *       - All options supported by WebPConvert::convert()
     *       - All options supported by ServeFile::serve()
     *       - All options supported by DecideWhatToServe::decide)
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
                $options,
                $e
            );
        }
    }
}
