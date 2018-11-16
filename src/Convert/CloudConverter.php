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
}
