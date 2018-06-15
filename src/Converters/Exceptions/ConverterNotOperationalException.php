<?php

namespace WebPConvert\Converters\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConverterNotOperationalException extends WebPConvertBaseException
{
    public $description = 'The converter is not operational';
}
