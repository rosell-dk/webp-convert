# Options

This is a list of all options available for converting.

Note that as the *stack* and *wpc* converters delegates the options to their containing converters, the options that they supports depend upon the converters they have been configured to use (and which of them that are operational)<br><br>

### `alpha-quality`
```
Type:         integer (0-100)
Default:      85
Supported by: cwebp, vips, imagick, gmagick, imagemagick and graphicsmagick
```
Quality of alpha channel. Only relevant for lossy encoding and only relevant for images with alpha channel.<br><br>

### `auto-filter`
```
Type:         boolean
Default:      false
Supported by: cwebp, vips, imagick, gmagick, imagemagick and graphicsmagick
```
Turns auto-filter on. This algorithm will spend additional time optimizing the filtering strength to reach a well-balanced quality. Unfortunately, it is extremely expensive in terms of computation. It takes about 5-10 times longer to do a conversion. A 1MB picture which perhaps typically takes about 2 seconds to convert, will takes about 15 seconds to convert with auto-filter. So in most cases, you will want to leave this at its default, which is off.<br><br>

### `auto-limit`
```
Type:         boolean
Default:      true
Supported by: all
```
Limits the quality to be no more than that of the jpeg. The option is only relevant when converting jpegs to lossy webp. To be functional, webp-convert needs to be able to detect the quality of the jpeg, which requires ImageMagick or GraphicsMagick. Read about the option in the [introduction](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md#auto-quality). In 2.7.0, it will become possible to adjust the limit with a new option. I'm currently debating with myself how this should work. Your comments and opinions would be appreciated - [here](https://github.com/rosell-dk/webp-convert/issues/289)


### `cwebp-command-line-options`
```
Type:         string
Default:      ''
Supported by: cwebp
```
This allows you to set any parameter available for cwebp in the same way as you would do when executing *cwebp*. You could ie set it to "-sharpness 5 -mt -crop 10 10 40 40". Read more about all the available parameters in [the docs](https://developers.google.com/speed/webp/docs/cwebp).<br><br>

### `cwebp-rel-path-to-precompiled-binaries`
```
Type:         string
Default:      './Binaries'
Supported by: cwebp
```
Allows you to change where to look for the precompiled binaries. While this may look as a risk, it is completely safe, as the binaries are hash-checked before being executed. The option is needed when you are using two-file version of webp-on-demand.

### `cwebp-try-common-system-paths`
```
Type:         boolean
Default:      true
Supported by: cwebp
```
If set, the converter will try to look for cwebp in locations such as `/usr/bin/cwebp`. It is a limited list. It might find something that isn't found using `try-discovering-cwebp` if these common paths are not within PATH or neither `which` or `whereis` are available.

### `cwebp-try-cwebp`
```
Type:         boolean
Default:      true
Supported by: cwebp
```
If set, the converter will try executing cwebp with a plain "cwebp" command (without specifying a path).

### `try-discovering-cwebp`
```
Type:         boolean
Default:      true
Supported by: cwebp
```
If set, the converter will try to discover installed cwebp binaries using the `which -a cwebp` command, or in case that fails, the `whereis -b cwebp` command. These commands will find cwebp binaries residing in PATH. They might find cwebp binaries which are not found by enabling `cwebp-try-common-system-paths`


### `cwebp-try-supplied-binary-for-os`
```
Type:         boolean
Default:      true
Supported by: cwebp
```
If set, the converter will try the precompiled cwebp binary that are located in `src/Convert/Converters/Binaries`, for the current OS. The binaries are hash-checked before executed.

### `cwebp-skip-these-precompiled-binaries`
```
Type:         string
Default:      ''
Supported by: cwebp
```
The precompiled binaries from google have dependencies, and they are different. This means that some of them works  on some systems, others on others. For this reason, several precompiled binaries are shipped with the library - we want it to simply work on as many systems as possible. Of course, the binary with the highest version number is tried first. But if it doesn't work, time has been wasted running an executable that doesn't work, and validating the hash before running it. To avoid this, use this option to bypass precompiled binaries that you know doesn't work on your current system. You pass in the filenames (comma separated), ie "cwebp-120-linux-x86-64,cwebp-110-linux-x86-64". In order to see if time is wasted on a supplied binary, that doesn't work, check the conversion log. You can also get info about the filenames of the binaries in the conversion log. Instructions on viewing the conversion log are available [here](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md#insights-to-the-process).

### `default-quality` (DEPRECATED)
```
Type:          integer (0-100)
Default:       75 for jpegs and 85 for pngs
Supported by:  all (cwebp, ewww, gd, ffmpeg, gmagick, graphicsmagick, imagick, imagemagick, vips)
```
This option has been deprecated. See why [here](https://github.com/rosell-dk/webp-convert/issues/281). It was used to determine the quality in case auto limiting was not available.<br><br>

### `encoding`
```
Type:          string  ("lossy" | "lossless" | "auto")
Default:       "auto"
Supported by:  cwebp, vips, ffmpeg, imagick, gmagick, imagemagick and graphicsmagick  (gd always uses lossy encoding, ewww uses lossless for pngs and lossy for jpegs)
```
Read about this option in the ["lossy/lossless" section in the introduction](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md#auto-selecting-between-losslesslossy-encoding).<br><br>

### `ewww-api-key`
```
Type:         string
Default:      ''
Supported by: ewww
```
Api key for the ewww converter. The option is actually called *api-key*, however, any option can be prefixed with a converter id to only apply to that converter. As this option is only for the ewww converter, it is natural to use the "ewww-" prefix.

Note: This option can alternatively be set through the *EWWW_API_KEY* environment variable.<br><br>

### `ewww-check-key-status-before-converting`
```
Type:         boolean
Default:      true
Supported by: ewww
```
Decides whether or not the ewww service should be invoked in order to check if the api key is valid. Doing this for every conversion is not optimal. However, it would be worse if the service was contacted repeatedly to do conversions with an invalid api key - as conversion requests carries a big upload with them. As this library cannot prevent such repeated failures (it is stateless), it per default does the additional check. However, your application can prevent it from happening by picking up invalid / exceeded api keys discovered during conversion. Such failures are stored in `Ewww::$nonFunctionalApiKeysDiscoveredDuringConversion` (this is also set even though a converter later in the stack succeeds. Do not only read this value off in a catch clauses).

You should only set this option to *false* if you handle when the converter discovers invalid api keys during conversion.

### `jpeg`
```
Type:          array
Default:       []
Supported by:  all
```
Override selected options when the source is a jpeg. The options provided here are simply merged into the other options when the source is a jpeg.
Read about this option in the [introduction](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md#png-og-jpeg-specific-options).<br><br>

### `log-call-arguments`
```
Type:          boolean
Default:       false
Supported by:  all
```
Enabling this simply puts some more in the log - namely the arguments that was supplied to the call. Sensitive information is starred out.

### `low-memory`
```
Type:          boolean
Default:       false
Supported by:  cwebp, imagick, imagemagick and graphicsmagick
```
Reduce memory usage of lossy encoding at the cost of ~30% longer encoding time and marginally larger output size. Read more in [the docs](https://developers.google.com/speed/webp/docs/cwebp).<br><br>

### `max-quality` (DEPRECATED)
```
Type:          integer (0-100)
Default:       85
Supported by:  all (cwebp, ewww, ffmpeg, gd, gmagick, graphicsmagick, imagick, imagemagick, vips)
```
This option has been deprecated. See why [here](https://github.com/rosell-dk/webp-convert/issues/281)<br><br>

### `metadata`
```
Type:          string ("all" | "none" | "exif" | "icc" | "xmp")
Default:       'none'
Supported by:  'none' is supported by all. 'all' is supported by all, except *gd* and *ffmpeg*. The rest is only supported by *cwebp*
```
Only *cwebp* supports all values. *gd* will always remove all metadata. The rest can either strip all or keep all (they will keep all, unless the option is set to *none*).<br><br>

### `method`
```
Type:          integer (0-6)
Default:       6
Supported by:  cwebp, vips, imagick, gmagick, imagemagick, graphicsmagick and ffmpeg
```
This parameter controls the trade off between encoding speed and the compressed file size and quality. Possible values range from 0 to 6. 0 is fastest. 6 results in best quality. PS: "method" is not a very descriptive name, but this is what its called in libwebp, which is why we also choose it for webpconvert. In ffmpeg, they renamed it "compression_level", in vips, they call it "reduction_effort". Both better names, but as said, use "method" with webpconvert<br><br>

### `near-lossless`
```
Type:          integer (0-100)
Default:       60
Supported by:  cwebp, vips
```
Specify the level of near-lossless image preprocessing. This option adjusts pixel values to help compressibility, but has minimal impact on the visual quality. It triggers lossless compression mode automatically. The range is 0 (maximum preprocessing) to 100 (no preprocessing). The typical value is around 60. Read more [here](https://groups.google.com/a/webmproject.org/forum/#!topic/webp-discuss/0GmxDmlexek).<br><br>

### `png`
```
Type:          array
Default:       []
Supported by:  all
```
Override selected options when the source is a png. The options provided here are simply merged into the other options when the source is a png.
Read about this option in the [introduction](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md#png-og-jpeg-specific-options).<br><br>

### `preset`
```
Type:          string ('none', 'default', 'photo', 'picture', 'drawing', 'icon' or 'text')
Default:       "none"
Supported by:  cwebp, vips, gmagick, graphicsmagick, imagick, imagemagick, ffmpeg
```
Using a preset will set many of the other options to suit a particular type of source material. It even overrides them. It does however not override the quality option. "none" means that no preset will be set. PS: The imagemagick family only partly supports this setting, as they have grouped three of the options ("drawing", "icon" and "text") into "graph". So if you for example set "preset" to "icon" with the imagemagick converter, imagemagick will be executed like this: "-define webp:image-hint='graph'".<br><br>

### `quality`
```
Type:          integer (0-100) | "auto"  ("auto" is now deprecated - use the "auto-limit" option instead)
Default:       75 for jpegs and 85 for pngs
Supported by:  all (cwebp, ewww, gd, gmagick, graphicsmagick, imagick, imagemagick, vips, ffmpeg)
```
Quality for lossy encoding.<br><br>

### `sharp-yuv`
```
Type:          boolean
Default:       true
Supported by:  cwebp, vips, gmagick, graphicsmagick, imagick, imagemagick
```
Use more accurate and sharper RGB->YUV conversion if needed. Note that this process is slower than the default 'fast' RGB->YUV conversion. The benefits of using this option is exemplified [here](https://www.ctrl.blog/entry/webp-sharp-yuv.html).

### `size-in-percentage`
```
Type:          integer (0-100) | null
Default:       null
Supported by:  cwebp
```
This option sets the file size, *cwebp* should aim for, in percentage of the original. If you for example set it to *45*, and the source file is 100 kb, *cwebp* will try to create a file with size 45 kb (we use the `-size` option). This is an excellent alternative to the "quality:auto" option. If the quality detection isn't working on your system (and you do not have the rights to install imagick or gmagick), you should consider using this options instead. *Cwebp* is generally able to create webp files with the same quality at about 45% the size. So *45* would be a good choice. The option overrides the quality option. And note that it slows down the conversion - it takes about 2.5 times longer to do a conversion this way, than when quality is specified. Default is *off* (null).<br><br>

### `skip`
```
Type:          boolean
Default:       false
Supported by:  all
```
Simply skips conversion. For example this can be used to skip png conversion for a specific converter like this:
```php
$options = [
    'png' => [
        'gd-skip' => true,
    ]
];
```

Or it can be used to skip unwanted converters from the default stack, like this:
```php
$options = [
    'ewww-skip' => true,
    'wpc-skip' => true,
    'gd-skip' => true,
    'imagick-skip' => true,
    'gmagick-skip' => true,
];
```
<br>

### `stack-converters`
```
Type:         array
Default:      ['cwebp', 'vips', 'imagick', 'gmagick', 'imagemagick', 'graphicsmagick', 'wpc', 'ewww', 'gd']
Supported by: stack
```

Specify the converters to try and their order.

Beware that if you use this option, you will miss out when more converters are added in future updates. If the purpose of setting this option is to remove converters that you do not want to use, you can use the *skip* option instead. Ie, to skip ewww, set *ewww-skip* to true. On the other hand, if what you actually want is to change the order, you can use the *stack-preferred-converters* option, ie setting *stack-preferred-converters* to `['vips', 'wpc']` will move vips and wpc in front of the others. Should they start to fail, you will still have the others as backup.

The array specifies the converters to try and their order. Each item can be:

- An id (ie "cwebp")
- A fully qualified class name (in case you have programmed your own custom converter)
- An array with two keys: "converter" and "options".

`
Alternatively, converter options can be set using the *converter-options* option.

Read more about the stack converter in the [introduction](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md#the-stack-converter).<br><br>

### `stack-converter-options`
```
Type:         array
Default:      []
Supported by: stack
```
Extra options for specific converters. Example:

```php
$options = [
    'converter-options' => [
        'vips' => [
            'quality' => 72
        ],
    ]    
]
```
<br>

### `stack-extra-converters`
```
Type:         array
Default:      []
Supported by: stack
```
Add extra converters to the bottom of the stack. The items are similar to those in the `stack-converters` option.<br><br>

### `stack-preferred-converters`
```
Type:         array
Default:      []
Supported by: stack
```
With this option you can move specified converters to the top of the stack. The converters are specified by id. For example, setting this option to ['vips', 'wpc'] ensures that *vips* will be tried first and - in case that fails - *wpc* will be tried. The rest of the converters keeps their relative order.<br><br>

### `stack-shuffle`
```
Type:          boolean
Default:       false
Supported by:  stack
```
Shuffle the converters in the stack. This can for example be used to balance load between several wpc instances in a substack, as illustrated [here](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/converters/stack.md)<br><br>

### `use-nice`
```
Type:          boolean
Default:       false
Supported by:  cwebp, graphicsmagick, imagemagick, ffmpeg
```
This option only applies to converters which are using exec() to execute a binary directly on the host. If *use-nice* is set, it will be examined if the [`nice`]( https://en.wikipedia.org/wiki/Nice_(Unix)) command is available on the host. If it is, the binary is executed using *nice*. This assigns low priority to the process and will save system resources - but result in slower conversion.<br><br>

### `vips-smart-subsample` (DEPRECATED)
```
Type:          boolean
Default:       false
Supported by:  vips
```
This feature seemed not to be part of *libwebp* but intrinsic to vips. However, we were wrong - the feature is the same as 'sharp-yuv'. Use that instead.<br><br>

### `wpc-api-key`
```
Type:          string
Default:       ''
Supported by:  wpc
```
Api key for the wpc converter. The option is actually called *api-key*, however, any option can be prefixed with a converter id to only apply to that converter. As this option is only for the wpc converter, it is natural to use the "wpc-" prefix. Same goes for the other "wpc-" options.

Note: You can alternatively set the api key through the *WPC_API_KEY* environment variable.<br><br>

### `wpc-api-url`
```
Type:          string
Default:       ''
Supported by:  wpc
```
Note: You can alternatively set the api url through the *WPC_API_URL* environment variable.<br><br>

### `wpc-api-version`
```
Type:          integer (0 - 1)
Default:       0
Supported by:  wpc
```
<br>

### `wpc-crypt-api-key-in-transfer`
```
Type:          boolean
Default:       false
Supported by:  wpc
```
<br>

### `wpc-secret`
```
Type:          string
Default:       ''
Supported by:  wpc
```
Note: This option is only relevant for api version 0.
