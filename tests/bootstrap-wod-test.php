<?php
require __DIR__ . '/../src-build/webp-on-demand-1.inc';
require __DIR__ . '/../src-build/webp-on-demand-2.inc';


spl_autoload_register('webpconvert_disable_autoload', true, true);
function webpconvert_disable_autoload($class) {
    //echo $class . "\n";
    //return;
    /*
    if (strpos($class, 'WebPConvert\\') === 0) {
        if (strpos($class, 'WebPConvert\\Tests\\') !== 0) {
            throw new \Exception(
                'Autoloader was about to autoload a WebPConvert class. ' .
                'But that means it was not included in the build! "' . $class . '"');
            //require_once WEBPEXPRESS_PLUGIN_DIR . '/lib/classes/' . substr($class, 12) . '.php';
        }
    }
    if (strpos($class, 'ImageMimeTypeGuesser\\') === 0) {
        throw new \Exception(
            'Autoloader was about to autoload a ImageMimeTypeGuesser class. ' .
            'But that means it was not included in the build! "' . $class . '"');
    }*/
}
