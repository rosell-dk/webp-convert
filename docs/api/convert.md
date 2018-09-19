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
| converters        | Array   | ['cwebp', 'gd', 'imagick']  | Specify converters to use, and their order. Also optionally set converter options (see below) |
| extra-converters  | Array   | []                          | Add extra converters    |


#### More on the `converters` option
When setting the `converters` option, you can also set options for the converter. This can be used for overriding the general options. For example, you may generally want the `quality` to be 85, but for a single converter, you would like it to be 100. It can also be used to set options that are special for the converter. For example, the ewww converter has a `key` option and `cwebp` has the special `use-nice` options. Gd converter has the option `skip-pngs`.

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

#### More on the `extra-converters` option
You use the `extra-converters` to append converters to the list defined by the `converters` option. This is the preferred way of adding cloud converters. You are allowed to specify the same converter multiple times (you can btw also do that with the `converters` option). This can be useful if you for example have multiple accounts for a cloud service and are afraid that one of them might expire.

Example:
```
WebPConvert::convert($source, $destination, [
    'extra-converters' => [
        [
            'converter' => 'ewww',
            'options' => [
                'key' => 'your api key here',
            ],
        ],
        [
            'converter' => 'ewww',
            'options' => [
                'key' => 'your other api key here, in case the first one has expired',
            ],
        ],
    ]
]);
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
