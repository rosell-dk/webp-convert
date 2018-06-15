<?php

namespace WebPConvert\Converters\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class ConversionDeclinedException extends WebPConvertBaseException
{
    public $description = 'The converter declined converting';
}
