<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class Ewww
{
    public static $extraOptions = [
        [
            'name' => 'key',
            'type' => 'string',
            'sensitive' => true,
            'default' => '',
            'required' => true
        ],
    ];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('ewww', $source, $destination, $options, true);
    }

    // Took this parser from Drupal
    private static function parseSize($size)
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

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options, $logger)
    {
        if ($options['key'] == '') {
            throw new ConverterNotOperationalException('Missing API key.');
        }
        if (strlen($options['key']) < 20) {
            throw new ConverterNotOperationalException(
                'Key is invalid. Keys are supposed to be 32 characters long - your key is much shorter'
            );
        }

        $keyStatus = self::getKeyStatus($options['key']);
        switch ($keyStatus) {
            case 'great':
                break;
            case 'exceeded':
                throw new ConverterNotOperationalException('quota has exceeded');
                break;
            case 'invalid':
                throw new ConverterNotOperationalException('key is invalid');
                break;
        }

        $fileSize = @filesize($source);
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


        $ch = ConverterHelper::initCurlForConverter();

        $curlOptions = [
            'api_key' => $options['key'],
            'webp' => '1',
            'file' => curl_file_create($source),
            'domain' => $_SERVER['HTTP_HOST'],
            'quality' => $options['_calculated_quality'],
            'metadata' => ($options['metadata'] == 'none' ? '0' : '1')
        ];

        curl_setopt_array(
            $ch,
            [
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
            ]
        );

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
            //echo curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            /* May return this: {"error":"invalid","t":"exceeded"} */
            $responseObj = json_decode($response);
            if (isset($responseObj->error)) {
                //echo 'error:' . $responseObj->error . '<br>';
                //echo $response;
                //self::blacklistKey($key);
                //throw new ConverterNotOperationalException('The key is invalid. Blacklisted it!');
                throw new ConverterNotOperationalException('The key is invalid');
            }

            throw new ConverterNotOperationalException(
                'ewww api did not return an image. It could be that the key is invalid. Response: '
                . $response
            );
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

    /**
     *  Keep subscription alive by optimizing a jpeg
     *  (ewww closes accounts after 6 months of inactivity - and webp conversions seems not to be counted? )
     */
    public static function keepSubscriptionAlive($source, $key)
    {
        try {
            $ch = curl_init();
        } catch (\Exception $e) {
            return 'curl is not installed';
        }
        curl_setopt_array(
            $ch,
            [
            CURLOPT_URL => "https://optimize.exactlywww.com/v2/",
            CURLOPT_HTTPHEADER => [
                'User-Agent: WebPConvert',
                'Accept: image/*'
            ],
            CURLOPT_POSTFIELDS => [
                'api_key' => $key,
                'webp' => '0',
                'file' => curl_file_create($source),
                'domain' => $_SERVER['HTTP_HOST'],
                'quality' => 60,
                'metadata' => 0
            ],
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
            ]
        );

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return 'curl error' . curl_error($ch);
        }
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) != 'application/octet-stream') {
            curl_close($ch);

            /* May return this: {"error":"invalid","t":"exceeded"} */
            $responseObj = json_decode($response);
            if (isset($responseObj->error)) {
                return 'The key is invalid';
            }

            return 'ewww api did not return an image. It could be that the key is invalid. Response: ' . $response;
        }

        // Not sure this can happen. So just in case
        if ($response == '') {
            return 'ewww api did not return anything';
        }

        return true;
    }

    /*
        public static function blacklistKey($key)
        {
        }

        public static function isKeyBlacklisted($key)
        {
        }*/

    /**
     *  Return "great", "exceeded" or "invalid"
     */
    public static function getKeyStatus($key)
    {
        $ch = ConverterHelper::initCurlForConverter();

        curl_setopt($ch, CURLOPT_URL, "https://optimize.exactlywww.com/verify/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            [
            'api_key' => $key
            ]
        );

        // The 403 forbidden is avoided with this line.
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)'
        );

        $response = curl_exec($ch);
        // echo $response;
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        // Possible responses:
        // “great” = verification successful
        // “exceeded” = indicates a valid key with no remaining image credits.
        // an empty response indicates that the key is not valid

        if ($response == '') {
            return 'invalid';
        }
        $responseObj = json_decode($response);
        if (isset($responseObj->error)) {
            if ($responseObj->error == 'invalid') {
                return 'invalid';
            } else {
                throw new \Exception('Ewww returned unexpected error: ' . $response);
            }
        }
        if (!isset($responseObj->status)) {
            throw new \Exception('Ewww returned unexpected response to verify request: ' . $response);
        }
        switch ($responseObj->status) {
            case 'great':
            case 'exceeded':
                return $responseObj->status;
        }
        throw new \Exception('Ewww returned unexpected status to verify request: "' . $responseObj->status . '"');
    }

    public static function isWorkingKey($key)
    {
        return (self::getKeyStatus($key) == 'great');
    }

    public static function isValidKey($key)
    {
        return (self::getKeyStatus($key) != 'invalid');
    }

    public static function getQuota($key)
    {
        $ch = ConverterHelper::initCurlForConverter();

        curl_setopt($ch, CURLOPT_URL, "https://optimize.exactlywww.com/quota/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            [
            'api_key' => $key
            ]
        );
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)'
        );

        $response = curl_exec($ch);
        return $response; // ie -830 23. Seems to return empty for invalid keys
        // or empty
        //echo $response;
    }
}
