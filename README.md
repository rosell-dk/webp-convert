# WebP Convert

[![Build Status](https://travis-ci.org/rosell-dk/webp-convert.png?branch=master)](https://travis-ci.org/rosell-dk/webp-convert)

*Convert JPEG & PNG to WebP with PHP*

This library enables you to do webp conversion with PHP using *cwebp*, *gd*, *imagick*, *ewww* cloud converter or the open source *wpc* cloud converter. It also allows you to try a whole stack &ndash; useful if you do not have control over the environment, and simply want the library to do *everything it can* to convert the image to webp.

**New. Only available in master:** 
In addition to converting, the library also has a method for *serving* converted images, and we have instructions here on how to set up a solution for automatically serving webp images to browsers that supports webp.

## Installation
Require the library with *Composer*, like this:

```text
composer require rosell-dk/webp-convert
```

## Converting images
To convert an image, using a stack of converters, use the *WebPConvert::convert* method. It is documented in [docs/api/convert.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/api/convert.md).

Here is an example:

```php
<?php

// Initialise your autoloader (this example is using Composer)
require 'vendor/autoload.php';

use WebPConvert\WebPConvert;

$source = __DIR__ . '/logo.jpg';
$destination = __DIR__ . '/logo.jpg.webp';

$success = WebPConvert::convert($source, $destination, [
    // It is not required that you set any options - all have sensible defaults.
    // We set some, for the sake of the example.
    'quality' => 'auto',
    'max-quality' => 80,
    'converters' => ['cwebp', 'gd', 'imagick', 'wpc', 'ewww'],  // Specify conversion methods to use, and their order

    // Note: the next option is only available in master (will be available in v1.2.0)
    // In pre 1.2.0, you set the options in the converters array (see docs)
    'converter-options' => [
        'ewww' => [
            'key' => 'your-api-key-here'
        ],
        'wpc' => [
            'url' => 'https://example.com/wpc.php',
            'secret' => 'my dog is white'
        ]
    ]

    // more options available! - see the api
]);
```

To convert using a specific conversion method, simply set the *converters* option so it only has that method.

The conversion methods (aka "converters") are documented here:   [docs/converters.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/converters.md).


## Serving converted images
**This feature is new and only available in master**
The *convertAndServe* method tries to serve a converted image. If there already is an image at the destination, it will take that, unless the original is newer or smaller. If the method cannot serve a converted image, it will serve original image, a 404, or whatever the 'fail' option is set to - and return false.

Example:
```php
<?php
$success = WebPConvert::convertAndServe($source, $destination, [
    'fail' => 'original',            // original | 404 | report-as-image | report

    // Besides the specific options for convertAndServe(), you can also use the options for convert()
]);
```
To see all options, look at the API: [docs/api/convert-and-serve.md](https://github.com/rosell-dk/webp-convert/blob/master/docs/api/convert-and-serve.md)


## WebP on demand
The library can be used to create a *WebP On Demand* solution, which automatically serves WebP images instead of jpeg/pngs for browsers that supports WebP. To set this up is described  [in this tutorial](https://github.com/rosell-dk/webp-convert/blob/master/docs/webp-on-demand/webp-on-demand.md)


## WebP Convert in the wild
*WebP Convert* is used in the following projects:


#### [webp-express](https://github.com/rosell-dk/webp-express)
Wordpress integration

#### [webp-convert-cloud-service](https://github.com/rosell-dk/webp-convert-cloud-service)
A cloud service based on WebPConvert

#### [kirby-webp](https://github.com/S1SYPHOS/kirby-webp)
Kirby CMS integration
