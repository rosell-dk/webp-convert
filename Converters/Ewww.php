<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class Ewww
{
    public static function convert($source, $destination, $quality = 80, $stripMetadata = true, $options = array())
    {
        ConverterHelper::prepareDestinationFolderAndRunCommonValidations($source, $destination);

        $defaultOptions = array(
            'key' => '',
        );

        // For backwards compatibility
        if (defined("WEBPCONVERT_EWWW_KEY")) {
            if (!isset($options['key'])) {
                $options['key'] = WEBPCONVERT_EWWW_KEY;
            }
        }

        $options = array_merge($defaultOptions, $options);

        if (!extension_loaded('curl')) {
            throw new ConverterNotOperationalException('Required cURL extension is not available.');
        }

        if (!function_exists('curl_init')) {
            throw new ConverterNotOperationalException('Required url_init() function is not available.');
        }

        if (!function_exists('curl_file_create')) {
            throw new ConverterNotOperationalException('Required curl_file_create() function is not available (requires PHP > 5.5).');
        }

        if ($options['key'] == '') {
            throw new ConverterNotOperationalException('Missing API key.');
        }

        $ch = curl_init();
        if (!$ch) {
            throw new ConverterNotOperationalException('Could not initialise cURL.');
        }

        $curlOptions = [
            'api_key' => $options['key'],
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
            throw new ConverterNotOperationalException(curl_error($ch));
        }

        // The API does not always return images.
        // For example, it may return a message such as '{"error":"invalid","t":"exceeded"}
        // Messages has a http content type of ie 'text/html; charset=UTF-8
        // Images has application/octet-stream.
        // So verify that we got an image back.
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) != 'application/octet-stream') {
            curl_close($ch);
            throw new ConverterNotOperationalException('ewww api did not return an image. It could be that the key is invalid');
        }

        // Not sure this can happen. So just in case
        if ($response == '') {
            throw new ConverterNotOperationalException('ewww api did not return anything');
        }

        $success = file_put_contents($destination, $response);

        if (!$success) {
            throw new ConverterFailedException('Error saving file');
        }
    }


    /*
        public static function getQuota($key) {
            curl_setopt($ch, CURLOPT_URL, "https://optimize.exactlywww.com/quota/");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "api_key=' . $key . '");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


        }*/

        // Throws an exception if the provided API key is invalid..
        /*
        public static function isValidKey($key)
        {
            if (!extension_loaded('curl')) {
                throw new \Exception('Required cURL extension is not available.');
            }

            if (!function_exists('curl_init')) {
                throw new \Exception('Required url_init() function is not available.');
            }

            $ch = curl_init();
            if (!$ch) {
                throw new \Exception('Could not initialise cURL.');
            }


            $headers = [];
            $headers[] = "Content-Type: application/x-www-form-urlencoded";

            curl_setopt($ch, CURLOPT_URL, "https://optimize.exactlywww.com/verify/");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "api_key=' . $key . '");
            //curl_setopt($ch, CURLOPT_POSTFIELDS, array('api_key' => $key));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

            // The 403 forbidden is avoided with this line.
            // but still, we just get empty answer
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)');

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            //curl_setopt($ch, CURLOPT_POST, 1);

            $response = curl_exec($ch);

            //$result[] = $response;
            //echo implode($result, '<br>');

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }
            curl_close($ch);

            /*
             * There are three possible responses:
             * 'great' = verification successful
             * 'exceeded' = indicates a valid key with no remaining image credits
             * '' = an empty response indicates that the key is not valid
            */ /*
    //echo 'response: ' . $response . '...';
            return ($response == 'great');
        }*/
}
