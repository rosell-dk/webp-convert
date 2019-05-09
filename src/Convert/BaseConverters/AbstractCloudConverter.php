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
     * Test that filesize is below "upload_max_filesize" and "post_max_size" values in php.ini.
     *
     * @param  string  $iniSettingId  Id of ini setting (ie "upload_max_filesize")
     *
     * @throws  ConversionFailedException  if filesize is larger than the ini setting
     * @return  void
     */
    private function checkFileSizeVsIniSetting($iniSettingId)
    {
        $fileSize = @filesize($this->source);
        if ($fileSize === false) {
            return;
        }
        $sizeInIni = PhpIniSizes::getIniBytes($iniSettingId);
        if ($sizeInIni === false) {
            // Not sure if we should throw an exception here, or not...
            return;
        }
        if ($sizeInIni < $fileSize) {
            throw new ConversionFailedException(
                'File is larger than your ' . $iniSettingId . ' (set in your php.ini). File size:' .
                    round($fileSize/1024) . ' kb. ' .
                    $iniSettingId . ' in php.ini: ' . ini_get($iniSettingId) .
                    ' (parsed as ' . round($sizeInIni/1024) . ' kb)'
            );
        }
    }

    /**
     * Test that filesize is below "upload_max_filesize" and "post_max_size" values in php.ini.
     *
     * @throws  ConversionFailedException  if filesize is larger than "upload_max_filesize" or "post_max_size"
     * @return  void
     */
    protected function checkFilesizeRequirements()
    {
        $this->checkFileSizeVsIniSetting('upload_max_filesize');
        $this->checkFileSizeVsIniSetting('post_max_size');
    }

    /**
     * Check if specific file is convertable with current converter / converter settings.
     * @return void
     */
    public function checkConvertability()
    {
        $this->checkFilesizeRequirements();
    }
}
