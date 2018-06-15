<?php

namespace WebPConvert\Converters\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConverterFailedException extends WebPConvertBaseException
{
    public $description = 'The converter failed converting, although requirements seemed to be met';
}
