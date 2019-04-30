<?php
namespace WebPConvert\Serve;

//use WebPConvert\Serve\Report;

/**
 * Decide what to serve based on options, file sizes and file modification dates.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class DecideWhatToServe
{

    public static $defaultOptions = [
        'reconvert' => false,
        'serve-original' => false,
        'show-report' => false,
    ];

    /**
     * Decides what to serve.
     *
     * It both decides what to serve and supplies the reason behind.
     * The possible combinations are:
     *
     * - "destination"  (serve existing converted image at the destination path)
     *      - "no-reason-not-to"
     * - "source"
     *      - "explicitly-told-to"
     *      - "source-lighter"
     * - "fresh-conversion" (note: this may still fail)
     *      - "explicitly-told-to"
     *      - "source-modified"
     *      - "no-existing"
     * - "fail"
     *       - "Missing destination argument"
     * - "critical-fail"   (a failure where the source file cannot be served)
     *       - "missing-source-argument"
     *       - "source-not-found"
     * - "report"
     *
     * @return  array  Three items: what to serve (id), why to serve (id) and suggested message
     */
    public static function decide($source, $destination, $options)
    {
        $options = array_merge(self::$defaultOptions, $options);

        if ($options['show-report']) {
            return ['report', 'explicitly-told-to', 'Serving report (explicitly told to)'];
        }
        if ($options['serve-original']) {
            return ['source', 'explicitly-told-to', 'Serving original image (was explicitly told to)'];
        }
        if ($options['reconvert']) {
            return ['fresh-conversion', 'explicitly-told-to', 'Serving fresh conversion (was explicitly told to)'];
        }

        if (@file_exists($destination)) {
            // Reconvert if existing conversion is older than the original
            $timestampSource = @filemtime($source);
            $timestampDestination = @filemtime($destination);
            if (($timestampSource !== false) &&
                ($timestampDestination !== false) &&
                ($timestampSource > $timestampDestination)) {
                return [
                    'fresh-conversion',
                    'source-modified',
                    'Serving fresh conversion ' .
                        '(the existing conversion is discarded because original has been modified since then)'
                ];
            }

            // Serve source if it is smaller than destination
            $filesizeDestination = @filesize($destination);
            $filesizeSource = @filesize($source);
            if (($filesizeSource !== false) &&
                ($filesizeDestination !== false) &&
                ($filesizeDestination > $filesizeSource)) {
                return [
                    'source',
                    'source-lighter',
                    'Serving original image (it is smaller than the already converted)'
                ];
            }

            // Destination exists, and there is no reason left not to serve it
            return ['destination', 'no-reason-not-to', 'Serving existing conversion'];
        } else {
            return [
                'fresh-conversion',
                'no-existing',
                'Serving fresh conversion (there were no existing conversion to serve)'
            ];
        }
    }
}
