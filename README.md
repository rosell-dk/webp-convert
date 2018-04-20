# WebPConvert

[![Build Status](https://travis-ci.org/rosell-dk/webp-convert.png?branch=master)](https://travis-ci.org/rosell-dk/webp-convert)

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
- Executing the `cwebp` binary directly via `exec()`
- Using a PHP extension (eg `gd` or `imagick`)
- Connecting to a cloud service which does the conversion (eg `EWWW`)

Converters **based on PHP extensions** should be your first choice. They are faster than other methods and they don't rely on server-side `exec()` calls (which may cause security risks). However, the `gd` converter doesn't support lossless conversion, so you may want to skip it when converting PNG images. Converters that **execute a binary** are also very fast (~ 50ms). Converters delegating the conversion process to a **cloud service** are much slower (~ one second), but work on *almost* any shared hosts (as opposed to the other methods). This makes the cloud-based converters an ideal last resort. They generally require you to *purchase* a paid plan, but the API key for [EWWW Image Optimizer](https://ewww.io) is very cheap. Beware though that you may encounter down-time whenever the cloud service is unavailable.

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

$source = __DIR__ . '/logo.jpg';
$destination = __DIR__ . '/logo.jpg.webp';

// .. fire up WebP conversion
$success = WebPConvert::convert($source, $destination, array(
    'quality' => 90,
    // more options available!
));
```

## Methods
The following methods are available:

**WebPConvert::convert($source, $destination)**

| Parameter        | Type    | Description                                                                                |
| ---------------- | ------- | ------------------------------------------------------------------------------------------ |
| `$source`        | String  | Absolute path to source image (only forward slashes allowed)                               |
| `$destination`   | String  | Absolute path to converted image (only forward slashes allowed)                            |
| `$options`       | Array   | Array of conversion options                                                                |

Available options:

Most options correspond to options of cwebp. These are documented [here](https://developers.google.com/speed/webp/docs/cwebp)

| Option            | Type    | Default                    | Description                                                          |
| ----------------- | ------- | -------------------------- | -------------------------------------------------------------------- |
| quality           | Integer | 85                          | Lossy quality of converted image (JPEG only - PNGs are created loslessly by default)  |
| metadata          | String  | 'none'                      | Valid values: all, none, exif, icc, xmp. Note: Not supported by all converters             |
| method            | Integer | 6                           | Specify the compression method to use (0-6). When higher values are used, the encoder will spend more time inspecting additional encoding possibilities and decide on the quality gain. Lower value can result in faster processing time at the expense of larger file size and lower compression quality. |
| low-memory        | Boolean | false                       | Reduce memory usage of lossy encoding by saving four times the compressed size (typically) |
| lossless          | Boolean | false                       | Encode the image without any loss. The option is ignored for PNG's (forced true) |
| converters        | Array   | ['cwebp', 'imagick', 'gd']  | Specify converters to use, and their order. Also optionally set converter options (see below) |
| extra-converters  | Array   | []                          | Add extra converters    |


When setting the `converters` option, you can also set options for the converter. This can be used for overriding the general options. For example, you may generally want the `quality` to be 85, but for a single converter, you would like it to be 100. It can also be used to set options that are special for the converter. For example, the ewww converter has a `key` option and `cwebp` has the special `use-nice` options. Gd converter has the option `skip-pngs`.

Example:
```
WebPConvert::convert($source, $destination, array(
    'converters' => array(
        'cwebp',    
        'imagick',
        array(
            'converter' => 'gd',
            'options' => array(            
                'skip-pngs' => false,
            ),
        ),
    );
)
```

You use the `extra-converters` to append converters to the list defined by the `converters` option. This is the preferred way of adding cloud converters. You are allowed to specify the same converter multiple times (you can btw also do that with the `converters` option). This can be useful if you for example have multiple accounts for a cloud service and are afraid that one of them might expire.

Example:

```
WebPConvert::convert($source, $destination, array(
    'extra-converters' => array(
        array(
            'converter' => 'ewww',
            'options' => array(
                'key' => 'your api key here',
            ),
        ),
        array(
            'converter' => 'ewww',
            'options' => array(
                'key' => 'your other api key here, in case the first one has expired',
            ),
        ),
    )
)
```

## Converters
In the most basic design, a converter consists of a static convert function which takes the same arguments as `WebPConvert::convert`. Its job is then to convert `$source` to WebP and save it at `$destination`, preferably taking the options specified in $options into account.

The converters may be called directly. But you probably don't want to do that, as it really doesn't hurt having other converters ready to take over, in case your preferred converter should fail.

### ImageMagick

<table>
  <tr><th>Requirements</th><td>Imagick PHP extension (compiled with WebP support)</td></tr>
  <tr><th>Performance</th><td>~20-320ms to convert a 40kb image (depending on `method` option)</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far!</td></tr>
  <tr><th>Availability</th><td>Probably only available on few shared hosts (if any)</td></tr>
  <tr><th>General options supported</th><td>`quality`, `method`, `low-memory`, `lossless`</td></tr>
  <tr><th>Extra options</th><td>None</td></tr>
</table>

WebP conversion with `imagick` is fast and [exposes many WebP options](http://www.imagemagick.org/script/webp.php). Unfortunately, WebP support for the `imagick` extension is pretty uncommon. At least not on the systems I have tried (Ubuntu 16.04 and Ubuntu 17.04). But if installed, it works great and has several WebP options.

See [this page](https://github.com/rosell-dk/webp-convert/wiki/Installing-Imagick-extension) in the Wiki for instructions on installing the extension.

### GD Graphics (Draw)

<table>
  <tr><th>Requirements</th><td>GD PHP extension and PHP >= 5.5.0 (compiled with WebP support)</td></tr>
  <tr><th>Performance</th><td>~30ms to convert a 40kb image</td></tr>
  <tr><th>Reliability</th><td>Not sure - I have experienced corrupted images, but cannot reproduce</td></tr>
  <tr><th>Availability</th><td>Unfortunately, according to <a href="https://stackoverflow.com/questions/25248382/how-to-create-a-webp-image-in-php">this link</a>, WebP support on shared hosts is rare.</td></tr>
  <tr><th>General options supported</th><td>`quality`</td></tr>
  <tr><th>Extra options</th><td>`skip-pngs`</td></tr>
</table>

[imagewebp](http://php.net/manual/en/function.imagewebp.php) is a function that comes with PHP (>5.5.0), *provided* that PHP has been compiled with WebP support.

`gd` neither supports copying metadata nor exposes any WebP options. Lacking the option to set lossless encoding results in poor encoding of PNGs - the filesize is generally much larger than the original. For this reason, PNG conversion is *disabled* by default, but it can be enabled my setting `skip-pngs` option to `false`.

Installaition instructions are [available in the wiki](https://github.com/rosell-dk/webp-convert/wiki/Installing-Gd-extension).

<details>
<summary><strong>Known bugs</strong> 👁</summary>
Due to a [bug](https://bugs.php.net/bug.php?id=66590), some versions sometimes created corrupted images. That bug can however easily be fixed in PHP (fix was released [here](https://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files)). However, I have experienced corrupted images *anyway* (but cannot reproduce that bug). So use this converter with caution. The corrupted images look completely transparent in Google Chrome, but have the correct size.
</details>

### cwebp binary

<table>
  <tr><th>Requirements</th><td><code>exec()</code> function</td></tr>
  <tr><th>Performance</th><td>~40-120ms to convert a 40kb image (depending on <code>WEBPCONVERT_CWEBP_METHOD</code>)</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far!</td></tr>
  <tr><th>Availability</th><td><code>exec()</code> is available on surprisingly many webhosts (a selection of which can be found <a href="https://docs.ewww.io/article/43-supported-web-hosts">here</a></td></tr>
  <tr><th>General options supported</th><td>`quality`, `method`, `low-memory`, `lossless`</td></tr>
  <tr><th>Extra options</th><td>`use-nice`</td></tr>
</table>

[cwebp](https://developers.google.com/speed/webp/docs/cwebp) is a WebP conversion command line converter released by Google. Our implementation ships with precompiled binaries for Linux, FreeBSD, WinNT, Darwin and SunOS. If however a cwebp binary is found in a usual location, that binary will be preferred. It is executed with [exec()](http://php.net/manual/en/function.exec.php).

In more detail, the implementation does this:
- It is tested whether cwebp is available in a common system path (eg `/usr/bin/cwebp`, ..)
- If not, then supplied binary is selected from `Converters/Binaries` (according to OS) - after validating checksum
- Command-line options are generated from the options
- If [`nice`]( https://en.wikipedia.org/wiki/Nice_(Unix)) command is found on host, binary is executed with low priority in order to save system resources
- Permissions of the generated file are set to be the same as parent folder

The `cwebp` binary has more options than we cared to implement. They can however easily be implemented, if there is an interest. View the options [here](https://developers.google.com/speed/webp/docs/cwebp).

The implementation is based on the work of Shane Bishop for his plugin, [EWWW Image Optimizer](https://ewww.io). Thanks for letting us do that!

See [the wiki](https://github.com/rosell-dk/webp-convert/wiki/Installing-cwebp---using-official-precompilations) for instructions regarding installing cwebp or using official precompilations.

### EWWW cloud service

<table>
  <tr><th>Requirements</th><td>Valid EWWW Image Optimizer <a href="https://ewww.io">API key</a>, <code>cURL</code> and PHP >= 5.5.0</td></tr>
  <tr><th>Performance</th><td>~1300ms to convert a 40kb image</td></tr>
  <tr><th>Reliability</th><td>Great (but, as with any cloud service, there is a risk of downtime)</td></tr>
  <tr><th>Availability</th><td>Should work on <em>almost</em> any webhost</td></tr>
  <tr><th>General options supported</th><td>`quality`, `metadata` (partly)</td></tr>
  <tr><th>Extra options</th><td>`key`</td></tr>
</table>

EWWW Image Optimizer is a very cheap cloud service for optimizing images. After purchasing an API key, add the converter in the `extra-converters` option, with `key` set to the key. Be aware that the `key` should be stored safely to avoid exploitation - preferably in the environment, ie with  [dotenv](https://github.com/vlucas/phpdotenv).

The EWWW api doesn't support the `lossless` option, but it does automatically convert PNG's losslessly. Metadata is either all or none. If you have set it to something else than one of these, all metadata will be preserved.

In more detail, the implementation does this:
- Validates that there is a key, and that `curl` extension is working
- Validates the key, using the [/verify/ endpoint](https://ewww.io/api/) (in order to [protect the EWWW service from unnecessary file uploads, when key has expired](https://github.com/rosell-dk/webp-convert/issues/38))
- Converts, using the [/ endpoint](https://ewww.io/api/).

<details>
<summary><strong>Roadmap</strong> 👁</summary>

The converter could be improved by using `fsockopen` when `cURL` is not available - which is extremely rare. PHP >= 5.5.0 is also widely available (PHP 5.4.0 reached end of life [more than two years ago!](http://php.net/supported-versions.php)).
</details>

## WebPConvert in the wild
WebPConvert is used in the following projects:

#### [webp-on-demand](https://github.com/rosell-dk/webp-on-demand)
Set up Apache server to serve autogenerated webp-files for Chromium.

#### [webp-convert-cloud-service](https://github.com/rosell-dk/webp-convert-cloud-service)
A cloud service based on WebPConvert (work in progress)

#### [webp-convert-and-serve](https://github.com/rosell-dk/webp-convert-and-serve)
Extends WebPConvert functionality for serving the generated files

#### [webp-express](https://github.com/rosell-dk/webp-express)
Wordpress integration (needs updating - we are on it!)

#### [kirby-webp](https://github.com/S1SYPHOS/kirby-webp)
Kirby CMS integration

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
