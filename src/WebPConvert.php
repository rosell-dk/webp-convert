<?php

namespace WebPConvert;

//use WebPConvert\Convert\Converters\ConverterHelper;
use WebPConvert\Convert\Converters\Stack;
use WebPConvert\Serve\ServeExistingOrHandOver;

class WebPConvert
{

    /**
     * Convert jpeg or png into webp
     *
     * @param  string  $source  Absolute path to image to be converted (no backslashes). Image must be jpeg or png
     * @param  string  $destination  Absolute path (no backslashes)
     * @param  array   $options  Array of named options, such as 'quality' and 'metadata'
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
        return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
    }
}
