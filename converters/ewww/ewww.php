<?php

function webpconvert_ewww($source, $destination, $quality, $strip_metadata)
{
    if (!extension_loaded('curl')) {
        return 'This implementation requires the curl extension, which you do not have';
    }

    if (!function_exists('curl_init')) {
        return 'curl is loaded, but <i>curl_init</i> is unavailable';
    }

    $ch = curl_init();
    if ($ch === false) {
        return 'Could not init curl';
    }

    if (!function_exists('curl_file_create')) {
        return 'This implementation requires PHP function <i>curl_file_create</i>, but it is not available, sorry. It should be available in PHP > 5.5';
    }

    if (!defined("WEBPCONVERT_EWW_KEY")) {
        return 'Key is missing. To use EWWW Image Converter, you must first purchase a key and then set it up by defining a constant "WEBPCONVERT_EWW_KEY". Ie: define("WEBPCONVERT_EWW_KEY", "your_key_here");';
    }
    $key = WEBPCONVERT_EWW_KEY;

    // Ok, lets get to it!

    $msgs = array();
    $msgs[] = 'Requesting image from EWWW Image Converter service, using curl';

    $cFile = curl_file_create($source);

    $url = 'https://optimize.exactlywww.com';
    curl_setopt_array($ch, array(
      CURLOPT_URL => $url,
      CURLOPT_HTTPHEADER => array(
        'User-Agent: WebPConvert',
        'Accept: image/*'
      ),
      CURLOPT_POSTFIELDS => array(
        'api_key' => $key,
        'webp' => '1',
        'file' => $cFile,
        'domain' => $_SERVER['HTTP_HOST'],
        'quality' => $quality,
        'metadata' => ($strip_metadata ? '0' : '1'),
      ),
      CURLOPT_BINARYTRANSFER => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => false,
      CURLOPT_SSL_VERIFYPEER => false
    ));

    $response = curl_exec($ch);

    if (empty($response)) {
        $msgs[] = 'We got nothing back. Perhaps key is invalid. Testing key with curl call to "verify"';
        $msgs[] = 'If it returns "great", then it means that the key should be ok (but something else went wrong). If it does not return "great", then its probably the key that is invalid, or has expired';

        // We got nothing back. Perhaps key is invalid? Lets check the key
        curl_setopt($ch, CURLOPT_URL, 'https://optimize.exactlywww.com/verify/');
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('api_key' => $key));

        $response = curl_exec($ch);
        $msgs[] = 'Verify returned "' . $response . '"';
        curl_close($ch);
        return implode($msgs, '<br>');
    }
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!empty($curlError)) {
        return 'curl error' . $curlError;
    }

    if (!file_put_contents($destination, $response)) {
        return 'Failed writing file' . $response;
    }

    return true;
}
