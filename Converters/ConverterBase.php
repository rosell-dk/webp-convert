<?php

namespace WebPConvert\Converters;

use WebPConvert\GeneralHelper;

abstract class ConverterBase
{
    abstract protected static function convert($source, $destination, $quality, $stripMetadata, $options = array());

    protected static function getExtension($filePath)
    {
        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($fileExtension);
    }

    protected static function prepareDestinationFolderAndRunCommonValidations($source, $destination)
    {
        GeneralHelper::isValidTarget($source);
        GeneralHelper::isAllowedExtension($source);
        GeneralHelper::createWritableFolder($destination);
    }
}
