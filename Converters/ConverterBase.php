<?php

namespace WebPConvert\Converters;

abstract class ConverterBase
{
    abstract protected static function convert($source, $destination, $quality, $stripMetadata, $options = array());

    protected static function getExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension);
    }
}
