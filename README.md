# WebP Convert

[![Latest Stable Version](https://img.shields.io/packagist/v/rosell-dk/webp-convert.svg?style=flat-square)](https://packagist.org/packages/rosell-dk/webp-convert)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg?style=flat-square)](https://php.net)
[![Build Status](https://travis-ci.org/rosell-dk/webp-convert.png?branch=master)](https://travis-ci.org/rosell-dk/webp-convert)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/rosell-dk/webp-convert.svg?style=flat-square)](https://scrutinizer-ci.com/g/rosell-dk/webp-convert/code-structure/master)
[![Quality Score](https://img.shields.io/scrutinizer/g/rosell-dk/webp-convert.svg?style=flat-square)](https://scrutinizer-ci.com/g/rosell-dk/webp-convert/)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/rosell-dk/webp-convert/blob/master/LICENSE)

*Convert JPEG & PNG to WebP with PHP*

This library enables you to do webp conversion with PHP using *cwebp*, *vips*, *gd*, *imagick*, *ewww* cloud converter or the open source *wpc* cloud converter. It also allows you to try a whole stack &ndash; useful if you do not have control over the environment, and simply want the library to do *everything it can* to convert the image to webp.

In addition to converting, the library also has a method for *serving* converted images, and we have instructions here on how to set up a solution for automatically serving webp images to browsers that supports webp.

**NOTE: This master branch contains code for the upcoming 2.0 release. It is not stable yet.**

## Installation
Require the library with *Composer*, like this:

```text
composer require rosell-dk/webp-convert
```

## Converting images
To convert an image using a stack of converters, you can use the *WebPConvert::convert* method.

Here is a minimal example:

```php
<?php

// Initialise your autoloader (this example is using Composer)
require 'vendor/autoload.php';

use WebPConvert\WebPConvert;

$source = __DIR__ . '/logo.jpg';
$destination = $source . '.webp';
$options = [];
WebPConvert::convert($source, $destination, $options);
```

The method comes with a bunch of options. The following introduction is a must-read:
[docs/convert-introduction.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/convert-introduction.md).


## Serving converted images
The *convertAndServe* method tries to serve a converted image. If there already is an image at the destination, it will take that, unless the original is newer or smaller. If the method cannot serve a converted image, it will serve original image, a 404, or whatever the 'fail' option is set to - and return false. It also adds a *X-WebP-Convert-Status* header, which allows you to inspect what happened.

Example:
```php
<?php
require 'vendor/autoload.php';
use WebPConvert\WebPConvert;

$source = __DIR__ . '/logo.jpg';
$destination = $source . '.webp';

WebPConvert::serveConverted($source, $destination, [
    'fail' => 'original',     // If failure, serve the original image (source).
    //'fail' => '404',        // If failure, respond with 404.
    //'show-report' => true,  // Generates a report instead of serving an image

    // Besides the specific options for convertAndServe(), you can also use the options for convert()
]);

```
convertAndServe

*NOTE:* In 2.0, the method is renamed to *serveConverted* ("convertAndServe" was implying that a conversion was always made, but the method simply serves destination if it exists and is smaller and newer than source)

To see all options, look at the API: [docs/api/convert-and-serve.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/api/convert-and-serve.md)


## WebP on demand
The library can be used to create a *WebP On Demand* solution, which automatically serves WebP images instead of jpeg/pngs for browsers that supports WebP. To set this up, follow what's described  [in this tutorial](https://github.com/rosell-dk/webp-convert/blob/master/docs/webp-on-demand/webp-on-demand.md).


## WebP Convert in the wild
*WebP Convert* is used in the following projects:


#### [webp-express](https://github.com/rosell-dk/webp-express)
Wordpress integration

#### [webp-convert-cloud-service](https://github.com/rosell-dk/webp-convert-cloud-service)
A cloud service based on WebPConvert

#### [kirby-webp](https://github.com/S1SYPHOS/kirby-webp)
Kirby CMS integration

## Supporting WebP Convert
Bread on the table don't come for free, even though this library does, and always will. I enjoy developing this, and supporting you guys, but I kind of need the bread too. Please make it possible for me to have both:

[Become a backer or sponsor on Patreon](https://www.patreon.com/rosell).
