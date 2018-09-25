<?php

namespace WebPConvert;

use WebPConvert\Converters\ConverterHelper;
use WebPConvert\ServeExistingOrConvert;
use WebPConvert\Serve\ServeExistingOrHandOver;

class WebPConvert
{

    /*
      @param (string) $source: Absolute path to image to be converted (no backslashes). Image must be jpeg or png
      @param (string) $destination: Absolute path (no backslashes)
      @param (object) $options: Array of named options, such as 'quality' and 'metadata'
    */
    public static function convert($source, $destination, $options = [], $logger = null)
    {
        return ConverterHelper::runConverterStack($source, $destination, $options, $logger);
    }

    public static function convertAndServe($source, $destination, $options = [])
    {
        //return ServeExistingOrConvert::serveExistingOrConvert($source, $destination, $options);
        return ServeExistingOrHandOver::serveConverted($source, $destination, $options);
    }
}
