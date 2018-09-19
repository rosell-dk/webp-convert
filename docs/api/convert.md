# API: The convert() method

**WebPConvert::convert($source, $destination, $options, $logger)**

| Parameter        | Type    | Description                                                                                |
| ---------------- | ------- | ------------------------------------------------------------------------------------------ |
| `$source`        | String  | Absolute path to source image (only forward slashes allowed)                               |
| `$destination`   | String  | Absolute path to converted image (only forward slashes allowed)                            |
| `$options` (optional)      | Array   | Array of conversion (option) options                                             |
| `$logger` (optional)        | Baselogger   | Information about the conversion process will be passed to this object. Read more below                               |

Returns true if success or false if no converters are *operational*. If any converter seems to have its requirements met (are *operational*), but fails anyway, and no other converters in the stack could convert the image, an the exception from that converter is rethrown (either *ConverterFailedException* or *ConversionDeclinedException*). Exceptions are also thrown if something is wrong entirely (*InvalidFileExtensionException*, *TargetNotFoundException*, *ConverterNotFoundException*, *CreateDestinationFileException*, *CreateDestinationFolderException*, or any unanticipated exceptions thrown by the converters).

### Available options

Many options correspond to options of *cwebp*. These are documented [here](https://developers.google.com/speed/webp/docs/cwebp)


| Option            | Type    | Default                    | Description                                                          |
| ----------------- | ------- | -------------------------- | -------------------------------------------------------------------- |
| quality           | An integer between 0-100. As of v1.1, it can also be "auto" | In v1.0, default is 85<br><br>As of v1.1, default is "auto"                          | Lossy quality of converted image (JPEG only - PNGs are always losless).<br><br> If set to "auto", *WebPConvert* will try to determine the quality of the JPEG (this is only possible, if Imagick or GraphicsMagic is installed). If successfully determined, the quality of the webp will be set to the same as that of the JPEG. however not to more than specified in the new `max-quality` option. If quality cannot be determined, quality will be set to what is specified in the new `default-quality` option |
| max-quality           | An integer between 0-100 | 85 | See the `quality` option. Only relevant, when quality is set to "auto".
| default-quality           | An integer between 0-100 | 80 | See the `quality` option. Only relevant, when quality is set to "auto".
| metadata          | String  | 'none'                      | Valid values: all, none, exif, icc, xmp. Note: Not supported by all converters             |
| method            | Integer | 6                           | Specify the compression method to use (0-6). When higher values are used, the encoder will spend more time inspecting additional encoding possibilities and decide on the quality gain. Lower value can result in faster processing time at the expense of larger file size and lower compression quality. |
| low-memory        | Boolean | false                       | Reduce memory usage of lossy encoding by saving four times the compressed size (typically) |
| lossless          | Boolean | false                       | Encode the image without any loss. The option is ignored for PNG's (forced true) |
| converters        | Array   | ['cwebp', 'gd', 'imagick']  | Specify conversion methods to use, and their order. Also optionally set converter options (see below) |
| converter-options | Array   | []                          | <b>Upcoming in v1.2.0</b>. Set options of the individual converters (see below) |


#### More on the `converter-options` option
***This option is available in master and will be part of the upcoming v1.2.0***
You use this option to set options for the individual converters. Example:

```
'converter-options' => [
    'ewww' => [
        'key' => 'your-api-key-here'
    ],
    'wpc' => [
        'url' => 'https://example.com/wpc.php',
        'secret' => 'my dog is white'
    ]
]
```
Besides options that are special to a converter, you can also override general options. For example, you may generally want the `max-quality` to be 85, but for a single converter, you would like it to be 100 (sorry, it is hard to come up with a useful example).

#### More on the `converters` option
The *converters* option specifies the conversion methods to use and their order. But it can also be used as an alternative way of setting converter options. Usually, you probably want to use the *converter-options* for that, but there may be cases where it is more convenient to specify them here. Also, specifying here allows you to put the same converter method to the stack multiple times, with different options (this could for example be used to have an extra *ewww* converter as a fallback).

Example:
```
WebPConvert::convert($source, $destination, [
    'converters' => [
        'cwebp',    
        'imagick',
        [
            'converter' => 'ewww',
            'options' => [            
                'key' => 'your api key here',
            ],
        ],
    ];
)
```


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
