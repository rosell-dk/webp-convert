<?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class InvalidFileExtensionException extends WebPConvertBaseException
{
    public $description = 'The converter does not accept the file extension';
}
