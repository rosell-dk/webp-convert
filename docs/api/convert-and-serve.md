# API: The WebPConvert::convertAndServe() method

The method tries to serve a converted image. If destination already exists, the already converted image will be served. Unless the original is newer or smaller. If the method fails, it will serve original image, a 404, or whatever the 'fail' option is set to.

**WebPConvert::convertAndServe($source, $destination, $options)**

| Parameter        | Type    | Description                                                         |
| ---------------- | ------- | ------------------------------------------------------------------- |
| `$source`        | String  | Absolute path to source image (only forward slashes allowed)        |
| `$destination`   | String  | Absolute path to converted image (only forward slashes allowed)     |
| `$options`       | Array   | Array of options (see below)                                        |

## The *$options* argument
The options argument is a named array. Besides the options described below, you can also use any options that the *convert* method takes (if a fresh convertion needs to be created, this method will call the *convert* method and hand over the options argument)

### *fail*
Indicate what to serve, in case of normal conversion failure.
Default value: *"original"*

| Possible values   | Meaning                                         |
| ----------------- | ----------------------------------------------- |
| "original"        | Serve the original image.                       |
| "404"             | Serve 404 status (not found)                    |
| "report-as-image" | Serve an image with text explaining the problem |
| "report"          | Serve a textual report explaining the problem   |

### *fail-when-original-unavailable*
Possible values: Same as above, except that "original" is not an option.
Default value: *"404"*

### *show-report*
Produce a report rather than serve an image.  
Default value: *false*

### *reconvert*
Force a conversion, discarding existing converted image (if any).
Default value: *false*

### *serve-original*
Forces serving original image.  
Default value: *false*

### *add-x-header-status*
When set to *true*, a *X-WebP-Convert-Status* header will be added describing how things went.  
Default value: *true*

Depending on how things goes, the header will be set to one of the following:
- "Failed (missing source argument)"
- "Failed (source not found)""
- "Failed (missing destination argument)"
- "Reporting..."
- "Serving original image (was explicitly told to)"
- "Serving original image - because it is smaller than the converted!"
- "Serving freshly converted image (the original had changed)"
- "Serving existing converted image"
- "Converting image (handed over to WebPConvertAndServe)"
- "Serving freshly converted image"
- "Failed (could not convert image)"

### *add-vary-header*
Add a "Vary: Accept" header when an image is served. Experimental.  
Default value: *true*

### *add-content-type-header*
Add a "Content-Type" header
Default value: *true*
If set, a Content-Type header will be added. It will be set to "image/webp" if a converted image is served, "image/jpeg" or "image/png", if the original is served or "image/gif", if an error message is served (as image). You can set it to false when debugging (to check if any errors are being outputted)

### *error-reporting*
Set error reporting
Allowed values: *"auto"*, *"dont-mess"*, *true*, *false*
Default value: *"auto"*

If set to true, error reporting will be turned on, like this:
```
    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
```

If set to false, error reporting will be turned off, like this:
```
    error_reporting(0);
    ini_set('display_errors', 'Off');
```
If set to "auto", errors will be turned off, unless the `show-report` option is set, in which case errors will be turned off.
If set to "dont-mess", error reporting will not be touched.


### *require-for-conversion*
If set, makes the library 'require in' a file just before doing an actual conversion with `ConvertAndServe::convertAndServe()`. This is not needed for composer projects, as composer takes care of autoloading classes when needed.
Default value: *null*
