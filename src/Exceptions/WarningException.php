<?php

namespace WebPConvert\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

/**
 *  WebPConvertException is the base exception for all exceptions in this library.
 *
 *  Note that the parameters for the constructor differs from that of the Exception class.
 *  We do not use exception code here, but are instead allowing two version of the error message:
 *  a short version and a long version.
 *  The short version may not contain special characters or dynamic content.
 *  The detailed version may.
 *  If the detailed version isn't provided, getDetailedMessage will return the short version.
 *
 */
class WarningException extends WebPConvertException
{
    public $description = 'A warning was issued and turned into an exception';
}
