<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\TargetNotFoundException;
use WebPConvert\Convert\Exceptions\ConversionFailed\InvalidInput\InvalidImageTypeException;

/**
 * Trait for handling options
 *
 * This trait is currently only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning options.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait SourceValidationTrait
{

    abstract protected function getMimeTypeOfSource();
    abstract public function getSource();

    /** @var array  Array of allowed mime types for source.  */
    public static $allowedMimeTypes = ['image/jpeg', 'image/png'];

    /**
     * Check that source file exists.
     *
     * Note: As the input validations are only run one time in a stack,
     * this method is not overridable
     *
     * @throws TargetNotFoundException
     * @return void
     */
    private function checkSourceExists()
    {
        // Check if source exists
        if (!@file_exists($this->getSource())) {
            throw new TargetNotFoundException('File or directory not found: ' . $this->getSource());
        }
    }

    /**
     * Check that source has a valid mime type.
     *
     * Note: As the input validations are only run one time in a stack,
     * this method is not overridable
     *
     * @throws InvalidImageTypeException  If mime type could not be detected or is unsupported
     * @return void
     */
    private function checkSourceMimeType()
    {
        $fileMimeType = $this->getMimeTypeOfSource();
        if (is_null($fileMimeType)) {
            throw new InvalidImageTypeException('Image type could not be detected');
        } elseif ($fileMimeType === false) {
            throw new InvalidImageTypeException('File seems not to be an image.');
        } elseif (!in_array($fileMimeType, self::$allowedMimeTypes)) {
            throw new InvalidImageTypeException('Unsupported mime type: ' . $fileMimeType);
        }
    }
}
