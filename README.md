# WebPConvert
*Convert JPEG & PNG to WebP with PHP*

In summary, the current state of WebP conversion in PHP is this: There are several ways to do it, but they all require *something* of the server setup. What works on one shared host might not work on another. `WebPConvert` combines these methods by iterating over them (optionally in the desired order) until one of them is successful - or all of them fail.

**Table of contents**
- [1. Introduction](#introduction)
- [2. Installation](#installation)
- [3. Usage](#usage)
- [4. API](#api)

## Introduction
Basically, there are three ways for JPEG & PNG to WebP conversion:
- Using a PHP extension (eg `gd` or `imagick`)
- Executing a binary directly using an `exec()` call (eg `cwebp`)
- Connecting to a cloud service which does the conversion (eg `EWWW`)

Converters **based on PHP extensions** should be your first choice. They are faster than other methods and they don't rely on server-side `exec()` calls (which may cause security risks). However, the `gd` converter doesn't support lossless conversion, so you may want to skip it when converting PNG images. Converters that **execute a binary** are also very fast (~ 50ms). Converters delegating the conversion process to a **cloud service** are much slower (~ one second), but work on *almost* any shared hosts (as opposed to the other methods). This makes the cloud-based converters an ideal last resort. They generally require you to *purchase* a paid plan, but the API key for [EWWW Image Optimizer](https://ewww.io) is very cheap. Beware though that you may encounter down-time whenever the cloud service is unavailable.

`WebPConvert` currently supports the following converters:

| Converter            | Method                                   | Summary                                              |
| -------------------- | ---------------------------------------- | ---------------------------------------------------- |
| [imagick](#imagick)  | Uses Imagick extension                   | Best converter, but rarely available on shared hosts |
| [gd](#gd)            | Uses GD Graphics extension               | Fast, but unable to do lossless encoding             |
| [cwebp](#cwebp)      | Calls cwebp binary directly              | Great, but requires `exec()` function                |
| [ewww](#ewww)        | Calls EWWW Image Optimizer cloud service | Works *almost* anywhere; slow, cheap, requires key.  |

## Installation
Simply require `WebPConvert` from the command line via [Composer](https://getcomposer.org):

```text
composer require rosell-dk/webp-convert
```

## Usage

### Basic usage example

```php
<?php
// Initialise your autoloader (this example is using composer)
require 'vendor/autoload.php';

// Define basic conversion options
$source = $_SERVER['DOCUMENT_ROOT'] . '/images/subfolder/logo.jpg';
$destination = $_SERVER['DOCUMENT_ROOT'] . '/images/subfolder/logo.jpg.webp';
$quality = 90;
$stripMetadata = true;

// Change order of converters (optional) & fire up WebP conversion
WebPConvert::setPreferredConverters(array('imagick','cwebp'));
WebPConvert::convert($source, $destination, $quality, $stripMetadata);
```

## API

### WebPConvert
*WebPConvert::convert($source, $destination, $quality, $stripMetadata)*\
- *$source:* (string) Absolute path to source image. Only forward slashes are allowed.\
- *$destination:* (string) Absolute path of the converted image. WebPConvert will take care of creating folders that does not exist. If there is already a file at the destination, it will be removed. Of course, it is required that the webserver has write permissions to the folder. Created folders will get the same permissions as the closest existing parent folder.\
- *$quality* (integer) Desired quality of output. Only relevant when source is a JPEG image. If source is a PNG, lossless encoding will be chosen.\
- *$stripMetadata* (bool) Whether to copy JPEG metadata to WebP (not all converters supports this)\

*WebPConvert::setPreferredConverters* (array)
Setting this manipulates the default order in which the converters are tried. If you for example set it to `cwebp`, it means that you want `cwebp` to be tried first. You can specify several favourite converters. Setting it to `imagick, cwebp` will put `imagick` to the top of the list and `cwebp` will be the next converter to try, if `imagick` fails. The option will not remove any converters from the list, only change the order.

## Converters
Each "method" of converting an image to WebP are implemented as a separate converter. *WebPConvert* autodetects the converters by scanning the "converters" directory, so it is easy to add new converters, and safe to remove existing ones.

A converter simply consists of a convert function, which takes same arguments as *WebPConvert::convert*. The job of the converter is to convert `$source` to WebP and save it at `$destination`, preferrably taking `$quality` and `$stripMetadata` into account. It however relies on *WebPConvert* to take care of the following common tasks:
- *WebPConvert* checks that source file exists
- *WebPConvert* prepares a directory for the destination if it doesn't exist already
- *WebPConvert* checks that it will be possible to write a file at the destination
- *WebPConvert* checks whether the converter actually produced a file at the destination

#### imagick
*Best, but rarely available on shared hosts*

```Requirements..```: imagick extension compiled with WebP support<br>
```Speed.........```: Between 20 ms - 320 ms to convert a 40kb image depending on *WEBPCONVERT_IMAGICK_METHOD* setting<br>
```Reliability...```: I'm not aware of any problems<br>
```Availability..```: Probably only available on few shared hosts (if any)<br>

WebP conversion with `imagick` is fast and [exposes many WebP options](http://www.imagemagick.org/script/webp.php). Unfortunately, WebP support for the `imagick` extension is not at all out of the box. At least not on the systems I have tried (Ubuntu 16.04 and Ubuntu 17.04). But if installed, it works great and has several WebP options.

The converter supports:
- lossless encoding of PNGs.
- quality
- prioritize between quality and speed
- low memory option

You can configure the converter by defining any of the following constants:

*WEBPCONVERT_IMAGICK_METHOD*: This parameter controls the trade off between encoding speed and the compressed file size and quality. Possible values range from 0 to 6. When higher values are used, the encoder will spend more time inspecting additional encoding possibilities and decide on the quality gain. Lower value can result in faster processing time at the expense of larger file size and lower compression quality. Default value is 6 (higher than the default value of the cwebp command, which is 4).\
*WEBPCONVERT_IMAGICK_LOW_MEMORY*: The low memory option will make the encoding slower and the output slightly different in size and distortion. This flag is only effective for methods 3 and up. It is *on* by default. To turn it off, set the constant to `false`\

In order to get imagick with WebP on Ubuntu 16.04, you currently need to:
1. [Compile libwebp from source](https://developers.google.com/speed/webp/docs/compiling)
2. [Compile imagemagick from source](https://www.imagemagick.org/script/install-source.php) (```./configure --with-webp=yes```)
3. Compile php-imagick from source, phpize it and add ```extension=/path/to/imagick.so``` to php.ini

#### gd
*Fast. But not good for PNGs*

```Requirements..```: GD extension and PHP > 5.5.0 compiled with WebP support<br>
```Speed.........```: Around 30 ms to convert a 40kb image<br>
```Reliability...```: Not sure. I have experienced corrupted images, but cannot reproduce<br>
```Availability..```: Unfortunately, according to [this link](https://stackoverflow.com/questions/25248382/how-to-create-a-webp-image-in-php), WebP support on shared hosts is rare.<br>

The converter does not support copying metadata.

*GD* unfortunately does not expose any WebP options. Lacking the option to set lossless encoding results in poor encoding of PNGs - the filesize is generally much larger than the original. For this reason, PNG conversion is *disabled* per default. The converter however lets you override this default by defining the *WEBPCONVERT_GD_PNG* constant.

Converter options:

*WEBPCONVERT_GD_PNG*: If set to `true`, the converter will convert PNGs even though the result will be bad.

[imagewebp](http://php.net/manual/en/function.imagewebp.php) is a function that comes with PHP (>5.5.0) *provided* that PHP has been compiled with WebP support. Due to a [bug](https://bugs.php.net/bug.php?id=66590), some versions sometimes created corrupted images. That bug can however easily be fixed in PHP (fix was released [here](https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files)). However, I have experienced corrupted images *anyway* (but cannot reproduce that bug). So use this converter with caution. The corrupted images shows as completely transparent images in Google Chrome, but with correct size.

To get WebP support for *gd* in PHP 5.5, PHP must be configured with the "--with-vpx-dir" flag. In PHP 7.0, php has to be configured with the "--with-webp-dir" flag [source](http://il1.php.net/manual/en/image.installation.php).

#### cwebp
*Great, fast enough but requires exec()*

```Requirements..```: exec()<br>
```Speed.........```: Between 40 ms - 120 ms to convert a 40kb image depending on *WEBPCONVERT_CWEBP_METHOD* setting<br>
```Reliability...```: Great<br>
```Availability..```: exec() is available on surprisingly many webhosts, and the PHP solution by *EWWW Image Optimizer*, which this code is largely based on has been reported to work on many webhosts - [here is a list](https://wordpress.org/plugins/ewww-image-optimizer/#installation)<br>

[cwebp](https://developers.google.com/speed/webp/docs/cwebp) is a WebP conversion command line converter released by Google. A its core, our implementation looks in the /bin folder for a precompiled binary appropriate for the OS and executes it with [exec()](http://php.net/manual/en/function.exec.php). Thanks to Shane Bishop for letting me copy his precompilations which comes with his plugin, [EWWW Image Optimizer](https://ewww.io/).

The converter supports:
- lossless encoding of PNGs.
- quality
- strip metadata
- prioritize between quality and speed
- low memory option

You can configure the converter by defining any of the following constants:

*WEBPCONVERT_CWEBP_METHOD*: This parameter controls the trade off between encoding speed and the compressed file size and quality. Possible values range from 0 to 6. When higher values are used, the encoder will spend more time inspecting additional encoding possibilities and decide on the quality gain. Lower value can result in faster processing time at the expense of larger file size and lower compression quality. Default value is 6 (higher than the default value of the cwebp command, which is 4).\
*WEBPCONVERT_CWEBP_LOW_MEMORY*: The low memory option will make the encoding slower and the output slightly different in size and distortion. This flag is only effective for methods 3 and up. It is *on* by default. To turn it off, set the constant to `false`

The cwebp command has more options, which can easily be implemented, if there is an interest. View the options [here](https://developers.google.com/speed/webp/docs/cwebp)

Official precompilations are available on [here](https://developers.google.com/speed/webp/docs/precompiled). But note that our script tests the checksum of the binary before executing it. This means that you cannot just replace a binary - you will have to change the checksum hardcoded in *converters/cwebp.php* too. If you find the need to use another binary, than those that comes with this project, please write - chances are that it should be added to the project.

In more detail, the implementation does this:
- Binary is selected form the bin-folder, according to OS
- If no binary is found, or if execution fails, try common system paths ('/usr/bin/cwebp' etc)
- Before executing binary, the checksum is tested
- Options are generated. -lossless is used for PNG. `-metadata` is set to "all" or "none"
- If "nice" command is found on host, then binary is run with low priority in order to save system ressources
- The permissions of the generated file is set to be the same as parent
- It is detected whether the command succeeds or not

Credits also goes to Shane regarding the code that revolves around the exec(). Most of it is a refactoring of the code in [EWWW Image Optimizer](https://ewww.io/).

#### ewww
*Cheap cloud service. Should work on *almost* any webhost. But slow. SEEMS TO BE OUT OF ORDER*

```Requirements..```: A valid key to [EWWW Image Optimizer](https://ewww.io/), curl and PHP >= 5.5<br>
```Speed.........```: Around 1300 ms to convert a 40kb image<br>
```Reliability...```: Great (but, as with any cloud service, there is a risk of downtime)<br>
```Availability..```: Should work on *almost* any webhost<br>

EWWW Image Optimizer is a very cheap cloud service for optimizing images.

You set up the key by defining the constant "WEBPCONVERT_EWW_KEY". Ie: ```define("WEBPCONVERT_EWW_KEY", "your_key_here")```;

The converter supports:
- lossless encoding of PNGs.
- quality
- metadata

The cloud service supports other options, which can easily be implemented, if there is an interest. View options [here](https://ewww.io/api)

The converter could be improved by using *fsockopen* if *curl* is not available. This is however low priority as the curl extension is available on most shared hosts. PHP >= 5.5 is also widely available (PHP 5.4 reached end of life [more than a year ago!](http://php.net/supported-versions.php)).
