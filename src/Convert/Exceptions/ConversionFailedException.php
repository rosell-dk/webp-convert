<?php

namespace WebPConvert\Convert\Exceptions;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInputException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblemsException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperationalException;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFileException;
use WebPConvert\Convert\Exceptions\ConversionFailed\FileSystemProblems\CreateDestinationFolderException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\InvalidImageTypeException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\ConverterNotFoundException;

/**
 *  ConversionFailedException is the base exception in the hierarchy for conversion errors.
 *
 *  Note that the parameters for the constructor differs from that of the Exception class.
 *  We do not use exception code here, but are instead allowing two version of the error message:
 *  a short version and a long version.
 *  The short version may not contain special characters or dynamic content.
 *  The detailed version may.
 *  If the detailed version isn't provided, getDetailedMessage will return the short version.
 *
 *  Exception hierarchy:
 *
 *  Exception
 *      ConversionFailedException
 *          ConversionDeclinedException
 *          ConverterNotOperationalException
 *              SystemRequirementsNotMetException
 *          FileSystemProblemsException
 *              CreateDestinationFileException
 *              CreateDestinationFolderException
 *          InvalidInputException
 *              ConverterNotFoundException
 *              InvalidImageTypeException
 *              TargetNotFoundException
 *          UnhandledException
 */
class ConversionFailedException extends \Exception
{
    public $description = 'The converter failed converting, although requirements seemed to be met';
    protected $detailedMessage;
    protected $shortMessage;

    public function getDetailedMessage()
    {
        return $this->detailedMessage;
    }

    public function getShortMessage()
    {
        return $this->shortMessage;
    }

    public function __construct($shortMessage="", $detailedMessage="", $previous = null)
    {
        $detailedMessage = ($detailedMessage != '') ? $detailedMessage : $shortMessage;
        $this->detailedMessage = $detailedMessage;
        $this->shortMessage = $shortMessage;

        parent::__construct(
            $detailedMessage,
            0,
            $previous
        );
    }
}
