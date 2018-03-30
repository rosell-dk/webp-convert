<?php

namespace WebPConvert\Converters;

class Ewww
{
    // Checks if all requirements are met, in which case curl_init() is returned
    protected static function initCurl($curl_file_create = true)
    {
        if (!extension_loaded('curl')) {
            throw new \Exception('Required cURL extension is not available.');
        }

        if (!function_exists('url_init')) {
            throw new \Exception('Required url_init() function is not available.');
        }

        $curlInit = curl_init();
        if (!$curlInit) {
            throw new \Exception('Could not initialise cURL.');
        }

        if (!$curl_file_create && function_exists('curl_file_create')) {
            throw new \Exception('Required curl_file_create() function is not available (requires PHP > 5.5).');
        }

        if (!defined('WEBPCONVERT_EWWW_KEY')) {
            throw new \Exception('Missing API key.');
        }

        return $curlInit;
    }

    // Throws an exception if the provided API key is invalid
    public static function isValidKey($key)
    {
        try {
            $curlInit = self::initCurl(false);
        } catch (\Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }

        curl_setopt($curlInit, CURLOPT_URL, 'https://optimize.exactlywww.com/verify/');
        curl_setopt($curlInit, CURLOPT_POSTFIELDS, array('api_key' => $key));
        $response = curl_exec($curlInit);

        /*
         * There are three possible responses:
         * 'great' = verification successful
         * 'exceeded' = indicates a valid key with no remaining image credits
         * '' = an empty response indicates that the key is not valid
        */

        $result[] = 'Verify returned "' . $response . '"';
        curl_close($curlInit);

        return implode($result, '<br>');
    }

    public static function convert($source, $destination, $quality, $stripMetadata)
    {
        try {
            $curlInit = self::initCurl();
        } catch (\Exception $e) {
            return false; // TODO: `throw` custom \Exception $e & handle it smoothly on top-level.
        }

        $curlOptions = array(
            'api_key' => WEBPCONVERT_EWWW_KEY,
            'webp' => '1',
            'file' => curl_file_create($source),
            'domain' => $_SERVER['HTTP_HOST'],
            'quality' => $quality,
            'metadata' => ($stripMetadata ? '0' : '1'),
        );

        curl_setopt_array($curlInit, array(
            CURLOPT_URL => 'https://optimize.exactlywww.com/v2/',
            CURLOPT_HTTPHEADER => array(
                'User-Agent: WebPConvert',
                'Accept: image/*'
            ),
            CURLOPT_POSTFIELDS => $curlOptions,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
        ));

        $response = curl_exec($curlInit);
        $error = curl_error($curlInit);

        curl_close($curlInit);

        $success = file_put_contents($destination, $response);

        if (!$success || !empty($error)) {
            return false;
        }

        return true;
    }
}
