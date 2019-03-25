<?php

namespace WebPConvert\Convert\Exceptions;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class ConversionFailedException extends \Exception
{
    public $description = 'The converter failed converting, although requirements seemed to be met';
}
