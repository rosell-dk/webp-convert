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

*webp-convert.php* can be used to serve converted images, or just convert without serving. It accepts the following parameters in the URL:

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



## Converters

Each "method" of converting an image to webp are implemented as a separate converter. WebPConvertClass autodetects the converters by scanning the "converters" directory, so it is easy to add new converters, and safe to remove existing ones

The following plugins are implemented:

### gd - Fast. But not good for PNG's
```Requirements..```: GD extension and PHP > 5.5.0 compiled with WebP support<br>
```Speed.........```: Around 30 ms to convert a 40kb image<br>
```Reliability...```: Not sure. I have experienced corrupted images, but cannot reproduce<br>
```Availability..```: Unfortunately, according to [this link](https://stackoverflow.com/questions/25248382/how-to-create-a-webp-image-in-php), WebP support on shared hosts is rare.<br>

[imagewebp](http://php.net/manual/en/function.imagewebp.php) is a function that comes with PHP (>5.5.0) *provided* that PHP has been compiled with WebP support. Due to a [bug](https://bugs.php.net/bug.php?id=66590), some versions sometimes created corrupted images. That bug can however easily be fixed in PHP (fix was released [here](https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files)). However, I have experienced corrupted images *anyway*. So use this tool with caution. The corrupted images shows as completely transparent images in Google Chrome, but with correct size.

To get WebP support in PHP 5.5, PHP must be configured with the "--with-vpx-dir" flag. In PHP 7.0, php has to be configured with the "--with-webp-dir" flag [source](http://il1.php.net/manual/en/image.installation.php).

The converter does not support copying metadata

GD unfortunately does not expose any WebP options. Lacking the option to set lossless encoding results in poor encoding of PNG's. 

### imagick - Great, but rarely available
```Requirements..```: imagick extension compiled with WebP support<br>
```Speed.........```: Around 50 ms to convert a 40kb image<br>
```Reliability...```: I'm not aware of any problems<br>
```Availability..```: Probably only available on few shared hosts (if any)<br>

The greatest problem here is the availability. The extension, php-imagick does currently not come with WebP support out of the box. And I could find no quick and easy way to add it. To make it work, I had to: 
1. [Compile libwebp from source](https://developers.google.com/speed/webp/docs/compiling)
2. [Compile imagemagick from source](https://www.imagemagick.org/script/install-source.php) (```./configure --with-webp=yes```)
3. Compile php-imagick from source, phpize it and add ```extension=/path/to/imagick.so``` to php.ini

But once installed, it works great and has several WebP options. In this implementation, we have set:
- *webp:method* = 6 (we prioritize quality over speed)
- *webp:low-memory* = true (memory can be an issue on some shared hosts)
- *webp:lossless* = true (for PNG's only, of course)


### cwebp - Great, fast enough but requires exec()
```Requirements..```: exec()<br>
```Speed.........```: Around 140 ms to convert a 40kb image<br>
```Reliability...```: Great<br>
```Availability..```: exec() is available on surprisingly many webhosts, and the PHP solution by *EWWW Image Optimizer*, which this code is largely based on has been reported to work on many webhosts - [here is a list](https://wordpress.org/plugins/ewww-image-optimizer/#installation)<br>

[cwebp](https://developers.google.com/speed/webp/docs/cwebp) is a WebP convertion command line tool released by Google. A its core, our implementation looks in the /bin folder for a precompiled binary appropriate for the OS and executes it with [exec()](http://php.net/manual/en/function.exec.php). Thanks to Shane Bishop for letting me copy his precompilations which comes with his plugin, [EWWW Image Optimizer](https://ewww.io/). 

Official precompilations are available on [here](https://developers.google.com/speed/webp/docs/precompiled). But note that our script tests the checksum of the binary before executing it. This means that you cannot just replace a binary - you will have to change the checksum hardcoded in *converters/cwebp.php* too. If you find the need to use another binary, than those that comes with this project, please write - chances are that it should be added to the project.

In more detail, the implementation does this:
- Binary is selected form the bin-folder, according to OS
- If no binary is found, or if execution fails, try common system paths ('/usr/bin/cwebp' etc)
- Before executing binary, the checksum is tested
- Options are generated. -lossless is used for png. -metadata is set to "all" or "none"
- If "nice" command is found on host, then binary is run with low priority in order to save system ressources
- The permissions of the generated file is set to be the same as parent
- It is detected whether the command succeeds or not

Credits also goes to Shane regarding the code that revolves around the exec(). Most of it is a refactoring of the code in [EWWW Image Optimizer](https://ewww.io/).

### ewww: Cheap and reliable fallback. But slow
```Requirements..```: A valid key to [EWWW Image Optimizer](https://ewww.io/), curl and PHP >= 5.5<br>
```Speed.........```: Around 1300 ms to convert a 40kb image<br>
```Reliability...```: Great<br>
```Availability..```: Should work on *almost* any webhost. - The curl extension is available on most shared hosts. As PHP 5.3 and PHP 5.4 is no longer supported, the PHP requirement should not be an issue. A key is of course available to anyone with a credit card.<br>


EWWW Image Optimizer is a cloud service. You purchase a key and then you can connect. Otherwise, there is not much to say. The key is very cheap, just below one dollar. It should work on *almost* any webhost, making it a cheap and reliable fallback. But not as fast as the other plugins.

The plugin could be improved by using *fsockopen* if *curl* is not available.

The plugin does not currently support metadata option (but the cloud service does)



## SECURITY
TODO! - The script does not currently sanitize values.

## Roadmap
* Sanitize






