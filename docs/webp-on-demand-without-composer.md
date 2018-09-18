# WebP On Demand without composer

***THIS SOLUTION NEEDS TO BE UPDATED. The method only works in the webp-on-demand git repository. But I'm soon closing that one in favour of having it here***

For your convenience, the library has been cooked down to two files: *webp-on-demand.inc* and *webp-convert-and-serve.inc*. The second one is loaded when the first one decides it needs to do a conversion (and not simply serve existing image).

## Installing

### 1. Copy the latest build files into your website
Copy *webp-on-demand.inc* and *webp-convert-and-serve.inc* from the *build* folder into your website. They can be located wherever you like.

### 2. Create a *webp-on-demand.php*

Create a file *webp-on-demand.php*, and place it in webroot, or where-ever you like in you web-application.

Here is a minimal example to get started with:

```php
<?php
require 'webp-on-demand.inc';

use WebPOnDemand\WebPOnDemand;

$source = $_GET['source'];            // Absolute file path to source file. Comes from the .htaccess
$destination = $source . '.webp';     // Store the converted images besides the original images (other options are available!)

$options = [

    // Tell where to find the webp-convert-and-serve library, which will
    // be dynamically loaded, if need be.
    'require-for-conversion' => 'webp-convert-and-serve.inc';

    // UNCOMMENT NEXT LINE, WHEN YOU ARE UP AND RUNNING!    
    'show-report' => true             // Show a conversion report instead of serving the converted image.

    // More options available!
];

WebPOnDemand::serve($source, $destination, $options);
```

### 3. Continue the main install instructions from step 3
[Click here to continue...](https://github.com/rosell-dk/webp-on-demand#3-add-redirect-rules)
