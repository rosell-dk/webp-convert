<?php

namespace WebPConvert\Convert\BaseConverters;

use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\BaseConverters\AbstractConverter;

/**
 * Base for converters that uses a cloud service and curl
 *
 * Handles checking that curl extension is loaded and functional.
 * Also provides a little helper for initializing curl (which throws in case of error)
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
abstract class AbstractCloudCurlConverter extends AbstractCloudConverter
{

    /**
     * Check basis operationality for converters relying on curl
     *
     * @throws  SystemRequirementsNotMetException
     * @return  void
     */
    public function checkOperationality()
    {
        parent::checkOperationality();

        if (!extension_loaded('curl')) {
            throw new SystemRequirementsNotMetException('Required cURL extension is not available.');
        }

        if (!function_exists('curl_init')) {
            throw new SystemRequirementsNotMetException('Required url_init() function is not available.');
        }

        if (!function_exists('curl_file_create')) {
            throw new SystemRequirementsNotMetException(
                'Required curl_file_create() function is not available (requires PHP > 5.5).'
            );
        }
    }

    /**
     * Init curl.
     *
     * @throws  SystemRequirementsNotMetException  if curl could not be initialized
     * @return  resource  curl handle
     */
    public static function initCurl()
    {
        // Get curl handle
        $ch = curl_init();
        if ($ch === false) {
            throw new SystemRequirementsNotMetException('Could not initialise cURL.');
        }
        return $ch;
    }
}
