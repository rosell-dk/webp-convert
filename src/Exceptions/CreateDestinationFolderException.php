<?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertBaseException;

class CreateDestinationFolderException extends WebPConvertBaseException
{
    public $description = 'The converter could not create destination folder. Check file permisions!';
}
