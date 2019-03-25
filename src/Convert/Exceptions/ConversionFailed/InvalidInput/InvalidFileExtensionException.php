<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput;

use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;

class InvalidFileExtensionException extends InvalidInputException
{
    public $description = 'The converter does not accept the file extension';
}
