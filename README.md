# WebPConvert

[![Build Status](https://travis-ci.org/rosell-dk/webp-convert.png?branch=master)](https://travis-ci.org/rosell-dk/webp-convert)

*Convert JPEG & PNG to WebP with PHP*

In summary, the current state of WebP conversion in PHP is this: There are several ways to do it, but they all require *something* of the server setup. What works on one shared host might not work on another. *WebPConvert* combines these methods by iterating over them (optionally in the desired order) until one of them is successful - or all of them fail.

**Table of contents**
- [1. Introduction](#introduction)
- [2. Getting started](#getting-started)
- [3. Methods](#methods)
- [4. Converters](#converters)
  - [Overview](#the-converters-at-a-glance)
  - [*cwebp*](#cwebp)
  - [*wpc*](#wpc)
  - [*ewww*](#ewww)
  - [*gd*](#gd)
  - [*imagick*](#imagick)
 - [5. WebPConvert in the wild](#webpconvert-in-the-wild)


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
$success = WebPConvert::convert($source, $destination, [
    'quality' => 80,  // Note: As of v1.1beta, the *quality* option can be set to "auto"

    // more options available!
]);
```


## Methods
The following methods are available:

**WebPConvert::convert($source, $destination, $options, $logger)**

| Parameter        | Type    | Description                                                                                |
| ---------------- | ------- | ------------------------------------------------------------------------------------------ |
| `$source`        | String  | Absolute path to source image (only forward slashes allowed)                               |
| `$destination`   | String  | Absolute path to converted image (only forward slashes allowed)                            |
| `$options` (optional)      | Array   | Array of conversion (option) options                                                                |
| `$logger` (optional)        | Baselogger   | Information about the conversion process will be passed to this object. Read more below                               |

### Available options

Many options correspond to options of cwebp. These are documented [here](https://developers.google.com/speed/webp/docs/cwebp)



| Option            | Type    | Default                    | Description                                                          |
| ----------------- | ------- | -------------------------- | -------------------------------------------------------------------- |
| quality           | An integer between 0-100. As of v1.1beta, it can also be "auto" | In v1.0, default is 85<br><br>As of v1.1beta, default is "auto"                          | Lossy quality of converted image (JPEG only - PNGs are always losless).<br><br> If set to "auto", *WebPConvert* will try to determine the quality of the JPEG (this is only possible, if Imagick or GraphicsMagic is installed). If successfully determined, the quality of the webp will be set to the same as that of the JPEG. however not to more than specified in the new `max-quality` option. If quality cannot be determined, quality will be set to what is specified in the new `default-quality` option |
| max-quality           | An integer between 0-100 | 85 | See the `quality` option. Only relevant, when quality is set to "auto".
| default-quality           | An integer between 0-100 | 80 | See the `quality` option. Only relevant, when quality is set to "auto".
| metadata          | String  | 'none'                      | Valid values: all, none, exif, icc, xmp. Note: Not supported by all converters             |
| method            | Integer | 6                           | Specify the compression method to use (0-6). When higher values are used, the encoder will spend more time inspecting additional encoding possibilities and decide on the quality gain. Lower value can result in faster processing time at the expense of larger file size and lower compression quality. |
| low-memory        | Boolean | false                       | Reduce memory usage of lossy encoding by saving four times the compressed size (typically) |
| lossless          | Boolean | false                       | Encode the image without any loss. The option is ignored for PNG's (forced true) |
| converters        | Array   | ['cwebp', 'gd', 'imagick']  | Specify converters to use, and their order. Also optionally set converter options (see below) |
| extra-converters  | Array   | []                          | Add extra converters    |


#### More on the `converters` option
When setting the `converters` option, you can also set options for the converter. This can be used for overriding the general options. For example, you may generally want the `quality` to be 85, but for a single converter, you would like it to be 100. It can also be used to set options that are special for the converter. For example, the ewww converter has a `key` option and `cwebp` has the special `use-nice` options. Gd converter has the option `skip-pngs`.

Example:
```
WebPConvert::convert($source, $destination, array(
    'converters' => array(
        'cwebp',    
        'imagick',
        array(
            'converter' => 'ewww',
            'options' => array(            
                'key' => 'your api key here',
            ),
        ),
    );
)
```

#### More on the `extra-converters` option
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
));
```
This used to be the preferred way of adding cloud converters, because it allows putting converters to the list without removing the default ones. That way, if new converters should arrive, they would be included in the list. However, if you use *wpc*, you probably want that to prioritized over *gd* and *imagick*. In that case, you will have to go for the `converters` option, rather than the `extra-converters` option.

### More on the `$logger` parameter
WebPConvert and the individual converters can provide information regarding the conversion process. Per default (when the parameter isn't provided), they write this to `\WebPConvert\Loggers\VoidLogger`, which does nothing with it.
In order to get this information echoed out, you can use `\WebPConvert\Loggers\EchoLogger` - like this:

```php
use WebPConvert\Loggers\EchoLogger;

WebPConvert::convert($source, $destination, $options, new EchoLogger());
```

In order to do something else with the information (perhaps write it to a log file?), you can extend `\WebPConvert\Loggers\BaseLogger`.

## Converters
In the most basic design, a converter consists of a static convert function which takes the same arguments as `WebPConvert::convert`. Its job is then to convert `$source` to WebP and save it at `$destination`, preferably taking the options specified in $options into account.

The converters may be called directly. But you probably don't want to do that, as it really doesn't hurt having other converters ready to take over, in case your preferred converter should fail.


## The converters at a glance

[`cwebp`](#cwebp) works by executing the *cwebp* binary from Google. This should be your first choice. Its best in terms of quality, speed and options. The only catch is that it requires the `exec` function to be enabled, and that the webserver user is allowed to execute the `cwebp` binary (either at known system locations, or one of the precompiled binaries, that comes with this library). If you are on a shared host that doesn't allow that, you can turn to the `wpc` cloud converter.

 [`wpc`](#wpc) is an open source cloud converter based on *WebPConvert*. Conversions will of course be slower than *cwebp*, as images need to go back and forth to the cloud converter. As images usually just needs to be converted once, the slower conversion speed is probably acceptable. The conversion quality and options of *wpc* matches *cwebp*. The only catch is that you will need to install the *WPC* library on a server (or have someone do it for you). If this this is a problem, we suggest you turn to *ewww*. (PS: A Wordpress plugin is planned, making it easier to set up a WPC instance)

[`ewww`](#ewww) is also a cloud service. It is a decent alternative for those who don't have the technical know-how to install *wpc*. *ewww* is using cwebp to do the conversion, so quality is great. *ewww* however only provides one conversion option (quality), and it is not free. But very cheap. Like in *almost* free.

[`gd`](#gd) uses the *Gd* extension to do the conversion. It is placed below the cloud converters for two reasons. Firstly, it does not seem to produce quite as good quality as *cwebp*. Secondly, it provides no conversion options, besides quality. The *Gd* extension is pretty common, so the main feature of this converter is that it may work out of the box. This is in contrast to the cloud converters, which requires that the user does some setup.

[`imagick`](#imagick) would be your last choice. For some reason it produces conversions that are only marginally better than the originals. See [this issue](https://github.com/rosell-dk/webp-convert/issues/43). But it is fast, and it supports many *cwebp* conversion options.

**Summary:**

*WebPConvert* currently supports the following converters:

| Converter                            | Method                                         | Quality                                       | Requirements                                       |
| ------------------------------------ | ---------------------------------------------- | --------------------------------------------- |
| [`cwebp`](#cwebp)             | Calls `cwebp` binary directly                | best | `exec()` function *and* that the webserver user has permission to run `cwebp` binary      |
| [`wpc`](#wpc) | Connects to WPC cloud service                      | best | A working *WPC* installation                |
| [`ewww`](#ewww)        | Connects to *EWWW Image Optimizer* cloud service           | great | Purchasing a key     |
| [`gd`](#gd)            | GD Graphics (Draw) extension (`LibGD` wrapper) | good | GD PHP extension compiled with WebP support  |
| [`imagick`](#imagick)            | Imagick extension (`ImageMagick` wrapper)      | so-so | Imagick PHP extension compiled with WebP support |


### cwebp

<table>
  <tr><th>Requirements</th><td><code>exec()</code> function and that the webserver has permission to run `cwebp` binary (either found in system path, or a precompiled version supplied with this library)</td></tr>
  <tr><th>Performance</th><td>~40-120ms to convert a 40kb image (depending on *method* option)</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far!</td></tr>
  <tr><th>Availability</th><td>According to ewww docs, requirements are met on surprisingly many webhosts. Look <a href="https://docs.ewww.io/article/43-supported-web-hosts">here</a> for a list</td></tr>
  <tr><th>General options supported</th><td>All (`quality`, `metadata`, `method`, `low-memory`, `lossless`)</td></tr>
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

### wpc
*WebPConvert Cloud Service*

**Will be available in 1.1.0. Its available in master**
<table>
  <tr><th>Requirements</th><td>Access to a server with [webp-convert-cloud-service](https://github.com/rosell-dk/webp-convert-cloud-service) installed, <code>cURL</code> and PHP >= 5.5.0</td></tr>
  <tr><th>Performance</th><td>Depends on the server where [webp-convert-cloud-service](https://github.com/rosell-dk/webp-convert-cloud-service) is set up, and the speed of internet connections. But perhaps ~1000ms to convert a 40kb image</td></tr>
  <tr><th>Reliability</th><td>Great (depends on the reliability on the server where it is set up)</td></tr>
  <tr><th>Availability</th><td>Should work on <em>almost</em> any webhost</td></tr>
  <tr><th>General options supported</th><td>All (`quality`, `metadata`, `method`, `low-memory`, `lossless`)</td></tr>
  <tr><th>Extra options</th><td>`url`, `secret`</td></tr>
</table>

[wpc](https://github.com/rosell-dk/webp-convert-cloud-service) is an open source cloud service. You do not buy a key, you set it up on a server. As WebPConvert Cloud Service itself is based on WebPConvert, all options are supported.

To use it, simply add it as extra converter with `url` option set to the correct endpoint, and `secret` set to match the secret set up on the server side.

Example:

```php
WebPConvert::convert($source, $destination, array(
    'extra-converters' => array(
        array(
            'converter' => 'wpc',
            'options' => array(
                'url' => 'http://example.com/wpc.php',
                'secret' => 'my dog is white',
            ),
        ),
    )
));
```


### ewww

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

### gd

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

### imagick

<table>
  <tr><th>Requirements</th><td>Imagick PHP extension (compiled with WebP support)</td></tr>
  <tr><th>Quality</th><td>Poor. [See this issue]( https://github.com/rosell-dk/webp-convert/issues/43)</td></tr>
  <tr><th>General options supported</th><td>`quality`, `method`, `low-memory`, `lossless`</td></tr>
  <tr><th>Extra options</th><td>None</td></tr>
  <tr><th>Performance</th><td>~20-320ms to convert a 40kb image (depending on `method` option)</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far</td></tr>
  <tr><th>Availability</th><td>Probably only available on few shared hosts (if any)</td></tr>
</table>

WebP conversion with `imagick` is fast and [exposes many WebP options](http://www.imagemagick.org/script/webp.php). Unfortunately, WebP support for the `imagick` extension is pretty uncommon. At least not on the systems I have tried (Ubuntu 16.04 and Ubuntu 17.04). But if installed, it works great and has several WebP options.

See [this page](https://github.com/rosell-dk/webp-convert/wiki/Installing-Imagick-extension) in the Wiki for instructions on installing the extension.






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
