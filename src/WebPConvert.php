<?php

namespace WebPConvert;

//use WebPConvert\Convert\Converters\ConverterHelper;
use WebPConvert\Convert\Converters\Stack;
//use WebPConvert\Serve\ServeExistingOrHandOver;
use WebPConvert\Serve\ServeConvertedWebP;
use WebPConvert\Serve\ServeConvertedWebPWithErrorHandling;

/**
 * Convert images to webp and/or serve them.
 *
 * This class is just a couple of convenience methods for doing conversion and/or
 * serving.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
class WebPConvert
{

    /**
     * Convert jpeg or png into webp
     *
     * Convenience method for calling Stack::convert.
     *
     * @param  string  $source       The image to convert (absolute,no backslashes)
     *                               Image must be jpeg or png.
     * @param  string  $destination  Where to store the converted file (absolute path, no backslashes).
     * @param  array   $options      (optional) Array of named options, such as 'quality' and 'metadata'.
     * @param   \WebPConvert\Loggers\BaseLogger $logger (optional)
     *     
     * @throws \WebPConvert\Exceptions\WebPConvertException
     * @return void
    */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        //return ConverterHelper::runConverterStack($source, $destination, $options, $logger);
        //return Convert::runConverterStack($source, $destination, $options, $logger);
        Stack::convert($source, $destination, $options, $logger);
    }

    public static function convertAndServe($source, $destination, $options = [])
    {
        //return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
        //if (isset($options['handle-errors']) && $options['handle-errors'] === true) {
        if (isset($options['fail']) && ($options['fail'] != 'throw')) {
            ServeConvertedWebPWithErrorHandling::serve($source, $destination, $options);
        } else {
            ServeConvertedWebP::serve($source, $destination, $options);
        }
    }
}
