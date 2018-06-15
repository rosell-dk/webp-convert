<?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class CreateDestinationFileException extends WebPConvertBaseException
{
    public $description = 'The converter could not create destination file. Check file permisions!';
}
