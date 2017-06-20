# webp-convert
Convert jpeg/png to webp with PHP (if at all possible)

The state of webp conversion in PHP is currently as such: There are several ways to do it, but they all require *something* of the server-setup. What works on one shared host might not work on another.

This php script aim to provide *ALL* methods. It will try one method after the other until success, or everything failed. You can setup the desired order with the "preferred_tools" option. D


/frameworks/tools available, but It is possible 

Unfortunately, there is currently no pure PHP method available for converting images into WebP format, which works on any server setup. This PHP script will try a number of tools in order of your preference.

The script takes the following arguments:

*source*\
Path to source file. Can be absolute or relative (relative to document root). If it starts with "/", it is considered an absolute path.

*destination (optional) (NOT IMPLEMENTED YET)*\
Path to destination file. Can be absolute or relative (relative to document root). You can choose not to specify destination. In that case, the path will be created based upon source, destination-root and absrel settings. If all these are blank, the destination will be same folder as source, and the filename will have ".webp" appended to it (ie image.jpeg.webp)

*destination-root (optional)*\
Path of destination (relative to document root) or an absolute path. If not supplied, then the converted file will be placed in same folder as the target. Double-dots are allowed, ie "../../webp-cache/images/2017"

*quality*\
The quality of the generated WebP image, 0-100.

*preferred_tools* (optional)\
Set the priority of the tools, that is, the order to try the tools in. You do not have to specify all tools. The tools you specify will move to the top of the list. The script will always try all tools before giving up.

Comma-separated list.
Allowed values: "cwebp", "imagewebp"
Default order is: cwebp, imagewebp

*absrel* (optional)\
Might be relevant in rare occasions where you want to pass relative source path, but don't have the start of it.

*debug* (optional)\
If set (if "&debug" is appended to the URL), the script will produce text output instead of an image.

## Tools

### imagewebp
Uses the php function [imagewebp](http://php.net/manual/en/function.imagewebp.php). The function is is available from PHP 5.5.0. However, it requires that PHP is compiled with WebP support, which unfortunately aren't the case on many webhosts (according to [this link](https://stackoverflow.com/questions/25248382/how-to-create-a-webp-image-in-php)). WebP generation in PHP 5.5 requires that php is configured with the "--with-vpx-dir" flag and in PHP 7.0, php has to be configured --with-webp-dir flag [source](http://il1.php.net/manual/en/image.installation.php).

### cwebp
Calls the cwebp binary with an exec() call. Works on surprisingly many webhosts ([here is a list](https://wordpress.org/plugins/ewww-image-optimizer/#installation)). There is precompiled binaries in the /bin folder, compiled for different OS'es. Thanks to Shane Bishop for letting me copy his precompilations which comes with his plugin, [EWWW Image Optimizer](https://ewww.io/).

The script tests the checksum of the binary before executing it. This means that you cannot just replace a binary - you will have to edit the script. If you find the need to use another binary than those that comes with this project, please write - chances are that it should be added to the project.

## SECURITY
TODO! - The script does not currently sanitize values.


