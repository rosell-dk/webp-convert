# WebP on demand

This is a solution for automatically serving WebP images instead of jpeg/pngs for browsers that supports WebP (Google Chrome, that is).

Once set up, it will automatically convert images, no matter how they are referenced. It for example also works on images referenced in CSS. As the solution does not require any change in the HTML, it can easily be integrated into any website / framework

## Overview

A setup consists of a PHP script that serves converted images and some *redirect rules* that redirects JPG/PNG images to the script.


## Requirements

* *Apache* or *LiteSpeed* web server. Can be made to work with *NGINX* as well. Documentation is on the roadmap.
* PHP >= 5.6  (we are only testing down to 5.6. It should however work in 5.5 as well)
* That one of the *webp-convert* converters are working (these have different requirements)

## Installation

Here we assume you are using Composer. [Not using composer? - Follow me!](https://github.com/rosell-dk/webp-convert/blob/master/docs/webp-on-demand/without-composer.md)

### 1. Require this library with composer
```
composer require rosell-dk/webp-convert
```


### 2. Create the script

Create a file *webp-on-demand.php*, and place it in webroot, or where-ever you like in you web-application.

Here is a minimal example to get started with:

```php
<?php
require 'vendor/autoload.php';        // Make sure to point this correctly

use WebPConvert\WebPConvert;

$source = $_GET['source'];            // Absolute file path to source file. Comes from the .htaccess
$destination = $source . '.webp';     // Store the converted images besides the original images (other options are available!)

$options = [

    // UNCOMMENT NEXT LINE, WHEN YOU ARE UP AND RUNNING!    
    'show-report' => true             // Show a conversion report instead of serving the converted image.

    // More options available!
];
WebPConvert::convertAndServe($source, $destination, $options);
```

### 3. Add redirect rules
Place the following rewrite rules in a *.htaccess* file in the directory where you want the solution to take effect:

```
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Redirect images to webp-on-demand.php (if browser supports webp)
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteRule ^(.*)\.(jpe?g|png)$ webp-on-demand.php?source=%{SCRIPT_FILENAME} [NC,L]
</IfModule>

AddType image/webp .webp
```
If you have placed *webp-on-demand.php* in a subfolder, you will need to change the rewrite rule accordingly.


### 4. Validate that it works

Browse to an JPEG image. Instead of an image, you should see a conversion report. Hopefully, you get a success. Otherwise, you need to hook up to a cloud converter or try to meet the requirements for cwebp, gd or imagick.

Once you get a successful conversion, you can uncomment the "show-report" option in the script.

It should work now, but to be absolute sure:

1. Visit a page on your site with an image on it, using *Google Chrome*.
- Right-click the page and choose "Inspect"
- Click the "Network" tab
- Reload the page
- Find a jpeg or png image in the list. In the "type" column, it should say "webp". There should also be a *X-WebP-Convert-Status* header on the image that provides some insights on how things went.



### 5. Customizing and tweaking

Basic customizing is done by setting options in the `$options` array. Check out the [docs on convert()](https://github.com/rosell-dk/webp-convert/blob/master/docs/api/convert.md) and the [docs on convertAndServe()](https://github.com/rosell-dk/webp-convert/blob/master/docs/api/convert-and-serve.md)

Other tweaking is described in *docs/webp-on-demand/tweaks.md*:
- [Store converted images in separate folder](https://github.com/rosell-dk/webp-convert/blob/master/docs/webp-on-demand/tweaks.md#store-converted-images-in-separate-folder)
- [CDN](https://github.com/rosell-dk/webp-convert/blob/master/docs/webp-on-demand/tweaks.md#cdn)
- [Make .htaccess route directly to existing images](https://github.com/rosell-dk/webp-convert/blob/master/docs/webp-on-demand/tweaks.md#make-htaccess-route-directly-to-existing-images)
- [Forward the query string](https://github.com/rosell-dk/webp-convert/blob/master/docs/webp-on-demand/tweaks.md#forward-the-querystring)


## Troubleshooting

### The redirect rule doesn't seem to be working
If images are neither routed to the converter or a 404, it means that the redirect rule isn't taking effect. Common reasons for this includes:

- Perhaps there are other rules in your *.htaccess* that interfere with the rules?
- Perhaps your site is on *Apache*, but it has been configured to use *Nginx* to serve image files. To find out which server that is handling the images, browse to an image and eximine the "Server" response header. In case *NGINX* are serving images, see if you can reconfigure your server setup. Alternatively, you can create *NGINX* rewrite rules. There are some [here](https://github.com/S1SYPHOS/kirby-webp#nginx).
- Perhaps the server isn't configured to allow *.htaccess* files? Try inserting rubbish in the top of the *.htaccess* file and refresh. You should now see an *Internal Server Error* error page. If you don't, your *.htaccess* file is ignored. Probably you will need to set *AllowOverride All* in your Virtual Host. [Look here for more help](
https://docs.bolt.cm/3.4/howto/making-sure-htaccess-works#test-if-htaccess-is-working)


## Related
* https://www.maxcdn.com/blog/how-to-reduce-image-size-with-webp-automagically/
