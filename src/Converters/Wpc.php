<?php

namespace WebPConvert\Converters;

use WebPConvert\Converters\Exceptions\ConverterNotOperationalException;
use WebPConvert\Converters\Exceptions\ConverterFailedException;

class Wpc
{
    public static $extraOptions = [
        [
            'name' => 'secret',
            'type' => 'string',
            'sensitive' => true,
            'default' => 'my dog is white',
            'required' => true
        ],
        [
            'name' => 'url',
            'type' => 'string',
            'sensitive' => true,
            'default' => '',
            'required' => true
        ],
    ];

    public static function convert($source, $destination, $options = [])
    {
        ConverterHelper::runConverter('wpc', $source, $destination, $options, true);
    }

    // Although this method is public, do not call directly.
    public static function doConvert($source, $destination, $options = [], $logger)
    {
        if ($options['url'] == '') {
            throw new ConverterNotOperationalException('Missing URL. You must install WebpConvertCloudService on a server, and supply url');
        }

        if (!extension_loaded('curl')) {
            throw new ConverterNotOperationalException('Required cURL extension is not available.');
        }

        if (!function_exists('curl_init')) {
            throw new ConverterNotOperationalException('Required url_init() function is not available.');
        }

        if (!function_exists('curl_file_create')) {
            throw new ConverterNotOperationalException('Required curl_file_create() function is not available (requires PHP > 5.5).');
        }

        // Got some code here:
        // https://coderwall.com/p/v4ps1a/send-a-file-via-post-with-curl-and-php

        $ch = curl_init();
        if (!$ch) {
            throw new ConverterNotOperationalException('Could not initialise cURL.');
        }

        $optionsToSend = $options;

        if (isset($options['_quality_could_not_be_detected'])) {
            // quality was set to "auto", but we could not meassure the quality of the jpeg locally
            // Ask the cloud service to do it, rather than using what we came up with.
            $optionsToSend['quality'] = 'auto';
        }

        unset($optionsToSend['converters']);
        unset($optionsToSend['secret']);
        unset($optionsToSend['_quality_could_not_be_detected']);

        curl_setopt_array($ch, [
            CURLOPT_URL => $options['url'],
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => [
                'file' => curl_file_create($source),
                'hash' => md5(md5_file($source) . $options['secret']),
                'options' => json_encode($optionsToSend)
            ],
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new ConverterNotOperationalException(curl_error($ch));
        }

        // The WPC cloud service either returns an image or an error message
        // Images has application/octet-stream.

        // TODO: Check for 404 response, and handle that here

        // Verify that we got an image back.
        if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) != 'application/octet-stream') {
            curl_close($ch);
            throw new ConverterFailedException($response);
            //throw new ConverterNotOperationalException($response);
        }

        $success = file_put_contents($destination, $response);
        curl_close($ch);

        if (!$success) {
            throw new ConverterFailedException('Error saving file');
        }
        /*
                $curlOptions = [
                    'api_key' => $options['key'],
                    'webp' => '1',
                    'file' => curl_file_create($source),
                    'domain' => $_SERVER['HTTP_HOST'],
                    'quality' => $options['quality'],
                    'metadata' => ($options['metadata'] == 'none' ? '0' : '1')
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
                ]);*/
    }
}
