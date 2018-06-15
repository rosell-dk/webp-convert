<?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConverterNotFoundException extends WebPConvertBaseException
{
    public $description = 'The converter does not exist.';
}
