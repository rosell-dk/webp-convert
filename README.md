# webp-convert
Convert jpeg/png to webp with PHP (if at all possible)

The state of webp conversion in PHP is currently as such: There are several ways to do it, but they all require *something* of the server-setup. What works on one shared host might not work on another.

This php script is able to convert to webp using several methods. It will try one method after the other until success, or every method failed. You can setup the desired order with the "preferred_tools" option.

## Usage

Basic usage:
```
include( __DIR__ . '/WebPConvertClass.php');

$source = $_SERVER['DOCUMENT_ROOT'] . '/images/subfolder/logo.jpg';
$destination = $_SERVER['DOCUMENT_ROOT'] . '/images/subfolder/logo.jpg.webp';
$quality = 90;
$strip_metadata = TRUE;

WebPConvert::$serve_converted_image = TRUE;
WebPConvert::$serve_original_image_on_fail = TRUE;
WebPConvert::set_preferred_tools(array('cwebp','imagewebp'));
WebPConvert::convert($source, $destination, $quality, $strip_metadata);
```

## The script

webp-convert.php can be used to serve converted images. It accepts the following parameters in the URL:

*source:*\
Path to source file. Can be absolute or relative (relative to document root). If it starts with "/", it is considered an absolute path.

*destination-root (optional):*\
The final destination will be calculated like this: [desired destination root] + [relative path of source file] + ".webp". If you want converted files to be put in the same folder as the originals, you can set destination-root to ".", or leave it blank. If you on the other hand want all converted files to reside in their own folder, set the destination-root to point to that folder. The converted files will be stored in a hierarchy that matches the source files. With destination-root set to "webp-cache", the source file "images/2017/cool.jpg" will be stored at "webp-cache/images/2017/cool.jpg.webp". Both absolute paths and relative paths are accepted (if the path starts with "/", it is considered an absolute path). Double-dots in paths are allowed, ie "../webp-cache"

*quality:*\
The quality of the generated WebP image, 0-100.

*strip-metadata:*\
If set (if "&strip-metadata" is appended to the url), metadata will not be copied over in the conversion process. Note however that not all tools supports copying metadata. cwebp supports it, imagewebp does not. You can also assign a value. Any value but "no" counts as yes

*preferred-tools (optional):*\
Setting this manipulates the default order in which the tools are tried. If you for example set it to "cwebp", it means that you want "cwebp" to be tried first. You can specify several favourite tools. Setting it to "cwebp,imagewebp" will put cwebp to the top of the list and imagewebp will be the next tool to try, if cwebp fails. The option will not remove any tools from the list, only change the order.

*serve-image (optional):*\
If set (if "&serve-image" is appended to the URL), the converted image will be served. Otherwise the script will produce text output about the convertion process. You can also assign a value. Any value but "no" counts as yes.

*debug (optional):*\
When WebPConvert is told to serve an image, but all tools fails to convert, the default action of WebPConvert is to serve the original image. End-users will not notice the fail, which is good on production servers, but not on development servers. With debugging enabled, WebPConvert will generate an image with the error message, when told to serve image, and things go wrong.



## Methods currently implemented

### imagewebp
[imagewebp](http://php.net/manual/en/function.imagewebp.php) is a function that comes with PHP (>5.5.0) *provided* that PHP has been compiled with WebP support. Due to a [bug](https://bugs.php.net/bug.php?id=66590), some versions sometimes created corrupted images. That bug can however easily be fixed in PHP (fix was released [here](https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files)). However, I have experienced corrupted images *anyway*. So use this tool with caution. The corrupted images shows as completely transparent images in Google Chrome, but with correct size.

#### Requirements
* PHP > 5.5.0 compiled with WebP support

To get WebP support in PHP 5.5, PHP must be configured with the "--with-vpx-dir" flag. In PHP 7.0, php has to be configured with the "--with-webp-dir" flag [source](http://il1.php.net/manual/en/image.installation.php).

#### Availability
Unfortunately, according to [this link](https://stackoverflow.com/questions/25248382/how-to-create-a-webp-image-in-php)), WebP support on shared hosts is rare.


### cwebp
[cwebp](https://developers.google.com/speed/webp/docs/cwebp) is a WebP convertion command line tool released by Google. Our implementation simply looks in the /bin folder for a precompiled binary appropriate for the OS, and executes it with [exec()](http://php.net/manual/en/function.exec.php). Thanks to Shane Bishop for letting me copy his precompilations which comes with his plugin, [EWWW Image Optimizer](https://ewww.io/).

The script tests the checksum of the binary before executing it. This means that you cannot just replace a binary - you will have to edit the script. If you find the need to use another binary than those that comes with this project, please write - chances are that it should be added to the project.

#### Requirements
* exec()

#### Availability
exec() is available on surprisingly many webhosts, and a PHP solution calling exec() has been reported to work on many [here is a list](https://wordpress.org/plugins/ewww-image-optimizer/#installation))

## SECURITY
TODO! - The script does not currently sanitize values.

## Roadmap
* Method: "EWWW Image Optimizer"
* Method: imagemagick




