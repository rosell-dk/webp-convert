<?php

namespace WebPConvert\Convert\Exceptions\ConversionFailed;

use WebPConvert\Convert\Exceptions\ConversionFailedException;

class UnhandledException extends ConversionFailedException
{
    public $description = 'The converter failed due to uncaught exception';

    /*
    Nah, do not add message of the uncaught exception to this.
    - because it might be long and contain characters which consumers for example cannot put inside a
    x-webpconvert-error header
    The messages we throw are guaranteed to be short

    public function __construct($message="", $code=0, $previous)
    {
        parent::__construct(
            'The converter failed due to uncaught exception: ' . $previous->getMessage(),
            $code,
            $previous
        );
        //$this->$message = 'hello.' . $message . ' ' . $previous->getMessage();
    }*/
}
