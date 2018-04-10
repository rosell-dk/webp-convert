<?php

namespace WebPConvert\Converters;

class Ewww
{
    // Checks if all requirements of cURL are met
    protected static function checkRequirements($curl_file_create = true)
    {
        if (!extension_loaded('curl')) {
            throw new \Exception('Required cURL extension is not available.');
        }

        if (!function_exists('curl_init')) {
            throw new \Exception('Required url_init() function is not available.');
        }

        if (!$curl_file_create && !function_exists('curl_file_create')) {
            throw new \Exception('Required curl_file_create() function is not available (requires PHP > 5.5).');
        }

        if (!defined("WEBPCONVERT_EWWW_KEY")) {
            throw new \Exception('Missing API key.');
        }

        return true;
    }

    // Throws an exception if the provided API key is invalid
    public static function isValidKey($key = WEBPCONVERT_EWWW_KEY)
    {
        try {
            self::checkRequirements(false);
            $ch = curl_init();
            if (!$ch) {
                throw new \Exception('Could not initialise cURL.');
            }

            $headers = [];
            $headers[] = "Content-Type: application/x-www-form-urlencoded";

            curl_setopt($ch, CURLOPT_URL, "https://optimize.exactlywww.com/quota/");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "api_key=' . $key . '");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }
        } catch (\Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }

        /*
         * There are three possible responses:
         * 'great' = verification successful
         * 'exceeded' = indicates a valid key with no remaining image credits
         * '' = an empty response indicates that the key is not valid
        */

        $result[] = $response;
        curl_close($ch);

        return implode($result, '<br>');
    }

    public static function convert($source, $destination, $quality, $stripMetadata)
    {
        try {
            self::checkRequirements();

            $ch = curl_init();
            if (!$ch) {
                throw new \Exception('Could not initialise cURL.');
            }

            $curlOptions = [
                'api_key' => WEBPCONVERT_EWWW_KEY,
                'webp' => '1',
                'file' => curl_file_create($source),
                'domain' => $_SERVER['HTTP_HOST'],
                'quality' => $quality,
                'metadata' => ($stripMetadata ? '0' : '1')
            ];

            curl_setopt_array($ch, [
                CURLOPT_URL => "https://optimize.exactlywww.com/v2/",
                CURLOPT_HTTPHEADER => [
                    'User-Agent: WebPConvert',
                    'Accept: image/*'
                ],
                CURLOPT_POSTFIELDS => $curlOptions,
                CURLOPT_BINARYTRANSFER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }
        } catch (\Exception $e) {
            //echo $e->getMessage();
            return false; // TODO: `throw` custom \Exception $e & handle it smoothly on top-level.
        }

        // The API does not always return images.
        // For example, it may return a message such as '{"error":"invalid","t":"exceeded"}
        // Messages has a http content type of ie 'text/html; charset=UTF-8
        // Images has application/octet-stream.
        // So verify that we got an image back.
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) != 'application/octet-stream') {
            curl_close($ch);
            return false;
        }

        // Not sure this can happen. So just in case
        if ($response == '') {
            return false;
        }

        $success = file_put_contents($destination, $response);

        if (!$success) {
            return false;
        }

        return true;
    }
}
