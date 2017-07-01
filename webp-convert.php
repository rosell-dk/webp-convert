<?php
// http://test2/webp-convert.php?source=/var/www/test/images/subfolder/logo.jpg&quality=100&preferred-converters=imagewebp,cwebp&debug&destination-root=cold&serve-image=y

/*
URL parameters:

source:
Path to source file. Can be absolute or relative (relative to document root). If it starts with "/", it is considered an absolute path.

destination-root (optional):
The final destination will be calculated like this: [desired destination root] + [relative path of source file] + ".webp". If you want converted files to be put in the same folder as the originals, you can set destination-root to ".", or leave it blank. If you on the other hand want all converted files to reside in their own folder, set the destination-root to point to that folder. The converted files will be stored in a hierarchy that matches the source files. With destination-root set to "webp-cache", the source file "images/2017/cool.jpg" will be stored at "webp-cache/images/2017/cool.jpg.webp". Both absolute paths and relative paths are accepted (if the path starts with "/", it is considered an absolute path). Double-dots in paths are allowed, ie "../webp-cache"

quality:
The quality of the generated WebP image, 0-100.

strip-metadata:
If set (if "&strip-metadata" is appended to the url), metadata will not be copied over in the conversion process. Note however that not all converters supports copying metadata. cwebp supports it, imagewebp does not. You can also assign a value. Any value but "no" counts as yes

preferred-converters (optional):
Setting this manipulates the default order in which the converters are tried. If you for example set it to "cwebp", it means that you want "cwebp" to be tried first. You can specify several favourite converters. Setting it to "cwebp,imagewebp" will put cwebp to the top of the list and imagewebp will be the next converter to try, if cwebp fails. The option will not remove any converters from the list, only change the order.

serve-image (optional):
If set (if "&serve-image" is appended to the URL), the converted image will be served. Otherwise the script will produce text output about the convertion process. You can also assign a value. Any value but "no" counts as yes.

destination (optional): (TODO)
Path to destination file. Can be absolute or relative (relative to document root). You can choose not to specify destination. In that case, the path will be created based upon source, destination-root and root-folder settings. If all these are blank, the destination will be same folder as source, and the filename will have ".webp" appended to it (ie image.jpeg.webp)

root-folder (optional):
Usually, you will not need to supply anything. Might be relevant in rare occasions where the converter that generates the URL cannot pass all of the relative path. For example, an .htaccess located in a subfolder may have trouble passing the parent folders. 

debug (optional):
Enabling debug has two functions:
1) It will always return text (serve-image setting is overriden)
2) PHP error reporting is turned on

serve-original-image-on-fail (optional):
Default: "yes". Decides what action to take in the situation that (1) all converters fails to convert the image, and (2) WebPConvert is told to serve the converted image. the original image. Default action is to serve the *original* image. End-users will not notice the fail, which is good on production servers, but not on development servers. If set to "no", WebPConvert will instead generate an image containing the error message.

ewww-key (optional):
Key to EWWW Image Converter
*/

$serve_converted_image = (isset($_GET['serve-image']) ? ($_GET['serve-image'] != 'no') : FALSE);
$debug = (isset($_GET['debug']) ? ($_GET['debug'] != 'no') : FALSE);
if ($debug) {
  $serve_converted_image = FALSE;
  error_reporting(E_ALL);
  ini_set('display_errors','On');
}
else {
  if ($serve_converted_image) {
    ini_set('display_errors','Off');
  }
}

include( __DIR__ . '/WebPConvert.php');
include( __DIR__ . '/WebPConvertPathHelper.php');

$root = (isset($_GET['root-folder']) ? WebPConvertPathHelper::remove_double_slash($_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['root-folder']) : NULL);;

$source = WebPConvertPathHelper::abspath($_GET['source'], $root);
$destination = WebPConvertPathHelper::get_destination_path($source, isset($_GET['destination-root']) ? $_GET['destination-root'] : '.', $root);

$quality = (isset($_GET['quality']) ? intval($_GET['quality']) : 85);
$strip_metadata = (isset($_GET['strip-metadata']) ? ($_GET['strip-metadata'] != 'no') : FALSE);

$preferred_converters = (isset($_GET['preferred-converters']) ? explode(',', $_GET['preferred-converters']) : array()); 
//$preferred_converters = array('imagewebp', 'cwebp');

if (isset($_GET['ewww-key'])) {
//  WebPConvertEWW::setKey($_GET['ewww-key']);
//  WebPConvert::
  define("WEBPCONVERT_EWW_KEY", $_GET['ewww-key']);
}

define("WEBPCONVERT_CWEBP_LOW_MEMORY", TRUE);
define("WEBPCONVERT_IMAGICK_LOW_MEMORY", WEBPCONVERT_CWEBP_LOW_MEMORY);

define("WEBPCONVERT_CWEBP_METHOD", "6");
define("WEBPCONVERT_IMAGICK_METHOD", WEBPCONVERT_CWEBP_METHOD);


WebPConvert::$serve_converted_image = $serve_converted_image;
WebPConvert::$serve_original_image_on_fail = (isset($_GET['serve-original-image-on-fail']) ? ($_GET['serve-original-image-on-fail'] != 'no') : TRUE);
WebPConvert::set_preferred_converters($preferred_converters);
WebPConvert::convert($source, $destination, $quality, $strip_metadata);


?>
