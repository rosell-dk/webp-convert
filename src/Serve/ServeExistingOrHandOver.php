<?php
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

        $decisionArr = $server->decideWhatToServe();
        if ($decisionArr[0] == 'destination') {
            $server->whyServingThis = 'no-reason-not-to';
            return $server->serveExisting();
        } else {
            ServeConverted::serveConverted($source, $destination, $options);
        }
    }


}
