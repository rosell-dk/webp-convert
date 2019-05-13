<?php

namespace WebPConvert\Convert\Exceptions;

use WebPConvert\Exceptions\WebPConvertException;

/**
 *  ConversionFailedException is the base exception in the hierarchy for conversion errors.
 *
 *  Exception hierarchy from here:
 *
 *  WebpConvertException
 *      ConversionFailedException
 *          ConversionSkippedException
 *          ConverterNotOperationalException
 *              InvalidApiKeyException
 *              SystemRequirementsNotMetException
 *          FileSystemProblemsException
 *              CreateDestinationFileException
 *              CreateDestinationFolderException
 *          InvalidInputException
 *              ConverterNotFoundException
 *              InvalidImageTypeException
 *              InvalidOptionTypeException
 *              TargetNotFoundException
 */
class ConversionFailedException extends WebPConvertException
{
    public $description = 'The converter failed converting, although requirements seemed to be met';
}
