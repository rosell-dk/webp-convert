# webp-convert
Convert jpeg/png to webp with available tool.

Unfortunately, there is currently no pure PHP method available for converting images into WebP format, which works on any server setup. This PHP script will try a number of tools in order of your preference.

The script takes the following arguments:

*filename*\
Either the relative path to the file (relative to document root) or an absolute path. If it starts with "/", it is considered an absolute path.

*destination-folder*\
Path of destination (relative to target file) or an absolute path. Double-dots are allowed, ie "../../webp-cache/images/2017"

*quality*\
The quality of the generated WebP image, 0-100.

*preferred_tools* (optional)\
The order to try the tools in. Comma-separated list.
Allowed values: "cwebp", "imagewebp"
Default order is: cwebp, imagewebp

*absrel* (optional)\
Might be relevant in rare occasions where you 
$filename_absolute = $_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['absrel'] . $_GET['file'];

*debug* (optional)\
If set (if "&debug" is appended to the URL), the script will produce text output instead of an image.

## Tools

### imagewebp
Uses the php function [imagewebp](http://php.net/manual/en/function.imagewebp.php). The function is is available from PHP 5.5.0. However, it requires that PHP is compiled with WebP support, which unfortunately aren't the case on many webhosts (according to [this link](https://stackoverflow.com/questions/25248382/how-to-create-a-webp-image-in-php)). WebP generation in PHP 5.5 requires that php is configured with the "--with-vpx-dir" flag and in PHP 7.0, php has to be configured --with-webp-dir flag [source](http://il1.php.net/manual/en/image.installation.php).

### cwebp
Calls the cwebp binary with an exec() call. Works on surprisingly many webhosts ([here is a list](https://wordpress.org/plugins/ewww-image-optimizer/#installation)). There is precompiled binaries in the /bin folder, compiled for different OS'es. Thanks to Shane Bishop for letting me copy his precompilations which comes with his plugin, [EWWW Image Optimizer](https://ewww.io/).

If your OS isn't supported, you can put a binary called "cwebp-custom" into the "bin" folder

## SECURITY
TODO! - The script does not currently sanitize values. Also exec() does not md5-test cwebp before running it


