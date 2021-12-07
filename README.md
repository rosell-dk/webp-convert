# WebP Convert

[![Latest Stable Version](https://img.shields.io/packagist/v/rosell-dk/webp-convert.svg?style=flat-square)](https://packagist.org/packages/rosell-dk/webp-convert)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg?style=flat-square)](https://php.net)
[![Build Status](https://img.shields.io/github/workflow/status/rosell-dk/webp-convert/PHP?logo=GitHub&style=flat-square)](https://github.com/rosell-dk/webp-convert/actions/workflows/php.yml)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/rosell-dk/webp-convert.svg?style=flat-square)](https://scrutinizer-ci.com/g/rosell-dk/webp-convert/code-structure/master)
[![Quality Score](https://img.shields.io/scrutinizer/g/rosell-dk/webp-convert.svg?style=flat-square)](https://scrutinizer-ci.com/g/rosell-dk/webp-convert/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/rosell-dk/webp-convert/blob/master/LICENSE)

*Convert JPEG & PNG to WebP with PHP*

This library enables you to do webp conversion with PHP. It supports an abundance of methods for converting and automatically selects the most capable of these that is available on the system.

The library can convert using the following methods:
- *cwebp* (executing [cwebp](https://developers.google.com/speed/webp/docs/cwebp) binary using an `exec` call)
- *vips* (using [Vips PHP extension](https://github.com/libvips/php-vips-ext))
- *imagick* (using [Imagick PHP extension](https://github.com/Imagick/imagick))
- *gmagick* (using [Gmagick PHP extension](https://www.php.net/manual/en/book.gmagick.php))
- *imagemagick* (executing [imagemagick](https://imagemagick.org/index.php) binary using an `exec` call)
- *graphicsmagick* (executing [graphicsmagick](http://www.graphicsmagick.org/) binary using an `exec` call)
- *ffmpeg* (executing [ffmpeg](https://ffmpeg.org/) binary using an `exec` call)
- *wpc* (using [WebPConvert Cloud Service](https://github.com/rosell-dk/webp-convert-cloud-service/) - an open source webp converter for PHP - based on this library)
- *ewwww* (using the [ewww](https://ewww.io/plans/) cloud converter (1 USD startup and then free webp conversion))
- *gd* (using the [Gd PHP extension](https://www.php.net/manual/en/book.image.php))

In addition to converting, the library also has a method for *serving* converted images, and we have instructions here on how to set up a solution for automatically serving webp images to browsers that supports webp.

## Installation
Require the library with *Composer*, like this:

```text
composer require rosell-dk/webp-convert
```

## Converting images
Here is a minimal example of converting using the *WebPConvert::convert* method:

```php
// Initialise your autoloader (this example is using Composer)
require 'vendor/autoload.php';

use WebPConvert\WebPConvert;

$source = __DIR__ . '/logo.jpg';
$destination = $source . '.webp';
$options = [];
WebPConvert::convert($source, $destination, $options);
```

The *WebPConvert::convert* method comes with a bunch of options. The following introduction is a *must-read*:
[docs/v2.0/converting/introduction-for-converting.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md).

If you are migrating from 1.3.9, [read this](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/migrating-to-2.0.md)

## Serving converted images
The *WebPConvert::serveConverted* method tries to serve a converted image. If there already is an image at the destination, it will take that, unless the original is newer or smaller. If the method cannot serve a converted image, it will serve original image, a 404, or whatever the 'fail' option is set to. It also adds *X-WebP-Convert-Log* headers, which provides insight into what happened.

Example (version 2.0):
```php
require 'vendor/autoload.php';
use WebPConvert\WebPConvert;

$source = __DIR__ . '/logo.jpg';
$destination = $source . '.webp';

WebPConvert::serveConverted($source, $destination, [
    'fail' => 'original',     // If failure, serve the original image (source). Other options include 'throw', '404' and 'report'
    //'show-report' => true,  // Generates a report instead of serving an image

    'serve-image' => [
        'headers' => [
            'cache-control' => true,
            'vary-accept' => true,
            // other headers can be toggled...
        ],
        'cache-control-header' => 'max-age=2',
    ],

    'convert' => [
        // all convert option can be entered here (ie "quality")
    ],
]);
```

The following introduction is a *must-read* (for 2.0):
[docs/v2.0/serving/introduction-for-serving.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/serving/introduction-for-serving.md).

The old introduction (for 1.3.9) is available here: [docs/v1.3/serving/convert-and-serve.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/v1.3/serving/convert-and-serve.md)


## WebP on demand
The library can be used to create a *WebP On Demand* solution, which automatically serves WebP images instead of jpeg/pngs for browsers that supports WebP. To set this up, follow what's described  [in this tutorial (not updated for 2.0 yet)](https://github.com/rosell-dk/webp-convert/blob/master/docs/v1.3/webp-on-demand/webp-on-demand.md).


## Projects using WebP Convert

### CMS plugins using WebP Convert
This library is used as the engine to provide webp conversions to a handful of platforms. Hopefully this list will be growing over time. Currently there are plugins / extensions / modules / whatever the term is for the following CMS'es (ordered by [market share](https://w3techs.com/technologies/overview/content_management/all)):

- [Wordpress](https://github.com/rosell-dk/webp-express)
- [Drupal 7](https://github.com/HDDen/Webp-Drupal-7)
- [Contao](https://github.com/postyou/contao-webp-bundle)
- [Kirby](https://github.com/S1SYPHOS/kirby-webp)
- [October CMS](https://github.com/OFFLINE-GmbH/oc-responsive-images-plugin/)

### Other projects using WebP Convert

- [webp-convert-cloud-service](https://github.com/rosell-dk/webp-convert-cloud-service)
A cloud service based on WebPConvert

- [webp-convert-concat](https://github.com/rosell-dk/webp-convert-concat)
The webp-convert library and its dependents as a single PHP file (or two)

## Supporting WebP Convert
Bread on the table don't come for free, even though this library does, and always will. I enjoy developing this, and supporting you guys, but I kind of need the bread too. Please make it possible for me to have both:

- [Become a backer or sponsor on Patreon](https://www.patreon.com/rosell).
- [Buy me a Coffee](https://ko-fi.com/rosell)

## Supporters
*Persons currently backing the project via patreon - Thanks!*

- Max Kreminsky
- Nodeflame
- [Mathieu Gollain-Dupont](https://www.linkedin.com/in/mathieu-gollain-dupont-9938a4a/)
- Ruben Solvang

*Persons who recently contributed with [ko-fi](https://ko-fi.com/rosell) - Thanks!*
* 20 Nov: Ben J
* 13 Nov: @sween
* 9 Nov: @utrenkner
* 26 Oct: Anonymous
* 29 Aug: Pawa Tecnologia

*Persons who contributed with extra generously amounts of coffee / lifetime backing (>50$) - thanks!:*

- Justin - BigScoots ($105)
- Sebastian ($99)
- Tammy Lee ($90)
- Max Kreminsky ($65)
- Steven Sullivan ($51)

## New in 2.9.0 (released 7 dec 2021, on my daughters 10 years birthday!)
- When exec() is unavailable, alternatives are now tried (emulations with proc_open(), passthru() etc). Using [this library](https://github.com/rosell-dk/exec-with-fallback) to do it.
- Gd is now marked as not operational when the needed functions for converting palette images to RGB is missing. Rationale: A half-working converter causes more trouble than one that is marked as not operational
- Improved CI tests. It is now tested on Windows, Mac and with deactivated functions (such as when exec() is disabled)
- And more (view closed issues [here](https://github.com/rosell-dk/webp-convert/milestone/25?closed=1)

## New in 2.8.0:
- Converter option definitions are now accessible along with suggested UI and helptexts. This allows one to auto-generate a frontend based on conversion options. The feature is already in use in the [webp-convert file manager](https://github.com/rosell-dk/webp-convert-filemanager), which is used in WebP Express. New method: `WebPConvert::getConverterOptionDefinitions()`
- The part of the log that displays the options are made more readable. It also now warns about deprecated options.
- Bumped image-mime-type guesser library to 0.4. This version is able to dectect more mime types by sniffing the first couple of bytes.
- And more (view closed issues [here](https://github.com/rosell-dk/webp-convert/milestone/23?closed=1)

## New in 2.7.0:
- ImageMagick now supports the "near-lossless" option (provided Imagick >= 7.0.10-54) [#299](https://github.com/rosell-dk/webp-convert/issues/299)
- Added "try-common-system-paths" option for ImageMagick (default: true). So ImageMagick will now peek for "convert" in common system paths [#293](https://github.com/rosell-dk/webp-convert/issues/293)
- Fixed memory leak in Gd on very old versions of PHP [#264](https://github.com/rosell-dk/webp-convert/issues/264)
- And more (view closed issues [here](https://github.com/rosell-dk/webp-convert/milestone/24?closed=1)

## New in 2.6.0:
- Introduced [auto-limit](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#auto-limit) option which replaces setting "quality" to "auto" [#281](https://github.com/rosell-dk/webp-convert/issues/281)
- Added "sharp-yuv" option and made it default on. [Its great](https://www.ctrl.blog/entry/webp-sharp-yuv.html), use it! Works in most converters (works in cwebp, vips, imagemagick, graphicsmagick, imagick and gmagick) [#267](https://github.com/rosell-dk/webp-convert/issues/267), [#280](https://github.com/rosell-dk/webp-convert/issues/280), [#284](https://github.com/rosell-dk/webp-convert/issues/284)
- Bumped cwebp binaries to 1.2.0 [#273](https://github.com/rosell-dk/webp-convert/issues/273)
- vips now supports "method" option and "preset" option.
- graphicsmagick now supports "auto-filter" potion
- vips, imagick, imagemagick, graphicsmagick and gmagick now supports "preset" option [#275](https://github.com/rosell-dk/webp-convert/issues/275)
- cwebp now only validates hash of supplied precompiled binaries when necessary. This cuts down conversion time. [#287](https://github.com/rosell-dk/webp-convert/issues/287)
- Added [new option](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#cwebp-skip-these-precompiled-binaries) to cwebp for skipping precompiled binaries that are known not to work on current system. This will cut down on conversion time. [#288](https://github.com/rosell-dk/webp-convert/issues/288)
- And more (view closed issues [here](https://github.com/rosell-dk/webp-convert/milestone/22?closed=1))
