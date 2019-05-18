# Migrating to 2.0

If you only used the `WebPConvert::convert()` and/or the `WebPConvert::serveConverted()` methods, there are only a few things you need to be aware of.

- *`WebPConvert::convert` no longer returns a boolean indicating the result.*
- A few options has been renamed
- A few option defaults has been changed

#### The options that has been renamed are the following:

- In *ewww*, the `key` option has been renamed to `api-key` (or [`ewww-api-key`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#ewww-api-key))
- In *wpc*, the `url` option has been renamed to `api-url` (or [`wpc-api-url`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#wpc-api-url))
* In *cwebp*, the [`lossless`] option is no longer forced true for pngs and it can now be ["auto"](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/introduction-for-converting.md#auto-selecting-between-losslesslossy-encoding)
- In *gd*, the `skip-pngs` option has been removed and replaced with the general `skip` option and prefixing. So `gd-skip` amounts to the same thing, but notice that Gd no longer skips per default.

#### The option defaults that has been changed are the following:
- the `converters` default now includes the cloud converters (*ewww* and *wpc*) and also two new converters, *vips* and *gmagickbinary*. So it is not necessary to add *ewww* or *wpc* explicitly. Also, when you set options with `converter-options` and point to a converter that isn't in the stack, in 1.3.9, this resulted in the converter automatically being added. This behavior has been removed.
- *gd* no longer skips pngs per default. To make it skip pngs, set `gd-skip` to *true*
- Default quality is now 75 for jpegs and 85 for pngs (it was 75 for both)
- Default `lossless` is now "auto"
- For *wpc*, default `secret` and `api-key` are now "" (they were "my dog is white")

## Additions
You might also be interested in the new options available in 2.0:

- Added a syntax for conveniently targeting specific converters. If you for example prefix the "quality" option with "gd-", it will override the "quality" option, but only for gd.
- Certain options can now be set with environment variables too ("EWWW_API_KEY", "WPC_API_KEY" and "WPC_API_URL")
- Added new *vips* converter.
- Added new *gmagickbinary* converter.
- Added new *stack* converter (the stack functionality has been moved into a converter)
- Added [`jpeg`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#jpeg) and [`png`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#png) options
- Added [`alpha-quality`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#alpha-quality) option for *cwebp*, *imagickbinary* and the new *vips* converter.
- Added [`autofilter`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#autofilter) option for *cwebp*, *imagickbinary* and the new *vips* converter.
- Added [`lossless`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#lossless) option for *imagickbinary* and the new *vips* converter. And it was changed in *cwebp* (see above)
- Added [`near-lossless`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#near-lossless) option for *cwebp* and *imagickbinary*.
- Added [`preset`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#preset) option for *cwebp* and the new *vips* converter.
- Added [`skip`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#skip) option (its general and works for all converters)
- Besides the ones mentioned above, *imagickbinary* now also supports [`low-memory`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#low-memory), [`metadata`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#metadata) ("all" or "none") and [`method`](https://github.com/rosell-dk/webp-convert/blob/master/docs/v2.0/converting/options.md#method). *imagickbinary* has become very potent!

## Changes in conversion api
- *`WebPConvert::convert` no longer returns a boolean indicating the result.* If conversion fails, an exception is thrown, no matter what the reason is. The exception hierarchy has been extended quite a lot.
- All converters now extend a common base class.
- The stack functionality is moved into a new "stack" converter
