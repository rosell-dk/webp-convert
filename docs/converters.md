# The webp converters

## The converters at a glance

[`cwebp`](#cwebp) works by executing the *cwebp* binary from Google, which is build upon the *libwebp* (also from Google). That library is actually the only library in town for generating webp images, which means that the other conversion methods ultimately uses that very same library. Which again means that the results using the different methods are very similar. However, with *cwebp*, we have more parameters to tweak than with the rest. We for example have the *method* option, which controls the trade off between encoding speed and the compressed file size and quality. Setting this to max, we can squeeze the images a few percent extra - without loosing quality (the converter is still pretty fast, so in most cases it is probably worth it). Unfortunately, *cwebp* (unlike *gmagick*) does not have a way to automatically use same quality for the converted image as was used for the source image. If you set the quality option to "auto", our library will try to detect the quality with Imagick and Gmagick, but if this fails, the fallback quality will be used. The effect is that a jpeg image with low quality (say 50) will be converted with higher quality (say 75). Converting a q=50 to a q=50 would typically result in a 60% reduction. But converting it to q=75 will only result in a ~45% reduction. Converting q=30 to q=75 only gives ~25% reduction. But I guess it is a rare case having jpeg images in q<50.

Of course, as we here have to call a binary directly, *cwebp* requires the *exec* function to be enabled, and that the webserver user is allowed to execute the `cwebp` binary (either at known system locations, or one of the precompiled binaries, that comes with this library).

[`imagick`](#imagick) does not support any special webp options, but is at least able to strip all metadata, if metadata is set to none. Imagick has a very nice feature - that it is able to detect the quality of a jpeg file. This enables it to automatically use same quality for destination as for source, which eliminates the risk of setting quality higher for the destination than for source (the result of that is that the file size gets higher, but the quality remains the same). As the other converters lends this capability from Imagick, this is however no reason for using Imagick rather than the other converters.

[`gmagick`](#gmagick) uses the *gmagick* extension. It is very similar to *imagick*.

[`gd`](#gd) uses the *Gd* extension to do the conversion. The *Gd* extension is pretty common, so the main feature of this converter is that it may work out of the box. It does not support any webp options, and does not support stripping metadata.

[`wpc`](#wpc) is an open source cloud converter based on *WebPConvert*. Conversions will of course be slower than the local converters, as images need to go back and forth to the cloud converter. As images usually just needs to be converted once, the slower conversion speed is probably acceptable. To get going, you need to install the [WPC library](https://github.com/rosell-dk/webp-convert-cloud-service) on a server (or have someone do it for you). If this this is a problem, we suggest you turn to *ewww*. (PS: A Wordpress plugin is planned, making it easier to set up a WPC instance)

[`ewww`](#ewww) is also a cloud service. It is a decent alternative for those who don't have the technical know-how to install *wpc*. *ewww* is using cwebp to do the conversion, so quality is great. *ewww* however only provides one conversion option (quality), and it is not free. But very cheap. Like in *almost* free. Oh, and *ewww* does not support auto quality (you need to have Imagick or Gmagick), but I have pitched the idea to the developer.

**Summary:**

*WebPConvert* currently supports the following converters:

| Converter                            | Method                                           | Quality | Requirements                                       |
| ------------------------------------ | ------------------------------------------------ | --------| -------------------------------------------------- |
| [`cwebp`](#cwebp)                    | Calls `cwebp` binary directly                    | best    | `exec()` function *and* that the webserver user has permission to run `cwebp` binary |
| [`wpc`](#wpc)                        | Connects to WPC cloud service                    | best    | A working *WPC* installation                       |
| [`ewww`](#ewww)                      | Connects to *EWWW Image Optimizer* cloud service | great   | Purchasing a key                                   |
| [`gd`](#gd)                          | GD Graphics (Draw) extension (`LibGD` wrapper)   | great    | GD PHP extension compiled with WebP support        |
| [`imagick`](#imagick)                | Imagick extension (`ImageMagick` wrapper)        | great   | Imagick PHP extension compiled with WebP support   |
| [`gmagick`](#gmagick)                | Gmagick extension (`ImageMagick` wrapper)        | great   | Gmagick PHP extension compiled with WebP support   |

## Installation
Instructions regarding getting the individual converters to work are [on the wiki](https://github.com/rosell-dk/webp-convert/wiki)

## cwebp
<table>
  <tr><th>Requirements</th><td><code>exec()</code> function and that the webserver has permission to run `cwebp` binary (either found in system path, or a precompiled version supplied with this library)</td></tr>
  <tr><th>Performance</th><td>~40-120ms to convert a 40kb image (depending on *method* option)</td></tr>
  <tr><th>Reliability</th><td>No problems detected so far!</td></tr>
  <tr><th>Availability</th><td>According to ewww docs, requirements are met on surprisingly many webhosts. Look <a href="https://docs.ewww.io/article/43-supported-web-hosts">here</a> for a list</td></tr>
  <tr><th>General options supported</th><td>All (`quality`, `metadata`, `method`, `low-memory`, `lossless`)</td></tr>
  <tr><th>Extra options</th><td>`use-nice` (boolean)<br>`try-common-system-paths` (boolean)<br> `try-supplied-binary-for-os` (boolean)</td></tr>
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

## wpc
*WebPConvert Cloud Service*

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
WebPConvert::convert($source, $destination, [
    'extra-converters' => [
        [
            'converter' => 'wpc',
            'options' => [
                'url' => 'http://example.com/wpc.php',
                'secret' => 'my dog is white',
            ],
        ],
    ]
));
```


## ewww

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

## gd

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

## imagick

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
