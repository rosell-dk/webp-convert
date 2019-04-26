<?php

namespace WebPConvert\Convert\BaseConverters;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\BaseConverters\AbstractConverter;
use WebPConvert\Convert\Helpers\PhpIniSizes;

/**
 * Base for converters that uses a cloud service.
 *
 * Handles checking that the file size of the source is smaller than the limits imposed in php.ini.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
abstract class AbstractCloudConverter extends AbstractConverter
{

    /**
     *  Test that filesize is below "upload_max_filesize" and "post_max_size" values in php.ini
     *
     * @throws  ConversionFailedException  if filesize is larger than "upload_max_filesize" or "post_max_size"
     * @return  void
     */
    protected function testFilesizeRequirements()
    {
        $fileSize = @filesize($this->source);
        if ($fileSize !== false) {
            $uploadMaxSize = PhpIniSizes::getIniBytes('upload_max_filesize');
            if ($uploadMaxSize === false) {
                // Not sure if we should throw an exception here, or not...
            } elseif ($uploadMaxSize < $fileSize) {
                throw new ConversionFailedException(
                    'File is larger than your max upload (set in your php.ini). File size:' .
                        round($fileSize/1024) . ' kb. ' .
                        'upload_max_filesize in php.ini: ' . ini_get('upload_max_filesize') .
                        ' (parsed as ' . round($uploadMaxSize/1024) . ' kb)'
                );
            }

            $postMaxSize = PhpIniSizes::getIniBytes(ini_get('post_max_size'));
            if ($postMaxSize === false) {
                // Not sure if we should throw an exception here, or not...
            } elseif ($postMaxSize < $fileSize) {
                throw new ConversionFailedException(
                    'File is larger than your post_max_size limit (set in your php.ini). File size:' .
                        round($fileSize/1024) . ' kb. ' .
                        'post_max_size in php.ini: ' . ini_get('post_max_size') .
                        ' (parsed as ' . round($postMaxSize/1024) . ' kb)'
                );
            }

            // Hm, should we worry about memory limit as well?
            // ini_get('memory_limit')
        }
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     *
     */
    public function checkConvertability()
    {
        $this->testFilesizeRequirements();
    }
}
