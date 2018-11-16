<?php

namespace WebPConvert\Convert;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;
use WebPConvert\Convert\BaseConverter;

class CloudConverter extends BaseConverter
{
    public static function testCurlRequirements()
    {
        if (!extension_loaded('curl')) {
            throw new ConverterNotOperationalException('Required cURL extension is not available.');
        }

        if (!function_exists('curl_init')) {
            throw new ConverterNotOperationalException('Required url_init() function is not available.');
        }

        if (!function_exists('curl_file_create')) {
            throw new ConverterNotOperationalException(
                'Required curl_file_create() function is not available (requires PHP > 5.5).'
            );
        }

    }

    public static function initCurl()
    {
        $ch = curl_init();
        if (!$ch) {
            throw new ConverterNotOperationalException('Could not initialise cURL.');
        }
        return $ch;
    }

    // Parse size found in php.ini
    // Took the parser from Drupal
    public static function parseSize($size)
    {

        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
            $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power
            // of magnitude to multiply a kilobyte by.
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }

    public function testFilesizeRequirements()
    {
        $fileSize = @filesize($this->source);
        if ($fileSize !== false) {
            $uploadMaxSize = self::parseSize(ini_get('upload_max_filesize'));
            if (($uploadMaxSize !== false) && ($uploadMaxSize < $fileSize)) {
                throw new ConverterFailedException(
                    'File is larger than your max upload (set in your php.ini). File size:' .
                        round($fileSize/1024) . ' kb. ' .
                        'upload_max_filesize in php.ini: ' . ini_get('upload_max_filesize') .
                        ' (parsed as ' . round($uploadMaxSize/1024) . ' kb)'
                );
            }

            $postMaxSize = self::parseSize(ini_get('post_max_size'));
            if (($postMaxSize !== false) && ($postMaxSize < $fileSize)) {
                throw new ConverterFailedException(
                    'File is larger than your post_max_size limit (set in your php.ini). File size:' .
                        round($fileSize/1024) . ' kb. ' .
                        'post_max_size in php.ini: ' . ini_get('post_max_size') .
                        ' (parsed as ' . round($postMaxSize/1024) . ' kb)'
                );
            }

            // ini_get('memory_limit')
        }
    }
}
