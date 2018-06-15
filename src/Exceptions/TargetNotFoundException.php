<?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class TargetNotFoundException extends WebPConvertBaseException
{
    public $description = 'The converter could not locate source file';
}
