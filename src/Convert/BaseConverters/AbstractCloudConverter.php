<?php

namespace WebPConvert\Convert\BaseConverters;

use WebPConvert\Convert\Exceptions\ConversionFailedException;
use WebPConvert\Convert\BaseConverters\AbstractConverter;

abstract class AbstractCloudConverter extends AbstractConverter
{
    /**
     * Parse a shordhandsize string as the ones returned by ini_get()
     *
     * Parse a shorthandsize string having the syntax allowed in php.ini and returned by ini_get().
     * Ie "1K" => 1024.
     * Strings without units are also accepted.
     * The shorthandbytes syntax is described here: https://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
     *
     * @param  string  $shortHandSize  A size string of the type returned by ini_get()
     * @return float|false  The parsed size (beware: it is float, do not check high numbers for equality),
     *                      or false if parse error
     */
    protected static function parseShortHandSize($shortHandSize)
    {

        $result = preg_match("#^\\s*(\\d+(?:\\.\\d+)?)([bkmgtpezy]?)\\s*$#i", $shortHandSize, $matches);
        if ($result !== 1) {
            return false;
        }

        // Truncate, because that is what php does.
        $digitsValue = floor($matches[1]);

        if ((count($matches) >= 3) && ($matches[2] != '')) {
            $unit = $matches[2];

            // Find the position of the unit in the ordered string which is the power
            // of magnitude to multiply a kilobyte by.
            $position = stripos('bkmgtpezy', $unit);

            return floatval($digitsValue * pow(1024, $position));
        } else {
            return $digitsValue;
        }
    }

    /*
    * Get the size of an php.ini option.
    *
    * Calls ini_get() and parses the size to a number.
    * If the configuration option is null, does not exist, or cannot be parsed as a shorthandsize, false is returned
    *
    * @param  string  $varname  The configuration option name.
    * @return float|false  The parsed size or false if the configuration option does not exist
    */
    protected static function getIniBytes($iniVarName)
    {
        $iniVarValue = ini_get($iniVarName);
        if (($iniVarValue == '') || $iniVarValue === false) {
            return false;
        }
        return self::parseShortHandSize($iniVarValue);
    }

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
            $uploadMaxSize = self::getIniBytes('upload_max_filesize');
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

            $postMaxSize = self::getIniBytes(ini_get('post_max_size'));
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

            // Should we worry about memory limit as well?
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
