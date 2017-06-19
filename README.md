# webp-convert
Convert jpeg/png to webp with available tool.

Unfortunately, there is currently no pure PHP method available for converting images into WebP format, which works on any server setup. This PHP script will try a number of tools in order of your preference.

The script takes the following arguments:

*filename*\
The filename of target file.

*destination-folder*\
Path of destination folder. Relative path is allowed

*quality*\
The quality of the generated WebP image, 0-100.

*preferred_tools* (optional)\
The order to try the tools in. Comma-separated list.
Allowed values: "cwebp", "imagewebp"
Default order is: cwebp, imagewebp

*absrel* (optional)\
$filename_absolute = $_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['absrel'] . $_GET['file'];


## Tools

### imagewebp
Uses the php function [imagewebp](http://php.net/manual/en/function.imagewebp.php). The function is is available from PHP 5.5.0. However, it requires that PHP is compiled with WebP support, which unfortunately aren't the case on many webhosts (according to [this link](https://stackoverflow.com/questions/25248382/how-to-create-a-webp-image-in-php)). WebP generation in PHP 5.5 requires that php is configured with the "--with-vpx-dir" flag and in PHP 7.0, php has to be configured --with-webp-dir flag [source](http://il1.php.net/manual/en/image.installation.php).

### cwebp
Calls the cwebp binary with an exec() call. Works on surprisingly many webhosts ([here is a list](https://wordpress.org/plugins/ewww-image-optimizer/#installation)). There is precompiled binaries in the /bin folder, compiled for different OS'es. Thanks to Shane Bishop for letting me copy his precompilations which comes with his plugin, [EWWW Image Optimizer](https://ewww.io/).

Currently though, only linux version is called. Will be fixed soon.

## SECURITY
TODO! - The script does not currently sanitize values.


