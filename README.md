# WebPConvert
*Convert JPEG & PNG to WebP with PHP*

In summary, the current state of WebP conversion in PHP is this: There are several ways to do it, but they all require *something* of the server setup. What works on one shared host might not work on another. `WebPConvert` combines these methods by iterating over them (optionally in the desired order) until one of them is successful - or all of them fail.

**Table of contents**
- [1. Introduction](#introduction)
- [2. Getting started](#getting-started)
- [3. Methods](#methods)
- [4. Converters](#converters)
  - [`imagick`](#imagemagick)
  - [`gd`](#gd-graphics-draw)
  - [`cwebp`](#cwebp-binary)
  - [`ewww`](#ewww-cloud-service)

## Introduction
Basically, there are three ways for JPEG & PNG to WebP conversion:
- Using a PHP extension (eg `gd` or `imagick`)
- Executing a binary directly using an `exec()` call (eg `cwebp`)
- Connecting to a cloud service which does the conversion (eg `EWWW`)

Converters **based on PHP extensions** should be your first choice. They are faster than other methods and they don't rely on server-side `exec()` calls (which may cause security risks). However, the `gd` converter doesn't support lossless conversion, so you may want to skip it when converting PNG images. Converters that **execute a binary** are also very fast (~ 50ms). Converters delegating the conversion process to a **cloud service** are much slower (~ one second), but work on *almost* any shared hosts (as opposed to the other methods). This makes the cloud-based converters an ideal last resort. They generally require you to *purchase* a paid plan, but the API key for [EWWW Image Optimizer](https://ewww.io) is very cheap. Beware though that you may encounter down-time whenever the cloud service is unavailable.

----

`WebPConvert` currently supports the following converters:

| Converter                     | Method                                         | Summary                                       |
| ----------------------------- | ---------------------------------------------- | --------------------------------------------- |
| [`imagick`](#imagemagick)     | Imagick extension (`ImageMagick` wrapper)      | (+) best (-) rarely available on shared hosts |
| [`gd`](#gd-graphics-draw)     | GD Graphics (Draw) extension (`LibGD` wrapper) | (+) fast (-) unable to do lossless encoding   |
| [`cwebp`](#cwebp-binary)      | Calling `cwebp` binary directly                | (+) great (-) requires `exec()` function      |
| [`ewww`](#ewww-cloud-service) | Cloud service `EWWW Image Optimizer`           | (+) high availability (-) slow, fee-based     |

## Getting started

### Installation
Simply require this plugin from the command line via [Composer](https://getcomposer.org):

```text
composer require rosell-dk/webp-convert
```

### Basic usage example

```php
<?php

// Initialise your autoloader (this example is using Composer)
require 'vendor/autoload.php';

use WebPConvert\WebPConvert;

// Define basic conversion options
$source = $_SERVER['DOCUMENT_ROOT'] . '/images/logo.jpg';
$destination = $_SERVER['DOCUMENT_ROOT'] . '/images/logo.webp';
$quality = 90;
$stripMetadata = true;

// Change order of converters (optional) ..
WebPConvert::setPreferredConverters(array('imagick','cwebp'));

// .. fire up WebP conversion
WebPConvert::convert($source, $destination, $quality, $stripMetadata);
```

## Methods
The following methods are available:

**WebPConvert::convert($source, $destination, $quality, $stripMetadata)**

| Parameter        | Type    | Description                                                                                |
| ---------------- | ------- | ------------------------------------------------------------------------------------------ |
| `$source`        | String  | Absolute path to source image (only forward slashes allowed)                               |
| `$destination`   | String  | Absolute path to converted image (only forward slashes allowed)                            |
| `$quality`       | Integer | Lossy quality of converted image (JPEG only - PNGs are created loslessly by default)       |
| `$stripMetadata` | Boolean | Whether or not to copy JPEG metadata to converted image (not all converters supports this) |

----

**WebPConvert::setPreferredConverters($converters)**

| Parameter        | Type    | Description                                                                           |
| ---------------- | ------- | ------------------------------------------------------------------------------------- |
| `$converters`    | Array   | Desired order in which the converters are tried (eg `cwebp`, `gd`, `imagick`, `ewww`) |

**Example:** Changing it to `imagick, cwebp` would lead to `imagick` being tried first, and `cwebp` right after that. This option will not remove any converters from the list.

----

**WebPConvert\Converters\Ewww::isValidKey($key)**

| Parameter | Type   | Description                  |
| --------- | ------ | ---------------------------- |
| `$key`    | String | EWWW Image Optimizer API key |

If you quickly need to verify your API key, or want to build upon `WebPConvert`, this might be helpful. Passing it as an argument returns one of three possible states: 'great' (successful verification), 'exceeded' (valid API key, but not enough image credits) & '' (invalid API key).

## Converters
Each "method" of converting an image to WebP is implemented through a separate converter `.php` file, containing a class of the same name. `WebPConvert` autodetects converters by scanning the `Converters` directory, so it's easy to add new converters and safe to remove existing ones.

In the most basic design, a converter consists of a convert function which takes the same arguments as `WebPConvert::convert`. Its job is then to convert `$source` to WebP and save it at `$destination`, preferably taking `$quality` and `$stripMetadata` into account. It however relies on the `WebPConvert` class to take care of the following common tasks:
- Checking that `$source` file exists
- Creating `$destination`if it doesn't exist
- Ensuring that write permissions are granted
- Handling errors / exceptions if need be

----

### ImageMagick

<table>
  <tr><th>Requirements</th><td>Imagick PHP extension (compiled with WebP support)</td></tr>
  <tr><th>Performance</th><td>~20-320ms to convert a 40kb image (depending on <code>WEBPCONVERT_IMAGICK_METHOD</code>)</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far!</td></tr>
  <tr><th>Availability</th><td>Probably only available on few shared hosts (if any)</td></tr>
</table>

WebP conversion with `imagick` is fast and [exposes many WebP options](http://www.imagemagick.org/script/webp.php). Unfortunately, WebP support for the `imagick` extension is pretty uncommon. At least not on the systems I have tried (Ubuntu 16.04 and Ubuntu 17.04). But if installed, it works great and has several WebP options.

The converter supports:
- lossless encoding of PNGs
- setting quality
- prioritizing between quality and speed
- low memory option

You can configure `imagick` by defining any of the following [constants](http://php.net/manual/en/language.constants.php):

- `WEBPCONVERT_IMAGICK_METHOD`: This parameter controls the trade off between encoding speed and the compressed file size and quality. Possible values range from 0 to 6. When higher values are used, the encoder will spend more time inspecting additional encoding possibilities and decide on the quality gain. Lower value can result in faster processing time at the expense of larger file size and lower compression quality. Default value is 6 (higher than the default value of the `cwebp` command, which is 4).
- `WEBPCONVERT_IMAGICK_LOW_MEMORY`: The low memory option will make the encoding slower and the output slightly different in size and distortion. This flag is only effective for methods 3 and up. It is *on* by default. To turn it off, set the constant to `false`.

In order to get imagick with WebP on Ubuntu 16.04, you currently need to:
1. [Compile libwebp from source](https://developers.google.com/speed/webp/docs/compiling)
2. [Compile imagemagick from source](https://www.imagemagick.org/script/install-source.php) (```./configure --with-webp=yes```)
3. Compile php-imagick from source, phpize it and add ```extension=/path/to/imagick.so``` to php.ini

----

### GD Graphics (Draw)

<table>
  <tr><th>Requirements</th><td>GD PHP extension and PHP >= 5.5.0 (compiled with WebP support)</td></tr>
  <tr><th>Performance</th><td>~30ms to convert a 40kb image</td></tr>
  <tr><th>Reliability</th><td>Not sure - I have experienced corrupted images, but cannot reproduce</td></tr>
  <tr><th>Availability</th><td>Unfortunately, according to <a href="https://stackoverflow.com/questions/25248382/how-to-create-a-webp-image-in-php">this link</a>, WebP support on shared hosts is rare.</td></tr>
</table>

`gd` neither supports copying metadata nor exposes any WebP options. Lacking the option to set lossless encoding results in poor encoding of PNGs - the filesize is generally much larger than the original. For this reason, PNG conversion is *disabled* by default. The converter however lets you override this default by defining the `WEBPCONVERT_GD_PNG` constant:

- `WEBPCONVERT_GD_PNG`: If set to `true`, the converter will convert PNGs even though the result will be bad.

<details>
<summary><strong>Known bugs</strong> 👁</summary>

[imagewebp](http://php.net/manual/en/function.imagewebp.php) is a function that comes with PHP (>5.5.0), *provided* that PHP has been compiled with WebP support. Due to a [bug](https://bugs.php.net/bug.php?id=66590), some versions sometimes created corrupted images. That bug can however easily be fixed in PHP (fix was released [here](https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files)). However, I have experienced corrupted images *anyway* (but cannot reproduce that bug). So use this converter with caution. The corrupted images look completely transparent in Google Chrome, but have the correct size.

To get WebP support for `gd` in PHP 5.5.0, PHP must be configured with the `--with-vpx-dir` flag. In PHP >7.0.0, PHP has to be configured with the `--with-webp-dir` flag ([source](http://il1.php.net/manual/en/image.installation.php)).
</details>

----

### cwebp binary

<table>
  <tr><th>Requirements</th><td><code>exec()</code> function</td></tr>
  <tr><th>Performance</th><td>~40-120ms to convert a 40kb image (depending on <code>WEBPCONVERT_CWEBP_METHOD</code>)</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far!</td></tr>
  <tr><th>Availability</th><td><code>exec()</code> is available on surprisingly many webhosts (a selection of which can be found <a href="https://docs.ewww.io/article/43-supported-web-hosts">here</a></td></tr>
</table>

[cwebp](https://developers.google.com/speed/webp/docs/cwebp) is a WebP conversion command line converter released by Google. A its core, our implementation looks in the /bin folder for a precompiled binary appropriate for the OS and executes it with [exec()](http://php.net/manual/en/function.exec.php). Thanks to Shane Bishop for letting me copy the precompiled binaries that come with his plugin, [EWWW Image Optimizer](https://ewww.io).

The converter supports:
- lossless encoding of PNGs
- quality
- strip metadata
- prioritize between quality and speed
- low memory option

You can configure the converter by defining any of the following constants:

- `WEBPCONVERT_CWEBP_METHOD`: This parameter controls the trade off between encoding speed and the compressed file size and quality. Possible values range from 0 to 6. When higher values are used, the encoder will spend more time inspecting additional encoding possibilities and decide on the quality gain. Lower value can result in faster processing time at the expense of larger file size and lower compression quality. Default value is 6 (higher than the default value of the cwebp command, which is 4).
- `WEBPCONVERT_CWEBP_LOW_MEMORY`: The low memory option will make the encoding slower and the output slightly different in size and distortion. This flag is only effective for methods 3 and up. It is *on* by default. To turn it off, set the constant to `false`.

----

The `cwebp` command has more options, which can easily be implemented, if there is an interest. View the options [here](https://developers.google.com/speed/webp/docs/cwebp).

Official precompilations are available [here](https://developers.google.com/speed/webp/docs/precompiled). Since `WebPConvert` compares each binary's checksum first, you will have to change the checksums hardcoded in `Converters/Cwebp.php` if you want to replace any of them. If you feel the need of using another binary, please let me know - chances are that it should be added to the project!

In more detail, the implementation does this:
- Binary is selected from `Converters/Binaries` (according to OS)
- If there's no matching binary or execution fails, try common system paths (eg `/usr/bin/cwebp`, ..)
- Before executing binary, the checksum is tested
- Options are generated. `-lossless` is used for PNG. `-metadata` is set to `all` or `none`
- If `[nice](https://en.wikipedia.org/wiki/Nice_(Unix))` command is found on host, binary is executed with low priority in order to save system ressources
- Permissions of the generated file are set to be the same as parent

----

### EWWW cloud service

<table>
  <tr><th>Requirements</th><td>Valid EWWW Image Optimizer <a href="https://ewww.io">API key</a>, <code>cURL</code> and PHP >= 5.5.0</td></tr>
  <tr><th>Performance</th><td>~1300ms to convert a 40kb image</td></tr>
  <tr><th>Reliability</th><td>Great (but, as with any cloud service, there is a risk of downtime)</td></tr>
  <tr><th>Availability</th><td>Should work on <em>almost</em> any webhost</td></tr>
</table>

EWWW Image Optimizer is a very cheap cloud service for optimizing images. After purchasing an API key, simply set it up like this:

```text
define("WEBPCONVERT_EWWW_KEY", "YOUR-KEY-HERE");
```

The converter supports:
- lossless encoding of PNGs
- quality
- metadata

The cloud service supports other options, which can easily be implemented, if there is an interest. View options [here](https://ewww.io/api).

<details>
<summary><strong>Roadmap</strong> 👁</summary>

The converter could be improved by using `fsockopen` when `cURL` is not available - which is extremely rare. PHP >= 5.5.0 is also widely available (PHP 5.4.0 reached end of life [more than two years ago!](http://php.net/supported-versions.php)).
</details>

## Development

`WebPConvert` uses the [PHP-CS-FIXER](https://github.com/FriendsOfPHP/PHP-CS-Fixer) library (based on squizlabs' [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)) so all PHP files automagically comply with the [PSR-2](https://www.php-fig.org/psr/psr-2/) coding standard.

```text
// Dry run - without making changes to any files
composer cs-dry

// Production mode
composer cs-fix
```

Furthermore, testing is done with Sebastian Bergmann's excellent testing framework [PHPUnit](https://github.com/sebastianbergmann/phpunit), like this:

```text
composer test
```
